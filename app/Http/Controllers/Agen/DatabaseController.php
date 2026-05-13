<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Agen;
use App\Models\Armada;
use App\Models\Karyawan;
use App\Models\Pangkalan;
use App\Models\Spbe;
use Illuminate\Http\Request;

class DatabaseController extends Controller
{
    // ── AGEN (profil tunggal per cabang) ─────────────────────────

    public function agen()
    {
        $agen = Agen::profil() ?? new Agen();
        return view('agen.database.agen', compact('agen'));
    }

    public function agenUpdate(Request $request)
    {
        $data = $request->validate([
            'nama_agen'  => 'required|string|max:100',
            'kode_agen'  => 'nullable|string|max:20',
            'sold_to'    => 'nullable|string|max:30',
            'alamat'     => 'nullable|string|max:255',
            'telepon'    => 'nullable|string|max:20',
            'email'      => 'nullable|email|max:100',
        ]);

        Agen::updateOrCreate(['id' => 1], $data);
        return back()->with('success', 'Profil agen berhasil disimpan.');
    }

    // ── SPBE ──────────────────────────────────────────────────────

    public function spbe(Request $request)
    {
        $search = $request->get('search', '');
        $spbes  = Spbe::when($search, fn($q) => $q->where('nama_spbe', 'like', "%$search%")
                                                   ->orWhere('kode_spbe', 'like', "%$search%"))
                      ->orderBy('nama_spbe')->paginate(15)->withQueryString();
        return view('agen.database.spbe', compact('spbes', 'search'));
    }

    public function spbeStore(Request $request)
    {
        $request->validate([
            'kode_spbe'  => 'required|string|max:20|unique:spbes,kode_spbe',
            'nama_spbe'  => 'required|string|max:100',
            'ship_to'    => 'nullable|string|max:30',
            'kode_plant' => 'nullable|string|max:20',
            'alamat'     => 'nullable|string|max:255',
            'telepon'    => 'nullable|string|max:20',
            'no_rekening'=> 'nullable|string|max:50',
            'nama_bank'  => 'nullable|string|max:50',
        ]);
        Spbe::create($request->all());
        return back()->with('success', "SPBE '{$request->nama_spbe}' berhasil ditambahkan.");
    }

    public function spbeUpdate(Request $request, Spbe $spbe)
    {
        $request->validate([
            'kode_spbe'  => "required|string|max:20|unique:spbes,kode_spbe,{$spbe->id}",
            'nama_spbe'  => 'required|string|max:100',
            'ship_to'    => 'nullable|string|max:30',
            'kode_plant' => 'nullable|string|max:20',
            'alamat'     => 'nullable|string|max:255',
            'telepon'    => 'nullable|string|max:20',
            'no_rekening'=> 'nullable|string|max:50',
            'nama_bank'  => 'nullable|string|max:50',
        ]);
        $spbe->update($request->all());
        return back()->with('success', "SPBE '{$spbe->nama_spbe}' berhasil diperbarui.");
    }

    public function spbeDestroy(Spbe $spbe)
    {
        $spbe->delete();
        return back()->with('success', "SPBE '{$spbe->nama_spbe}' berhasil dihapus.");
    }

    public function spbeToggle(Spbe $spbe)
    {
        $spbe->update(['is_active' => ! $spbe->is_active]);
        return back()->with('success', "Status SPBE diubah.");
    }

    // ── PANGKALAN ─────────────────────────────────────────────────

    public function pangkalan(Request $request)
    {
        $search    = $request->get('search', '');
        $pangkalans = Pangkalan::when($search, fn($q) => $q->where('nama_pangkalan','like',"%$search%")
                                                            ->orWhere('no_reg','like',"%$search%"))
                               ->orderBy('nama_pangkalan')->paginate(20)->withQueryString();
        return view('agen.database.pangkalan', compact('pangkalans', 'search'));
    }

    public function pangkalanStore(Request $request)
    {
        $request->validate([
            'no_reg'         => 'required|string|max:20|unique:pangkalans,no_reg',
            'nama_pangkalan' => 'required|string|max:100',
            'nama_pemilik'   => 'nullable|string|max:100',
            'nik_pemilik'    => 'nullable|string|max:20',
            'alamat'         => 'nullable|string|max:255',
            'alamat_pemilik' => 'nullable|string|max:255',
            'telepon'        => 'nullable|string|max:20',
            'tipe'           => 'in:mandiri,kerjasama',
            'alokasi_per_bulan' => 'nullable|integer|min:0',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
            'map_email'      => 'nullable|string|max:100',
            'map_pin'        => 'nullable|string|min:4',
        ]);

        $data = $request->except(['map_pin','_token']);
        if ($request->filled('map_pin')) {
            $data['map_pin_encrypted'] = \Illuminate\Support\Facades\Crypt::encryptString($request->map_pin);
        }
        unset($data['map_pin']);

        Pangkalan::create($data);
        return back()->with('success', "Pangkalan '{$request->nama_pangkalan}' berhasil ditambahkan.");
    }

    public function pangkalanUpdate(Request $request, Pangkalan $pangkalan)
    {
        $request->validate([
            'no_reg'         => "required|string|max:20|unique:pangkalans,no_reg,{$pangkalan->id}",
            'nama_pangkalan' => 'required|string|max:100',
            'nama_pemilik'   => 'nullable|string|max:100',
            'nik_pemilik'    => 'nullable|string|max:20',
            'alamat'         => 'nullable|string|max:255',
            'alamat_pemilik' => 'nullable|string|max:255',
            'telepon'        => 'nullable|string|max:20',
            'tipe'           => 'in:mandiri,kerjasama',
            'alokasi_per_bulan' => 'nullable|integer|min:0',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
            'map_email'      => 'nullable|string|max:100',
            'map_pin'        => 'nullable|string|min:4',
        ]);

        $data = $request->except(['map_pin','_token','_method']);
        if ($request->filled('map_pin')) {
            $data['map_pin_encrypted'] = \Illuminate\Support\Facades\Crypt::encryptString($request->map_pin);
        }
        unset($data['map_pin']);

        $pangkalan->update($data);
        return back()->with('success', "Pangkalan '{$pangkalan->nama_pangkalan}' berhasil diperbarui.");
    }

    public function pangkalanDestroy(Pangkalan $pangkalan)
    {
        $pangkalan->delete();
        return back()->with('success', "Pangkalan berhasil dihapus.");
    }

    public function pangkalanPerjanjian(Pangkalan $pangkalan)
    {
        if ($pangkalan->tipe !== 'kerjasama') {
            return back()->withErrors(['msg' => 'Surat perjanjian hanya untuk pangkalan tipe kerjasama.']);
        }
        $agen = Agen::profil();
        return view('agen.database.pangkalan-perjanjian', compact('pangkalan', 'agen'));
    }

    public function pangkalanToggle(Pangkalan $pangkalan)
    {
        $pangkalan->update(['is_active' => ! $pangkalan->is_active]);
        return back()->with('success', "Status pangkalan diubah.");
    }

    public function pangkalanImport(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);

        $handle  = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header  = fgetcsv($handle); // skip header
        $ok = $skip = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) { $skip++; continue; }
            [$no_reg, $nama] = [trim($row[0]), trim($row[1])];
            if (! $no_reg || ! $nama) { $skip++; continue; }
            if (Pangkalan::where('no_reg', $no_reg)->exists()) { $skip++; continue; }
            Pangkalan::create(['no_reg' => $no_reg, 'nama_pangkalan' => $nama]);
            $ok++;
        }
        fclose($handle);
        return back()->with('success', "{$ok} pangkalan diimport, {$skip} dilewati.");
    }

    // ── KARYAWAN / DRIVER ─────────────────────────────────────────

    public function karyawan(Request $request)
    {
        $search    = $request->get('search', '');
        $role      = $request->get('role', '');
        $karyawans = Karyawan::when($search, fn($q) => $q->where('nama_karyawan','like',"%$search%"))
                             ->when($role, fn($q) => $q->where('role', $role))
                             ->orderBy('nama_karyawan')->paginate(20)->withQueryString();
        $roles = \App\Models\Karyawan::ROLES;
        return view('agen.database.karyawan', compact('karyawans', 'search', 'role', 'roles'));
    }

    public function karyawanStore(Request $request)
    {
        $request->validate([
            'nama_karyawan' => 'required|string|max:100',
            'role'          => 'required|in:owner,direktur,manager,admin,driver,co-driver,security',
            'telepon'       => 'nullable|string|max:20',
        ]);
        Karyawan::create($request->all());
        return back()->with('success', "Karyawan '{$request->nama_karyawan}' berhasil ditambahkan.");
    }

    public function karyawanUpdate(Request $request, Karyawan $karyawan)
    {
        $request->validate([
            'nama_karyawan' => 'required|string|max:100',
            'role'          => 'required|in:owner,direktur,manager,admin,driver,co-driver,security',
            'telepon'       => 'nullable|string|max:20',
        ]);
        $karyawan->update($request->all());
        return back()->with('success', "Data '{$karyawan->nama_karyawan}' berhasil diperbarui.");
    }

    public function karyawanDestroy(Karyawan $karyawan)
    {
        $karyawan->delete();
        return back()->with('success', "Karyawan berhasil dihapus.");
    }

    public function karyawanToggle(Karyawan $karyawan)
    {
        $karyawan->update(['is_active' => ! $karyawan->is_active]);
        return back()->with('success', "Status karyawan diubah.");
    }

    // ── ARMADA ────────────────────────────────────────────────────

    public function armada(Request $request)
    {
        $search  = $request->get('search', '');
        $armadas = Armada::with(['sopir','kernet'])
                         ->when($search, fn($q) => $q->where('no_polisi','like',"%$search%"))
                         ->orderBy('no_polisi')->paginate(15)->withQueryString();
        $drivers = Karyawan::aktif()->whereIn('role',['driver'])->orderBy('nama_karyawan')->get();
        $kernets = Karyawan::aktif()->whereIn('role',['co-driver'])->orderBy('nama_karyawan')->get();
        // Kumpulkan semua notifikasi STNK dari semua armada aktif
        $notifikasiStnk = Armada::aktif()->get()
            ->map(fn($a) => $a->notifikasi_stnk)
            ->filter()->flatten(1);
        return view('agen.database.armada', compact('armadas','search','drivers','kernets','notifikasiStnk'));
    }

    public function armadaStore(Request $request)
    {
        $request->validate([
            'no_polisi'       => 'required|string|max:20|unique:armadas,no_polisi',
            'jenis'           => 'nullable|string|max:50',
            'no_rangka'       => 'nullable|string|max:50',
            'no_mesin'        => 'nullable|string|max:50',
            'tahun_pembuatan' => 'nullable|integer|min:1990|max:'.(date('Y')+1),
            'sopir_id'        => 'nullable|exists:karyawans,id',
            'kernet_id'       => 'nullable|exists:karyawans,id',
            'stnk_tahunan'    => 'nullable|date',
            'stnk_5tahunan'   => 'nullable|date',
        ]);
        Armada::create($request->only([
            'no_polisi','jenis','no_rangka','no_mesin','tahun_pembuatan',
            'sopir_id','kernet_id','stnk_tahunan','stnk_5tahunan',
        ]));
        return back()->with('success', "Armada '{$request->no_polisi}' berhasil ditambahkan.");
    }

    public function armadaUpdate(Request $request, Armada $armada)
    {
        $request->validate([
            'no_polisi'       => "required|string|max:20|unique:armadas,no_polisi,{$armada->id}",
            'jenis'           => 'nullable|string|max:50',
            'no_rangka'       => 'nullable|string|max:50',
            'no_mesin'        => 'nullable|string|max:50',
            'tahun_pembuatan' => 'nullable|integer|min:1990',
            'sopir_id'        => 'nullable|exists:karyawans,id',
            'kernet_id'       => 'nullable|exists:karyawans,id',
            'stnk_tahunan'    => 'nullable|date',
            'stnk_5tahunan'   => 'nullable|date',
        ]);
        $armada->update($request->only([
            'no_polisi','jenis','no_rangka','no_mesin','tahun_pembuatan',
            'sopir_id','kernet_id','stnk_tahunan','stnk_5tahunan',
        ]));
        return back()->with('success', "Armada '{$armada->no_polisi}' berhasil diperbarui.");
    }

    public function armadaDestroy(Armada $armada)
    {
        $armada->delete();
        return back()->with('success', "Armada berhasil dihapus.");
    }

    public function armadaToggle(Armada $armada)
    {
        $armada->update(['is_active' => ! $armada->is_active]);
        return back()->with('success', "Status armada diubah.");
    }
}
