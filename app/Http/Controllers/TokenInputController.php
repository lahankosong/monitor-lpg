<?php

namespace App\Http\Controllers;

use App\Models\PangkalanToken;
use App\Models\ScrapeLog;
use App\Jobs\ScrapeTransactionsJob;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TokenInputController extends Controller
{
    /**
     * Tampilkan halaman input token.
     */
    public function index()
    {
        $pangkalans = PangkalanToken::where('is_active', true)
            ->orderByDesc('updated_at')
            ->get()
            ->each(function ($p) {
                $p->lastLog = ScrapeLog::where('pangkalan_id', $p->pangkalan_id)
                    ->latest('scraped_at')->first();
            });

        return view('dashboard.token', compact('pangkalans'));
    }

    /**
     * Simpan token + langsung scrape (sinkron).
     */
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'from'  => 'required|date',
            'to'    => 'required|date|after_or_equal:from',
        ]);

        // Bersihkan token
        $token = trim(str_replace('Bearer ', '', $request->token));

        // Decode JWT
        $pangkalanId = null;
        $expiredAt   = null;
        $issuedAt    = null;

        try {
            $parts   = explode('.', $token);
            $payload = json_decode(base64_decode(
                str_pad(strtr($parts[1], '-_', '+/'),
                strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
            ), true);

            $pangkalanId = $payload['sub'] ?? null;
            $expiredAt   = isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null;
            $issuedAt    = isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat']) : null;
        } catch (\Exception $e) {
            return back()->withErrors(['token' => 'Format token tidak valid. Pastikan copy nilai Authorization lengkap.']);
        }

        if (! $pangkalanId) {
            return back()->withErrors(['token' => 'Pangkalan ID tidak ditemukan dalam token.']);
        }

        // Cek apakah token sudah expired
        if ($expiredAt && $expiredAt->isPast()) {
            return back()->withErrors([
                'token' => "Token sudah expired sejak {$expiredAt->format('H:i')}. Silakan ambil token baru dari browser."
            ]);
        }

        // Simpan token
        $record = PangkalanToken::updateOrCreate(
            ['pangkalan_id' => $pangkalanId],
            [
                'label'            => $request->label ?: null,
                'token'            => $token,
                'token_issued_at'  => $issuedAt ?? now(),
                'token_expires_at' => $expiredAt,
                'is_active'        => true,
            ]
        );

        $label = $record->label ?: substr($pangkalanId, 0, 8) . '...';

        // Langsung scrape (sinkron — tidak lewat queue)
        $saved = 0;
        $error = null;

        try {
            $job = new ScrapeTransactionsJob($request->from, $request->to, $pangkalanId);
            $job->handle();

            $lastLog = ScrapeLog::where('pangkalan_id', $pangkalanId)
                ->latest('scraped_at')->first();
            $saved = $lastLog?->records_saved ?? 0;

        } catch (\Exception $e) {
            $error = $e->getMessage();
            \Illuminate\Support\Facades\Log::error('[TokenInput] Scrape gagal: ' . $e->getMessage());
        }

        return back()->with('scrape_result', [
            'success' => $error === null,
            'label'   => $label,
            'saved'   => $saved,
            'from'    => $request->from,
            'to'      => $request->to,
            'error'   => $error,
        ]);
    }

    /**
     * Scrape ulang pangkalan yang tokennya masih aktif.
     */
    public function rescrape(Request $request)
    {
        $request->validate([
            'pangkalan_id' => 'required|string',
            'from'         => 'required|date',
            'to'           => 'required|date',
        ]);

        $record = PangkalanToken::where('pangkalan_id', $request->pangkalan_id)
            ->where('is_active', true)->first();

        if (! $record) {
            return back()->withErrors(['msg' => 'Token tidak ditemukan.']);
        }

        if ($record->token_expires_at && $record->token_expires_at->isPast()) {
            return back()->withErrors(['msg' => 'Token sudah expired. Input token baru.']);
        }

        try {
            $job = new ScrapeTransactionsJob($request->from, $request->to, $request->pangkalan_id);
            $job->handle();

            $lastLog = ScrapeLog::where('pangkalan_id', $request->pangkalan_id)
                ->latest('scraped_at')->first();

            return back()->with('scrape_result', [
                'success' => true,
                'label'   => $record->label ?: $request->pangkalan_id,
                'saved'   => $lastLog?->records_saved ?? 0,
                'from'    => $request->from,
                'to'      => $request->to,
                'error'   => null,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => 'Scrape gagal: ' . $e->getMessage()]);
        }
    }
}
