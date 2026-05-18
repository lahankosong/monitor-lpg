@extends('layouts.app')
@section('title', 'Jurnal Umum')
@section('content')

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Jurnal Umum</h1>
    <p style="font-size:12px;color:var(--muted)">Semua transaksi akuntansi — otomatis dari modul & manual</p>
  </div>
  <div style="display:flex;gap:8px">
    <button onclick="document.getElementById('modal-modal').style.display='flex'"
            style="border:1px solid var(--accent);color:var(--accent);background:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">
      💰 Modal / Prive
    </button>
    <button onclick="document.getElementById('modal-jurnal').style.display='flex'"
            style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer">
      + Jurnal Manual
    </button>
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
  <select name="modul" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua modul</option>
    @foreach(['modal_masuk'=>'Modal Masuk','prive'=>'Prive','utang_pemilik'=>'Utang Pemilik','tebusan'=>'Tebusan','distribusi'=>'Distribusi','brimola'=>'BRImola','kerjasama'=>'Kerjasama','kas_kecil'=>'Kas Kecil','penyesuaian'=>'Manual'] as $k=>$v)
      <option value="{{ $k }}" {{ $modul==$k?'selected':'' }}>{{ $v }}</option>
    @endforeach
  </select>
  <button type="submit" style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- List jurnal --}}
<div style="display:flex;flex-direction:column;gap:10px">
  @forelse($jurnals as $j)
  @php
    $det    = $details[$j->id] ?? collect();
    $modColor = match($j->modul) {
      'modal_masuk','utang_pemilik' => '#059669',
      'prive'                       => '#DC2626',
      'tebusan'                     => '#F59E0B',
      'distribusi'                  => '#3B82F6',
      'brimola'                     => '#8B5CF6',
      'kerjasama'                   => '#0891B2',
      'kas_kecil'                   => '#6B7280',
      default                       => 'var(--muted)',
    };
  @endphp
  <div class="card" style="padding:14px 18px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
      <div>
        <span style="font-family:monospace;font-size:12px;color:var(--accent);font-weight:600">{{ $j->no_jurnal }}</span>
        <span style="margin:0 8px;color:var(--border)">·</span>
        <span style="font-size:12px;color:var(--muted)">{{ \Carbon\Carbon::parse($j->tanggal)->format('d/m/Y') }}</span>
        <span style="margin-left:8px;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(255,255,255,.07);color:{{ $modColor }}">
          {{ strtoupper(str_replace('_',' ',$j->modul)) }}
        </span>
        @if(!$j->is_otomatis)
        <span style="margin-left:4px;padding:1px 6px;border-radius:99px;font-size:10px;background:#FEF3C7;color:#92400E">Manual</span>
        @endif
      </div>
      <span style="font-size:11px;color:var(--muted)">{{ $j->nama_user ?? '—' }}</span>
    </div>
    <p style="font-size:13px;color:var(--text);margin-bottom:10px;font-weight:500">{{ $j->keterangan }}</p>
    <div style="background:var(--bg);border-radius:8px;overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
          <tr>
            @foreach(['Akun','Keterangan','Debit','Kredit'] as $h)
            <th style="text-align:left;padding:6px 12px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border)">{{ $h }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($det as $d)
          <tr style="{{ !$loop->last?'border-bottom:1px solid var(--border)':'' }}">
            <td style="padding:6px 12px;font-family:monospace">
              <span style="font-size:10px;color:var(--muted)">{{ $d->kode_akun }}</span>
              <span style="margin-left:6px;color:var(--text)">{{ $d->nama_akun }}</span>
            </td>
            <td style="padding:6px 12px;color:var(--muted)">{{ $d->ket_detail ?? '' }}</td>
            <td style="padding:6px 12px;text-align:right;font-weight:600;color:{{ $d->posisi==='debit'?'var(--text)':'var(--border)' }}">
              {{ $d->posisi==='debit' ? 'Rp '.number_format($d->jumlah) : '' }}
            </td>
            <td style="padding:6px 12px;text-align:right;font-weight:600;color:{{ $d->posisi==='kredit'?'var(--text)':'var(--border)' }}">
              {{ $d->posisi==='kredit' ? 'Rp '.number_format($d->jumlah) : '' }}
            </td>
          </tr>
          @endforeach
          {{-- Total baris --}}
          @if($det->count() > 1)
          <tr style="border-top:2px solid var(--border);background:rgba(255,255,255,.03)">
            <td colspan="2" style="padding:6px 12px;font-size:11px;color:var(--muted)">TOTAL</td>
            <td style="padding:6px 12px;text-align:right;font-weight:700;font-family:monospace;font-size:11px">
              Rp {{ number_format($det->where('posisi','debit')->sum('jumlah')) }}
            </td>
            <td style="padding:6px 12px;text-align:right;font-weight:700;font-family:monospace;font-size:11px">
              Rp {{ number_format($det->where('posisi','kredit')->sum('jumlah')) }}
            </td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>
  @empty
  <div class="card" style="padding:48px;text-align:center;color:var(--muted)">Belum ada jurnal bulan ini</div>
  @endforelse
</div>
<div style="margin-top:16px">{{ $jurnals->links() }}</div>

{{-- Modal Modal/Prive --}}
<div id="modal-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:440px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Transaksi Modal / Prive</h3>
      <button onclick="this.closest('#modal-modal').style.display='none'" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.akuntansi.buku-besar.modal-store') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div style="grid-column:span 2">
          <label class="flabel">Tipe Transaksi *</label>
          <select name="tipe" id="sel-tipe" onchange="updateTipeLabel()" class="finput">
            <option value="modal_disetor">💰 Modal Disetor (permanen)</option>
            <option value="pinjaman_pemilik">🤝 Pinjaman dari Pemilik</option>
            <option value="prive">💸 Prive / Penarikan Pemilik</option>
            <option value="bayar_utang_pemilik">↩ Bayar Kembali Pinjaman ke Pemilik</option>
          </select>
        </div>
        <div>
          <label class="flabel">Tanggal *</label>
          <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput">
        </div>
        <div>
          <label class="flabel">Via</label>
          <select name="akun_kas" class="finput">
            <option value="1001">Kas Kecil</option>
            <option value="1002">Rekening Giro BRI</option>
          </select>
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Jumlah (Rp) *</label>
          <input type="number" name="jumlah" required min="1" class="finput" placeholder="0">
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Keterangan</label>
          <input type="text" name="keterangan" class="finput" id="ket-modal" placeholder="Tambahkan catatan...">
        </div>
      </div>
      <div id="info-tipe" style="background:var(--bg);border-radius:8px;padding:10px;margin-bottom:12px;font-size:12px;color:var(--muted)">
        Debit: Kas · Kredit: Modal Disetor
      </div>
      <button type="submit" style="width:100%;background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Simpan
      </button>
    </form>
  </div>
</div>

{{-- Modal Jurnal Manual --}}
<div id="modal-jurnal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:flex-start;justify-content:center;z-index:300;padding:16px;overflow-y:auto" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:580px;margin:0 auto" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Jurnal Manual</h3>
      <button onclick="this.closest('#modal-jurnal').style.display='none'" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.akuntansi.buku-besar.jurnal-store') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
        <div>
          <label class="flabel">Tanggal *</label>
          <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput">
        </div>
        <div>
          <label class="flabel">Modul</label>
          <select name="modul" class="finput">
            <option value="penyesuaian">Koreksi / Penyesuaian</option>
            <option value="modal_masuk">Modal Masuk</option>
            <option value="prive">Prive</option>
            <option value="kas_kecil">Kas Kecil</option>
          </select>
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Keterangan *</label>
          <input type="text" name="keterangan" required class="finput" placeholder="Penjelasan transaksi">
        </div>
        <div>
          <label class="flabel">Referensi</label>
          <input type="text" name="referensi" class="finput" placeholder="No. SJ, SA, dll">
        </div>
      </div>

      {{-- Baris akun --}}
      <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:12px">
        <div style="padding:8px 12px;background:var(--bg);border-bottom:1px solid var(--border);display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px">
          @foreach(['Akun','Posisi','Jumlah','Keterangan',''] as $h)
            <span style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</span>
          @endforeach
        </div>
        <div id="baris-akun">
          <div class="baris-jurnal" style="padding:8px 12px;display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;border-bottom:1px solid var(--border)">
            <select name="akun[]" class="finput" style="padding:5px 8px;font-size:12px">
              @foreach($akuns as $a)
                <option value="{{ $a->kode }}">{{ $a->kode }} — {{ $a->nama }}</option>
              @endforeach
            </select>
            <select name="posisi[]" class="finput" style="padding:5px 8px;font-size:12px">
              <option value="debit">Debit</option>
              <option value="kredit">Kredit</option>
            </select>
            <input type="number" name="jumlah[]" required min="1" class="finput" style="padding:5px 8px;font-size:12px" placeholder="0">
            <input type="text" name="ket_detail[]" class="finput" style="padding:5px 8px;font-size:12px" placeholder="Opsional">
            <button type="button" onclick="hapusBaris(this)" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:16px">×</button>
          </div>
          <div class="baris-jurnal" style="padding:8px 12px;display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px">
            <select name="akun[]" class="finput" style="padding:5px 8px;font-size:12px">
              @foreach($akuns as $a)
                <option value="{{ $a->kode }}">{{ $a->kode }} — {{ $a->nama }}</option>
              @endforeach
            </select>
            <select name="posisi[]" class="finput" style="padding:5px 8px;font-size:12px">
              <option value="debit">Debit</option>
              <option value="kredit" selected>Kredit</option>
            </select>
            <input type="number" name="jumlah[]" required min="1" class="finput" style="padding:5px 8px;font-size:12px" placeholder="0">
            <input type="text" name="ket_detail[]" class="finput" style="padding:5px 8px;font-size:12px" placeholder="Opsional">
            <button type="button" onclick="hapusBaris(this)" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:16px">×</button>
          </div>
        </div>
      </div>
      <button type="button" onclick="tambahBaris()"
              style="width:100%;border:1px dashed var(--border);background:none;color:var(--muted);border-radius:8px;padding:8px;font-size:12px;cursor:pointer;margin-bottom:12px">
        + Tambah Baris
      </button>
      <div id="info-balance" style="font-size:12px;text-align:right;margin-bottom:10px;color:var(--muted)">Debit: Rp 0 · Kredit: Rp 0</div>
      <button type="submit" style="width:100%;background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Simpan Jurnal
      </button>
    </form>
  </div>
</div>

@endsection
@push('scripts')
<script>
const akunOptions = `@foreach($akuns as $a)<option value="{{ $a->kode }}">{{ $a->kode }} — {{ $a->nama }}</option>@endforeach`;

function tambahBaris() {
  const container = document.getElementById('baris-akun');
  const div = document.createElement('div');
  div.className = 'baris-jurnal';
  div.style.cssText = 'padding:8px 12px;display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;border-top:1px solid var(--border)';
  div.innerHTML = `
    <select name="akun[]" class="finput" style="padding:5px 8px;font-size:12px">${akunOptions}</select>
    <select name="posisi[]" class="finput" style="padding:5px 8px;font-size:12px">
      <option value="debit">Debit</option><option value="kredit" selected>Kredit</option>
    </select>
    <input type="number" name="jumlah[]" required min="1" class="finput" style="padding:5px 8px;font-size:12px" placeholder="0">
    <input type="text" name="ket_detail[]" class="finput" style="padding:5px 8px;font-size:12px" placeholder="Opsional">
    <button type="button" onclick="hapusBaris(this)" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:16px">×</button>`;
  container.appendChild(div);
  updateBalance();
}

function hapusBaris(btn) {
  const baris = document.querySelectorAll('.baris-jurnal');
  if (baris.length <= 2) return;
  btn.closest('.baris-jurnal').remove();
  updateBalance();
}

function updateBalance() {
  let debit = 0, kredit = 0;
  document.querySelectorAll('.baris-jurnal').forEach(b => {
    const pos = b.querySelector('[name="posisi[]"]').value;
    const jml = parseInt(b.querySelector('[name="jumlah[]"]').value) || 0;
    if (pos === 'debit') debit += jml; else kredit += jml;
  });
  const el = document.getElementById('info-balance');
  const ok = debit === kredit && debit > 0;
  el.textContent = `Debit: Rp ${debit.toLocaleString('id')} · Kredit: Rp ${kredit.toLocaleString('id')}`;
  el.style.color = ok ? '#059669' : (debit > 0 || kredit > 0 ? '#DC2626' : 'var(--muted)');
}

document.addEventListener('input', e => {
  if (e.target.name === 'jumlah[]' || e.target.name === 'posisi[]') updateBalance();
});

const tipeMeta = {
  modal_disetor:      'Debit: Kas/Giro → Kredit: Modal Disetor Pemilik',
  pinjaman_pemilik:   'Debit: Kas/Giro → Kredit: Utang ke Pemilik',
  prive:              'Debit: Prive/Penarikan → Kredit: Kas/Giro',
  bayar_utang_pemilik:'Debit: Utang ke Pemilik → Kredit: Kas/Giro',
};
function updateTipeLabel() {
  const t = document.getElementById('sel-tipe').value;
  document.getElementById('info-tipe').textContent = tipeMeta[t] || '';
}
</script>
@endpush
