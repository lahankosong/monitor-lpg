<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Surat Perjanjian Kerjasama — {{ $pangkalan->nama_pangkalan }}</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Times New Roman',serif; font-size:12pt; color:#000; padding:25mm 20mm; }
    .kop { display:flex; align-items:center; gap:16px; border-bottom:3px double #000; padding-bottom:10px; margin-bottom:20px; }
    .kop img { height:65px; object-fit:contain; }
    .kop-text { flex:1; text-align:center; }
    .kop-text h1 { font-size:15pt; font-weight:bold; text-transform:uppercase; }
    .kop-text h2 { font-size:12pt; font-weight:bold; text-transform:uppercase; margin:3px 0; }
    .kop-text p  { font-size:10pt; }
    .kop-logo-r  { height:65px; object-fit:contain; }
    .judul { text-align:center; margin:20px 0 16px; }
    .judul h3 { font-size:14pt; font-weight:bold; text-transform:uppercase; text-decoration:underline; letter-spacing:1px; }
    .judul p { font-size:10pt; margin-top:4px; }
    .intro { margin-bottom:16px; line-height:1.7; text-align:justify; }
    .pihak { margin:10px 0 16px; }
    .pihak table { width:100%; border-collapse:collapse; }
    .pihak td { padding:3px 6px; vertical-align:top; font-size:11pt; line-height:1.6; }
    .pihak td:first-child { width:180px; }
    .pihak td:nth-child(2) { width:10px; }
    .pasal { margin-bottom:14px; }
    .pasal h4 { text-align:center; font-size:12pt; font-weight:bold; text-transform:uppercase; margin-bottom:6px; }
    .pasal p { line-height:1.8; text-align:justify; margin-bottom:4px; }
    .pasal ol { margin-left:20px; line-height:1.8; }
    .pasal ol li { margin-bottom:3px; }
    .ttd { display:flex; justify-content:space-between; margin-top:40px; }
    .ttd-box { text-align:center; width:220px; }
    .ttd-box p { font-size:11pt; }
    .ttd-box .stempel { border:1px dashed #aaa; height:70px; margin:6px 0; display:flex;
                        align-items:center; justify-content:center; font-size:9pt; color:#999; }
    .ttd-box .nama { border-top:1px solid #000; margin-top:65px; padding-top:4px;
                     font-weight:bold; font-size:11pt; }
    .ttd-box .jabatan { font-size:10pt; }
    @media print {
      body { padding:15mm 15mm; }
      button { display:none !important; }
    }
  </style>
</head>
<body>

<div style="text-align:right;margin-bottom:16px">
  <button onclick="window.print()" style="background:#1D4ED8;color:#fff;border:none;border-radius:6px;padding:8px 20px;font-size:13px;cursor:pointer;font-family:Arial">
    🖨 Cetak Surat Perjanjian
  </button>
  <button onclick="window.close()" style="background:#6B7280;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;cursor:pointer;margin-left:8px;font-family:Arial">
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
    <p>{{ $agen?->alamat }}</p>
    <p>Telp: {{ $agen?->telepon }}{{ $agen?->email ? ' · Email: '.$agen->email : '' }}</p>
  </div>
  @if($agen?->logo_elpiji_path)
    <img src="{{ asset('storage/'.$agen->logo_elpiji_path) }}" class="kop-logo-r" alt="Elpiji">
  @endif
</div>

{{-- JUDUL --}}
<div class="judul">
  <h3>Surat Perjanjian Kerjasama</h3>
  <h3>Peminjaman Tabung LPG 3 Kg</h3>
  <p>Nomor: {{ $pangkalan->nomor_bukti_pinjaman ?? 'PJB' . now()->format('y') . '01.' . str_pad($pangkalan->id, 2, '0', STR_PAD_LEFT) }}</p>
</div>

{{-- PEMBUKA --}}
<div class="intro">
  <p>Pada {{ \Carbon\Carbon::parse($pangkalan->tanggal_mulai_pinjaman ?? now())->translatedFormat('l') }},
  Tanggal {{ \Carbon\Carbon::parse($pangkalan->tanggal_mulai_pinjaman ?? now())->translatedFormat('d F Y') }},
  kami yang bertanda tangan di bawah ini:</p>
</div>

{{-- PIHAK --}}
<div class="pihak">
  <table>
    <tr><td colspan="3"><strong>PIHAK PERTAMA</strong></td></tr>
    <tr><td>Nama</td><td>:</td><td><strong>{{ $agen?->nama_agen }}</strong></td></tr>
    <tr><td>Alamat</td><td>:</td><td>{{ $agen?->alamat }}</td></tr>
    <tr><td></td><td></td><td><em>Selanjutnya disebut sebagai <strong>PIHAK PERTAMA</strong></em></td></tr>
  </table>
</div>

<div class="pihak">
  <table>
    <tr><td colspan="3"><strong>PIHAK KEDUA</strong></td></tr>
    <tr><td>Nama Sub Agen / Toko</td><td>:</td><td><strong>{{ $pangkalan->nama_pangkalan }}</strong></td></tr>
    <tr><td>Nama Pemilik</td><td>:</td><td>{{ $pangkalan->nama_pemilik ?? '...............................' }}</td></tr>
    <tr><td>Nomor KTP</td><td>:</td><td>{{ $pangkalan->nik_pemilik ?? '...............................' }}</td></tr>
    <tr><td>Nomor Registrasi</td><td>:</td><td>{{ $pangkalan->no_registrasi ?? '...............................' }}</td></tr>
    <tr><td>Alamat</td><td>:</td><td>{{ $pangkalan->alamat_pemilik ?? $pangkalan->alamat ?? '...............................' }}</td></tr>
    <tr><td></td><td></td><td><em>Selanjutnya disebut sebagai <strong>PIHAK KEDUA</strong></em></td></tr>
  </table>
</div>

<div class="intro">
  <p>PIHAK PERTAMA menyerahkan barang kepada PIHAK KEDUA dan PIHAK KEDUA menyatakan telah menerima
  barang dari PIHAK PERTAMA sebagai pinjaman, dengan daftar barang sebagai berikut:</p>
</div>

{{-- TABEL BARANG --}}
<table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:11pt">
  <thead>
    <tr style="background:#f0f0f0">
      <th style="border:1px solid #000;padding:5px 8px;text-align:center;width:40px">No.</th>
      <th style="border:1px solid #000;padding:5px 8px;text-align:center;width:80px">Kode Barang</th>
      <th style="border:1px solid #000;padding:5px 8px">Nama Barang</th>
      <th style="border:1px solid #000;padding:5px 8px;text-align:center;width:70px">Jumlah</th>
      <th style="border:1px solid #000;padding:5px 8px;text-align:center;width:60px">Satuan</th>
      <th style="border:1px solid #000;padding:5px 8px">Keterangan</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="border:1px solid #000;padding:5px 8px;text-align:center">1</td>
      <td style="border:1px solid #000;padding:5px 8px;text-align:center">020001</td>
      <td style="border:1px solid #000;padding:5px 8px">Tabung LPG 3 KG</td>
      <td style="border:1px solid #000;padding:5px 8px;text-align:center;font-weight:bold">{{ $pangkalan->jumlah_tabung_pinjaman }}</td>
      <td style="border:1px solid #000;padding:5px 8px;text-align:center">Tabung</td>
      <td style="border:1px solid #000;padding:5px 8px">Tabung Kosong LPG 3kg</td>
    </tr>
  </tbody>
</table>

{{-- PASAL-PASAL --}}
<div class="pasal">
  <h4>Pasal 1 — Ketentuan Peminjaman</h4>
  <ol>
    <li>Jangka waktu peminjaman adalah <strong>{{ $pangkalan->jangka_pinjaman_bulan ?? 12 }} bulan</strong>
        terhitung sejak tanggal {{ \Carbon\Carbon::parse($pangkalan->tanggal_mulai_pinjaman ?? now())->translatedFormat('d F Y') }}.</li>
    <li>PIHAK PERTAMA berhak memperbaharui masa peminjaman atau menarik kembali barang dan membatalkan
        peminjaman sewaktu-waktu bila dipandang perlu.</li>
    <li>Kehilangan atau kerusakan barang dalam kurun waktu peminjaman menjadi tanggung jawab PIHAK KEDUA
        dan PIHAK KEDUA wajib mengganti dengan barang yang sama.</li>
  </ol>
</div>

<div class="pasal">
  <h4>Pasal 2 — Biaya Sewa</h4>
  <ol>
    <li>PIHAK KEDUA dikenakan biaya sewa tabung sebesar
        <strong>Rp {{ number_format($pangkalan->harga_sewa_per_tabung) }},-</strong>
        per tabung per distribusi.</li>
    <li>Biaya sewa ditagihkan setiap awal bulan berikutnya berdasarkan realisasi distribusi bulan sebelumnya.</li>
    <li>Alokasi distribusi per bulan sebesar
        <strong>{{ $pangkalan->alokasi_per_bulan > 0 ? number_format($pangkalan->alokasi_per_bulan).' tabung' : 'sesuai kesepakatan' }}</strong>.</li>
  </ol>
</div>

<div class="pasal">
  <h4>Pasal 3 — Kewajiban Para Pihak</h4>
  <ol>
    <li>PIHAK KEDUA wajib menjaga dan merawat tabung pinjaman dengan baik.</li>
    <li>PIHAK KEDUA wajib mengembalikan tabung dalam kondisi baik apabila peminjaman berakhir atau dibatalkan.</li>
    <li>PIHAK KEDUA dilarang memindahtangankan atau menyewakan kembali tabung kepada pihak lain.</li>
    <li>PIHAK KEDUA wajib melunasi biaya sewa sebelum mengambil refil berikutnya.</li>
  </ol>
</div>

<p style="line-height:1.8;text-align:justify;margin-bottom:20px">
  Demikian berita acara serah terima peminjaman barang ini dibuat oleh PIHAK PERTAMA dan PIHAK KEDUA.
  Adapun barang-barang tersebut di atas dalam keadaan baik dan cukup sejak penandatanganan berita acara ini.
</p>

{{-- TTD --}}
<div class="ttd">
  <div class="ttd-box">
    <p>PIHAK PERTAMA</p>
    <div style="display:flex;gap:10px;justify-content:center;margin:6px 0">
      @php
        $manajer  = \App\Models\Karyawan::where('role','manager')->where('is_active',1)->first();
        $security = \App\Models\Karyawan::where('role','security')->where('is_active',1)->first();
        $driver   = \App\Models\Karyawan::where('role','driver')->where('is_active',1)->first();
      @endphp
      @foreach(['Manager','Gudang/Security','Pengemudi'] as $jab)
      <div style="text-align:center;width:80px">
        <p style="font-size:9pt">{{ $jab }}</p>
        <div style="height:55px"></div>
        <div style="border-top:1px solid #000;font-size:9pt;padding-top:3px">
          @if($jab==='Manager') ({{ $manajer?->nama_karyawan ?? '.....' }})
          @elseif($jab==='Gudang/Security') ({{ $security?->nama_karyawan ?? '.....' }})
          @else ({{ $driver?->nama_karyawan ?? '.....' }})
          @endif
        </div>
      </div>
      @endforeach
    </div>
  </div>
  <div class="ttd-box">
    <p>PIHAK KEDUA</p>
    <p style="font-size:10pt;margin-top:4px">Menerima</p>
    <div class="stempel">[ Tempel Meterai / Stempel ]</div>
    <div class="nama">({{ $pangkalan->nama_pemilik ?? '............................' }})</div>
    <div class="jabatan">Pemilik</div>
  </div>
</div>

</body>
</html>
