@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php
  $pctRealisasi = $totalDO > 0 ? round($totalTerkirim / $totalDO * 100) : 0;
@endphp

{{-- Header + filter bulan --}}
<div style="display:flex;justify-content:space-between;align-items:flex-start;
            flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">
      Ikhtisar — {{ $bulanList[$bulan] }} {{ $tahun }}
    </h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      {{ now()->translatedFormat('l, d F Y') }}
    </p>
  </div>
  <form method="GET" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
    <select name="bulan" style="border:1px solid var(--border);background:var(--surface);
            color:var(--text);border-radius:8px;padding:6px 10px;font-size:12px;outline:none">
      @foreach($bulanList as $n => $nama)
        <option value="{{ $n }}" {{ $bulan==$n?'selected':'' }}>{{ $nama }}</option>
      @endforeach
    </select>
    <select name="tahun" style="border:1px solid var(--border);background:var(--surface);
            color:var(--text);border-radius:8px;padding:6px 10px;font-size:12px;outline:none">
      @for($y=now()->year;$y>=now()->year-2;$y--)
        <option value="{{ $y }}" {{ $tahun==$y?'selected':'' }}>{{ $y }}</option>
      @endfor
    </select>
    <button type="submit" style="background:var(--accent);color:#fff;border:none;
            border-radius:8px;padding:6px 12px;font-size:12px;cursor:pointer">Filter</button>
  </form>
</div>

{{-- ── ALERT NOTIFIKASI ───────────────────────────────────────── --}}
@if($alerts->isNotEmpty())
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px">
  @foreach($alerts as $a)
  @php
    $bg = match($a['tipe']) {
      'danger'  => 'background:#FEE2E2;border-color:#FECACA;color:#991B1B',
      'warning' => 'background:#FEF3C7;border-color:#FDE68A;color:#92400E',
      default   => 'background:#DBEAFE;border-color:#BFDBFE;color:#1E40AF',
    };
  @endphp
  <a href="{{ $a['url'] }}"
     style="display:flex;align-items:center;gap:10px;padding:10px 14px;
            border-radius:10px;border:1px solid;text-decoration:none;{{ $bg }}">
    <span style="font-size:18px;flex-shrink:0">{{ $a['icon'] }}</span>
    <div style="min-width:0">
      <p style="font-size:12px;font-weight:700;margin:0">{{ $a['judul'] }}</p>
      <p style="font-size:11px;margin:2px 0 0;opacity:.85;
                overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $a['pesan'] }}</p>
    </div>
    <svg style="margin-left:auto;flex-shrink:0;opacity:.5" width="16" height="16"
         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </a>
  @endforeach
</div>
@endif

{{-- ── STAT CARDS DISTRIBUSI ─────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
            gap:12px;margin-bottom:20px">

  <div class="stat-card" style="border-left:3px solid var(--accent)">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">DO Bulan Ini</p>
    <p style="font-size:28px;font-weight:700;color:var(--accent);margin-top:4px;line-height:1">
      {{ number_format($totalDO) }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:2px">tabung</p>
  </div>

  <div class="stat-card" style="border-left:3px solid #059669">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">Terkirim</p>
    <p style="font-size:28px;font-weight:700;color:#059669;margin-top:4px;line-height:1">
      {{ number_format($totalTerkirim) }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:2px">{{ $pctRealisasi }}% dari DO</p>
  </div>

  <div class="stat-card" style="border-left:3px solid #F59E0B">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">SJ Bulan Ini</p>
    <p style="font-size:28px;font-weight:700;color:var(--text);margin-top:4px;line-height:1">
      {{ $sjBulanIni }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:2px">{{ $sjSelesai }} selesai</p>
  </div>

  @if($sjAktif > 0)
  <div class="stat-card" style="border-left:3px solid #8B5CF6;background:rgba(139,92,246,.05)">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">Sedang Jalan</p>
    <p style="font-size:28px;font-weight:700;color:#8B5CF6;margin-top:4px;line-height:1">
      {{ $sjAktif }}
    </p>
    <p style="font-size:11px;color:#8B5CF6;margin-top:2px">armada aktif sekarang</p>
  </div>
  @endif

  @if($gendongan->isNotEmpty())
  <div class="stat-card" style="border-left:3px solid #F59E0B;background:rgba(245,158,11,.05)">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">⚡ Gendongan</p>
    <p style="font-size:28px;font-weight:700;color:#F59E0B;margin-top:4px;line-height:1">
      {{ number_format($gendongan->sum('sisa_akhir')) }}
    </p>
    <p style="font-size:11px;color:#92400E;margin-top:2px">tabung isi sisa kemarin</p>
  </div>
  @endif

</div>

{{-- ── GRAFIK DISTRIBUSI 30 HARI ──────────────────────────────── --}}
<div class="card" style="padding:16px 18px;margin-bottom:20px">
  <p style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:12px">
    Tren Distribusi 30 Hari Terakhir
  </p>
  <div style="overflow-x:auto">
    <div id="chartWrap" style="min-width:400px;height:120px;display:flex;
                align-items:flex-end;gap:3px;padding-bottom:20px;position:relative">
      @php $maxVal = max($grafikData->max('terkirim'), 1); @endphp
      @foreach($grafikData as $d)
      @php $h = $d['terkirim'] > 0 ? max(4, round($d['terkirim'] / $maxVal * 100)) : 2; @endphp
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;
                  position:relative" title="{{ $d['tgl'] }}: {{ $d['terkirim'] }} tabung">
        <div style="width:100%;background:{{ $d['terkirim'] > 0 ? 'var(--accent)' : 'var(--border)' }};
                    border-radius:3px 3px 0 0;height:{{ $h }}%;
                    opacity:{{ $d['terkirim'] > 0 ? '1' : '.4' }};
                    transition:opacity .15s;cursor:default"
             onmouseover="showTip(this,'{{ $d['tgl'] }}','{{ $d['terkirim'] }}')"
             onmouseout="hideTip()"></div>
        @if($loop->iteration % 5 === 1 || $loop->last)
        <span style="font-size:9px;color:var(--muted);position:absolute;bottom:-18px;
                     white-space:nowrap">{{ $d['tgl'] }}</span>
        @endif
      </div>
      @endforeach
      {{-- Tooltip --}}
      <div id="chartTip" style="display:none;position:absolute;background:var(--text);
           color:var(--surface);font-size:11px;padding:4px 8px;border-radius:6px;
           pointer-events:none;z-index:10;white-space:nowrap;transform:translateX(-50%)"></div>
    </div>
  </div>
</div>

{{-- ── ROW: KEUANGAN + GUDANG ─────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

  {{-- Keuangan --}}
  <div class="card" style="padding:16px 18px">
    <p style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:12px">
      💰 Keuangan
    </p>
    <div style="display:flex;flex-direction:column;gap:10px">

      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:8px 12px;background:var(--bg);border-radius:8px">
        <span style="font-size:12px;color:var(--muted)">Tebusan {{ $bulanList[$bulan] }}</span>
        <span style="font-size:14px;font-weight:700;color:var(--text)">
          Rp {{ number_format($tebusanBulan) }}
        </span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:8px 12px;background:var(--bg);border-radius:8px">
        <span style="font-size:12px;color:var(--muted)">Saldo BRImola</span>
        <span style="font-size:14px;font-weight:700;
                     color:{{ $brimolaKum >= 0 ? '#059669' : '#DC2626' }}">
          Rp {{ number_format(abs($brimolaKum)) }}
        </span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:8px 12px;background:var(--bg);border-radius:8px;
                  {{ $piutangOut > 0 ? 'border:1px solid #FECACA' : '' }}">
        <span style="font-size:12px;color:var(--muted)">Piutang Kerjasama</span>
        <span style="font-size:14px;font-weight:700;
                     color:{{ $piutangOut > 0 ? '#DC2626' : '#059669' }}">
          Rp {{ number_format($piutangOut) }}
        </span>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:8px 12px;background:var(--bg);border-radius:8px">
        <span style="font-size:12px;color:var(--muted)">Kas Kecil</span>
        <span style="font-size:14px;font-weight:700;color:var(--text)">
          Rp {{ number_format($kasKecil) }}
        </span>
      </div>

      @if($tebusanBesok > 0)
      <div style="padding:8px 12px;background:#FEF3C7;border-radius:8px;
                  border:1px solid #FDE68A">
        <p style="font-size:11px;color:#92400E;font-weight:600">📦 DO Besok</p>
        <p style="font-size:12px;color:#92400E;margin-top:2px">
          Estimasi {{ number_format($tebusanBesok) }} tabung perlu ditebus
        </p>
      </div>
      @endif

    </div>
  </div>

  {{-- Gudang & Tabung --}}
  <div class="card" style="padding:16px 18px">
    <p style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:12px">
      🏪 Gudang & Tabung
    </p>

    {{-- Progress bar kepemilikan --}}
    @php
      $tot = max($totalKepemilikan, 1);
      $pKosong  = round($bufferKosong    / $tot * 100);
      $pIsi     = round($tabungIsi       / $tot * 100);
      $pArmada  = round($tabungDiArmada  / $tot * 100);
      $pPinjam  = round($totalPinjaman   / $tot * 100);
    @endphp
    <div style="height:10px;border-radius:99px;overflow:hidden;display:flex;
                margin-bottom:8px;background:var(--border)">
      @if($bufferKosong  > 0)<div style="width:{{ $pKosong }}%;background:var(--accent)"></div>@endif
      @if($tabungIsi     > 0)<div style="width:{{ $pIsi }}%;background:#059669"></div>@endif
      @if($tabungDiArmada> 0)<div style="width:{{ $pArmada }}%;background:#F97316"></div>@endif
      @if($totalPinjaman > 0)<div style="width:{{ $pPinjam }}%;background:#F59E0B"></div>@endif
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:10px;color:var(--muted);
                margin-bottom:14px">
      <span>🔵 Buffer: {{ number_format($bufferKosong) }}</span>
      <span>🟢 Isi: {{ number_format($tabungIsi) }}</span>
      <span>🟠 Armada: {{ number_format($tabungDiArmada) }}</span>
      <span>🟡 Pinjam: {{ number_format($totalPinjaman) }}</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:8px 12px;background:var(--bg);border-radius:8px;
                  {{ $bufferKosong < 100 ? 'border:1px solid #FECACA' : '' }}">
        <span style="font-size:12px;color:var(--muted)">Buffer Kosong</span>
        <span style="font-size:18px;font-weight:700;
                     color:{{ $bufferKosong < 100 ? '#DC2626' : 'var(--accent)' }}">
          {{ number_format($bufferKosong) }}
          @if($bufferKosong < 100) <span style="font-size:11px">⚠</span>@endif
        </span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:8px 12px;background:var(--bg);border-radius:8px">
        <span style="font-size:12px;color:var(--muted)">Tabung Isi di Gudang</span>
        <span style="font-size:18px;font-weight:700;color:#059669">{{ number_format($tabungIsi) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:8px 12px;background:var(--bg);border-radius:8px">
        <span style="font-size:12px;color:var(--muted)">Total Kepemilikan</span>
        <span style="font-size:18px;font-weight:700;color:#8B5CF6">{{ number_format($totalKepemilikan) }}</span>
      </div>
    </div>

    <a href="{{ route('dashboard.agen.distribusi.gudang.index') }}"
       style="display:block;text-align:center;margin-top:12px;font-size:12px;
              color:var(--accent);text-decoration:none;font-weight:500">
      Detail gudang →
    </a>
  </div>
</div>

{{-- Quick actions --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px">
  @foreach([
    ['url' => route('dashboard.agen.distribusi.index'),              'icon' => '🚛', 'label' => 'Input Realisasi'],
    ['url' => route('dashboard.agen.operasional.kitir.index'),       'icon' => '📋', 'label' => 'Kitir DO'],
    ['url' => route('dashboard.agen.akuntansi.tebusan.index'),       'icon' => '💵', 'label' => 'Tebusan'],
    ['url' => route('dashboard.agen.akuntansi.brimola.index'),       'icon' => '🏦', 'label' => 'BRImola'],
    ['url' => route('dashboard.agen.distribusi.gudang.index'),       'icon' => '🏪', 'label' => 'Gudang'],
    ['url' => route('dashboard.agen.akuntansi.buku-besar.jurnal'),   'icon' => '📒', 'label' => 'Jurnal'],
  ] as $q)
  <a href="{{ $q['url'] }}"
     style="display:flex;align-items:center;gap:10px;padding:12px 14px;
            background:var(--surface);border:1px solid var(--border);border-radius:10px;
            text-decoration:none;color:var(--text);font-size:13px;font-weight:500;
            transition:border-color .15s"
     onmouseover="this.style.borderColor='var(--accent)'"
     onmouseout="this.style.borderColor='var(--border)'">
    <span style="font-size:20px">{{ $q['icon'] }}</span>
    {{ $q['label'] }}
  </a>
  @endforeach
</div>

@endsection

@push('scripts')
<script>
// Grafik tooltip
function showTip(el, tgl, val) {
  const tip = document.getElementById('chartTip');
  const wrap = document.getElementById('chartWrap');
  const elRect = el.getBoundingClientRect();
  const wrapRect = wrap.getBoundingClientRect();
  tip.textContent = tgl + ': ' + parseInt(val).toLocaleString('id') + ' tb';
  tip.style.display = 'block';
  tip.style.left = (elRect.left - wrapRect.left + elRect.width/2) + 'px';
  tip.style.top = '0px';
}
function hideTip() {
  document.getElementById('chartTip').style.display = 'none';
}
</script>
@endpush
