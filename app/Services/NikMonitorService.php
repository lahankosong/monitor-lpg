<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class NikMonitorService
{
    // Jam operasional normal (WIB = UTC+7)
    const JAM_BUKA  = '05:30';
    const JAM_TUTUP = '20:00';

    // ── Analisis satu individu ────────────────────────────────────

    public function analyzeIndividu(Collection $txns, int $minInterval = 7): array
    {
        $sorted = $txns->sortBy('transaction_at')->values();
        if ($sorted->isEmpty()) return [];

        $kategori    = strtolower($sorted->first()->category ?? 'rumah tangga');
        $violations  = [];
        $warnings    = [];

        // 1. Cek transaksi di luar jam normal
        foreach ($sorted as $tx) {
            $jam = Carbon::parse($tx->transaction_at)->setTimezone('Asia/Jakarta');
            $jamStr = $jam->format('H:i');

            if ($jamStr < self::JAM_BUKA || $jamStr > self::JAM_TUTUP) {
                $warnings[] = [
                    'type'    => 'jam_abnormal',
                    'level'   => 'warn',
                    'tanggal' => $jam->format('d/m/Y H:i'),
                    'pesan'   => "Transaksi di luar jam normal ({$jamStr} WIB, normal 05:30-20:00)",
                ];
            }
        }

        // 2. Hitung transaksi masif dalam satu hari
        $perHari = $sorted->groupBy(fn($t) => Carbon::parse($t->transaction_at)->toDateString());
        foreach ($perHari as $tgl => $txHari) {
            $totalHari = $txHari->count();
            if ($totalHari > 5) {
                // Cek apakah di jam normal
                $adaDiLuarJam = $txHari->filter(function ($tx) {
                    $jam = Carbon::parse($tx->transaction_at)->setTimezone('Asia/Jakarta')->format('H:i');
                    return $jam < self::JAM_BUKA || $jam > self::JAM_TUTUP;
                })->isNotEmpty();

                if ($adaDiLuarJam) {
                    $warnings[] = [
                        'type'    => 'masif_luar_jam',
                        'level'   => 'alert',
                        'tanggal' => $tgl,
                        'pesan'   => "{$totalHari} transaksi masif di luar jam normal pada {$tgl}",
                    ];
                }
            }
        }

        // 3. Analisis berdasarkan tipe konsumen
        if (str_contains($kategori, 'rumah tangga')) {
            $violations = array_merge($violations, $this->checkRumahTangga($sorted));
        } elseif (str_contains($kategori, 'usaha mikro') || str_contains($kategori, 'mikro')) {
            $violations = array_merge($violations, $this->checkUsahaMikro($sorted));
        } elseif (str_contains($kategori, 'pengecer') || str_contains($kategori, 'sub pangkalan')) {
            // Pengecer dicek di level pangkalan, bukan individu
        }

        // Hitung gap antar transaksi
        $gaps = [];
        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = Carbon::parse($sorted[$i-1]->transaction_at);
            $curr = Carbon::parse($sorted[$i]->transaction_at);
            $gaps[] = $prev->diffInDays($curr);
        }

        $avgGap = count($gaps) ? round(array_sum($gaps) / count($gaps), 1) : null;

        // Status keseluruhan
        $hasAlert = ! empty(array_filter($violations, fn($v) => $v['level'] === 'alert'))
                 || ! empty(array_filter($warnings,   fn($w) => $w['level'] === 'alert'));
        $hasWarn  = ! empty($violations) || ! empty($warnings);

        $status = 'aman';
        if ($sorted->count() === 1) $status = 'new';
        elseif ($hasAlert) $status = 'alert';
        elseif ($hasWarn)  $status = 'warn';

        // Aset tabung (max sekali transaksi = kepemilikan)
        $maxTabungSekali = $sorted->max('total');

        return [
            'nik'              => $sorted->first()->nationality_id,
            'nama'             => $sorted->first()->name,
            'kategori'         => $sorted->first()->category ?? 'Rumah Tangga',
            'pangkalan_id'     => $sorted->first()->pangkalan_id,
            'store_name'       => $sorted->first()->store_name,
            'total_txn'        => $sorted->count(),
            'total_tabung'     => $sorted->sum('total'),
            'max_tabung_sekali'=> $maxTabungSekali,
            'tgl_pertama'      => $sorted->first()->transaction_date,
            'tgl_terakhir'     => $sorted->last()->transaction_date,
            'avg_gap'          => $avgGap,
            'violations'       => $violations,
            'warnings'         => $warnings,
            'all_alerts'       => array_merge($violations, $warnings),
            'status'           => $status,
            // Map ke array plain agar Js::from() bisa serialize ke JSON
            'txns'             => $sorted->map(fn($t) => [
                'transaction_at'  => (string) $t->transaction_at,
                'transaction_date'=> (string) $t->transaction_date,
                'total'           => $t->total,
                'category'        => $t->category ?? 'Rumah Tangga',
                'store_name'      => $t->store_name ?? '',
                'customer_report_id' => $t->customer_report_id ?? '',
            ])->values()->toArray(),
        ];
    }

    // ── Rumah Tangga ──────────────────────────────────────────────

    private function checkRumahTangga(Collection $txns): array
    {
        $violations = [];
        $sorted     = $txns->sortBy('transaction_at')->values();

        foreach ($sorted as $tx) {
            // Tidak boleh lebih dari 1 tabung per transaksi
            if ($tx->total > 1) {
                $violations[] = [
                    'type'    => 'rt_lebih_1_tabung',
                    'level'   => 'alert',
                    'tanggal' => Carbon::parse($tx->transaction_at)->format('d/m/Y H:i'),
                    'pesan'   => "Rumah Tangga beli {$tx->total} tabung sekaligus (maks 1 tabung/transaksi)",
                ];
            }
        }

        // Cek interval (normal 5-14 hari)
        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = Carbon::parse($sorted[$i-1]->transaction_at);
            $curr = Carbon::parse($sorted[$i]->transaction_at);
            $gap  = $prev->diffInDays($curr);

            if ($gap < 5) {
                $violations[] = [
                    'type'    => 'rt_interval_pendek',
                    'level'   => 'alert',
                    'tanggal' => $curr->format('d/m/Y'),
                    'pesan'   => "Interval hanya {$gap} hari (Rumah Tangga: normal 5-14 hari)",
                    'gap'     => $gap,
                ];
            } elseif ($gap < 7) {
                $violations[] = [
                    'type'    => 'rt_interval_dekat',
                    'level'   => 'warn',
                    'tanggal' => $curr->format('d/m/Y'),
                    'pesan'   => "Interval {$gap} hari (Rumah Tangga: disarankan minimal 7 hari)",
                    'gap'     => $gap,
                ];
            }
        }

        return $violations;
    }

    // ── Usaha Mikro ───────────────────────────────────────────────

    private function checkUsahaMikro(Collection $txns): array
    {
        $violations = [];
        $sorted     = $txns->sortBy('transaction_at')->values();

        /**
         * Aturan Usaha Mikro:
         * - Aset = max tabung sekali beli (kepemilikan tabung)
         * - Setelah beli N tabung:
         *   - 2 hari kemudian: boleh 1 tabung
         *   - 3 hari kemudian: boleh 2 tabung
         *   - 4 hari kemudian: boleh 3 tabung
         *   - 5 hari kemudian: boleh N tabung (kembali normal)
         */
        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev     = $sorted[$i - 1];
            $curr     = $sorted[$i];
            $prevDate = Carbon::parse($prev->transaction_at);
            $currDate = Carbon::parse($curr->transaction_at);
            $gap      = $prevDate->diffInDays($currDate);
            $prevQty  = $prev->total;
            $currQty  = $curr->total;

            // Tentukan batas tabung berdasarkan gap setelah transaksi sebelumnya
            $maxDibolehkan = $this->maxTabungUsahaMikro($prevQty, $gap);

            if ($maxDibolehkan === 0) {
                $violations[] = [
                    'type'    => 'um_terlalu_cepat',
                    'level'   => 'alert',
                    'tanggal' => $currDate->format('d/m/Y'),
                    'pesan'   => "Terlalu cepat! Beli {$prevQty} tabung pada "
                               . $prevDate->format('d/m') . ", belum boleh beli lagi (jarak {$gap} hari)",
                    'gap'     => $gap,
                ];
            } elseif ($currQty > $maxDibolehkan) {
                $violations[] = [
                    'type'    => 'um_melebihi_kuota',
                    'level'   => $gap < 3 ? 'alert' : 'warn',
                    'tanggal' => $currDate->format('d/m/Y'),
                    'pesan'   => "Beli {$currQty} tabung, seharusnya maks {$maxDibolehkan} tabung "
                               . "(jarak {$gap} hari dari transaksi {$prevQty} tabung)",
                    'gap'     => $gap,
                    'max_boleh' => $maxDibolehkan,
                ];
            }
        }

        return $violations;
    }

    /**
     * Hitung max tabung yang dibolehkan untuk Usaha Mikro
     * berdasarkan jumlah tabung transaksi sebelumnya dan jarak hari.
     */
    private function maxTabungUsahaMikro(int $prevQty, int $gap): int
    {
        // Belum boleh beli sama sekali (< 2 hari)
        if ($gap < 2) return 0;

        // Gap 2 hari: boleh 1 tabung
        if ($gap === 2) return 1;

        // Gap 3 hari: boleh 2 tabung
        if ($gap === 3) return 2;

        // Gap 4 hari: boleh 3 tabung
        if ($gap === 4) return 3;

        // Gap >= 5 hari: kembali normal sesuai kepemilikan (prevQty)
        // Maksimum umum adalah 4 tabung sesuai aturan
        return max($prevQty, 4);
    }

    // ── Cek Pengecer/Sub Pangkalan ───────────────────────────────

    public function checkPengecer(Collection $semuaTxns): array
    {
        $warnings = [];

        // Total tabung semua transaksi di pangkalan ini
        $totalTabungPangkalan = $semuaTxns->sum('total');
        if ($totalTabungPangkalan === 0) return [];

        // Group per konsumen pengecer
        $pengecer = $semuaTxns->filter(function ($tx) {
            $kat = strtolower($tx->category ?? '');
            return str_contains($kat, 'pengecer') || str_contains($kat, 'sub pangkalan');
        })->groupBy(fn($t) => $t->nationality_id . '||' . $t->name);

        foreach ($pengecer as $key => $txns) {
            $totalPengecer = $txns->sum('total');
            $persen        = round($totalPengecer / $totalTabungPangkalan * 100, 1);

            if ($persen > 10) {
                $nama = $txns->first()->name;
                $warnings[] = [
                    'type'    => 'pengecer_melebihi_10persen',
                    'level'   => 'alert',
                    'nama'    => $nama,
                    'nik'     => $txns->first()->nationality_id,
                    'pesan'   => "{$nama} (Pengecer) mengambil {$totalPengecer} tabung ({$persen}% dari total pangkalan, maks 10%)",
                    'persen'  => $persen,
                ];
            }
        }

        return $warnings;
    }

    // ── Hitung status ringkasan ───────────────────────────────────

    public function nikStatusSummary(Collection $groups): array
    {
        $counts = ['aman' => 0, 'warn' => 0, 'alert' => 0, 'new' => 0];
        foreach ($groups as $result) {
            $counts[$result['status']]++;
        }
        return $counts;
    }
}