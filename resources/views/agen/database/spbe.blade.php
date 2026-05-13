@extends('layouts.app')
@section('title', 'Data SPBE')

@section('content')
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Data SPBE</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">Stasiun Pengisian Bulk Elpiji — sumber pengambilan tabung gas</p>
  </div>
  <button onclick="openModal('modal-tambah')"
          style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">
    + Tambah SPBE
  </button>
</div>

<form method="GET" style="margin-bottom:16px;display:flex;gap:8px">
  <input name="search" value="{{ $search }}" placeholder="Cari nama atau kode SPBE..."
         style="flex:1;max-width:320px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
  <button type="submit" style="background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer">Cari</button>
  @if($search)
    <a href="{{ route('dashboard.agen.db.spbe') }}" style="border:1px solid var(--border);border-radius:8px;padding:7px 14px;font-size:13px;color:var(--muted);text-decoration:none">✕ Reset</a>
  @endif
</form>

<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Kode','Nama SPBE','Kode Plant','Telepon','Alamat','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($spbes as $s)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;font-family:monospace;font-size:12px;font-weight:600;color:var(--muted)">{{ $s->kode_spbe }}</td>
        <td style="padding:11px 14px;font-weight:600;color:var(--text)">{{ $s->nama_spbe }}</td>
        <td style="padding:11px 14px;font-family:monospace;color:var(--muted)">{{ $s->kode_plant ?? '—' }}</td>
        <td style="padding:11px 14px;color:var(--muted)">{{ $s->telepon ?? '—' }}</td>
        <td style="padding:11px 14px;color:var(--muted);font-size:12px">{{ $s->alamat ?? '—' }}</td>
        <td style="padding:11px 14px">
          <form action="{{ route('dashboard.agen.db.spbe.toggle', $s) }}" method="POST" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" style="border:none;cursor:pointer;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $s->is_active ? 'background:#D1FAE5;color:#065F46' : 'background:var(--bg);color:var(--muted)' }}">
              {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
            </button>
          </form>
        </td>
        <td style="padding:11px 14px;white-space:nowrap">
          <button onclick="editSpbe({{ Js::from($s) }})"
                  style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;margin-right:4px">
            Edit
          </button>
          <form action="{{ route('dashboard.agen.db.spbe.destroy', $s) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus SPBE {{ $s->nama_spbe }}?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">
              Hapus
            </button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">Belum ada data SPBE</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $spbes->links() }}</div>
</div>

{{-- Modal Tambah --}}
<div id="modal-tambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-tambah')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:500px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Tambah SPBE</h3>
      <button onclick="closeModal('modal-tambah')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.db.spbe.store') }}" method="POST" style="padding:20px 24px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label class="flabel">Kode SPBE *</label>
          <input name="kode_spbe" required placeholder="SPBE-001" class="finput" style="font-family:monospace">
        </div>
        <div>
          <label class="flabel">Nama SPBE *</label>
          <input name="nama_spbe" required placeholder="SPBE Maju Jaya" class="finput">
        </div>
        <div>
          <label class="flabel">Kode Plant</label>
          <input name="kode_plant" placeholder="P001 / 239P" class="finput" style="font-family:monospace">
        </div>
        <div>
          <label class="flabel">Telepon</label>
          <input name="telepon" type="tel" placeholder="0281-xxx-xxxx" class="finput">
        </div>
        <div style="grid-column:1/-1">
          <label class="flabel">Alamat</label>
          <input name="alamat" placeholder="Jl. Industri No. 1, Kec. ..." class="finput">
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
<div id="modal-edit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-edit')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:500px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Edit SPBE</h3>
      <button onclick="closeModal('modal-edit')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-edit-spbe" method="POST" style="padding:20px 24px">
      @csrf @method('PUT')
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div><label class="flabel">Kode SPBE *</label><input name="kode_spbe" id="e_kode_spbe" required class="finput" style="font-family:monospace"></div>
        <div><label class="flabel">Nama SPBE *</label><input name="nama_spbe" id="e_nama_spbe" required class="finput"></div>
        <div><label class="flabel">Kode Plant</label><input name="kode_plant" id="e_kode_plant" class="finput" style="font-family:monospace"></div>
        <div><label class="flabel">Telepon</label><input name="telepon" id="e_telepon" type="tel" class="finput"></div>
        <div style="grid-column:1/-1"><label class="flabel">Alamat</label><input name="alamat" id="e_alamat" class="finput"></div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">Perbarui</button>
        <button type="button" onclick="closeModal('modal-edit')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<style>
.flabel{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.finput{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box}
.finput:focus{border-color:var(--accent)}
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') { closeModal('modal-tambah'); closeModal('modal-edit'); }});

function editSpbe(s) {
  document.getElementById('form-edit-spbe').action = `/dashboard/agen/database/spbe/${s.id}`;
  ['kode_spbe','nama_spbe','kode_plant','telepon','alamat'].forEach(f => {
    const el = document.getElementById('e_'+f);
    if (el) el.value = s[f] ?? '';
  });
  openModal('modal-edit');
}
</script>
@endpush