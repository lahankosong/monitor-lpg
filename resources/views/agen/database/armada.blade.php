@extends('layouts.app')
@section('title', 'Data Armada')
@section('content')

<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Data Armada</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">{{ $armadas->total() }} kendaraan · 1 DO = 560 tabung</p>
  </div>
  <button onclick="openModal('modal-tambah')" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">+ Tambah Armada</button>
</div>

{{-- Notifikasi STNK jatuh tempo --}}
@if($notifikasiStnk->isNotEmpty())
<div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:14px 18px;margin-bottom:16px">
  <p style="font-size:13px;font-weight:600;color:#92400E;margin-bottom:8px">⚠ Notifikasi Pajak Kendaraan</p>
  @foreach($notifikasiStnk as $notif)
  <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-top:1px solid #FCD34D">
    <span style="font-size:13px;color:#92400E">
      <strong>{{ $notif['no_polisi'] }}</strong> — {{ $notif['label'] }}
      jatuh tempo <strong>{{ $notif['jatuh_tempo'] }}</strong>
      ({{ $notif['sisa_hari'] }} hari lagi)
    </span>
    <a href="#" onclick="alert('Fitur pembayaran pajak coming soon')"
       style="background:#D97706;color:#fff;border-radius:6px;padding:4px 12px;font-size:12px;text-decoration:none">
      Update Bayar
    </a>
  </div>
  @endforeach
</div>
@endif

<form method="GET" style="margin-bottom:16px;display:flex;gap:8px">
  <input name="search" value="{{ $search }}" placeholder="Cari no. polisi..."
         style="flex:1;max-width:280px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
  <button type="submit" style="background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer">Cari</button>
  @if($search)
    <a href="{{ route('dashboard.agen.db.armada') }}" style="border:1px solid var(--border);border-radius:8px;padding:7px 14px;font-size:13px;color:var(--muted);text-decoration:none">✕</a>
  @endif
</form>

<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['No. Polisi','Jenis / Tahun','Sopir','Kernet','STNK','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($armadas as $a)
      @php
        $notif = $a->notifikasi_stnk;
        $rowBg = $notif ? 'rgba(251,191,36,.06)' : 'transparent';
      @endphp
      <tr style="border-top:1px solid var(--border);background:{{ $rowBg }}">
        <td style="padding:11px 14px">
          <span style="font-weight:700;color:var(--text);font-family:monospace;font-size:14px">{{ $a->no_polisi }}</span>
          @if($a->no_rangka)
            <span style="display:block;font-size:10px;color:var(--muted);font-family:monospace">{{ $a->no_rangka }}</span>
          @endif
        </td>
        <td style="padding:11px 14px;color:var(--muted)">
          {{ $a->jenis ?? '—' }}
          @if($a->tahun_pembuatan)
            <span style="display:block;font-size:11px">{{ $a->tahun_pembuatan }}</span>
          @endif
        </td>
        <td style="padding:11px 14px;color:var(--text)">{{ $a->sopir?->nama_karyawan ?? '—' }}</td>
        <td style="padding:11px 14px;color:var(--text)">{{ $a->kernet?->nama_karyawan ?? '—' }}</td>
        <td style="padding:11px 14px;font-size:11px">
          @if($a->stnk_tahunan)
            <div style="color:{{ $a->notifikasi_stnk ? '#D97706' : 'var(--muted)' }}">
              Tahunan: {{ $a->stnk_tahunan->format('d/m') }}
            </div>
          @endif
          @if($a->stnk_5tahunan)
            <div style="color:var(--muted)">5 Tahun: {{ $a->stnk_5tahunan->format('d/m/Y') }}</div>
          @endif
          @if(! $a->stnk_tahunan && ! $a->stnk_5tahunan) <span style="color:var(--muted)">—</span> @endif
        </td>
        <td style="padding:11px 14px">
          <form action="{{ route('dashboard.agen.db.armada.toggle', $a) }}" method="POST" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" style="border:none;cursor:pointer;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $a->is_active ? 'background:#D1FAE5;color:#065F46' : 'background:var(--bg);color:var(--muted)' }}">
              {{ $a->is_active ? 'Aktif' : 'Nonaktif' }}
            </button>
          </form>
        </td>
        <td style="padding:11px 14px;white-space:nowrap">
          <button onclick="editArmada({{ Js::from($a) }})"
                  style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;margin-right:4px">Edit</button>
          <form action="{{ route('dashboard.agen.db.armada.destroy', $a) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus armada {{ $a->no_polisi }}?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--muted)">Belum ada data armada</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $armadas->links() }}</div>
</div>

@foreach(['tambah','edit'] as $m)
<div id="modal-{{ $m }}" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:flex-start;justify-content:center;z-index:300;padding:16px;overflow-y:auto" onclick="closeModal('modal-{{ $m }}')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:560px;margin:0 auto" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">{{ $m==='tambah' ? 'Tambah' : 'Edit' }} Armada</h3>
      <button onclick="closeModal('modal-{{ $m }}')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-{{ $m }}-armada" action="{{ $m==='tambah' ? route('dashboard.agen.db.armada.store') : '#' }}" method="POST" style="padding:20px 24px">
      @csrf @if($m==='edit') @method('PUT') @endif

      <p class="fsec">Data Kendaraan</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><label class="flabel">No. Polisi *</label><input name="no_polisi" {{ $m==='edit'?'id=e_no_polisi':'' }} required class="finput" placeholder="H 1234 AB" style="font-family:monospace;text-transform:uppercase"></div>
        <div><label class="flabel">Jenis Kendaraan</label><input name="jenis" {{ $m==='edit'?'id=e_jenis':'' }} class="finput" placeholder="Truk / Pickup"></div>
        <div><label class="flabel">No. Rangka</label><input name="no_rangka" {{ $m==='edit'?'id=e_no_rangka':'' }} class="finput" placeholder="MHF..." style="font-family:monospace"></div>
        <div><label class="flabel">No. Mesin</label><input name="no_mesin" {{ $m==='edit'?'id=e_no_mesin':'' }} class="finput" placeholder="2GD..." style="font-family:monospace"></div>
        <div><label class="flabel">Tahun Pembuatan</label><input name="tahun_pembuatan" {{ $m==='edit'?'id=e_tahun':'' }} type="number" min="1990" max="{{ date('Y')+1 }}" class="finput" placeholder="{{ date('Y') }}"></div>
      </div>

      <p class="fsec">Crew Utama</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="flabel">Sopir Utama</label>
          <select name="sopir_id" {{ $m==='edit'?'id=e_sopir_id':'' }} class="finput">
            <option value="">-- Pilih Sopir --</option>
            @foreach($drivers as $d)
              <option value="{{ $d->id }}">{{ $d->nama_karyawan }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="flabel">Kernet Utama</label>
          <select name="kernet_id" {{ $m==='edit'?'id=e_kernet_id':'' }} class="finput">
            <option value="">-- Pilih Kernet --</option>
            @foreach($kernets as $k)
              <option value="{{ $k->id }}">{{ $k->nama_karyawan }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <p style="font-size:11px;color:var(--muted);margin-bottom:14px">💡 Sopir dan kernet akan otomatis muncul di Surat Jalan, tapi bisa diganti saat membuat SJ.</p>

      <p class="fsec">STNK — Notifikasi Pajak</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label class="flabel">Jatuh Tempo Pajak Tahunan</label>
          <input name="stnk_tahunan" {{ $m==='edit'?'id=e_stnk_tahunan':'' }} type="date" class="finput">
          <p style="font-size:10px;color:var(--muted);margin-top:3px">Notifikasi 14 hari sebelum jatuh tempo</p>
        </div>
        <div>
          <label class="flabel">Jatuh Tempo Pajak 5 Tahunan</label>
          <input name="stnk_5tahunan" {{ $m==='edit'?'id=e_stnk_5tahunan':'' }} type="date" class="finput">
          <p style="font-size:10px;color:var(--muted);margin-top:3px">Ganti plat + STNK baru</p>
        </div>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">{{ $m==='tambah'?'Simpan':'Perbarui' }}</button>
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
.fsec{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin:0 0 8px;padding-top:12px;border-top:1px solid var(--border)}
.fsec:first-child{border-top:none;padding-top:0}
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') ['modal-tambah','modal-edit'].forEach(closeModal) });

function editArmada(a) {
  document.getElementById('form-edit-armada').action = `/dashboard/agen/database/armada/${a.id}`;
  const m = {
    'e_no_polisi': a.no_polisi, 'e_jenis': a.jenis,
    'e_no_rangka': a.no_rangka, 'e_no_mesin': a.no_mesin,
    'e_tahun': a.tahun_pembuatan,
    'e_sopir_id': a.sopir_id, 'e_kernet_id': a.kernet_id,
    'e_stnk_tahunan': a.stnk_tahunan, 'e_stnk_5tahunan': a.stnk_5tahunan,
  };
  Object.entries(m).forEach(([id, val]) => {
    const el = document.getElementById(id);
    if (el) el.value = val ?? '';
  });
  openModal('modal-edit');
}
</script>
@endpush