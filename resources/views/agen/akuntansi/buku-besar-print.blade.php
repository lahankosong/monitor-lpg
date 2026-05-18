<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Buku Besar {{ $bulanList[$bulan] }} {{ $tahun }}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; padding: 20px; }
.kop { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 16px; }
.kop h1 { font-size: 15px; font-weight: 700; }
.kop p  { font-size: 11px; color: #444; margin-top: 3px; }
.kop h2 { font-size: 13px; margin-top: 8px; font-weight: 600; }
.kel-header { font-size: 11px; font-weight: 700; text-transform: uppercase;
              letter-spacing: .06em; margin: 14px 0 6px;
              border-bottom: 1px solid #ccc; padding-bottom: 4px; }
.akun-header { display: flex; justify-content: space-between;
               background: #f5f5f5; padding: 5px 10px; margin-bottom: 0;
               border: 1px solid #ddd; border-bottom: none; }
.akun-kode  { font-weight: 700; font-size: 12px; }
.akun-saldo { font-weight: 700; }
table { width: 100%; border-collapse: collapse; border: 1px solid #ddd; margin-bottom: 12px; }
th { background: #eee; text-align: left; padding: 4px 8px; font-size: 10px;
     font-weight: 700; text-transform: uppercase; border: 1px solid #ddd; }
td { padding: 4px 8px; border: 1px solid #ddd; }
.saldo-awal td { background: #fafafa; font-style: italic; color: #666; }
.total-row td  { background: #f0f0f0; font-weight: 700; }
.text-right { text-align: right; }
.debit  { color: #1d4ed8; }
.kredit { color: #dc2626; }
.saldo-pos { color: #15803d; font-weight: 700; }
.saldo-neg { color: #dc2626; font-weight: 700; }
.manual-badge { font-size: 9px; color: #b45309; }
@media print {
  body { padding: 10px; }
  .no-print { display: none; }
}
</style>
</head>
<body>

<div class="no-print" style="margin-bottom:16px;display:flex;gap:8px">
  <button onclick="window.print()" style="background:#1d4ed8;color:#fff;border:none;border-radius:6px;padding:8px 18px;font-size:13px;cursor:pointer;font-weight:600">🖨 Cetak / Simpan PDF</button>
  <button onclick="window.close()" style="border:1px solid #ddd;background:#fff;border-radius:6px;padding:8px 14px;font-size:13px;cursor:pointer">Tutup</button>
</div>

{{-- Kop surat --}}
<div class="kop">
  <h1>{{ strtoupper($agen?->nama_agen ?? 'AGEN LPG SUBSIDI') }}</h1>
  <p>
    Kode Agen: {{ $agen?->kode_agen ?? '—' }}
    @if($agen?->sold_to) · SOLD TO: {{ $agen->sold_to }} @endif
  </p>
  @if($agen?->alamat_agen)
  <p>{{ $agen->alamat_agen }}
    @if($agen?->telepon_agen) · Telp. {{ $agen->telepon_agen }} @endif
  </p>
  @endif
  <h2>BUKU BESAR UMUM</h2>
  <p>Periode: {{ $bulanList[$bulan] }} {{ $tahun }}</p>
  @if($kodeAkun)
  <p>Akun: {{ $kodeAkun }}</p>
  @endif
  <p style="font-size:10px;color:#888;margin-top:4px">Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
</div>

@php
$kelompokLabel = [
  'aset'=>'ASET','kewajiban'=>'KEWAJIBAN','modal'=>'MODAL',
  'pendapatan'=>'PENDAPATAN','beban'=>'BEBAN',
];
@endphp

@foreach($bukuBesar as $kel => $akunList)
<div class="kel-header">{{ $kelompokLabel[$kel] ?? strtoupper($kel) }}</div>

@foreach($akunList as $akunData)
@php
  $akun      = $akunData['akun'];
  $mutasi    = $akunData['mutasi'];
  $saldoAwal = $akunData['saldo_awal'];
  $totalD    = $mutasi->sum(fn($m) => $m->posisi==='debit' ? $m->jumlah : 0);
  $totalK    = $mutasi->sum(fn($m) => $m->posisi==='kredit' ? $m->jumlah : 0);
  $saldoAkhir= $mutasi->isNotEmpty() ? $mutasi->last()->saldo_running : $saldoAwal;
@endphp

@if($mutasi->isNotEmpty() || $saldoAwal != 0)
<div class="akun-header">
  <span class="akun-kode">{{ $akun->kode }} &nbsp; {{ $akun->nama }}</span>
  <span class="akun-saldo {{ $saldoAkhir>=0?'saldo-pos':'saldo-neg' }}">
    Saldo Akhir: Rp {{ number_format($saldoAkhir) }}
  </span>
</div>
<table>
  <thead>
    <tr>
      <th style="width:75px">Tanggal</th>
      <th style="width:100px">No Jurnal</th>
      <th>Keterangan</th>
      <th style="width:80px">Modul</th>
      <th style="width:100px" class="text-right">Debit</th>
      <th style="width:100px" class="text-right">Kredit</th>
      <th style="width:110px" class="text-right">Saldo</th>
    </tr>
  </thead>
  <tbody>
    <tr class="saldo-awal">
      <td colspan="4" style="font-style:italic">Saldo Awal Periode</td>
      <td></td><td></td>
      <td class="text-right">Rp {{ number_format($saldoAwal) }}</td>
    </tr>
    @foreach($mutasi as $m)
    <tr>
      <td>{{ \Carbon\Carbon::parse($m->tanggal)->format('d/m/Y') }}</td>
      <td style="font-family:monospace;font-size:10px">{{ $m->no_jurnal }}</td>
      <td>{{ Str::limit($m->ket_detail ?: $m->ket_header, 60) }}
        @if(!$m->is_otomatis)<span class="manual-badge">✎</span>@endif
      </td>
      <td style="font-size:9px;font-weight:700">{{ strtoupper(str_replace('_',' ',$m->modul)) }}</td>
      <td class="text-right debit">{{ $m->posisi==='debit' ? 'Rp '.number_format($m->jumlah) : '' }}</td>
      <td class="text-right kredit">{{ $m->posisi==='kredit' ? 'Rp '.number_format($m->jumlah) : '' }}</td>
      <td class="text-right {{ $m->saldo_running>=0?'saldo-pos':'saldo-neg' }}">
        Rp {{ number_format($m->saldo_running) }}
      </td>
    </tr>
    @endforeach
    @if($mutasi->isNotEmpty())
    <tr class="total-row">
      <td colspan="4">TOTAL</td>
      <td class="text-right debit">Rp {{ number_format($totalD) }}</td>
      <td class="text-right kredit">Rp {{ number_format($totalK) }}</td>
      <td class="text-right {{ $saldoAkhir>=0?'saldo-pos':'saldo-neg' }}">Rp {{ number_format($saldoAkhir) }}</td>
    </tr>
    @endif
  </tbody>
</table>
@endif
@endforeach
@endforeach

<div style="margin-top:30px;display:flex;justify-content:flex-end">
  <div style="text-align:center;border-top:1px solid #ccc;padding-top:6px;min-width:160px">
    <p style="font-size:10px;color:#666">Dibuat oleh</p>
    <p style="font-size:10px;margin-top:40px">(_____________________)</p>
  </div>
</div>

</body>
</html>
