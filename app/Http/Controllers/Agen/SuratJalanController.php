<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\SuratJalanHeader;
use App\Models\SuratJalanDetail;
use App\Models\KitirDetail;
use App\Models\Armada;
use App\Models\Karyawan;
use App\Models\Pangkalan;
use App\Models\Agen;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuratJalanController extends Controller
{
    public function index(Request $request)
    {
        $bulan  = $request->get('bulan', now()->month);
        $tahun  = $request->get('tahun', now()->year);

        $suratJalan = SuratJalanHeader::with(['armada','sopir','kernet','kitirDetail.kitir'])
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->orderByDesc('tanggal')->orderByDesc('nomor_urut')
            ->paginate(20)->withQueryString();

        // Tanggal kitir yang sudah tebus dan belum ada SJ
        // Cek dulu apakah kolom kitir_detail_id sudah ada
        try {
            $kitirSiapSJ = KitirDetail::where('status', 'sudah_tebus')
                ->whereDoesntHave('suratJalan', fn($q) => $q->whereIn('status', ['draft','aktif','selesai']))
                ->with('kitir.spbe')
                ->orderBy('tanggal')
                ->get();
        } catch (\Exception $e) {
            $kitirSiapSJ = collect();
        }

        $armadas  = Armada::aktif()->orderBy('no_polisi')->get();
        $drivers  = Karyawan::aktif()->where('role', 'driver')->orderBy('nama_karyawan')->get();
        $kernets  = Karyawan::aktif()->where('role', 'co-driver')->orderBy('nama_karyawan')->get();
        $pangkalans = Pangkalan::aktif()->orderBy('nama_pangkalan')->get();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.operasional.surat-jalan.index', compact(
            'suratJalan','kitirSiapSJ','armadas','drivers','kernets','pangkalans',
            'bulan','tahun','bulanList'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'kitir_detail_id'  => 'required|exists:kitir_details,id',
            'armada_id'        => 'required|exists:armadas,id',
            'sopir_id'         => 'required|exists:karyawans,id',
            'kernet_id'        => 'nullable|exists:karyawans,id',
            'tanggal'          => 'required|date',
            'qty_refil'        => 'required|integer|min:1',
            'qty_tabung_baru'  => 'nullable|integer|min:0',
            'pangkalan_ids'    => 'required|array|min:1',
            'pangkalan_ids.*'  => 'exists:pangkalans,id',
            'qty_jadwals'      => 'required|array|min:1',
            'qty_jadwals.*'    => 'required|integer|min:1',
            'urutans'          => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            $kitirDetail = KitirDetail::findOrFail($request->kitir_detail_id);
            $totalQty    = array_sum($request->qty_jadwals);

            // Generate nomor SJ
            $generated = SuratJalanHeader::generateNomorByArmadaId(
                $request->tanggal,
                $request->armada_id
            );

            $sj = SuratJalanHeader::create([
                'no_sj'           => $generated['no_sj'],
                'nomor_urut'      => $generated['nomor_urut'],
                'tanggal'         => $request->tanggal,
                'kitir_id'        => $kitirDetail->kitir_id,
                'kitir_detail_id' => $kitirDetail->id,
                'armada_id'       => $request->armada_id,
                'sopir_id'        => $request->sopir_id,
                'kernet_id'       => $request->kernet_id,
                'total_kuota'     => $request->qty_refil,
                'total_terjadwal' => $totalQty,
                'qty_refil'       => $request->qty_refil,
                'qty_tabung_baru' => $request->qty_tabung_baru ?? 0,
                'status'          => 'aktif',
            ]);

            foreach ($request->pangkalan_ids as $i => $pangkalanId) {
                SuratJalanDetail::create([
                    'header_id'    => $sj->id,
                    'pangkalan_id' => $pangkalanId,
                    'qty_jadwal'   => $request->qty_jadwals[$i],
                    'urutan'       => $request->urutans[$i] ?? ($i + 1),
                    'status'       => 'terjadwal',
                ]);
            }
        });

        return back()->with('success', 'Surat Jalan berhasil dibuat.');
    }

    public function show(SuratJalanHeader $suratJalan)
    {
        $suratJalan->load(['armada','sopir','kernet','kitirDetail.kitir.spbe','details.pangkalan','details.dialihKe']);
        $pangkalans = Pangkalan::aktif()->orderBy('nama_pangkalan')->get();
        return view('agen.operasional.surat-jalan.show', compact('suratJalan','pangkalans'));
    }

    /** Cetak PDF Surat Pengantar ke SPBE */
    public function cetakSpbe(SuratJalanHeader $suratJalan)
    {
        $suratJalan->load(['armada','sopir','kernet','kitirDetail.kitir.spbe']);
        $agen = Agen::profil();
        return view('agen.operasional.surat-jalan.cetak-spbe', compact('suratJalan','agen'));
    }

    /** Cetak PDF Jadwal Distribusi ke Pangkalan */
    public function cetakDistribusi(SuratJalanHeader $suratJalan)
    {
        $suratJalan->load(['armada','sopir','kernet','kitirDetail.kitir.spbe','details.pangkalan']);
        $agen = Agen::profil();
        return view('agen.operasional.surat-jalan.cetak-distribusi', compact('suratJalan','agen'));
    }

    /** Update Nomor LO setelah pengambilan di SPBE */
    public function updateLo(Request $request, SuratJalanHeader $suratJalan)
    {
        $request->validate(['no_lo' => 'required|string|max:30']);
        $suratJalan->update(['no_lo' => $request->no_lo]);
        return back()->with('success', "Nomor LO {$request->no_lo} berhasil disimpan.");
    }

    /** Update realisasi distribusi per pangkalan */
    public function updateRealisasi(Request $request, SuratJalanDetail $detail)
    {
        $request->validate([
            'qty_terima'            => 'required|integer|min:0',
            'status'                => 'required|in:terkirim,sebagian,dialihkan,batal',
            'qty_dialihkan'         => 'nullable|integer|min:0',
            'dialih_ke_pangkalan_id'=> 'nullable|exists:pangkalans,id',
            'keterangan'            => 'nullable|string|max:255',
        ]);

        $detail->update($request->only([
            'qty_terima','status','qty_dialihkan','dialih_ke_pangkalan_id','keterangan'
        ]));

        // Cek apakah semua detail selesai → update header
        $header     = $detail->header;
        $belumSelesai = $header->details()->whereIn('status', ['terjadwal'])->count();
        if ($belumSelesai === 0) {
            $header->update(['status' => 'selesai']);
            // Update kitir_detail → diambil
            if ($header->kitirDetail) {
                $header->kitirDetail->update(['status' => 'diambil']);
            }
        }

        return back()->with('success', 'Realisasi berhasil diperbarui.');
    }

    /** Hapus permanen SJ yang sudah dibatalkan */
    public function destroy(SuratJalanHeader $suratJalan)
    {
        if ($suratJalan->status !== 'batal') {
            return back()->withErrors(['msg' => 'Hanya SJ yang sudah dibatalkan yang bisa dihapus permanen.']);
        }

        $noSj = $suratJalan->no_sj;
        $suratJalan->details()->delete();
        $suratJalan->delete();

        return back()->with('success', "SJ {$noSj} berhasil dihapus permanen.");
    }

    /** Batalkan Surat Jalan */
    public function batal(Request $request, SuratJalanHeader $suratJalan)
    {
        $request->validate(['alasan_batal' => 'required|string|max:255']);

        if ($suratJalan->status === 'selesai') {
            return back()->withErrors(['msg' => 'SJ yang sudah selesai tidak bisa dibatalkan.']);
        }

        $suratJalan->update([
            'status'       => 'batal',
            'alasan_batal' => $request->alasan_batal,
        ]);

        // Kembalikan status kitir_detail → sudah_tebus agar bisa dibuat SJ baru
        if ($suratJalan->kitirDetail && $suratJalan->kitirDetail->status !== 'diambil') {
            $suratJalan->kitirDetail->update(['status' => 'sudah_tebus']);
        }

        return redirect()->route('dashboard.agen.operasional.sj.index')
            ->with('success', "SJ {$suratJalan->no_sj} dibatalkan. Tanggal kitir siap dibuat SJ baru.");
    }
}
