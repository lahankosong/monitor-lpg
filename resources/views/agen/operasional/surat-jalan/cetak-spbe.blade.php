<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Surat Pengantar — {{ $suratJalan->no_sj }}</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; font-size:12px; color:#000; padding:20px; }
    .kop { display:flex; align-items:center; gap:16px; border-bottom:3px solid #000; padding-bottom:10px; margin-bottom:16px; }
    .kop img { height:60px; object-fit:contain; }
    .kop-text { flex:1; }
    .kop-text h1 { font-size:16px; font-weight:bold; text-transform:uppercase; }
    .kop-text h2 { font-size:13px; font-weight:bold; text-transform:uppercase; margin:2px 0; }
    .kop-text p  { font-size:10px; color:#333; }
    .kop-logo-elpiji { height:55px; }
    .judul { text-align:center; font-size:14px; font-weight:bold; text-transform:uppercase;
             margin:16px 0 20px; text-decoration:underline; }
    table.info { width:100%; border-collapse:collapse; margin-bottom:16px; }
    table.info td { padding:4px 8px; vertical-align:top; }
    table.info td:first-child { width:160px; font-weight:normal; }
    table.info td:nth-child(2) { width:10px; }
    table.info td:last-child { font-weight:bold; }
    table.detail { width:100%; border-collapse:collapse; margin-bottom:20px; }
    table.detail th, table.detail td { border:1px solid #000; padding:5px 8px; text-align:left; }
    table.detail th { background:#f0f0f0; font-size:11px; }
    .ttd { display:flex; justify-content:space-between; margin-top:40px; }
    .ttd-box { text-align:center; width:180px; }
    .ttd-box .nama { border-top:1px solid #000; margin-top:60px; padding-top:4px; font-weight:bold; }
    @media print {
      body { padding:10px; }
      button { display:none; }
    }
  </style>
</head>
<body>

{{-- Tombol cetak --}}
<div style="text-align:right;margin-bottom:12px">
  <button onclick="window.print()" style="background:#1D4ED8;color:#fff;border:none;border-radius:6px;padding:8px 20px;font-size:13px;cursor:pointer">
    🖨 Cetak
  </button>
  <button onclick="window.close()" style="background:#6B7280;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;cursor:pointer;margin-left:8px">
    Tutup
  </button>
</div>

{{-- KOP SURAT --}}
<div class="kop">
  @if($agen?->logo_path)
    <img src="{{ asset('storage/'.$agen->logo_path) }}" alt="Logo">
  @endif
  <div class="kop-text">
    <h1>{{ $agen?->nama_agen ?? 'PT. NAMA AGEN' }}</h1>
    <h2>AGEN LPG 3 KG PERTAMINA - {{ strtoupper($agen?->nama_agen ?? '') }}</h2>
    <p>{{ $agen?->alamat ?? '' }}</p>
    <p>Telp: {{ $agen?->telepon ?? '' }}{{ $agen?->email ? ' · Email: '.$agen->email : '' }}</p>
  </div>
  @if($agen?->logo_elpiji_path)
    <img src="{{ asset('storage/'.$agen->logo_elpiji_path) }}" class="kop-logo-elpiji" alt="Elpiji">
  @endif
</div>

<div class="judul">Surat Pengantar Pengambilan</div>

<p style="margin-bottom:12px">
  Kepada SPBE/SPPBE,<br>
  <strong>{{ $suratJalan->kitirDetail?->kitir?->spbe?->nama_spbe ?? '—' }}</strong><br>
  {{ $suratJalan->kitirDetail?->kitir?->spbe?->alamat ?? '' }}
</p>

<p style="margin-bottom:12px">Dengan hormat,</p>
<p style="margin-bottom:16px">Mohon bantuannya untuk pelaksanaan transaksi pengambilan LPG 3 kg kami sebagai berikut:</p>

<table class="info">
  <tr>
    <td>Tanggal Pengambilan</td><td>:</td>
    <td>{{ $suratJalan->tanggal->translatedFormat('d F Y') }}</td>
  </tr>
  <tr>
    <td>Nomor Armada</td><td>:</td>
    <td><strong>{{ $suratJalan->armada?->no_polisi ?? '—' }}</strong></td>
  </tr>
  <tr>
    <td>Nama Pengemudi</td><td>:</td>
    <td>{{ $suratJalan->sopir?->nama_karyawan ?? '—' }}</td>
  </tr>
  <tr>
    <td>Nomor SO/SA</td><td>:</td>
    <td><strong>{{ $suratJalan->kitirDetail?->kitir?->nomor_sa ?? '—' }}</strong></td>
  </tr>
  <tr>
    <td>Nomor LO</td><td>:</td>
    <td>{{ $suratJalan->no_lo ?? '.................................................................' }}</td>
  </tr>
  <tr>
    <td>Kwantitas yang diambil</td><td>:</td>
    <td>
      - Refill : <strong>{{ number_format($suratJalan->qty_refil) }} tabung</strong><br>
      - Tabung Baru : {{ $suratJalan->qty_tabung_baru > 0 ? number_format($suratJalan->qty_tabung_baru).' tabung' : '...... tabung' }}<br>
      <strong>Total Pengambilan : {{ number_format($suratJalan->qty_refil + $suratJalan->qty_tabung_baru) }} tabung</strong>
    </td>
  </tr>
</table>

<p style="margin-bottom:24px">Demikian dan atas bantuannya kami ucapkan terima kasih.</p>

<div class="ttd">
  <div></div>
  <div class="ttd-box">
    <p>{{ strtoupper($suratJalan->tanggal->translatedFormat('d F Y')) }}</p>
    <p>{{ strtoupper($agen?->nama_agen ?? '') }}</p>
    @php
      $manajer = \App\Models\Karyawan::where('role','manager')->where('is_active',1)->first()
                 ?? \App\Models\Karyawan::where('role','direktur')->where('is_active',1)->first();
    @endphp
    <div class="nama">{{ $manajer?->nama_karyawan ?? $agen?->nama_pimpinan ?? '................................' }}</div>
  </div>
</div>

</body>
</html>
