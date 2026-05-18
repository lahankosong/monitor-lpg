@extends('layouts.app')
@section('title', 'Laporan Laba Rugi')
@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Laporan Laba Rugi</h1>
    <p style="font-size:12px;color:var(--muted)">
      Periode {{ $bulanList[$bulan] }} {{ $tahun }}
    </p>
  </div>
  <form method="GET" style="display:flex;gap:8px">
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

<div class="card" style="max-width:600px;overflow:hidden">
  {{-- Header --}}
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);text-align:center">
    <p style="font-weight:700;color:var(--text)">PT. MUARA GAS ELPIJI</p>
    <p style="font-size:13px;color:var(--muted);margin-top:2px">LAPORAN LABA RUGI</p>
    <p style="font-size:13px;color:var(--muted)">Periode {{ $bulanList[$bulan] }} {{ $tahun }}</p>
  </div>

  <table style="width:100%;border-collapse:collapse;font-size:13px">

    {{-- PENDAPATAN --}}
    <tr><td colspan="2" style="padding:12px 20px 4px;font-weight:700;color:var(--text);font-size:12px;text-transform:uppercase;letter-spacing:.05em">PENDAPATAN</td></tr>
    @foreach($pendapatan as $p)
    <tr>
      <td style="padding:5px 20px 5px 32px;color:var(--muted)">{{ $p->kode }} · {{ $p->nama }}</td>
      <td style="padding:5px 20px;text-align:right;color:var(--text);font-family:monospace">Rp {{ number_format($p->nilai) }}</td>
    </tr>
    @endforeach
    <tr style="border-top:1px solid var(--border);border-bottom:2px solid var(--border)">
      <td style="padding:8px 20px;font-weight:700;color:var(--text)">Total Pendapatan</td>
      <td style="padding:8px 20px;text-align:right;font-weight:700;color:#059669;font-family:monospace">Rp {{ number_format($totalPendapatan) }}</td>
    </tr>

    {{-- LABA KOTOR --}}
    <tr style="background:rgba(41,253,83,.04)">
      <td style="padding:8px 20px;font-weight:600;color:var(--text)">Laba Kotor (Pendapatan - HPP)</td>
      <td style="padding:8px 20px;text-align:right;font-weight:700;color:{{ $labaKotor>=0?'#059669':'#DC2626' }};font-family:monospace">Rp {{ number_format($labaKotor) }}</td>
    </tr>

    {{-- BEBAN --}}
    <tr><td colspan="2" style="padding:12px 20px 4px;font-weight:700;color:var(--text);font-size:12px;text-transform:uppercase;letter-spacing:.05em">BEBAN OPERASIONAL</td></tr>
    @foreach($beban as $b)
    <tr>
      <td style="padding:5px 20px 5px 32px;color:var(--muted)">{{ $b->kode }} · {{ $b->nama }}</td>
      <td style="padding:5px 20px;text-align:right;color:var(--text);font-family:monospace">Rp {{ number_format($b->nilai) }}</td>
    </tr>
    @endforeach
    <tr style="border-top:1px solid var(--border)">
      <td style="padding:8px 20px;font-weight:700;color:var(--text)">Total Beban</td>
      <td style="padding:8px 20px;text-align:right;font-weight:700;color:#DC2626;font-family:monospace">Rp {{ number_format($totalBeban) }}</td>
    </tr>

    {{-- LABA BERSIH --}}
    <tr style="background:{{ $labaBersih>=0?'rgba(41,253,83,.08)':'rgba(220,38,38,.06)' }};border-top:2px solid var(--border)">
      <td style="padding:12px 20px;font-weight:700;font-size:14px;color:var(--text)">
        {{ $labaBersih >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}
      </td>
      <td style="padding:12px 20px;text-align:right;font-weight:700;font-size:16px;font-family:monospace;color:{{ $labaBersih>=0?'#059669':'#DC2626' }}">
        Rp {{ number_format(abs($labaBersih)) }}
      </td>
    </tr>
  </table>
</div>

<div style="margin-top:16px;display:flex;gap:10px">
  <a href="{{ route('dashboard.agen.akuntansi.neraca') }}"
     style="border:1px solid var(--border);color:var(--text);background:var(--surface);border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
    Lihat Neraca →
  </a>
  <a href="{{ route('dashboard.agen.akuntansi.buku-besar.index') }}"
     style="border:1px solid var(--border);color:var(--text);background:var(--surface);border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
    Buku Besar →
  </a>
</div>
@endsection
