@extends('layouts.app')
@section('title', 'Tebusan Kitir')

@section('content')
{{-- Header --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Tebusan Kitir — PSO LPG 3 Kg</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      Harga tebus aktif:
      @if($hargaTebus)
        <strong style="color:var(--text)">Rp {{ number_format($hargaTebus->harga) }}/tabung</strong>
        <span style="color:var(--muted)">(berlaku {{ $hargaTebus->berlaku_mulai->format('d/m/Y') }})</span>
      @else
        <a href="#" style="color:#EF4444">⚠ Belum ada harga — atur di Referensi Harga</a>
      @endif
    </p>
  </div>
  <button onclick="openModal('modal-tebusan')"
          style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:500;cursor:pointer">
    + Input Tebusan
  </button>
</div>

{{-- Filter bulan --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;align-items:center;flex-wrap:wrap">
  <select name="bulan" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    @foreach($bulanList as $n => $nama)
      <option value="{{ $n }}" {{ $bulan == $n ? 'selected' : '' }}>{{ $nama }}</option>
    @endforeach
  </select>
  <select name="tahun" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    @for($y = now()->year; $y >= now()->year - 2; $y--)
      <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
    @endfor
  </select>
  <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- Summary cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
  @foreach([
    ['Total Tabung Ditebus', number_format($summary->total_tabung ?? 0).' tb', '#3B82F6'],
    ['Total Tebusan',        'Rp '.number_format($summary->total_bayar ?? 0),    '#EF4444'],
    ['Selisih Pembulatan',   'Rp '.number_format(abs($summary->total_selisih ?? 0)), '#F59E0B'],
    ['Total Aktual Bayar',   'Rp '.number_format($summary->total_aktual ?? 0),   '#7C3AED'],
  ] as [$label, $val, $color])
  <div class="stat-card" style="border-left:3px solid {{ $color }}">
    <p style="font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em">{{ $label }}</p>
    <p style="font-size:18px;font-weight:700;color:var(--text);margin-top:4px">{{ $val }}</p>
  </div>
  @endforeach
</div>

{{-- Tabel tebusan --}}
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Tgl Bayar','No SA / SPBE','Tabung','Harga/Tabung','Total Tebus','Selisih','Total Aktual',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($tebusan as $t)
      @php
        $hargaPerTabung = $t->total_bayar > 0 && $t->jumlah_tabung_ditebus > 0
            ? $t->total_bayar / $t->jumlah_tabung_ditebus : 0;
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;font-weight:600;color:var(--text)">{{ $t->tanggal_bayar->format('d/m/Y') }}</td>
        <td style="padding:11px 14px">
          <span style="font-family:monospace;font-weight:700;color:var(--accent)">{{ $t->kitir->nomor_sa }}</span>
          <span style="display:block;font-size:11px;color:var(--muted)">{{ $t->kitir->spbe->nama_spbe }}</span>
        </td>
        <td style="padding:11px 14px;font-weight:700;color:var(--text)">{{ number_format($t->jumlah_tabung_ditebus) }}</td>
        <td style="padding:11px 14px;color:var(--muted)">Rp {{ number_format($hargaPerTabung) }}</td>
        <td style="padding:11px 14px;font-weight:600;color:#EF4444">Rp {{ number_format($t->total_bayar) }}</td>
        <td style="padding:11px 14px;font-size:12px">
          @if($t->selisih_pembulatan != 0)
            <span style="color:{{ $t->selisih_pembulatan > 0 ? '#F59E0B' : '#059669' }}">
              {{ $t->selisih_pembulatan > 0 ? '+' : '' }}Rp {{ number_format($t->selisih_pembulatan, 2) }}/tb
            </span>
          @else
            <span style="color:var(--muted)">—</span>
          @endif
        </td>
        <td style="padding:11px 14px;font-weight:700;color:#7C3AED">Rp {{ number_format($t->total_bayar_aktual) }}</td>
        <td style="padding:11px 14px">
          <button onclick="lihatDetail({{ Js::from($t->load('details.kitirDetail')) }})"
                  style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer">
            Detail
          </button>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" style="padding:40px;text-align:center;color:var(--muted)">
        Belum ada tebusan bulan ini
        @if($kitirAktif->isEmpty())
          <br><span style="font-size:12px">Pastikan sudah ada kitir aktif dengan kuota belum ditebus</span>
        @endif
      </td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $tebusan->links() }}</div>
</div>

{{-- ── MODAL INPUT TEBUSAN ──────────────────────────────────────── --}}
<div id="modal-tebusan" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:flex-start;justify-content:center;z-index:300;padding:20px;overflow-y:auto" onclick="closeModal('modal-tebusan')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:640px;margin:0 auto" onclick="event.stopPropagation()">

    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Input Tebusan Kitir</h3>
      <button onclick="closeModal('modal-tebusan')" style="background:none;border:none;font-size:24px;color:var(--muted);cursor:pointer">×</button>
    </div>

    <form action="{{ route('dashboard.agen.akuntansi.tebusan.store') }}" method="POST" style="padding:20px 24px">
      @csrf
      <input type="hidden" name="kitir_id" id="input_kitir_id">

      {{-- Step 1: Pilih No SA --}}
      <div style="margin-bottom:16px">
        <label class="flabel">No. SA / Scheduling Agreement *</label>
        <div style="display:flex;gap:8px">
          <select id="select_sa" class="finput" onchange="loadKitirDetail(this.value)">
            <option value="">-- Pilih SA yang ingin ditebus --</option>
            @foreach($kitirAktif as $k)
              <option value="{{ $k->nomor_sa }}" data-id="{{ $k->id }}">
                SA# {{ $k->nomor_sa }} — {{ $k->spbe->nama_spbe }}
                ({{ $k->details->sum('kuota_tabung') }} tabung belum ditebus)
              </option>
            @endforeach
          </select>
        </div>
      </div>

      {{-- Info SA yang dipilih --}}
      <div id="sa-info" style="display:none;background:var(--bg);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px">
        <div style="display:flex;gap:20px;flex-wrap:wrap">
          <span style="color:var(--muted)">SPBE: <strong id="info-spbe" style="color:var(--text)">—</strong></span>
          <span style="color:var(--muted)">Total kuota: <strong id="info-kuota" style="color:var(--text)">—</strong></span>
        </div>
      </div>

      {{-- Tabel pilih tanggal yang akan ditebus --}}
      <div id="detail-container" style="display:none;margin-bottom:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <label class="flabel" style="margin:0">Pilih Tanggal yang Ditebus *</label>
          <div style="display:flex;gap:6px">
            <button type="button" onclick="checkAll(true)" style="border:1px solid var(--border);background:none;color:var(--muted);border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">Semua</button>
            <button type="button" onclick="checkAll(false)" style="border:1px solid var(--border);background:none;color:var(--muted);border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">Batal Semua</button>
          </div>
        </div>

        {{-- Header tabel detail --}}
        <div style="display:grid;grid-template-columns:32px 1fr 100px 1fr 1fr;gap:0;background:var(--bg);border:1px solid var(--border);border-bottom:none;border-radius:6px 6px 0 0;padding:6px 10px">
          <span></span>
          <span style="font-size:10px;font-weight:600;color:var(--muted)">TANGGAL</span>
          <span style="font-size:10px;font-weight:600;color:var(--muted);text-align:right">KUOTA</span>
          <span style="font-size:10px;font-weight:600;color:var(--muted);text-align:right">HARGA/TABUNG</span>
          <span style="font-size:10px;font-weight:600;color:var(--muted);text-align:right">SUBTOTAL</span>
        </div>
        <div id="detail-rows" style="border:1px solid var(--border);border-radius:0 0 6px 6px;overflow:hidden"></div>
      </div>

      {{-- Harga & Transfer --}}
      <div id="harga-section" style="display:none">
        {{-- Harga tebus hidden - auto dari DB --}}
        <input name="harga_tebus" id="input_harga_tebus" type="hidden"
               value="{{ $hargaTebus?->harga ?? '' }}">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div style="background:var(--bg);border-radius:8px;padding:10px 14px">
            <p style="font-size:11px;color:var(--muted);margin-bottom:2px">Harga Tebus/Tabung</p>
            <p style="font-size:18px;font-weight:700;color:var(--text)">
              Rp {{ number_format($hargaTebus?->harga ?? 0) }}
              @if(!$hargaTebus)
                <a href="{{ route('dashboard.agen.akuntansi.harga.index') }}" style="color:#EF4444;font-size:11px;font-weight:400">⚠ Atur harga dulu</a>
              @endif
            </p>
          </div>
          <div>
            <label class="flabel">Tanggal Bayar *</label>
            <input name="tanggal_bayar" type="date" required
                   value="{{ now()->toDateString() }}" class="finput">
          </div>
        </div>

        {{-- Ringkasan total SEBELUM selisih --}}
        <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px 14px;margin-bottom:14px">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <div style="display:flex;gap:20px;align-items:center">
              <div>
                <p style="font-size:11px;color:var(--muted)">Tabung dipilih</p>
                <p id="sum-tabung" style="font-size:22px;font-weight:700;color:var(--text)">0</p>
              </div>
              <span style="font-size:20px;color:var(--muted)">×</span>
              <div>
                <p style="font-size:11px;color:var(--muted)">Harga/tabung</p>
                <p id="sum-harga" style="font-size:18px;font-weight:700;color:var(--text)">Rp 0</p>
              </div>
              <span style="font-size:20px;color:var(--muted)">=</span>
              <div>
                <p style="font-size:11px;color:var(--muted)">Total perhitungan</p>
                <p id="sum-total" style="font-size:22px;font-weight:700;color:#EF4444">Rp 0</p>
              </div>
            </div>
          </div>
        </div>

        {{-- Data transfer / slip --}}
        <p style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Data Transfer (dari Slip)</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div>
            <label class="flabel">No. Rekening Tujuan</label>
            <input name="no_rekening_tujuan" class="finput" style="font-family:monospace" placeholder="Rekening SPBE tujuan">
          </div>
          <div>
            <label class="flabel">
              Jumlah Transfer Aktual (Rp)
              <span style="font-weight:400;font-size:10px;color:var(--muted)">dari slip transfer</span>
            </label>
            <input name="jumlah_transfer_aktual" id="input_transfer" type="text"
                   placeholder="mis: 30822401,2 atau 30822401.20" class="finput"
                   oninput="hitungSelisih()" style="font-family:monospace">
          </div>
          <div>
            <label class="flabel">Selisih Pembulatan Total (Rp) <span style="color:var(--muted);font-weight:400;font-size:10px">otomatis terhitung</span></label>
            <input name="selisih_pembulatan" id="input_selisih" type="number" step="0.01"
                   value="0" class="finput" style="font-family:monospace" readonly>
          </div>
          <div>
            <label class="flabel">Total Aktual Dibayar (Rp)</label>
            <input id="sum-aktual-display" disabled class="finput"
                   style="font-weight:700;color:#7C3AED;opacity:1;cursor:default;background:var(--bg)">
          </div>
          <div style="grid-column:1/-1">
            <label class="flabel">Keterangan</label>
            <input name="keterangan" class="finput" placeholder="Catatan tambahan (opsional)">
          </div>
        </div>



        <div style="display:flex;gap:8px">
          <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:13px;font-weight:600;cursor:pointer">
            Simpan Tebusan
          </button>
          <button type="button" onclick="closeModal('modal-tebusan')"
                  style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:10px 16px;font-size:13px;cursor:pointer">
            Batal
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Modal Detail --}}
<div id="modal-detail" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);align-items:center;justify-content:center;z-index:300;padding:16px" onclick="closeModal('modal-detail')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:520px" onclick="event.stopPropagation()">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Detail Tebusan</h3>
      <button onclick="closeModal('modal-detail')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <div id="modal-detail-content" style="padding:20px 24px"></div>
  </div>
</div>

@endsection

@push('scripts')
<style>
.flabel{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.finput{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box}
.finput:focus{border-color:var(--accent)}
.detail-row-check:checked ~ * { background:rgba(37,99,235,.05); }
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') { closeModal('modal-tebusan'); closeModal('modal-detail'); }});

let kitirDetails = [];

async function loadKitirDetail(nomor_sa) {
  const opt = document.querySelector(`#select_sa option[value="${nomor_sa}"]`);

  document.getElementById('sa-info').style.display      = 'none';
  document.getElementById('detail-container').style.display = 'none';
  document.getElementById('harga-section').style.display    = 'none';

  if (!nomor_sa) return;

  const res  = await fetch(`{{ route('dashboard.agen.akuntansi.tebusan.kitir-detail') }}?nomor_sa=${nomor_sa}`);
  const data = await res.json();

  if (!data.success) { alert(data.message); return; }

  document.getElementById('input_kitir_id').value = opt?.dataset.id ?? '';
  document.getElementById('info-spbe').textContent  = data.spbe;
  document.getElementById('info-kuota').textContent = data.total_belum_tebus + ' tabung belum ditebus';
  document.getElementById('sa-info').style.display  = 'block';

  // Set harga tebus dari SA
  if (data.harga_tebus) {
    document.getElementById('input_harga_tebus').value = data.harga_tebus;
  }

  kitirDetails = data.details;
  renderDetailRows(data.details);

  document.getElementById('detail-container').style.display = 'block';
  document.getElementById('harga-section').style.display    = 'block';
  hitungTotal();
}

function renderDetailRows(details) {
  const container = document.getElementById('detail-rows');
  container.innerHTML = details.map((d, i) => `
    <label style="display:grid;grid-template-columns:32px 1fr 100px 1fr 1fr;gap:0;border-bottom:1px solid var(--border);padding:8px 10px;cursor:pointer;align-items:center"
           class="hover-row">
      <input type="checkbox" name="detail_ids[]" value="${d.id}" checked
             onchange="hitungTotal()" style="width:16px;height:16px;cursor:pointer">
      <span style="font-size:13px;font-weight:500;color:var(--text)">${d.tanggal}</span>
      <span style="font-size:13px;text-align:right;color:var(--text)">${d.kuota_tabung.toLocaleString('id')}</span>
      <span style="font-size:13px;text-align:right;color:var(--muted)" class="cell-harga">Rp ${d.harga_tebus.toLocaleString('id')}</span>
      <span style="font-size:13px;text-align:right;font-weight:600;color:var(--text)" class="cell-subtotal">Rp ${d.subtotal.toLocaleString('id')}</span>
    </label>
  `).join('');
}

function checkAll(state) {
  document.querySelectorAll('input[name="detail_ids[]"]').forEach(cb => { cb.checked = state; });
  hitungTotal();
}

function hitungTotal() {
  const checks = document.querySelectorAll('input[name="detail_ids[]"]:checked');
  const harga  = parseFloat((document.getElementById('input_harga_tebus')?.value || '0').replace(',', '.'));

  let totalTabung = 0;
  checks.forEach(cb => {
    const d = kitirDetails.find(x => x.id == cb.value);
    if (d) totalTabung += d.kuota_tabung;
  });

  const totalTebus = totalTabung * harga;

  document.getElementById('sum-tabung').textContent = totalTabung.toLocaleString('id');
  document.getElementById('sum-harga').textContent  = 'Rp ' + harga.toLocaleString('id');
  document.getElementById('sum-total').textContent  = 'Rp ' + Math.round(totalTebus).toLocaleString('id');

  // Update cell harga & subtotal di tabel
  document.querySelectorAll('.cell-harga').forEach(el => el.textContent = 'Rp ' + harga.toLocaleString('id'));
  document.querySelectorAll('#detail-rows label').forEach((label, i) => {
    if (kitirDetails[i]) {
      const sub = kitirDetails[i].kuota_tabung * harga;
      const el  = label.querySelector('.cell-subtotal');
      if (el) el.textContent = 'Rp ' + Math.round(sub).toLocaleString('id');
    }
  });

  hitungSelisih();
}

function hitungSelisih() {
  const checks = document.querySelectorAll('input[name="detail_ids[]"]:checked');
  const harga  = parseFloat((document.getElementById('input_harga_tebus')?.value || '0').replace(',', '.'));

  const rawTransfer = (document.getElementById('input_transfer')?.value || '0').replace(',', '.');
  const aktual = parseFloat(rawTransfer) || 0;

  let totalTabung = 0;
  checks.forEach(cb => {
    const d = kitirDetails.find(x => x.id == cb.value);
    if (d) totalTabung += d.kuota_tabung;
  });

  const totalTebus   = totalTabung * harga;
  const selisihTotal = aktual - totalTebus; // total selisih Rp (bukan per tabung)

  // Simpan selisih TOTAL (bukan per tabung)
  document.getElementById('input_selisih').value = selisihTotal.toFixed(2);

  // Hidden field jumlah aktual
  let hiddenAktual = document.getElementById('hidden_transfer_aktual');
  if (!hiddenAktual) {
    hiddenAktual = document.createElement('input');
    hiddenAktual.type = 'hidden';
    hiddenAktual.name = 'jumlah_transfer_aktual';
    hiddenAktual.id   = 'hidden_transfer_aktual';
    document.getElementById('input_transfer').closest('form').appendChild(hiddenAktual);
  }
  hiddenAktual.value = aktual;

  // Total aktual — tampilkan bersih tanpa keterangan selisih
  const displayEl = document.getElementById('sum-aktual-display');
  if (aktual > 0) {
    displayEl.value = 'Rp ' + aktual.toLocaleString('id', {minimumFractionDigits:1, maximumFractionDigits:2});
    displayEl.style.color = '#7C3AED';
  } else {
    displayEl.value = '';
  }
}

function lihatDetail(t) {
  const hargaPT = t.jumlah_tabung_ditebus > 0 ? (t.total_bayar / t.jumlah_tabung_ditebus) : 0;
  document.getElementById('modal-detail-content').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;font-size:13px">
      <div><p style="font-size:11px;color:var(--muted)">No SA</p><p style="font-weight:700;font-family:monospace;color:var(--accent)">${t.kitir?.nomor_sa ?? '—'}</p></div>
      <div><p style="font-size:11px;color:var(--muted)">Tgl Bayar</p><p style="font-weight:600">${t.tanggal_bayar}</p></div>
      <div><p style="font-size:11px;color:var(--muted)">Total Tabung</p><p style="font-weight:700;font-size:18px">${t.jumlah_tabung_ditebus?.toLocaleString('id')}</p></div>
      <div><p style="font-size:11px;color:var(--muted)">Harga/Tabung</p><p style="font-weight:600">Rp ${Math.round(hargaPT).toLocaleString('id')}</p></div>
      <div><p style="font-size:11px;color:var(--muted)">Total Tebusan</p><p style="font-weight:700;color:#EF4444">Rp ${t.total_bayar?.toLocaleString('id')}</p></div>
      <div><p style="font-size:11px;color:var(--muted)">Selisih</p><p style="color:#F59E0B">Rp ${t.selisih_pembulatan}/tb</p></div>
      <div style="grid-column:1/-1"><p style="font-size:11px;color:var(--muted)">Total Aktual Dibayar</p><p style="font-weight:700;font-size:20px;color:#7C3AED">Rp ${t.total_bayar_aktual?.toLocaleString('id')}</p></div>
    </div>
    <p style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:8px">TANGGAL YANG DITEBUS</p>
    ${(t.details || []).map(d => `
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px">
        <span>${d.kitir_detail?.tanggal ?? '—'}</span>
        <span style="font-weight:600">${d.jumlah_tabung?.toLocaleString('id')} tabung</span>
        <span style="color:#EF4444">Rp ${d.subtotal?.toLocaleString('id')}</span>
      </div>`).join('')}
  `;
  openModal('modal-detail');
}
</script>
@endpush
