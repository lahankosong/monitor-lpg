<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Pangkalan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PangkalanExportImportController extends Controller
{
    /** Download template XLSX */
    public function template()
    {
        $locations = [
            storage_path('app/templates/template_import_pangkalan.xlsx'),
            public_path('templates/template_import_pangkalan.xlsx'),
            base_path('resources/templates/template_import_pangkalan.xlsx'),
        ];

        foreach ($locations as $path) {
            if (file_exists($path)) {
                return response()->download($path, 'template_import_pangkalan.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            }
        }

        return back()->withErrors(['msg' => 'File template tidak ditemukan. Taruh template_import_pangkalan.xlsx di storage/app/templates/']);
    }

    /** Export semua pangkalan ke CSV */
    public function export()
    {
        $pangkalans = Pangkalan::orderBy('nama_pangkalan')->get();

        $callback = function () use ($pangkalans) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($handle, [
                'no_reg','nama_pangkalan','nama_pemilik','nik_pemilik',
                'alamat','alamat_pemilik','telepon','tipe','no_registrasi',
                'jml_tabung_pinjaman','harga_sewa_tabung','map_email','status'
            ]);
            foreach ($pangkalans as $p) {
                fputcsv($handle, [
                    $p->no_reg, $p->nama_pangkalan, $p->nama_pemilik ?? '',
                    $p->nik_pemilik ?? '', $p->alamat ?? '', $p->alamat_pemilik ?? '',
                    $p->telepon ?? '', $p->tipe, $p->no_registrasi ?? '',
                    $p->jumlah_tabung_pinjaman, $p->harga_sewa_per_tabung,
                    $p->map_email ?? '', $p->is_active ? 'aktif' : 'nonaktif'
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pangkalan_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    /** Import dari XLSX atau CSV */
    public function import(Request $request)
    {
        $request->validate([
            'xlsx_file' => 'required|file|max:5120',
        ]);

        $file = $request->file('xlsx_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xls'])) {
            $rows = $this->readXlsx($file->getRealPath());
        } elseif (in_array($ext, ['csv', 'txt'])) {
            $rows = $this->readCsv($file->getRealPath());
        } else {
            return back()->withErrors(['msg' => 'Format tidak didukung. Gunakan .xlsx atau .csv']);
        }

        if (is_string($rows)) {
            // Error message dari reader
            return back()->withErrors(['msg' => $rows]);
        }

        if (empty($rows)) {
            return back()->withErrors(['msg' => 'Tidak ada data yang bisa dibaca. Pastikan data diisi mulai baris ke-6 dan kolom no_reg serta nama_pangkalan terisi.']);
        }

        return $this->prosesImport($rows, $request);
    }

    /** Baca XLSX - langsung pakai native PHP ZipArchive + DOMDocument */
    private function readXlsx(string $filePath): array|string
    {
        return $this->readXlsxNative($filePath);
    }

    /** Fallback: baca XLSX native dengan ZipArchive + SimpleXML */
    private function readXlsxNative(string $filePath): array|string
    {
        if (!class_exists('ZipArchive')) {
            return 'ZipArchive tidak tersedia. Install php-zip.';
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return 'Tidak bisa membuka file XLSX.';
        }

        // Shared strings - pakai DOMDocument agar lebih reliable dari SimpleXML
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $dom = new \DOMDocument();
            if (@$dom->loadXML($ssXml)) {
                foreach ($dom->getElementsByTagName('si') as $si) {
                    $text = '';
                    foreach ($si->getElementsByTagName('t') as $t) {
                        $text .= $t->nodeValue;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) return 'Sheet 1 tidak ditemukan.';

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($sheetXml)) return 'Gagal parse XML sheet.';

        $rows = [];
        foreach ($dom->getElementsByTagName('row') as $rowEl) {
            $rowIdx = (int)$rowEl->getAttribute('r');
            if ($rowIdx < 6) continue;

            $rowData = array_fill(0, 13, '');
            foreach ($rowEl->getElementsByTagName('c') as $cell) {
                $ref  = $cell->getAttribute('r');
                $col  = $this->colIndex($ref) - 1;
                if ($col < 0 || $col >= 13) continue;

                $type  = $cell->getAttribute('t');
                $vList = $cell->getElementsByTagName('v');
                $value = $vList->length > 0 ? $vList->item(0)->nodeValue : '';

                $rowData[$col] = ($type === 's')
                    ? ($sharedStrings[(int)$value] ?? '')
                    : $value;
            }

            if (array_filter($rowData)) {
                if ($rowData[0] === 'P-001' && $rowData[1] === 'Pangkalan Bu Sari') continue;
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    private function colIndex(string $ref): int
    {
        preg_match('/([A-Z]+)/i', strtoupper($ref), $m);
        $col = $m[1] ?? 'A';
        $idx = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - 64);
        }
        return $idx;
    }

    /** Baca CSV */
    private function readCsv(string $filePath): array
    {
        $rows   = [];
        $handle = fopen($filePath, 'r');
        $bom    = fread($handle, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) fseek($handle, 0);

        $line = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (++$line === 1) continue; // skip header
            if (array_filter($row)) $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /** Proses dan simpan ke database */
    private function prosesImport(array $rows, Request $request): \Illuminate\Http\RedirectResponse
    {
        $ok = $skip = $errors = 0;
        $errorList = [];

        foreach ($rows as $i => $row) {
            $noReg = trim($row[0] ?? '');
            $nama  = trim($row[1] ?? '');

            if (empty($noReg) || empty($nama)) { $skip++; continue; }

            if (Pangkalan::where('no_reg', $noReg)->exists()) {
                $skip++;
                $errorList[] = "no_reg '{$noReg}' sudah ada → dilewati";
                continue;
            }

            $tipe = strtolower(trim($row[7] ?? 'mandiri'));
            if (!in_array($tipe, ['mandiri','kerjasama'])) $tipe = 'mandiri';

            // Kolom: 0=no_reg,1=nama,2=nama_pemilik,3=nik,4=alamat,5=alamat_pemilik,
            //        6=telepon,7=tipe,8=alokasi_per_bulan,9=no_registrasi,
            //        10=jml_tabung,11=harga_sewa,12=latitude,13=longitude,14=map_email
            $lat = trim($row[12] ?? '');
            $lng = trim($row[13] ?? '');

            $data = [
                'no_reg'                 => $noReg,
                'nama_pangkalan'         => $nama,
                'nama_pemilik'           => trim($row[2] ?? '') ?: null,
                'nik_pemilik'            => trim($row[3] ?? '') ?: null,
                'alamat'                 => trim($row[4] ?? '') ?: null,
                'alamat_pemilik'         => trim($row[5] ?? '') ?: null,
                'telepon'                => trim($row[6] ?? '') ?: null,
                'tipe'                   => $tipe,
                'alokasi_per_bulan'      => intval($row[8] ?? 0),
                'no_registrasi'          => trim($row[9] ?? '') ?: null,
                'jumlah_tabung_pinjaman' => intval($row[10] ?? 0),
                'harga_sewa_per_tabung'  => intval($row[11] ?? 0),
                'latitude'               => (is_numeric($lat) && $lat !== '') ? floatval($lat) : null,
                'longitude'              => (is_numeric($lng) && $lng !== '') ? floatval($lng) : null,
                'map_email'              => trim($row[14] ?? '') ?: null,
            ];

            try {
                Pangkalan::create($data);
                $ok++;
            } catch (\Exception $e) {
                $errors++;
                $errorList[] = "'{$noReg}': " . $e->getMessage();
            }
        }

        $msg = "{$ok} pangkalan berhasil diimport";
        if ($skip)   $msg .= ", {$skip} dilewati";
        if ($errors) $msg .= ", {$errors} error";

        $request->session()->flash('import_errors', $errorList);
        return back()->with('success', $msg);
    }
}
