@extends('layouts.app')
@section('title', 'Profil Agen')

@section('content')
<div style="max-width:680px;margin:0 auto">

  <div style="margin-bottom:20px">
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Profil Agen</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">Data identitas agen untuk keperluan surat jalan dan dokumen distribusi</p>
  </div>

  <div class="card" style="padding:24px">
    <form action="{{ route('dashboard.agen.db.agen.update') }}" method="POST">
      @csrf @method('PUT')

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Nama Agen *</label>
          <input name="nama_agen" required value="{{ old('nama_agen', $agen->nama_agen) }}"
                 placeholder="PT. Sumber Gas Sejahtera"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none"
                 @error('nama_agen') style="border-color:#ef4444" @enderror>
          @error('nama_agen')<p style="font-size:11px;color:#ef4444;margin-top:4px">{{ $message }}</p>@enderror
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Kode Agen</label>
          <input name="kode_agen" value="{{ old('kode_agen', $agen->kode_agen) }}"
                 placeholder="AGN-001"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Sold-To (Kode Pertamina)</label>
          <input name="sold_to" value="{{ old('sold_to', $agen->sold_to) }}"
                 placeholder="1234567890"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Telepon</label>
          <input name="telepon" value="{{ old('telepon', $agen->telepon) }}"
                 placeholder="0812-3456-7890"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Email</label>
          <input name="email" type="email" value="{{ old('email', $agen->email) }}"
                 placeholder="agen@email.com"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px">Alamat</label>
          <input name="alamat" value="{{ old('alamat', $agen->alamat) }}"
                 placeholder="Jl. Merdeka No. 1, Semarang"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
        </div>
      </div>

      <div style="display:flex;gap:8px;padding-top:8px;border-top:1px solid var(--border)">
        <button type="submit"
                style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">
          Simpan Profil
        </button>
      </div>
    </form>
  </div>
</div>
@endsection