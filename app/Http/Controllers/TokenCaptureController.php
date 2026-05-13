<?php

namespace App\Http\Controllers;

use App\Models\PangkalanToken;
use App\Models\ScrapeLog;
use App\Jobs\ScrapeTransactionsJob;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TokenCaptureController extends Controller
{
    /**
     * Dipanggil Chrome Extension — tanpa CSRF, dengan CORS header manual.
     * Route ini sudah dikecualikan dari CSRF di bootstrap/app.php.
     */
    public function capture(Request $request)
    {
        // Handle preflight OPTIONS request dari browser/extension
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin',  '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With, X-Extension-Key');
        }

        // Baca JSON body (extension kirim application/json)
        $data = $request->json()->all();

        if (empty($data)) {
            // Fallback: coba baca dari form input
            $data = $request->all();
        }

        $token       = trim(str_replace('Bearer ', '', $data['token'] ?? ''));
        $pangkalanId = $data['pangkalan_id'] ?? null;
        $expAt       = $data['expires_at']   ?? null;
        $issuedAt    = $data['issued_at']    ?? null;

        if (! $token) {
            return $this->corsResponse(['success' => false, 'message' => 'Token kosong'], 400);
        }

        // Kalau pangkalan_id tidak dikirim, decode dari JWT
        if (! $pangkalanId) {
            try {
                $parts   = explode('.', $token);
                $payload = json_decode(base64_decode(
                    str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
                ), true);
                $pangkalanId = $payload['sub']  ?? null;
                $expAt       = $expAt  ?? (isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp'])->toISOString() : null);
                $issuedAt    = $issuedAt ?? (isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat'])->toISOString() : null);
            } catch (\Exception $e) {
                return $this->corsResponse(['success' => false, 'message' => 'Token tidak valid'], 400);
            }
        }

        if (! $pangkalanId) {
            return $this->corsResponse(['success' => false, 'message' => 'Pangkalan ID tidak ditemukan dalam token'], 400);
        }

        $expiredAt = $expAt ? Carbon::parse($expAt) : null;

        // Simpan / update token pangkalan
        $record = PangkalanToken::updateOrCreate(
            ['pangkalan_id' => $pangkalanId],
            [
                'token'            => $token,
                'token_issued_at'  => $issuedAt ? Carbon::parse($issuedAt) : now(),
                'token_expires_at' => $expiredAt,
                'is_active'        => true,
            ]
        );

        $label = $record->label ?: substr($pangkalanId, 0, 8) . '...';

        // Langsung scrape hari ini (sinkron, tidak lewat queue)
        // karena token hanya valid ±15 menit
        $scraped = false;
        $savedCount = 0;
        try {
            $job = new ScrapeTransactionsJob(now()->toDateString(), now()->toDateString(), $pangkalanId);
            $job->handle();

            $lastLog    = ScrapeLog::where('pangkalan_id', $pangkalanId)->latest('scraped_at')->first();
            $savedCount = $lastLog?->records_saved ?? 0;
            $scraped    = true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[TokenCapture] Scrape gagal: ' . $e->getMessage());
        }

        return $this->corsResponse([
            'success'      => true,
            'label'        => $label,
            'pangkalan_id' => $pangkalanId,
            'scraped'      => $scraped,
            'saved_count'  => $savedCount,
            'message'      => $scraped
                ? "Token tersimpan. {$savedCount} transaksi baru diambil."
                : "Token tersimpan. Scrape gagal — cek log Laravel.",
        ]);
    }

    /**
     * Helper: response JSON dengan CORS header.
     */
    private function corsResponse(array $data, int $status = 200)
    {
        return response()->json($data, $status)
            ->header('Access-Control-Allow-Origin',  '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With, X-Extension-Key');
    }

    /**
     * Halaman daftar semua pangkalan.
     */
    public function pangkalanList()
    {
        $pangkalans = PangkalanToken::orderBy('label')->get()->map(function ($p) {
            $lastLog = ScrapeLog::where('pangkalan_id', $p->pangkalan_id)
                ->latest('scraped_at')->first();

            return [
                'id'          => $p->pangkalan_id,
                'label'       => $p->label ?: $p->pangkalan_id,
                'is_active'   => $p->is_active,
                'token_ok'    => $p->token_expires_at && $p->token_expires_at->isFuture(),
                'expires_at'  => $p->token_expires_at?->format('H:i d/m'),
                'last_scrape' => $lastLog?->scraped_at?->format('d/m H:i'),
                'last_status' => $lastLog?->status,
                'last_saved'  => $lastLog?->records_saved ?? 0,
            ];
        });

        $tokenOk    = $pangkalans->where('token_ok', true)->count();
        $totalSaved = ScrapeLog::where('status', 'success')
            ->whereDate('created_at', today())->sum('records_saved');

        return view('dashboard.pangkalan', compact('pangkalans', 'tokenOk', 'totalSaved'));
    }

    /**
     * Update nama/label pangkalan.
     */
    public function updateLabel(Request $request, string $pangkalanId)
    {
        $request->validate(['label' => 'required|string|max:100']);
        PangkalanToken::where('pangkalan_id', $pangkalanId)->update(['label' => $request->label]);
        return back()->with('success', 'Nama pangkalan diperbarui');
    }

    /**
     * Scrape semua pangkalan yang tokennya masih valid.
     */
    public function scrapeAll(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $aktif = PangkalanToken::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('token_expires_at')
                  ->orWhere('token_expires_at', '>', now());
            })->get();

        if ($aktif->isEmpty()) {
            return back()->withErrors(['msg' => 'Tidak ada token aktif. Login ke pangkalan via browser terlebih dahulu.']);
        }

        $berhasil = 0;
        $gagal    = 0;

        foreach ($aktif as $p) {
            try {
                $job = new ScrapeTransactionsJob($request->from, $request->to, $p->pangkalan_id);
                $job->handle();
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                \Illuminate\Support\Facades\Log::error("[ScrapeAll] {$p->pangkalan_id}: " . $e->getMessage());
            }
        }

        return back()->with('success',
            "Scrape selesai: {$berhasil} pangkalan berhasil" .
            ($gagal > 0 ? ", {$gagal} gagal (cek log)" : "") .
            " · Periode: {$request->from} s/d {$request->to}"
        );
    }
}
