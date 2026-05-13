@extends('layouts.app')
@section('title', 'BRImola — Pembayaran')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">BRImola</h1>
    <p style="font-size:12px;color:var(--muted)">Pembayaran pangkalan via BRI Virtual Account</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button onclick="openModal('modal-input')"
            style="background:#059669;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">
      + Input Manual
    </button>
    <button onclick="openModal('modal-import')"
            style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer">
      ↑ Import Excel
    </button>
    <a href="{{ route('dashboard.agen.akuntansi.brimola.export', ['bulan'=>$bulan,'tahun'=>$tahun]) }}"
       style="border:1px solid var(--border);color:var(--text);background:var(--surface);border-radius:8px;padding:8px 14px;font-size:13px;text-decoration:none">
      ↓ Export Excel
    </a>
  </div>
</div>

{{-- Filter --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
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
    <option value="unmatched" {{ $status=='unmatched'?'selected':'' }}>⚠ Unmatched</option>
    <option value="matched"   {{ $status=='matched'?'selected':'' }}>✓ Matched</option>
    <option value="verified"  {{ $status=='verified'?'selected':'' }}>✓✓ Verified</option>
  </select>
  <input name="search" value="{{ $search }}" placeholder="Cari nama / no BRIVA..."
         style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none;min-width:200px">
  <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">
    Filter
  </button>
</form>

{{-- Rekap cards --}}
@if($rekap)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
  @foreach([
    ['Total Transaksi', number_format($rekap->total_trx), '#3B82F6'],
    ['Total Tabung',    number_format($rekap->total_tabung), '#059669'],
    ['Total Nilai',     'Rp '.number_format($rekap->total_nilai), '#F59E0B'],
    ['Unmatched',       number_format($rekap->total_unmatched), '#DC2626'],
    ['Verified',        number_format($rekap->total_verified), '#059669'],
  ] as [$l,$v,$c])
  <div class="stat-card" style="border-left:3px solid {{ $c }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">{{ $l }}</p>
    <p style="font-size:18px;font-weight:700;color:var(--text);margin-top:4px">{{ $v }}</p>
  </div>
  @endforeach
</div>
@endif

{{-- Alert unmatched --}}
@if($rekap && $rekap->total_unmatched > 0)
<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#991B1B">
  ⚠ <strong>{{ $rekap->total_unmatched }} transaksi</strong> tidak bisa dicocokkan otomatis dengan pangkalan.
  Cocokkan manual di tabel di bawah (filter: Unmatched).
</div>
@endif

{{-- Tab: Transaksi vs Rekap Pangkalan --}}
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:1px solid var(--border)">
  <button onclick="switchTab('trx')" id="tab-trx"
          style="background:none;border:none;border-bottom:2px solid var(--accent);padding:8px 16px;font-size:13px;font-weight:600;color:var(--accent);cursor:pointer">
    Detail Transaksi
  </button>
  <button onclick="switchTab('rekap')" id="tab-rekap"
          style="background:none;border:none;border-bottom:2px solid transparent;padding:8px 16px;font-size:13px;font-weight:500;color:var(--muted);cursor:pointer">
    Rekap per Pangkalan
  </button>
</div>

{{-- Tabel detail transaksi --}}
<div id="view-trx">
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Tanggal','Pangkalan (file)','Pangkalan DB','No BRIVA','Tabung','Nilai','Status',''] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($transaksi as $t)
      @php
        $sc = match($t->status) {
          'unmatched' => 'background:#FEE2E2;color:#991B1B',
          'matched'   => 'background:#DBEAFE;color:#1E40AF',
          'verified'  => 'background:#D1FAE5;color:#065F46',
          default     => '',
        };
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;white-space:nowrap;color:var(--muted)">
          {{ \Carbon\Carbon::parse($t->tanggal_bayar)->format('d/m/Y') }}
          <span style="display:block;font-size:10px">{{ \Carbon\Carbon::parse($t->tanggal_bayar)->format('H:i') }}</span>
        </td>
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">{{ $t->nama_pangkalan }}</td>
        <td style="padding:9px 14px;font-size:12px">
          @if($t->pangkalan_nama_db)
            <span style="color:#059669">{{ $t->pangkalan_nama_db }}</span>
            <span style="display:block;font-size:10px;color:var(--muted);font-family:monospace">{{ $t->pangkalan_no_reg }}</span>
          @else
            <span style="color:#DC2626">—</span>
          @endif
        </td>
        <td style="padding:9px 14px;font-family:monospace;font-size:11px;color:var(--muted)">{{ $t->no_briva }}</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700">{{ number_format($t->jumlah_tabung) }}</td>
        <td style="padding:9px 14px;text-align:right;font-size:12px;color:var(--muted)">
          @if($t->total_bayar > 0) Rp {{ number_format($t->total_bayar) }} @else — @endif
        </td>
        <td style="padding:9px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $sc }}">
            {{ ucfirst($t->status) }}
          </span>
        </td>
        <td style="padding:9px 14px">
          @if($t->status === 'unmatched')
          <button onclick="bukaMatch({{ $t->id }}, '{{ addslashes($t->nama_pangkalan) }}')"
                  style="background:none;border:1px solid var(--accent);color:var(--accent);border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
            Cocokkan
          </button>
          @elseif($t->status === 'matched')
          <form action="{{ route('dashboard.agen.akuntansi.brimola.verify') }}" method="POST" style="display:inline">
            @csrf
            <input type="hidden" name="transaksi_ids[]" value="{{ $t->id }}">
            <button type="submit" style="background:none;border:1px solid #059669;color:#059669;border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
              Verify
            </button>
          </form>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="8" style="padding:48px;text-align:center;color:var(--muted)">
        Belum ada data BRImola bulan ini
      </td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $transaksi->links() }}</div>
</div>
</div>

{{-- Rekap per pangkalan --}}
<div id="view-rekap" style="display:none">
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Pangkalan','No Reg','Transaksi','Total Tabung','Total Nilai','Status'] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($rekapPangkalan as $r)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">
          {{ $r->nama_db ?? $r->nama_pangkalan }}
          @if(!$r->pangkalan_id)
            <span style="display:block;font-size:10px;color:#DC2626">⚠ Belum dicocokkan</span>
          @endif
        </td>
        <td style="padding:9px 14px;font-family:monospace;color:var(--muted)">{{ $r->no_reg ?? '—' }}</td>
        <td style="padding:9px 14px;text-align:center">{{ $r->jml_trx }}×</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700">{{ number_format($r->total_tabung) }}</td>
        <td style="padding:9px 14px;text-align:right;color:var(--muted)">
          @if($r->total_nilai > 0) Rp {{ number_format($r->total_nilai) }} @else — @endif
        </td>
        <td style="padding:9px 14px">
          @if(!$r->pangkalan_id)
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;background:#FEE2E2;color:#991B1B">Unmatched</span>
          @else
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;background:#D1FAE5;color:#065F46">OK</span>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--muted)">Belum ada data</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
</div>

{{-- History import --}}
@if($batches->isNotEmpty())
<h2 style="font-size:14px;font-weight:600;color:var(--text);margin:20px 0 10px">Riwayat Import</h2>
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:12px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Nama File','Periode','Transaksi','Matched','Unmatched','Nilai','Diimport'] as $h)
          <th style="text-align:left;padding:8px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($batches as $b)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:8px 14px;color:var(--text)">{{ $b->nama_file }}</td>
        <td style="padding:8px 14px;color:var(--muted)">
          {{ \Carbon\Carbon::parse($b->periode_dari)->format('d/m') }} —
          {{ \Carbon\Carbon::parse($b->periode_sampai)->format('d/m/Y') }}
        </td>
        <td style="padding:8px 14px;text-align:center">{{ $b->total_transaksi }}</td>
        <td style="padding:8px 14px;text-align:center;color:#059669">{{ $b->total_matched }}</td>
        <td style="padding:8px 14px;text-align:center;color:{{ $b->total_unmatched>0?'#DC2626':'var(--muted)' }}">
          {{ $b->total_unmatched ?: '—' }}
        </td>
        <td style="padding:8px 14px;color:var(--muted)">Rp {{ number_format($b->total_nilai) }}</td>
        <td style="padding:8px 14px;color:var(--muted)">
          {{ \Carbon\Carbon::parse($b->created_at)->format('d/m/Y H:i') }}
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- MODAL IMPORT --}}
<div id="modal-import" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-import')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:460px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Import Data BRImola</h3>
      <button onclick="closeModal('modal-import')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.akuntansi.brimola.import') }}" method="POST"
          enctype="multipart/form-data" style="padding:20px">
      @csrf
      <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px;color:var(--muted);line-height:1.6">
        <p style="font-weight:600;color:var(--text);margin-bottom:4px">Format file yang diterima:</p>
        <p>Kolom: <code>pangkalan | no_briva | tanggal_bayar | jumlah_tabung</code></p>
        <p>Format: <code>.xlsx</code> atau <code>.csv</code></p>
        <p>Nama pangkalan akan dicocokkan otomatis dengan database.</p>
      </div>
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">
          File Excel / CSV BRImola
        </label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
               style="width:100%;font-size:13px;color:var(--text);padding:6px 0">
      </div>
      @if(session('success'))
      <div style="background:#D1FAE5;border-radius:8px;padding:10px;margin-bottom:12px;font-size:12px;color:#065F46;font-weight:500">
        ✓ {{ session('success') }}
      </div>
      @endif
      <div style="display:flex;gap:8px">
        <button type="submit" style="flex:1;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;font-weight:600;cursor:pointer">
          Import Sekarang
        </button>
        <button type="button" onclick="closeModal('modal-import')"
                style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:10px 16px;font-size:13px;cursor:pointer">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

{{-- MODAL MATCH --}}
<div id="modal-match" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-match')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:420px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Cocokkan Pangkalan</h3>
        <p id="match-nama" style="font-size:12px;color:var(--muted);margin-top:2px">—</p>
      </div>
      <button onclick="closeModal('modal-match')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.akuntansi.brimola.match') }}" method="POST" style="padding:20px">
      @csrf
      <input type="hidden" name="transaksi_id" id="match-trx-id">
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">
          Pilih Pangkalan yang Sesuai
        </label>
        <select name="pangkalan_id" required
                style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
          <option value="">-- Pilih Pangkalan --</option>
          @foreach($pangkalans as $p)
            <option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>
          @endforeach
        </select>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="flex:1;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;font-weight:600;cursor:pointer">
          Simpan Pencocokan
        </button>
        <button type="button" onclick="closeModal('modal-match')"
                style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:10px 16px;font-size:13px;cursor:pointer">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>


{{-- MODAL INPUT MANUAL --}}
<div id="modal-input" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-input')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:480px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Input Manual Pembayaran</h3>
      <button onclick="closeModal('modal-input')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.akuntansi.brimola.store') }}" method="POST" style="padding:20px">
      @csrf
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">
          Pangkalan <span style="color:#DC2626">*</span>
        </label>
        <select name="pangkalan_id" required
                style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
          <option value="">-- Pilih Pangkalan --</option>
          @foreach($pangkalans as $p)
            <option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>
          @endforeach
        </select>
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">
          No BRIVA <span style="color:#DC2626">*</span>
        </label>
        <input type="text" name="no_briva" required placeholder="Nomor Virtual Account BRI"
               style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box">
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">
          Tanggal Bayar <span style="color:#DC2626">*</span>
        </label>
        <input type="datetime-local" name="tanggal_bayar" required
               style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">
            Jumlah Tabung <span style="color:#DC2626">*</span>
          </label>
          <input type="number" name="jumlah_tabung" id="input-tabung" required min="1" placeholder="0"
                 onchange="hitungTotal()" oninput="hitungTotal()"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">
            Harga/Tabung <span style="color:#DC2626">*</span>
          </label>
          <input type="number" name="harga_per_tabung" id="input-harga" required min="0" placeholder="0"
                 value="{{ \App\Models\HargaReferensi::aktif()?->harga_per_tabung ?? 0 }}"
                 onchange="hitungTotal()" oninput="hitungTotal()"
                 style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box">
        </div>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:16px">
        <p style="font-size:12px;color:var(--muted);margin-bottom:4px">Total Bayar</p>
        <p id="input-total" style="font-size:18px;font-weight:700;color:var(--text)">Rp 0</p>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" style="flex:1;background:#059669;color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;font-weight:600;cursor:pointer">
          Simpan Pembayaran
        </button>
        <button type="button" onclick="closeModal('modal-input')"
                style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:10px 16px;font-size:13px;cursor:pointer">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

@endsection
@push('scripts')
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') ['modal-input','modal-import','modal-match'].forEach(closeModal); });

function bukaMatch(id, nama) {
  document.getElementById('match-trx-id').value = id;
  document.getElementById('match-nama').textContent = `"${nama}"`;
  openModal('modal-match');
}

function switchTab(tab) {
  document.getElementById('view-trx').style.display   = tab==='trx'   ? 'block' : 'none';
  document.getElementById('view-rekap').style.display  = tab==='rekap' ? 'block' : 'none';
  document.getElementById('tab-trx').style.borderBottomColor   = tab==='trx'   ? 'var(--accent)' : 'transparent';
  document.getElementById('tab-rekap').style.borderBottomColor = tab==='rekap' ? 'var(--accent)' : 'transparent';
  document.getElementById('tab-trx').style.color   = tab==='trx'   ? 'var(--accent)' : 'var(--muted)';
  document.getElementById('tab-rekap').style.color = tab==='rekap' ? 'var(--accent)' : 'var(--muted)';
}

function hitungTotal() {
  const tabung = parseInt(document.getElementById('input-tabung').value) || 0;
  const harga = parseInt(document.getElementById('input-harga').value) || 0;
  const total = tabung * harga;
  document.getElementById('input-total').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

@if(session('success')) openModal('modal-import'); @endif
</script>
@endpush
