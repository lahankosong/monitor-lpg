<?php

namespace App\Http\Controllers;

use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use App\Models\ScrapeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class AkunPangkalanController extends Controller
{
    private string $pythonPath;
    private string $scriptsPath;
    private string $accountsPath;

    public function __construct()
    {
        $this->pythonPath   = env('PYTHON_PATH', 'python');
        $this->scriptsPath  = base_path('scripts');
        $this->accountsPath = base_path('scripts/accounts.json');
    }

    // ── CRUD Akun ─────────────────────────────────────────────────

    /**
     * Halaman utama — daftar akun + tombol scrape
     */
    public function index()
    {
        // Deduplikasi per username — prioritaskan UUID asli (bukan pending_)
        // Jika ada 2 baris (pending_ dan UUID), pakai yang UUID
        $raw = PangkalanSession::orderBy('label')->get()
            ->groupBy('username')
            ->map(function ($group) {
                // Pilih baris UUID asli jika ada, fallback ke pending_
                return $group->first(fn($s) => !str_starts_with($s->pangkalan_id, 'pending_'))
                    ?? $group->first();
            });

        $akuns = $raw->map(function ($s) {
            $token   = PangkalanToken::where('pangkalan_id', $s->pangkalan_id)->first();
            $lastLog = ScrapeLog::where('pangkalan_id', $s->pangkalan_id)
                ->latest('scraped_at')->first();

            return [
                'id'             => $s->id,
                'pangkalan_id'   => $s->pangkalan_id,
                'label'          => $s->label,
                'username'       => $s->username,
                'registration_id'=> $s->registration_id,
                'has_password'   => !empty($s->password_encrypted),
                'token_valid'    => $token?->token_expires_at?->isFuture() ?? false,
                'token_expires'  => $token?->token_expires_at?->format('H:i d/m'),
                'last_scrape'    => $lastLog?->scraped_at?->format('d/m/Y H:i'),
                'last_status'    => $lastLog?->status,
                'last_saved'     => $lastLog?->records_saved ?? 0,
                'is_active'      => $s->is_active,
                'is_pending'     => str_starts_with($s->pangkalan_id, 'pending_'),
            ];
        })->sortBy('label')->values();

        $isRunning  = Cache::get('batch_scrape_running', false);
        $lastResult = Cache::get('batch_scrape_last_result');
        $progress   = Cache::get('batch_scrape_progress', []);

        return view('dashboard.akun-pangkalan', compact(
            'akuns', 'isRunning', 'lastResult', 'progress'
        ));
    }

    /**
     * Simpan akun baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'label'    => 'required|string|max:100',
            'username' => 'required|string|max:100|unique:pangkalan_sessions,username',
            'password' => 'required|string|min:4',
        ]);

        PangkalanSession::create([
            'pangkalan_id'       => 'pending_' . md5($request->username . time()),
            'label'              => $request->label,
            'username'           => $request->username,
            'password_encrypted' => Crypt::encryptString($request->password),
            'is_active'          => true,
        ]);

        // Sync ke accounts.json
        $this->syncAccountsJson();

        return back()->with('success', "Akun '{$request->label}' berhasil ditambahkan.");
    }

    /**
     * Form edit akun
     */
    public function edit(int $id)
    {
        $akun = PangkalanSession::findOrFail($id);
        return view('dashboard.akun-edit', compact('akun'));
    }

    /**
     * Update akun
     */
    public function update(Request $request, int $id)
    {
        $akun = PangkalanSession::findOrFail($id);

        $request->validate([
            'label'    => 'required|string|max:100',
            'username' => 'required|string|max:100|unique:pangkalan_sessions,username,' . $id,
            'password' => 'nullable|string|min:4',
        ]);

        $data = [
            'label'     => $request->label,
            'username'  => $request->username,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password_encrypted'] = Crypt::encryptString($request->password);
        }

        $akun->update($data);

        // Update label di token juga
        PangkalanToken::where('pangkalan_id', $akun->pangkalan_id)
            ->update(['label' => $request->label]);

        // Sync ke accounts.json
        $this->syncAccountsJson();

        return redirect()->route('dashboard.akun.index')
            ->with('success', "Akun '{$request->label}' berhasil diperbarui.");
    }

    /**
     * Hapus akun
     */
    public function destroy(int $id)
    {
        $akun = PangkalanSession::findOrFail($id);
        $label = $akun->label;

        PangkalanToken::where('pangkalan_id', $akun->pangkalan_id)->delete();
        ScrapeLog::where('pangkalan_id', $akun->pangkalan_id)->delete();
        $akun->delete();

        $this->syncAccountsJson();

        return back()->with('success', "Akun '{$label}' berhasil dihapus.");
    }

    /**
     * Toggle aktif/nonaktif
     */
    public function toggleActive(int $id)
    {
        $akun = PangkalanSession::findOrFail($id);
        $akun->update(['is_active' => ! $akun->is_active]);
        $this->syncAccountsJson();

        $status = $akun->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Akun '{$akun->label}' {$status}.");
    }

    // ── Scraping ──────────────────────────────────────────────────

    /**
     * Scrape SEMUA akun aktif — satu klik dari dashboard
     * Jalankan sebagai background process agar tidak timeout
     */
    public function scrapeAll(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        if (Cache::get('batch_scrape_running')) {
            return back()->withErrors(['msg' => 'Proses sedang berjalan. Tunggu selesai.']);
        }

        // Sync accounts.json dulu
        $this->syncAccountsJson();

        $accounts = $this->loadAccounts();
        if (empty($accounts)) {
            return back()->withErrors(['msg' => 'Tidak ada akun aktif untuk di-scrape.']);
        }

        // Tandai running
        Cache::put('batch_scrape_running', true, now()->addHours(2));
        Cache::forget('batch_scrape_last_result');
        Cache::forget('batch_scrape_progress');

        // Jalankan Artisan command sebagai background process
        $artisan = base_path('artisan');
        $cmd = sprintf(
            'start /B php %s batch:scrape --from=%s --to=%s',
            escapeshellarg($artisan),
            escapeshellarg($request->from),
            escapeshellarg($request->to)
        );

        pclose(popen($cmd, 'r'));

        Log::info("[AkunPangkalan] Batch scrape dimulai background: {$request->from} s/d {$request->to}");

        return redirect()->route('dashboard.akun.index')
            ->with('success', "Batch scraping dimulai untuk {$request->from} s/d {$request->to}. Halaman akan update otomatis.");
    }

    /**
     * Scrape SATU akun — dipanggil via AJAX, return JSON progress
     */
    public function scrapeOne(Request $request, int $id)
    {
        $akun = PangkalanSession::findOrFail($id);
        $from = $request->input('from', now()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        // ── MODE 1: Pakai token tersimpan (ringan, hosting-friendly) ─
        $token = \App\Models\PangkalanToken::where('pangkalan_id', $akun->pangkalan_id)
            ->where('is_active', true)
            ->where('token_expires_at', '>', now()->utc())
            ->whereNotNull('token')
            ->first();

        if ($token) {
            return $this->scrapeWithToken($token, $akun->label, $from, $to);
        }

        // ── MODE 2: Pakai Python Playwright (lokal/VPS saja) ─────────
        $scriptPath = $this->scriptsPath . '/auto_login_batch.py';
        $hasPython  = file_exists($scriptPath);
        $isWindows  = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if (!$hasPython) {
            // Tidak ada Python — trigger GitHub Actions untuk login akun ini
            return $this->triggerGithubActions($akun->username, $akun->label, $from, $to);
        }

        // Ada Python — jalankan (lokal/VPS)
        $password = null;
        if (!empty($akun->password_encrypted)) {
            try {
                $password = \Illuminate\Support\Facades\Crypt::decryptString($akun->password_encrypted);
            } catch (\Exception $e) {
                return response()->json(['success' => false,
                    'message' => 'Password tidak bisa didekripsi. Edit akun dan simpan ulang.']);
            }
        }

        if (!$password) {
            return response()->json(['success' => false,
                'message' => 'Password belum diisi untuk akun ini.']);
        }

        $accounts        = [['label' => $akun->label, 'email' => $akun->username, 'pin' => $password]];
        $tempAccountFile = storage_path('app/temp_account_' . $id . '.json');
        file_put_contents($tempAccountFile, json_encode($accounts, JSON_UNESCAPED_UNICODE));

        $resultFile = storage_path('app/single_result_' . $id . '.json');
        if (file_exists($resultFile)) unlink($resultFile);

        $envPrefix = $isWindows ? 'set PYTHONUNBUFFERED=1 &&' : 'PYTHONUNBUFFERED=1';
        $cmd = sprintf(
            '%s %s %s --accounts %s --from %s --to %s --output %s 2>&1',
            $envPrefix,
            escapeshellcmd($this->pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($tempAccountFile),
            escapeshellarg($from),
            escapeshellarg($to),
            escapeshellarg($resultFile),
        );

        set_time_limit(180);
        ini_set('max_execution_time', 180);
        exec($cmd, $output, $exitCode);

        // Hapus temp file
        if (file_exists($tempAccountFile)) unlink($tempAccountFile);

        if (! file_exists($resultFile)) {
            $outputStr = implode("\n", $output);
            return response()->json([
                'success' => false,
                'message' => 'Script gagal dijalankan. ' . substr(strip_tags($outputStr), 0, 150),
            ]);
        }

        $content = file_get_contents($resultFile);
        $result  = json_decode($content, true);

        if (! $result || empty($result['results'])) {
            return response()->json(['success' => false, 'message' => 'Format hasil tidak valid']);
        }

        $firstResult = $result['results'][0] ?? null;
        if (! $firstResult || ! $firstResult['success']) {
            $err = $firstResult['error'] ?? 'Login gagal (mungkin pangkalan sedang aktif transaksi)';
            return response()->json(['success' => false, 'message' => $err]);
        }

        // Tambah from/to ke setiap result
        foreach ($result['results'] as &$r) {
            $r['from'] = $from;
            $r['to']   = $to;
        }
        unset($r); // Putus reference agar tidak ada side effect

        // Tulis ulang file dengan from/to yang sudah ditambahkan
        // (importResult membaca dari file, bukan dari variable)
        file_put_contents($resultFile, json_encode($result, JSON_UNESCAPED_UNICODE));

        $controller = app(\App\Http\Controllers\BatchScrapeController::class);
        $stats      = $controller->importResult($resultFile);

        return response()->json([
            'success' => true,
            'saved'   => $stats['total_baru'],
            'message' => "{$akun->label}: {$stats['total_baru']} transaksi baru disimpan",
        ]);
    }


    /**
     * Import/sync semua akun dari accounts.json ke database
     * Satu klik — update password semua akun sekaligus
     */
    public function importFromJson()
    {
        if (! file_exists($this->accountsPath)) {
            return back()->withErrors(['msg' => 'accounts.json tidak ditemukan di scripts/']);
        }

        $accounts = json_decode(file_get_contents($this->accountsPath), true);
        if (! $accounts) {
            return back()->withErrors(['msg' => 'accounts.json tidak valid atau kosong']);
        }

        $updated = 0;
        $created = 0;
        $skipped = 0;

        foreach ($accounts as $acc) {
            $email = $acc['email'] ?? '';
            $pin   = $acc['pin']   ?? '';
            $label = $acc['label'] ?? $email;

            if (! $email || ! $pin) {
                $skipped++;
                continue;
            }

            $session = PangkalanSession::where('username', $email)->first();

            if (! $session) {
                PangkalanSession::create([
                    'pangkalan_id'       => 'pending_' . md5($email . time()),
                    'label'              => $label,
                    'username'           => $email,
                    'password_encrypted' => Crypt::encryptString($pin),
                    'is_active'          => true,
                ]);
                $created++;
            } else {
                $session->update([
                    'password_encrypted' => Crypt::encryptString($pin),
                    'label'              => $session->label ?: $label,
                ]);
                $updated++;
            }
        }

        $msg = "Sync selesai: {$updated} diupdate";
        if ($created > 0) $msg .= ", {$created} akun baru dibuat";
        if ($skipped > 0) $msg .= ", {$skipped} dilewati (data tidak lengkap)";

        return back()->with('success', $msg);
    }

    /**
     * Tampilkan password tersimpan untuk verifikasi (hanya di halaman edit)
     */
    public function showPassword(int $id)
    {
        $akun = PangkalanSession::findOrFail($id);
        try {
            $password = \Illuminate\Support\Facades\Crypt::decryptString($akun->password_encrypted);
            return response()->json(['success' => true, 'password' => $password]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Password tidak bisa didekripsi']);
        }
    }


    /**
     * API status polling untuk halaman akun
     */
    public function statusApi()
    {
        // Baca log dari file realtime (lebih reliable dari Cache antar proses)
        $logFile = Cache::get('batch_log_file',
                   storage_path('app/batch_realtime_log.jsonl'));

        $logs = [];
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach (array_slice($lines, -150) as $line) { // ambil 150 baris terakhir
                $decoded = json_decode($line, true);
                if ($decoded) $logs[] = $decoded;
            }
        }

        return response()->json([
            'running'     => Cache::get('batch_scrape_running', false),
            'progress'    => Cache::get('batch_scrape_progress', []),
            'last_result' => Cache::get('batch_scrape_last_result'),
            'logs'        => $logs,
            'log_count'   => count($logs),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Sync database → accounts.json (selalu up to date)
     */
    private function syncAccountsJson(): void
    {
        $accounts = PangkalanSession::where('is_active', true)
            ->orderBy('label')
            ->get()
            ->map(function ($s) {
                $password = null;
                if (! empty($s->password_encrypted)) {
                    try {
                        $password = \Illuminate\Support\Facades\Crypt::decryptString($s->password_encrypted);
                    } catch (\Exception $e) {
                        $password = null;
                    }
                }
                return [
                    'label' => $s->label,
                    'email' => $s->username,
                    'pin'   => $password,
                ];
            })
            ->filter(fn($a) => $a['pin'] !== null)
            ->values()
            ->toArray();

        if (! is_dir($this->scriptsPath)) {
            mkdir($this->scriptsPath, 0755, true);
        }

        file_put_contents(
            $this->accountsPath,
            json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function loadAccounts(): array
    {
        if (! file_exists($this->accountsPath)) return [];
        return json_decode(file_get_contents($this->accountsPath), true) ?? [];
    }

    // ─────────────────────────────────────────────────────────────
    // Trigger GitHub Actions untuk login + scrape satu akun
    // ─────────────────────────────────────────────────────────────
    private function triggerGithubActions(string $email, string $label, string $from, string $to)
    {
        $githubToken = env('GITHUB_PAT', '');
        $githubRepo  = env('GITHUB_REPO', '');  // format: owner/repo

        if (!$githubToken || !$githubRepo) {
            return response()->json([
                'success' => false,
                'message' => '⚠ Token expired & Python tidak tersedia. '.
                             'Set GITHUB_PAT dan GITHUB_REPO di .env untuk trigger otomatis, '.
                             'atau jalankan GitHub Actions manual.',
            ]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "Bearer {$githubToken}",
                'Accept'        => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->post("https://api.github.com/repos/{$githubRepo}/actions/workflows/scrape-tokens.yaml/dispatches", [
                'ref'    => 'main',
                'inputs' => [
                    'date_from'    => $from,
                    'date_to'      => $to,
                    'single_email' => $email,
                ],
            ]);

            if ($response->status() === 204) {
                return response()->json([
                    'success' => true,
                    'message' => "🚀 GitHub Actions dipicu untuk {$label}. ".
                                 "Token akan tersedia ~2 menit. Klik Scrape lagi setelah itu.",
                    'mode'    => 'github_actions',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Gagal trigger GitHub Actions: HTTP {$response->status()} — ".
                             ($response->json('message') ?? 'Unknown error'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error trigger GitHub Actions: {$e->getMessage()}",
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Scrape ringan pakai token tersimpan (tidak butuh Python)
    // ─────────────────────────────────────────────────────────────
    private function scrapeWithToken(
        \App\Models\PangkalanToken $token,
        string $label,
        string $from,
        string $to
    ) {
        $headers = [
            'Authorization' => "Bearer {$token->token}",
            'Accept'        => 'application/json',
            'User-Agent'    => 'Mozilla/5.0',
            'Origin'        => 'https://subsiditepatlpg.mypertamina.id',
            'Referer'       => 'https://subsiditepatlpg.mypertamina.id/',
        ];

        $allCustomers = [];
        $current = \Carbon\Carbon::parse($from);
        $end     = \Carbon\Carbon::parse($to);

        while ($current <= $end) {
            $batchEnd = $current->copy()->addDays(6);
            if ($batchEnd > $end) $batchEnd = $end->copy();

            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                    ->timeout(30)
                    ->get('https://api-map.my-pertamina.id/general/v3/transactions/report', [
                        'search'    => '',
                        'sort'      => 'latest',
                        'startDate' => $current->toDateString(),
                        'endDate'   => $batchEnd->toDateString(),
                    ]);

                if ($response->status() === 401) {
                    $token->update(['is_active' => false]);
                    return response()->json([
                        'success' => false,
                        'message' => '⚠ Token expired. Jalankan GitHub Actions untuk refresh token.',
                    ]);
                }

                $body      = $response->json();
                $customers = $body['data']['customersReport'] ?? [];
                $allCustomers = array_merge($allCustomers, $customers);

            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()]);
            }

            $current = $batchEnd->copy()->addDay();
            usleep(500000);
        }

        // Simpan ke database
        $saved = 0;
        foreach ($allCustomers as $c) {
            $txnId = $c['customerReportId'] ?? null;
            if (!$txnId) continue;
            \Illuminate\Support\Facades\DB::table('transactions')->upsert([
                'pangkalan_id'       => $token->pangkalan_id,
                'customer_report_id' => $txnId,
                'nationality_id'     => $c['nationalityId'] ?? null,
                'name'               => $c['name']          ?? null,
                'categories'         => $c['categories']    ?? null,
                'total'              => $c['total']          ?? 0,
                'created_at'         => $c['createdAt']      ?? now(),
                'transaction_date'   => !empty($c['createdAt'])
                                        ? \Carbon\Carbon::parse($c['createdAt'])->toDateString()
                                        : now()->toDateString(),
                'updated_at'         => now(),
            ], ['customer_report_id'], ['name','total','updated_at']);
            $saved++;
        }

        \App\Models\ScrapeLog::create([
            'pangkalan_id'    => $token->pangkalan_id,
            'start_date'      => $from,
            'end_date'        => $to,
            'records_fetched' => count($allCustomers),
            'records_saved'   => $saved,
            'status'          => 'success',
            'scraped_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "✓ {$label}: {$saved} transaksi tersimpan (⚡ mode ringan)",
        ]);
    }

}