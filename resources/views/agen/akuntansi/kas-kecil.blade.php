@extends('layouts.app')
@section('title', 'Kas Kecil')
@section('content')

@php
$kategoriLabel = [
  'bbm_armada'     => '⛽ BBM Armada',
  'gaji_karyawan'  => '👤 Gaji Karyawan',
  'servis_armada'  => '🔧 Servis Armada',
  'stnk_pajak'     => '📄 STNK & Pajak',
  'kantor'         => '🏢 Operasional Kantor',
  'tabung'         => '🪣 Tabung',
  'lain_lain'      => '📦 Lain-lain',
];
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Kas Kecil</h1>
    <p style="font-size:12px;color:var(--muted)">Pengeluaran operasional harian: BBM, gaji, servis, dll</p>
  </div>
  <button onclick="document.getElementById('modal-tambah').style.display='flex'"
          style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">
    + Tambah Transaksi
  </button>
</div>

{{-- Summary 3 cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
  <div class="stat-card" style="border-left:3px solid #059669">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Saldo Kas</p>
    <p style="font-size:20px;font-weight:700;color:{{ $saldo>=0?'#059669':'#DC2626' }};margin-top:4px">
      Rp {{ number_format(abs($saldo)) }}
    </p>
    <p style="font-size:11px;color:var(--muted)">{{ $saldo>=0?'tersedia':'defisit' }}</p>
  </div>
  <div class="stat-card" style="border-left:3px solid #3B82F6">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Masuk Bulan Ini</p>
    <p style="font-size:20px;font-weight:700;color:#3B82F6;margin-top:4px">Rp {{ number_format($summary->masuk??0) }}</p>
  </div>
  <div class="stat-card" style="border-left:3px solid #DC2626">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase">Keluar Bulan Ini</p>
    <p style="font-size:20px;font-weight:700;color:#DC2626;margin-top:4px">Rp {{ number_format($summary->keluar??0) }}</p>
  </div>
</div>

{{-- Rekap kategori --}}
@if($rekapKategori->isNotEmpty())
<div class="card" style="padding:14px 18px;margin-bottom:16px">
  <p style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:10px">Pengeluaran per Kategori</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    @foreach($rekapKategori as $r)
    <div style="background:var(--bg);border-radius:8px;padding:8px 12px;font-size:12px;text-align:center;min-width:120px">
      <p style="color:var(--muted)">{{ $kategoriLabel[$r->kategori] ?? $r->kategori }}</p>
      <p style="font-weight:700;color:var(--text);margin-top:2px">Rp {{ number_format($r->total) }}</p>
      <p style="font-size:10px;color:var(--muted)">{{ $r->jml }}x</p>
    </div>
    @endforeach
  </div>
</div>
@endif

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
  <select name="kategori" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua kategori</option>
    @foreach($kategoriLabel as $k => $l)
      <option value="{{ $k }}" {{ $kategori==$k?'selected':'' }}>{{ $l }}</option>
    @endforeach
  </select>
  <button type="submit" style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- Tabel transaksi --}}
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Tanggal','Kategori','Keterangan','Armada','Jumlah','Jenis','Dicatat',''] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($transaksi as $t)
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:9px 14px;color:var(--muted);white-space:nowrap">{{ \Carbon\Carbon::parse($t->tanggal)->format('d/m/Y') }}</td>
        <td style="padding:9px 14px">
          <span style="font-size:12px;font-weight:500;color:var(--text)">{{ $kategoriLabel[$t->kategori] ?? $t->kategori }}</span>
        </td>
        <td style="padding:9px 14px;color:var(--text)">{{ $t->keterangan }}</td>
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)">{{ $t->no_polisi ?? '—' }}</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700;color:{{ $t->jenis==='masuk'?'#059669':'#DC2626' }}">
          {{ $t->jenis==='masuk'?'+':'-' }} Rp {{ number_format($t->jumlah) }}
        </td>
        <td style="padding:9px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;
                       {{ $t->jenis==='masuk'?'background:#D1FAE5;color:#065F46':'background:#FEE2E2;color:#991B1B' }}">
            {{ $t->jenis==='masuk'?'Masuk':'Keluar' }}
          </span>
        </td>
        <td style="padding:9px 14px;font-size:11px;color:var(--muted)">{{ $t->nama_user ?? '—' }}</td>
        <td style="padding:9px 14px">
          <form action="{{ route('dashboard.agen.akuntansi.kas.destroy', $t->id) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus transaksi ini?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:none;color:#DC2626;font-size:12px;cursor:pointer">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" style="padding:48px;text-align:center;color:var(--muted)">Belum ada transaksi bulan ini</td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $transaksi->links() }}</div>
</div>

{{-- Modal Tambah --}}
<div id="modal-tambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:480px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Tambah Transaksi Kas</h3>
      <button onclick="document.getElementById('modal-tambah').style.display='none'" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.akuntansi.kas.store') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div>
          <label class="flabel">Tanggal *</label>
          <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput">
        </div>
        <div>
          <label class="flabel">Jenis *</label>
          <select name="jenis" id="sel-jenis" onchange="updateKategori()" class="finput">
            <option value="keluar">Keluar (pengeluaran)</option>
            <option value="masuk">Masuk (isi kas)</option>
          </select>
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Kategori *</label>
          <select name="kategori" id="sel-kategori" class="finput">
            @foreach($kategoriLabel as $k => $l)
              <option value="{{ $k }}">{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Keterangan *</label>
          <input type="text" name="keterangan" required class="finput" placeholder="Contoh: BBM armada R 8040 MR, 30 liter">
        </div>
        <div>
          <label class="flabel">Jumlah (Rp) *</label>
          <input type="number" name="jumlah" required min="1" class="finput" placeholder="150000">
        </div>
        <div id="field-armada">
          <label class="flabel">Armada (jika terkait)</label>
          <select name="armada_id" class="finput">
            <option value="">— Tidak ada —</option>
            @foreach($armadas as $a)
              <option value="{{ $a->id }}">{{ $a->no_polisi }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <button type="submit" style="width:100%;background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Simpan Transaksi
      </button>
    </form>
  </div>
</div>

@endsection
@push('scripts')
<script>
function updateKategori() {
  const jenis = document.getElementById('sel-jenis').value;
  const katEl = document.getElementById('sel-kategori');
  const armEl = document.getElementById('field-armada');

  if (jenis === 'masuk') {
    // Isi kas — hanya 1 opsi
    katEl.innerHTML = '<option value="lain_lain">💰 Penerimaan / Isi Kas</option>';
    armEl.style.display = 'none';
  } else {
    katEl.innerHTML = `
      <option value="bbm_armada">⛽ BBM Armada</option>
      <option value="gaji_karyawan">👤 Gaji Karyawan</option>
      <option value="servis_armada">🔧 Servis Armada</option>
      <option value="stnk_pajak">📄 STNK & Pajak</option>
      <option value="kantor">🏢 Operasional Kantor</option>
      <option value="tabung">🪣 Tabung</option>
      <option value="lain_lain">📦 Lain-lain</option>`;
    armEl.style.display = 'block';
  }
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.getElementById('modal-tambah').style.display = 'none';
});
</script>
@endpush
