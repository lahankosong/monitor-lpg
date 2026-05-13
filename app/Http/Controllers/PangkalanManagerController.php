<?php

namespace App\Http\Controllers;

use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use App\Models\ScrapeLog;
use App\Jobs\ScrapeTransactionsJob;
use App\Services\AutoLoginService;
use App\Services\PlaywrightLoginService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PangkalanManagerController extends Controller
{
    public function __construct(
        private AutoLoginService      $autoLogin,
        private PlaywrightLoginService $playwright,
    ) {}

    public function index()
    {
        $pangkalans = PangkalanSession::where('is_active', true)
            ->orderBy('label')
            ->get()
            ->map(function ($p) {
                $token   = PangkalanToken::where('pangkalan_id', $p->pangkalan_id)->first();
                $lastLog = ScrapeLog::where('pangkalan_id', $p->pangkalan_id)
                    ->latest('scraped_at')->first();

                return [
                    'id'              => $p->pangkalan_id,
                    'label'           => $p->label ?: 'Pangkalan ' . substr($p->pangkalan_id, 0, 6),
                    'username'        => $p->username,
                    'registration_id' => $p->registration_id,
                    'has_password'    => ! empty($p->password_encrypted),
                    'token_valid'     => $token?->token_expires_at?->isFuture() ?? false,
                    'token_expires'   => $token?->token_expires_at?->format('H:i'),
                    'cookies_fresh'   => $p->isCookiesFresh(),
                    'last_login'      => $p->last_login_at?->diffForHumans() ?? 'Belum pernah',
                    'needs_relogin'   => ! ($token?->token_expires_at?->isFuture() ?? false),
                    'last_scrape'     => $lastLog?->scraped_at?->format('d/m H:i'),
                    'last_status'     => $lastLog?->status,
                    'last_saved'      => $lastLog?->records_saved ?? 0,
                ];
            });

        $siap         = $pangkalans->where('token_valid', true)->count();
        $needsRelogin = $pangkalans->where('needs_relogin', true)->count();
        $total        = $pangkalans->count();

        return view('dashboard.pangkalan-manager', compact(
            'pangkalans', 'siap', 'needsRelogin', 'total'
        ));
    }

    /**
     * Tambah pangkalan baru — simpan credentials saja.
     * Token akan diambil saat pertama kali scrape.
     */
    public function store(Request $request)
    {
        $request->validate([
            'label'    => 'required|string|max:100',
            'username' => 'required|string|max:100',
            'password' => 'required|string',
            'token'    => 'nullable|string',
        ]);

        $pangkalanId = null;
        $storeName   = null;

        // Jika token disertakan — decode untuk ambil pangkalan_id
        if ($request->filled('token')) {
            $token = trim(str_replace('Bearer ', '', $request->token));
            try {
                $parts   = explode('.', $token);
                $payload = json_decode(base64_decode(
                    str_pad(strtr($parts[1], '-_', '+/'),
                    strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
                ), true);
                $pangkalanId = $payload['sub'] ?? null;
                $expiredAt   = isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null;

                // Simpan token jika belum expired
                if ($pangkalanId && $expiredAt?->isFuture()) {
                    PangkalanToken::updateOrCreate(
                        ['pangkalan_id' => $pangkalanId],
                        [
                            'label'            => $request->label,
                            'token'            => $token,
                            'token_expires_at' => $expiredAt,
                            'is_active'        => true,
                        ]
                    );
                }
            } catch (\Exception $e) {
                // Token tidak valid, lanjut tanpa token
            }
        }

        // Kalau belum dapat pangkalan_id, coba auto-login via Playwright sekarang
        if (! $pangkalanId && $this->isPlaywrightAvailable()) {
            $loginResult = $this->playwright->login($request->username, $request->password);
            if ($loginResult) {
                $pangkalanId = $loginResult['pangkalan_id'];
                $storeName   = $loginResult['store_name'];
            }
        }

        // Fallback: gunakan username sebagai temporary ID
        if (! $pangkalanId) {
            $pangkalanId = 'pending_' . md5($request->username);
        }

        PangkalanSession::updateOrCreate(
            ['pangkalan_id' => $pangkalanId],
            [
                'label'              => $request->label ?: $storeName,
                'username'           => $request->username,
                'password_encrypted' => Crypt::encryptString($request->password),
                'is_active'          => true,
                'last_login_at'      => now(),
            ]
        );

        return back()->with('success', "Pangkalan '{$request->label}' berhasil ditambahkan.");
    }

    /**
     * One-click scrape — otomatis login dulu jika token expired.
     */
    public function scrapeOne(Request $request, string $pangkalanId)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $session = PangkalanSession::where('pangkalan_id', $pangkalanId)->first();
        $label   = $session?->label ?? $pangkalanId;

        // Cek token aktif dulu
        $token = $this->autoLogin->getActiveToken($pangkalanId);

        // Token tidak ada/expired → coba auto-login via Playwright
        if (! $token && $session?->password) {
            if ($this->isPlaywrightAvailable()) {
                $token = $this->playwright->loginAndSave($session);
                if ($token) {
                    // Update pangkalan_id jika sebelumnya pending
                    if (str_starts_with($pangkalanId, 'pending_')) {
                        // Ambil pangkalan_id baru dari token yang tersimpan
                        $newToken = PangkalanToken::where('is_active', true)
                            ->latest()->first();
                        if ($newToken) {
                            $pangkalanId = $newToken->pangkalan_id;
                        }
                    }
                }
            }
        }

        if (! $token) {
            return back()->with('warning',
                "⚠ Token '{$label}' expired dan auto-login gagal. " .
                "Pastikan Playwright terinstall atau input token manual."
            );
        }

        try {
            $job = new ScrapeTransactionsJob($request->from, $request->to, $pangkalanId);
            $job->handle();

            $lastLog = ScrapeLog::where('pangkalan_id', $pangkalanId)
                ->latest('scraped_at')->first();

            return back()->with('success',
                "✓ {$label}: {$lastLog?->records_saved} transaksi ({$request->from} s/d {$request->to})"
            );
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => "Scrape gagal: " . $e->getMessage()]);
        }
    }

    /**
     * Scrape semua — auto-login pangkalan yang tokennya expired.
     */
    public function scrapeAll(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $sessions  = PangkalanSession::where('is_active', true)->get();
        $berhasil  = 0;
        $gagal     = 0;
        $autoLogin = 0;

        foreach ($sessions as $session) {
            $token = $this->autoLogin->getActiveToken($session->pangkalan_id);

            // Auto-login jika token expired dan Playwright tersedia
            if (! $token && $session->password && $this->isPlaywrightAvailable()) {
                $token = $this->playwright->loginAndSave($session);
                if ($token) $autoLogin++;
            }

            if (! $token) {
                $gagal++;
                continue;
            }

            try {
                $job = new ScrapeTransactionsJob($request->from, $request->to, $session->pangkalan_id);
                $job->handle();
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
            }
        }

        return back()->with('success',
            "Selesai: {$berhasil} berhasil" .
            ($autoLogin > 0 ? " ({$autoLogin} auto-login)" : "") .
            ($gagal > 0 ? ", {$gagal} gagal" : "")
        );
    }

    public function destroy(string $pangkalanId)
    {
        PangkalanSession::where('pangkalan_id', $pangkalanId)->delete();
        PangkalanToken::where('pangkalan_id', $pangkalanId)->delete();
        return back()->with('success', 'Pangkalan dihapus.');
    }

    private function isPlaywrightAvailable(): bool
    {
        $scriptPath = base_path('scripts/auto_login.py');
        return file_exists($scriptPath);
    }
}
