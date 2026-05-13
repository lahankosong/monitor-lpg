@extends('layouts.app')
@section('title', 'Laporan Distribusi')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Laporan Distribusi Bulanan</h1>
    <p style="font-size:12px;color:var(--muted)">Rekap harian gendongan, gudang, dan pengalihan</p>
  </div>
  <a href="{{ route('dashboard.agen.distribusi.index') }}"
     style="border:1px solid var(--border);color:var(--text);background:var(--surface);border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
    ← Input Realisasi
  </a>
</div>

{{-- Filter --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <select name="bulan" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    @foreach($bulanList as $n => $nama)
      <option value="{{ $n }}" {{ $bulan == $n ? 'selected':'' }}>{{ $nama }}</option>
    @endforeach
  </select>
  <select name="tahun" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    @for($y=now()->year;$y>=now()->year-2;$y--)
      <option value="{{ $y }}" {{ $tahun==$y?'selected':'' }}>{{ $y }}</option>
    @endfor
  </select>
  <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- Rekap Harian --}}
@if($rekapHarian->isNotEmpty())
<div class="card" style="overflow:hidden;margin-bottom:20px">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Rekap Harian</h2>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      Gendongan = sisa di armada wajib habis sebelum ambil DO · Gudang = sisa yang disimpan
    </p>
  </div>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:600px">
      <thead>
        <tr style="background:var(--bg)">
          @foreach(['Tanggal','Ambil DO','Gend. Masuk','Ambil Gudang','Total','Terkirim','Gend. Keluar','Gudang'] as $h)
            <th style="text-align:right;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap;first-child:text-align:left">{{ $h }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($rekapHarian as $tgl => $r)
        @php $sisa = $r['total_tersedia'] - $r['terkirim'] - $r['gudang']; @endphp
        <tr style="border-top:1px solid var(--border)">
          <td style="padding:9px 14px;font-weight:600;color:var(--text);text-align:left">{{ $tgl }}</td>
          <td style="padding:9px 14px;text-align:right">{{ number_format($r['ambil_do']) }}</td>
          <td style="padding:9px 14px;text-align:right;color:{{ $r['gendongan_masuk']>0?'#F59E0B':'var(--muted)' }};font-weight:{{ $r['gendongan_masuk']>0?'600':'400' }}">
            {{ $r['gendongan_masuk']>0 ? number_format($r['gendongan_masuk']) : '—' }}
          </td>
          <td style="padding:9px 14px;text-align:right;color:{{ $r['ambil_gudang']>0?'#7C3AED':'var(--muted)' }}">
            {{ $r['ambil_gudang']>0 ? number_format($r['ambil_gudang']) : '—' }}
          </td>
          <td style="padding:9px 14px;text-align:right;font-weight:700">{{ number_format($r['total_tersedia']) }}</td>
          <td style="padding:9px 14px;text-align:right;color:#059669;font-weight:700">{{ number_format($r['terkirim']) }}</td>
          <td style="padding:9px 14px;text-align:right;color:{{ $r['gendongan_keluar']>0?'#F59E0B':'var(--muted)' }};font-weight:{{ $r['gendongan_keluar']>0?'700':'400' }}">
            {{ $r['gendongan_keluar']>0 ? '⚡ '.number_format($r['gendongan_keluar']) : '—' }}
          </td>
          <td style="padding:9px 14px;text-align:right;color:{{ $r['gudang']>0?'#7C3AED':'var(--muted)' }};font-weight:{{ $r['gudang']>0?'700':'400' }}">
            {{ $r['gudang']>0 ? '🏪 '.number_format($r['gudang']) : '—' }}
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
<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Detail Sisa per Pangkalan</h2>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','Jadwal','Terima','Sisa','Nasib Sisa'] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($rekapPangkalan as $detailId => $sisaRows)
      @php $detail = $sisaRows->first()?->detail; @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">
          {{ $detail?->pangkalan?->nama_pangkalan }}
          <span style="display:block;font-size:11px;color:var(--muted);font-family:monospace">{{ $detail?->pangkalan?->no_reg }}</span>
        </td>
        <td style="padding:9px 14px;text-align:right">{{ number_format($detail?->qty_jadwal ?? 0) }}</td>
        <td style="padding:9px 14px;text-align:right;color:#059669;font-weight:700">{{ number_format($detail?->qty_terima ?? 0) }}</td>
        <td style="padding:9px 14px;text-align:right;color:#F59E0B;font-weight:700">
          {{ number_format(($detail?->qty_jadwal ?? 0) - ($detail?->qty_terima ?? 0)) }}
        </td>
        <td style="padding:9px 14px">
          @foreach($sisaRows as $s)
            @php $color = match($s->tipe) {
              'alih_pangkalan'  => '#1E40AF',
              'stok_armada'     => '#92400E',
              'gudang_sendiri'  => '#5B21B6',
              'titip_agen_lain' => '#065F46',
              default           => 'var(--muted)',
            }; @endphp
            <span style="font-size:11px;color:{{ $color }};font-weight:600">
              {{ \App\Models\SjSisaDistribusi::TIPE_LABEL[$s->tipe] ?? $s->tipe }}: {{ $s->qty }} tb
            </span><br>
          @endforeach
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@else
<div class="card" style="padding:48px;text-align:center;color:var(--muted)">
  Belum ada data distribusi bulan ini
</div>
@endif
@endsection
