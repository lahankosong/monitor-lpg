<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\SuratJalanHeader;
use App\Models\SuratJalanDetail;
use App\Models\DistribusiRealisasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverController extends Controller
{
    /** Halaman utama driver — SJ hari ini */
    public function index(Request $request)
    {
        $tanggal = $request->get('tanggal', now()->toDateString());

        // Ambil semua SJ aktif pada tanggal tersebut
        // (driver bisa lihat semua SJ, bukan hanya miliknya — karena bisa saling bantu)
        $sjList = SuratJalanHeader::with([
                'armada','sopir','kernet',
                'kitirDetail.kitir.spbe',
                'details.pangkalan',
                'details.dialihKe',
            ])
            ->where('tanggal', $tanggal)
            ->whereIn('status', ['aktif','selesai'])
            ->orderBy('nomor_urut')
            ->get();

        return view('agen.driver.index', compact('sjList','tanggal'));
    }

    /** Input realisasi dari driver (qty + keterangan saja) */
    public function inputRealisasi(Request $request, SuratJalanDetail $detail)
    {
        $request->validate([
            'qty_terima'  => 'required|integer|min:0',
            'status'      => 'required|in:terkirim,sebagian,dialihkan,batal',
            'keterangan'  => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $detail) {
            // Update detail SJ
            $detail->update([
                'qty_terima'  => $request->qty_terima,
                'status'      => $request->status,
                'keterangan'  => $request->keterangan,
            ]);

            // Simpan ke distribusi_realisasis
            DistribusiRealisasi::updateOrCreate(
                ['sj_detail_id' => $detail->id],
                [
                    'header_id'         => $detail->header_id,
                    'pangkalan_id'      => $detail->pangkalan_id,
                    'qty_jadwal'        => $detail->qty_jadwal,
                    'qty_terima'        => $request->qty_terima,
                    'status_sisa'       => $request->qty_terima >= $detail->qty_jadwal
                                          ? 'lunas' : 'gendongan',
                    'tanggal_realisasi' => $detail->header->tanggal,
                    'keterangan'        => $request->keterangan,
                    'dilaporkan_oleh'   => auth()->id(),
                ]
            );

            // Cek apakah semua detail selesai → update header
            $header       = $detail->header;
            $belumSelesai = $header->details()->where('status','terjadwal')->count();
            if ($belumSelesai === 0) {
                $header->update(['status' => 'selesai']);
                if ($header->kitirDetail) {
                    $header->kitirDetail->update(['status' => 'diambil']);
                }
            }
        });

        return back()->with('success', "✓ {$detail->pangkalan?->nama_pangkalan} — {$request->qty_terima} tabung dilaporkan.");
    }

    /** Halaman stok untuk driver */
    public function stokView(Request $request)
    {
        // Reuse distribusi stok view tapi dengan layout driver
        $agen      = \App\Models\Agen::profil();
        $gendongan = \App\Models\StokArmada::with(['armada','sjHeader'])
            ->where('sisa_akhir', '>', 0)
            ->orderByDesc('tanggal')->get()->groupBy('armada_id');
        $gudang    = \App\Models\GudangStok::with('agenAsal')
            ->where('agen_id', $agen?->id ?? 0)
            ->where('sisa_stok', '>', 0)->orderBy('tgl_masuk')->get();
        return view('agen.driver.stok', compact('gendongan','gudang'));
    }

    /** Histori laporan driver bulan ini */
    public function histori(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);

        $histori = SuratJalanDetail::with(['pangkalan','header'])
            ->whereHas('header', fn($q) =>
                $q->whereYear('tanggal', $tahun)
                  ->whereMonth('tanggal', $bulan)
                  ->whereIn('status', ['aktif','selesai'])
            )
            ->whereNotIn('status', ['terjadwal'])
            ->get()
            ->groupBy(fn($d) => $d->header->tanggal->format('Y-m-d'))
            ->sortKeysDesc();

        // Summary
        $allDetails    = $histori->flatten();
        $totalJadwal   = $allDetails->sum('qty_jadwal');
        $totalTerima   = $allDetails->sum('qty_terima');
        $totalTrip     = $histori->count();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.driver.histori', compact(
            'histori','totalJadwal','totalTerima','totalTrip',
            'bulan','tahun','bulanList'
        ));
    }
}
