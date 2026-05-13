@extends('layouts.driver')
@section('topbar-title', 'Histori Distribusi')
@section('topbar-sub', 'Riwayat laporan Anda')

@section('content')
<form method="GET" style="padding:12px;display:flex;gap:8px">
  <select name="bulan" style="flex:1;border:1.5px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;color:var(--text);background:var(--surface);outline:none">
    @foreach($bulanList as $n => $nama)
      <option value="{{ $n }}" {{ $bulan == $n ? 'selected' : '' }}>{{ $nama }}</option>
    @endforeach
  </select>
  <button type="submit" class="btn btn-primary" style="padding:10px 16px;border-radius:10px">Cari</button>
</form>

{{-- Summary cards --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:0 12px 12px">
  @foreach([
    ['Total Jadwal',  number_format($totalJadwal).' tb',  '#3B82F6'],
    ['Total Terkirim',number_format($totalTerima).' tb',  '#059669'],
    ['Sisa',          number_format($totalJadwal-$totalTerima).' tb', '#F59E0B'],
    ['Trip',          $totalTrip.' hari', '#7C3AED'],
  ] as [$l,$v,$c])
  <div style="background:var(--surface);border-radius:12px;padding:12px;border-left:3px solid {{ $c }}">
    <div style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em">{{ $l }}</div>
    <div style="font-size:20px;font-weight:700;color:var(--text);margin-top:4px">{{ $v }}</div>
  </div>
  @endforeach
</div>

{{-- List per hari --}}
@forelse($histori as $tanggal => $items)
<div class="card" style="margin:0 12px 12px">
  <div class="card-header" style="font-size:13px;color:var(--muted)">
    📅 {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
  </div>
  @foreach($items as $d)
  <div class="pkln-row">
    <div class="pkln-urut" style="background:{{ $d->status==='terkirim' ? 'var(--success)' : 'var(--warning)' }}">
      {{ $d->status==='terkirim' ? '✓' : '~' }}
    </div>
    <div class="pkln-info">
      <div class="pkln-nama">{{ $d->pangkalan?->nama_pangkalan }}</div>
      <div class="pkln-sub">
        {{ $d->header?->no_sj }} ·
        <span style="color:{{ $d->qty_terima>=$d->qty_jadwal?'var(--success)':'var(--warning)' }}">
          {{ $d->qty_terima }}/{{ $d->qty_jadwal }} tabung
        </span>
        @if($d->keterangan) · {{ Str::limit($d->keterangan,25) }} @endif
      </div>
    </div>
    <div class="pkln-qty">
      <div class="pkln-qty-num" style="font-size:14px;color:{{ $d->qty_terima>=$d->qty_jadwal?'var(--success)':'var(--warning)' }}">
        {{ number_format($d->qty_terima) }}
      </div>
      <div class="pkln-qty-label">terkirim</div>
    </div>
  </div>
  @endforeach
</div>
@empty
<div style="text-align:center;padding:60px 20px;color:var(--muted)">
  <div style="font-size:40px;margin-bottom:12px">📋</div>
  <div style="font-size:15px;font-weight:600;color:var(--text)">Belum ada histori bulan ini</div>
</div>
@endforelse
@endsection
