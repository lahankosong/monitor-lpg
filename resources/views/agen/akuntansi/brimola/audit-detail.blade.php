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

{{-- Verifikasi Transaksi BRImola --}}
<div class="card" style="overflow:hidden;margin-top:20px">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <div>
      <h2 style="font-size:14px;font-weight:600;color:var(--text)">Verifikasi Pembayaran BRImola</h2>
      <p style="font-size:12px;color:var(--muted);margin-top:2px">
        @if($brimolaStats)
          {{ $brimolaStats->total }} transaksi:
          <span style="color:#059669">{{ $brimolaStats->verified }} verified</span>,
          <span style="color:#3B82F6">{{ $brimolaStats->matched }} matched</span>
          @if($brimolaStats->unmatched > 0)
            , <span style="color:#DC2626">{{ $brimolaStats->unmatched }} unmatched</span>
          @endif
        @else
          Belum ada transaksi
        @endif
      </p>
    </div>
    @if($brimolaStats && $brimolaStats->matched > 0)
    <form action="{{ route('dashboard.agen.akuntansi.brimola.audit.verify-all', $pangkalan->id) }}" method="POST">
      @csrf
      <button type="submit" onclick="return confirm('Verifikasi semua transaksi matched?')"
              style="background:#059669;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:500;cursor:pointer">
        ✓ Verify Semua Matched
      </button>
    </form>
    @endif
  </div>

  @if($brimolaTransaksi->isEmpty())
  <div style="padding:48px;text-align:center;color:var(--muted)">
    Belum ada transaksi BRImola untuk pangkalan ini
  </div>
  @else
  <form action="{{ route('dashboard.agen.akuntansi.brimola.audit.verify', $pangkalan->id) }}" method="POST" id="form-verify">
    @csrf
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--bg)">
          <th style="padding:9px 14px;width:40px">
            <input type="checkbox" id="check-all" onchange="toggleAll(this)">
          </th>
          @foreach(['Tanggal','No BRIVA','Tabung','Nilai','Status',''] as $h)
            <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($brimolaTransaksi as $t)
        @php
          $sc = match($t->status) {
            'unmatched' => 'background:#FEE2E2;color:#991B1B',
            'matched'   => 'background:#DBEAFE;color:#1E40AF',
            'verified'  => 'background:#D1FAE5;color:#065F46',
            default     => '',
          };
        @endphp
        <tr style="border-top:1px solid var(--border)">
          <td style="padding:9px 14px;text-align:center">
            @if($t->status === 'matched')
              <input type="checkbox" name="transaksi_ids[]" value="{{ $t->id }}" class="check-item">
            @endif
          </td>
          <td style="padding:9px 14px;white-space:nowrap;color:var(--muted)">
            {{ \Carbon\Carbon::parse($t->tanggal_bayar)->format('d/m/Y') }}
            <span style="display:block;font-size:10px">{{ \Carbon\Carbon::parse($t->tanggal_bayar)->format('H:i') }}</span>
          </td>
          <td style="padding:9px 14px;font-family:monospace;font-size:11px">{{ $t->no_briva }}</td>
          <td style="padding:9px 14px;text-align:right;font-weight:700">{{ number_format($t->jumlah_tabung) }}</td>
          <td style="padding:9px 14px;text-align:right;color:var(--muted)">Rp {{ number_format($t->total_bayar) }}</td>
          <td style="padding:9px 14px">
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $sc }}">
              {{ ucfirst($t->status) }}
            </span>
          </td>
          <td style="padding:9px 14px">
            @if($t->status === 'matched')
              <button type="button" onclick="verifySingle({{ $t->id }})"
                      style="background:none;border:1px solid #059669;color:#059669;border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
                Verify
              </button>
            @elseif($t->status === 'verified')
              <span style="color:#059669;font-size:11px">✓</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div style="padding:12px 14px;border-top:1px solid var(--border);display:flex;gap:8px;align-items:center">
      <button type="submit" id="btn-verify-selected" disabled
              style="background:#059669;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:500;cursor:pointer;opacity:0.5">
        ✓ Verify Terpilih (<span id="count-selected">0</span>)
      </button>
      <span style="font-size:12px;color:var(--muted)">Pilih transaksi matched untuk diverifikasi</span>
    </div>
  </form>
  @endif
</div>

@push('scripts')
<script>
function toggleAll(el) {
  document.querySelectorAll('.check-item').forEach(c => c.checked = el.checked);
  updateCount();
}
function updateCount() {
  const count = document.querySelectorAll('.check-item:checked').length;
  document.getElementById('count-selected').textContent = count;
  const btn = document.getElementById('btn-verify-selected');
  btn.disabled = count === 0;
  btn.style.opacity = count === 0 ? '0.5' : '1';
}
function verifySingle(id) {
  document.querySelectorAll('.check-item').forEach(c => c.checked = c.value == id);
  document.getElementById('form-verify').submit();
}
document.querySelectorAll('.check-item').forEach(c => c.addEventListener('change', updateCount));
</script>
@endpush

@endsection
