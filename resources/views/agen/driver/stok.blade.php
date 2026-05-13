@extends('layouts.driver')
@section('topbar-title', 'Stok & Gendongan')
@section('topbar-sub', 'Status armada & gudang')

@section('content')

{{-- Gendongan --}}
<div style="margin-bottom:8px">
  <span style="font-size:12px;font-weight:600;color:var(--warning);text-transform:uppercase;letter-spacing:.06em">
    ⚡ Gendongan Aktif
  </span>
  <span style="font-size:11px;color:var(--muted);margin-left:6px">Wajib habis sebelum ambil DO</span>
</div>

@if($gendongan->isEmpty())
<div class="card" style="padding:24px;text-align:center;color:var(--muted);margin-bottom:16px">
  Semua armada bersih — tidak ada gendongan ✓
</div>
@else
@foreach($gendongan as $armadaId => $stoks)
@php $total = $stoks->sum('sisa_akhir'); @endphp
<div class="card" style="margin-bottom:12px">
  <div style="padding:12px 14px;background:rgba(250,173,20,.08);border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center">
    <div>
      <span style="font-family:monospace;font-size:15px;font-weight:700;color:var(--warning)">
        {{ $stoks->first()->armada?->no_polisi }}
      </span>
      <span style="font-size:11px;color:var(--muted);margin-left:8px">{{ $stoks->count() }} trip</span>
    </div>
    <div style="text-align:right">
      <div style="font-size:24px;font-weight:700;color:var(--warning)">{{ number_format($total) }}</div>
      <div style="font-size:10px;color:var(--muted)">tabung</div>
    </div>
  </div>
  @foreach($stoks as $s)
  <div style="padding:8px 14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;
              align-items:center;font-size:12px">
    <div>
      <span style="color:var(--muted)">SJ: </span>
      <span style="font-family:monospace;color:var(--accent)">{{ $s->sjHeader?->no_sj }}</span>
      <span style="display:block;color:var(--muted);font-size:11px">{{ $s->tanggal->format('d/m/Y') }}</span>
    </div>
    <span style="font-size:16px;font-weight:700;color:var(--warning)">{{ $s->sisa_akhir }} tb</span>
  </div>
  @endforeach
  <div style="padding:8px 14px;font-size:11px;color:var(--muted)">
    Hubungi admin untuk masukkan gendongan ke SJ berikutnya
  </div>
</div>
@endforeach
@endif

{{-- Gudang --}}
<div style="margin:16px 0 8px">
  <span style="font-size:12px;font-weight:600;color:#a78bfa;text-transform:uppercase;letter-spacing:.06em">
    🏪 Stok Gudang
  </span>
  <span style="font-size:11px;color:var(--muted);margin-left:6px">Bisa diambil kapan saja</span>
</div>

@if($gudang->isEmpty())
<div class="card" style="padding:24px;text-align:center;color:var(--muted)">
  Tidak ada stok di gudang
</div>
@else
<div class="card">
  @foreach($gudang as $g)
  <div style="padding:12px 14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <div>
      <div style="font-size:13px;font-weight:500;color:var(--text)">
        @if($g->sumber==='titipan_agen')
          <span style="color:var(--accent)">🤝</span> Titipan {{ $g->agenAsal?->nama_agen }}
        @else
          <span style="color:#a78bfa">📦</span> Sisa SJ
        @endif
      </div>
      <div style="font-size:11px;color:var(--muted);margin-top:2px">
        Masuk: {{ $g->tgl_masuk->format('d/m/Y') }}
        @if($g->keterangan) · {{ Str::limit($g->keterangan, 25) }} @endif
      </div>
    </div>
    <div style="font-size:20px;font-weight:700;color:#a78bfa">
      {{ number_format($g->sisa_stok) }}
      <span style="font-size:11px;font-weight:400;color:var(--muted)">tb</span>
    </div>
  </div>
  @endforeach
  <div style="padding:10px 14px;display:flex;justify-content:space-between;font-size:13px">
    <span style="color:var(--muted)">Total tersedia</span>
    <span style="font-weight:700;color:#a78bfa">{{ number_format($gudang->sum('sisa_stok')) }} tabung</span>
  </div>
</div>
<div style="padding:10px 14px 0;font-size:11px;color:var(--muted)">
  Untuk ambil stok gudang ke SJ, hubungi admin di halaman Distribusi → Realisasi.
</div>
@endif
@endsection
