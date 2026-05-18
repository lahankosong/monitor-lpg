@extends('layouts.app')
@section('title', 'Piutang Kerjasama')
@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Piutang Kerjasama</h1>
    <p style="font-size:12px;color:var(--muted)">Uang sewa tabung pangkalan kerjasama — dihitung per distribusi, ditagih awal bulan</p>
  </div>
  <div style="display:flex;gap:8px">
    <form action="{{ route('dashboard.agen.akuntansi.piutang.generate') }}" method="POST">
      @csrf
      <input type="hidden" name="bulan" value="{{ $bulan }}">
      <input type="hidden" name="tahun" value="{{ $tahun }}">
      <button type="submit"
              onclick="return confirm('Generate tagihan kerjasama untuk bulan ini dari distribusi bulan lalu?')"
              style="background:var(--accent);color:#151F28;border:none;border-radius:8px;
                     padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer">
        ⚡ Generate Tagihan
      </button>
    </form>
  </div>
</div>

{{-- Filter --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
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
  <select name="status" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua status</option>
    <option value="belum_bayar" {{ $status=='belum_bayar'?'selected':'' }}>⚠ Belum Bayar</option>
    <option value="sebagian"    {{ $status=='sebagian'?'selected':'' }}>◑ Sebagian</option>
    <option value="lunas"       {{ $status=='lunas'?'selected':'' }}>✓ Lunas</option>
  </select>
  <button type="submit" style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- Summary --}}
@if($summary)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
  @foreach([
    ['Total Tagihan','Rp '.number_format($summary->tagihan??0),'#3B82F6'],
    ['Sudah Bayar','Rp '.number_format($summary->bayar??0),'#059669'],
    ['Sisa Piutang','Rp '.number_format($summary->sisa??0),($summary->sisa??0)>0?'#DC2626':'#059669'],
  ] as [$l,$v,$c])
  <div class="stat-card" style="border-left:3px solid {{ $c }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">{{ $l }}</p>
    <p style="font-size:18px;font-weight:700;color:{{ $c }};margin-top:4px">{{ $v }}</p>
  </div>
  @endforeach
</div>
@endif

{{-- Tabel piutang --}}
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','No SJ','Distribusi','Jatuh Tempo','Qty','Tagihan','Sudah Bayar','Sisa','Status',''] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($piutang as $p)
      @php
        $sc = match($p->status) {
          'lunas'       => 'background:#D1FAE5;color:#065F46',
          'sebagian'    => 'background:#DBEAFE;color:#1E40AF',
          'belum_bayar' => 'background:#FEE2E2;color:#991B1B',
          default       => '',
        };
        $terlambat = $p->status !== 'lunas' && \Carbon\Carbon::parse($p->jatuh_tempo)->isPast();
      @endphp
      <tr style="border-top:1px solid var(--border){{ $terlambat ? ';background:rgba(220,38,38,.03)' : '' }}">
        <td style="padding:9px 14px">
          <span style="font-weight:600;color:var(--text)">{{ $p->nama_pangkalan }}</span>
          <span style="display:block;font-family:monospace;font-size:11px;color:var(--muted)">{{ $p->no_reg }}</span>
        </td>
        <td style="padding:9px 14px;font-family:monospace;font-size:11px;color:var(--accent)">{{ $p->no_sj ?? '—' }}</td>
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)">{{ \Carbon\Carbon::parse($p->tanggal_distribusi)->format('d/m/Y') }}</td>
        <td style="padding:9px 14px;font-size:12px;color:{{ $terlambat?'#DC2626':'var(--muted)' }}">
          {{ \Carbon\Carbon::parse($p->jatuh_tempo)->format('d/m/Y') }}
          @if($terlambat)<span style="font-size:10px;font-weight:600"> ⚠ TERLAMBAT</span>@endif
        </td>
        <td style="padding:9px 14px;text-align:right;font-weight:700">{{ number_format($p->qty_tabung) }}</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700">Rp {{ number_format($p->total_tagihan) }}</td>
        <td style="padding:9px 14px;text-align:right;color:#059669">Rp {{ number_format($p->total_bayar) }}</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700;color:{{ $p->sisa_tagihan>0?'#DC2626':'#059669' }}">
          {{ $p->sisa_tagihan > 0 ? 'Rp '.number_format($p->sisa_tagihan) : '—' }}
        </td>
        <td style="padding:9px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $sc }}">
            {{ ['lunas'=>'✓ Lunas','sebagian'=>'◑ Sebagian','belum_bayar'=>'⚠ Belum'][$p->status] ?? $p->status }}
          </span>
        </td>
        <td style="padding:9px 14px;white-space:nowrap">
          @if($p->status !== 'lunas')
          <button onclick="bukaBayar({{ $p->id }}, '{{ addslashes($p->nama_pangkalan) }}', {{ $p->sisa_tagihan }})"
                  style="background:none;border:1px solid var(--accent);color:var(--accent);
                         border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
            Catat Bayar
          </button>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="10" style="padding:48px;text-align:center;color:var(--muted)">
        Belum ada tagihan — klik "Generate Tagihan" untuk buat dari distribusi bulan lalu
      </td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $piutang->links() }}</div>
</div>

{{-- Modal Catat Bayar --}}
<div id="modal-bayar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-bayar')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:420px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Catat Pembayaran</h3>
        <p id="bayar-nama" style="font-size:12px;color:var(--muted);margin-top:2px">—</p>
      </div>
      <button onclick="closeModal('modal-bayar')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-bayar" method="POST" style="padding:18px 20px">
      @csrf
      <div style="background:var(--bg);border-radius:8px;padding:10px;margin-bottom:14px;text-align:center">
        <p style="font-size:10px;color:var(--muted)">Sisa Tagihan</p>
        <p id="bayar-sisa" style="font-size:24px;font-weight:700;color:#DC2626">Rp 0</p>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div>
          <label class="flabel">Tanggal Bayar *</label>
          <input type="date" name="tanggal_bayar" value="{{ now()->toDateString() }}" required class="finput">
        </div>
        <div>
          <label class="flabel">Metode</label>
          <select name="metode" class="finput">
            <option value="tunai">Tunai</option>
            <option value="transfer">Transfer</option>
            <option value="briva">BRIVA</option>
          </select>
        </div>
        <div>
          <label class="flabel">Jumlah (Rp) *</label>
          <input type="number" name="jumlah" id="bayar-jumlah" required min="1" class="finput" placeholder="0">
        </div>
        <div>
          <label class="flabel">Referensi</label>
          <input type="text" name="referensi" class="finput" placeholder="No. transfer / BRIVA">
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label class="flabel">Keterangan</label>
        <input type="text" name="keterangan" class="finput" placeholder="Catatan opsional">
      </div>
      <button type="submit" style="width:100%;background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Simpan Pembayaran
      </button>
    </form>
  </div>
</div>

@endsection
@push('scripts')
<script>
function bukaBayar(id, nama, sisa) {
  document.getElementById('form-bayar').action = `/dashboard/agen/akuntansi/piutang/${id}/bayar`;
  document.getElementById('bayar-nama').textContent = nama;
  document.getElementById('bayar-sisa').textContent = 'Rp ' + sisa.toLocaleString('id');
  document.getElementById('bayar-jumlah').value = sisa;
  document.getElementById('modal-bayar').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal('modal-bayar'); });
</script>
@endpush
