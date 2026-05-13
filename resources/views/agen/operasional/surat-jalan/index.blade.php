@extends('layouts.app')
@section('title', 'Surat Jalan')

@section('content')
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Surat Jalan</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">Pengantar ke SPBE + Jadwal Distribusi ke Pangkalan</p>
  </div>
  <button onclick="openModal('modal-buat')"
          style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:500;cursor:pointer">
    + Buat Surat Jalan
  </button>
</div>

{{-- Filter --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <select name="bulan" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    @foreach($bulanList as $n => $nama)
      <option value="{{ $n }}" {{ $bulan == $n ? 'selected' : '' }}>{{ $nama }}</option>
    @endforeach
  </select>
  <select name="tahun" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    @for($y = now()->year; $y >= now()->year - 1; $y--)
      <option value="{{ $y }}" {{ $tahun == $y ? 'selected':'' }}>{{ $y }}</option>
    @endfor
  </select>
  <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- Tabel SJ --}}
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['No SJ','Tanggal','SA / SPBE','Armada','Sopir / Kernet','Kuota','Jadwal','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($suratJalan as $sj)
      @php
        $sc = match($sj->status) {
          'draft'   => 'background:#F1F5F9;color:#475569',
          'aktif'   => 'background:#DBEAFE;color:#1E40AF',
          'selesai' => 'background:#D1FAE5;color:#065F46',
          'batal'   => 'background:#FEE2E2;color:#991B1B',
          default   => '',
        };
      @endphp
      <tr style="border-top:1px solid var(--border);{{ $sj->status==='batal' ? 'opacity:.6' : '' }}">
        <td style="padding:11px 14px">
          <span style="font-family:monospace;font-weight:700;color:var(--accent);font-size:12px">{{ $sj->no_sj }}</span>
          @if($sj->no_lo)
            <span style="display:block;font-size:10px;color:var(--muted)">LO: {{ $sj->no_lo }}</span>
          @endif
        </td>
        <td style="padding:11px 14px;font-weight:600;color:var(--text)">
          {{ $sj->tanggal->format('d/m/Y') }}
          <span style="display:block;font-size:11px;color:var(--muted)">{{ $sj->tanggal->translatedFormat('l') }}</span>
        </td>
        <td style="padding:11px 14px">
          <span style="font-family:monospace;color:var(--accent)">{{ $sj->kitirDetail?->kitir?->nomor_sa ?? '—' }}</span>
          <span style="display:block;font-size:11px;color:var(--muted)">{{ $sj->kitirDetail?->kitir?->spbe?->nama_spbe ?? '—' }}</span>
        </td>
        <td style="padding:11px 14px;font-family:monospace;font-weight:600;color:var(--text)">{{ $sj->armada?->no_polisi ?? '—' }}</td>
        <td style="padding:11px 14px;font-size:12px">
          <span style="color:var(--text)">{{ $sj->sopir?->nama_karyawan ?? '—' }}</span>
          @if($sj->kernet)
            <span style="display:block;color:var(--muted)">{{ $sj->kernet->nama_karyawan }}</span>
          @endif
        </td>
        <td style="padding:11px 14px;text-align:right;font-weight:700;color:var(--text)">{{ number_format($sj->qty_refil) }}</td>
        <td style="padding:11px 14px;text-align:right;color:var(--muted)">{{ number_format($sj->total_terjadwal) }}</td>
        <td style="padding:11px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $sc }}">{{ $sj->status_label }}</span>
        </td>
        <td style="padding:11px 14px;white-space:nowrap">
          <a href="{{ route('dashboard.agen.operasional.sj.show', $sj) }}"
             style="background:var(--accent);color:#fff;border-radius:6px;padding:4px 10px;font-size:12px;text-decoration:none;display:inline-block">
            Detail
          </a>
          @if($sj->status !== 'batal')
          <a href="{{ route('dashboard.agen.operasional.sj.cetak-spbe', $sj) }}" target="_blank"
             style="border:1px solid #7C3AED;color:#7C3AED;border-radius:6px;padding:4px 10px;font-size:12px;text-decoration:none;display:inline-block;margin-left:4px">
            🖨 SPBE
          </a>
          <a href="{{ route('dashboard.agen.operasional.sj.cetak-distribusi', $sj) }}" target="_blank"
             style="border:1px solid #059669;color:#059669;border-radius:6px;padding:4px 10px;font-size:12px;text-decoration:none;display:inline-block;margin-left:4px">
            🖨 Dist
          </a>
          @else
          <form action="{{ route('dashboard.agen.operasional.sj.destroy', $sj) }}" method="POST"
                style="display:inline"
                onsubmit="return confirm('Hapus permanen SJ {{ $sj->no_sj }}? Data tidak bisa dikembalikan.')">
            @csrf @method('DELETE')
            <button type="submit"
                    style="border:1px solid #FECACA;color:#DC2626;background:none;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;margin-left:4px">
              🗑 Hapus
            </button>
          </form>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="9" style="padding:48px;text-align:center;color:var(--muted)">
        Belum ada surat jalan bulan ini
      </td></tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $suratJalan->links() }}</div>
</div>

{{-- ── MODAL BUAT SURAT JALAN ──────────────────────────────── --}}
<div id="modal-buat" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:flex-start;justify-content:center;z-index:300;padding:16px;overflow-y:auto" onclick="closeModal('modal-buat')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:700px;margin:0 auto" onclick="event.stopPropagation()">

    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3 style="font-size:16px;font-weight:700;color:var(--text)">Buat Surat Jalan</h3>
      <button onclick="closeModal('modal-buat')" style="background:none;border:none;font-size:24px;color:var(--muted);cursor:pointer">×</button>
    </div>

    <form action="{{ route('dashboard.agen.operasional.sj.store') }}" method="POST" style="padding:20px 24px">
      @csrf

      {{-- Dropdown SA / Kitir Detail --}}
      <div style="margin-bottom:16px">
        <label class="flabel">Pilih SA & Tanggal Kuota *</label>
        <select name="kitir_detail_id" id="input_kitir_detail_id" required class="finput"
                onchange="onSaChange(this)" style="font-family:monospace">
          <option value="">-- Pilih SA (hanya yang sudah ditebus) --</option>
          @foreach($kitirSiapSJ as $kd)
            <option value="{{ $kd->id }}"
                    data-tgl="{{ $kd->tanggal->format('Y-m-d') }}"
                    data-kuota="{{ $kd->kuota_tabung }}">
              SA#{{ $kd->kitir->nomor_sa }} — {{ $kd->tanggal->format('d/m/Y') }}
              ({{ number_format($kd->kuota_tabung) }} tabung · {{ $kd->kitir->spbe->nama_spbe }})
            </option>
          @endforeach
        </select>
        @if($kitirSiapSJ->isEmpty())
          <p style="font-size:11px;color:#F59E0B;margin-top:4px">
            ⚠ Belum ada kitir yang siap dibuat SJ — pastikan tanggal kitir sudah ditebus di menu Tebusan
          </p>
        @endif
      </div>

      {{-- Info SA yang dipilih --}}
      <div id="sa-selected" style="display:none;background:var(--bg);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px">
        <div style="display:flex;gap:20px;flex-wrap:wrap">
          <span style="color:var(--muted)">SA#: <strong id="disp_sa" style="color:var(--accent);font-family:monospace">—</strong></span>
          <span style="color:var(--muted)">SPBE: <strong id="disp_spbe" style="color:var(--text)">—</strong></span>
          <span style="color:var(--muted)">Kuota: <strong id="disp_kuota" style="color:var(--text)">—</strong> tabung</span>
        </div>
      </div>

      {{-- Baris 1: Tanggal, Armada --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="flabel">Tanggal Pengambilan *</label>
          <input name="tanggal" id="input_tanggal" type="date" required class="finput"
                 value="{{ now()->toDateString() }}" onchange="updateNomorSJ()">
        </div>
        <div>
          <label class="flabel">Armada *</label>
          <select name="armada_id" id="input_armada" required class="finput" onchange="onArmadaChange(this)">
            <option value="">-- Pilih Armada --</option>
            @foreach($armadas as $a)
              <option value="{{ $a->id }}"
                      data-polisi="{{ $a->no_polisi }}"
                      data-sopir="{{ $a->sopir_id }}"
                      data-kernet="{{ $a->kernet_id }}">
                {{ $a->no_polisi }} {{ $a->jenis ? '('.$a->jenis.')' : '' }}
              </option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="flabel">Sopir *</label>
          <select name="sopir_id" id="input_sopir" required class="finput">
            <option value="">-- Pilih Sopir --</option>
            @foreach($drivers as $d)
              <option value="{{ $d->id }}">{{ $d->nama_karyawan }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="flabel">Kernet</label>
          <select name="kernet_id" id="input_kernet" class="finput">
            <option value="">-- Pilih Kernet --</option>
            @foreach($kernets as $k)
              <option value="{{ $k->id }}">{{ $k->nama_karyawan }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="flabel">Refil (tabung) *</label>
          <input name="qty_refil" id="input_qty_refil" type="number" required min="1" value="560" class="finput">
        </div>
        <div>
          <label class="flabel">Tabung Baru</label>
          <input name="qty_tabung_baru" type="number" min="0" value="0" class="finput">
        </div>
      </div>

      {{-- Preview No SJ --}}
      <div style="background:rgba(37,99,235,.06);border-radius:8px;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
        <span style="font-size:12px;color:var(--muted)">No. SJ:</span>
        <span id="preview_no_sj" style="font-family:monospace;font-weight:700;color:var(--accent);font-size:15px">—</span>
        <span style="font-size:11px;color:var(--muted)">(otomatis)</span>
      </div>

      {{-- Tabel Distribusi Pangkalan --}}
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <p style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">Jadwal Distribusi ke Pangkalan</p>
        <button type="button" onclick="tambahPangkalan()"
                style="border:1px solid var(--accent);color:var(--accent);background:none;border-radius:6px;padding:3px 10px;font-size:12px;cursor:pointer">
          + Tambah Pangkalan
        </button>
      </div>

      {{-- Header tabel --}}
      <div style="display:grid;grid-template-columns:32px 1fr 80px 80px 32px;gap:0;background:var(--bg);border:1px solid var(--border);border-bottom:none;border-radius:6px 6px 0 0;padding:6px 10px">
        <span style="font-size:10px;font-weight:600;color:var(--muted);text-align:center">#</span>
        <span style="font-size:10px;font-weight:600;color:var(--muted)">PANGKALAN</span>
        <span style="font-size:10px;font-weight:600;color:var(--muted);text-align:right">QTY</span>
        <span style="font-size:10px;font-weight:600;color:var(--muted);text-align:center">URUTAN</span>
        <span></span>
      </div>
      <div id="pangkalan-container" style="border:1px solid var(--border);border-radius:0 0 6px 6px;overflow:hidden">
        {{-- Baris default --}}
        <div class="baris-pangkalan" style="display:grid;grid-template-columns:32px 1fr 80px 80px 32px;gap:0;border-bottom:1px solid var(--border);align-items:stretch">
          <span class="urutan-num" style="display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--muted);font-weight:600;background:var(--bg);border-right:1px solid var(--border)">1</span>
          <select name="pangkalan_ids[]" required style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box">
            <option value="">-- Pilih Pangkalan --</option>
            @foreach($pangkalans as $p)
              <option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>
            @endforeach
          </select>
          <input type="number" name="qty_jadwals[]" required min="1" value="560"
                 style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box;text-align:right"
                 oninput="hitungTotalJadwal()">
          <input type="number" name="urutans[]" required min="1" value="1"
                 style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box;text-align:center">
          <button type="button" onclick="hapusPangkalan(this)"
                  style="border:none;background:none;color:#DC2626;font-size:16px;cursor:pointer">×</button>
        </div>
      </div>

      {{-- Total jadwal vs kuota --}}
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 4px;margin-bottom:16px">
        <span style="font-size:12px;color:var(--muted)" id="jml-pangkalan">1 pangkalan</span>
        <div style="display:flex;gap:12px;align-items:center">
          <span style="font-size:12px;color:var(--muted)">Total dijadwal:</span>
          <span id="total-jadwal" style="font-size:18px;font-weight:700;color:var(--accent)">0</span>
          <span style="font-size:12px;color:var(--muted)">tabung</span>
        </div>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:13px;font-weight:600;cursor:pointer">
          Buat Surat Jalan
        </button>
        <button type="button" onclick="closeModal('modal-buat')"
                style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:10px 16px;font-size:13px;cursor:pointer">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<style>
.flabel{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.finput{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box}
</style>
<script>
const pangkalanOptions = `@foreach($pangkalans as $p)<option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>@endforeach`;
const kodeAgen = '{{ \App\Models\Agen::profil()?->kode_agen ?? "AGN" }}';

function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal('modal-buat'); });

// Data SA dari server untuk lookup
const saData = {
  @foreach($kitirSiapSJ as $kd)
  {{ $kd->id }}: {
    tgl: '{{ $kd->tanggal->format('Y-m-d') }}',
    kuota: {{ $kd->kuota_tabung }},
    sa: '{{ $kd->kitir->nomor_sa }}',
    spbe: '{{ addslashes($kd->kitir->spbe->nama_spbe) }}'
  },
  @endforeach
};

function onSaChange(sel) {
  const id  = sel.value;
  const d   = saData[id];
  if (!id || !d) {
    document.getElementById('sa-selected').style.display = 'none';
    document.getElementById('input_tanggal').value = '';
    document.getElementById('input_qty_refil').value = 560;
    updateNomorSJ();
    return;
  }
  // Auto-fill tanggal dan qty refil dari SA yang dipilih
  document.getElementById('input_tanggal').value       = d.tgl;
  document.getElementById('input_qty_refil').value     = d.kuota;
  document.getElementById('disp_sa').textContent       = d.sa;
  document.getElementById('disp_spbe').textContent     = d.spbe;
  document.getElementById('disp_kuota').textContent    = d.kuota.toLocaleString('id');
  document.getElementById('sa-selected').style.display = 'block';
  updateNomorSJ();
}

function onArmadaChange(sel) {
  const opt = sel.options[sel.selectedIndex];
  const sopirId  = opt.dataset.sopir;
  const kernetId = opt.dataset.kernet;
  if (sopirId)  document.getElementById('input_sopir').value  = sopirId;
  if (kernetId) document.getElementById('input_kernet').value = kernetId;
  updateNomorSJ();
}

function updateNomorSJ() {
  const tgl   = document.getElementById('input_tanggal')?.value;
  const arm   = document.getElementById('input_armada');
  const plat  = arm?.options[arm.selectedIndex]?.dataset?.polisi ?? '';
  if (!tgl || !plat) { document.getElementById('preview_no_sj').textContent = '—'; return; }
  const d = new Date(tgl);
  const yy = String(d.getFullYear()).slice(2);
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  const platClean = plat.replace(/[^A-Z0-9]/gi,'').toUpperCase();
  // Nomor urut preview (sementara XX, server yang tentukan final)
  document.getElementById('preview_no_sj').textContent = `${kodeAgen}-${yy}${mm}${dd}-${platClean}-XX`;
}

function tambahPangkalan() {
  const container = document.getElementById('pangkalan-container');
  const n = container.querySelectorAll('.baris-pangkalan').length + 1;
  const tpl = `
    <div class="baris-pangkalan" style="display:grid;grid-template-columns:32px 1fr 80px 80px 32px;gap:0;border-bottom:1px solid var(--border);align-items:stretch">
      <span class="urutan-num" style="display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--muted);font-weight:600;background:var(--bg);border-right:1px solid var(--border)">${n}</span>
      <select name="pangkalan_ids[]" required style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box">
        <option value="">-- Pilih Pangkalan --</option>${pangkalanOptions}
      </select>
      <input type="number" name="qty_jadwals[]" required min="1" value="560"
             style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box;text-align:right"
             oninput="hitungTotalJadwal()">
      <input type="number" name="urutans[]" required min="1" value="${n}"
             style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box;text-align:center">
      <button type="button" onclick="hapusPangkalan(this)"
              style="border:none;background:none;color:#DC2626;font-size:16px;cursor:pointer">×</button>
    </div>`;
  container.insertAdjacentHTML('beforeend', tpl);
  renumberPangkalan();
  hitungTotalJadwal();
}

function hapusPangkalan(btn) {
  const rows = document.querySelectorAll('.baris-pangkalan');
  if (rows.length <= 1) { alert('Minimal 1 pangkalan'); return; }
  btn.closest('.baris-pangkalan').remove();
  renumberPangkalan();
  hitungTotalJadwal();
}

function renumberPangkalan() {
  document.querySelectorAll('.baris-pangkalan .urutan-num').forEach((el, i) => el.textContent = i+1);
  const n = document.querySelectorAll('.baris-pangkalan').length;
  document.getElementById('jml-pangkalan').textContent = n + ' pangkalan';
}

function hitungTotalJadwal() {
  const inputs = document.querySelectorAll('input[name="qty_jadwals[]"]');
  let total = 0;
  inputs.forEach(i => total += parseInt(i.value||0));
  document.getElementById('total-jadwal').textContent = total.toLocaleString('id');
}

// Hitung saat load
hitungTotalJadwal();
</script>
@endpush
