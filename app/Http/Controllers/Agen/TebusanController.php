<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Kitir;
use App\Models\KitirDetail;
use App\Models\HargaReferensi;
use App\Models\TebusanKitir;
use App\Models\TebusanKitirDetail;
use App\Models\JurnalAkuntansi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TebusanController extends Controller
{
    public function index(Request $request)
    {
        $bulan  = $request->get('bulan', now()->month);
        $tahun  = $request->get('tahun', now()->year);

        $tebusan = TebusanKitir::with(['kitir.spbe', 'details.kitirDetail', 'createdBy'])
            ->whereYear('tanggal_bayar', $tahun)
            ->whereMonth('tanggal_bayar', $bulan)
            ->orderByDesc('tanggal_bayar')
            ->paginate(15)->withQueryString();

        // Summary bulan ini
        $summary = TebusanKitir::whereYear('tanggal_bayar', $tahun)
            ->whereMonth('tanggal_bayar', $bulan)
            ->selectRaw('
                SUM(jumlah_tabung_ditebus) as total_tabung,
                SUM(total_bayar) as total_bayar,
                SUM(total_bayar_aktual) as total_aktual,
                SUM(selisih_pembulatan) as total_selisih
            ')->first();

        $hargaTebus = HargaReferensi::hargaAktif('tebus_refil');
        $kitirAktif = Kitir::aktif()->with(['spbe','details' => fn($q) => $q->where('status','belum_tebus')])
            ->get()
            ->filter(fn($k) => $k->details->isNotEmpty());

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.akuntansi.tebusan.index', compact(
            'tebusan','summary','hargaTebus','kitirAktif',
            'bulan','tahun','bulanList'
        ));
    }

    /**
     * API — ambil detail kitir berdasarkan nomor SA untuk form tebusan
     */
    public function getKitirDetail(Request $request)
    {
        $nomor_sa = $request->get('nomor_sa');
        $kitir    = Kitir::where('nomor_sa', $nomor_sa)
            ->with(['spbe','details' => fn($q) => $q->where('status','belum_tebus')->orderBy('tanggal')])
            ->first();

        if (! $kitir) {
            return response()->json(['success' => false, 'message' => 'SA tidak ditemukan']);
        }

        $hargaTebus = HargaReferensi::hargaAktif('tebus_refil')?->harga ?? 0;

        $details = $kitir->details->map(fn($d) => [
            'id'           => $d->id,
            'tanggal'      => $d->tanggal->format('d/m/Y'),
            'tanggal_raw'  => $d->tanggal->format('Y-m-d'),
            'kuota_tabung' => $d->kuota_tabung,
            'harga_tebus'  => $d->harga_tebus ?: $hargaTebus,
            'subtotal'     => $d->kuota_tabung * ($d->harga_tebus ?: $hargaTebus),
            'status'       => $d->status,
        ]);

        return response()->json([
            'success'      => true,
            'nomor_sa'     => $kitir->nomor_sa,
            'spbe'         => $kitir->spbe->nama_spbe,
            'total_kuota'  => $kitir->total_kuota,
            'harga_tebus'  => $hargaTebus,
            'details'      => $details,
            'total_belum_tebus' => $kitir->details->sum('kuota_tabung'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kitir_id'           => 'required|exists:kitirs,id',
            'tanggal_bayar'      => 'required|date',
            'detail_ids'         => 'required|array|min:1',
            'detail_ids.*'       => 'exists:kitir_details,id',
            'harga_tebus'        => 'required|numeric|min:0',
            'selisih_pembulatan' => 'nullable|numeric', // total selisih Rp
            'no_rekening_tujuan' => 'nullable|string|max:50',
            'keterangan'         => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            $kitir      = Kitir::findOrFail($request->kitir_id);
            $details    = KitirDetail::whereIn('id', $request->detail_ids)->get();
            $hargaTebus = (float) $request->harga_tebus;
            $selisih    = (float) ($request->selisih_pembulatan ?? 0); // selisih TOTAL Rp

            $totalTabung = $details->sum('kuota_tabung');
            $totalBayar  = $totalTabung * $hargaTebus;
            $totalAktual = $totalBayar + $selisih; // aktual = perhitungan + selisih total

            $tebusan = TebusanKitir::create([
                'kitir_id'              => $kitir->id,
                'tanggal_bayar'         => $request->tanggal_bayar,
                'jumlah_tabung_ditebus' => $totalTabung,
                'total_bayar'           => $totalBayar,
                'selisih_pembulatan'    => $selisih,
                'total_bayar_aktual'    => $totalAktual,
                'no_rekening_tujuan'    => $request->no_rekening_tujuan,
                'bukti_transfer'        => null,
                'keterangan'            => $request->keterangan,
                'created_by'            => null, // fallback jika belum ada multi-user
            ]);

            foreach ($details as $d) {
                TebusanKitirDetail::create([
                    'tebusan_id'      => $tebusan->id,
                    'kitir_detail_id' => $d->id,
                    'jumlah_tabung'   => $d->kuota_tabung,
                    'subtotal'        => $d->kuota_tabung * $hargaTebus,
                ]);
                $d->update(['status' => 'sudah_tebus']);
            }

            // Jurnal keluar tebusan
            JurnalAkuntansi::create([
                'tanggal'    => $request->tanggal_bayar,
                'modul'      => 'tebusan',
                'jenis'      => 'keluar',
                'jumlah'     => $totalAktual,
                'keterangan' => "Tebusan SA#{$kitir->nomor_sa} — {$totalTabung} tabung @ Rp ".number_format($hargaTebus),
                'referensi'  => $kitir->nomor_sa,
                'created_by' => null,
            ]);

            // Jurnal selisih terpisah jika ada
            if (abs($selisih) > 0) {
                JurnalAkuntansi::create([
                    'tanggal'    => $request->tanggal_bayar,
                    'modul'      => 'tebusan',
                    'jenis'      => $selisih > 0 ? 'keluar' : 'masuk',
                    'jumlah'     => abs($selisih),
                    'keterangan' => "Selisih pembulatan SA#{$kitir->nomor_sa} total Rp ".number_format(abs($selisih), 2),
                    'referensi'  => $kitir->nomor_sa,
                    'created_by' => null,
                ]);
            }
        });

        return back()->with('success', 'Tebusan berhasil dicatat.');
    }
}
