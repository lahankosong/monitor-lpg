@extends('layouts.app')
@section('title', 'Laporan Distribusi')

@section('content')
@include('layouts.partials.distribusi-styles')

<div class="page-header">
  <div>
    <h1 class="page-title">Laporan Distribusi Bulanan</h1>
    <p class="page-sub">Rekap harian gendongan, gudang, dan pengalihan</p>
  </div>
  <a href="{{ route('dashboard.agen.distribusi.index') }}" class="btn btn-outline">← Input Realisasi</a>
</div>

{{-- Filter --}}
<form method="GET" class="filter-bar">
  <select name="bulan">
    @foreach($bulanList as $n => $nama)
      <option value="{{ $n }}" {{ $bulan == $n ? 'selected' : '' }}>{{ $nama }}</option>
    @endforeach
  </select>
  <select name="tahun">
    @for($y = now()->year; $y >= now()->year - 2; $y--)
      <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
    @endfor
  </select>
  <button type="submit" class="btn btn-primary">Filter</button>
</form>

{{-- Rekap Harian --}}
@if($rekapHarian->isNotEmpty())
<div class="card" style="margin-bottom:20px">
  <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
    <div style="font-size:14px;font-weight:600;color:var(--text)">Rekap Harian</div>
    <div style="font-size:11px;color:var(--muted);margin-top:2px">
      Gendongan = sisa di armada wajib habis sebelum ambil DO · Gudang = sisa yang disimpan
    </div>
  </div>
  <div class="table-wrap">
    <table style="min-width:640px">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th style="text-align:right">Ambil DO</th>
          <th style="text-align:right">Gend. Masuk</th>
          <th style="text-align:right">Ambil Gudang</th>
          <th style="text-align:right">Total</th>
          <th style="text-align:right">Terkirim</th>
          <th style="text-align:right">Gend. Keluar</th>
          <th style="text-align:right">Gudang</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rekapHarian as $tgl => $r)
        <tr>
          <td style="font-weight:600">{{ $tgl }}</td>
          <td style="text-align:right">{{ number_format($r['ambil_do']) }}</td>
          <td style="text-align:right;color:{{ $r['gendongan_masuk'] > 0 ? '#F59E0B' : 'var(--muted)' }};font-weight:{{ $r['gendongan_masuk'] > 0 ? '600' : '400' }}">
            {{ $r['gendongan_masuk'] > 0 ? number_format($r['gendongan_masuk']) : '—' }}
          </td>
          <td style="text-align:right;color:{{ $r['ambil_gudang'] > 0 ? '#7C3AED' : 'var(--muted)' }}">
            {{ $r['ambil_gudang'] > 0 ? number_format($r['ambil_gudang']) : '—' }}
          </td>
          <td style="text-align:right;font-weight:700">{{ number_format($r['total_tersedia']) }}</td>
          <td style="text-align:right;color:#059669;font-weight:700">{{ number_format($r['terkirim']) }}</td>
          <td style="text-align:right;color:{{ $r['gendongan_keluar'] > 0 ? '#F59E0B' : 'var(--muted)' }};font-weight:{{ $r['gendongan_keluar'] > 0 ? '700' : '400' }}">
            {{ $r['gendongan_keluar'] > 0 ? '⚡ '.number_format($r['gendongan_keluar']) : '—' }}
          </td>
          <td style="text-align:right;color:{{ $r['gudang'] > 0 ? '#7C3AED' : 'var(--muted)' }};font-weight:{{ $r['gudang'] > 0 ? '700' : '400' }}">
            {{ $r['gudang'] > 0 ? '🏪 '.number_format($r['gudang']) : '—' }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

{{-- Rekap Sisa per Pangkalan --}}
@if($rekapPangkalan->isNotEmpty())
<div class="card">
  <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
    <div style="font-size:14px;font-weight:600;color:var(--text)">Detail Sisa per Pangkalan</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Pangkalan</th>
          <th style="text-align:right">Jadwal</th>
          <th style="text-align:right">Terima</th>
          <th style="text-align:right">Sisa</th>
          <th>Nasib Sisa</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rekapPangkalan as $detailId => $sisaRows)
        @php $detail = $sisaRows->first()?->detail; @endphp
        <tr>
          <td>
            <div style="font-weight:600;color:var(--text)">{{ $detail?->pangkalan?->nama_pangkalan }}</div>
            <div style="font-size:11px;font-family:monospace;color:var(--muted)">{{ $detail?->pangkalan?->no_reg }}</div>
          </td>
          <td style="text-align:right">{{ number_format($detail?->qty_jadwal ?? 0) }}</td>
          <td style="text-align:right;color:#059669;font-weight:700">{{ number_format($detail?->qty_terima ?? 0) }}</td>
          <td style="text-align:right;color:#F59E0B;font-weight:700">
            {{ number_format(($detail?->qty_jadwal ?? 0) - ($detail?->qty_terima ?? 0)) }}
          </td>
          <td>
            @foreach($sisaRows as $s)
            @php $color = match($s->tipe) {
              'alih_pangkalan'  => '#1E40AF',
              'stok_armada'     => '#92400E',
              'gudang_sendiri'  => '#5B21B6',
              'titip_agen_lain' => '#065F46',
              default           => 'var(--muted)',
            }; @endphp
            <span style="display:block;font-size:11px;color:{{ $color }};font-weight:600">
              {{ \App\Models\SjSisaDistribusi::TIPE_LABEL[$s->tipe] ?? $s->tipe }}: {{ $s->qty }} tb
            </span>
            @endforeach
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@else
<div class="card" style="padding:48px;text-align:center;color:var(--muted)">
  Belum ada data distribusi bulan ini
</div>
@endif

@endsection
