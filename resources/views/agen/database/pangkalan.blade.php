@extends('layouts.app')
@section('title', 'Data Pangkalan')

@section('content')
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Data Pangkalan</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">{{ $pangkalans->total() }} pangkalan terdaftar</p>
  </div>
  <div style="display:flex;gap:8px">
    <button onclick="openModal('modal-import')"
            style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">
      ↑ Import CSV
    </button>
    <button onclick="openModal('modal-tambah')"
            style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">
      + Tambah Pangkalan
    </button>
  </div>
</div>

<form method="GET" style="margin-bottom:16px;display:flex;gap:8px">
  <input name="search" value="{{ $search }}" placeholder="Cari nama atau no. reg..."
         style="flex:1;max-width:320px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
  <button type="submit" style="background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer">Cari</button>
  @if($search)
    <a href="{{ route('dashboard.agen.db.pangkalan') }}" style="border:1px solid var(--border);border-radius:8px;padding:7px 14px;font-size:13px;color:var(--muted);text-decoration:none">✕ Reset</a>
  @endif
</form>

<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['No. Reg','Nama Pangkalan','Alamat','Telepon','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($pangkalans as $p)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;font-family:monospace;font-size:12px;color:var(--muted)">{{ $p->no_reg }}</td>
        <td style="padding:11px 14px;font-weight:600;color:var(--text)">{{ $p->nama_pangkalan }}</td>
        <td style="padding:11px 14px;color:var(--muted);font-size:12px">{{ $p->alamat ?? '—' }}</td>
        <td style="padding:11px 14px;color:var(--muted)">{{ $p->telepon ?? '—' }}</td>
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
          <form action="{{ route('dashboard.agen.db.pangkalan.destroy', $p) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus {{ $p->nama_pangkalan }}?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Belum ada data pangkalan. Tambah manual atau import CSV.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $pangkalans->links() }}</div>
</div>

{{-- Modal Tambah --}}
<div id="modal-tambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-tambah')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:480px" onclick="event.stopPropagation()">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Tambah Pangkalan</h3>
      <button onclick="closeModal('modal-tambah')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.db.pangkalan.store') }}" method="POST" style="padding:20px 24px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label class="flabel">No. Reg *</label>
          <input name="no_reg" required placeholder="P-001" class="finput">
        </div>
        <div>
          <label class="flabel">Nama Pangkalan *</label>
          <input name="nama_pangkalan" required placeholder="Pangkalan Bu Sari" class="finput">
        </div>
        <div>
          <label class="flabel">Telepon</label>
          <input name="telepon" placeholder="0812-xxxx-xxxx" class="finput">
        </div>
        <div>
          <label class="flabel">Alamat</label>
          <input name="alamat" placeholder="Jl. Mawar No. 5" class="finput">
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
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:560px;margin:0 auto" onclick="event.stopPropagation()">
    <div style="padding:16px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Edit Pangkalan</h3>
      <button onclick="closeModal('modal-edit')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-edit-pangkalan" method="POST" style="padding:20px 24px">
      @csrf @method('PUT')

      {{-- Identitas --}}
      <p style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Identitas</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
        <div><label class="flabel">No. Reg *</label><input name="no_reg" id="e_no_reg" required class="finput"></div>
        <div><label class="flabel">Nama Pangkalan *</label><input name="nama_pangkalan" id="e_nama_pangkalan" required class="finput"></div>
        <div><label class="flabel">Telepon</label><input name="telepon" id="e_telepon" class="finput"></div>
        <div>
          <label class="flabel">Tipe</label>
          <select name="tipe" id="e_tipe" class="finput">
            <option value="mandiri">Mandiri</option>
            <option value="kerjasama">Kerjasama</option>
          </select>
        </div>
        <div style="grid-column:span 2"><label class="flabel">Alamat</label><input name="alamat" id="e_alamat" class="finput"></div>
        <div style="grid-column:span 2"><label class="flabel">Alamat Pemilik</label><input name="alamat_pemilik" id="e_alamat_pemilik" class="finput"></div>
      </div>

      {{-- Koordinat --}}
      <p style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Koordinat</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
        <div><label class="flabel">Latitude</label><input name="lat" id="e_lat" type="number" step="any" class="finput" placeholder="-7.123456"></div>
        <div><label class="flabel">Longitude</label><input name="lng" id="e_lng" type="number" step="any" class="finput" placeholder="109.123456"></div>
      </div>

      {{-- Akun MAP --}}
      <p style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Akun MAP MyPertamina</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
        <div>
          <label class="flabel">Email / No HP</label>
          <input name="map_email" id="e_map_email" class="finput" placeholder="email@gmail.com">
        </div>
        <div>
          <label class="flabel">PIN MAP <span style="font-weight:400;color:var(--muted)">(kosong = tidak ubah)</span></label>
          <div style="position:relative">
            <input type="password" name="map_pin" id="e_map_pin" class="finput"
                   placeholder="••••••" style="padding-right:70px">
            <button type="button" id="btn-show-pin"
                    onclick="toggleShowPin()"
                    style="position:absolute;right:8px;top:50%;transform:translateY(-50%);
                           background:none;border:1px solid var(--border);color:var(--muted);
                           border-radius:6px;padding:2px 8px;font-size:11px;cursor:pointer">
              Lihat
            </button>
          </div>
          <p id="e_pin_info" style="font-size:11px;color:var(--muted);margin-top:4px;display:none"></p>
        </div>
      </div>

      {{-- Kerjasama Tabung --}}
      <div id="section-kerjasama">
        <p style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Kerjasama Tabung</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
          <div><label class="flabel">Jumlah Tabung</label><input name="jumlah_tabung_kerjasama" id="e_jml_tabung" type="number" min="0" value="0" class="finput"></div>
          <div><label class="flabel">Harga Sewa/Tabung (Rp)</label><input name="harga_sewa_tabung" id="e_harga_sewa" type="number" min="0" value="0" class="finput"></div>
          <div><label class="flabel">Tanggal Mulai</label><input name="tgl_mulai_kerjasama" id="e_tgl_mulai" type="date" class="finput"></div>
          <div><label class="flabel">Jangka (bulan)</label><input name="jangka_kerjasama" id="e_jangka" type="number" min="1" value="12" class="finput"></div>
          <div style="grid-column:span 2"><label class="flabel">No. Perjanjian</label><input name="no_perjanjian" id="e_no_perjanjian" class="finput"></div>
        </div>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer">Perbarui</button>
        <button type="button" onclick="closeModal('modal-edit')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Import CSV --}}
<div id="modal-import" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-import')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:440px" onclick="event.stopPropagation()">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Import CSV Pangkalan</h3>
      <button onclick="closeModal('modal-import')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.db.pangkalan.import') }}" method="POST" enctype="multipart/form-data" style="padding:20px 24px">
      @csrf
      <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px;color:var(--muted)">
        Format CSV: <code>no_reg, nama_pangkalan, telepon (opsional), alamat (opsional)</code>
      </div>
      <input type="file" name="csv_file" accept=".csv,.txt" required
             style="width:100%;margin-bottom:14px;font-size:13px;color:var(--text)">
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:#059669;color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">Import</button>
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
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') ['modal-tambah','modal-edit','modal-import'].forEach(closeModal) });

function editPangkalan(p) {
  document.getElementById('form-edit-pangkalan').action = `/dashboard/agen/database/pangkalan/${p.id}`;
  currentPangkalanId = p.id;

  // Identitas
  ['no_reg','nama_pangkalan','telepon','alamat'].forEach(f => {
    const el = document.getElementById('e_'+f);
    if (el) el.value = p[f] ?? '';
  });
  const eTipe = document.getElementById('e_tipe');
  if (eTipe) eTipe.value = p.tipe || 'mandiri';
  const eAlamatP = document.getElementById('e_alamat_pemilik');
  if (eAlamatP) eAlamatP.value = p.alamat_pemilik || '';

  // Koordinat
  const eLat = document.getElementById('e_lat');
  const eLng = document.getElementById('e_lng');
  if (eLat) eLat.value = p.lat || '';
  if (eLng) eLng.value = p.lng || '';

  // Akun MAP — email tampil, PIN reset
  const eEmail = document.getElementById('e_map_email');
  const ePin   = document.getElementById('e_map_pin');
  const eBtn   = document.getElementById('btn-show-pin');
  const eInfo  = document.getElementById('e_pin_info');
  if (eEmail) eEmail.value = p.map_email || '';
  if (ePin)   { ePin.value = ''; ePin.type = 'password'; }
  if (eBtn)   eBtn.textContent = 'Lihat';
  if (eInfo)  {
    if (p.map_email) {
      eInfo.textContent = 'PIN tersimpan — kosongkan jika tidak ingin mengubah · klik Lihat untuk tampilkan';
      eInfo.style.color = 'var(--muted)';
      eInfo.style.display = 'block';
    } else {
      eInfo.style.display = 'none';
    }
  }

  // Kerjasama
  ['e_jml_tabung','e_harga_sewa','e_jangka'].forEach((id, i) => {
    const keys = ['jumlah_tabung_kerjasama','harga_sewa_tabung','jangka_kerjasama'];
    const el = document.getElementById(id);
    if (el) el.value = p[keys[i]] || 0;
  });
  const eTgl = document.getElementById('e_tgl_mulai');
  if (eTgl) eTgl.value = p.tgl_mulai_kerjasama || '';
  const ePerjanjian = document.getElementById('e_no_perjanjian');
  if (ePerjanjian) ePerjanjian.value = p.no_perjanjian || '';

  openModal('modal-edit');
}

// Toggle show/hide PIN MAP
let currentPangkalanId = null;
async function toggleShowPin() {
  const input = document.getElementById('e_map_pin');
  const btn   = document.getElementById('btn-show-pin');
  const info  = document.getElementById('e_pin_info');

  if (input.type === 'text') {
    input.type = 'password';
    btn.textContent = 'Lihat';
    return;
  }
  if (input.value) {
    input.type = 'text';
    btn.textContent = 'Sembunyikan';
    return;
  }
  if (!currentPangkalanId) return;
  try {
    const res  = await fetch(`/dashboard/agen/database/pangkalan/${currentPangkalanId}/pin`, {
      headers: { 'Accept': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    const data = await res.json();
    if (data.success) {
      input.value = data.pin;
      input.type  = 'text';
      btn.textContent = 'Sembunyikan';
      info.textContent = '⚠ PIN ditampilkan — auto-sembunyikan 10 detik';
      info.style.color = '#F59E0B';
      info.style.display = 'block';
      setTimeout(() => {
        input.type = 'password';
        btn.textContent = 'Lihat';
      }, 10000);
    } else {
      info.textContent = 'PIN belum diset — isi baru di kolom ini';
      info.style.color = '#DC2626';
      info.style.display = 'block';
    }
  } catch(e) {
    console.error(e);
  }
}
</script>
@endpush
