@extends('layouts.app')
@section('title', 'Kitir — Alokasi Kuota')

@section('content')
@php $agen = \App\Models\Agen::profil(); @endphp

{{-- Header --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Kitir — Scheduling Agreement</h1>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">
      Sold-To: <span style="font-family:monospace;font-weight:600">{{ $agen?->sold_to ?? '—' }}</span>
      &nbsp;·&nbsp; Ship-To: <span style="font-family:monospace;font-weight:600">{{ $agen?->ship_to ?? '—' }}</span>
      @if(! $agen?->sold_to)
        &nbsp;<a href="{{ route('dashboard.agen.db.agen') }}" style="color:#EF4444;font-size:11px">⚠ Lengkapi profil agen</a>
      @endif
    </p>
  </div>
  <button onclick="openModal('modal-tambah')"
          style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:500;cursor:pointer">
    + Input Kitir SA
  </button>
</div>

{{-- Filter bulan --}}
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
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
  <select name="jenis" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua Jenis</option>
    <option value="reguler" {{ $jenis === 'reguler' ? 'selected' : '' }}>Reguler</option>
    <option value="fakultatif" {{ $jenis === 'fakultatif' ? 'selected' : '' }}>Fakultatif</option>
  </select>
  <select name="status" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
    <option value="">Semua Status</option>
    <option value="aktif" {{ $status === 'aktif' ? 'selected' : '' }}>Aktif</option>
    <option value="selesai" {{ $status === 'selesai' ? 'selected' : '' }}>Selesai</option>
    <option value="batal" {{ $status === 'batal' ? 'selected' : '' }}>Batal</option>
  </select>
  <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Filter</button>
</form>

{{-- Tabel Kitir --}}
<div class="card" style="overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['No SA','SPBE','Jenis','Periode','Total Kuota','Sudah Tebus','Sisa','Status',''] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($kitirs as $k)
      @php
        $terbayar  = $k->details->whereIn('status',['sudah_tebus','diambil'])->sum('kuota_tabung');
        $sisa      = $k->total_kuota - $terbayar;
        $pctTebus  = $k->total_kuota > 0 ? round($terbayar/$k->total_kuota*100) : 0;
        $statusColor = match($k->status) {
            'aktif'   => 'background:#DBEAFE;color:#1E40AF',
            'selesai' => 'background:#D1FAE5;color:#065F46',
            'batal'   => 'background:#FEE2E2;color:#991B1B',
            default   => 'background:var(--bg);color:var(--muted)',
        };
        $jenisColor = $k->jenis === 'reguler'
            ? 'background:#EDE9FE;color:#5B21B6'
            : 'background:#FEF3C7;color:#92400E';
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:12px 14px">
          <span style="font-family:monospace;font-weight:700;color:var(--text);font-size:13px">{{ $k->nomor_sa }}</span>
        </td>
        <td style="padding:12px 14px">
          <span style="font-weight:500;color:var(--text)">{{ $k->spbe->nama_spbe }}</span>
          <span style="display:block;font-size:11px;color:var(--muted)">{{ $k->spbe->kode_spbe }}</span>
        </td>
        <td style="padding:12px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $jenisColor }}">
            {{ ucfirst($k->jenis) }}
          </span>
        </td>
        <td style="padding:12px 14px;font-size:12px;color:var(--text);white-space:nowrap">
          {{ $k->valid_from->format('d/m/Y') }} –<br>{{ $k->valid_to->format('d/m/Y') }}
        </td>
        <td style="padding:12px 14px;text-align:right;font-weight:700;color:var(--text)">
          {{ number_format($k->total_kuota) }}
          <span style="display:block;font-size:11px;font-weight:400;color:var(--muted)">{{ $k->details->count() }} hari</span>
        </td>
        <td style="padding:12px 14px;text-align:right">
          <span style="font-weight:600;color:#059669">{{ number_format($terbayar) }}</span>
          <div style="height:4px;background:var(--border);border-radius:2px;margin-top:4px;width:60px">
            <div style="height:4px;background:#059669;border-radius:2px;width:{{ $pctTebus }}%"></div>
          </div>
        </td>
        <td style="padding:12px 14px;text-align:right;font-weight:600;color:{{ $sisa > 0 ? '#F59E0B' : 'var(--muted)' }}">
          {{ number_format($sisa) }}
        </td>
        <td style="padding:12px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $statusColor }}">
            {{ ucfirst($k->status) }}
          </span>
        </td>
        <td style="padding:12px 14px;white-space:nowrap">
          <a href="{{ route('dashboard.agen.operasional.kitir.show', $k) }}"
             style="background:var(--accent);color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:12px;text-decoration:none;display:inline-block">
            Detail
          </a>
          @if($k->status === 'aktif' && $k->details->where('status','belum_tebus')->count() === $k->details->count())
          <form action="{{ route('dashboard.agen.operasional.kitir.destroy', $k) }}" method="POST" style="display:inline"
                onsubmit="return confirm('Hapus kitir SA#{{ $k->nomor_sa }}?')">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;margin-left:4px">Hapus</button>
          </form>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="9" style="padding:48px;text-align:center;color:var(--muted)">
          Belum ada kitir untuk periode ini.<br>
          <button onclick="openModal('modal-tambah')" style="margin-top:12px;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">
            + Input Kitir SA Pertama
          </button>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
  <div style="padding:12px 14px;border-top:1px solid var(--border)">{{ $kitirs->links() }}</div>
</div>

{{-- ── MODAL INPUT KITIR ─────────────────────────────────────────── --}}
<div id="modal-tambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:flex-start;justify-content:center;z-index:300;padding:20px;overflow-y:auto" onclick="closeModal('modal-tambah')">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:680px;margin:0 auto" onclick="event.stopPropagation()">

    {{-- Header modal --}}
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);z-index:1;border-radius:16px 16px 0 0">
      <div>
        <h3 style="font-size:16px;font-weight:700;color:var(--text)">Input Kitir — Scheduling Agreement</h3>
        <p style="font-size:12px;color:var(--muted);margin-top:2px">Salin data dari dokumen SA Pertamina</p>
      </div>
      <button onclick="closeModal('modal-tambah')" style="background:none;border:none;font-size:24px;color:var(--muted);cursor:pointer;line-height:1">×</button>
    </div>

    <form action="{{ route('dashboard.agen.operasional.kitir.store') }}" method="POST" style="padding:20px 24px">
      @csrf

      {{-- Baris 1: No SA, SPBE, Jenis --}}
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="flabel">No. SA / Schd Agrmt # *</label>
          <input name="nomor_sa" required placeholder="2718709" class="finput"
                 style="font-family:monospace;font-size:14px;font-weight:700">
        </div>
        <div>
          <label class="flabel">SPBE *</label>
          <select name="spbe_id" required class="finput">
            <option value="">-- Pilih SPBE --</option>
            @foreach($spbes as $s)
              <option value="{{ $s->id }}">{{ $s->nama_spbe }} ({{ $s->kode_spbe }})</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="flabel">Jenis *</label>
          <select name="jenis" required class="finput">
            <option value="reguler">Reguler</option>
            <option value="fakultatif">Fakultatif</option>
          </select>
        </div>
      </div>

      {{-- Baris 2: Sold-To, Ship-To, Nama Agen --}}
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label class="flabel">Sold-To *</label>
          <input name="sold_to" required value="{{ $agen?->sold_to }}"
                 placeholder="962536" class="finput" style="font-family:monospace">
        </div>
        <div>
          <label class="flabel">Ship-To *</label>
          <input name="ship_to" required value="{{ $agen?->ship_to }}"
                 placeholder="962538" class="finput" style="font-family:monospace">
        </div>
        <div>
          <label class="flabel">Nama Agen</label>
          <input value="{{ $agen?->nama_agen ?? '—' }}" disabled
                 class="finput" style="opacity:.55;cursor:not-allowed;background:var(--bg)">
        </div>
      </div>

      {{-- Tabel alokasi per tanggal - compact --}}
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
        <p style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">Alokasi Per Tanggal</p>
        <button type="button" onclick="tambahBarisTanggal()"
                style="background:none;border:1px solid var(--accent);color:var(--accent);border-radius:6px;padding:3px 10px;font-size:12px;cursor:pointer">
          + Tambah Baris
        </button>
      </div>

      {{-- Header kolom --}}
      <div style="display:grid;grid-template-columns:32px 1fr 140px 32px;gap:6px;padding:4px 6px;background:var(--bg);border-radius:6px 6px 0 0;border:1px solid var(--border);border-bottom:none">
        <span style="font-size:10px;font-weight:600;color:var(--muted);text-align:center">#</span>
        <span style="font-size:10px;font-weight:600;color:var(--muted)">TANGGAL</span>
        <span style="font-size:10px;font-weight:600;color:var(--muted)">KUOTA (TABUNG)</span>
        <span></span>
      </div>

      {{-- Baris data --}}
      <div id="tanggal-container" style="border:1px solid var(--border);border-radius:0 0 6px 6px;overflow:hidden">
        <div class="baris-tanggal" style="display:grid;grid-template-columns:32px 1fr 140px 32px;gap:0;border-bottom:1px solid var(--border);align-items:stretch">
          <span class="nomor-baris" style="display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--muted);font-weight:600;background:var(--bg);border-right:1px solid var(--border)">1</span>
          <input type="date" name="tanggals[]" required
                 style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box">
          <input type="number" name="kuotas[]" required min="1" placeholder="560"
                 style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box;text-align:right"
                 oninput="hitungTotal()">
          <button type="button" onclick="hapusBaris(this)"
                  style="border:none;background:none;color:#DC2626;font-size:16px;cursor:pointer;width:32px">×</button>
        </div>
      </div>

      {{-- Total --}}
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 4px;margin-bottom:14px">
        <span id="jumlah-baris" style="font-size:12px;color:var(--muted)">1 hari</span>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:12px;color:var(--muted)">Total Kuota:</span>
          <span id="total-kuota" style="font-size:22px;font-weight:700;color:var(--accent)">0</span>
          <span style="font-size:12px;color:var(--muted)">tabung</span>
        </div>
      </div>

      <div style="margin-bottom:16px">
        <label class="flabel">Keterangan</label>
        <input name="keterangan" class="finput" placeholder="Catatan tambahan (opsional)">
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:13px;font-weight:600;cursor:pointer">
          Simpan Kitir
        </button>
        <button type="button" onclick="closeModal('modal-tambah')"
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
.finput:focus{border-color:var(--accent)}
</style>
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal('modal-tambah'); });

function tambahBarisTanggal() {
  const container = document.getElementById('tanggal-container');
  const nomor     = container.querySelectorAll('.baris-tanggal').length + 1;
  const tpl = `
    <div class="baris-tanggal" style="display:grid;grid-template-columns:32px 1fr 140px 32px;gap:0;border-bottom:1px solid var(--border);align-items:stretch">
      <span class="nomor-baris" style="display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--muted);font-weight:600;background:var(--bg);border-right:1px solid var(--border)">${nomor}</span>
      <input type="date" name="tanggals[]" required
             style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box">
      <input type="number" name="kuotas[]" required min="1" placeholder="560"
             style="border:none;border-right:1px solid var(--border);padding:8px 10px;font-size:13px;background:var(--surface);color:var(--text);outline:none;width:100%;box-sizing:border-box;text-align:right"
             oninput="hitungTotal()">
      <button type="button" onclick="hapusBaris(this)"
              style="border:none;background:none;color:#DC2626;font-size:16px;cursor:pointer;width:32px">×</button>
    </div>`;
  container.insertAdjacentHTML('beforeend', tpl);
  renumberBaris();
}

function hapusBaris(btn) {
  const rows = document.querySelectorAll('.baris-tanggal');
  if (rows.length <= 1) { alert('Minimal harus ada 1 tanggal'); return; }
  btn.closest('.baris-tanggal').remove();
  renumberBaris();
  hitungTotal();
}

function renumberBaris() {
  document.querySelectorAll('.baris-tanggal .nomor-baris').forEach((el, i) => {
    el.textContent = i + 1;
  });
  const jml = document.querySelectorAll('.baris-tanggal').length;
  const el  = document.getElementById('jumlah-baris');
  if (el) el.textContent = jml + ' hari';
}

function hitungTotal() {
  const inputs = document.querySelectorAll('input[name="kuotas[]"]');
  let total = 0;
  inputs.forEach(i => total += parseInt(i.value || 0));
  document.getElementById('total-kuota').textContent = total.toLocaleString('id');
}
</script>
@endpush