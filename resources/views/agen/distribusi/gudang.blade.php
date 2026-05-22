@extends('layouts.app')
@section('title', 'Manajemen Gudang Tabung')
@section('content')

@php
  $alertKosong = $saldoKosong < 100;
@endphp

{{-- Header --}}
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Gudang Tabung</h1>
    <p style="font-size:12px;color:var(--muted)">
      Buffer kosong · Tabung isi · Kepemilikan & pinjaman
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button onclick="openModal('modal-beli')"
            style="background:var(--accent);color:#fff;border:none;border-radius:8px;
                   padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer">
      + Beli Tabung Baru
    </button>
    <button onclick="openModal('modal-opname')"
            style="border:1px solid var(--accent-2,#F97316);color:var(--accent-2,#F97316);
                   background:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">
      📋 Opname Fisik
    </button>
    <button onclick="openModal('modal-pinjam')"
            style="border:1px solid var(--border);color:var(--text);background:var(--surface);
                   border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">
      📄 Catat Pinjaman
    </button>
  </div>
</div>

{{-- Alert stok rendah --}}
@if($alertKosong)
<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;
            padding:12px 16px;margin-bottom:16px;font-size:13px;color:#991B1B;
            display:flex;align-items:center;gap:10px">
  <span style="font-size:20px">⚠️</span>
  <div>
    <strong>Stok tabung kosong (buffer) rendah!</strong>
    Saat ini hanya <strong>{{ number_format($saldoKosong) }} tabung</strong> —
    minimum yang direkomendasikan adalah 100 tabung.
    Segera beli tabung baru dari Pertamina.
  </div>
</div>
@endif

{{-- Alert pinjaman kadaluarsa --}}
@if($hampirKadaluarsa->isNotEmpty())
<div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;
            padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400E">
  ⚠ <strong>{{ $hampirKadaluarsa->count() }} surat perjanjian pinjaman</strong>
  akan kadaluarsa dalam 30 hari — perlu diperbarui.
</div>
@endif

{{-- 4 Stat Cards Utama --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">

  <div class="stat-card" style="border-left:4px solid {{ $alertKosong ? '#DC2626' : 'var(--accent)' }}">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em">
      Buffer Kosong
    </p>
    <p style="font-size:30px;font-weight:700;color:{{ $alertKosong ? '#DC2626' : 'var(--accent)' }};margin-top:4px;line-height:1">
      {{ number_format($saldoKosong) }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:3px">
      tabung kosong di gudang
      @if($alertKosong)<span style="color:#DC2626;font-weight:600"> ⚠ rendah</span>@endif
    </p>
  </div>

  <div class="stat-card" style="border-left:4px solid var(--stat-positive,#059669)">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em">
      Tabung Isi
    </p>
    <p style="font-size:30px;font-weight:700;color:var(--stat-positive,#059669);margin-top:4px;line-height:1">
      {{ number_format($saldoIsi) }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:3px">tabung isi di gudang</p>
  </div>

  <div class="stat-card" style="border-left:4px solid #F97316">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em">
      Di Armada
    </p>
    <p style="font-size:30px;font-weight:700;color:#F97316;margin-top:4px;line-height:1">
      {{ number_format($kosongDiArmada) }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:3px">
      tabung kosong sedang distribusi
    </p>
  </div>

  <div class="stat-card" style="border-left:4px solid var(--stat-warning,#F59E0B)">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em">
      Dipinjam
    </p>
    <p style="font-size:30px;font-weight:700;color:var(--stat-warning,#F59E0B);margin-top:4px;line-height:1">
      {{ number_format($totalPinjaman) }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:3px">
      tabung di {{ $pinjamanAktif->count() }} pihak
    </p>
  </div>

  <div class="stat-card" style="border-left:4px solid #8B5CF6">
    <p style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em">
      Total Kepemilikan
    </p>
    <p style="font-size:30px;font-weight:700;color:#8B5CF6;margin-top:4px;line-height:1">
      {{ number_format($totalKepemilikan) }}
    </p>
    <p style="font-size:11px;color:var(--muted);margin-top:3px">
      buffer + isi + pinjaman + armada
    </p>
  </div>

</div>

{{-- Visualisasi Kepemilikan --}}
<div class="card" style="padding:16px 18px;margin-bottom:16px">
  <p style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:10px">
    Distribusi Kepemilikan Tabung
  </p>
  @php
    $total      = max($totalKepemilikan, 1);
    $pctKosong  = round($saldoKosong      / $total * 100);
    $pctIsi     = round($saldoIsi         / $total * 100);
    $pctArmada  = round($kosongDiArmada   / $total * 100);
    $pctPinjam  = round($totalPinjaman    / $total * 100);
  @endphp
  <div style="height:24px;border-radius:6px;overflow:hidden;display:flex;background:var(--border)">
    @if($saldoKosong > 0)
    <div style="width:{{ $pctKosong }}%;background:var(--accent);display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;white-space:nowrap;padding:0 4px">
        @if($pctKosong > 8) Buffer {{ $pctKosong }}% @endif
      </span>
    </div>
    @endif
    @if($saldoIsi > 0)
    <div style="width:{{ $pctIsi }}%;background:var(--stat-positive,#059669);display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;white-space:nowrap;padding:0 4px">
        @if($pctIsi > 8) Isi {{ $pctIsi }}% @endif
      </span>
    </div>
    @endif
    @if($kosongDiArmada > 0)
    <div style="width:{{ $pctArmada }}%;background:#F97316;display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;white-space:nowrap;padding:0 4px">
        @if($pctArmada > 8) Armada {{ $pctArmada }}% @endif
      </span>
    </div>
    @endif
    @if($totalPinjaman > 0)
    <div style="width:{{ $pctPinjam }}%;background:var(--stat-warning,#F59E0B);display:flex;align-items:center;justify-content:center">
      <span style="font-size:10px;color:#fff;font-weight:600;white-space:nowrap;padding:0 4px">
        @if($pctPinjam > 8) Pinjam {{ $pctPinjam }}% @endif
      </span>
    </div>
    @endif
  </div>
  <div style="display:flex;gap:16px;margin-top:8px;font-size:11px;color:var(--muted);flex-wrap:wrap">
    <span>🔵 Buffer: {{ number_format($saldoKosong) }}</span>
    <span>🟢 Isi: {{ number_format($saldoIsi) }}</span>
    <span>🟠 Armada: {{ number_format($kosongDiArmada) }}</span>
    <span>🟡 Pinjaman: {{ number_format($totalPinjaman) }}</span>
    <span style="font-weight:700;color:var(--text)">= {{ number_format($totalKepemilikan) }} total</span>
  </div>
</div>

{{-- Tab: Mutasi | Pinjaman | Armada --}}
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:1px solid var(--border)">
  @foreach(['mutasi'=>'Mutasi Gudang','pinjaman'=>'Pinjaman Tabung','armada'=>'Stok Armada'] as $t=>$l)
  <button onclick="switchTab('{{ $t }}')" id="tab-{{ $t }}"
          style="background:none;border:none;border-bottom:{{ $t==='mutasi'?'2px solid var(--accent)':'2px solid transparent' }};
                 padding:8px 16px;font-size:13px;font-weight:{{ $t==='mutasi'?'600':'400' }};
                 color:{{ $t==='mutasi'?'var(--accent)':'var(--muted)' }};cursor:pointer">
    {{ $l }}
    @if($t==='pinjaman' && $pinjamanAktif->count() > 0)
      <span style="background:var(--stat-warning,#F59E0B);color:#fff;font-size:9px;
                   padding:1px 5px;border-radius:99px;margin-left:4px">
        {{ $pinjamanAktif->count() }}
      </span>
    @endif
  </button>
  @endforeach
</div>

{{-- TAB: Mutasi Gudang --}}
<div id="view-mutasi">
  {{-- Filter --}}
  <form method="GET" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
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
    <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer">Filter</button>

    {{-- Quick actions --}}
    <button type="button" onclick="openModal('modal-keluar-kosong')"
            style="border:1px solid var(--border);background:var(--surface);color:var(--text);
                   border-radius:8px;padding:7px 12px;font-size:12px;cursor:pointer;margin-left:auto">
      📤 Keluar Tabung Kosong
    </button>
    <button type="button" onclick="openModal('modal-masuk-isi')"
            style="border:1px solid var(--border);background:var(--surface);color:var(--text);
                   border-radius:8px;padding:7px 12px;font-size:12px;cursor:pointer">
      📥 Turun Tabung Isi
    </button>
  </form>

  <div class="card" style="overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--bg)">
          @foreach(['Tanggal','Jenis Tabung','Arah','Sumber / Tujuan','Qty','Keterangan'] as $h)
            <th style="text-align:left;padding:8px 14px;font-size:10px;font-weight:600;
                       color:var(--muted);text-transform:uppercase;letter-spacing:.08em">{{ $h }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse($mutasi as $m)
        @php
          $isIsi    = $m->tipe_tabung === 'isi';
          $isMasuk  = $m->jenis === 'masuk';
          $tabungColor = $isIsi ? 'var(--stat-positive,#059669)' : 'var(--accent)';
          $arahColor   = $isMasuk ? 'var(--stat-positive,#059669)' : 'var(--stat-negative,#DC2626)';
        @endphp
        <tr style="border-top:1px solid var(--border)">
          <td style="padding:8px 14px;color:var(--muted);white-space:nowrap">
            {{ \Carbon\Carbon::parse($m->tanggal)->format('d/m/Y') }}
          </td>
          <td style="padding:8px 14px">
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;
                         background:{{ $isIsi ? 'rgba(5,150,105,.12)' : 'rgba(14,165,233,.12)' }};
                         color:{{ $tabungColor }}">
              {{ $isIsi ? '🔵 Isi' : '⚪ Kosong' }}
            </span>
          </td>
          <td style="padding:8px 14px">
            <span style="font-weight:700;color:{{ $arahColor }}">
              {{ $isMasuk ? '↑ Masuk' : '↓ Keluar' }}
            </span>
          </td>
          <td style="padding:8px 14px;font-size:12px;color:var(--muted)">
            {{ $m->sumber ?? $m->tujuan ?? '—' }}
          </td>
          <td style="padding:8px 14px;text-align:right;font-weight:700;
                     color:{{ $arahColor }};font-size:15px">
            {{ $isMasuk ? '+' : '-' }}{{ number_format($m->qty) }}
          </td>
          <td style="padding:8px 14px;color:var(--text);font-size:12px">
            {{ $m->keterangan ?? '—' }}
          </td>
        </tr>
        @empty
        <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--muted)">
          Belum ada mutasi bulan ini
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- TAB: Pinjaman Tabung --}}
<div id="view-pinjaman" style="display:none">
  <div class="card" style="overflow:hidden">
    <div style="padding:12px 18px;border-bottom:1px solid var(--border);
                display:flex;justify-content:space-between;align-items:center">
      <h2 style="font-size:14px;font-weight:600;color:var(--text)">Daftar Pinjaman Aktif</h2>
      <button onclick="openModal('modal-pinjam')"
              style="background:var(--accent);color:#fff;border:none;border-radius:7px;
                     padding:6px 12px;font-size:12px;cursor:pointer">
        + Tambah Pinjaman
      </button>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--bg)">
          @foreach(['Pangkalan / Pihak','No Perjanjian','Pinjam','Kembali','Aktif','Berlaku s/d','Status',''] as $h)
            <th style="text-align:left;padding:8px 14px;font-size:10px;font-weight:600;
                       color:var(--muted);text-transform:uppercase;letter-spacing:.08em">{{ $h }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse($pinjamanAktif as $p)
        @php
          $kadaluarsa = \Carbon\Carbon::parse($p->tgl_berlaku_sampai)->isPast();
          $hampir     = \Carbon\Carbon::parse($p->tgl_berlaku_sampai)->diffInDays(now()) <= 30 && !$kadaluarsa;
          $scStatus   = $p->status === 'lunas'   ? 'background:#D1FAE5;color:#065F46'
                      : ($kadaluarsa             ? 'background:#FEE2E2;color:#991B1B'
                      : ($hampir                 ? 'background:#FEF3C7;color:#92400E'
                                                 : 'background:#DBEAFE;color:#1E40AF'));
          $labelStatus= $p->status === 'lunas' ? 'Lunas'
                      : ($kadaluarsa ? 'Kadaluarsa' : ($hampir ? 'Hampir kadaluarsa' : 'Aktif'));
        @endphp
        <tr style="border-top:1px solid var(--border)">
          <td style="padding:8px 14px">
            <span style="font-weight:600;color:var(--text)">
              {{ $p->nama_pangkalan ?? $p->nama_pihak ?? '—' }}
            </span>
            @if($p->no_reg)
              <span style="display:block;font-size:10px;font-family:monospace;color:var(--muted)">{{ $p->no_reg }}</span>
            @endif
          </td>
          <td style="padding:8px 14px;font-family:monospace;font-size:11px;color:var(--muted)">
            {{ $p->no_perjanjian ?? '—' }}
          </td>
          <td style="padding:8px 14px;text-align:right;font-weight:600">{{ number_format($p->qty_pinjam) }}</td>
          <td style="padding:8px 14px;text-align:right;color:var(--stat-positive,#059669)">
            {{ $p->qty_kembali > 0 ? number_format($p->qty_kembali) : '—' }}
          </td>
          <td style="padding:8px 14px;text-align:right;font-weight:700;color:var(--stat-warning,#F59E0B)">
            {{ number_format($p->qty_aktif) }}
          </td>
          <td style="padding:8px 14px;font-size:12px;color:{{ $kadaluarsa ? '#DC2626' : 'var(--muted)' }}">
            {{ \Carbon\Carbon::parse($p->tgl_berlaku_sampai)->format('d/m/Y') }}
          </td>
          <td style="padding:8px 14px">
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $scStatus }}">
              {{ $labelStatus }}
            </span>
          </td>
          <td style="padding:8px 14px">
            @if($p->status === 'aktif' && $p->qty_aktif > 0)
            <button onclick="bukaKembali({{ $p->id }}, '{{ addslashes($p->nama_pangkalan ?? $p->nama_pihak) }}', {{ $p->qty_aktif }})"
                    style="background:none;border:1px solid var(--accent);color:var(--accent);
                           border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
              Kembali
            </button>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="8" style="padding:48px;text-align:center;color:var(--muted)">
          Belum ada pinjaman tabung aktif
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- TAB: Stok Armada --}}
<div id="view-armada" style="display:none">

  {{-- Alert armada belum dapat alokasi tabung --}}
  @if($armadaBelumAlokasi->isNotEmpty())
  <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;
              padding:12px 16px;margin-bottom:14px;font-size:13px;color:#991B1B">
    ⚠ <strong>{{ $armadaBelumAlokasi->count() }} armada belum mendapat alokasi tabung:</strong>
    @foreach($armadaBelumAlokasi as $ab)
      <span style="font-family:monospace;font-weight:600;margin-left:6px">{{ $ab->no_polisi }}</span>
    @endforeach
    — klik <strong>Alokasi Tabung ke Armada</strong> di bawah.
  </div>
  @endif

  {{-- Alokasi tabung per armada (permanen) --}}
  <div class="card" style="overflow:hidden;margin-bottom:14px">
    <div style="padding:12px 18px;border-bottom:1px solid var(--border);
                display:flex;justify-content:space-between;align-items:center">
      <div>
        <h2 style="font-size:14px;font-weight:600;color:var(--text)">Alokasi Tabung Kosong per Armada</h2>
        <p style="font-size:12px;color:var(--muted);margin-top:2px">Tabung kosong yang ditugaskan permanen ke armada untuk ambil DO</p>
      </div>
      <button onclick="openModal('modal-alokasi')"
              style="background:var(--accent);color:#fff;border:none;border-radius:8px;
                     padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer">
        + Alokasi Tabung ke Armada
      </button>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--bg)">
          @foreach(['Armada','Tahun','Layak s/d','Alokasi Tabung','Sisa di Armada','Status Masa Pakai',''] as $h)
            <th style="text-align:left;padding:8px 14px;font-size:10px;font-weight:600;
                       color:var(--muted);text-transform:uppercase;letter-spacing:.08em">{{ $h }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse($alokasiArmada as $al)
        @php
          $layakSampai  = $al->layak_sampai ? \Carbon\Carbon::parse($al->layak_sampai) : null;
          $sudahHabis   = $layakSampai && $layakSampai->isPast();
          $hampirHabis  = $layakSampai && !$sudahHabis && $layakSampai->diffInMonths(now()) <= 6;
          $sisaTahun    = $layakSampai ? now()->diffInYears($layakSampai, false) : null;
          $scLayak      = $sudahHabis  ? 'background:#FEE2E2;color:#991B1B'
                        : ($hampirHabis ? 'background:#FEF3C7;color:#92400E'
                                       : 'background:#D1FAE5;color:#065F46');
          $labelLayak   = $sudahHabis  ? '✗ Masa pakai habis'
                        : ($hampirHabis ? "⚠ Sisa ~{$sisaTahun}th"
                                       : "✓ Layak ({$sisaTahun}th lagi)");
        @endphp
        <tr style="border-top:1px solid var(--border);{{ $sudahHabis ? 'background:rgba(220,38,38,.03)' : '' }}">
          <td style="padding:9px 14px;font-weight:700;color:var(--text);font-family:monospace">
            {{ $al->no_polisi }}
          </td>
          <td style="padding:9px 14px;color:var(--muted)">{{ $al->tahun_pembuatan ?? '—' }}</td>
          <td style="padding:9px 14px;color:{{ $sudahHabis ? '#DC2626' : ($hampirHabis ? '#F59E0B' : 'var(--muted)') }}">
            {{ $layakSampai ? $layakSampai->format('d/m/Y') : '—' }}
          </td>
          <td style="padding:9px 14px;text-align:right;font-weight:700;color:var(--accent)">
            {{ number_format($al->total_masuk) }}
          </td>
          <td style="padding:9px 14px;text-align:right">
            <span style="font-size:18px;font-weight:700;color:var(--text)">{{ number_format($al->saldo_tabung) }}</span>
            <span style="font-size:11px;color:var(--muted)"> tb</span>
          </td>
          <td style="padding:9px 14px">
            <span style="padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500;{{ $scLayak }}">
              {{ $labelLayak }}
            </span>
          </td>
          <td style="padding:9px 14px">
            @if($sudahHabis)
            <button onclick="bukaKembalikanArmada({{ $al->armada_id }}, '{{ $al->no_polisi }}', {{ $al->saldo_tabung }})"
                    style="background:none;border:1px solid #DC2626;color:#DC2626;
                           border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
              Kembalikan
            </button>
            @else
            <button onclick="bukaKembalikanArmada({{ $al->armada_id }}, '{{ $al->no_polisi }}', {{ $al->saldo_tabung }})"
                    style="background:none;border:1px solid var(--border);color:var(--muted);
                           border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer">
              Kembalikan
            </button>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7" style="padding:32px;text-align:center;color:var(--muted)">
          Belum ada alokasi tabung ke armada — klik "+ Alokasi Tabung ke Armada"
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Gendongan aktif (tabung ISI di armada trip hari ini) --}}
  <div class="card" style="overflow:hidden">
    <div style="padding:12px 18px;border-bottom:1px solid var(--border)">
      <h2 style="font-size:14px;font-weight:600;color:var(--text)">Gendongan Aktif (Tabung Isi di Trip Hari Ini)</h2>
    </div>
    @if($stokArmada->isEmpty())
    <div style="padding:32px;text-align:center;color:var(--muted)">Tidak ada armada yang sedang jalan</div>
    @else
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--bg)">
          @foreach(['Armada','No SJ','Tanggal','Sisa Gendongan (Isi)'] as $h)
            <th style="text-align:left;padding:8px 14px;font-size:10px;font-weight:600;
                       color:var(--muted);text-transform:uppercase;letter-spacing:.08em">{{ $h }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($stokArmada as $sa)
        <tr style="border-top:1px solid var(--border)">
          <td style="padding:9px 14px;font-weight:700;color:var(--text);font-family:monospace">{{ $sa->no_polisi }}</td>
          <td style="padding:9px 14px;font-family:monospace;font-size:11px;color:var(--accent)">{{ $sa->no_sj }}</td>
          <td style="padding:9px 14px;color:var(--muted)">{{ \Carbon\Carbon::parse($sa->tanggal)->format('d/m/Y') }}</td>
          <td style="padding:9px 14px;text-align:right">
            <span style="font-size:20px;font-weight:700;color:var(--stat-warning,#F59E0B)">
              {{ number_format($sa->sisa_akhir) }}
            </span>
            <span style="font-size:11px;color:var(--muted);margin-left:4px">tabung isi</span>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>

{{-- ==================== MODALS ==================== --}}

{{-- Modal: Beli Tabung Baru --}}
<div id="modal-beli" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-beli')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:440px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Beli Tabung Kosong dari Pertamina</h3>
      <button onclick="closeModal('modal-beli')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.gudang.beli') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div><label class="flabel">Tanggal Beli *</label><input type="date" name="tgl_beli" value="{{ now()->toDateString() }}" required class="finput"></div>
        <div><label class="flabel">Jumlah Tabung *</label><input type="number" name="qty" required min="1" class="finput" placeholder="0"></div>
        <div><label class="flabel">Harga/Tabung (Rp)</label><input type="number" name="harga_per_tabung" min="0" class="finput" placeholder="0"></div>
        <div><label class="flabel">No. Faktur</label><input type="text" name="no_faktur" class="finput" placeholder="FAK-001"></div>
        <div style="grid-column:span 2"><label class="flabel">Keterangan</label><input type="text" name="keterangan" class="finput" placeholder="Catatan tambahan"></div>
      </div>
      <button type="submit" style="width:100%;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">Simpan Pembelian</button>
    </form>
  </div>
</div>

{{-- Modal: Keluar Tabung Kosong --}}
<div id="modal-keluar-kosong" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-keluar-kosong')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:420px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Keluar Tabung Kosong</h3>
        <p style="font-size:12px;color:var(--muted);margin-top:2px">Tersedia: <strong>{{ number_format($saldoKosong) }}</strong> tabung</p>
      </div>
      <button onclick="closeModal('modal-keluar-kosong')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.gudang.keluar-kosong') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div><label class="flabel">Tanggal *</label><input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput"></div>
        <div><label class="flabel">Jumlah *</label><input type="number" name="qty" required min="1" max="{{ $saldoKosong }}" class="finput" placeholder="0"></div>
        <div style="grid-column:span 2">
          <label class="flabel">Tujuan *</label>
          <select name="tujuan" class="finput">
            <option value="ke_armada">Ke Armada (ambil DO)</option>
            <option value="penyesuaian">Penyesuaian</option>
          </select>
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Armada</label>
          <select name="armada_id" class="finput">
            <option value="">— Pilih armada —</option>
            @foreach(\App\Models\Armada::orderBy('no_polisi')->get() as $arm)
              <option value="{{ $arm->id }}">{{ $arm->no_polisi }}</option>
            @endforeach
          </select>
        </div>
        <div style="grid-column:span 2"><label class="flabel">Keterangan *</label><input type="text" name="keterangan" required class="finput" placeholder="Contoh: Armada R 8040 MR ambil DO SA-2718786"></div>
      </div>
      <button type="submit" style="width:100%;background:#DC2626;color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">Catat Keluar</button>
    </form>
  </div>
</div>

{{-- Modal: Masuk Tabung Isi --}}
<div id="modal-masuk-isi" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-masuk-isi')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:420px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Turun Tabung Isi ke Gudang</h3>
      <button onclick="closeModal('modal-masuk-isi')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.gudang.masuk-isi') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div><label class="flabel">Tanggal *</label><input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput"></div>
        <div><label class="flabel">Jumlah Tabung Isi *</label><input type="number" name="qty" required min="1" class="finput" placeholder="0"></div>
        <div style="grid-column:span 2">
          <label class="flabel">Dari Armada</label>
          <select name="armada_id" class="finput">
            <option value="">— Pilih armada —</option>
            @foreach(\App\Models\Armada::orderBy('no_polisi')->get() as $arm)
              <option value="{{ $arm->id }}">{{ $arm->no_polisi }}</option>
            @endforeach
          </select>
        </div>
        <div style="grid-column:span 2"><label class="flabel">Keterangan</label><input type="text" name="keterangan" class="finput" placeholder="Gendongan turun dari armada R 8040 MR"></div>
      </div>
      <button type="submit" style="width:100%;background:var(--stat-positive,#059669);color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">Catat Masuk</button>
    </form>
  </div>
</div>

{{-- Modal: Alokasi Tabung ke Armada --}}
<div id="modal-alokasi" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-alokasi')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:480px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Alokasi Tabung ke Armada</h3>
        <p style="font-size:12px;color:var(--muted);margin-top:2px">
          Buffer tersedia: <strong>{{ number_format($saldoKosong) }}</strong> tabung kosong
        </p>
      </div>
      <button onclick="closeModal('modal-alokasi')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.gudang.alokasi-armada') }}" method="POST" style="padding:18px 20px">
      @csrf

      {{-- Info standar --}}
      <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px;color:var(--muted);line-height:1.6">
        📋 Standar: <strong>560 tabung kosong per armada</strong> untuk 1x DO penuh.<br>
        Tabung akan tercatat sebagai aset armada selama masa pakai (10 tahun dari tahun pembuatan).<br>
        Setelah masa pakai habis, tabung dikembalikan ke buffer untuk armada baru.
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div style="grid-column:span 2">
          <label class="flabel">Armada *</label>
          <select name="armada_id" id="sel-armada-alokasi" class="finput" onchange="updateInfoArmada()">
            <option value="">— Pilih armada —</option>
            @foreach(\App\Models\Armada::where('is_active',true)->orderBy('no_polisi')->get() as $arm)
            <option value="{{ $arm->id }}"
                    data-tahun="{{ $arm->tahun_pembuatan }}"
                    data-layak="{{ $arm->layak_sampai }}">
              {{ $arm->no_polisi }}
              @if($arm->tahun_pembuatan) ({{ $arm->tahun_pembuatan }}) @endif
            </option>
            @endforeach
          </select>
          <div id="info-armada-alokasi" style="margin-top:6px;font-size:11px;color:var(--muted);display:none">
            <span id="txt-layak"></span>
          </div>
        </div>
        <div>
          <label class="flabel">Tanggal Alokasi *</label>
          <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput">
        </div>
        <div>
          <label class="flabel">Jumlah Tabung *</label>
          <input type="number" name="qty" required min="1" max="{{ $saldoKosong }}"
                 value="560" class="finput" placeholder="560">
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Keterangan</label>
          <input type="text" name="keterangan" class="finput"
                 placeholder="Alokasi tabung pertama untuk armada baru">
        </div>
      </div>
      <button type="submit"
              style="width:100%;background:var(--accent);color:#fff;border:none;
                     border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Alokasikan Tabung ke Armada
      </button>
    </form>
  </div>
</div>

{{-- Modal: Kembalikan Tabung dari Armada --}}
<div id="modal-kembalikan-armada" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-kembalikan-armada')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:420px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Kembalikan Tabung ke Buffer</h3>
        <p id="txt-armada-kembali" style="font-size:12px;color:var(--muted);margin-top:2px">—</p>
      </div>
      <button onclick="closeModal('modal-kembalikan-armada')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-kembalikan-armada" method="POST" style="padding:18px 20px">
      @csrf
      <div style="background:#FEF2F2;border-radius:8px;padding:10px;margin-bottom:14px;font-size:12px;color:#991B1B">
        ⚠ Biasanya dilakukan saat armada pensiun, rusak permanen, atau masa pakai tabung habis (10 tahun).
        Tabung akan kembali ke buffer gudang dan bisa dialokasikan ke armada baru.
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div>
          <label class="flabel">Tanggal *</label>
          <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput">
        </div>
        <div>
          <label class="flabel">Jumlah *</label>
          <input type="number" name="qty" id="inp-qty-kembali-armada" required min="1" class="finput">
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Alasan *</label>
          <input type="text" name="keterangan" required class="finput"
                 placeholder="Contoh: Armada pensiun, masa pakai 10 tahun habis">
        </div>
      </div>
      <button type="submit"
              onclick="return confirm('Kembalikan tabung ke buffer? Pastikan armada sudah tidak beroperasi.')"
              style="width:100%;background:#DC2626;color:#fff;border:none;
                     border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Kembalikan ke Buffer Gudang
      </button>
    </form>
  </div>
</div>

{{-- Modal: Catat Pinjaman --}}
<div id="modal-pinjam" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-pinjam')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:480px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:700;color:var(--text)">Catat Pinjaman Tabung</h3>
      <button onclick="closeModal('modal-pinjam')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.gudang.pinjam') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div style="grid-column:span 2">
          <label class="flabel">Pihak *</label>
          <select name="pihak" id="sel-pihak" onchange="updatePihak()" class="finput">
            <option value="pangkalan">Pangkalan</option>
            <option value="cabang">Cabang Lain</option>
          </select>
        </div>
        <div id="field-pangkalan" style="grid-column:span 2">
          <label class="flabel">Pangkalan *</label>
          <select name="pangkalan_id" class="finput">
            @foreach(\App\Models\Pangkalan::aktif()->orderBy('nama_pangkalan')->get() as $pk)
              <option value="{{ $pk->id }}">{{ $pk->nama_pangkalan }}</option>
            @endforeach
          </select>
        </div>
        <div id="field-cabang" style="grid-column:span 2;display:none">
          <label class="flabel">Nama Cabang *</label>
          <input type="text" name="nama_pihak" class="finput" placeholder="Nama agen/cabang lain">
        </div>
        <div><label class="flabel">Tgl Pinjam *</label><input type="date" name="tgl_pinjam" value="{{ now()->toDateString() }}" required class="finput"></div>
        <div><label class="flabel">Berlaku Sampai *</label><input type="date" name="tgl_berlaku_sampai" value="{{ now()->addYear()->toDateString() }}" required class="finput"></div>
        <div><label class="flabel">Jumlah Tabung *</label><input type="number" name="qty_pinjam" required min="1" max="{{ $saldoKosong }}" class="finput" placeholder="0"></div>
        <div><label class="flabel">No. Perjanjian</label><input type="text" name="no_perjanjian" class="finput" placeholder="PKS-2026-001"></div>
        <div style="grid-column:span 2"><label class="flabel">Keterangan</label><input type="text" name="keterangan" class="finput"></div>
      </div>
      <button type="submit" style="width:100%;background:var(--stat-warning,#F59E0B);color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Simpan Perjanjian Pinjaman
      </button>
    </form>
  </div>
</div>

{{-- Modal: Opname Fisik --}}
<div id="modal-opname" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-opname')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:440px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Opname Stok Fisik</h3>
        <p style="font-size:12px;color:var(--muted);margin-top:2px">
          Sistem: Kosong {{ number_format($saldoKosong) }} · Isi {{ number_format($saldoIsi) }}
        </p>
      </div>
      <button onclick="closeModal('modal-opname')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form action="{{ route('dashboard.agen.distribusi.gudang.opname') }}" method="POST" style="padding:18px 20px">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div>
          <label class="flabel">Stok Fisik Kosong *</label>
          <input type="number" name="stok_fisik_kosong" required min="0" class="finput"
                 placeholder="{{ $saldoKosong }}" id="inp-fisik-kosong" oninput="hitungSelisih()">
          <p id="info-kosong" style="font-size:10px;color:var(--muted);margin-top:3px"></p>
        </div>
        <div>
          <label class="flabel">Stok Fisik Isi *</label>
          <input type="number" name="stok_fisik_isi" required min="0" class="finput"
                 placeholder="{{ $saldoIsi }}" id="inp-fisik-isi" oninput="hitungSelisih()">
          <p id="info-isi" style="font-size:10px;color:var(--muted);margin-top:3px"></p>
        </div>
        <div style="grid-column:span 2">
          <label class="flabel">Keterangan *</label>
          <input type="text" name="keterangan" required class="finput" placeholder="Opname bulanan rutin">
        </div>
      </div>
      <button type="submit"
              onclick="return confirm('Simpan hasil opname? Penyesuaian tidak bisa dibatalkan.')"
              style="width:100%;background:var(--accent-2,#F97316);color:#fff;border:none;
                     border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Simpan Opname
      </button>
    </form>
  </div>
</div>

{{-- Modal: Pengembalian Pinjaman --}}
<div id="modal-kembali" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;z-index:300;padding:16px"
     onclick="if(event.target===this)closeModal('modal-kembali')">
  <div style="background:var(--surface);border-radius:14px;width:100%;max-width:400px" onclick="event.stopPropagation()">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
      <div>
        <h3 style="font-size:15px;font-weight:700;color:var(--text)">Catat Pengembalian</h3>
        <p id="kembali-nama" style="font-size:12px;color:var(--muted);margin-top:2px">—</p>
      </div>
      <button onclick="closeModal('modal-kembali')" style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">×</button>
    </div>
    <form id="form-kembali" method="POST" style="padding:18px 20px">
      @csrf
      <div style="background:var(--bg);border-radius:8px;padding:10px 14px;margin-bottom:12px;text-align:center">
        <p style="font-size:10px;color:var(--muted)">Maksimal kembali</p>
        <p id="kembali-max" style="font-size:24px;font-weight:700;color:var(--text)">0</p>
        <p style="font-size:11px;color:var(--muted)">tabung</p>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div><label class="flabel">Tanggal *</label><input type="date" name="tanggal" value="{{ now()->toDateString() }}" required class="finput"></div>
        <div><label class="flabel">Jumlah *</label><input type="number" name="qty" required min="1" id="inp-kembali" class="finput" placeholder="0"></div>
        <div style="grid-column:span 2"><label class="flabel">Keterangan</label><input type="text" name="keterangan" class="finput"></div>
      </div>
      <button type="submit" style="width:100%;background:var(--stat-positive,#059669);color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer">
        Simpan Pengembalian
      </button>
    </form>
  </div>
</div>

@endsection
@push('scripts')
<script>
function openModal(id)  { document.getElementById(id).style.display='flex'; document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    ['modal-beli','modal-keluar-kosong','modal-masuk-isi','modal-pinjam','modal-opname','modal-kembali']
      .forEach(closeModal);
  }
});

function switchTab(t) {
  ['mutasi','pinjaman','armada'].forEach(id => {
    document.getElementById('view-'+id).style.display = id===t ? 'block' : 'none';
    const tab = document.getElementById('tab-'+id);
    tab.style.borderBottomColor = id===t ? 'var(--accent)' : 'transparent';
    tab.style.color              = id===t ? 'var(--accent)' : 'var(--muted)';
    tab.style.fontWeight         = id===t ? '600' : '400';
  });
}

function bukaKembali(id, nama, maxQty) {
  document.getElementById('form-kembali').action = `/dashboard/agen/distribusi/gudang/pinjam/${id}/kembali`;
  document.getElementById('kembali-nama').textContent = nama;
  document.getElementById('kembali-max').textContent  = maxQty.toLocaleString('id');
  document.getElementById('inp-kembali').max = maxQty;
  openModal('modal-kembali');
}

function updatePihak() {
  const cabang = document.getElementById('sel-pihak').value === 'cabang';
  document.getElementById('field-pangkalan').style.display = cabang ? 'none' : 'block';
  document.getElementById('field-cabang').style.display    = cabang ? 'block' : 'none';
}

const saldoKosong = {{ $saldoKosong }};
const saldoIsi    = {{ $saldoIsi }};
function updateInfoArmada() {
  const sel    = document.getElementById('sel-armada-alokasi');
  const opt    = sel.options[sel.selectedIndex];
  const info   = document.getElementById('info-armada-alokasi');
  const txtL   = document.getElementById('txt-layak');
  if (!opt.value) { info.style.display='none'; return; }
  const tahun  = opt.dataset.tahun;
  const layak  = opt.dataset.layak;
  if (tahun && layak) {
    const layakDate = new Date(layak);
    const now       = new Date();
    const diffYears = Math.floor((layakDate - now) / (1000*60*60*24*365));
    const sudah     = layakDate < now;
    txtL.textContent = sudah
      ? `⚠ Tahun ${tahun} — masa pakai SUDAH HABIS (${layak})`
      : `✓ Tahun ${tahun} — layak hingga ${layak} (${diffYears} tahun lagi)`;
    txtL.style.color = sudah ? '#DC2626' : '#059669';
    info.style.display = 'block';
  }
}

function bukaKembalikanArmada(armadaId, noPolisi, maxQty) {
  document.getElementById('form-kembalikan-armada').action =
    `/dashboard/agen/distribusi/gudang/armada/${armadaId}/kembalikan`;
  document.getElementById('txt-armada-kembali').textContent =
    `${noPolisi} · Saldo tabung: ${maxQty.toLocaleString('id')} tabung`;
  document.getElementById('inp-qty-kembali-armada').max   = maxQty;
  document.getElementById('inp-qty-kembali-armada').value = maxQty;
  openModal('modal-kembalikan-armada');
}

function hitungSelisih() {
  const fisikK   = parseInt(document.getElementById('inp-fisik-kosong').value) || saldoKosong;
  const fisikI   = parseInt(document.getElementById('inp-fisik-isi').value) || saldoIsi;
  const selK     = fisikK - saldoKosong;
  const selI     = fisikI - saldoIsi;
  const infoK    = document.getElementById('info-kosong');
  const infoI    = document.getElementById('info-isi');
  infoK.textContent = selK === 0 ? '✓ Sama' : (selK > 0 ? `+${selK} (lebih)` : `${selK} (kurang)`);
  infoK.style.color = selK === 0 ? 'var(--stat-positive,#059669)' : selK > 0 ? '#3B82F6' : '#DC2626';
  infoI.textContent = selI === 0 ? '✓ Sama' : (selI > 0 ? `+${selI} (lebih)` : `${selI} (kurang)`);
  infoI.style.color = selI === 0 ? 'var(--stat-positive,#059669)' : selI > 0 ? '#3B82F6' : '#DC2626';
}
</script>
@endpush
