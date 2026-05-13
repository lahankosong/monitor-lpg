<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Jadwal Distribusi — {{ $suratJalan->no_sj }}</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; font-size:12px; color:#000; padding:20px; }
    .kop { display:flex; align-items:center; gap:16px; border-bottom:3px solid #000; padding-bottom:10px; margin-bottom:16px; }
    .kop img { height:60px; object-fit:contain; }
    .kop-text { flex:1; }
    .kop-text h1 { font-size:16px; font-weight:bold; text-transform:uppercase; }
    .kop-text h2 { font-size:13px; font-weight:bold; margin:2px 0; }
    .kop-text p  { font-size:10px; color:#333; }
    .kop-logo-elpiji { height:55px; }
    .judul { text-align:center; font-size:13px; font-weight:bold; text-transform:uppercase;
             margin:12px 0 16px; text-decoration:underline; }
    .info-row { display:flex; gap:40px; margin-bottom:14px; font-size:12px; }
    .info-row span { color:#555; }
    .info-row strong { color:#000; }
    table.dist { width:100%; border-collapse:collapse; }
    table.dist th { background:#f0f0f0; border:1px solid #000; padding:6px 8px; font-size:11px; text-align:center; }
    table.dist td { border:1px solid #000; padding:7px 8px; font-size:12px; }
    table.dist td.center { text-align:center; }
    table.dist td.right  { text-align:right; }
    table.dist tr.total td { font-weight:bold; background:#f9f9f9; }
    .ttd { display:flex; justify-content:space-between; margin-top:40px; }
    .ttd-box { text-align:center; width:160px; }
    .ttd-box .nama { border-top:1px solid #000; margin-top:55px; padding-top:4px; font-size:11px; }
    @media print { button { display:none; } }
  </style>
</head>
<body>

<div style="text-align:right;margin-bottom:12px">
  <button onclick="window.print()" style="background:#059669;color:#fff;border:none;border-radius:6px;padding:8px 20px;font-size:13px;cursor:pointer">🖨 Cetak</button>
  <button onclick="window.close()" style="background:#6B7280;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;cursor:pointer;margin-left:8px">Tutup</button>
</div>

<div class="kop">
  @if($agen?->logo_path)
    <img src="{{ asset('storage/'.$agen->logo_path) }}" alt="Logo">
  @endif
  <div class="kop-text">
    <h1>{{ $agen?->nama_agen ?? 'PT. NAMA AGEN' }}</h1>
    <h2>AGEN LPG 3 KG PERTAMINA</h2>
    <p>{{ $agen?->alamat }}</p>
    <p>Telp: {{ $agen?->telepon }}</p>
  </div>
  @if($agen?->logo_elpiji_path)
    <img src="{{ asset('storage/'.$agen->logo_elpiji_path) }}" class="kop-logo-elpiji" alt="Elpiji">
  @endif
</div>

<div class="judul">Jadwal Distribusi LPG 3 KG</div>

<div class="info-row">
  <span>No. SJ: <strong>{{ $suratJalan->no_sj }}</strong></span>
  <span>Tanggal: <strong>{{ $suratJalan->tanggal->translatedFormat('d F Y') }}</strong></span>
  <span>Armada: <strong>{{ $suratJalan->armada?->no_polisi }}</strong></span>
  <span>SA: <strong>{{ $suratJalan->kitirDetail?->kitir?->nomor_sa }}</strong></span>
</div>
<div class="info-row" style="margin-bottom:16px">
  <span>Sopir: <strong>{{ $suratJalan->sopir?->nama_karyawan }}</strong></span>
  @if($suratJalan->kernet)
    <span>Kernet: <strong>{{ $suratJalan->kernet->nama_karyawan }}</strong></span>
  @endif
  <span>Total Refil: <strong>{{ number_format($suratJalan->qty_refil) }} tabung</strong></span>
</div>

<table class="dist">
  <thead>
    <tr>
      <th style="width:35px">No</th>
      <th>Nama Pangkalan</th>
      <th style="width:80px">No. Reg</th>
      <th style="width:80px">Qty Jadwal</th>
      <th style="width:80px">Qty Terima</th>
      <th style="width:80px">Sisa</th>
      <th>Keterangan</th>
    </tr>
  </thead>
  <tbody>
    @foreach($suratJalan->details->sortBy('urutan') as $i => $d)
    <tr>
      <td class="center">{{ $i + 1 }}</td>
      <td>{{ $d->pangkalan?->nama_pangkalan }}</td>
      <td class="center">{{ $d->pangkalan?->no_reg }}</td>
      <td class="center">{{ number_format($d->qty_jadwal) }}</td>
      <td class="center">{{ $d->qty_terima > 0 ? number_format($d->qty_terima) : '' }}</td>
      <td class="center">{{ $d->sisa > 0 ? number_format($d->sisa) : '' }}</td>
      <td style="font-size:11px">{{ $d->keterangan }}</td>
    </tr>
    @endforeach
    <tr class="total">
      <td colspan="3" class="right">TOTAL</td>
      <td class="center">{{ number_format($suratJalan->details->sum('qty_jadwal')) }}</td>
      <td class="center"></td>
      <td></td>
      <td></td>
    </tr>
  </tbody>
</table>

<div class="ttd">
  <div class="ttd-box">
    <p>Dibuat oleh,</p>
    <div class="nama">{{ $agen?->nama_pimpinan ?? '................................' }}</div>
  </div>
  <div class="ttd-box">
    <p>Sopir / Pengantar,</p>
    <div class="nama">{{ $suratJalan->sopir?->nama_karyawan ?? '................................' }}</div>
  </div>
  <div class="ttd-box">
    <p>Kernet,</p>
    <div class="nama">{{ $suratJalan->kernet?->nama_karyawan ?? '................................' }}</div>
  </div>
</div>

</body>
</html>
