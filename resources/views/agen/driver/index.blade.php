@extends('layouts.driver')

@section('topbar-title', 'Surat Jalan Hari Ini')
@section('topbar-sub', \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y'))

@section('content')

@if(session('success'))
<div style="margin:12px;padding:12px 14px;background:#D1FAE5;border-radius:10px;color:#065F46;font-size:13px;font-weight:500">
  ✓ {{ session('success') }}
</div>
@endif

{{-- Pilih tanggal --}}
<div style="padding:12px 12px 0">
  <form method="GET" style="display:flex;gap:8px">
    <input type="date" name="tanggal" value="{{ $tanggal }}"
           style="flex:1;border:1.5px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;color:var(--text);background:var(--surface);outline:none">
    <button type="submit" class="btn btn-primary" style="padding:10px 16px;border-radius:10px">
      Cari
    </button>
  </form>
</div>

@if($sjList->isEmpty())
<div style="text-align:center;padding:60px 20px;color:var(--muted)">
  <div style="font-size:48px;margin-bottom:12px">📦</div>
  <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px">Tidak ada tugas hari ini</div>
  <div style="font-size:13px">Tidak ada surat jalan aktif untuk tanggal ini</div>
</div>
@endif

@foreach($sjList as $sj)
@php
  $totalJadwal  = $sj->details->sum('qty_jadwal');
  $totalTerima  = $sj->details->sum('qty_terima');
  $belum        = $sj->details->where('status','terjadwal')->count();
  $pct          = $totalJadwal > 0 ? round($totalTerima/$totalJadwal*100) : 0;
@endphp

<div class="card">
  {{-- Header SJ --}}
  <div style="padding:14px;background:linear-gradient(135deg,#1E40AF,#2563EB);color:#fff">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div style="font-family:monospace;font-size:16px;font-weight:700">{{ $sj->no_sj }}</div>
        <div style="font-size:11px;opacity:.8;margin-top:2px">
          {{ $sj->kitirDetail?->kitir?->spbe?->nama_spbe }}
          · SA# {{ $sj->kitirDetail?->kitir?->nomor_sa }}
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:22px;font-weight:700">{{ number_format($sj->qty_refil) }}</div>
        <div style="font-size:10px;opacity:.7">tabung refil</div>
      </div>
    </div>

    {{-- Armada & crew --}}
    <div style="display:flex;gap:12px;margin-top:10px;font-size:12px;opacity:.9;flex-wrap:wrap">
      <span>🚛 {{ $sj->armada?->no_polisi }}</span>
      <span>👤 {{ $sj->sopir?->nama_karyawan }}</span>
      @if($sj->kernet)
        <span>👥 {{ $sj->kernet->nama_karyawan }}</span>
      @endif
      @if($sj->no_lo)
        <span>📋 LO: {{ $sj->no_lo }}</span>
      @endif
    </div>

    {{-- Progress --}}
    <div style="margin-top:12px">
      <div style="display:flex;justify-content:space-between;font-size:11px;opacity:.8;margin-bottom:5px">
        <span>Progress distribusi</span>
        <span>{{ $totalTerima }}/{{ $totalJadwal }} tabung ({{ $pct }}%)</span>
      </div>
      <div style="height:6px;background:rgba(255,255,255,.2);border-radius:3px">
        <div style="height:6px;background:{{ $pct==100?'#34D399':'#FCD34D' }};border-radius:3px;width:{{ $pct }}%;transition:width .3s"></div>
      </div>
    </div>
  </div>

  {{-- Daftar pangkalan --}}
  @foreach($sj->details->sortBy('urutan') as $d)
  @php
    $isDone   = in_array($d->status, ['terkirim','dialihkan','batal']);
    $isFull   = $d->status === 'terkirim' && $d->qty_terima >= $d->qty_jadwal;
  @endphp
  <div class="pkln-row" style="{{ $isDone ? 'opacity:.65' : '' }}">
    {{-- Nomor urut / centang --}}
    <div class="pkln-urut" style="{{ $isFull ? 'background:#059669' : ($isDone ? 'background:#94A3B8' : '') }}">
      @if($isFull) ✓
      @elseif($isDone) —
      @else {{ $d->urutan }}
      @endif
    </div>

    {{-- Info pangkalan --}}
    <div class="pkln-info">
      <div class="pkln-nama">{{ $d->pangkalan?->nama_pangkalan }}</div>
      <div class="pkln-sub">
        {{ $d->pangkalan?->no_reg }}
        @if($d->status !== 'terjadwal')
          · <span style="color:{{ $isFull?'var(--success)':'var(--warning)' }}">
            {{ $d->qty_terima }} diterima
          </span>
        @endif
        @if($d->keterangan)
          · <span style="color:var(--muted);font-style:italic">{{ Str::limit($d->keterangan,30) }}</span>
        @endif
      </div>
    </div>

    {{-- Qty & aksi --}}
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px">
      @php
          $sudahDialihkan = $d->sisaDistribusi
              ? $d->sisaDistribusi->where('tipe','alih_pangkalan')->sum('qty')
              : 0;
          $sudahDisalurkan = $d->qty_terima + $sudahDialihkan;
          $sisaArmada = max(0, $d->qty_jadwal - $sudahDisalurkan);
        @endphp
      <div class="pkln-qty">
        @if($d->status === 'terjadwal')
          <div class="pkln-qty-num">{{ number_format($d->qty_jadwal) }}</div>
          <div class="pkln-qty-label">tabung</div>
        @else
          <div class="pkln-qty-num" style="color:{{ $sisaArmada>0?'var(--warning)':'var(--success)' }}">
            {{ number_format($sisaArmada) }}
          </div>
          <div class="pkln-qty-label" style="color:{{ $sisaArmada>0?'var(--warning)':'var(--success)' }}">
            {{ $sisaArmada>0 ? 'sisa' : 'lunas' }}
          </div>
        @endif
      </div>
      @if(!$isDone)
        <button onclick="bukaInput({{ $d->id }}, '{{ addslashes($d->pangkalan?->nama_pangkalan) }}', {{ $d->qty_jadwal }}, 0, '')"
                class="btn btn-primary"
                style="padding:6px 14px;border-radius:8px;font-size:12px">
          Input
        </button>
      @elseif($d->status !== 'batal')
        <button onclick="bukaInput({{ $d->id }}, '{{ addslashes($d->pangkalan?->nama_pangkalan) }}', {{ $d->qty_jadwal }}, {{ $d->qty_terima }}, '{{ $d->keterangan }}')"
                class="btn btn-outline"
                style="padding:6px 14px;border-radius:8px;font-size:12px">
          Edit
        </button>
      @endif
    </div>
  </div>
  @endforeach

  {{-- Footer SJ --}}
  @if($belum > 0)
  <div style="padding:10px 14px;background:#FEF9C3;font-size:12px;color:#92400E;display:flex;align-items:center;gap:6px">
    ⏳ <strong>{{ $belum }}</strong> pangkalan belum dilaporkan
  </div>
  @else
  <div style="padding:10px 14px;background:#D1FAE5;font-size:12px;color:#065F46;display:flex;align-items:center;gap:6px">
    ✓ Semua pangkalan sudah dilaporkan
  </div>
  @endif
</div>
@endforeach

{{-- ── BOTTOM SHEET INPUT REALISASI ──────────────────────────── --}}
<div class="sheet-overlay" id="sheet-input" onclick="tutupSheet()">
  <div class="sheet" onclick="event.stopPropagation()">
    <div class="sheet-handle"></div>
    <div class="sheet-title" id="sheet-nama-pangkalan">Input Realisasi</div>

    <div class="sheet-body">
      <form id="form-driver-input" method="POST">
        @csrf @method('PUT')

        {{-- Qty terima dengan tombol +/- --}}
        <div style="margin-bottom:16px">
          <label class="flabel">Qty Diterima</label>
          <div class="qty-input-wrap">
            <button type="button" class="qty-btn" onclick="ubahQty(-10)">−</button>
            <input type="number" name="qty_terima" id="d_qty_terima"
                   class="qty-input-big" min="0" oninput="updateStatus()">
            <button type="button" class="qty-btn" onclick="ubahQty(10)">+</button>
          </div>
          <div id="d_info_jadwal" style="text-align:center;margin-top:8px;font-size:12px;color:var(--muted)"></div>
        </div>

        {{-- Tombol cepat --}}
        <div class="pill-row">
          <button type="button" class="pill" id="pill-full" onclick="setFull()">✓ Full Jadwal</button>
          <button type="button" class="pill" id="pill-0"    onclick="setKosong()">✗ Tidak Terima</button>
        </div>

        {{-- Status (hidden, auto) --}}
        <input type="hidden" name="status" id="d_status" value="terkirim">

        {{-- Keterangan --}}
        <div style="margin-bottom:20px">
          <label class="flabel">Keterangan / Catatan</label>
          <textarea name="keterangan" id="d_keterangan"
                    rows="3" placeholder="Tulis catatan jika ada (opsional)..."
                    style="width:100%;border:1.5px solid var(--border);border-radius:10px;padding:12px;font-size:14px;color:var(--text);resize:none;outline:none;font-family:inherit"></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="btn-simpan">
          Simpan Laporan
        </button>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
let qtyJadwal = 0;
const BASE = '/dashboard/agen/distribusi/detail/';

function bukaInput(detailId, namaPangkalan, jadwal, qtyTerima = null, keterangan = '') {
  qtyJadwal = jadwal;

  document.getElementById('form-driver-input').action = BASE + detailId;
  document.getElementById('sheet-nama-pangkalan').textContent = namaPangkalan;
  document.getElementById('d_qty_terima').value = qtyTerima !== null ? qtyTerima : jadwal;
  document.getElementById('d_keterangan').value = keterangan;
  document.getElementById('d_info_jadwal').textContent = `Jadwal: ${jadwal.toLocaleString('id')} tabung`;

  updateStatus();
  document.getElementById('sheet-input').classList.add('open');
  document.body.style.overflow = 'hidden';

  // Focus qty input
  setTimeout(() => document.getElementById('d_qty_terima').select(), 300);
}

function tutupSheet() {
  document.getElementById('sheet-input').classList.remove('open');
  document.body.style.overflow = '';
}

function ubahQty(delta) {
  const el  = document.getElementById('d_qty_terima');
  const val = Math.max(0, Math.min(qtyJadwal, parseInt(el.value||0) + delta));
  el.value  = val;
  updateStatus();
}

function setFull() {
  document.getElementById('d_qty_terima').value = qtyJadwal;
  updateStatus();
}

function setKosong() {
  document.getElementById('d_qty_terima').value = 0;
  updateStatus();
}

function updateStatus() {
  const qty = parseInt(document.getElementById('d_qty_terima').value || 0);
  let status = 'terkirim';
  if (qty <= 0)           status = 'batal';
  else if (qty < qtyJadwal) status = 'sebagian';
  document.getElementById('d_status').value = status;

  // Visual feedback tombol
  const pillFull = document.getElementById('pill-full');
  const pill0    = document.getElementById('pill-0');
  pillFull.className = 'pill' + (qty >= qtyJadwal ? ' active' : '');
  pill0.className    = 'pill' + (qty <= 0 ? ' active' : '');

  // Info sisa
  const sisa = qtyJadwal - qty;
  const info = document.getElementById('d_info_jadwal');
  if (sisa > 0 && qty > 0)
    info.textContent = `Jadwal: ${qtyJadwal} tabung — Sisa: ${sisa} tabung`;
  else if (qty >= qtyJadwal)
    info.textContent = `✓ Full — semua ${qtyJadwal} tabung terkirim`;
  else
    info.textContent = `Jadwal: ${qtyJadwal} tabung`;
}

// Swipe down to close sheet
let startY = 0;
document.getElementById('sheet-input').addEventListener('touchstart', e => { startY = e.touches[0].clientY; });
document.getElementById('sheet-input').addEventListener('touchend', e => {
  if (e.changedTouches[0].clientY - startY > 80) tutupSheet();
});
</script>
@endpush
