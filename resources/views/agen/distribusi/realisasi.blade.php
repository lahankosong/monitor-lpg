@extends('layouts.app')
@section('title', 'Realisasi Distribusi')
@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Realisasi Distribusi</h1>
    <p style="font-size:12px;color:var(--muted)">Input bebas — jadwal hanya referensi</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('dashboard.agen.distribusi.laporan') }}"
       style="border:1px solid var(--border);color:var(--text);background:var(--surface);
              border-radius:8px;padding:7px 14px;font-size:13px;text-decoration:none">
      📊 Laporan
    </a>
  </div>
</div>

{{-- Filter tanggal --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;align-items:center;flex-wrap:wrap">
  <input type="date" name="tanggal" value="{{ $tanggal }}"
         style="border:1px solid var(--border);background:var(--surface);color:var(--text);
                border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
  <button type="submit" style="background:var(--accent);color:#fff;border:none;
          border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Tampilkan</button>
  @if($tanggal !== now()->toDateString())
    <a href="{{ route('dashboard.agen.distribusi.index') }}"
       style="border:1px solid var(--border);border-radius:8px;padding:8px 14px;
              font-size:13px;color:var(--muted);text-decoration:none">Hari Ini</a>
  @endif
</form>

{{-- Panel stok & gendongan --}}
@php $adaStok = $stokArmada->isNotEmpty() || $stokGudang > 0 || ($bufferKosong ?? 0) > 0; @endphp
@if($adaStok)
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:16px">
  @if(($bufferKosong ?? 0) > 0)
  <div class="stat-card" style="border-left:3px solid var(--accent)">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">Buffer Kosong Gudang</p>
    <p style="font-size:22px;font-weight:700;color:var(--accent);margin-top:4px">{{ number_format($bufferKosong) }}</p>
    <p style="font-size:11px;color:var(--muted)">tabung kosong tersedia</p>
  </div>
  @endif
  @if($stokGudang > 0)
  <div class="stat-card" style="border-left:3px solid #7C3AED">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em">Stok Isi Gudang</p>
    <p style="font-size:22px;font-weight:700;color:#7C3AED;margin-top:4px">{{ number_format($stokGudang) }}</p>
    <p style="font-size:11px;color:var(--muted)">tabung isi siap distribusi</p>
  </div>
  @endif
  @foreach($stokArmada as $armadaId => $stoks)
  @php $totalGnd = $stoks->sum('sisa_akhir'); $noPolisi = $stoks->first()->armada?->no_polisi ?? '?'; @endphp
  @if($totalGnd > 0)
  <div class="stat-card" style="border-left:3px solid #F59E0B;background:rgba(245,158,11,.05)">
    <p style="font-size:10px;color:#92400E;font-weight:600;text-transform:uppercase;letter-spacing:.06em">⚡ Gendongan · {{ $noPolisi }}</p>
    <p style="font-size:22px;font-weight:700;color:#F59E0B;margin-top:4px">{{ number_format($totalGnd) }}</p>
    <p style="font-size:10px;color:#92400E;margin-top:2px;font-style:italic">Wajib habis sebelum DO baru</p>
  </div>
  @endif
  @endforeach
</div>
@endif

{{-- ═══ DAFTAR SJ ═══ --}}
@forelse($sjHariIni as $sj)
@php
  $totalTersedia = $sj->qty_refil
                 + ($sj->qty_gendongan_masuk ?? 0)
                 + ($sj->qty_ambil_gudang ?? 0);
  $totalTerkirim = $sj->details->sum('qty_terima');
  $pct           = $totalTersedia > 0 ? round($totalTerkirim / $totalTersedia * 100) : 0;
  $sisaTrip      = max(0, $totalTersedia - $totalTerkirim);
  $adaBelum      = $sj->details->where('status','terjadwal')->count();
@endphp
<div class="card" style="margin-bottom:20px;overflow:hidden">

  {{-- Header SJ --}}
  <div style="padding:12px 18px;background:var(--bg);border-bottom:1px solid var(--border);
              display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <div>
      <span style="font-family:monospace;font-size:15px;font-weight:700;color:var(--accent)">{{ $sj->no_sj }}</span>
      <span style="font-size:12px;color:var(--muted);margin-left:10px">
        SA#{{ $sj->kitirDetail?->kitir?->nomor_sa }} · {{ $sj->kitirDetail?->kitir?->spbe?->nama_spbe }}
      </span>
    </div>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:12px;color:var(--muted)">
      <span>🚛 <strong style="color:var(--text)">{{ $sj->armada?->no_polisi }}</strong></span>
      <span>👤 {{ $sj->sopir?->nama_karyawan }}</span>
      <span>📦 DO: <strong>{{ number_format($sj->qty_refil) }}</strong></span>
      @if(($sj->qty_gendongan_masuk??0)>0)
        <span style="color:#F59E0B">⚡+{{ $sj->qty_gendongan_masuk }}</span>
      @endif
      @if(($sj->qty_ambil_gudang??0)>0)
        <span style="color:#7C3AED">🏪+{{ $sj->qty_ambil_gudang }}</span>
      @endif
      <span style="font-weight:700;color:var(--text)">= {{ number_format($totalTersedia) }} total</span>
      <span style="padding:3px 10px;border-radius:99px;font-weight:600;
                   {{ $pct>=100?'background:#D1FAE5;color:#065F46':'background:#DBEAFE;color:#1E40AF' }}">
        {{ $pct }}% terkirim
      </span>
    </div>
  </div>

  {{-- Konfirmasi sumber (gendongan & gudang) --}}
  @php
    $gendonganAktif = \App\Models\StokArmada::where('armada_id',$sj->armada_id)
        ->where('sisa_akhir','>',0)->where('sj_header_id','!=',$sj->id)->sum('sisa_akhir');
    $gudangIsi      = \App\Models\GudangStok::where('agen_id',\App\Models\Agen::profil()?->id??0)
        ->where('sisa_stok','>',0)->sum('sisa_stok');
  @endphp
  @if($gendonganAktif > 0 && ($sj->qty_gendongan_masuk??0) == 0)
  <div style="padding:10px 18px;background:#FFFBEB;border-bottom:1px solid #FCD34D;
              display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <span style="font-size:12px;color:#92400E;font-weight:600">
      ⚡ Masih ada gendongan: <strong>{{ number_format($gendonganAktif) }} tb</strong>
    </span>
    <form action="{{ route('dashboard.agen.distribusi.konfirmasi-gendongan') }}" method="POST"
          style="display:flex;gap:6px;align-items:center">
      @csrf
      <input type="hidden" name="sj_header_id" value="{{ $sj->id }}">
      <input type="hidden" name="stok_armada_id"
             value="{{ \App\Models\StokArmada::where('armada_id',$sj->armada_id)->where('sisa_akhir','>',0)->where('sj_header_id','!=',$sj->id)->first()?->id }}">
      <input type="number" name="qty_gendongan_masuk" value="{{ $gendonganAktif }}"
             min="1" max="{{ $gendonganAktif }}"
             style="width:70px;border:1px solid #FCD34D;border-radius:6px;padding:4px 8px;
                    font-size:13px;font-weight:700;text-align:center;background:#fff;outline:none">
      <button type="submit"
              style="background:#D97706;color:#fff;border:none;border-radius:6px;
                     padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer">
        Masukkan ke Trip
      </button>
    </form>
    <span style="font-size:11px;color:#92400E">atau</span>
    <form action="{{ route('dashboard.agen.distribusi.turun-gudang') }}" method="POST"
          style="display:flex;gap:6px;align-items:center">
      @csrf
      <input type="hidden" name="armada_id" value="{{ $sj->armada_id }}">
      <button type="submit"
              style="border:1px solid #D97706;color:#D97706;background:none;border-radius:6px;
                     padding:5px 12px;font-size:12px;cursor:pointer">
        Turun ke Gudang Dulu
      </button>
    </form>
  </div>
  @endif

  {{-- Progress bar --}}
  <div style="height:4px;background:var(--border)">
    <div style="height:4px;background:{{ $pct>=100?'#059669':'var(--accent)' }};
                width:{{ min(100,$pct) }}%;transition:width .3s"></div>
  </div>

  {{-- ═══ TABEL REALISASI FLEKSIBEL ═══ --}}
  <form action="{{ route('dashboard.agen.distribusi.simpan-realisasi', $sj->id) }}"
        method="POST" id="form-realisasi-{{ $sj->id }}">
    @csrf

    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--bg)">
          <th style="text-align:left;padding:9px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;width:30px">#</th>
          <th style="text-align:left;padding:9px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">Pangkalan</th>
          <th style="text-align:right;padding:9px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">Jadwal</th>
          <th style="text-align:center;padding:9px 14px;font-size:10px;font-weight:600;color:var(--accent);text-transform:uppercase">Realisasi</th>
          <th style="text-align:left;padding:9px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">Status</th>
        </tr>
      </thead>
      <tbody id="tbody-{{ $sj->id }}">
        {{-- Pangkalan dari jadwal --}}
        @foreach($sj->details->sortBy('urutan') as $d)
        @php
          $st = match($d->status) {
            'terkirim' => ['#D1FAE5','#065F46','✓ Terkirim'],
            'sebagian' => ['#DBEAFE','#1E40AF','◑ Sebagian'],
            'batal'    => ['#FEE2E2','#991B1B','✗ Batal'],
            default    => ['#FEF3C7','#92400E','Terjadwal'],
          };
        @endphp
        <tr style="border-top:1px solid var(--border)" data-row>
          <td style="padding:9px 14px;color:var(--muted)">{{ $d->urutan }}</td>
          <td style="padding:9px 14px">
            <input type="hidden" name="pangkalan_id[]" value="{{ $d->pangkalan_id }}">
            <span style="font-weight:600;color:var(--text)">{{ $d->pangkalan?->nama_pangkalan }}</span>
            <span style="display:block;font-size:10px;font-family:monospace;color:var(--muted)">{{ $d->pangkalan?->no_reg }}</span>
          </td>
          <td style="padding:9px 14px;text-align:right;color:var(--muted)">{{ number_format($d->qty_jadwal) }}</td>
          <td style="padding:9px 14px;text-align:center">
            <input type="number" name="qty_terima[]" value="{{ $d->qty_terima }}"
                   min="0" placeholder="0"
                   style="width:80px;border:1px solid var(--border);background:var(--surface);
                          color:var(--text);border-radius:8px;padding:6px 8px;font-size:15px;
                          font-weight:700;text-align:center;outline:none"
                   oninput="hitungTotal('{{ $sj->id }}', {{ $totalTersedia }})">
          </td>
          <td style="padding:9px 14px">
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;
                         background:{{ $st[0] }};color:{{ $st[1] }}">
              {{ $st[2] }}
            </span>
          </td>
        </tr>
        @endforeach

        {{-- Baris tambahan (pangkalan di luar jadwal) --}}
        <tr id="extra-rows-{{ $sj->id }}"></tr>
      </tbody>

      <tfoot>
        <tr>
          <td colspan="5" style="padding:8px 14px">
            <button type="button"
                    onclick="tambahPangkalan('{{ $sj->id }}')"
                    style="background:none;border:1px dashed var(--border);color:var(--muted);
                           border-radius:8px;padding:6px 14px;font-size:12px;cursor:pointer;
                           width:100%">
              + Tambah Pangkalan di Luar Jadwal
            </button>
          </td>
        </tr>
        <tr style="border-top:2px solid var(--border);background:var(--bg)">
          <td colspan="2" style="padding:10px 14px;font-size:12px;color:var(--muted);font-weight:600">
            TOTAL · Tersedia: <strong style="color:var(--text)">{{ number_format($totalTersedia) }}</strong>
          </td>
          <td style="padding:10px 14px;text-align:right;font-weight:700;color:var(--muted)">
            {{ number_format($sj->details->sum('qty_jadwal')) }}
          </td>
          <td style="padding:10px 14px;text-align:center">
            <strong id="total-terima-{{ $sj->id }}" style="font-size:16px;color:#059669">
              {{ number_format($totalTerkirim) }}
            </strong>
          </td>
          <td style="padding:10px 14px">
            Sisa: <strong id="sisa-label-{{ $sj->id }}"
                          style="color:{{ $sisaTrip>0?'#F59E0B':'#059669' }}">
              {{ number_format($sisaTrip) }}
            </strong> tb
          </td>
        </tr>
      </tfoot>
    </table>

    {{-- Nasib sisa trip --}}
    <div id="sisa-panel-{{ $sj->id }}"
         style="{{ $adaBelum==0?'':'display:none;' }}padding:14px 18px;
                border-top:1px dashed var(--border);background:rgba(245,158,11,.04)">

      @if($adaBelum > 0)
      <div style="font-size:12px;color:#92400E;padding:10px 18px;background:#FFFBEB;
                  border-radius:8px;border:1px solid #FCD34D;margin-bottom:12px">
        ⏳ <strong>{{ $adaBelum }}</strong> pangkalan jadwal belum diisi — isi dulu atau biarkan 0 jika tidak ambil
      </div>
      @endif

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <p style="font-size:12px;font-weight:700;color:#92400E">
          Sisa <span id="sisa-info-{{ $sj->id }}">{{ $sisaTrip }}</span> tabung — nasibnya:
        </p>
        <span style="font-size:11px;color:var(--muted)">Total harus = sisa</span>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <span style="font-size:13px;font-weight:600;color:var(--text)">⚡ Tetap di armada</span>
          <input type="number" name="sisa_armada" id="sisa-armada-{{ $sj->id }}"
                 min="0" value="{{ $sisaTrip }}"
                 style="width:80px;border:1px solid #FCD34D;border-radius:8px;
                        padding:7px 8px;font-size:14px;font-weight:700;text-align:center;
                        background:var(--surface);color:var(--text);outline:none"
                 oninput="cekSisa('{{ $sj->id }}', {{ $totalTersedia }})">
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <span style="font-size:13px;font-weight:600;color:var(--text)">🏪 Turun gudang</span>
          <input type="number" name="sisa_gudang" id="sisa-gudang-{{ $sj->id }}"
                 min="0" value="0"
                 style="width:80px;border:1px solid #DDD6FE;border-radius:8px;
                        padding:7px 8px;font-size:14px;font-weight:700;text-align:center;
                        background:var(--surface);color:var(--text);outline:none"
                 oninput="cekSisa('{{ $sj->id }}', {{ $totalTersedia }})">
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <span style="font-size:13px;font-weight:600;color:var(--text)">🤝 Titip agen lain</span>
          <input type="number" name="sisa_agen_lain" id="sisa-agenlain-{{ $sj->id }}"
                 min="0" value="0"
                 style="width:80px;border:1px solid #A7F3D0;border-radius:8px;
                        padding:7px 8px;font-size:14px;font-weight:700;text-align:center;
                        background:var(--surface);color:var(--text);outline:none"
                 oninput="cekSisa('{{ $sj->id }}', {{ $totalTersedia }})">
        </label>
      </div>

      <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center">
        <p id="info-cek-{{ $sj->id }}" style="font-size:12px;color:var(--muted)"></p>
        <button type="submit" id="btn-simpan-{{ $sj->id }}"
                style="background:#059669;color:#fff;border:none;border-radius:8px;
                       padding:10px 24px;font-size:14px;font-weight:700;cursor:pointer"
                {{ $adaBelum > 0 ? '' : '' }}>
          🏁 Simpan &amp; Tutup Trip
        </button>
      </div>
    </div>

  </form>
</div>
@empty
<div class="card" style="padding:48px;text-align:center;color:var(--muted)">
  <p style="font-size:32px;margin-bottom:8px">📦</p>
  <p style="font-size:15px;font-weight:600;color:var(--text)">
    Tidak ada SJ aktif pada {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}
  </p>
</div>
@endforelse

{{-- Hidden template untuk baris tambahan --}}
<template id="tpl-baris-extra">
  <tr data-row style="border-top:1px solid var(--border);background:rgba(14,165,233,.03)">
    <td style="padding:9px 14px;color:var(--muted);font-size:11px">+</td>
    <td style="padding:9px 14px">
      <select name="pangkalan_id[]"
              style="width:100%;border:1px solid var(--border);background:var(--surface);
                     color:var(--text);border-radius:8px;padding:7px 10px;font-size:13px;outline:none">
        <option value="">— Pilih Pangkalan —</option>
        @foreach($pangkalans as $p)
          <option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>
        @endforeach
      </select>
    </td>
    <td style="padding:9px 14px;text-align:right;color:#0EA5E9;font-size:11px;font-weight:600">
      di luar jadwal
    </td>
    <td style="padding:9px 14px;text-align:center">
      <input type="number" name="qty_terima[]" min="0" placeholder="0" value="0"
             style="width:80px;border:1px solid var(--border);background:var(--surface);
                    color:var(--text);border-radius:8px;padding:6px 8px;font-size:15px;
                    font-weight:700;text-align:center;outline:none"
             oninput="hitungTotal(this.closest('form').id.replace('form-realisasi-',''), 0)">
    </td>
    <td style="padding:9px 14px">
      <button type="button" onclick="this.closest('tr').remove()"
              style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:18px">×</button>
    </td>
  </tr>
</template>

@endsection
@push('scripts')
<style>
.flabel{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
.finput{width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;box-sizing:border-box}
</style>
<script>
// Hitung total terkirim + update sisa real-time
function hitungTotal(sjId, totalTersedia) {
  const form   = document.getElementById('form-realisasi-' + sjId);
  if (!form) return;
  const inputs = form.querySelectorAll('input[name="qty_terima[]"]');
  let total = 0;
  inputs.forEach(el => total += parseInt(el.value || 0));

  document.getElementById('total-terima-' + sjId).textContent = total.toLocaleString('id');
  const sisa = Math.max(0, totalTersedia - total);
  const sisaEl = document.getElementById('sisa-label-' + sjId);
  if (sisaEl) {
    sisaEl.textContent = sisa.toLocaleString('id') + ' tb';
    sisaEl.style.color = sisa > 0 ? '#F59E0B' : '#059669';
  }

  // Sync default armada = sisa
  const armadaEl = document.getElementById('sisa-armada-' + sjId);
  if (armadaEl) {
    armadaEl.value = sisa;
    document.getElementById('sisa-info-' + sjId).textContent = sisa;
  }
  cekSisa(sjId, totalTersedia);
}

// Cek apakah nasib sisa sudah balance
function cekSisa(sjId, totalTersedia) {
  const form = document.getElementById('form-realisasi-' + sjId);
  if (!form) return;

  // Total terkirim
  let terkirim = 0;
  form.querySelectorAll('input[name="qty_terima[]"]').forEach(el =>
    terkirim += parseInt(el.value || 0)
  );
  const sisa = Math.max(0, totalTersedia - terkirim);

  // Total sisa dialokasikan
  const armada  = parseInt(document.getElementById('sisa-armada-' + sjId)?.value  || 0);
  const gudang  = parseInt(document.getElementById('sisa-gudang-' + sjId)?.value  || 0);
  const agenlain= parseInt(document.getElementById('sisa-agenlain-' + sjId)?.value || 0);
  const total   = armada + gudang + agenlain;

  const info = document.getElementById('info-cek-' + sjId);
  const btn  = document.getElementById('btn-simpan-' + sjId);

  if (total === sisa) {
    if (info) { info.textContent = '✓ Siap disimpan'; info.style.color = '#059669'; }
    if (btn)  { btn.disabled = false; btn.style.opacity = '1'; }
  } else {
    const diff = sisa - total;
    if (info) {
      info.textContent = diff > 0
        ? `Sisa ${diff} tb belum ditentukan`
        : `Melebihi sisa ${Math.abs(diff)} tb`;
      info.style.color = '#DC2626';
    }
    if (btn) { btn.disabled = true; btn.style.opacity = '0.4'; }
  }

  // Update label sisa
  const sisaInfo = document.getElementById('sisa-info-' + sjId);
  if (sisaInfo) sisaInfo.textContent = sisa;
  const sisaLabel = document.getElementById('sisa-label-' + sjId);
  if (sisaLabel) { sisaLabel.textContent = sisa; sisaLabel.style.color = sisa > 0 ? '#F59E0B' : '#059669'; }
}

// Tambah baris pangkalan di luar jadwal
function tambahPangkalan(sjId) {
  const tpl   = document.getElementById('tpl-baris-extra');
  const clone = tpl.content.cloneNode(true);
  const tbody = document.getElementById('form-realisasi-' + sjId)
                        .querySelector('tbody');
  // Sisipkan sebelum baris extra-rows placeholder
  const placeholder = document.getElementById('extra-rows-' + sjId);
  tbody.insertBefore(clone, placeholder);
}

// Init semua SJ
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[id^=form-realisasi-]').forEach(form => {
    const sjId = form.id.replace('form-realisasi-','');
    // Panggil cekSisa awal agar tombol state benar
    const armadaEl = document.getElementById('sisa-armada-' + sjId);
    if (armadaEl) {
      // Ambil totalTersedia dari data-total jika ada, atau skip
      cekSisa(sjId, parseInt(form.dataset.total || 0));
    }
  });
});
</script>
@endpush
