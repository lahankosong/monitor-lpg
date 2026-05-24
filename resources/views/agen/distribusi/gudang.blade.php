@extends('layouts.app')
@section('title', 'Gudang Tabung')

@section('content')
@include('layouts.partials.distribusi-styles')

@php $alertKosong = $saldoKosong < 100; @endphp

{{-- Page Header --}}
<div class="page-header">
  <div>
    <h1 class="page-title">Gudang Tabung</h1>
    <p class="page-sub">Buffer kosong · Tabung isi · Kepemilikan & pinjaman</p>
  </div>
  <div class="btn-group">
    <button onclick="openModal('modal-beli')" class="btn btn-primary">+ Beli Tabung Baru</button>
    <button onclick="openModal('modal-opname')" class="btn btn-outline" style="color:#F97316;border-color:#F97316">📋 Opname Fisik</button>
    <button onclick="openModal('modal-pinjam')" class="btn btn-outline">📄 Catat Pinjaman</button>
  </div>
</div>

{{-- Alerts --}}
@if($alertKosong)
<div class="alert-banner alert-danger">
  <span>⚠️</span>
  <div>
    <strong>Stok tabung kosong (buffer) rendah!</strong>
    Saat ini hanya <strong>{{ number_format($saldoKosong) }} tabung</strong> —
    minimum yang direkomendasikan adalah 100 tabung. Segera beli tabung baru dari Pertamina.
  </div>
</div>
@endif

@if($hampirKadaluarsa->isNotEmpty())
<div class="alert-banner alert-warn">
  ⚠ <strong>{{ $hampirKadaluarsa->count() }} surat perjanjian pinjaman</strong>
  akan kadaluarsa dalam 30 hari — perlu diperbarui.
</div>
@endif

{{-- Stat Cards --}}
<div class="stat-grid">
  <div class="stat-card" style="border-left:4px solid {{ $alertKosong ? '#DC2626' : 'var(--accent)' }}">
    <div class="stat-label">Buffer Kosong</div>
    <div class="stat-value" style="color:{{ $alertKosong ? '#DC2626' : 'var(--accent)' }}">{{ number_format($saldoKosong) }}</div>
    <div class="stat-sub">tabung kosong di gudang @if($alertKosong)<span style="color:#DC2626;font-weight:600"> ⚠ rendah</span>@endif</div>
  </div>
  <div class="stat-card" style="border-left:4px solid #059669">
    <div class="stat-label">Tabung Isi</div>
    <div class="stat-value" style="color:#059669">{{ number_format($saldoIsi) }}</div>
    <div class="stat-sub">tabung isi di gudang</div>
  </div>
  <div class="stat-card" style="border-left:4px solid #F97316">
    <div class="stat-label">Di Armada</div>
    <div class="stat-value" style="color:#F97316">{{ number_format($kosongDiArmada) }}</div>
    <div class="stat-sub">tabung kosong sedang distribusi</div>
  </div>
  <div class="stat-card" style="border-left:4px solid #F59E0B">
    <div class="stat-label">Dipinjam</div>
    <div class="stat-value" style="color:#F59E0B">{{ number_format($totalPinjaman) }}</div>
    <div class="stat-sub">tabung di {{ $pinjamanAktif->count() }} pihak</div>
  </div>
  <div class="stat-card" style="border-left:4px solid #8B5CF6">
    <div class="stat-label">Total Kepemilikan</div>
    <div class="stat-value" style="color:#8B5CF6">{{ number_format($totalKepemilikan) }}</div>
    <div class="stat-sub">buffer + isi + pinjaman + armada</div>
  </div>
</div>

{{-- Bar visualisasi kepemilikan --}}
@php
  $total     = max($totalKepemilikan, 1);
  $pctKosong = round($saldoKosong    / $total * 100);
  $pctIsi    = round($saldoIsi       / $total * 100);
  $pctArmada = round($kosongDiArmada / $total * 100);
  $pctPinjam = round($totalPinjaman  / $total * 100);
@endphp
<div class="card" style="padding:16px 18px;margin-bottom:16px">
  <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:10px">Distribusi Kepemilikan Tabung</div>
  <div style="height:24px;border-radius:6px;overflow:hidden;display:flex;background:var(--border)">
    @if($saldoKosong > 0)
    <div style="width:{{ $pctKosong }}%;background:var(--accent);display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;padding:0 4px;white-space:nowrap">@if($pctKosong > 8)Buffer {{ $pctKosong }}%@endif</span>
    </div>
    @endif
    @if($saldoIsi > 0)
    <div style="width:{{ $pctIsi }}%;background:#059669;display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;padding:0 4px;white-space:nowrap">@if($pctIsi > 8)Isi {{ $pctIsi }}%@endif</span>
    </div>
    @endif
    @if($kosongDiArmada > 0)
    <div style="width:{{ $pctArmada }}%;background:#F97316;display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;padding:0 4px;white-space:nowrap">@if($pctArmada > 8)Armada {{ $pctArmada }}%@endif</span>
    </div>
    @endif
    @if($totalPinjaman > 0)
    <div style="width:{{ $pctPinjam }}%;background:#F59E0B;display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;padding:0 4px;white-space:nowrap">@if($pctPinjam > 8)Pinjam {{ $pctPinjam }}%@endif</span>
    </div>
    @endif
  </div>
  <div style="display:flex;gap:16px;margin-top:8px;font-size:11px;color:var(--muted);flex-wrap:wrap">
    <span>🔵 Buffer: {{ number_format($saldoKosong) }}</span>
    <span>🟢 Isi: {{ number_format($saldoIsi) }}</span>
    <span>🟠 Armada: {{ number_format($kosongDiArmada) }}</span>
    <span>🟡 Pinjam: {{ number_format($totalPinjaman) }}</span>
  </div>
</div>

{{-- Tab navigasi --}}
<div class="tab-bar" style="margin-bottom:16px">
  <button class="tab-btn active" id="tab-mutasi"  onclick="switchTab('mutasi')">Mutasi Stok</button>
  <button class="tab-btn"        id="tab-pinjaman" onclick="switchTab('pinjaman')">Pinjaman Aktif</button>
  <button class="tab-btn"        id="tab-armada"   onclick="switchTab('armada')">Alokasi Armada</button>
</div>

{{-- VIEW: Mutasi Stok --}}
<div id="view-mutasi">
  <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <button onclick="openModal('modal-keluar-kosong')" class="btn btn-outline btn-sm">↑ Keluar Kosong (DO)</button>
    <button onclick="openModal('modal-masuk-isi')"    class="btn btn-outline btn-sm">↓ Masuk Isi</button>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th><th>Tipe</th><th>Keterangan</th>
            <th style="text-align:right">Kosong</th>
            <th style="text-align:right">Isi</th>
            <th>Ref</th>
          </tr>
        </thead>
        <tbody>
          @forelse($mutasi as $m)
          <tr>
            <td style="white-space:nowrap;color:var(--muted)">{{ $m->tanggal->format('d/m/Y') }}</td>
            <td>
              @php
                $tipeLabel = ['masuk_isi'=>'Masuk Isi','keluar_kosong'=>'Keluar Kosong DO',
                  'beli'=>'Beli Baru','opname'=>'Opname Fisik','pinjam_keluar'=>'Pinjam Keluar',
                  'pinjam_kembali'=>'Pinjam Kembali','alokasi_armada'=>'Alokasi Armada',
                  'kembali_armada'=>'Kembali Armada'];
                $tipeColor = str_contains($m->tipe,'masuk')||str_contains($m->tipe,'beli')||str_contains($m->tipe,'kembali')
                  ? 'badge-green' : 'badge-danger';
              @endphp
              <span class="badge {{ $tipeColor }}">{{ $tipeLabel[$m->tipe] ?? $m->tipe }}</span>
            </td>
            <td style="font-size:12px;color:var(--muted)">{{ $m->keterangan }}</td>
            <td style="text-align:right;font-family:monospace">
              @if($m->delta_kosong != 0)
                <span style="color:{{ $m->delta_kosong > 0 ? '#059669' : '#DC2626' }}">
                  {{ $m->delta_kosong > 0 ? '+' : '' }}{{ number_format($m->delta_kosong) }}
                </span>
              @else —
              @endif
            </td>
            <td style="text-align:right;font-family:monospace">
              @if($m->delta_isi != 0)
                <span style="color:{{ $m->delta_isi > 0 ? '#059669' : '#DC2626' }}">
                  {{ $m->delta_isi > 0 ? '+' : '' }}{{ number_format($m->delta_isi) }}
                </span>
              @else —
              @endif
            </td>
            <td style="font-size:11px;font-family:monospace;color:var(--muted)">{{ $m->ref_no }}</td>
          </tr>
          @empty
          <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Belum ada mutasi stok</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if(method_exists($mutasi,'links'))
    <div style="padding:10px 14px;border-top:1px solid var(--border)">{{ $mutasi->links() }}</div>
    @endif
  </div>
</div>

{{-- VIEW: Pinjaman Aktif --}}
<div id="view-pinjaman" style="display:none">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pihak Peminjam</th><th>Jenis</th>
            <th style="text-align:right">Qty Aktif</th>
            <th>Berlaku Sampai</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($pinjamanAktif as $p)
          @php
            $hampir  = $p->tgl_berlaku_sampai && $p->tgl_berlaku_sampai->diffInDays(now()) <= 30 && $p->tgl_berlaku_sampai->isFuture();
            $expired = $p->tgl_berlaku_sampai && $p->tgl_berlaku_sampai->isPast();
          @endphp
          <tr>
            <td>
              <div style="font-weight:600">{{ $p->pangkalan?->nama_pangkalan ?? $p->nama_cabang ?? '—' }}</div>
              @if($p->pangkalan?->no_reg)
              <div style="font-size:11px;font-family:monospace;color:var(--muted)">{{ $p->pangkalan->no_reg }}</div>
              @endif
            </td>
            <td><span class="badge badge-blue">{{ $p->jenis_pihak === 'cabang' ? 'Cabang' : 'Pangkalan' }}</span></td>
            <td style="text-align:right;font-size:16px;font-weight:700;color:#F59E0B">{{ number_format($p->qty_aktif) }}</td>
            <td style="font-size:12px">
              @if($p->tgl_berlaku_sampai)
                <span style="color:{{ $expired ? '#DC2626' : ($hampir ? '#F59E0B' : 'var(--text)') }}">
                  {{ $p->tgl_berlaku_sampai->format('d/m/Y') }}
                  @if($expired) <span class="badge badge-danger">Expired</span>
                  @elseif($hampir) <span class="badge badge-warn">{{ $p->tgl_berlaku_sampai->diffInDays(now()) }} hari lagi</span>
                  @endif
                </span>
              @else —
              @endif
            </td>
            <td><span class="badge badge-green">Aktif</span></td>
            <td>
              <button onclick="bukaKembali({{ $p->id }}, '{{ addslashes($p->pangkalan?->nama_pangkalan ?? $p->nama_cabang) }}', {{ $p->qty_aktif }})"
                      class="btn btn-sm btn-ghost">Catat Kembali</button>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Tidak ada pinjaman aktif</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- VIEW: Alokasi Armada --}}
<div id="view-armada" style="display:none">
  <div style="margin-bottom:10px">
    <button onclick="openModal('modal-alokasi-armada')" class="btn btn-primary btn-sm">+ Alokasi ke Armada</button>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Armada</th><th>Sopir</th>
            <th style="text-align:right">Tabung Dialokasi</th>
            <th>Tahun Tabung</th><th>Layak s/d</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($alokasiArmada as $a)
          <tr>
            <td style="font-family:monospace;font-weight:700;color:var(--accent)">{{ $a->armada?->no_polisi }}</td>
            <td style="font-size:12px">{{ $a->armada?->sopirAktif?->nama_karyawan ?? '—' }}</td>
            <td style="text-align:right;font-size:16px;font-weight:700;color:var(--text)">{{ number_format($a->qty_tabung) }}</td>
            <td style="text-align:center">{{ $a->tahun_tabung ?? '—' }}</td>
            <td style="font-size:12px">
              @if($a->tgl_layak_sampai)
                @php $expired = \Carbon\Carbon::parse($a->tgl_layak_sampai)->isPast(); @endphp
                <span style="color:{{ $expired ? '#DC2626' : '#059669' }}">
                  {{ \Carbon\Carbon::parse($a->tgl_layak_sampai)->format('d/m/Y') }}
                  @if($expired)<span class="badge badge-danger" style="margin-left:4px">Expired</span>@endif
                </span>
              @else —
              @endif
            </td>
            <td>
              <button onclick="bukaKembalikanArmada({{ $a->armada_id }}, '{{ $a->armada?->no_polisi }}', {{ $a->qty_tabung }})"
                      class="btn btn-sm btn-outline">Kembalikan</button>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">Belum ada alokasi armada</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ══ MODALS ════════════════════════════════════════════════════ --}}

@php
$modalStyle = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px';
$boxStyle   = 'background:var(--surface);border-radius:14px;width:100%;max-width:420px;max-height:90vh;overflow-y:auto';
@endphp

{{-- Modal: Beli Tabung Baru --}}
<div id="modal-beli" style="{{ $modalStyle }}" onclick="if(event.target===this)closeModal('modal-beli')">
  <div style="{{ $boxStyle }}" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3 class="modal-title">Beli Tabung Baru</h3>
      <button class="modal-close" onclick="closeModal('modal-beli')">×</button>
    </div>
    <div class="modal-body">
      <form action="{{ route('dashboard.agen.distribusi.gudang.beli') }}" method="POST">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
          <div>
            <label class="form-label">Tanggal *</label>
            <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="form-select">
          </div>
          <div>
            <label class="form-label">Qty Kosong *</label>
            <input type="number" name="qty_kosong" required min="1" class="form-select" placeholder="0">
          </div>
          <div style="grid-column:span 2">
            <label class="form-label">No. Referensi / Faktur</label>
            <input type="text" name="ref_no" class="form-select" placeholder="INV-...">
          </div>
          <div style="grid-column:span 2">
            <label class="form-label">Keterangan</label>
            <input type="text" name="keterangan" class="form-select" placeholder="Pembelian tabung baru dari Pertamina">
          </div>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">Simpan</button>
          <button type="button" onclick="closeModal('modal-beli')" class="btn btn-outline">Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal: Opname Fisik --}}
<div id="modal-opname" style="{{ $modalStyle }}" onclick="if(event.target===this)closeModal('modal-opname')">
  <div style="{{ $boxStyle }}" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Opname Stok Fisik</h3>
        <p style="font-size:12px;color:var(--muted);margin-top:2px">Sistem: Kosong {{ number_format($saldoKosong) }} · Isi {{ number_format($saldoIsi) }}</p>
      </div>
      <button class="modal-close" onclick="closeModal('modal-opname')">×</button>
    </div>
    <div class="modal-body">
      <form action="{{ route('dashboard.agen.distribusi.gudang.opname') }}" method="POST">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
          <div>
            <label class="form-label">Stok Fisik Kosong *</label>
            <input type="number" name="stok_fisik_kosong" required min="0" class="form-select"
                   placeholder="{{ $saldoKosong }}" id="inp-fisik-kosong" oninput="hitungSelisih()">
            <p id="info-kosong" style="font-size:10px;color:var(--muted);margin-top:3px"></p>
          </div>
          <div>
            <label class="form-label">Stok Fisik Isi *</label>
            <input type="number" name="stok_fisik_isi" required min="0" class="form-select"
                   placeholder="{{ $saldoIsi }}" id="inp-fisik-isi" oninput="hitungSelisih()">
            <p id="info-isi" style="font-size:10px;color:var(--muted);margin-top:3px"></p>
          </div>
          <div style="grid-column:span 2">
            <label class="form-label">Keterangan *</label>
            <input type="text" name="keterangan" required class="form-select" placeholder="Opname bulanan rutin">
          </div>
        </div>
        <button type="submit" class="btn btn-block" style="background:#F97316;color:#fff;padding:11px;font-size:14px"
                onclick="return confirm('Simpan hasil opname? Penyesuaian tidak bisa dibatalkan.')">
          Simpan Opname
        </button>
      </form>
    </div>
  </div>
</div>

{{-- Modal: Catat Pinjaman --}}
<div id="modal-pinjam" style="{{ $modalStyle }}" onclick="if(event.target===this)closeModal('modal-pinjam')">
  <div style="{{ $boxStyle }}" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3 class="modal-title">Catat Pinjaman Tabung</h3>
      <button class="modal-close" onclick="closeModal('modal-pinjam')">×</button>
    </div>
    <div class="modal-body">
      <form action="{{ route('dashboard.agen.distribusi.gudang.pinjam') }}" method="POST">
        @csrf
        <div class="form-group">
          <label class="form-label">Jenis Pihak *</label>
          <select name="jenis_pihak" id="sel-pihak" class="form-select" onchange="updatePihak()">
            <option value="pangkalan">Pangkalan</option>
            <option value="cabang">Cabang / Pihak Lain</option>
          </select>
        </div>
        <div id="field-pangkalan" class="form-group">
          <label class="form-label">Pangkalan *</label>
          <select name="pangkalan_id" class="form-select">
            <option value="">-- Pilih Pangkalan --</option>
            @foreach(\App\Models\Pangkalan::orderBy('nama_pangkalan')->get() as $p)
              <option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>
            @endforeach
          </select>
        </div>
        <div id="field-cabang" class="form-group" style="display:none">
          <label class="form-label">Nama Cabang / Pihak *</label>
          <input type="text" name="nama_cabang" class="form-select" placeholder="Nama pihak peminjam">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group">
            <label class="form-label">Qty Tabung *</label>
            <input type="number" name="qty" required min="1" max="{{ $saldoKosong }}" class="form-select" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal *</label>
            <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="form-select">
          </div>
          <div class="form-group">
            <label class="form-label">Berlaku Sampai</label>
            <input type="date" name="tgl_berlaku_sampai" class="form-select">
          </div>
          <div class="form-group">
            <label class="form-label">No. Perjanjian</label>
            <input type="text" name="no_perjanjian" class="form-select" placeholder="PKS-...">
          </div>
        </div>
        <button type="submit" class="btn btn-block" style="background:#F59E0B;color:#fff;padding:11px;font-size:14px">
          Simpan Perjanjian Pinjaman
        </button>
      </form>
    </div>
  </div>
</div>

{{-- Modal: Pengembalian Pinjaman --}}
<div id="modal-kembali" style="{{ $modalStyle }}" onclick="if(event.target===this)closeModal('modal-kembali')">
  <div style="{{ $boxStyle }}" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Catat Pengembalian</h3>
        <p id="kembali-nama" style="font-size:12px;color:var(--muted);margin-top:2px">—</p>
      </div>
      <button class="modal-close" onclick="closeModal('modal-kembali')">×</button>
    </div>
    <div class="modal-body">
      <div class="qty-display" style="margin-bottom:14px">
        <div class="qty-display-label">Maksimal kembali</div>
        <div class="qty-display-value" id="kembali-max" style="color:var(--text)">0</div>
        <div class="qty-display-label">tabung</div>
      </div>
      <form id="form-kembali" method="POST">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
          <div><label class="form-label">Tanggal *</label><input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="form-select"></div>
          <div><label class="form-label">Jumlah *</label><input type="number" name="qty" required min="1" id="inp-kembali" class="form-select" placeholder="0"></div>
          <div style="grid-column:span 2"><label class="form-label">Keterangan</label><input type="text" name="keterangan" class="form-select"></div>
        </div>
        <button type="submit" class="btn btn-block" style="background:#059669;color:#fff;padding:11px;font-size:14px">Simpan Pengembalian</button>
      </form>
    </div>
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
document.addEventListener('keydown', e => {
  if(e.key==='Escape')
    ['modal-beli','modal-keluar-kosong','modal-masuk-isi','modal-pinjam','modal-opname','modal-kembali','modal-alokasi-armada','modal-kembalikan-armada']
      .forEach(closeModal);
});

function switchTab(t) {
  ['mutasi','pinjaman','armada'].forEach(id => {
    document.getElementById('view-'+id).style.display = id===t ? 'block' : 'none';
    const tab = document.getElementById('tab-'+id);
    tab.classList.toggle('active', id===t);
  });
}

function bukaKembali(id, nama, maxQty) {
  document.getElementById('form-kembali').action = `/dashboard/agen/distribusi/gudang/pinjam/${id}/kembali`;
  document.getElementById('kembali-nama').textContent = nama;
  document.getElementById('kembali-max').textContent  = maxQty.toLocaleString('id');
  document.getElementById('inp-kembali').max = maxQty;
  openModal('modal-kembali');
}

function bukaKembalikanArmada(armadaId, noPolisi, maxQty) {
  document.getElementById('form-kembalikan-armada').action =
    `/dashboard/agen/distribusi/gudang/armada/${armadaId}/kembalikan`;
  document.getElementById('txt-armada-kembali').textContent =
    `${noPolisi} · Saldo tabung: ${maxQty.toLocaleString('id')} tabung`;
  document.getElementById('inp-qty-kembali-armada').max   = maxQty;
  document.getElementById('inp-qty-kembali-armada').value = maxQty;
  openModal('modal-kembalikan-armada');
}

function updatePihak() {
  const cabang = document.getElementById('sel-pihak').value === 'cabang';
  document.getElementById('field-pangkalan').style.display = cabang ? 'none' : 'block';
  document.getElementById('field-cabang').style.display    = cabang ? 'block' : 'none';
}

const saldoKosong = {{ $saldoKosong }};
const saldoIsi    = {{ $saldoIsi }};
function hitungSelisih() {
  const fK = parseInt(document.getElementById('inp-fisik-kosong').value) || saldoKosong;
  const fI = parseInt(document.getElementById('inp-fisik-isi').value)    || saldoIsi;
  const sK = fK - saldoKosong, sI = fI - saldoIsi;
  const iK = document.getElementById('info-kosong'), iI = document.getElementById('info-isi');
  iK.textContent = sK===0?'✓ Sama':(sK>0?`+${sK} (lebih)`:`${sK} (kurang)`);
  iK.style.color = sK===0?'#059669':sK>0?'#3B82F6':'#DC2626';
  iI.textContent = sI===0?'✓ Sama':(sI>0?`+${sI} (lebih)`:`${sI} (kurang)`);
  iI.style.color = sI===0?'#059669':sI>0?'#3B82F6':'#DC2626';
}
</script>
@endpush