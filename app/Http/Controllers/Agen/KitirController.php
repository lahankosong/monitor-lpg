<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Kitir;
use App\Models\KitirDetail;
use App\Models\Spbe;
use App\Models\Agen;
use App\Models\HargaReferensi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KitirController extends Controller
{
    public function index(Request $request)
    {
        $bulan  = $request->get('bulan', now()->month);
        $tahun  = $request->get('tahun', now()->year);
        $jenis  = $request->get('jenis', '');
        $status = $request->get('status', '');

        $kitirs = Kitir::with(['spbe', 'details'])
            ->when($jenis,  fn($q) => $q->where('jenis', $jenis))
            ->when($status, fn($q) => $q->where('status', $status))
            ->where(fn($q) => $q
                ->whereYear('valid_from', $tahun)->whereMonth('valid_from', $bulan)
                ->orWhereYear('valid_to', $tahun)->whereMonth('valid_to', $bulan)
            )
            ->orderByDesc('valid_from')
            ->paginate(15)->withQueryString();

        $spbes        = Spbe::aktif()->orderBy('nama_spbe')->get();
        $hargaTebus   = HargaReferensi::hargaAktif('tebus_refil');
        $bulanList    = collect(range(1, 12))->mapWithKeys(fn($m) => [$m => Carbon::create()->month($m)->translatedFormat('F')]);

        return view('agen.operasional.kitir.index', compact(
            'kitirs', 'spbes', 'hargaTebus',
            'bulan', 'tahun', 'jenis', 'status', 'bulanList'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nomor_sa'   => 'required|string|max:20|unique:kitirs,nomor_sa',
            'spbe_id'    => 'required|exists:spbes,id',
            'jenis'      => 'required|in:reguler,fakultatif',
            'sold_to'    => 'required|string|max:20',
            'ship_to'    => 'required|string|max:20',
            'tanggals'   => 'required|array|min:1',
            'tanggals.*' => 'required|date',
            'kuotas.*'   => 'required|integer|min:1',
        ]);

        $kitir = Kitir::create([
            'nomor_sa'   => $request->nomor_sa,
            'sold_to'    => $request->sold_to,
            'ship_to'    => $request->ship_to,
            'spbe_id'    => $request->spbe_id,
            'jenis'      => $request->jenis,
            'valid_from' => min($request->tanggals),
            'valid_to'   => max($request->tanggals),
            'total_kuota'=> array_sum($request->kuotas),
            'status'     => 'aktif',
            'keterangan' => $request->keterangan,
        ]);

        $hargaTebus = HargaReferensi::hargaAktif('tebus_refil')?->harga ?? 0;

        foreach ($request->tanggals as $i => $tgl) {
            KitirDetail::create([
                'kitir_id'     => $kitir->id,
                'tanggal'      => $tgl,
                'kuota_tabung' => $request->kuotas[$i],
                'harga_tebus'  => $hargaTebus,
                'status'       => 'belum_tebus',
            ]);
        }

        return back()->with('success', "Kitir SA#{$kitir->nomor_sa} berhasil disimpan ({$kitir->total_kuota} tabung, ".count($request->tanggals)." hari).");
    }

    public function show(Kitir $kitir)
    {
        $kitir->load(['spbe', 'details', 'tebusan.details']);
        return view('agen.operasional.kitir.show', compact('kitir'));
    }

    public function destroy(Kitir $kitir)
    {
        if ($kitir->status === 'aktif') {
            // Cek apakah ada yang sudah ditebus
            $sudahTebus = $kitir->details()->where('status', '!=', 'belum_tebus')->count();
            if ($sudahTebus > 0) {
                return back()->withErrors(['msg' => 'Kitir tidak bisa dihapus karena sebagian sudah ditebus.']);
            }
        }
        $kitir->delete();
        return back()->with('success', "Kitir SA#{$kitir->nomor_sa} berhasil dihapus.");
    }

    public function updateDetailStatus(Request $request, KitirDetail $detail)
    {
        $request->validate(['status' => 'required|in:belum_tebus,sudah_tebus,diambil']);
        $detail->update(['status' => $request->status]);

        // Cek apakah semua detail sudah diambil → update parent kitir
        $kitir      = $detail->kitir;
        $allDiambil = $kitir->details()->where('status', '!=', 'diambil')->count() === 0;
        if ($allDiambil) $kitir->update(['status' => 'selesai']);

        return back()->with('success', 'Status kitir diperbarui.');
    }
}