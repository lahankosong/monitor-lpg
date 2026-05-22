@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Notifikasi</h1>
    @if($totalBelumBaca > 0)
    <p style="font-size:12px;color:var(--muted)">
      {{ $totalBelumBaca }} belum dibaca
    </p>
    @endif
  </div>
  @if($totalBelumBaca > 0)
  <form action="{{ route('dashboard.notifikasi.baca-semua') }}" method="POST">
    @csrf
    <button type="submit" style="border:1px solid var(--border);background:var(--surface);
            color:var(--text);border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer">
      Tandai semua dibaca
    </button>
  </form>
  @endif
</div>

{{-- Filter --}}
<div style="display:flex;gap:8px;margin-bottom:16px">
  @foreach(['semua'=>'Semua', 'belum'=>'Belum Dibaca'] as $k => $v)
  <a href="{{ route('dashboard.notifikasi.index', ['filter'=>$k]) }}"
     style="padding:6px 14px;border-radius:8px;font-size:13px;text-decoration:none;
            {{ $filter===$k ? 'background:var(--accent);color:#fff;font-weight:600' : 'border:1px solid var(--border);color:var(--text)' }}">
    {{ $v }}
  </a>
  @endforeach
</div>

<div style="display:flex;flex-direction:column;gap:8px">
  @forelse($notifs as $n)
  @php
    $warna = match(true) {
      in_array($n->tipe, ['piutang_jatuh_tempo','brimola_unmatched','stok_gudang_rendah','scraping_gagal']) => '#DC2626',
      in_array($n->tipe, ['tebusan_baru','sj_selesai']) => '#0EA5E9',
      $n->tipe === 'scraping_selesai' => '#059669',
      default => '#6B7280',
    };
    $icon = match($n->tipe) {
      'tebusan_baru' => '📋', 'sj_selesai' => '🚛',
      'piutang_jatuh_tempo' => '⚠️', 'brimola_unmatched' => '💳',
      'stok_gudang_rendah' => '📦', 'scraping_selesai' => '✓',
      'scraping_gagal' => '✗', default => 'ℹ️',
    };
  @endphp
  <a href="{{ route('dashboard.notifikasi.baca', $n->id) }}"
     style="display:flex;gap:12px;padding:14px 18px;border-radius:10px;text-decoration:none;
            background:{{ $n->is_read ? 'var(--surface)' : 'rgba(14,165,233,.05)' }};
            border:1px solid {{ $n->is_read ? 'var(--border)' : 'rgba(14,165,233,.2)' }};
            transition:background .15s"
     onmouseover="this.style.background='var(--bg)'"
     onmouseout="this.style.background='{{ $n->is_read ? 'var(--surface)' : 'rgba(14,165,233,.05)' }}'">
    <div style="flex-shrink:0;width:40px;height:40px;border-radius:10px;
                background:{{ $warna }}18;display:flex;align-items:center;
                justify-content:center;font-size:20px">
      {{ $icon }}
    </div>
    <div style="flex:1">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
        <p style="font-size:14px;font-weight:{{ $n->is_read?'400':'600' }};color:var(--text)">
          {{ $n->judul }}
        </p>
        <span style="font-size:11px;color:var(--muted);white-space:nowrap;flex-shrink:0">
          {{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}
        </span>
      </div>
      <p style="font-size:13px;color:var(--muted);margin-top:3px">{{ $n->pesan }}</p>
    </div>
    @if(!$n->is_read)
      <div style="width:8px;height:8px;border-radius:50%;background:#0EA5E9;flex-shrink:0;margin-top:6px"></div>
    @endif
  </a>
  @empty
  <div class="card" style="padding:48px;text-align:center;color:var(--muted)">
    {{ $filter === 'belum' ? 'Semua notifikasi sudah dibaca ✓' : 'Belum ada notifikasi' }}
  </div>
  @endforelse
</div>
<div style="margin-top:16px">{{ $notifs->links() }}</div>
@endsection
