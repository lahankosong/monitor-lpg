@extends('layouts.app')
@section('title', 'Audit — '.$pangkalan->nama_pangkalan)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <a href="{{ route('dashboard.agen.akuntansi.brimola.audit.index') }}"
       style="color:var(--muted);font-size:12px;text-decoration:none">← Kembali ke audit</a>
    <h1 style="font-size:20px;font-weight:700;color:var(--text);margin-top:4px">{{ $pangkalan->nama_pangkalan }}</h1>
    <p style="font-size:12px;color:var(--muted)">
      {{ $pangkalan->no_reg }} · {{ ucfirst($pangkalan->tipe ?? '—') }} · alokasi terakhir:
      {{ $saldo?->last_calculated_at ? \Carbon\Carbon::parse($saldo->last_calculated_at)->diffForHumans() : 'belum pernah' }}
    </p>
  </div>
  <form action="{{ route('dashboard.agen.akuntansi.brimola.audit.realokasi-pangkalan', $pangkalan->id) }}" method="POST">
    @csrf
    <button type="submit"
            style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">
      ↻ Re-alokasi Pangkalan Ini
    </button>
  </form>
</div>

{{-- Summary 3 cards --}}
@if($saldo)
@php
  $stColor = match($saldo->status) {
    'piutang'      => '#DC2626',
    'saldo_kredit' => '#059669',
    default        => 'var(--text)',
  };
  $stLabel = match($saldo->status) {
    'piutang'      => '⚠ Piutang',
    'saldo_kredit' => '💰 Saldo Kredit',
    default        => '✓ Lunas',
  };
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
  <div class="stat-card" style="border-left:3px solid #059669">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Total Dibayar</p>
    <p style="font-size:22px;font-weight:700;color:#059669;margin-top:4px">{{ number_format($saldo->total_dibayar) }} tb</p>
    <p style="font-size:11px;color:var(--muted)">Rp {{ number_format($saldo->total_nilai_bayar) }}</p>
  </div>
  <div class="stat-card" style="border-left:3px solid #3B82F6">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Didistribusi</p>
    <p style="font-size:22px;font-weight:700;color:#3B82F6;margin-top:4px">{{ number_format($saldo->total_didistribusi) }} tb</p>
    <p style="font-size:11px;color:var(--muted)">Rp {{ number_format($saldo->total_nilai_distribusi) }}</p>
  </div>
  <div class="stat-card" style="border-left:3px solid {{ $stColor }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Saldo Akhir</p>
    <p style="font-size:22px;font-weight:700;color:{{ $stColor }};margin-top:4px">
      {{ $saldo->saldo_tabung > 0 ? '+' : '' }}{{ number_format($saldo->saldo_tabung) }} tb
    </p>
    <p style="font-size:11px;font-weight:600;color:{{ $stColor }}">{{ $stLabel }}</p>
  </div>
</div>
@endif

{{-- Timeline kronologis --}}
<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Timeline Pembayaran &amp; Distribusi</h2>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">Urut kronologis dengan saldo berjalan (FIFO)</p>
  </div>

  @if(empty($timeline))
  <div style="padding:48px;text-align:center;color:var(--muted)">
    Belum ada aktivitas BRImola maupun distribusi untuk pangkalan ini
  </div>
  @else
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Tanggal','Tipe','No / Referensi','Qty','Terpakai','Sisa','Saldo Berjalan'] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($timeline as $row)
      @php
        $isBriva = $row->tipe === 'briva';
        $saldoColor = $row->saldo_running > 0 ? '#059669' : ($row->saldo_running < 0 ? '#DC2626' : 'var(--muted)');
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;white-space:nowrap;color:var(--muted)">
          {{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}
          @if($isBriva)
          <span style="display:block;font-size:10px">{{ \Carbon\Carbon::parse($row->tanggal)->format('H:i') }}</span>
          @endif
        </td>
        <td style="padding:9px 14px">
          @if($isBriva)
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600">
              💰 Bayar
            </span>
          @else
            <span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600">
              📦 Distribusi
            </span>
          @endif
        </td>
        <td style="padding:9px 14px;font-family:monospace;font-size:11px;color:var(--muted)">{{ $row->no_briva }}</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700;color:{{ $isBriva?'#059669':'#3B82F6' }}">
          {{ $isBriva ? '+' : '−' }}{{ number_format($row->jumlah_tabung) }}
        </td>
        <td style="padding:9px 14px;text-align:right;color:var(--muted);font-size:12px">
          {{ $isBriva && $row->qty_terpakai > 0 ? number_format($row->qty_terpakai) : '—' }}
        </td>
        <td style="padding:9px 14px;text-align:right;font-weight:600;color:{{ $isBriva && $row->qty_sisa > 0 ? '#F59E0B' : 'var(--muted)' }}">
          {{ $isBriva ? number_format($row->qty_sisa) : '—' }}
        </td>
        <td style="padding:9px 14px;text-align:right">
          <span style="font-size:14px;font-weight:700;color:{{ $saldoColor }}">
            {{ $row->saldo_running > 0 ? '+' : '' }}{{ number_format($row->saldo_running) }}
          </span>
          <span style="display:block;font-size:10px;color:var(--muted)">tabung</span>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>
@endsection
