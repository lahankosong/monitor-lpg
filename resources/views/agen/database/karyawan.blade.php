@extends('layouts.app')
@section('title', 'Data Karyawan')
@section('content')
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Data Karyawan</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">{{ $karyawans->total() }} karyawan terdaftar</p>
  </div>
  <button onclick="openModal('modal-tambah')" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">+ Tambah Karyawan</button>
</div>

<form method="GET" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <input name="search" value="{{ $search }}" placeholder="Cari nama..."
         style="flex:1;min-width:180px;max-width:280px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
  <select name="role" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua Role</option>
    @foreach($roles as $key => $label)
      <option value="{{ $key }}" {{ $role === $key ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
  </select>
  <button type="submit" style="background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer">Filter</button>
  @if($search || $role)
    <a href="{{ route('dashboard.agen.db.karyawan') }}" style="border:1px solid var(--border);border-radius:8px;padding:7px 14px;font-size:13px;color:var(--muted);text-decoration:none">✕ Reset</a>
  @endif
</form>

<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Nama','Role','Telepon','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @php
        $roleBadge = [
          'owner'     => '#EDE9FE;color:#5B21B6',
          'direktur'  => '#DBEAFE;color:#1E40AF',
          'manager'   => '#D1FAE5;color:#065F46',
          'admin'     => '#FEF3C7;color:#92400E',
          'driver'    => '#F1F5F9;color:#475569',
          'co-driver' => '#FCE7F3;color:#9D174D',
          'security'  => '#FEF2F2;color:#991B1B',
        ];
      @endphp
      @forelse($karyawans as $k)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;font-weight:600;color:var(--text)">{{ $k->nama_karyawan }}</td>
        <td style="padding:11px 14px">
          <span style="padding:2px 10px;border-radius:99px;font-size:11px;font-weight:500;background:{{ $roleBadge[$k->role] ?? '#F1F5F9;color:#475569' }}">
            {{ $k->role_label }}
          </span>
        </td>
        <td style="padding:11px 14px;color:var(--muted)">{{ $k->telepon ?? '—' }}</td>
        <td style="padding:11px 14px">
          <form action="{{ route('dashboard.agen.db.karyawan.toggle', $k) }}" method="POST" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" style="border:none;cursor:pointer;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $k->is_active ? 'background:#D1FAE5;color:#065F46' : 'background:var(--bg);color:var(--muted)' }}">
              {{ $k->is_active ? 'Aktif' : 'Nonaktif' }}
            </button>
          </form>
        </td>
        <td style="padding:11px 14px;white-space:nowrap">
          <button onclick="editKaryawan({{ Js::from($k) }})"
                  style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;margin-right:4px">Edit</button>
          <form action="{{ route('dashboard.agen.db.karyawan.destroy', $k) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus {{ $k->nama_karyawan }}?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--muted)">Belum ada data karyawan</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $karyawans->links() }}</div>
</div>

@foreach(['tambah','edit'] as $m)
<div id="modal-{{ $m }}" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-{{ $m }}')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:420px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">{{ $m==='tambah' ? 'Tambah' : 'Edit' }} Karyawan</h3>
      <button onclick="closeModal('modal-{{ $m }}')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-{{ $m }}-karyawan" action="{{ $m==='tambah' ? route('dashboard.agen.db.karyawan.store') : '#' }}" method="POST" style="padding:20px 24px">
      @csrf @if($m==='edit') @method('PUT') @endif
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px">
        <div><label class="flabel">Nama Karyawan *</label><input name="nama_karyawan" {{ $m==='edit' ? 'id=e_nama_karyawan' : '' }} required class="finput" placeholder="Budi Santoso"></div>
        <div>
          <label class="flabel">Role *</label>
          <select name="role" {{ $m==='edit' ? 'id=e_role' : '' }} required class="finput">
            @foreach($roles as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div><label class="flabel">Telepon</label><input name="telepon" {{ $m==='edit' ? 'id=e_telepon' : '' }} type="tel" class="finput" placeholder="0812-xxxx-xxxx"></div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">{{ $m==='tambah' ? 'Simpan' : 'Perbarui' }}</button>
        <button type="button" onclick="closeModal('modal-{{ $m }}')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>
@endforeach
@endsection
@push('scripts')
<style>
.flabel{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.finput{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box}
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') ['modal-tambah','modal-edit'].forEach(closeModal) });
function editKaryawan(k) {
  document.getElementById('form-edit-karyawan').action = `/dashboard/agen/database/karyawan/${k.id}`;
  document.getElementById('e_nama_karyawan').value = k.nama_karyawan ?? '';
  document.getElementById('e_role').value = k.role ?? '';
  document.getElementById('e_telepon').value = k.telepon ?? '';
  openModal('modal-edit');
}
</script>
@endpush