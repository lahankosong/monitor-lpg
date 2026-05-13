@extends('layouts.app')
@section('title', 'Audit BRImola')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Audit Pembayaran ↔ Distribusi</h1>
    <p style="font-size:12px;color:var(--muted)">Saldo per pangkalan kumulatif berdasarkan BRImola dan distribusi</p>
  </div>
  <form action="{{ route('dashboard.agen.akuntansi.brimola.audit.realokasi-semua') }}" method="POST">
    @csrf
    <button type="submit" onclick="return confirm('Re-alokasi semua pangkalan? Proses ini akan menghitung ulang FIFO dari awal.')"
            style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">
      ↻ Re-alokasi Semua
    </button>
  </form>
</div>

{{-- Summary cards --}}
@if($summary)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
  <div class="stat-card" style="border-left:3px solid #DC2626">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Total Piutang</p>
    <p style="font-size:20px;font-weight:700;color:#DC2626;margin-top:4px">{{ number_format($summary->total_piutang_tb ?? 0) }} tb</p>
    <p style="font-size:11px;color:var(--muted);margin-top:2px">
      Rp {{ number_format($summary->total_piutang_rp ?? 0) }} · {{ $summary->jml_piutang ?? 0 }} pangkalan
    </p>
  </div>
  <div class="stat-card" style="border-left:3px solid #059669">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Saldo Kredit</p>
    <p style="font-size:20px;font-weight:700;color:#059669;margin-top:4px">{{ number_format($summary->total_kredit_tb ?? 0) }} tb</p>
    <p style="font-size:11px;color:var(--muted);margin-top:2px">
      Rp {{ number_format($summary->total_kredit_rp ?? 0) }} · {{ $summary->jml_kredit ?? 0 }} pangkalan
    </p>
  </div>
  <div class="stat-card" style="border-left:3px solid #6B7280">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Pangkalan Lunas</p>
    <p style="font-size:20px;font-weight:700;color:var(--text);margin-top:4px">{{ $summary->jml_lunas ?? 0 }}</p>
    <p style="font-size:11px;color:var(--muted);margin-top:2px">Saldo = 0 tabung</p>
  </div>
</div>
@endif

{{-- Filter --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
  <select name="status" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua status</option>
    <option value="piutang"      {{ $status=='piutang'?'selected':'' }}>⚠ Piutang</option>
    <option value="saldo_kredit" {{ $status=='saldo_kredit'?'selected':'' }}>💰 Saldo Kredit</option>
    <option value="lunas"        {{ $status=='lunas'?'selected':'' }}>✓ Lunas</option>
  </select>
  <input name="search" value="{{ $search }}" placeholder="Cari pangkalan..."
         style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none;min-width:240px">
  <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- Tabel saldo --}}
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','Tipe','Dibayar','Didistribusi','Saldo','Nilai Saldo','Status',''] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($pangkalans as $p)
      @php
        $st = match($p->status) {
          'piutang'      => ['background:#FEE2E2;color:#991B1B','⚠ Piutang','#DC2626'],
          'saldo_kredit' => ['background:#D1FAE5;color:#065F46','💰 Saldo Kredit','#059669'],
          'lunas'        => ['background:#E5E7EB;color:#374151','✓ Lunas','var(--text)'],
          default        => ['background:#FEF3C7;color:#92400E','Belum dihitung','var(--muted)'],
        };
        $tipeColor = $p->tipe_pangkalan === 'kerjasama' ? '#1E40AF' : '#5B21B6';
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:10px 14px">
          <a href="{{ route('dashboard.agen.akuntansi.brimola.audit.detail', $p->id) }}"
             style="color:var(--text);text-decoration:none;font-weight:600">
            {{ $p->nama_pangkalan }}
          </a>
          <span style="display:block;font-family:monospace;font-size:11px;color:var(--muted)">{{ $p->no_reg }}</span>
        </td>
        <td style="padding:10px 14px">
          <span style="font-size:11px;color:{{ $tipeColor }};font-weight:600">
            {{ ucfirst($p->tipe_pangkalan ?? '—') }}
          </span>
        </td>
        <td style="padding:10px 14px;text-align:right">
          <span style="font-weight:600;color:#059669">{{ number_format($p->total_dibayar ?? 0) }}</span>
          <span style="display:block;font-size:11px;color:var(--muted)">tabung</span>
        </td>
        <td style="padding:10px 14px;text-align:right">
          <span style="font-weight:600;color:var(--text)">{{ number_format($p->total_didistribusi ?? 0) }}</span>
          <span style="display:block;font-size:11px;color:var(--muted)">tabung</span>
        </td>
        <td style="padding:10px 14px;text-align:right">
          <span style="font-size:16px;font-weight:700;color:{{ $st[2] }}">
            {{ $p->saldo_tabung > 0 ? '+' : '' }}{{ number_format($p->saldo_tabung ?? 0) }}
          </span>
          <span style="display:block;font-size:11px;color:var(--muted)">tabung</span>
        </td>
        <td style="padding:10px 14px;text-align:right">
          <span style="font-size:13px;font-weight:600;color:{{ $st[2] }}">
            @if(($p->saldo_nilai ?? 0) != 0)
              Rp {{ number_format(abs($p->saldo_nilai ?? 0)) }}
            @else
              —
            @endif
          </span>
        </td>
        <td style="padding:10px 14px">
          <span style="padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;{{ $st[0] }}">
            {{ $st[1] }}
          </span>
        </td>
        <td style="padding:10px 14px">
          <a href="{{ route('dashboard.agen.akuntansi.brimola.audit.detail', $p->id) }}"
             style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:4px 12px;font-size:11px;text-decoration:none;display:inline-block">
            Detail
          </a>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" style="padding:48px;text-align:center;color:var(--muted)">
        Belum ada data — klik "Re-alokasi Semua" untuk mulai
      </td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $pangkalans->links() }}</div>
</div>
@endsection
