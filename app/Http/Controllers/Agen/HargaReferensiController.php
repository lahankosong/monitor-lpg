<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\HargaReferensi;
use Illuminate\Http\Request;

class HargaReferensiController extends Controller
{
    public function index()
    {
        $hargaList = HargaReferensi::orderByDesc('berlaku_mulai')->paginate(20);
        $kategoriList = [
            'tebus_refil'    => 'Harga Tebus Refil (dari SPBE)',
            'jual_pangkalan' => 'Harga Jual ke Pangkalan',
            'tabung_perdana' => 'Harga Tabung Perdana',
            'sewa_tabung'    => 'Harga Sewa Tabung/Distribusi',
            'lainnya'        => 'Lainnya',
        ];
        // Harga aktif per kategori
        $hargaAktif = collect(array_keys($kategoriList))->mapWithKeys(fn($k) => [
            $k => HargaReferensi::hargaAktif($k)
        ]);
        return view('agen.akuntansi.harga.index', compact('hargaList','kategoriList','hargaAktif'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_item'     => 'required|string|max:100',
            'kategori'      => 'required|in:tebus_refil,jual_pangkalan,tabung_perdana,sewa_tabung,lainnya',
            'harga'         => 'required|integer|min:0',
            'satuan'        => 'nullable|string|max:30',
            'berlaku_mulai' => 'required|date',
            'berlaku_sampai'=> 'nullable|date|after:berlaku_mulai',
            'keterangan'    => 'nullable|string|max:255',
        ]);
        HargaReferensi::create($request->all());
        return back()->with('success', "Harga '{$request->nama_item}' berhasil disimpan.");
    }

    public function destroy(HargaReferensi $harga)
    {
        $harga->delete();
        return back()->with('success', 'Data harga dihapus.');
    }

    /** API — ambil harga aktif per kategori (untuk form tebusan) */
    public function api(Request $request)
    {
        $kategori = $request->get('kategori', 'tebus_refil');
        $harga    = HargaReferensi::hargaAktif($kategori);
        return response()->json([
            'success' => (bool) $harga,
            'harga'   => $harga?->harga,
            'satuan'  => $harga?->satuan,
            'label'   => $harga?->nama_item,
        ]);
    }
}
