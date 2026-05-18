@extends('layouts.app')
@section('title', 'Buku Besar')

@section('content')

@php
  $agen = \App\Models\Agen::first();
  $kelompokLabel = [
    'aset'       => 'ASET',
    'kewajiban'  => 'KEWAJIBAN',
    'modal'      => 'MODAL',
    'pendapatan' => 'PENDAPATAN',
    'beban'      => 'BEBAN',
  ];
  $kelompokColor = [
    'aset'       => '#3B82F6',
    'kewajiban'  => '#DC2626',
    'modal'      => '#059669',
    'pendapatan' => '#0891B2',
    'beban'      => '#F59E0B',
  ];
@endphp

{{-- Header + Filter --}}
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">Buku Besar</h1>
    <p style="font-size:12px;color:var(--muted)">Semua akun · {{ $bulanList[$bulan] }} {{ $tahun }}</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('dashboard.agen.akuntansi.buku-besar.export-pdf', ['bulan'=>$bulan,'tahun'=>$tahun,'akun'=>$kodeAkun]) }}"
       target="_blank"
       style="border:1px solid #DC2626;color:#DC2626;background:none;border-radius:8px;padding:7px 13px;font-size:12px;text-decoration:none;display:flex;align-items:center;gap:5px">
      📄 PDF
    </a>
    <a href="{{ route('dashboard.agen.akuntansi.buku-besar.export-excel', ['bulan'=>$bulan,'tahun'=>$tahun,'akun'=>$kodeAkun]) }}"
       style="border:1px solid #059669;color:#059669;background:none;border-radius:8px;padding:7px 13px;font-size:12px;text-decoration:none;display:flex;align-items:center;gap:5px">
      📊 Excel
    </a>
  </div>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
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
  <select name="akun" style="border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:8px;padding:7px 12px;font-size:13px;outline:none;min-width:200px">
    <option value="">Semua Akun</option>
    @foreach($akuns->groupBy('kelompok') as $kel => $list)
      <optgroup label="{{ strtoupper($kel) }}">
        @foreach($list as $a)
          <option value="{{ $a->kode }}" {{ $kodeAkun==$a->kode?'selected':'' }}>
            {{ $a->kode }} · {{ $a->nama }}
          </option>
        @endforeach
      </optgroup>
    @endforeach
  </select>
  <button type="submit" style="background:var(--accent);color:#151F28;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer">Tampilkan</button>
</form>

{{-- Kop identitas --}}
<div class="card" style="padding:14px 18px;margin-bottom:16px;border-left:3px solid var(--accent)">
  <p style="font-size:14px;font-weight:700;color:var(--text)">{{ $agen?->nama_agen ?? 'NAMA AGEN' }}</p>
  <p style="font-size:12px;color:var(--muted)">
    Kode: {{ $agen?->kode_agen ?? '—' }}
    @if($agen?->alamat_agen) · {{ $agen->alamat_agen }} @endif
    @if($agen?->telepon_agen) · Telp. {{ $agen->telepon_agen }} @endif
  </p>
  <p style="font-size:11px;color:var(--muted);margin-top:2px">
    Buku Besar Periode: <strong>{{ $bulanList[$bulan] }} {{ $tahun }}</strong>
  </p>
</div>

{{-- Tampilkan per kelompok akun --}}
@forelse($bukuBesar as $kel => $akunList)
<div style="margin-bottom:24px">

  {{-- Header kelompok --}}
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
    <div style="height:2px;flex:1;background:{{ $kelompokColor[$kel] ?? 'var(--border)' }};opacity:.3"></div>
    <span style="font-size:12px;font-weight:700;color:{{ $kelompokColor[$kel] ?? 'var(--muted)' }};
                 letter-spacing:.08em;text-transform:uppercase;padding:0 4px">
      {{ $kelompokLabel[$kel] ?? strtoupper($kel) }}
    </span>
    <div style="height:2px;flex:1;background:{{ $kelompokColor[$kel] ?? 'var(--border)' }};opacity:.3"></div>
  </div>

  @foreach($akunList as $akunData)
  @php
    $mutasi     = $akunData['mutasi'];
    $akun       = $akunData['akun'];
    $saldoAwal  = $akunData['saldo_awal'];
    $totalDebit = $mutasi->sum(fn($m) => $m->posisi === 'debit'  ? $m->jumlah : 0);
    $totalKredit= $mutasi->sum(fn($m) => $m->posisi === 'kredit' ? $m->jumlah : 0);
    $saldoAkhir = $mutasi->isNotEmpty()
        ? $mutasi->last()->saldo_running
        : $saldoAwal;
  @endphp

  @if($mutasi->isNotEmpty() || $saldoAwal != 0)
  <div class="card" style="overflow:hidden;margin-bottom:10px">
    {{-- Header akun --}}
    <div style="padding:10px 16px;background:rgba(255,255,255,.03);border-bottom:1px solid var(--border);
                display:flex;justify-content:space-between;align-items:center">
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-family:monospace;font-size:14px;font-weight:700;
                     color:{{ $kelompokColor[$kel] ?? 'var(--accent)' }}">{{ $akun->kode }}</span>
        <span style="font-size:13px;font-weight:600;color:var(--text)">{{ $akun->nama }}</span>
      </div>
      <div style="text-align:right">
        <span style="font-size:14px;font-weight:700;color:{{ $saldoAkhir>=0?'var(--text)':'#DC2626' }};font-family:monospace">
          Rp {{ number_format(abs($saldoAkhir)) }}
        </span>
        <span style="display:block;font-size:10px;color:var(--muted)">saldo akhir</span>
      </div>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr style="background:var(--bg)">
          <th style="text-align:left;padding:7px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;white-space:nowrap">Tanggal</th>
          <th style="text-align:left;padding:7px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">No Jurnal</th>
          <th style="text-align:left;padding:7px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">Keterangan</th>
          <th style="text-align:left;padding:7px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">Modul</th>
          <th style="text-align:right;padding:7px 14px;font-size:10px;font-weight:600;color:#3B82F6;text-transform:uppercase">Debit</th>
          <th style="text-align:right;padding:7px 14px;font-size:10px;font-weight:600;color:#DC2626;text-transform:uppercase">Kredit</th>
          <th style="text-align:right;padding:7px 14px;font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase">Saldo</th>
        </tr>
      </thead>
      <tbody>
        {{-- Saldo awal --}}
        <tr style="background:rgba(255,255,255,.02);border-bottom:1px solid var(--border)">
          <td colspan="4" style="padding:7px 14px;font-style:italic;color:var(--muted);font-size:11px">Saldo Awal Periode</td>
          <td colspan="2" style="padding:7px 14px"></td>
          <td style="padding:7px 14px;text-align:right;font-family:monospace;font-weight:600;color:var(--muted)">
            Rp {{ number_format($saldoAwal) }}
          </td>
        </tr>

        {{-- Mutasi --}}
        @foreach($mutasi as $m)
        @php
          $modColor = match($m->modul) {
            'modal_masuk','utang_pemilik' => '#059669',
            'prive'         => '#DC2626',
            'tebusan'       => '#F59E0B',
            'distribusi'    => '#3B82F6',
            'brimola'       => '#8B5CF6',
            'kerjasama'     => '#0891B2',
            'kas_kecil'     => '#6B7280',
            default         => 'var(--muted)',
          };
        @endphp
        <tr style="border-bottom:1px solid var(--border);{{ !$m->is_otomatis ? 'background:rgba(245,158,11,.03)' : '' }}">
          <td style="padding:7px 14px;color:var(--muted);white-space:nowrap">
            {{ \Carbon\Carbon::parse($m->tanggal)->format('d/m/Y') }}
          </td>
          <td style="padding:7px 14px;font-family:monospace;font-size:10px;color:var(--accent)">
            {{ $m->no_jurnal }}
          </td>
          <td style="padding:7px 14px;color:var(--text)">
            {{ Str::limit($m->ket_detail ?: $m->ket_header, 50) }}
            @if(!$m->is_otomatis)
              <span style="font-size:9px;color:#F59E0B;margin-left:3px">✎</span>
            @endif
          </td>
          <td style="padding:7px 14px">
            <span style="font-size:9px;font-weight:700;color:{{ $modColor }}">
              {{ strtoupper(str_replace('_',' ',$m->modul)) }}
            </span>
          </td>
          <td style="padding:7px 14px;text-align:right;font-family:monospace;color:#3B82F6">
            {{ $m->posisi==='debit' ? 'Rp '.number_format($m->jumlah) : '' }}
          </td>
          <td style="padding:7px 14px;text-align:right;font-family:monospace;color:#DC2626">
            {{ $m->posisi==='kredit' ? 'Rp '.number_format($m->jumlah) : '' }}
          </td>
          <td style="padding:7px 14px;text-align:right;font-family:monospace;font-weight:600;
                     color:{{ $m->saldo_running>=0?'var(--text)':'#DC2626' }}">
            Rp {{ number_format($m->saldo_running) }}
          </td>
        </tr>
        @endforeach

        {{-- Baris total --}}
        @if($mutasi->isNotEmpty())
        <tr style="background:rgba(255,255,255,.04);border-top:2px solid var(--border)">
          <td colspan="4" style="padding:8px 14px;font-weight:700;font-size:11px;color:var(--muted)">TOTAL MUTASI</td>
          <td style="padding:8px 14px;text-align:right;font-weight:700;font-family:monospace;font-size:11px;color:#3B82F6">
            Rp {{ number_format($totalDebit) }}
          </td>
          <td style="padding:8px 14px;text-align:right;font-weight:700;font-family:monospace;font-size:11px;color:#DC2626">
            Rp {{ number_format($totalKredit) }}
          </td>
          <td style="padding:8px 14px;text-align:right;font-weight:700;font-family:monospace;font-size:13px;color:var(--accent)">
            Rp {{ number_format($saldoAkhir) }}
          </td>
        </tr>
        @endif
      </tbody>
    </table>
  </div>
  @endif
  @endforeach
</div>
@empty
<div class="card" style="padding:48px;text-align:center;color:var(--muted)">
  Belum ada jurnal untuk periode ini. Input tebusan atau distribusi untuk mulai mencatat.
</div>
@endforelse

@endsection
