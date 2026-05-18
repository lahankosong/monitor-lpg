@extends('layouts.app')
@section('title', 'Neraca')
@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Neraca</h1>
    <p style="font-size:12px;color:var(--muted)">Per tanggal {{ \Carbon\Carbon::parse($tanggal)->format('d F Y') }}</p>
  </div>
  <form method="GET" style="display:flex;gap:8px">
    <input type="date" name="tanggal" value="{{ $tanggal }}"
           style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <button type="submit" style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">Tampilkan</button>
  </form>
</div>

@if(abs($balance) > 100)
<div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#991B1B">
  ⚠ Neraca tidak balance — selisih Rp {{ number_format(abs($balance)) }}. Periksa jurnal yang belum dicatat.
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

  {{-- ASET --}}
  <div class="card" style="overflow:hidden">
    <div style="padding:12px 18px;border-bottom:1px solid var(--border);background:rgba(59,130,246,.06)">
      <p style="font-weight:700;font-size:13px;color:#3B82F6;text-transform:uppercase;letter-spacing:.05em">ASET</p>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      @foreach($data['aset'] as $a)
      <tr style="{{ !$loop->last?'border-bottom:1px solid var(--border)':'' }}">
        <td style="padding:8px 18px;color:var(--muted)">
          <span style="font-size:11px;font-family:monospace">{{ $a->kode }}</span>
          <span style="margin-left:8px">{{ $a->nama }}</span>
        </td>
        <td style="padding:8px 18px;text-align:right;font-family:monospace;color:{{ $a->saldo>0?'var(--text)':'var(--muted)' }}">
          Rp {{ number_format($a->saldo) }}
        </td>
      </tr>
      @endforeach
      <tr style="border-top:2px solid var(--border);background:rgba(59,130,246,.04)">
        <td style="padding:10px 18px;font-weight:700;color:var(--text)">TOTAL ASET</td>
        <td style="padding:10px 18px;text-align:right;font-weight:700;font-size:14px;color:#3B82F6;font-family:monospace">
          Rp {{ number_format($totalAset) }}
        </td>
      </tr>
    </table>
  </div>

  {{-- KEWAJIBAN + MODAL --}}
  <div style="display:flex;flex-direction:column;gap:12px">
    {{-- Kewajiban --}}
    <div class="card" style="overflow:hidden">
      <div style="padding:12px 18px;border-bottom:1px solid var(--border);background:rgba(220,38,38,.06)">
        <p style="font-weight:700;font-size:13px;color:#DC2626;text-transform:uppercase;letter-spacing:.05em">KEWAJIBAN</p>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        @foreach($data['kewajiban'] as $k)
        <tr style="{{ !$loop->last?'border-bottom:1px solid var(--border)':'' }}">
          <td style="padding:8px 18px;color:var(--muted)">
            <span style="font-size:11px;font-family:monospace">{{ $k->kode }}</span>
            <span style="margin-left:8px">{{ $k->nama }}</span>
          </td>
          <td style="padding:8px 18px;text-align:right;font-family:monospace;color:{{ $k->saldo>0?'var(--text)':'var(--muted)' }}">
            Rp {{ number_format($k->saldo) }}
          </td>
        </tr>
        @endforeach
        <tr style="border-top:2px solid var(--border);background:rgba(220,38,38,.04)">
          <td style="padding:10px 18px;font-weight:700;color:var(--text)">Total Kewajiban</td>
          <td style="padding:10px 18px;text-align:right;font-weight:700;color:#DC2626;font-family:monospace">
            Rp {{ number_format($totalKewajiban) }}
          </td>
        </tr>
      </table>
    </div>

    {{-- Modal --}}
    <div class="card" style="overflow:hidden">
      <div style="padding:12px 18px;border-bottom:1px solid var(--border);background:rgba(5,150,105,.06)">
        <p style="font-weight:700;font-size:13px;color:#059669;text-transform:uppercase;letter-spacing:.05em">MODAL</p>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        @foreach($data['modal'] as $m)
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px 18px;color:var(--muted)">
            <span style="font-size:11px;font-family:monospace">{{ $m->kode }}</span>
            <span style="margin-left:8px">{{ $m->nama }}</span>
          </td>
          <td style="padding:8px 18px;text-align:right;font-family:monospace;color:var(--text)">
            Rp {{ number_format($m->saldo) }}
          </td>
        </tr>
        @endforeach
        {{-- Laba berjalan --}}
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:8px 18px;color:var(--muted)">
            <span style="font-size:11px;font-family:monospace">3099</span>
            <span style="margin-left:8px">Laba Berjalan</span>
          </td>
          <td style="padding:8px 18px;text-align:right;font-family:monospace;color:{{ $labaYtd>=0?'#059669':'#DC2626' }}">
            Rp {{ number_format($labaYtd) }}
          </td>
        </tr>
        <tr style="border-top:2px solid var(--border);background:rgba(5,150,105,.04)">
          <td style="padding:10px 18px;font-weight:700;color:var(--text)">Total Modal</td>
          <td style="padding:10px 18px;text-align:right;font-weight:700;color:#059669;font-family:monospace">
            Rp {{ number_format($totalModal) }}
          </td>
        </tr>
      </table>
    </div>

    {{-- Total Kewajiban + Modal --}}
    <div class="card" style="padding:12px 18px;background:rgba(41,253,83,.06)">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700;color:var(--text)">TOTAL KEWAJIBAN + MODAL</span>
        <span style="font-weight:700;font-size:15px;font-family:monospace;color:{{ abs($balance)<=100?'#059669':'#DC2626' }}">
          Rp {{ number_format($totalKewajiban + $totalModal) }}
        </span>
      </div>
      @if(abs($balance) <= 100)
      <p style="font-size:11px;color:#059669;margin-top:4px">✓ Balance</p>
      @else
      <p style="font-size:11px;color:#DC2626;margin-top:4px">⚠ Selisih Rp {{ number_format(abs($balance)) }}</p>
      @endif
    </div>
  </div>

</div>
@endsection
