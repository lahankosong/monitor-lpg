@extends('layouts.app')
@section('title', 'Dashboard Akuntansi')
@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Dashboard Akuntansi</h1>
    <p style="font-size:12px;color:var(--muted)">Ringkasan piutang kerjasama dan kas kecil</p>
  </div>
  <form method="GET" style="display:flex;gap:8px;align-items:center">
    <select name="bulan" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
      @foreach($bulanList as $n => $nama)
        <option value="{{ $n }}" {{ $bulan==$n?'selected':'' }}>{{ $nama }}</option>
      @endforeach
    </select>
    <select name="tahun" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
      @for($y=now()->year;$y>=now()->year-1;$y--)
        <option value="{{ $y }}" {{ $tahun==$y?'selected':'' }}>{{ $y }}</option>
      @endfor
    </select>
    <button type="submit" style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">Tampilkan</button>
  </form>
</div>

{{-- 2 kolom utama --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

  {{-- Piutang Kerjasama --}}
  <div class="card" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h2 style="font-size:14px;font-weight:700;color:var(--text)">Piutang Kerjasama</h2>
      <a href="{{ route('dashboard.agen.akuntansi.piutang.index', ['bulan'=>$bulan,'tahun'=>$tahun]) }}"
         style="font-size:12px;color:var(--accent);text-decoration:none">Lihat semua →</a>
    </div>
    @if($piutangRekap)
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <div style="background:var(--bg);border-radius:8px;padding:10px">
        <p style="font-size:10px;color:var(--muted);text-transform:uppercase;font-weight:600">Total Tagihan</p>
        <p style="font-size:16px;font-weight:700;color:var(--text);margin-top:3px">Rp {{ number_format($piutangRekap->total_tagihan??0) }}</p>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:10px">
        <p style="font-size:10px;color:var(--muted);text-transform:uppercase;font-weight:600">Sisa Piutang</p>
        <p style="font-size:16px;font-weight:700;color:{{ ($piutangRekap->sisa_tagihan??0)>0?'#DC2626':'#059669' }};margin-top:3px">
          Rp {{ number_format($piutangRekap->sisa_tagihan??0) }}
        </p>
      </div>
    </div>
    <div style="display:flex;gap:8px;font-size:12px">
      <span style="background:#D1FAE5;color:#065F46;padding:3px 10px;border-radius:99px">✓ {{ $piutangRekap->jml_lunas??0 }} lunas</span>
      <span style="background:#DBEAFE;color:#1E40AF;padding:3px 10px;border-radius:99px">◑ {{ $piutangRekap->jml_sebagian??0 }} sebagian</span>
      <span style="background:#FEE2E2;color:#991B1B;padding:3px 10px;border-radius:99px">⚠ {{ $piutangRekap->jml_belum??0 }} belum</span>
    </div>
    @else
    <p style="color:var(--muted);font-size:13px">Belum ada data tagihan bulan ini.</p>
    @endif
  </div>

  {{-- Kas Kecil --}}
  <div class="card" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h2 style="font-size:14px;font-weight:700;color:var(--text)">Kas Kecil</h2>
      <a href="{{ route('dashboard.agen.akuntansi.kas.index', ['bulan'=>$bulan,'tahun'=>$tahun]) }}"
         style="font-size:12px;color:var(--accent);text-decoration:none">Lihat semua →</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <div style="background:var(--bg);border-radius:8px;padding:10px">
        <p style="font-size:10px;color:var(--muted);text-transform:uppercase;font-weight:600">Saldo Kas</p>
        <p style="font-size:16px;font-weight:700;color:{{ $saldoKas>=0?'#059669':'#DC2626' }};margin-top:3px">
          Rp {{ number_format(abs($saldoKas)) }}
        </p>
        <p style="font-size:10px;color:var(--muted)">{{ $saldoKas>=0?'tersedia':'defisit' }}</p>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:10px">
        <p style="font-size:10px;color:var(--muted);text-transform:uppercase;font-weight:600">Pengeluaran Bulan Ini</p>
        <p style="font-size:16px;font-weight:700;color:#DC2626;margin-top:3px">Rp {{ number_format($kasRekap->keluar??0) }}</p>
      </div>
    </div>
    <div style="font-size:12px;color:var(--muted)">
      {{ $kasRekap->total_trx??0 }} transaksi · masuk Rp {{ number_format($kasRekap->masuk??0) }}
    </div>
  </div>

</div>

{{-- Jatuh Tempo Bulan Ini --}}
@if($jatuhTempo->isNotEmpty())
<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Jatuh Tempo Bulan Ini</h2>
    <span style="font-size:12px;background:#FEE2E2;color:#991B1B;padding:3px 10px;border-radius:99px">
      {{ $jatuhTempo->count() }} tagihan
    </span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','Jatuh Tempo','Tagihan','Sisa','Status'] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($jatuhTempo as $p)
      @php $terlambat = \Carbon\Carbon::parse($p->jatuh_tempo)->isPast(); @endphp
      <tr style="border-top:1px solid var(--border){{ $terlambat?';background:rgba(220,38,38,.03)':'' }}">
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">
          {{ $p->nama_pangkalan }}
          <span style="display:block;font-family:monospace;font-size:11px;color:var(--muted)">{{ $p->no_reg }}</span>
        </td>
        <td style="padding:9px 14px;color:{{ $terlambat?'#DC2626':'var(--muted)' }};font-size:12px;font-weight:{{ $terlambat?'700':'400' }}">
          {{ \Carbon\Carbon::parse($p->jatuh_tempo)->format('d/m/Y') }}
          @if($terlambat) <span style="font-size:10px">⚠ TERLAMBAT {{ \Carbon\Carbon::parse($p->jatuh_tempo)->diffForHumans() }}</span>@endif
        </td>
        <td style="padding:9px 14px;text-align:right">Rp {{ number_format($p->total_tagihan) }}</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700;color:#DC2626">Rp {{ number_format($p->sisa_tagihan) }}</td>
        <td style="padding:9px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;
                       {{ $p->status==='sebagian'?'background:#DBEAFE;color:#1E40AF':'background:#FEE2E2;color:#991B1B' }}">
            {{ $p->status==='sebagian'?'◑ Sebagian':'⚠ Belum Bayar' }}
          </span>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@else
<div class="card" style="padding:32px;text-align:center;color:var(--muted)">
  ✓ Tidak ada tagihan jatuh tempo bulan ini
</div>
@endif

@endsection
