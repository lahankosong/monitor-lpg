@extends('layouts.app')
@section('title', 'Detail SJ '.$suratJalan->no_sj)

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('dashboard.agen.operasional.sj.index') }}"
     style="font-size:12px;color:var(--muted);text-decoration:none">← Kembali ke daftar surat jalan</a>
</div>

{{-- Header --}}
@php
  $sc = match($suratJalan->status) {
    'draft'   => 'background:#F1F5F9;color:#475569',
    'aktif'   => 'background:#DBEAFE;color:#1E40AF',
    'selesai' => 'background:#D1FAE5;color:#065F46',
    'batal'   => 'background:#FEE2E2;color:#991B1B',
    default   => '',
  };
@endphp
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <h1 style="font-size:20px;font-weight:700;color:var(--text);font-family:monospace">{{ $suratJalan->no_sj }}</h1>
      <span style="padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600;{{ $sc }}">{{ $suratJalan->status_label }}</span>
    </div>
    <p style="font-size:12px;color:var(--muted);margin-top:4px">
      {{ $suratJalan->tanggal->translatedFormat('l, d F Y') }}
      · SA# <span style="font-family:monospace;color:var(--accent)">{{ $suratJalan->kitirDetail?->kitir?->nomor_sa }}</span>
      · {{ $suratJalan->kitirDetail?->kitir?->spbe?->nama_spbe }}
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    @if($suratJalan->status !== 'batal')
    <a href="{{ route('dashboard.agen.operasional.sj.cetak-spbe', $suratJalan) }}" target="_blank"
       style="border:1px solid #7C3AED;color:#7C3AED;background:none;border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
      🖨 Cetak SPBE
    </a>
    <a href="{{ route('dashboard.agen.operasional.sj.cetak-distribusi', $suratJalan) }}" target="_blank"
       style="border:1px solid #059669;color:#059669;background:none;border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
      🖨 Cetak Distribusi
    </a>
    @endif
    @if($suratJalan->status === 'aktif')
    <button onclick="openModal('modal-batal')"
            style="border:1px solid #DC2626;color:#DC2626;background:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">
      Batalkan SJ
    </button>
    @endif
  </div>
</div>

@if($suratJalan->status === 'batal')
<div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#991B1B">
  <strong>SJ Dibatalkan</strong> — {{ $suratJalan->alasan_batal }}
</div>
@endif

{{-- Info cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
  @foreach([
    ['Armada', $suratJalan->armada?->no_polisi ?? '—', '#3B82F6'],
    ['Sopir',  $suratJalan->sopir?->nama_karyawan ?? '—', '#7C3AED'],
    ['Kernet', $suratJalan->kernet?->nama_karyawan ?? '—', '#6B7280'],
    ['Total Refil', number_format($suratJalan->qty_refil).' tb', '#EF4444'],
    ['Dijadwal', number_format($suratJalan->total_terjadwal).' tb', '#F59E0B'],
    ['No. LO', $suratJalan->no_lo ?? '(belum diisi)', '#059669'],
  ] as [$label, $val, $color])
  <div class="stat-card" style="border-left:3px solid {{ $color }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">{{ $label }}</p>
    <p style="font-size:14px;font-weight:700;color:var(--text);margin-top:4px">{{ $val }}</p>
  </div>
  @endforeach
</div>

{{-- Update Nomor LO --}}
@if($suratJalan->status === 'aktif')
<div class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span style="font-size:13px;color:var(--muted)">Nomor LO (diisi setelah ambil di SPBE):</span>
  <form action="{{ route('dashboard.agen.operasional.sj.update-lo', $suratJalan) }}" method="POST"
        style="display:flex;gap:8px;flex:1;min-width:200px">
    @csrf @method('PATCH')
    <input name="no_lo" value="{{ $suratJalan->no_lo }}" placeholder="Nomor Loading Order"
           style="flex:1;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none;font-family:monospace">
    <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:13px;cursor:pointer">Simpan LO</button>
  </form>
</div>
@endif

{{-- Tabel distribusi --}}
<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Jadwal Distribusi ke Pangkalan</h2>
    <span style="font-size:12px;color:var(--muted)">{{ $suratJalan->details->count() }} pangkalan</span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['#','Pangkalan','Qty Jadwal','Qty Terima','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($suratJalan->details->sortBy('urutan') as $i => $d)
      @php
        $ds = match($d->status) {
          'terjadwal' => 'background:#FEF3C7;color:#92400E',
          'terkirim'  => 'background:#D1FAE5;color:#065F46',
          'sebagian'  => 'background:#DBEAFE;color:#1E40AF',
          'dialihkan' => 'background:#EDE9FE;color:#5B21B6',
          'batal'     => 'background:#FEE2E2;color:#991B1B',
          default     => '',
        };
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;color:var(--muted)">{{ $d->urutan }}</td>
        <td style="padding:11px 14px;font-weight:600;color:var(--text)">
          {{ $d->pangkalan?->nama_pangkalan }}
          <span style="display:block;font-size:11px;color:var(--muted);font-family:monospace">{{ $d->pangkalan?->no_reg }}</span>
        </td>
        <td style="padding:11px 14px;font-weight:700;text-align:right">{{ number_format($d->qty_jadwal) }}</td>
        <td style="padding:11px 14px;text-align:right;color:{{ $d->qty_terima > 0 ? 'var(--text)' : 'var(--muted)' }};font-weight:{{ $d->qty_terima > 0 ? '700' : '400' }}">
          {{ $d->qty_terima > 0 ? number_format($d->qty_terima) : '—' }}
        </td>
        <td style="padding:11px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $ds }}">
            {{ ucfirst($d->status) }}
          </span>
          @if($d->status === 'dialihkan' && $d->dialihKe)
            <span style="display:block;font-size:10px;color:var(--muted);margin-top:2px">→ {{ $d->dialihKe->nama_pangkalan }}</span>
          @endif
        </td>
        <td style="padding:11px 14px">
          @if($suratJalan->status === 'aktif' && $d->status === 'terjadwal')
          <button onclick="openRealisasi({{ Js::from($d) }}, {{ Js::from($d->pangkalan?->nama_pangkalan) }})"
                  style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">
            Update
          </button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="border-top:2px solid var(--border);background:var(--bg)">
        <td colspan="2" style="padding:10px 14px;font-weight:600;color:var(--muted)">TOTAL</td>
        <td style="padding:10px 14px;text-align:right;font-weight:700">{{ number_format($suratJalan->details->sum('qty_jadwal')) }}</td>
        <td style="padding:10px 14px;text-align:right;font-weight:700;color:#059669">{{ number_format($suratJalan->details->sum('qty_terima')) }}</td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
</div>

{{-- ── MODAL UPDATE REALISASI ──────────────────────────────────── --}}
<div id="modal-realisasi" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-realisasi')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:460px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Update Realisasi</h3>
        <p id="realisasi-pangkalan-nama" style="font-size:12px;color:var(--muted);margin-top:2px">—</p>
      </div>
      <button onclick="closeModal('modal-realisasi')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-realisasi" method="POST" style="padding:20px 24px">
      @csrf @method('PATCH')
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="flabel">Qty Diterima *</label>
          <input name="qty_terima" id="r_qty_terima" type="number" min="0" required class="finput">
        </div>
        <div>
          <label class="flabel">Status *</label>
          <select name="status" id="r_status" required class="finput" onchange="toggleDialihkan(this.value)">
            <option value="terkirim">Terkirim (penuh)</option>
            <option value="sebagian">Sebagian</option>
            <option value="dialihkan">Dialihkan ke pangkalan lain</option>
            <option value="batal">Batal (tidak jadi kirim)</option>
          </select>
        </div>
        <div id="div-dialihkan" style="display:none;grid-column:1/-1">
          <label class="flabel">Qty Dialihkan</label>
          <input name="qty_dialihkan" type="number" min="0" value="0" class="finput">
          <label class="flabel" style="margin-top:8px">Dialihkan ke Pangkalan</label>
          <select name="dialih_ke_pangkalan_id" class="finput">
            <option value="">-- Pilih Pangkalan --</option>
            @foreach($pangkalans as $p)
              <option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>
            @endforeach
          </select>
        </div>
        <div style="grid-column:1/-1">
          <label class="flabel">Keterangan</label>
          <input name="keterangan" class="finput" placeholder="Catatan opsional">
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">Simpan</button>
        <button type="button" onclick="closeModal('modal-realisasi')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Batal</button>
      </div>
    </form>
  </div>
</div>

{{-- ── MODAL BATAL SJ ───────────────────────────────────────────── --}}
<div id="modal-batal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-batal')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:420px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:15px;font-weight:700;color:#DC2626">Batalkan Surat Jalan</h3>
      <button onclick="closeModal('modal-batal')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.operasional.sj.batal', $suratJalan) }}" method="POST" style="padding:20px 24px">
      @csrf @method('PATCH')
      <p style="font-size:13px;color:var(--muted);margin-bottom:14px">
        SJ <strong style="font-family:monospace;color:var(--text)">{{ $suratJalan->no_sj }}</strong> akan dibatalkan.
        Nomor urut tetap tercatat. Tanggal kitir akan dikembalikan ke status <em>sudah_tebus</em>.
      </p>
      <div style="margin-bottom:16px">
        <label class="flabel">Alasan Pembatalan *</label>
        <input name="alasan_batal" required class="finput" placeholder="Mis: Armada rusak, sopir berhalangan...">
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="background:#DC2626;color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:500;cursor:pointer">Ya, Batalkan</button>
        <button type="button" onclick="closeModal('modal-batal')" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer">Kembali</button>
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
document.addEventListener('keydown', e => { if(e.key==='Escape') ['modal-realisasi','modal-batal'].forEach(closeModal); });

function openRealisasi(detail, nama) {
  document.getElementById('form-realisasi').action =
    `/dashboard/agen/operasional/surat-jalan/detail/${detail.id}/realisasi`;
  document.getElementById('realisasi-pangkalan-nama').textContent = nama;
  document.getElementById('r_qty_terima').value = detail.qty_jadwal;
  document.getElementById('r_status').value     = 'terkirim';
  toggleDialihkan('terkirim');
  openModal('modal-realisasi');
}

function toggleDialihkan(val) {
  document.getElementById('div-dialihkan').style.display =
    val === 'dialihkan' ? 'block' : 'none';
}
</script>
@endpush
