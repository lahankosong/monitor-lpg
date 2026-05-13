@extends('layouts.app')
@section('title', 'Monitoring Stok')

@section('content')
<div style="margin-bottom:20px">
  <h1 style="font-size:20px;font-weight:700;color:var(--text)">Monitoring Stok</h1>
  <p style="font-size:12px;color:var(--muted);margin-top:2px">Gendongan (armada) · Gudang agen · Titipan antar agen</p>
</div>

@if(session('success'))
<div style="background:#D1FAE5;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#065F46;font-weight:500">
  ✓ {{ session('success') }}
</div>
@endif

{{-- ── GENDONGAN (STOK ARMADA) ─────────────────────────────────── --}}
<h2 style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:10px">
  ⚡ Stok Armada (Gendongan)
  <span style="font-size:11px;font-weight:400;color:#F59E0B;margin-left:8px">Wajib habis sebelum ambil DO berikutnya</span>
</h2>

@if($gendongan->isEmpty())
<div class="card" style="padding:24px;text-align:center;color:var(--muted);margin-bottom:20px;font-size:13px">
  Tidak ada gendongan aktif — semua armada sudah bersih ✓
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-bottom:20px">
  @foreach($gendongan as $armadaId => $stoks)
  @php $totalGnd = $stoks->sum('sisa_akhir'); @endphp
  <div class="card" style="overflow:hidden">
    <div style="padding:12px 14px;background:linear-gradient(135deg,#92400E,#B45309);color:#fff;display:flex;justify-content:space-between;align-items:center">
      <div>
        <p style="font-size:15px;font-weight:700;font-family:monospace">{{ $stoks->first()->armada?->no_polisi }}</p>
        <p style="font-size:11px;opacity:.8;margin-top:2px">{{ $stoks->count() }} trip tersisa</p>
      </div>
      <div style="text-align:right">
        <p style="font-size:28px;font-weight:700">{{ number_format($totalGnd) }}</p>
        <p style="font-size:10px;opacity:.7">tabung</p>
      </div>
    </div>
    @foreach($stoks as $s)
    <div style="padding:8px 14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:12px">
      <div>
        <span style="color:var(--muted)">SJ: </span>
        <span style="font-family:monospace;color:var(--accent)">{{ $s->sjHeader?->no_sj }}</span>
        <span style="display:block;color:var(--muted)">{{ $s->tanggal->format('d/m/Y') }}</span>
      </div>
      <div style="text-align:right">
        <span style="font-size:16px;font-weight:700;color:#F59E0B">{{ number_format($s->sisa_akhir) }}</span>
        <span style="font-size:10px;color:var(--muted)"> tb</span>
      </div>
    </div>
    @endforeach
    {{-- Tombol: Masukkan ke SJ baru --}}
    <div style="padding:10px 14px">
      <button onclick="bukaModalGendongan({{ $armadaId }}, '{{ $stoks->first()->armada?->no_polisi }}', {{ $totalGnd }}, {{ $stoks->first()->id }})"
              style="width:100%;background:#D97706;color:#fff;border:none;border-radius:8px;padding:8px;font-size:12px;font-weight:600;cursor:pointer">
        Masukkan ke SJ (armada sama) →
      </button>
    </div>
  </div>
  @endforeach
</div>
@endif

{{-- ── STOK GUDANG ─────────────────────────────────────────────── --}}
<h2 style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:10px">
  🏪 Stok Gudang
  <span style="font-size:11px;font-weight:400;color:#7C3AED;margin-left:8px">Bisa diambil kapan saja</span>
</h2>

@if($gudang->isEmpty())
<div class="card" style="padding:24px;text-align:center;color:var(--muted);margin-bottom:20px;font-size:13px">
  Tidak ada stok di gudang
</div>
@else
<div class="card" style="overflow:hidden;margin-bottom:20px">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Tgl Masuk','Sumber','Sisa Stok','Keterangan',''] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($gudang as $g)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;color:var(--muted)">{{ $g->tgl_masuk->format('d/m/Y') }}</td>
        <td style="padding:9px 14px">
          @if($g->sumber === 'titipan_agen')
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600">
              🤝 Titipan {{ $g->agenAsal?->nama_agen }}
            </span>
          @elseif($g->sumber === 'sisa_sj')
            <span style="background:#EDE9FE;color:#5B21B6;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600">
              📦 Sisa SJ
            </span>
          @else
            <span style="color:var(--muted)">Manual</span>
          @endif
        </td>
        <td style="padding:9px 14px;font-size:16px;font-weight:700;color:#7C3AED">
          {{ number_format($g->sisa_stok) }} <span style="font-size:11px;font-weight:400;color:var(--muted)">tb</span>
        </td>
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)">{{ $g->keterangan }}</td>
        <td style="padding:9px 14px">
          <button onclick="bukaModalAmbilGudang({{ $g->id }}, {{ $g->sisa_stok }}, '{{ $g->tgl_masuk->format('d/m/Y') }}')"
                  style="background:#7C3AED;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer">
            Ambil
          </button>
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="border-top:2px solid var(--border);background:var(--bg)">
        <td colspan="2" style="padding:9px 14px;font-weight:600;color:var(--muted)">TOTAL TERSEDIA</td>
        <td style="padding:9px 14px;font-size:16px;font-weight:700;color:#7C3AED">
          {{ number_format($gudang->sum('sisa_stok')) }} <span style="font-size:11px;font-weight:400">tb</span>
        </td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
</div>
@endif

{{-- ── TITIPAN ANTAR AGEN ─────────────────────────────────────── --}}
@if($titipanAktif->isNotEmpty())
<h2 style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:10px">
  🤝 Titipan Antar Agen (Aktif)
</h2>
<div class="card" style="overflow:hidden;margin-bottom:20px">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Tgl Titip','Dari Agen','Ke Agen','Qty Isi','Pinjam Kosong','Status',''] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($titipanAktif as $t)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;color:var(--muted)">{{ $t->tgl_titip->format('d/m/Y') }}</td>
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">{{ $t->agenAsal?->nama_agen }}</td>
        <td style="padding:9px 14px;font-weight:600;color:var(--text)">{{ $t->agenTujuan?->nama_agen }}</td>
        <td style="padding:9px 14px;font-weight:700;color:#059669">{{ number_format($t->qty_tabung_isi) }} tb</td>
        <td style="padding:9px 14px;color:{{ $t->qty_tabung_kosong>0?'#DC2626':'var(--muted)' }}">
          {{ $t->qty_tabung_kosong > 0 ? number_format($t->qty_tabung_kosong).' tb' : '—' }}
        </td>
        <td style="padding:9px 14px">
          <span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600">Aktif</span>
        </td>
        <td style="padding:9px 14px">
          <form action="{{ route('dashboard.agen.distribusi.selesai-antar-agen', $t) }}" method="POST" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" style="background:none;border:1px solid #059669;color:#059669;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer"
                    onclick="return confirm('Tandai transaksi ini selesai?')">
              Selesai
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- ── MODAL AMBIL GUDANG ──────────────────────────────────────── --}}
<div id="modal-ambil-gudang" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="document.getElementById('modal-ambil-gudang').style.display='none'">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:400px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Ambil dari Gudang</h3>
      <button onclick="document.getElementById('modal-ambil-gudang').style.display='none'" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.ambil-gudang') }}" method="POST" style="padding:18px 20px">
      @csrf
      <input type="hidden" name="gudang_stok_id" id="ag_gudang_id">

      <div style="background:var(--bg);border-radius:8px;padding:10px;margin-bottom:14px;text-align:center">
        <p style="font-size:11px;color:var(--muted)">Tersedia</p>
        <p id="ag_tersedia" style="font-size:28px;font-weight:700;color:#7C3AED">0</p>
        <p style="font-size:11px;color:var(--muted)">tabung</p>
      </div>

      <div style="margin-bottom:14px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Masukkan ke SJ</label>
        <select name="sj_header_id"
                style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
          <option value="">-- Pilih SJ Aktif --</option>
          @foreach(\App\Models\SuratJalanHeader::where('status','aktif')->orderByDesc('tanggal')->get() as $sj)
            <option value="{{ $sj->id }}">{{ $sj->no_sj }} · {{ $sj->tanggal->format('d/m/Y') }}</option>
          @endforeach
        </select>
      </div>

      <div style="margin-bottom:16px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Qty Diambil</label>
        <input type="number" name="qty" id="ag_qty" min="1" class="finput"
               style="border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:20px;font-weight:700;text-align:center;width:100%;background:var(--surface);color:var(--text);outline:none;box-sizing:border-box">
      </div>

      <button type="submit" style="width:100%;background:#7C3AED;color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Ambil dari Gudang
      </button>
    </form>
  </div>
</div>

{{-- ── MODAL GENDONGAN KE SJ ──────────────────────────────────── --}}
<div id="modal-gendongan" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="document.getElementById('modal-gendongan').style.display='none'">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:400px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Gendongan → SJ Aktif</h3>
        <p id="gnd_armada_label" style="font-size:12px;color:var(--muted)">Armada</p>
      </div>
      <button onclick="document.getElementById('modal-gendongan').style.display='none'" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.konfirmasi-gendongan') }}" method="POST" style="padding:18px 20px">
      @csrf
      <input type="hidden" name="stok_armada_id" id="gnd_stok_id">

      <div style="background:#FEF3C7;border-radius:8px;padding:10px;margin-bottom:14px;text-align:center">
        <p style="font-size:11px;color:#92400E">Gendongan Tersedia</p>
        <p id="gnd_tersedia" style="font-size:28px;font-weight:700;color:#D97706">0</p>
        <p style="font-size:11px;color:#92400E">tabung · wajib dihabiskan</p>
      </div>

      <div style="margin-bottom:14px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Masukkan ke SJ</label>
        <select name="sj_header_id"
                style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none">
          <option value="">-- Pilih SJ Aktif --</option>
          @foreach(\App\Models\SuratJalanHeader::where('status','aktif')->orderByDesc('tanggal')->get() as $sj)
            <option value="{{ $sj->id }}">{{ $sj->no_sj }} · {{ $sj->tanggal->format('d/m/Y') }} · {{ $sj->armada?->no_polisi }}</option>
          @endforeach
        </select>
      </div>

      <div style="margin-bottom:16px">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px">Qty Gendongan Masuk</label>
        <input type="number" name="qty_gendongan_masuk" id="gnd_qty" min="1" class="finput"
               style="border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:20px;font-weight:700;text-align:center;width:100%;background:var(--surface);color:var(--text);outline:none;box-sizing:border-box">
      </div>

      <button type="submit" style="width:100%;background:#D97706;color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Konfirmasi Gendongan Masuk
      </button>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function bukaModalAmbilGudang(id, tersedia, tgl) {
  document.getElementById('ag_gudang_id').value = id;
  document.getElementById('ag_tersedia').textContent = tersedia.toLocaleString('id');
  document.getElementById('ag_qty').value = tersedia;
  document.getElementById('ag_qty').max = tersedia;
  document.getElementById('modal-ambil-gudang').style.display = 'flex';
}

function bukaModalGendongan(armadaId, polisi, total, stokId) {
  document.getElementById('gnd_stok_id').value = stokId;
  document.getElementById('gnd_armada_label').textContent = polisi;
  document.getElementById('gnd_tersedia').textContent = total.toLocaleString('id');
  document.getElementById('gnd_qty').value = total;
  document.getElementById('gnd_qty').max = total;
  document.getElementById('modal-gendongan').style.display = 'flex';
}
</script>
@endpush
