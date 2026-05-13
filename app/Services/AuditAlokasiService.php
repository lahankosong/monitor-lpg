<?php

namespace App\Services;

use App\Models\Pangkalan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Service untuk alokasi pembayaran BRImola ke distribusi (FIFO).
 *
 * Cara kerja:
 * 1. Ambil semua distribusi pangkalan, urutkan dari paling lama
 * 2. Ambil semua BRIVA pangkalan (matched), urutkan dari paling lama
 * 3. Iterate distribusi: alokasi qty dari BRIVA tertua yang masih punya sisa
 * 4. Saldo akhir = total BRIVA - total distribusi
 *    > 0 → saldo kredit (uang muka)
 *    < 0 → piutang
 *    = 0 → lunas
 */
class AuditAlokasiService
{
    /** Re-alokasi semua pangkalan (bulk) */
    public function alokasiSemua(): array
    {
        $pangkalans = Pangkalan::all();
        $hasil = ['lunas' => 0, 'kredit' => 0, 'piutang' => 0];

        foreach ($pangkalans as $p) {
            $status = $this->alokasiPangkalan($p->id);
            $hasil[$status] = ($hasil[$status] ?? 0) + 1;
        }
        return $hasil;
    }

    /** Re-alokasi untuk satu pangkalan, return status: 'lunas'|'kredit'|'piutang' */
    public function alokasiPangkalan(int $pangkalanId): string
    {
        return DB::transaction(function () use ($pangkalanId) {
            // 1. Hapus alokasi lama
            DB::table('audit_distribusi_bayar')
                ->where('pangkalan_id', $pangkalanId)->delete();

            // 2. Reset qty_terpakai di BRIVA
            DB::table('brimola_transaksi')
                ->where('pangkalan_id', $pangkalanId)
                ->update(['qty_terpakai' => 0, 'qty_sisa' => DB::raw('jumlah_tabung')]);

            // 3. Ambil semua distribusi pangkalan ini, urut FIFO
            $distribusi = DB::table('surat_jalan_details as d')
                ->join('surat_jalan_headers as h', 'd.header_id', '=', 'h.id')
                ->where('d.pangkalan_id', $pangkalanId)
                ->whereIn('d.status', ['terkirim','sebagian'])
                ->where('d.qty_terima', '>', 0)
                ->select('d.id as sj_detail_id', 'd.qty_terima', 'h.tanggal as tgl_distribusi')
                ->orderBy('h.tanggal')->orderBy('d.id')
                ->get();

            // 4. Ambil semua BRIVA matched pangkalan ini, urut FIFO
            $brivaList = DB::table('brimola_transaksi')
                ->where('pangkalan_id', $pangkalanId)
                ->where('status', '!=', 'unmatched')
                ->orderBy('tanggal_bayar')->orderBy('id')
                ->get()
                ->map(fn($b) => (object)[
                    'id'            => $b->id,
                    'tanggal_bayar' => $b->tanggal_bayar,
                    'sisa'          => $b->jumlah_tabung,
                    'harga'         => $b->harga_per_tabung,
                ])->all();

            // 5. Alokasi FIFO
            foreach ($distribusi as $d) {
                $perlu = $d->qty_terima;

                foreach ($brivaList as &$briva) {
                    if ($perlu <= 0) break;
                    if ($briva->sisa <= 0) continue;

                    $ambil = min($perlu, $briva->sisa);

                    DB::table('audit_distribusi_bayar')->insert([
                        'sj_detail_id'       => $d->sj_detail_id,
                        'brimola_trx_id'     => $briva->id,
                        'pangkalan_id'       => $pangkalanId,
                        'qty_dialokasi'      => $ambil,
                        'tipe_alokasi'       => 'otomatis_fifo',
                        'tanggal_distribusi' => $d->tgl_distribusi,
                        'tanggal_bayar'      => $briva->tanggal_bayar,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    $briva->sisa -= $ambil;
                    $perlu       -= $ambil;
                }
            }
            unset($briva);

            // 6. Update qty_terpakai & qty_sisa di brimola_transaksi
            foreach ($brivaList as $b) {
                DB::table('brimola_transaksi')->where('id', $b->id)->update([
                    'qty_terpakai' => DB::raw("jumlah_tabung - {$b->sisa}"),
                    'qty_sisa'     => $b->sisa,
                ]);
            }

            // 7. Hitung saldo pangkalan
            return $this->hitungSaldo($pangkalanId);
        });
    }

    /** Hitung dan simpan saldo pangkalan, return status */
    public function hitungSaldo(int $pangkalanId): string
    {
        // Total dibayar (semua BRIVA matched)
        $totalBayar = DB::table('brimola_transaksi')
            ->where('pangkalan_id', $pangkalanId)
            ->where('status', '!=', 'unmatched')
            ->sum('jumlah_tabung');

        $totalNilaiBayar = DB::table('brimola_transaksi')
            ->where('pangkalan_id', $pangkalanId)
            ->where('status', '!=', 'unmatched')
            ->sum('total_bayar');

        // Total distribusi
        $totalDist = DB::table('surat_jalan_details as d')
            ->where('d.pangkalan_id', $pangkalanId)
            ->whereIn('d.status', ['terkirim','sebagian'])
            ->sum('d.qty_terima');

        // Ambil harga refil aktif
        $harga = DB::table('harga_referensis')
            ->where('kategori', 'jual_pangkalan')
            ->where('is_active', true)
            ->orderByDesc('berlaku_mulai')
            ->value('harga') ?? 0;

        $totalNilaiDist = $totalDist * $harga;
        $saldoTabung    = $totalBayar - $totalDist;
        $saldoNilai     = $totalNilaiBayar - $totalNilaiDist;

        $status = $saldoTabung > 0 ? 'saldo_kredit'
                : ($saldoTabung < 0 ? 'piutang' : 'lunas');

        DB::table('saldo_pangkalan')->updateOrInsert(
            ['pangkalan_id' => $pangkalanId],
            [
                'total_dibayar'          => $totalBayar,
                'total_didistribusi'     => $totalDist,
                'saldo_tabung'           => $saldoTabung,
                'total_nilai_bayar'      => $totalNilaiBayar,
                'total_nilai_distribusi' => $totalNilaiDist,
                'saldo_nilai'            => $saldoNilai,
                'status'                 => $status,
                'last_calculated_at'     => now(),
                'updated_at'             => now(),
                'created_at'             => now(),
            ]
        );

        return $status === 'saldo_kredit' ? 'kredit' :
               ($status === 'piutang' ? 'piutang' : 'lunas');
    }

    /** Ambil timeline alokasi untuk satu pangkalan (untuk UI) */
    public function timelinePangkalan(int $pangkalanId): array
    {
        // BRIVA + distribusi digabung jadi timeline kronologis
        $briva = DB::table('brimola_transaksi')
            ->where('pangkalan_id', $pangkalanId)
            ->where('status', '!=', 'unmatched')
            ->select(
                'id', 'no_briva', 'tanggal_bayar as tanggal',
                'jumlah_tabung', 'qty_terpakai', 'qty_sisa',
                'total_bayar', DB::raw('"briva" as tipe')
            )
            ->get();

        $dist = DB::table('surat_jalan_details as d')
            ->join('surat_jalan_headers as h', 'd.header_id', '=', 'h.id')
            ->where('d.pangkalan_id', $pangkalanId)
            ->whereIn('d.status', ['terkirim','sebagian'])
            ->select(
                'd.id', 'h.no_sj as no_briva', 'h.tanggal as tanggal',
                'd.qty_terima as jumlah_tabung',
                DB::raw('0 as qty_terpakai'),
                DB::raw('0 as qty_sisa'),
                DB::raw('0 as total_bayar'),
                DB::raw('"distribusi" as tipe')
            )
            ->get();

        $merged = $briva->concat($dist)->sortBy('tanggal')->values();

        // Hitung running balance
        $saldo = 0;
        $result = [];
        foreach ($merged as $row) {
            if ($row->tipe === 'briva') {
                $saldo += $row->jumlah_tabung;
            } else {
                $saldo -= $row->jumlah_tabung;
            }
            $row->saldo_running = $saldo;
            $result[] = $row;
        }

        return $result;
    }

    /** Ambil detail alokasi per distribusi (untuk drill-down) */
    public function alokasiPerDistribusi(int $sjDetailId): array
    {
        return DB::table('audit_distribusi_bayar as a')
            ->join('brimola_transaksi as b', 'a.brimola_trx_id', '=', 'b.id')
            ->where('a.sj_detail_id', $sjDetailId)
            ->select('a.qty_dialokasi','b.no_briva','b.tanggal_bayar','b.jumlah_tabung')
            ->orderBy('b.tanggal_bayar')
            ->get()->toArray();
    }
}
