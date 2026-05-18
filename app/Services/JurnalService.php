<?php
namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Service untuk membuat jurnal akuntansi.
 * Semua modul (tebusan, distribusi, brimola, dll) panggil service ini.
 * Jurnal otomatis bisa di-override manual dari halaman buku besar.
 */
class JurnalService
{
    // ── Generate nomor jurnal otomatis ───────────────────────────
    public function noJurnal(string $modul): string
    {
        $prefix = match($modul) {
            'modal_masuk'   => 'MDL',
            'prive'         => 'PRV',
            'utang_pemilik' => 'UTP',
            'tebusan'       => 'TBS',
            'distribusi'    => 'DST',
            'brimola'       => 'BRM',
            'kerjasama'     => 'KRJ',
            'kas_kecil'     => 'KAS',
            default         => 'JRN',
        };
        $bulan  = now()->format('Ym');
        $urutan = DB::table('jurnal_headers')
            ->where('no_jurnal', 'like', "{$prefix}-{$bulan}-%")
            ->count() + 1;
        return "{$prefix}-{$bulan}-" . str_pad($urutan, 4, '0', STR_PAD_LEFT);
    }

    // ── Buat jurnal dengan detail debit/kredit ────────────────────
    public function buat(array $header, array $details): int
    {
        return DB::transaction(function () use ($header, $details) {
            // Validasi balance debit = kredit
            $totalDebit  = collect($details)->where('posisi','debit')->sum('jumlah');
            $totalKredit = collect($details)->where('posisi','kredit')->sum('jumlah');
            if ($totalDebit !== $totalKredit) {
                throw new \Exception(
                    "Jurnal tidak balance: Debit Rp ".number_format($totalDebit).
                    " ≠ Kredit Rp ".number_format($totalKredit)
                );
            }

            $jurnalId = DB::table('jurnal_headers')->insertGetId([
                'no_jurnal'        => $header['no_jurnal']   ?? $this->noJurnal($header['modul'] ?? 'penyesuaian'),
                'tanggal'          => $header['tanggal']     ?? now()->toDateString(),
                'keterangan'       => $header['keterangan']  ?? '',
                'modul'            => $header['modul']       ?? 'penyesuaian',
                'referensi'        => $header['referensi']   ?? null,
                'referensi_id'     => $header['referensi_id']?? null,
                'is_otomatis'      => $header['is_otomatis'] ?? false,
                'dibuat_oleh'      => Auth::id(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($details as $d) {
                DB::table('jurnal_details')->insert([
                    'jurnal_id'  => $jurnalId,
                    'kode_akun'  => $d['kode_akun'],
                    'posisi'     => $d['posisi'],
                    'jumlah'     => $d['jumlah'],
                    'keterangan' => $d['keterangan'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return $jurnalId;
        });
    }

    // ── Jurnal Modal Masuk ────────────────────────────────────────
    public function modalMasuk(Carbon $tanggal, int $jumlah,
                               string $akun_kas, string $tipe,
                               string $ket = ''): int
    {
        // $tipe = 'modal_disetor' atau 'pinjaman_pemilik'
        $kredit = $tipe === 'modal_disetor' ? '3001' : '2003';
        $modul  = $tipe === 'modal_disetor' ? 'modal_masuk' : 'utang_pemilik';
        $label  = $tipe === 'modal_disetor' ? 'Modal disetor pemilik' : 'Pinjaman dari pemilik';

        return $this->buat([
            'tanggal'    => $tanggal->toDateString(),
            'modul'      => $modul,
            'keterangan' => $ket ?: $label,
            'is_otomatis'=> false,
        ], [
            ['kode_akun' => $akun_kas, 'posisi' => 'debit',  'jumlah' => $jumlah],
            ['kode_akun' => $kredit,   'posisi' => 'kredit', 'jumlah' => $jumlah],
        ]);
    }

    // ── Jurnal Prive / Penarikan Pemilik ─────────────────────────
    public function prive(Carbon $tanggal, int $jumlah,
                          string $akun_kas, string $ket = ''): int
    {
        return $this->buat([
            'tanggal'    => $tanggal->toDateString(),
            'modul'      => 'prive',
            'keterangan' => $ket ?: 'Penarikan oleh pemilik',
            'is_otomatis'=> false,
        ], [
            ['kode_akun' => '3002',   'posisi' => 'debit',  'jumlah' => $jumlah],
            ['kode_akun' => $akun_kas,'posisi' => 'kredit', 'jumlah' => $jumlah],
        ]);
    }

    // ── Jurnal Tebusan Dibayar ────────────────────────────────────
    public function tebusan(Carbon $tanggal, int $jumlah,
                            string $noSa, int $refId): int
    {
        return $this->buat([
            'tanggal'     => $tanggal->toDateString(),
            'modul'       => 'tebusan',
            'keterangan'  => "Bayar tebusan SA#{$noSa}",
            'referensi'   => $noSa,
            'referensi_id'=> $refId,
            'is_otomatis' => true,
        ], [
            ['kode_akun' => '2001', 'posisi' => 'debit',  'jumlah' => $jumlah, 'keterangan' => "SA#{$noSa}"],
            ['kode_akun' => '1002', 'posisi' => 'kredit', 'jumlah' => $jumlah],
        ]);
    }

    // ── Jurnal Distribusi Selesai ─────────────────────────────────
    public function distribusi(Carbon $tanggal, int $qtyTabung,
                               int $hargaJual, int $hargaTebus,
                               string $noSj, int $refId): int
    {
        $nilaiJual  = $qtyTabung * $hargaJual;
        $nilaiTebus = $qtyTabung * $hargaTebus;

        // Debit Piutang Dagang, Kredit Pendapatan
        // Debit HPP, Kredit Persediaan
        return $this->buat([
            'tanggal'     => $tanggal->toDateString(),
            'modul'       => 'distribusi',
            'keterangan'  => "Distribusi refil SJ#{$noSj} - {$qtyTabung} tabung",
            'referensi'   => $noSj,
            'referensi_id'=> $refId,
            'is_otomatis' => true,
        ], [
            ['kode_akun' => '1003', 'posisi' => 'debit',  'jumlah' => $nilaiJual,  'keterangan' => 'Piutang penjualan refil'],
            ['kode_akun' => '4001', 'posisi' => 'kredit', 'jumlah' => $nilaiJual,  'keterangan' => "SJ#{$noSj}"],
            ['kode_akun' => '5001', 'posisi' => 'debit',  'jumlah' => $nilaiTebus, 'keterangan' => 'HPP tebusan'],
            ['kode_akun' => '1005', 'posisi' => 'kredit', 'jumlah' => $nilaiTebus, 'keterangan' => 'Keluar persediaan'],
        ]);
    }

    // ── Jurnal BRImola Masuk ──────────────────────────────────────
    public function brimola(Carbon $tanggal, int $jumlah,
                            string $noBriva, int $refId,
                            int $sisaKredit = 0): int
    {
        $details = [
            ['kode_akun' => '1002', 'posisi' => 'debit',  'jumlah' => $jumlah, 'keterangan' => "BRIVA {$noBriva}"],
        ];

        if ($sisaKredit > 0) {
            // Bayar lebih — sisa masuk ke titipan pangkalan
            $lunas = $jumlah - $sisaKredit;
            if ($lunas > 0) {
                $details[] = ['kode_akun' => '1003', 'posisi' => 'kredit', 'jumlah' => $lunas,       'keterangan' => 'Lunasi piutang'];
            }
            $details[] = ['kode_akun' => '2002', 'posisi' => 'kredit', 'jumlah' => $sisaKredit, 'keterangan' => 'Titipan saldo kredit'];
        } else {
            $details[] = ['kode_akun' => '1003', 'posisi' => 'kredit', 'jumlah' => $jumlah, 'keterangan' => 'Lunasi piutang dagang'];
        }

        return $this->buat([
            'tanggal'     => $tanggal->toDateString(),
            'modul'       => 'brimola',
            'keterangan'  => "Pembayaran BRImola {$noBriva}",
            'referensi'   => $noBriva,
            'referensi_id'=> $refId,
            'is_otomatis' => true,
        ], $details);
    }

    // ── Jurnal Penerimaan Kerjasama ───────────────────────────────
    public function kerjasama(Carbon $tanggal, int $jumlah,
                              string $akun_kas, string $ket,
                              int $refId): int
    {
        return $this->buat([
            'tanggal'     => $tanggal->toDateString(),
            'modul'       => 'kerjasama',
            'keterangan'  => $ket,
            'referensi_id'=> $refId,
            'is_otomatis' => true,
        ], [
            ['kode_akun' => $akun_kas, 'posisi' => 'debit',  'jumlah' => $jumlah],
            ['kode_akun' => '4002',    'posisi' => 'kredit', 'jumlah' => $jumlah],
        ]);
    }

    // ── Jurnal Kas Kecil Keluar ───────────────────────────────────
    public function kasKecil(Carbon $tanggal, int $jumlah,
                             string $kode_beban, string $ket,
                             int $refId): int
    {
        return $this->buat([
            'tanggal'     => $tanggal->toDateString(),
            'modul'       => 'kas_kecil',
            'keterangan'  => $ket,
            'referensi_id'=> $refId,
            'is_otomatis' => true,
        ], [
            ['kode_akun' => $kode_beban, 'posisi' => 'debit',  'jumlah' => $jumlah],
            ['kode_akun' => '1001',      'posisi' => 'kredit', 'jumlah' => $jumlah],
        ]);
    }

    // ── Hitung saldo akun per tanggal ─────────────────────────────
    public function saldoAkun(string $kodeAkun, ?Carbon $sampai = null): int
    {
        $sampai ??= now();

        // Saldo awal (terakhir sebelum tanggal)
        $saldoAwal = DB::table('saldo_awal_akun')
            ->where('kode_akun', $kodeAkun)
            ->where('per_tanggal', '<=', $sampai->toDateString())
            ->orderByDesc('per_tanggal')
            ->value('saldo') ?? 0;

        // Mutasi dari jurnal
        $debit  = DB::table('jurnal_details as jd')
            ->join('jurnal_headers as jh', 'jd.jurnal_id', '=', 'jh.id')
            ->where('jd.kode_akun', $kodeAkun)
            ->where('jd.posisi', 'debit')
            ->where('jh.tanggal', '<=', $sampai->toDateString())
            ->sum('jd.jumlah');

        $kredit = DB::table('jurnal_details as jd')
            ->join('jurnal_headers as jh', 'jd.jurnal_id', '=', 'jh.id')
            ->where('jd.kode_akun', $kodeAkun)
            ->where('jd.posisi', 'kredit')
            ->where('jh.tanggal', '<=', $sampai->toDateString())
            ->sum('jd.jumlah');

        // Akun aset & beban: saldo normal debit
        $akun = DB::table('akun_keuangan')->where('kode', $kodeAkun)->first();
        if (!$akun) return 0;

        if ($akun->posisi_normal === 'debit') {
            return (int)($saldoAwal + $debit - $kredit);
        } else {
            return (int)($saldoAwal + $kredit - $debit);
        }
    }
}
