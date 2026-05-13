@extends('layouts.app')
@section('title', 'Detail Kitir SA#'.$kitir->nomor_sa)

@section('content')
<div style="margin-bottom:16px">
  <a href="{{ route('dashboard.agen.operasional.kitir.index') }}"
     style="font-size:12px;color:var(--muted);text-decoration:none">← Kembali ke daftar kitir</a>
</div>

{{-- Header --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div>
    <h1 style="font-size:20px;font-weight:700;color:var(--text)">
      Kitir SA# <span style="font-family:monospace">{{ $kitir->nomor_sa }}</span>
    </h1>
    <div style="display:flex;gap:12px;margin-top:6px;flex-wrap:wrap">
      <span style="font-size:12px;color:var(--muted)">SPBE: <strong style="color:var(--text)">{{ $kitir->spbe->nama_spbe }}</strong></span>
      <span style="font-size:12px;color:var(--muted)">Periode: <strong style="color:var(--text)">{{ $kitir->valid_from->format('d/m/Y') }} – {{ $kitir->valid_to->format('d/m/Y') }}</strong></span>
      <span style="font-size:12px;color:var(--muted)">Total: <strong style="color:var(--text)">{{ number_format($kitir->total_kuota) }} tabung</strong></span>
    </div>
  </div>
  @php
    $statusColor = match($kitir->status) {
      'aktif'   => 'background:#DBEAFE;color:#1E40AF',
      'selesai' => 'background:#D1FAE5;color:#065F46',
      'batal'   => 'background:#FEE2E2;color:#991B1B',
      default   => '',
    };
  @endphp
  <span style="padding:6px 16px;border-radius:99px;font-size:13px;font-weight:600;{{ $statusColor }}">
    {{ ucfirst($kitir->status) }}
  </span>
</div>

{{-- Summary cards --}}
@php
  $terbayar = $kitir->details->whereIn('status',['sudah_tebus','diambil'])->sum('kuota_tabung');
  $diambil  = $kitir->details->where('status','diambil')->sum('kuota_tabung');
  $belum    = $kitir->details->where('status','belum_tebus')->sum('kuota_tabung');
  $totalNilai = $kitir->details->sum(fn($d) => $d->kuota_tabung * $d->harga_tebus);
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
  @foreach([
    ['Total Kuota',    number_format($kitir->total_kuota).' tb', '#3B82F6'],
    ['Sudah Tebus',    number_format($terbayar).' tb',  '#059669'],
    ['Sudah Diambil',  number_format($diambil).' tb',   '#7C3AED'],
    ['Belum Tebus',    number_format($belum).' tb',     '#F59E0B'],
    ['Nilai Total',    'Rp '.number_format($totalNilai), '#EF4444'],
  ] as [$label, $val, $color])
  <div class="stat-card" style="border-left:3px solid {{ $color }}">
    <p style="font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em">{{ $label }}</p>
    <p style="font-size:20px;font-weight:700;color:var(--text);margin-top:4px">{{ $val }}</p>
  </div>
  @endforeach
</div>

{{-- Tabel detail per tanggal --}}
<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:14px;font-weight:600;color:var(--text)">Alokasi Per Tanggal</h2>
    <span style="font-size:12px;color:var(--muted)">{{ $kitir->details->count() }} hari</span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg)">
        @foreach(['Tanggal','Kuota','Harga Tebus','Nilai','Status','Ubah Status'] as $h)
          <th style="text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($kitir->details->sortBy('tanggal') as $d)
      @php
        $nilai = $d->kuota_tabung * $d->harga_tebus;
        $sc = match($d->status) {
          'belum_tebus'  => 'background:#FEF3C7;color:#92400E',
          'sudah_tebus'  => 'background:#DBEAFE;color:#1E40AF',
          'diambil'      => 'background:#D1FAE5;color:#065F46',
          default        => '',
        };
        $slabel = match($d->status) {
          'belum_tebus'  => 'Belum Tebus',
          'sudah_tebus'  => 'Sudah Tebus',
          'diambil'      => 'Sudah Diambil',
          default        => $d->status,
        };
      @endphp
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:11px 14px;font-weight:600;color:var(--text)">
          {{ $d->tanggal->format('d/m/Y') }}
          <span style="display:block;font-size:11px;color:var(--muted)">{{ $d->tanggal->translatedFormat('l') }}</span>
        </td>
        <td style="padding:11px 14px;font-weight:700;color:var(--text)">{{ number_format($d->kuota_tabung) }}</td>
        <td style="padding:11px 14px;color:var(--muted)">
          {{ $d->harga_tebus > 0 ? 'Rp '.number_format($d->harga_tebus) : '—' }}
        </td>
        <td style="padding:11px 14px;color:var(--text)">
          {{ $nilai > 0 ? 'Rp '.number_format($nilai) : '—' }}
        </td>
        <td style="padding:11px 14px">
          <span style="padding:2px 10px;border-radius:99px;font-size:11px;font-weight:500;{{ $sc }}">{{ $slabel }}</span>
        </td>
        <td style="padding:11px 14px">
          @if($d->status !== 'diambil')
          <form action="{{ route('dashboard.agen.operasional.kitir.detail.status', $d) }}" method="POST" style="display:flex;gap:6px">
            @csrf @method('PATCH')
            @if($d->status === 'belum_tebus')
              <input type="hidden" name="status" value="sudah_tebus">
              <button type="submit" style="background:#1D4ED8;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer">
                Tandai Sudah Tebus
              </button>
            @elseif($d->status === 'sudah_tebus')
              <input type="hidden" name="status" value="diambil">
              <button type="submit" style="background:#059669;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer">
                Tandai Sudah Diambil
              </button>
            @endif
          </form>
          @else
            <span style="font-size:11px;color:var(--muted)">Selesai</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
