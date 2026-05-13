@extends('layouts.app')
@section('title', 'Realisasi Distribusi')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Realisasi Distribusi</h1>
    <p style="font-size:12px;color:var(--muted)">Input realisasi pengiriman ke pangkalan</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    {{-- Info stok aktif --}}
    @if($stokGudang > 0)
    <span style="background:#EDE9FE;color:#5B21B6;border-radius:99px;padding:4px 12px;font-size:12px;font-weight:600">
      🏪 Gudang: {{ number_format($stokGudang) }} tb
    </span>
    @endif
    @foreach($stokArmada as $armadaId => $stoks)
      @php $totalGnd = $stoks->sum('sisa_akhir'); @endphp
      @if($totalGnd > 0)
      <span style="background:#FEF3C7;color:#92400E;border-radius:99px;padding:4px 12px;font-size:12px;font-weight:600">
        ⚡ Gendongan {{ $stoks->first()->armada?->no_polisi }}: {{ number_format($totalGnd) }} tb
      </span>
      @endif
    @endforeach
    <a href="{{ route('dashboard.agen.distribusi.laporan') }}"
       style="border:1px solid var(--border);color:var(--text);background:var(--surface);border-radius:8px;padding:7px 14px;font-size:13px;text-decoration:none">
      📊 Laporan
    </a>
  </div>
</div>

{{-- Filter tanggal --}}
<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;align-items:center;flex-wrap:wrap">
  <input type="date" name="tanggal" value="{{ $tanggal }}"
         style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none">
  <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;cursor:pointer">Tampilkan</button>
  @if($tanggal !== now()->toDateString())
    <a href="{{ route('dashboard.agen.distribusi.index') }}" style="border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-size:13px;color:var(--muted);text-decoration:none">Hari Ini</a>
  @endif
</form>

@forelse($sjHariIni as $sj)
@php
  $totalJadwal  = $sj->details->sum('qty_jadwal');
  $totalTerima  = $sj->details->sum('qty_terima');
  $pct          = $totalJadwal > 0 ? round($totalTerima/$totalJadwal*100) : 0;
  $belum        = $sj->details->where('status','terjadwal')->count();
@endphp
<div class="card" style="margin-bottom:20px;overflow:hidden">

  {{-- Header SJ --}}
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;background:var(--bg)">
    <div>
      <span style="font-family:monospace;font-size:15px;font-weight:700;color:var(--accent)">{{ $sj->no_sj }}</span>
      <span style="font-size:12px;color:var(--muted);margin-left:10px">
        SA# {{ $sj->kitirDetail?->kitir?->nomor_sa }} · {{ $sj->kitirDetail?->kitir?->spbe?->nama_spbe }}
      </span>
    </div>
    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;font-size:12px;color:var(--muted)">
      <span>🚛 <strong style="color:var(--text)">{{ $sj->armada?->no_polisi }}</strong></span>
      <span>👤 {{ $sj->sopir?->nama_karyawan }}</span>
      <span>📦 <strong style="color:var(--text)">{{ number_format($sj->qty_refil) }}</strong> tb DO</span>
      @if(($sj->qty_gendongan_masuk ?? 0) > 0)
        <span style="color:#F59E0B">⚡ +{{ number_format($sj->qty_gendongan_masuk) }} gendongan</span>
      @endif
      @if(($sj->qty_ambil_gudang ?? 0) > 0)
        <span style="color:#7C3AED">🏪 +{{ number_format($sj->qty_ambil_gudang) }} gudang</span>
      @endif
      <span style="padding:3px 10px;border-radius:99px;font-weight:600;{{ $pct==100?'background:#D1FAE5;color:#065F46':'background:#DBEAFE;color:#1E40AF' }}">
        {{ $pct }}% terkirim
      </span>
    </div>
  </div>

  {{-- Gendongan & Gudang info + quick action --}}
  @php
    $gendonganAktif = \App\Models\StokArmada::where('armada_id', $sj->armada_id)
        ->where('sisa_akhir', '>', 0)
        ->where('sj_header_id', '!=', $sj->id)
        ->sum('sisa_akhir');
    $gudangTersedia = \App\Models\GudangStok::where('agen_id', \App\Models\Agen::profil()?->id ?? 0)
        ->where('sisa_stok', '>', 0)->sum('sisa_stok');
    $gndMasukSudah = $sj->qty_gendongan_masuk ?? 0;
    $gudangSudah   = $sj->qty_ambil_gudang ?? 0;
  @endphp
  @if($gendonganAktif > 0 || $gudangTersedia > 0 || $gndMasukSudah > 0 || $gudangSudah > 0)
  <div style="padding:10px 18px;background:#FFFBEB;border-bottom:1px solid #FCD34D;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    @if($gendonganAktif > 0 && $gndMasukSudah == 0)
    {{-- Ada gendongan belum dimasukkan --}}
    <form action="{{ route('dashboard.agen.distribusi.konfirmasi-gendongan') }}" method="POST" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      @csrf
      <input type="hidden" name="sj_header_id" value="{{ $sj->id }}">
      <input type="hidden" name="stok_armada_id"
             value="{{ \App\Models\StokArmada::where('armada_id',$sj->armada_id)->where('sisa_akhir','>',0)->where('sj_header_id','!=',$sj->id)->first()?->id }}">
      <span style="font-size:12px;color:#92400E;font-weight:600">⚡ Gendongan {{ $sj->armada?->no_polisi }}: <strong>{{ number_format($gendonganAktif) }} tb</strong></span>
      <input type="number" name="qty_gendongan_masuk" value="{{ $gendonganAktif }}" min="1" max="{{ $gendonganAktif }}"
             style="width:70px;border:1px solid #FCD34D;border-radius:6px;padding:4px 8px;font-size:13px;font-weight:700;text-align:center;background:#fff;outline:none">
      <button type="submit" style="background:#D97706;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer">
        Masukkan ke SJ
      </button>
    </form>
    @elseif($gndMasukSudah > 0)
    <span style="font-size:12px;color:#065F46;font-weight:600">✓ Gendongan masuk: {{ number_format($gndMasukSudah) }} tb</span>
    @endif

    @if($gudangTersedia > 0 && $gudangSudah == 0)
    {{-- Ada stok gudang --}}
    <form action="{{ route('dashboard.agen.distribusi.ambil-gudang') }}" method="POST" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      @csrf
      <input type="hidden" name="sj_header_id" value="{{ $sj->id }}">
      <span style="font-size:12px;color:#5B21B6;font-weight:600">🏪 Stok Gudang: <strong>{{ number_format($gudangTersedia) }} tb</strong></span>
      <input type="number" name="qty" min="1" max="{{ $gudangTersedia }}" placeholder="qty"
             style="width:70px;border:1px solid #DDD6FE;border-radius:6px;padding:4px 8px;font-size:13px;font-weight:700;text-align:center;background:#fff;outline:none">
      <button type="submit" style="background:#7C3AED;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer">
        Ambil
      </button>
    </form>
    @elseif($gudangSudah > 0)
    <span style="font-size:12px;color:#5B21B6;font-weight:600">✓ Ambil gudang: {{ number_format($gudangSudah) }} tb</span>
    @endif
  </div>
  @endif

  {{-- Progress bar --}}
  <div style="height:4px;background:var(--border)">
    <div style="height:4px;background:{{ $pct==100?'#059669':'#2563EB' }};width:{{ $pct }}%"></div>
  </div>

  {{-- Tabel pangkalan --}}
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['#','Pangkalan','Jadwal','Tambahan','Terima','Sisa & Nasib','Status',''] as $h)
          <th style="text-align:left;padding:9px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($sj->details->sortBy('urutan') as $d)
      @php
        $sisa = $d->qty_jadwal - $d->qty_terima;
        $ds = match($d->status) {
          'terjadwal' => ['background:#FEF3C7;color:#92400E','Terjadwal'],
          'terkirim'  => ['background:#D1FAE5;color:#065F46','Terkirim ✓'],
          'sebagian'  => ['background:#DBEAFE;color:#1E40AF','Sebagian'],
          'dialihkan' => ['background:#EDE9FE;color:#5B21B6','Dialihkan'],
          'batal'     => ['background:#FEE2E2;color:#991B1B','Batal'],
          default     => ['','—'],
        };
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;color:var(--muted);width:32px">{{ $d->urutan }}</td>
        <td style="padding:11px 14px">
          <span style="font-weight:600;color:var(--text)">{{ $d->pangkalan?->nama_pangkalan }}</span>
          <span style="display:block;font-size:11px;color:var(--muted);font-family:monospace">{{ $d->pangkalan?->no_reg }}</span>
        </td>
        <td style="padding:11px 14px;text-align:right;font-weight:700">{{ number_format($d->qty_jadwal) }}</td>
        <td style="padding:11px 14px;font-size:12px">
          @if($d->tambahan->isNotEmpty())
            @foreach($d->tambahan as $tb)
            <div style="display:flex;align-items:center;gap:4px;margin-bottom:2px">
              <span style="background:#DBEAFE;color:#1E40AF;padding:1px 7px;border-radius:99px;font-weight:700;font-size:12px">
                +{{ $tb->qty }}
              </span>
              <span style="font-size:10px;color:var(--muted)">
                dari {{ $tb->sumberDetail?->pangkalan?->nama_pangkalan ?? '—' }}
              </span>
            </div>
            @endforeach
            <div style="font-size:10px;color:#059669;font-weight:600;margin-top:3px">
              Max terima: {{ number_format($d->qty_maks) }} tb
            </div>
          @else
            <span style="color:var(--muted)">—</span>
          @endif
        </td>
        <td style="padding:11px 14px;text-align:right;font-weight:700;color:{{ $d->qty_terima>0?'#059669':'var(--muted)' }}">
          {{ $d->qty_terima > 0 ? number_format($d->qty_terima) : '—' }}
        </td>
        <td style="padding:11px 14px;min-width:180px">
          @if($d->status !== 'terjadwal' && $sisa > 0)
            {{-- Tampilkan nasib sisa per baris --}}
            @foreach($d->sisaDistribusi as $s)
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">
              @php $tipeLabel = \App\Models\SjSisaDistribusi::TIPE_LABEL[$s->tipe] ?? $s->tipe;
                   $tipeColor = match($s->tipe) {
                     'alih_pangkalan'  => '#1E40AF',
                     'stok_armada'     => '#92400E',
                     'gudang_sendiri'  => '#5B21B6',
                     'titip_agen_lain' => '#065F46',
                     default           => 'var(--muted)',
                   };
              @endphp
              <span style="font-size:11px;font-weight:600;color:{{ $tipeColor }}">{{ $s->qty }} tb</span>
              <span style="font-size:10px;color:var(--muted)">{{ $tipeLabel }}</span>
              @if($s->tipe === 'alih_pangkalan')
                <span style="font-size:10px;color:var(--muted)">→ {{ ($alihMap[$s->referensi_id] ?? null)?->pangkalan?->nama_pangkalan }}</span>
              @endif
            </div>
            @endforeach
          @elseif($d->status === 'terkirim')
            <span style="font-size:11px;color:#059669">✓ Lunas</span>
          @else
            <span style="color:var(--muted)">—</span>
          @endif
        </td>
        <td style="padding:11px 14px">
          <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $ds[0] }}">{{ $ds[1] }}</span>
        </td>
        <td style="padding:11px 14px;white-space:nowrap">
          @if($d->status === 'terjadwal')
          <button onclick="bukaModal({{ Js::from($d) }}, {{ $d->qty_jadwal }}, {{ Js::from($d->pangkalan?->nama_pangkalan) }}, {{ $d->qty_maks ?? $d->qty_jadwal }})"
                  style="background:var(--accent);color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer;font-weight:500">
            ✓ Input
          </button>
          @else
          <button onclick="bukaModal({{ Js::from($d) }}, {{ $d->qty_jadwal }}, {{ Js::from($d->pangkalan?->nama_pangkalan) }}, {{ $d->qty_maks ?? $d->qty_jadwal }})"
                  style="background:none;border:1px solid var(--border);color:var(--text);border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer">
            Edit
          </button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="border-top:2px solid var(--border);background:var(--bg)">
        <td colspan="2" style="padding:9px 14px;font-size:12px;color:var(--muted);font-weight:600">TOTAL</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700">{{ number_format($sj->details->sum('qty_jadwal')) }}</td>
        <td style="padding:9px 14px;text-align:right;font-weight:700;color:#059669">{{ number_format($sj->details->sum('qty_terima')) }}</td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
  </table>

  {{-- Footer status trip --}}
  @if($belum > 0)
  <div style="padding:10px 18px;background:#FEF9C3;font-size:12px;color:#92400E;display:flex;align-items:center;gap:6px">
    ⏳ <strong>{{ $belum }}</strong> pangkalan belum dilaporkan
  </div>
  @else
  <div style="padding:10px 18px;background:#D1FAE5;font-size:12px;color:#065F46;display:flex;align-items:center;gap:6px">
    ✓ Semua pangkalan sudah dilaporkan
  </div>
  @endif
</div>
@empty
<div class="card" style="padding:48px;text-align:center;color:var(--muted)">
  <p style="font-size:32px;margin-bottom:8px">📦</p>
  <p style="font-size:15px;font-weight:600;color:var(--text)">Tidak ada SJ aktif pada {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</p>
</div>
@endforelse

{{-- ═══════════════════════════════════════════════════════════════
     MODAL INPUT REALISASI
══════════════════════════════════════════════════════════════════ --}}
<div id="modal-realisasi" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:flex-start;justify-content:center;z-index:300;padding:16px;overflow-y:auto" onclick="closeModal()">
<div style="background:var(--surface);border-radius:16px;width:100%;max-width:560px;margin:0 auto" onclick="event.stopPropagation()">

  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
    <div>
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Input Realisasi</h3>
      <p id="m-nama" style="font-size:12px;color:var(--muted)">—</p>
    </div>
    <button onclick="closeModal()" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
  </div>

  <form id="form-realisasi" method="POST" style="padding:18px 20px">
    @csrf @method('PUT')

    {{-- Info angka --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:var(--bg);border-radius:10px;padding:10px;text-align:center">
        <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;margin-bottom:2px">Jadwal</p>
        <p id="m-jadwal" style="font-size:22px;font-weight:700;color:var(--text)">0</p>
        <p id="m-label-maks" style="display:none;font-size:10px;color:#1E40AF;margin-top:2px;font-weight:600"></p>
      </div>
      <div style="background:var(--bg);border-radius:10px;padding:10px;text-align:center">
        <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;margin-bottom:2px">Terima</p>
        <p id="m-terima" style="font-size:22px;font-weight:700;color:#059669">0</p>
      </div>
      <div style="background:var(--bg);border-radius:10px;padding:10px;text-align:center">
        <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;margin-bottom:2px">Sisa</p>
        <p id="m-sisa" style="font-size:22px;font-weight:700;color:#F59E0B">0</p>
      </div>
    </div>

    {{-- Input qty terima --}}
    <div style="margin-bottom:14px">
      <label class="flabel">Qty Diterima *</label>
      <div style="display:flex;gap:8px;align-items:center">
        <input name="qty_terima" id="m-qty-terima" type="number" min="0" required class="finput"
               style="font-size:22px;font-weight:700;text-align:center;padding:10px" oninput="hitungSisa()">
        <div style="display:flex;flex-direction:column;gap:4px">
          <button type="button" onclick="setFull()" style="background:#059669;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer">Full ✓</button>
          <button type="button" onclick="setNol()"  style="background:#EF4444;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer">0 ✗</button>
        </div>
      </div>
      <p id="m-info-maks" style="display:none;font-size:11px;color:#1E40AF;margin-top:4px;font-weight:600"></p>
      <p id="m-warn-melebihi" style="display:none;font-size:12px;color:#DC2626;margin-top:6px;font-weight:600;padding:8px 10px;background:#FEE2E2;border-radius:6px;line-height:1.5"></p>
    </div>

    {{-- Keterangan --}}
    <div style="margin-bottom:14px">
      <label class="flabel">Keterangan</label>
      <input name="keterangan" id="m-keterangan" class="finput" placeholder="Catatan opsional">
    </div>

    {{-- ── SECTION SISA ──────────────────────────────────────── --}}
    <div id="sisa-section" style="display:none;margin-bottom:14px">
      <div style="border:1.5px solid #FCD34D;border-radius:12px;overflow:hidden;background:#FFFBEB">

        {{-- Header --}}
        <div style="padding:10px 14px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed #FCD34D">
          <span style="font-size:12px;font-weight:700;color:#92400E">
            ⚠ Sisa <span id="label-sisa">0</span> tabung — ke mana?
          </span>
          <span id="label-total-alokasi" style="font-size:12px;font-weight:700;color:#059669"></span>
        </div>

        {{-- 4 Pilihan --}}
        <div style="padding:12px 14px;display:flex;flex-direction:column;gap:6px">

          {{-- 1. Pangkalan Lain --}}
          <label id="opt-alih" style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 10px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);transition:border-color .15s"
                 onclick="pilihanKlik('alih')">
            <input type="checkbox" id="chk-alih" style="width:16px;height:16px;accent-color:#1D4ED8;flex-shrink:0;cursor:pointer">
            <span style="font-size:13px;font-weight:600;color:var(--text)">↗ Pangkalan lain</span>
          </label>

          {{-- Sub-section alih --}}
          <div id="sub-alih" style="display:none;padding:10px 12px;background:rgba(37,99,235,.05);border-radius:8px;border:1px solid #BFDBFE;margin-bottom:2px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <span style="font-size:11px;font-weight:600;color:#1E40AF">Daftar Pengalihan</span>
              <button type="button" onclick="tambahBarisPangkalan()"
                      style="background:#1D4ED8;color:#fff;border:none;border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
                + Tambah
              </button>
            </div>
            <div id="container-alih"></div>
            <div style="font-size:11px;color:#1E40AF;margin-top:4px">
              Total dialihkan: <strong id="label-total-alih">0</strong> tabung
            </div>
          </div>

          {{-- 2. Tetap di Armada --}}
          <label id="opt-armada" style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 10px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface)"
                 onclick="pilihanKlik('armada')">
            <input type="checkbox" id="chk-armada" style="width:16px;height:16px;accent-color:#D97706;flex-shrink:0;cursor:pointer">
            <div style="flex:1;display:flex;justify-content:space-between;align-items:center">
              <span style="font-size:13px;font-weight:600;color:var(--text)">⚡ Tetap di armada <span style="font-weight:400;font-size:11px;color:var(--muted)">(gendongan)</span></span>
              <input type="number" name="sisa[armada][qty]" id="qty-armada" min="0" value="0"
                     onclick="event.stopPropagation()"
                     oninput="updateTotalAlokasi()"
                     style="display:none;width:64px;border:1px solid #FCD34D;border-radius:6px;padding:4px 6px;font-size:13px;font-weight:700;text-align:center;background:var(--surface);color:var(--text);outline:none">
            </div>
          </label>
          <input type="hidden" name="sisa[armada][tipe]" value="stok_armada">

          {{-- 3. Gudang sendiri --}}
          <label id="opt-gudang" style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 10px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface)"
                 onclick="pilihanKlik('gudang')">
            <input type="checkbox" id="chk-gudang" style="width:16px;height:16px;accent-color:#7C3AED;flex-shrink:0;cursor:pointer">
            <div style="flex:1;display:flex;justify-content:space-between;align-items:center">
              <span style="font-size:13px;font-weight:600;color:var(--text)">🏪 Simpan di gudang <span style="font-weight:400;font-size:11px;color:var(--muted)">(sendiri)</span></span>
              <input type="number" name="sisa[gudang][qty]" id="qty-gudang" min="0" value="0"
                     onclick="event.stopPropagation()"
                     oninput="updateTotalAlokasi()"
                     style="display:none;width:64px;border:1px solid #DDD6FE;border-radius:6px;padding:4px 6px;font-size:13px;font-weight:700;text-align:center;background:var(--surface);color:var(--text);outline:none">
            </div>
          </label>
          <input type="hidden" name="sisa[gudang][tipe]" value="gudang_sendiri">

          {{-- 4. Gudang Agen Lain --}}
          <label id="opt-agenLain" style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 10px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface)"
                 onclick="pilihanKlik('agenLain')">
            <input type="checkbox" id="chk-agenLain" style="width:16px;height:16px;accent-color:#059669;flex-shrink:0;cursor:pointer">
            <span style="font-size:13px;font-weight:600;color:var(--text)">🤝 Titip gudang agen lain</span>
          </label>

          {{-- Sub-section agen lain --}}
          <div id="sub-agenLain" style="display:none;padding:10px 12px;background:rgba(5,150,105,.05);border-radius:8px;border:1px solid #A7F3D0;margin-bottom:2px">
            <div style="display:grid;grid-template-columns:1fr 80px 80px;gap:8px;align-items:end">
              <div>
                <label style="font-size:10px;color:var(--muted);font-weight:600;display:block;margin-bottom:3px">Agen Tujuan</label>
                <select name="sisa[agen_lain][agen_tujuan_id]"
                        style="width:100%;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:6px;padding:6px 8px;font-size:12px;outline:none">
                  <option value="">-- Pilih Agen --</option>
                  @foreach($agenLain as $a)
                    <option value="{{ $a->id }}">{{ $a->nama_agen }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label style="font-size:10px;color:var(--muted);font-weight:600;display:block;margin-bottom:3px">Qty Titip</label>
                <input type="number" name="sisa[agen_lain][qty]" id="qty-agenLain" min="0" value="0"
                       oninput="updateTotalAlokasi()"
                       style="width:100%;border:1px solid #A7F3D0;border-radius:6px;padding:6px 8px;font-size:13px;font-weight:700;text-align:center;background:var(--surface);color:var(--text);outline:none;box-sizing:border-box">
              </div>
              <div>
                <label style="font-size:10px;color:var(--muted);font-weight:600;display:block;margin-bottom:3px">Pinjam Kosong</label>
                <input type="number" name="sisa[agen_lain][qty_kosong]" min="0" value="0"
                       style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 8px;font-size:13px;text-align:center;background:var(--surface);color:var(--text);outline:none;box-sizing:border-box">
              </div>
            </div>
            <input type="hidden" name="sisa[agen_lain][tipe]" value="titip_agen_lain">
            <p style="font-size:10px;color:#059669;margin-top:5px">Tabung isi masuk gudang agen tujuan. Catat tabung kosong yang dipinjam jika ada.</p>
          </div>

        </div>{{-- end pilihan --}}

        <p id="label-error-sisa" style="display:none;color:#DC2626;font-size:11px;padding:0 14px 10px;margin:0"></p>
      </div>
    </div>

    <button type="submit" id="btn-simpan"
            style="width:100%;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer">
      Simpan Realisasi
    </button>
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
let qtyJadwal = 0;
let qtyMaksGlobal = 0;
let barisPangkalanCount = 0;

const pangkalanOpts = `@foreach($pangkalans as $p)<option value="{{ $p->id }}">{{ $p->nama_pangkalan }} ({{ $p->no_reg }})</option>@endforeach`;

// ── Buka modal ──────────────────────────────────────────────────
function bukaModal(detail, jadwal, namaP, qtyMaks) {
  qtyJadwal      = jadwal;
  qtyMaksGlobal  = (qtyMaks && qtyMaks > 0) ? qtyMaks : jadwal;

  document.getElementById('form-realisasi').action =
    `/dashboard/agen/distribusi/detail/${detail.id}`;
  document.getElementById('m-nama').textContent   = namaP;
  document.getElementById('m-jadwal').textContent = jadwal.toLocaleString('id');

  // Set nilai awal: jika sudah pernah diisi pakai itu, default = jadwal (bukan maks)
  const nilaiAwal = detail.qty_terima > 0 ? detail.qty_terima : jadwal;
  const input = document.getElementById('m-qty-terima');
  input.value = nilaiAwal;
  input.max   = qtyMaksGlobal;

  document.getElementById('m-keterangan').value = detail.keterangan || '';

  // Tampilkan info maks di bawah angka Jadwal jika ada tambahan
  const labelMaks = document.getElementById('m-label-maks');
  const infoMaks  = document.getElementById('m-info-maks');
  if (qtyMaksGlobal > jadwal) {
    const tambahan = qtyMaksGlobal - jadwal;
    if (labelMaks) {
      labelMaks.textContent = `+ ${tambahan} tambahan = max ${qtyMaksGlobal}`;
      labelMaks.style.display = 'block';
    }
    if (infoMaks) {
      infoMaks.textContent = `Ada tambahan ${tambahan} tb → boleh terima hingga ${qtyMaksGlobal} tabung`;
      infoMaks.style.color = '#1E40AF';
      infoMaks.style.display = 'block';
    }
  } else {
    if (labelMaks) labelMaks.style.display = 'none';
    if (infoMaks)  infoMaks.style.display  = 'none';
  }

  resetSisa();
  hitungSisa();
  document.getElementById('modal-realisasi').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('modal-realisasi').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

// Full = qty_maks (jadwal + semua tambahan yang sudah dikonfirmasi)
function setFull() {
  document.getElementById('m-qty-terima').value = qtyMaksGlobal;
  hitungSisa();
}
function setNol() {
  document.getElementById('m-qty-terima').value = 0;
  hitungSisa();
}

// ── VALIDASI & HITUNG SISA ──────────────────────────────────────
function hitungSisa() {
  const input  = document.getElementById('m-qty-terima');
  const terima = parseInt(input.value || 0);
  const btn    = document.getElementById('btn-simpan');
  const warn   = document.getElementById('m-warn-melebihi');

  // Validasi real-time — blokir jika melebihi maks
  if (terima > qtyMaksGlobal) {
    const lebih = terima - qtyJadwal;
    if (warn) {
      warn.textContent = `⚠ Melebihi jadwal ${qtyJadwal} sebesar ${lebih} tabung!`
        + (qtyMaksGlobal > qtyJadwal
          ? ` (sudah ada tambahan ${qtyMaksGlobal - qtyJadwal} tb, max ${qtyMaksGlobal})`
          : ` Tambahkan sumber kelebihan dulu (gendongan / gudang / pengalihan masuk).`);
      warn.style.display = 'block';
    }
    input.style.borderColor = '#DC2626';
    btn.disabled      = true;
    btn.style.opacity = '0.4';

    // Tetap hitung sisa untuk info saja (berdasar maks)
    const sisaDisplay = Math.max(0, qtyMaksGlobal - Math.min(terima, qtyMaksGlobal));
    document.getElementById('m-terima').textContent = terima.toLocaleString('id');
    document.getElementById('m-sisa').textContent   = '—';
    document.getElementById('m-sisa').style.color   = '#DC2626';
    document.getElementById('sisa-section').style.display = 'none';
    return; // stop processing
  }

  // Normal — tidak melebihi maks
  if (warn) warn.style.display = 'none';
  input.style.borderColor = '';
  btn.disabled      = false;
  btn.style.opacity = '1';

  const sisa = Math.max(0, qtyMaksGlobal - terima);
  document.getElementById('m-terima').textContent    = terima.toLocaleString('id');
  document.getElementById('m-sisa').textContent      = sisa.toLocaleString('id');
  document.getElementById('m-sisa').style.color      = sisa > 0 ? '#F59E0B' : '#059669';
  document.getElementById('label-sisa').textContent  = sisa.toLocaleString('id');
  document.getElementById('sisa-section').style.display = sisa > 0 ? 'block' : 'none';

  if (sisa === 0) {
    resetSisa();
  } else {
    updateAutoQty();
  }
  updateTotalAlokasi();
}

// ── Klik salah satu pilihan sisa ────────────────────────────────
function pilihanKlik(id) {
  const chk = document.getElementById('chk-' + id);
  // Toggle dilakukan browser setelah onclick, jadi kita delay
  setTimeout(() => {
    const aktif = chk.checked;
    const optEl = document.getElementById('opt-' + id);
    optEl.style.border = aktif ? '1.5px solid var(--accent)' : '1.5px solid var(--border)';

    // Tampilkan sub-section untuk alih dan agenLain
    if (id === 'alih') {
      document.getElementById('sub-alih').style.display = aktif ? 'block' : 'none';
      if (aktif && document.querySelectorAll('.baris-alih').length === 0)
        tambahBarisPangkalan();
    }
    if (id === 'agenLain') {
      document.getElementById('sub-agenLain').style.display = aktif ? 'block' : 'none';
    }

    // Tampilkan qty input untuk armada/gudang
    if (id === 'armada' || id === 'gudang') {
      const qtyEl = document.getElementById('qty-' + id);
      qtyEl.style.display = aktif ? 'inline-block' : 'none';
      if (!aktif) qtyEl.value = 0;
    }

    updateAutoQty();
    updateTotalAlokasi();
  }, 0);
}

// ── Auto-fill qty jika hanya 1 pilihan aktif ────────────────────
function updateAutoQty() {
  const sisa      = Math.max(0, qtyMaksGlobal - parseInt(document.getElementById('m-qty-terima').value || 0));
  const aktif     = getAktifPilihan();
  const totalAlih = hitungTotalAlih();

  // Jika satu pilihan non-alih aktif, auto-isi sisa - total alih
  if (aktif.length === 1 && aktif[0] !== 'alih') {
    const qtyEl = document.getElementById('qty-' + aktif[0]);
    if (qtyEl) qtyEl.value = Math.max(0, sisa - totalAlih);
  }
}

function getAktifPilihan() {
  return ['alih','armada','gudang','agenLain'].filter(id =>
    document.getElementById('chk-' + id)?.checked
  );
}

// ── Multi-baris pengalihan pangkalan ────────────────────────────
function tambahBarisPangkalan() {
  const sisa      = Math.max(0, qtyMaksGlobal - parseInt(document.getElementById('m-qty-terima').value || 0));
  const sudah     = hitungTotalAlih();
  const sisanya   = Math.max(0, sisa - sudah -
    parseInt(document.getElementById('qty-armada')?.value || 0) -
    parseInt(document.getElementById('qty-gudang')?.value || 0) -
    parseInt(document.getElementById('qty-agenLain')?.value || 0));
  const idx = barisPangkalanCount++;

  const row = document.createElement('div');
  row.className = 'baris-alih';
  row.style = 'display:grid;grid-template-columns:1fr 70px 26px;gap:6px;align-items:center;margin-bottom:6px';
  row.innerHTML = `
    <select name="sisa_alih[${idx}][pangkalan_id]"
            style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:6px;padding:6px 8px;font-size:12px;outline:none;width:100%">
      <option value="">-- Pilih Pangkalan --</option>${pangkalanOpts}
    </select>
    <div style="display:flex;align-items:center;gap:3px">
      <input type="number" name="sisa_alih[${idx}][qty]" value="${sisanya}" min="1"
             style="width:100%;border:1px solid #BFDBFE;border-radius:6px;padding:5px 6px;font-size:13px;font-weight:700;text-align:center;background:var(--surface);color:var(--text);outline:none"
             oninput="updateTotalAlokasi()">
    </div>
    <button type="button" onclick="hapusBarisPangkalan(this)"
            style="background:none;border:1px solid #FECACA;color:#DC2626;border-radius:5px;width:26px;height:26px;cursor:pointer;font-size:13px;line-height:1">×</button>
  `;
  document.getElementById('container-alih').appendChild(row);
  document.getElementById('label-total-alih').textContent = hitungTotalAlih();
  updateTotalAlokasi();
}

function hapusBarisPangkalan(btn) {
  btn.closest('.baris-alih').remove();
  document.getElementById('label-total-alih').textContent = hitungTotalAlih();
  updateTotalAlokasi();
}

function hitungTotalAlih() {
  let total = 0;
  document.querySelectorAll('input[name^="sisa_alih"][name$="[qty]"]')
    .forEach(i => total += parseInt(i.value || 0));
  document.getElementById('label-total-alih').textContent = total;
  return total;
}

// ── Hitung total semua alokasi sisa ─────────────────────────────
function hitungTotalAlokasi() {
  return hitungTotalAlih()
    + parseInt(document.getElementById('qty-armada')?.value || 0)
    + parseInt(document.getElementById('qty-gudang')?.value || 0)
    + parseInt(document.getElementById('qty-agenLain')?.value || 0);
}

function updateTotalAlokasi() {
  const sisa    = Math.max(0, qtyMaksGlobal - parseInt(document.getElementById('m-qty-terima').value || 0));
  const total   = hitungTotalAlokasi();
  const label   = document.getElementById('label-total-alokasi');
  const err     = document.getElementById('label-error-sisa');
  const btn     = document.getElementById('btn-simpan');

  if (sisa === 0) { label.textContent = ''; btn.disabled = false; btn.style.opacity = '1'; return; }

  label.textContent = `${total}/${sisa} tb`;
  label.style.color = total === sisa ? '#059669' : (total > sisa ? '#DC2626' : '#F59E0B');

  const ok = total === sisa;
  err.style.display   = ok ? 'none' : 'block';
  err.textContent     = ok ? '' : (total > sisa
    ? `Kelebihan ${total - sisa} tabung`
    : `Kurang ${sisa - total} tabung`);
  btn.disabled        = !ok;
  btn.style.opacity   = ok ? '1' : '0.5';
}

// ── Reset semua pilihan ─────────────────────────────────────────
function resetSisa() {
  ['alih','armada','gudang','agenLain'].forEach(id => {
    const chk = document.getElementById('chk-' + id);
    if (chk) chk.checked = false;
    const opt = document.getElementById('opt-' + id);
    if (opt) opt.style.border = '1.5px solid var(--border)';
  });
  document.getElementById('sub-alih').style.display     = 'none';
  document.getElementById('sub-agenLain').style.display  = 'none';
  document.getElementById('container-alih').innerHTML    = '';
  barisPangkalanCount = 0;

  const qtyArmada = document.getElementById('qty-armada');
  const qtyGudang = document.getElementById('qty-gudang');
  if (qtyArmada) { qtyArmada.value = 0; qtyArmada.style.display = 'none'; }
  if (qtyGudang) { qtyGudang.value = 0; qtyGudang.style.display = 'none'; }

  const qtyAgenLain = document.getElementById('qty-agenLain');
  if (qtyAgenLain) qtyAgenLain.value = 0;

  const err = document.getElementById('label-error-sisa');
  if (err) err.style.display = 'none';

  const btn = document.getElementById('btn-simpan');
  if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
}
</script>
@endpush
