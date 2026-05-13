@extends('layouts.app')
@section('title', 'Data Pangkalan')

@section('content')
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Data Pangkalan</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">{{ $pangkalans->total() }} pangkalan terdaftar</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="{{ route('dashboard.agen.db.pangkalan.template') }}"
       style="border:1px solid #059669;color:#059669;background:none;border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
      ⬇ Template XLSX
    </a>
    <button onclick="openModal('modal-import')"
            style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">
      ↑ Import XLSX
    </button>
    <a href="{{ route('dashboard.agen.db.pangkalan.export') }}"
       style="border:1px solid #7C3AED;color:#7C3AED;background:none;border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
      ↓ Export CSV
    </a>
    <button onclick="openModal('modal-tambah')"
            style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">
      + Tambah Pangkalan
    </button>
  </div>
</div>

<form method="GET" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <input name="search" value="{{ $search }}" placeholder="Cari nama atau no. reg..."
         style="flex:1;max-width:320px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
  <select name="tipe" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua Tipe</option>
    <option value="mandiri"   {{ request('tipe')==='mandiri'   ? 'selected' : '' }}>Mandiri</option>
    <option value="kerjasama" {{ request('tipe')==='kerjasama' ? 'selected' : '' }}>Kerjasama</option>
  </select>
  <button type="submit" style="background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer">Filter</button>
  @if($search || request('tipe'))
    <a href="{{ route('dashboard.agen.db.pangkalan') }}" style="border:1px solid var(--border);border-radius:8px;padding:7px 14px;font-size:13px;color:var(--muted);text-decoration:none">✕ Reset</a>
  @endif
</form>

<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['No. Reg','Nama Pangkalan','Pemilik','Tipe','Alokasi/Bln','Koordinat','MAP','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($pangkalans as $p)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;font-family:monospace;font-size:12px;color:var(--muted)">{{ $p->no_reg }}</td>
        <td style="padding:11px 14px">
          <span style="font-weight:600;color:var(--text)">{{ $p->nama_pangkalan }}</span>
          @if($p->alamat)
            <span style="display:block;font-size:11px;color:var(--muted)">{{ Str::limit($p->alamat, 30) }}</span>
          @endif
        </td>
        <td style="padding:11px 14px;font-size:12px;color:var(--text)">
          {{ $p->nama_pemilik ?? '—' }}
          @if($p->nik_pemilik)
            <span style="display:block;font-size:11px;color:var(--muted);font-family:monospace">{{ $p->nik_pemilik }}</span>
          @endif
        </td>
        <td style="padding:11px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $p->tipe==='kerjasama' ? 'background:#DBEAFE;color:#1E40AF' : 'background:#F1F5F9;color:#475569' }}">
            {{ ucfirst($p->tipe) }}
          </span>
        </td>
        <td style="padding:11px 14px;text-align:right;font-size:13px;font-weight:{{ $p->alokasi_per_bulan > 0 ? '700' : '400' }};color:{{ $p->alokasi_per_bulan > 0 ? 'var(--text)' : 'var(--muted)' }}">
          {{ $p->alokasi_per_bulan > 0 ? number_format($p->alokasi_per_bulan) : '—' }}
        </td>
        <td style="padding:11px 14px;font-size:11px">
          @if($p->latitude && $p->longitude)
            <a href="https://maps.google.com/?q={{ $p->latitude }},{{ $p->longitude }}" target="_blank"
               style="color:var(--accent);text-decoration:none;font-family:monospace">
              📍 {{ number_format($p->latitude,4) }},{{ number_format($p->longitude,4) }}
            </a>
          @else
            <span style="color:var(--muted)">—</span>
          @endif
        </td>
        <td style="padding:11px 14px;text-align:center">
          @if($p->map_email)
            <span style="background:#D1FAE5;color:#065F46;font-size:11px;padding:2px 6px;border-radius:4px">✓ Terdaftar</span>
          @else
            <span style="color:var(--muted);font-size:12px">—</span>
          @endif
        </td>
        <td style="padding:11px 14px">
          <form action="{{ route('dashboard.agen.db.pangkalan.toggle', $p) }}" method="POST" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" style="border:none;cursor:pointer;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $p->is_active ? 'background:#D1FAE5;color:#065F46' : 'background:var(--bg);color:var(--muted)' }}">
              {{ $p->is_active ? 'Aktif' : 'Nonaktif' }}
            </button>
          </form>
        </td>
        <td style="padding:11px 14px;white-space:nowrap">
          <button onclick="editPangkalan({{ Js::from($p) }})"
                  style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;margin-right:4px">Edit</button>
          @if($p->tipe === 'kerjasama')
          <a href="{{ route('dashboard.agen.db.pangkalan.perjanjian', $p) }}" target="_blank"
             style="background:none;border:1px solid #7C3AED;color:#7C3AED;border-radius:6px;padding:4px 10px;font-size:12px;text-decoration:none;margin-right:4px">
            📄 SPA
          </a>
          @endif
          <form action="{{ route('dashboard.agen.db.pangkalan.destroy', $p) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus {{ $p->nama_pangkalan }}?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" style="padding:40px;text-align:center;color:var(--muted)">Belum ada data pangkalan</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $pangkalans->links() }}</div>
</div>

{{-- Modal Tambah --}}
<div id="modal-tambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:flex-start;justify-content:center;z-index:300;padding:16px;overflow-y:auto" onclick="closeModal('modal-tambah')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:640px;margin:0 auto" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Tambah Pangkalan</h3>
      <button onclick="closeModal('modal-tambah')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.db.pangkalan.store') }}" method="POST" style="padding:20px 24px">
      @csrf

      <p class="fsec">Data Pangkalan</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">No. Reg *</label><input name="no_reg" required placeholder="P-001" class="finput"></div>
        <div><label class="flabel">Nama Pangkalan *</label><input name="nama_pangkalan" required placeholder="Pangkalan Bu Sari" class="finput"></div>
        <div><label class="flabel">Telepon</label><input name="telepon" type="tel" placeholder="0812-xxxx-xxxx" class="finput"></div>
        <div><label class="flabel">Tipe</label>
          <select name="tipe" class="finput" onchange="toggleKerjasama(this.value, 'add')">
            <option value="mandiri">Mandiri (modal sendiri)</option>
            <option value="kerjasama">Kerjasama (sewa tabung)</option>
          </select>
        </div>
        <div><label class="flabel">Alokasi Kontrak/Bulan (tabung)</label><input name="alokasi_per_bulan" type="number" min="0" placeholder="0" class="finput"></div>
        <div style="grid-column:1/-1"><label class="flabel">Alamat</label><input name="alamat" placeholder="Jl. Mawar No. 5, Desa..." class="finput"></div>
      </div>

      <p class="fsec">Data Pemilik</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">Nama Pemilik</label><input name="nama_pemilik" placeholder="Budi Santoso" class="finput"></div>
        <div><label class="flabel">NIK Pemilik</label><input name="nik_pemilik" placeholder="3302xxxxxxxxxx01" class="finput" style="font-family:monospace"></div>
        <div style="grid-column:1/-1"><label class="flabel">Alamat Pemilik</label><input name="alamat_pemilik" placeholder="Jl. Melati No. 3 RT.01/RW.02" class="finput"></div>
      </div>

      <p class="fsec">Koordinat Lokasi</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">Latitude</label><input name="latitude" type="number" step="0.0000001" placeholder="-7.4012345" class="finput" id="add_lat"></div>
        <div><label class="flabel">Longitude</label><input name="longitude" type="number" step="0.0000001" placeholder="109.2345678" class="finput" id="add_lng"></div>
      </div>
      <button type="button" onclick="getLocation('add_lat','add_lng')"
              style="border:1px solid var(--accent);color:var(--accent);background:none;border-radius:8px;padding:6px 14px;font-size:12px;cursor:pointer;margin-bottom:14px">
        📍 Gunakan Lokasi Saya
      </button>

      <p class="fsec">Akun MAP (MyPertamina Scraping)</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">Email / No HP</label><input name="map_email" type="text" placeholder="email@gmail.com atau 08xxx" class="finput"></div>
        <div><label class="flabel">PIN MAP</label><input name="map_pin" type="password" placeholder="••••••" class="finput"></div>
      </div>

      <div id="add-kerjasama" style="display:none">
        <p class="fsec">Data Kerjasama Tabung</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div><label class="flabel">Jumlah Tabung Pinjaman</label><input name="jumlah_tabung_pinjaman" type="number" min="0" placeholder="50" class="finput"></div>
          <div><label class="flabel">Harga Sewa/Tabung/Distribusi (Rp)</label><input name="harga_sewa_per_tabung" type="number" min="0" placeholder="500" class="finput"></div>
          <div><label class="flabel">Tanggal Mulai Pinjaman</label><input name="tanggal_mulai_pinjaman" type="date" class="finput"></div>
          <div><label class="flabel">Jangka Pinjaman (bulan)</label><input name="jangka_pinjaman_bulan" type="number" min="1" value="12" class="finput"></div>
          <div style="grid-column:1/-1"><label class="flabel">No. Bukti / Nomor Perjanjian</label><input name="nomor_bukti_pinjaman" placeholder="PJB2401.xx" class="finput"></div>
        </div>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">Simpan</button>
        <button type="button" onclick="closeModal('modal-tambah')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div id="modal-edit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:flex-start;justify-content:center;z-index:300;padding:16px;overflow-y:auto" onclick="closeModal('modal-edit')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:640px;margin:0 auto" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Edit Pangkalan</h3>
      <button onclick="closeModal('modal-edit')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-edit-pangkalan" method="POST" style="padding:20px 24px">
      @csrf @method('PUT')
      <p class="fsec">Data Pangkalan</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">No. Reg *</label><input name="no_reg" id="e_no_reg" required class="finput"></div>
        <div><label class="flabel">Nama Pangkalan *</label><input name="nama_pangkalan" id="e_nama_pangkalan" required class="finput"></div>
        <div><label class="flabel">Telepon</label><input name="telepon" id="e_telepon" type="tel" class="finput"></div>
        <div><label class="flabel">Tipe</label>
          <select name="tipe" id="e_tipe" class="finput" onchange="toggleKerjasama(this.value,'edit')">
            <option value="mandiri">Mandiri</option>
            <option value="kerjasama">Kerjasama</option>
          </select>
        </div>
        <div><label class="flabel">Alokasi Kontrak/Bulan (tabung)</label><input name="alokasi_per_bulan" id="e_alokasi" type="number" min="0" class="finput"></div>
        <div style="grid-column:1/-1"><label class="flabel">Alamat</label><input name="alamat" id="e_alamat" class="finput"></div>
      </div>
      <p class="fsec">Data Pemilik</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">Nama Pemilik</label><input name="nama_pemilik" id="e_nama_pemilik" class="finput"></div>
        <div><label class="flabel">NIK</label><input name="nik_pemilik" id="e_nik_pemilik" class="finput" style="font-family:monospace"></div>
        <div style="grid-column:1/-1"><label class="flabel">Alamat Pemilik</label><input name="alamat_pemilik" id="e_alamat_pemilik" class="finput"></div>
      </div>
      <p class="fsec">Koordinat</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">Latitude</label><input name="latitude" id="e_latitude" type="number" step="0.0000001" class="finput"></div>
        <div><label class="flabel">Longitude</label><input name="longitude" id="e_longitude" type="number" step="0.0000001" class="finput"></div>
      </div>
      <p class="fsec">Akun MAP</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">Email / No HP</label><input name="map_email" id="e_map_email" class="finput"></div>
        <div><label class="flabel">PIN MAP <span style="font-weight:400;opacity:.6">(kosong = tidak ubah)</span></label><input name="map_pin" id="e_map_pin" type="password" placeholder="••••••" class="finput"></div>
      </div>
      <div id="edit-kerjasama" style="display:none">
        <p class="fsec">Kerjasama Tabung</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div><label class="flabel">Jumlah Tabung</label><input name="jumlah_tabung_pinjaman" id="e_jtp" type="number" class="finput"></div>
          <div><label class="flabel">Harga Sewa/Tabung (Rp)</label><input name="harga_sewa_per_tabung" id="e_hst" type="number" class="finput"></div>
          <div><label class="flabel">Tanggal Mulai</label><input name="tanggal_mulai_pinjaman" id="e_tmp" type="date" class="finput"></div>
          <div><label class="flabel">Jangka (bulan)</label><input name="jangka_pinjaman_bulan" id="e_jpb" type="number" class="finput"></div>
          <div style="grid-column:1/-1"><label class="flabel">No. Perjanjian</label><input name="nomor_bukti_pinjaman" id="e_nbp" class="finput"></div>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">Perbarui</button>
        <button type="button" onclick="closeModal('modal-edit')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Import --}}
<div id="modal-import" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-import')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:440px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Import Pangkalan dari XLSX</h3>
      <button onclick="closeModal('modal-import')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.db.pangkalan.import-xlsx') }}" method="POST" enctype="multipart/form-data" style="padding:20px 24px">
      @csrf
      <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px;color:var(--muted)">
        <p style="font-weight:600;color:var(--text);margin-bottom:6px">Petunjuk Import:</p>
        <p>1. Download <a href="{{ route('dashboard.agen.db.pangkalan.template') }}" style="color:var(--accent)">Template XLSX</a> terlebih dahulu</p>
        <p>2. Isi data di sheet "Import Pangkalan" mulai baris ke-6</p>
        <p>3. Hapus baris contoh (baris 5 hijau) sebelum import</p>
        <p>4. Upload file yang sudah diisi</p>
      </div>
      @if(session('import_errors'))
        <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:10px;margin-bottom:12px;font-size:12px;color:#991B1B;max-height:120px;overflow-y:auto">
          @foreach(session('import_errors') as $err)
            <div>{{ $err }}</div>
          @endforeach
        </div>
      @endif
      <div style="margin-bottom:14px">
        <label class="flabel">File XLSX / CSV *</label>
        <input type="file" name="xlsx_file" accept=".xlsx,.xls,.csv" required
               style="width:100%;font-size:13px;color:var(--text);padding:8px 0">
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:#059669;color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">
          Import Sekarang
        </button>
        <button type="button" onclick="closeModal('modal-import')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<style>
.flabel{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.finput{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box}
.fsec{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin:0 0 8px;padding-top:4px;border-top:1px solid var(--border)}
.fsec:first-child{border-top:none;padding-top:0}
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') ['modal-tambah','modal-edit','modal-import'].forEach(closeModal) });

function toggleKerjasama(val, prefix) {
  const el = document.getElementById(prefix === 'add' ? 'add-kerjasama' : 'edit-kerjasama');
  if (el) el.style.display = val === 'kerjasama' ? 'block' : 'none';
}

function getLocation(latId, lngId) {
  if (!navigator.geolocation) { alert('Browser tidak mendukung GPS'); return; }
  navigator.geolocation.getCurrentPosition(pos => {
    document.getElementById(latId).value = pos.coords.latitude.toFixed(7);
    document.getElementById(lngId).value = pos.coords.longitude.toFixed(7);
  }, () => alert('Gagal mendapatkan lokasi'));
}

function editPangkalan(p) {
  document.getElementById('form-edit-pangkalan').action = `/dashboard/agen/database/pangkalan/${p.id}`;
  const fields = {
    'e_no_reg': p.no_reg, 'e_nama_pangkalan': p.nama_pangkalan,
    'e_telepon': p.telepon, 'e_alamat': p.alamat,
    'e_nama_pemilik': p.nama_pemilik, 'e_nik_pemilik': p.nik_pemilik,
    'e_alamat_pemilik': p.alamat_pemilik,
    'e_latitude': p.latitude, 'e_longitude': p.longitude,
    'e_map_email': p.map_email,
    'e_alokasi': p.alokasi_per_bulan,
    'e_jtp': p.jumlah_tabung_pinjaman, 'e_hst': p.harga_sewa_per_tabung,
    'e_tmp': p.tanggal_mulai_pinjaman, 'e_jpb': p.jangka_pinjaman_bulan,
    'e_nbp': p.nomor_bukti_pinjaman,
  };
  Object.entries(fields).forEach(([id, val]) => {
    const el = document.getElementById(id);
    if (el) el.value = val ?? '';
  });
  document.getElementById('e_tipe').value = p.tipe ?? 'mandiri';
  toggleKerjasama(p.tipe, 'edit');
  openModal('modal-edit');
}
</script>
@endpush
