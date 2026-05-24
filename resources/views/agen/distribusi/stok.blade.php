@extends('layouts.app')
@section('title', 'Monitoring Stok')

@section('content')
@include('layouts.partials.distribusi-styles')

{{-- Page Header --}}
<div class="page-header">
  <div>
    <h1 class="page-title">Monitoring Stok</h1>
    <p class="page-sub">Gendongan (armada) · Gudang agen · Titipan antar agen</p>
  </div>
</div>

@if(session('success'))
<div class="alert-banner alert-success">✓ {{ session('success') }}</div>
@endif

{{-- Summary stat cards --}}
<div class="stat-grid">
  @php
    $totalGendongan = $gendongan->flatten()->sum('sisa_akhir');
    $totalGudang    = $gudang->sum('sisa_stok');
    $totalTitipan   = $titipanAktif->count();
  @endphp
  @if($totalGendongan > 0)
  <div class="stat-card" style="border-left:3px solid #D97706">
    <div class="stat-label">Gendongan aktif</div>
    <div class="stat-value" style="color:#D97706">{{ number_format($totalGendongan) }}</div>
    <div class="stat-sub">{{ $gendongan->count() }} armada · wajib habis</div>
  </div>
  @endif
  @if($totalGudang > 0)
  <div class="stat-card" style="border-left:3px solid #7C3AED">
    <div class="stat-label">Stok gudang</div>
    <div class="stat-value" style="color:#7C3AED">{{ number_format($totalGudang) }}</div>
    <div class="stat-sub">tabung isi siap distribusi</div>
  </div>
  @endif
  @if($titipanAktif->isNotEmpty())
  <div class="stat-card" style="border-left:3px solid var(--accent)">
    <div class="stat-label">Titipan aktif</div>
    <div class="stat-value" style="color:var(--accent)">{{ $titipanAktif->count() }}</div>
    <div class="stat-sub">dari agen lain</div>
  </div>
  @endif
</div>

{{-- ── GENDONGAN ──────────────────────────────────────────────── --}}
<div class="section-title">
  ⚡ Stok Armada (Gendongan)
  <span class="badge badge-warn">Wajib habis sebelum DO baru</span>
</div>

@if($gendongan->isEmpty())
<div class="card" style="padding:28px;text-align:center;color:var(--muted);margin-bottom:20px">
  Tidak ada gendongan aktif — semua armada sudah bersih ✓
</div>
@else
<div class="armada-grid">
  @foreach($gendongan as $armadaId => $stoks)
  @php $totalGnd = $stoks->sum('sisa_akhir'); @endphp
  <div class="armada-card">
    <div class="armada-header">
      <div>
        <div class="armada-polisi">{{ $stoks->first()->armada?->no_polisi }}</div>
        <div style="font-size:11px;opacity:.75;margin-top:2px">{{ $stoks->count() }} trip tersisa</div>
      </div>
      <div style="text-align:right">
        <div class="armada-qty">{{ number_format($totalGnd) }}</div>
        <div style="font-size:10px;opacity:.65">tabung</div>
      </div>
    </div>
    @foreach($stoks as $s)
    <div class="armada-trip">
      <div>
        <span style="color:var(--muted)">SJ: </span>
        <span style="font-family:monospace;color:var(--accent)">{{ $s->sjHeader?->no_sj }}</span>
        <span style="display:block;color:var(--muted);font-size:11px">{{ $s->tanggal->format('d/m/Y') }}</span>
      </div>
      <div>
        <span style="font-size:16px;font-weight:700;color:#F59E0B">{{ number_format($s->sisa_akhir) }}</span>
        <span style="font-size:10px;color:var(--muted)"> tb</span>
      </div>
    </div>
    @endforeach
    <div class="armada-action">
      <button onclick="bukaModalGendongan({{ $armadaId }}, '{{ $stoks->first()->armada?->no_polisi }}', {{ $totalGnd }}, {{ $stoks->first()->id }})"
              class="btn btn-warn btn-block">
        Masukkan ke SJ →
      </button>
    </div>
  </div>
  @endforeach
</div>
@endif

{{-- ── STOK GUDANG ──────────────────────────────────────────────── --}}
<div class="section-title">
  🏪 Stok Gudang
  <span class="badge badge-purple">Bisa diambil kapan saja</span>
</div>

@if($gudang->isEmpty())
<div class="card" style="padding:28px;text-align:center;color:var(--muted);margin-bottom:20px">
  Tidak ada stok di gudang
</div>
@else
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tgl Masuk</th>
          <th>Sumber</th>
          <th style="text-align:right">Sisa Stok</th>
          <th>Keterangan</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($gudang as $g)
        <tr>
          <td style="color:var(--muted)">{{ $g->tgl_masuk->format('d/m/Y') }}</td>
          <td>
            @if($g->sumber === 'titipan_agen')
              <span class="badge badge-green">🤝 Titipan {{ $g->agenAsal?->nama_agen }}</span>
            @elseif($g->sumber === 'sisa_sj')
              <span class="badge badge-purple">📦 Sisa SJ</span>
            @else
              <span style="color:var(--muted)">Manual</span>
            @endif
          </td>
          <td style="text-align:right">
            <strong style="font-size:15px;color:#7C3AED">{{ number_format($g->sisa_stok) }}</strong>
            <span style="font-size:11px;color:var(--muted)"> tb</span>
          </td>
          <td style="font-size:12px;color:var(--muted)">{{ $g->keterangan }}</td>
          <td>
            <button onclick="bukaModalAmbilGudang({{ $g->id }}, {{ $g->sisa_stok }}, '{{ $g->tgl_masuk->format('d/m/Y') }}')"
                    class="btn btn-sm btn-purple">
              Ambil
            </button>
          </td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr style="background:var(--bg)">
          <td colspan="2" style="font-weight:600;color:var(--muted)">TOTAL TERSEDIA</td>
          <td style="text-align:right">
            <strong style="font-size:16px;color:#7C3AED">{{ number_format($gudang->sum('sisa_stok')) }}</strong>
            <span style="font-size:11px;color:var(--muted)"> tb</span>
          </td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
@endif

{{-- ── TITIPAN ANTAR AGEN ──────────────────────────────────────── --}}
@if($titipanAktif->isNotEmpty())
<div class="section-title">🤝 Titipan Antar Agen (Aktif)</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tgl Titip</th>
          <th>Dari Agen</th>
          <th>Ke Agen</th>
          <th style="text-align:right">Qty Isi</th>
          <th style="text-align:right">Pinjam Kosong</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($titipanAktif as $t)
        <tr>
          <td style="color:var(--muted)">{{ $t->tgl_titip->format('d/m/Y') }}</td>
          <td style="font-weight:600">{{ $t->agenAsal?->nama_agen }}</td>
          <td style="font-weight:600">{{ $t->agenTujuan?->nama_agen }}</td>
          <td style="text-align:right;font-weight:700;color:#059669">{{ number_format($t->qty_tabung_isi) }} tb</td>
          <td style="text-align:right;color:{{ $t->qty_tabung_kosong > 0 ? '#DC2626' : 'var(--muted)' }}">
            {{ $t->qty_tabung_kosong > 0 ? number_format($t->qty_tabung_kosong).' tb' : '—' }}
          </td>
          <td><span class="badge badge-blue">Aktif</span></td>
          <td>
            <form action="{{ route('dashboard.agen.distribusi.selesai-antar-agen', $t) }}" method="POST" style="display:inline">
              @csrf @method('PATCH')
              <button type="submit" class="btn btn-sm btn-ghost"
                      onclick="return confirm('Tandai transaksi ini selesai?')">Selesai</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

{{-- ── MODAL AMBIL GUDANG ──────────────────────────────────────── --}}
<div id="modal-ambil-gudang" class="modal-overlay" onclick="this.classList.remove('open')">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3 class="modal-title">Ambil dari Gudang</h3>
      <button class="modal-close" onclick="document.getElementById('modal-ambil-gudang').classList.remove('open')">×</button>
    </div>
    <div class="modal-body">
      <form action="{{ route('dashboard.agen.distribusi.ambil-gudang') }}" method="POST">
        @csrf
        <input type="hidden" name="gudang_stok_id" id="ag_gudang_id">
        <div class="qty-display">
          <div class="qty-display-label">Tersedia</div>
          <div class="qty-display-value" id="ag_tersedia">0</div>
          <div class="qty-display-label">tabung</div>
        </div>
        <div class="form-group">
          <label class="form-label">Masukkan ke SJ</label>
          <select name="sj_header_id" class="form-select">
            <option value="">-- Pilih SJ Aktif --</option>
            @foreach(\App\Models\SuratJalanHeader::where('status','aktif')->orderByDesc('tanggal')->get() as $sj)
              <option value="{{ $sj->id }}">{{ $sj->no_sj }} · {{ $sj->tanggal->format('d/m/Y') }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Qty Diambil</label>
          <input type="number" name="qty" id="ag_qty" min="1" class="form-input-lg">
        </div>
        <button type="submit" class="btn btn-purple btn-block" style="padding:11px;font-size:14px">
          Ambil dari Gudang
        </button>
      </form>
    </div>
  </div>
</div>

{{-- ── MODAL GENDONGAN KE SJ ──────────────────────────────────── --}}
<div id="modal-gendongan" class="modal-overlay" onclick="this.classList.remove('open')">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Gendongan → SJ Aktif</h3>
        <p id="gnd_armada_label" style="font-size:12px;color:var(--muted)">Armada</p>
      </div>
      <button class="modal-close" onclick="document.getElementById('modal-gendongan').classList.remove('open')">×</button>
    </div>
    <div class="modal-body">
      <form action="{{ route('dashboard.agen.distribusi.konfirmasi-gendongan') }}" method="POST">
        @csrf
        <input type="hidden" name="stok_armada_id" id="gnd_stok_id">
        <div class="qty-display" style="background:#FEF3C7">
          <div class="qty-display-label" style="color:#92400E">Gendongan Tersedia</div>
          <div class="qty-display-value" id="gnd_tersedia" style="color:#D97706">0</div>
          <div class="qty-display-label" style="color:#92400E">tabung · wajib dihabiskan</div>
        </div>
        <div class="form-group">
          <label class="form-label">Masukkan ke SJ</label>
          <select name="sj_header_id" class="form-select">
            <option value="">-- Pilih SJ Aktif --</option>
            @foreach(\App\Models\SuratJalanHeader::where('status','aktif')->orderByDesc('tanggal')->get() as $sj)
              <option value="{{ $sj->id }}">{{ $sj->no_sj }} · {{ $sj->tanggal->format('d/m/Y') }} · {{ $sj->armada?->no_polisi }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Qty Gendongan Masuk</label>
          <input type="number" name="qty_gendongan_masuk" id="gnd_qty" min="1" class="form-input-lg">
        </div>
        <button type="submit" class="btn btn-warn btn-block" style="padding:11px;font-size:14px">
          Konfirmasi Gendongan Masuk
        </button>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
function bukaModalAmbilGudang(id, tersedia, tgl) {
  document.getElementById('ag_gudang_id').value = id;
  document.getElementById('ag_tersedia').textContent = tersedia.toLocaleString('id');
  document.getElementById('ag_qty').value = tersedia;
  document.getElementById('ag_qty').max = tersedia;
  document.getElementById('modal-ambil-gudang').classList.add('open');
}
function bukaModalGendongan(armadaId, polisi, total, stokId) {
  document.getElementById('gnd_stok_id').value = stokId;
  document.getElementById('gnd_armada_label').textContent = polisi;
  document.getElementById('gnd_tersedia').textContent = total.toLocaleString('id');
  document.getElementById('gnd_qty').value = total;
  document.getElementById('gnd_qty').max = total;
  document.getElementById('modal-gendongan').classList.add('open');
}
// Tutup modal dengan Escape
document.addEventListener('keydown', e => {
  if(e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  }
});
</script>
@endpush
