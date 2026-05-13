@extends('layouts.driver')
@section('topbar-title', 'Surat Jalan Hari Ini')
@section('topbar-sub', \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y'))

@section('content')

{{-- Pilih tanggal --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:12px">
  <input type="date" name="tanggal" value="{{ $tanggal }}"
         style="flex:1;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
                border-radius:10px;padding:10px 12px;font-size:14px;color:var(--text);
                font-family:'Poppins',sans-serif;outline:none">
  <button type="submit" class="btn btn-accent" style="padding:10px 16px">Cari</button>
</form>

@if(session('success'))
<div style="background:rgba(41,253,83,.1);border:1px solid var(--accent);border-radius:10px;
            padding:10px 14px;margin-bottom:12px;font-size:13px;color:var(--accent);font-weight:500">
  ✓ {{ session('success') }}
</div>
@endif

@forelse($sjList as $sj)
@php
  $totalJadwal  = $sj->details->sum('qty_jadwal');
  $totalTerima  = $sj->details->sum('qty_terima');
  $totalMaks    = $sj->details->sum(fn($d) => $d->qty_maks);
  $belum        = $sj->details->where('status','terjadwal')->count();
  $pct          = $totalMaks > 0 ? min(100, round($totalTerima/$totalMaks*100)) : 0;
@endphp

<div class="card">
  {{-- Header SJ --}}
  <div style="padding:14px;background:linear-gradient(135deg,#1E2A36,#253341)">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
      <div>
        <div style="font-family:monospace;font-size:15px;font-weight:700;color:var(--accent)">
          {{ $sj->no_sj }}
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px">
          {{ $sj->kitirDetail?->kitir?->spbe?->nama_spbe }}
          · SA# {{ $sj->kitirDetail?->kitir?->nomor_sa }}
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:24px;font-weight:700;color:var(--text);line-height:1">
          {{ number_format($sj->qty_refil) }}
        </div>
        <div style="font-size:10px;color:var(--muted)">tabung DO</div>
      </div>
    </div>

    {{-- Crew & stok info --}}
    <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:11px;color:var(--muted);margin-bottom:10px">
      <span>🚛 {{ $sj->armada?->no_polisi }}</span>
      <span>👤 {{ $sj->sopir?->nama_karyawan }}</span>
      @if($sj->kernet) <span>👥 {{ $sj->kernet->nama_karyawan }}</span> @endif
      @if(($sj->qty_gendongan_masuk??0)>0)
        <span style="color:var(--warning)">⚡ +{{ $sj->qty_gendongan_masuk }} gendongan</span>
      @endif
      @if(($sj->qty_ambil_gudang??0)>0)
        <span style="color:var(--info)">🏪 +{{ $sj->qty_ambil_gudang }} gudang</span>
      @endif
    </div>

    {{-- Progress --}}
    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:5px">
      <span>Progress distribusi</span>
      <span style="color:{{ $pct==100?'var(--accent)':'var(--warning)' }};font-weight:600">
        {{ $totalTerima }}/{{ $totalMaks }} ({{ $pct }}%)
      </span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width:{{ $pct }}%;background:{{ $pct==100?'var(--accent)':'var(--warning)' }}"></div>
    </div>
  </div>

  {{-- List pangkalan --}}
  @foreach($sj->details->sortBy('urutan') as $d)
  @php
    $isDone    = in_array($d->status, ['terkirim','dialihkan','batal','sebagian']);
    $isFull    = $d->status === 'terkirim' && $d->qty_terima >= $d->qty_maks;
    $hasTambah = $d->tambahan->isNotEmpty();
    $qtyMaks   = $d->qty_maks;
    $sisaArmada = max(0, $qtyMaks - $d->qty_terima - $d->sisaDistribusi->where('tipe','alih_pangkalan')->sum('qty'));
  @endphp
  <div class="pkln-row">
    {{-- Urut / status icon --}}
    <div class="pkln-urut {{ $isFull?'done':($isDone?'partial':'') }}">
      @if($isFull) ✓
      @elseif($d->status==='batal') ✗
      @elseif($d->status==='dialihkan') →
      @else {{ $d->urutan }}
      @endif
    </div>

    {{-- Info pangkalan --}}
    <div class="pkln-info">
      <div class="pkln-nama">{{ $d->pangkalan?->nama_pangkalan }}</div>
      <div class="pkln-sub">
        {{ $d->pangkalan?->no_reg }}
        @if($isDone)
          · <span style="color:{{ $isFull?'var(--accent)':'var(--warning)' }}">
            {{ $d->qty_terima }} diterima
          </span>
        @endif
        @if($d->keterangan)
          · <span style="font-style:italic">{{ Str::limit($d->keterangan,28) }}</span>
        @endif
      </div>
      @if($hasTambah)
      <div class="pkln-tambahan">
        @foreach($d->tambahan as $tb)
          +{{ $tb->qty }} dari {{ $tb->sumberDetail?->pangkalan?->nama_pangkalan ?? '—' }}
        @endforeach
        → max {{ $qtyMaks }} tb
      </div>
      @endif
      @if($isDone && $d->sisaDistribusi->isNotEmpty())
      <div style="font-size:10px;color:var(--muted);margin-top:2px">
        @foreach($d->sisaDistribusi as $s)
          <span style="color:{{ match($s->tipe){'alih_pangkalan'=>'var(--info)','stok_armada'=>'var(--warning)','gudang_sendiri'=>'#a78bfa',default=>'var(--muted)'} }}">
            {{ $s->qty }}tb {{ \App\Models\SjSisaDistribusi::TIPE_LABEL[$s->tipe]??$s->tipe }}
          </span>
        @endforeach
      </div>
      @endif
    </div>

    {{-- Qty & tombol --}}
    <div class="pkln-right">
      @if(!$isDone)
        <div class="pkln-qty">{{ number_format($qtyMaks) }}</div>
        <div class="pkln-qty-label">{{ $qtyMaks > $d->qty_jadwal ? 'max' : 'tabung' }}</div>
        <button onclick="bukaInput({{ Js::from($d) }}, {{ $qtyMaks }}, '{{ addslashes($d->pangkalan?->nama_pangkalan) }}')"
                class="btn btn-accent" style="padding:5px 12px;font-size:12px;margin-top:5px;border-radius:8px">
          Input
        </button>
      @elseif($isFull)
        <div class="pkln-qty done">{{ number_format($d->qty_terima) }}</div>
        <div class="pkln-qty-label" style="color:var(--accent)">lunas</div>
        <button onclick="bukaInput({{ Js::from($d) }}, {{ $qtyMaks }}, '{{ addslashes($d->pangkalan?->nama_pangkalan) }}')"
                class="btn btn-outline" style="padding:4px 10px;font-size:11px;margin-top:5px;border-radius:8px">
          Edit
        </button>
      @else
        <div class="pkln-qty partial">{{ number_format($d->qty_terima) }}</div>
        <div class="pkln-qty-label" style="color:var(--warning)">
          @if($sisaArmada > 0) sisa {{ $sisaArmada }} @else selesai @endif
        </div>
        <button onclick="bukaInput({{ Js::from($d) }}, {{ $qtyMaks }}, '{{ addslashes($d->pangkalan?->nama_pangkalan) }}')"
                class="btn btn-outline" style="padding:4px 10px;font-size:11px;margin-top:5px;border-radius:8px">
          Edit
        </button>
      @endif
    </div>
  </div>
  @endforeach

  {{-- Footer SJ --}}
  <div style="padding:10px 14px;border-top:1px solid var(--border);
              background:{{ $belum>0?'rgba(250,173,20,.05)':'rgba(41,253,83,.05)' }};
              font-size:12px;color:{{ $belum>0?'var(--warning)':'var(--accent)' }};
              display:flex;align-items:center;gap:6px">
    @if($belum > 0) ⏳ {{ $belum }} pangkalan belum dilaporkan
    @else ✓ Semua pangkalan sudah dilaporkan
    @endif
  </div>
</div>
@empty
<div style="text-align:center;padding:60px 20px;color:var(--muted)">
  <div style="font-size:48px;margin-bottom:12px">📦</div>
  <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px">
    Tidak ada tugas hari ini
  </div>
  <div style="font-size:13px">Belum ada Surat Jalan aktif</div>
</div>
@endforelse

{{-- ══ BOTTOM SHEET INPUT REALISASI ════════════════════════════ --}}
<div class="sheet-overlay" id="sheet-input" onclick="if(event.target===this)tutupSheet('sheet-input')">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
      <div>
        <div class="sheet-title" id="sh-nama">Input Realisasi</div>
        <div class="sheet-sub" id="sh-sub">Pangkalan</div>
      </div>
      <button class="sheet-close" onclick="tutupSheet('sheet-input')">×</button>
    </div>
    <div class="sheet-body">
      <form id="form-input" method="POST">
        @csrf @method('PUT')

        {{-- Info 3 cards --}}
        <div class="info-grid">
          <div class="info-card">
            <div class="info-card-label">Jadwal</div>
            <div class="info-card-val" id="sh-jadwal" style="color:var(--text)">0</div>
            <div id="sh-label-maks" style="display:none;font-size:9px;color:var(--info);margin-top:2px;font-weight:600"></div>
          </div>
          <div class="info-card">
            <div class="info-card-label">Terima</div>
            <div class="info-card-val" id="sh-terima" style="color:var(--accent)">0</div>
          </div>
          <div class="info-card">
            <div class="info-card-label">Sisa</div>
            <div class="info-card-val" id="sh-sisa" style="color:var(--warning)">0</div>
          </div>
        </div>

        {{-- Warning melebihi --}}
        <div class="warn-box" id="sh-warn"></div>
        {{-- Info tambahan --}}
        <div class="info-box" id="sh-info-tambahan"></div>

        {{-- Input qty --}}
        <div style="margin-bottom:12px">
          <label class="flabel">Qty Diterima</label>
          <div class="qty-wrap">
            <button type="button" class="qty-btn" onclick="ubahQty(-10)">−</button>
            <input type="number" id="sh-qty" name="qty_terima" min="0" class="qty-input-big"
                   oninput="hitungSisa()">
            <button type="button" class="qty-btn" onclick="ubahQty(10)">+</button>
          </div>
        </div>

        {{-- Tombol cepat --}}
        <div class="pill-row">
          <button type="button" class="pill" id="pill-full" onclick="setFull()">✓ Full</button>
          <button type="button" class="pill" id="pill-0" onclick="setNol()">✗ Nol</button>
        </div>

        {{-- Keterangan --}}
        <div style="margin-bottom:14px">
          <label class="flabel">Keterangan</label>
          <textarea name="keterangan" id="sh-keterangan" rows="2" placeholder="Catatan (opsional)..."
                    class="finput" style="resize:none"></textarea>
        </div>

        {{-- SISA SECTION --}}
        <div id="sh-sisa-section" style="display:none">
          <div class="sisa-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
              <span style="font-size:12px;font-weight:700;color:var(--warning)">
                ⚠ Sisa <span id="sh-label-sisa">0</span> tb — ke mana?
              </span>
              <span id="sh-alokasi-label" style="font-size:11px;font-weight:700;color:var(--muted)">0/0</span>
            </div>

            {{-- Pilihan 1: Pangkalan lain --}}
            <div class="sisa-option" id="opt-alih" onclick="pilihanKlik('alih')">
              <input type="checkbox" id="chk-alih">
              <div>
                <div class="sisa-option-label">↗ Pangkalan lain</div>
                <div class="sisa-option-desc">Dialihkan ke 1 atau lebih pangkalan</div>
              </div>
            </div>
            <div id="sub-alih" style="display:none;padding:8px 0 4px">
              <div id="container-alih"></div>
              <button type="button" onclick="tambahBarisPangkalan()"
                      style="background:rgba(24,144,255,.1);border:1px solid rgba(24,144,255,.3);
                             color:var(--info);border-radius:8px;padding:6px 14px;
                             font-size:12px;font-family:'Poppins',sans-serif;cursor:pointer;width:100%">
                + Tambah Pangkalan
              </button>
              <div style="font-size:11px;color:var(--info);margin-top:4px">
                Total dialihkan: <strong id="sh-total-alih">0</strong> tabung
              </div>
            </div>

            {{-- Pilihan 2: Tetap di armada --}}
            <div class="sisa-option" id="opt-armada" onclick="pilihanKlik('armada')">
              <input type="checkbox" id="chk-armada">
              <div style="flex:1;display:flex;justify-content:space-between;align-items:center">
                <div>
                  <div class="sisa-option-label">⚡ Tetap di armada</div>
                  <div class="sisa-option-desc">Gendongan — wajib habis sebelum ambil DO lagi</div>
                </div>
                <input type="number" name="sisa[armada][qty]" id="qty-armada" min="0" value="0"
                       onclick="event.stopPropagation()" oninput="hitungAlokasi()"
                       style="display:none;width:60px;background:rgba(255,255,255,.06);
                              border:1.5px solid var(--warning);border-radius:8px;
                              padding:6px;font-size:14px;font-weight:700;text-align:center;
                              color:var(--warning);font-family:'Poppins',sans-serif;outline:none">
              </div>
            </div>
            <input type="hidden" name="sisa[armada][tipe]" value="stok_armada">

            {{-- Pilihan 3: Gudang --}}
            <div class="sisa-option" id="opt-gudang" onclick="pilihanKlik('gudang')">
              <input type="checkbox" id="chk-gudang">
              <div style="flex:1;display:flex;justify-content:space-between;align-items:center">
                <div>
                  <div class="sisa-option-label">🏪 Gudang agen</div>
                  <div class="sisa-option-desc">Disimpan — bisa diambil kapan saja</div>
                </div>
                <input type="number" name="sisa[gudang][qty]" id="qty-gudang" min="0" value="0"
                       onclick="event.stopPropagation()" oninput="hitungAlokasi()"
                       style="display:none;width:60px;background:rgba(255,255,255,.06);
                              border:1.5px solid #a78bfa;border-radius:8px;
                              padding:6px;font-size:14px;font-weight:700;text-align:center;
                              color:#a78bfa;font-family:'Poppins',sans-serif;outline:none">
              </div>
            </div>
            <input type="hidden" name="sisa[gudang][tipe]" value="gudang_sendiri">

            <div id="sh-err-alokasi" style="display:none;font-size:11px;color:var(--danger);margin-top:6px;font-weight:600"></div>
          </div>
        </div>

        <button type="submit" id="sh-btn-simpan" class="btn btn-accent btn-full btn-lg">
          Simpan Laporan
        </button>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
let qtyJadwal = 0;
let qtyMaksGlobal = 0;
let barisPangkalanCount = 0;

const pangkalanOpts = `@foreach($pangkalans as $p)<option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>@endforeach`;

function bukaInput(detail, qtyMaks, nama) {
  qtyJadwal     = detail.qty_jadwal;
  qtyMaksGlobal = qtyMaks > 0 ? qtyMaks : detail.qty_jadwal;

  document.getElementById('form-input').action = `/dashboard/agen/driver/detail/${detail.id}`;
  document.getElementById('sh-nama').textContent = nama;
  document.getElementById('sh-sub').textContent  = `P-${detail.pangkalan_id} · Jadwal ${detail.qty_jadwal} tb`;

  const nilaiAwal = detail.qty_terima > 0 ? detail.qty_terima : qtyMaksGlobal;
  document.getElementById('sh-qty').value = nilaiAwal;

  // Info tambahan
  const infoEl = document.getElementById('sh-info-tambahan');
  const labelMaks = document.getElementById('sh-label-maks');
  if (qtyMaksGlobal > qtyJadwal) {
    const t = qtyMaksGlobal - qtyJadwal;
    infoEl.textContent = `Ada tambahan ${t} tb → boleh terima hingga ${qtyMaksGlobal} tabung`;
    infoEl.style.display = 'block';
    if (labelMaks) { labelMaks.textContent = `+${t} tambahan = max ${qtyMaksGlobal}`; labelMaks.style.display = 'block'; }
  } else {
    infoEl.style.display = 'none';
    if (labelMaks) labelMaks.style.display = 'none';
  }

  document.getElementById('sh-keterangan').value = detail.keterangan || '';
  resetSisa();
  hitungSisa();
  bukaSheet('sheet-input');

  // Focus setelah animasi
  setTimeout(() => document.getElementById('sh-qty').select(), 300);
}

function setFull() {
  document.getElementById('sh-qty').value = qtyMaksGlobal;
  hitungSisa();
}
function setNol() {
  document.getElementById('sh-qty').value = 0;
  hitungSisa();
}
function ubahQty(d) {
  const el = document.getElementById('sh-qty');
  el.value = Math.max(0, Math.min(qtyMaksGlobal, parseInt(el.value||0) + d));
  hitungSisa();
}

function hitungSisa() {
  const terima = parseInt(document.getElementById('sh-qty').value || 0);
  const btn    = document.getElementById('sh-btn-simpan');
  const warn   = document.getElementById('sh-warn');
  const qtyEl  = document.getElementById('sh-qty');

  // Validasi melebihi maks
  if (terima > qtyMaksGlobal) {
    const lebih = terima - qtyJadwal;
    warn.textContent = `⚠ Melebihi jadwal ${qtyJadwal} sebesar ${lebih} tb! `
      + (qtyMaksGlobal > qtyJadwal ? `(max ${qtyMaksGlobal} dengan tambahan)` : `Hubungi admin untuk tambahkan sumber.`);
    warn.style.display = 'block';
    qtyEl.classList.add('warn');
    btn.disabled = true; btn.style.opacity = '.4';

    document.getElementById('sh-terima').textContent = terima;
    document.getElementById('sh-sisa').textContent   = '—';
    document.getElementById('sh-sisa-section').style.display = 'none';
    return;
  }

  warn.style.display = 'none';
  qtyEl.classList.remove('warn');
  btn.disabled = false; btn.style.opacity = '1';

  const sisa = Math.max(0, qtyMaksGlobal - terima);
  document.getElementById('sh-jadwal').textContent  = qtyJadwal.toLocaleString('id');
  document.getElementById('sh-terima').textContent  = terima.toLocaleString('id');
  document.getElementById('sh-sisa').textContent    = sisa.toLocaleString('id');
  document.getElementById('sh-sisa').style.color    = sisa > 0 ? 'var(--warning)' : 'var(--accent)';
  document.getElementById('sh-label-sisa').textContent = sisa;

  // Pill active
  document.getElementById('pill-full').classList.toggle('active', terima >= qtyMaksGlobal);
  document.getElementById('pill-0').classList.toggle('active', terima === 0);

  if (sisa > 0) {
    document.getElementById('sh-sisa-section').style.display = 'block';
    autoFillSisaTunggal();
  } else {
    document.getElementById('sh-sisa-section').style.display = 'none';
    resetSisa();
    btn.disabled = false; btn.style.opacity = '1';
  }
  hitungAlokasi();
}

function pilihanKlik(id) {
  const chk = document.getElementById('chk-' + id);
  setTimeout(() => {
    const aktif = chk.checked;
    const opt = document.getElementById('opt-' + id);
    opt.classList.toggle('checked', aktif);

    if (id === 'alih') {
      document.getElementById('sub-alih').style.display = aktif ? 'block' : 'none';
      if (aktif && document.querySelectorAll('.alih-row').length === 0) tambahBarisPangkalan();
    }
    if (id === 'armada' || id === 'gudang') {
      const qEl = document.getElementById('qty-' + id);
      qEl.style.display = aktif ? 'inline-block' : 'none';
      if (!aktif) qEl.value = 0;
    }
    autoFillSisaTunggal();
    hitungAlokasi();
  }, 0);
}

function autoFillSisaTunggal() {
  const terima = parseInt(document.getElementById('sh-qty').value || 0);
  const sisa   = Math.max(0, qtyMaksGlobal - terima);
  const aktif  = ['alih','armada','gudang'].filter(id => document.getElementById('chk-'+id)?.checked);

  if (aktif.length === 1 && aktif[0] !== 'alih') {
    const qEl = document.getElementById('qty-' + aktif[0]);
    const totalAlih = hitungTotalAlih();
    if (qEl) qEl.value = Math.max(0, sisa - totalAlih);
  }
}

function tambahBarisPangkalan() {
  const terima  = parseInt(document.getElementById('sh-qty').value || 0);
  const sisa    = Math.max(0, qtyMaksGlobal - terima);
  const sudah   = hitungTotalAlih()
    + parseInt(document.getElementById('qty-armada')?.value||0)
    + parseInt(document.getElementById('qty-gudang')?.value||0);
  const sisanya = Math.max(0, sisa - sudah);
  const idx = barisPangkalanCount++;

  const row = document.createElement('div');
  row.className = 'alih-row';
  row.innerHTML = `
    <select name="sisa_alih[${idx}][pangkalan_id]">
      <option value="">-- Pangkalan --</option>${pangkalanOpts}
    </select>
    <input type="number" name="sisa_alih[${idx}][qty]" value="${sisanya}" min="1"
           oninput="hitungAlokasi()">
    <button type="button" class="btn-del-alih" onclick="hapusBarisPangkalan(this)">×</button>
  `;
  document.getElementById('container-alih').appendChild(row);
  hitungAlokasi();
}

function hapusBarisPangkalan(btn) {
  btn.closest('.alih-row').remove();
  hitungAlokasi();
}

function hitungTotalAlih() {
  let total = 0;
  document.querySelectorAll('input[name^="sisa_alih"][name$="[qty]"]')
    .forEach(i => total += parseInt(i.value||0));
  document.getElementById('sh-total-alih').textContent = total;
  return total;
}

function hitungAlokasi() {
  const terima = parseInt(document.getElementById('sh-qty').value || 0);
  const sisa   = Math.max(0, qtyMaksGlobal - terima);
  const total  = hitungTotalAlih()
    + parseInt(document.getElementById('qty-armada')?.value||0)
    + parseInt(document.getElementById('qty-gudang')?.value||0);

  const label  = document.getElementById('sh-alokasi-label');
  const err    = document.getElementById('sh-err-alokasi');
  const btn    = document.getElementById('sh-btn-simpan');

  if (sisa === 0) {
    label.textContent = ''; err.style.display = 'none';
    btn.disabled = false; btn.style.opacity = '1';
    return;
  }

  label.textContent = `${total}/${sisa}`;
  label.style.color = total === sisa ? 'var(--accent)' : (total > sisa ? 'var(--danger)' : 'var(--warning)');

  const ok = total === sisa;
  err.style.display = ok ? 'none' : 'block';
  err.textContent   = ok ? '' : (total > sisa ? `Kelebihan ${total-sisa} tb` : `Kurang ${sisa-total} tb`);
  btn.disabled      = !ok;
  btn.style.opacity = ok ? '1' : '.4';
}

function resetSisa() {
  ['alih','armada','gudang'].forEach(id => {
    const chk = document.getElementById('chk-'+id);
    const opt = document.getElementById('opt-'+id);
    if (chk) chk.checked = false;
    if (opt) opt.classList.remove('checked');
  });
  document.getElementById('sub-alih').style.display = 'none';
  document.getElementById('container-alih').innerHTML = '';
  barisPangkalanCount = 0;
  ['armada','gudang'].forEach(id => {
    const q = document.getElementById('qty-'+id);
    if (q) { q.value = 0; q.style.display = 'none'; }
  });
  const err = document.getElementById('sh-err-alokasi');
  if (err) err.style.display = 'none';
}

// Fix indicator position magic nav
function fixNavIndicator() {
  const nav    = document.querySelector('.navigation ul');
  if (!nav) return;
  const items  = nav.querySelectorAll('.list');
  const ind    = nav.querySelector('.nav-indicator');
  const active = nav.querySelector('.list.active');
  if (!active || !ind) return;
  const idx = Array.from(items).indexOf(active);
  const w   = (nav.offsetWidth - 20) / items.length; // 20 = padding
  ind.style.transform = `translateX(${w * idx + (w/2 - 27)}px)`;
}
window.addEventListener('resize', fixNavIndicator);
window.addEventListener('load', fixNavIndicator);
setTimeout(fixNavIndicator, 150);
</script>
@endpush
