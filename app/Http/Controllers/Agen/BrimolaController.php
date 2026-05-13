<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Pangkalan;
use App\Models\HargaReferensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BrimolaController extends Controller
{
    // ── Model inline (agar tidak perlu file terpisah) ──────────────
    private function trxQuery() {
        return DB::table('brimola_transaksi');
    }

    // ─────────────────────────────────────────────────────────────────
    // INDEX — Dashboard BRImola
    // ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);

        // Rekap bulan ini
        $rekap = DB::table('brimola_transaksi')
            ->whereYear('tanggal_bayar', $tahun)
            ->whereMonth('tanggal_bayar', $bulan)
            ->selectRaw('
                COUNT(*) as total_trx,
                SUM(jumlah_tabung) as total_tabung,
                SUM(total_bayar) as total_nilai,
                SUM(CASE WHEN status="unmatched" THEN 1 ELSE 0 END) as total_unmatched,
                SUM(CASE WHEN status="verified" THEN 1 ELSE 0 END) as total_verified
            ')->first();

        // List transaksi dengan filter
        $status = $request->get('status', '');
        $search = $request->get('search', '');

        $transaksi = DB::table('brimola_transaksi as bt')
            ->leftJoin('pangkalans as p', 'bt.pangkalan_id', '=', 'p.id')
            ->whereYear('bt.tanggal_bayar', $tahun)
            ->whereMonth('bt.tanggal_bayar', $bulan)
            ->when($status, fn($q) => $q->where('bt.status', $status))
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->where('bt.nama_pangkalan', 'like', "%{$search}%")
                   ->orWhere('bt.no_briva', 'like', "%{$search}%")
            ))
            ->select(
                'bt.*',
                'p.nama_pangkalan as pangkalan_nama_db',
                'p.no_reg as pangkalan_no_reg'
            )
            ->orderByDesc('bt.tanggal_bayar')
            ->paginate(30)->withQueryString();

        // Rekap per pangkalan bulan ini
        $rekapPangkalan = DB::table('brimola_transaksi as bt')
            ->leftJoin('pangkalans as p', 'bt.pangkalan_id', '=', 'p.id')
            ->whereYear('bt.tanggal_bayar', $tahun)
            ->whereMonth('bt.tanggal_bayar', $bulan)
            ->groupBy('bt.pangkalan_id','bt.nama_pangkalan','p.nama_pangkalan','p.no_reg')
            ->selectRaw('
                bt.pangkalan_id,
                bt.nama_pangkalan,
                p.nama_pangkalan as nama_db,
                p.no_reg,
                COUNT(*) as jml_trx,
                SUM(jumlah_tabung) as total_tabung,
                SUM(total_bayar) as total_nilai,
                MAX(bt.status) as status,
                SUM(CASE WHEN bt.status = "unmatched" THEN 1 ELSE 0 END) as jml_unmatched,
                SUM(CASE WHEN bt.status = "verified"  THEN 1 ELSE 0 END) as jml_verified
            ')
            ->orderByRaw('SUM(jumlah_tabung) DESC')
            ->get();

        // Batch import history
        $batches = DB::table('brimola_import_batch')
            ->orderByDesc('created_at')->limit(10)->get();

        $pangkalans = Pangkalan::aktif()->orderBy('nama_pangkalan')->get();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.akuntansi.brimola.index', compact(
            'rekap','transaksi','rekapPangkalan','batches',
            'pangkalans','bulan','tahun','bulanList','status','search'
        ));
    }


    // ─────────────────────────────────────────────────────────────────
    // STORE — Input manual transaksi pembayaran
    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'pangkalan_id'    => 'required|exists:pangkalans,id',
            'no_briva'        => 'required|string|unique:brimola_transaksi,no_briva',
            'tanggal_bayar'   => 'required|date',
            'jumlah_tabung'   => 'required|integer|min:1',
            'harga_per_tabung'=> 'required|numeric|min:0',
        ]);

        $pangkalan = Pangkalan::find($request->pangkalan_id);
        $totalBayar = $request->jumlah_tabung * $request->harga_per_tabung;

        DB::table('brimola_transaksi')->insert([
            'pangkalan_id'     => $request->pangkalan_id,
            'nama_pangkalan'   => $pangkalan->nama_pangkalan,
            'no_briva'         => $request->no_briva,
            'tanggal_bayar'    => Carbon::parse($request->tanggal_bayar),
            'jumlah_tabung'    => $request->jumlah_tabung,
            'harga_per_tabung' => $request->harga_per_tabung,
            'total_bayar'      => $totalBayar,
            'status'           => 'matched',
            'sumber_file'      => 'input_manual',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return back()->with('success', 'Transaksi pembayaran berhasil ditambahkan.');
    }

    // ─────────────────────────────────────────────────────────────────
    // IMPORT — Upload file Excel BRImola
    // ─────────────────────────────────────────────────────────────────
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        // Baca file
        if (in_array($ext, ['xlsx','xls'])) {
            $rows = $this->readXlsx($file->getRealPath());
        } elseif (in_array($ext, ['csv','txt'])) {
            $rows = $this->readCsv($file->getRealPath());
        } else {
            return back()->withErrors(['file' => 'Format tidak didukung. Gunakan .xlsx atau .csv']);
        }

        if (empty($rows)) {
            return back()->withErrors(['file' => 'File kosong atau format tidak sesuai template BRImola.']);
        }

        // Ambil harga referensi aktif untuk kalkulasi nilai
        $harga = HargaReferensi::hargaAktif('jual_pangkalan')?->harga ?? 0;

        // Load semua pangkalan untuk matching
        $pangkalans = Pangkalan::all();

        $ok = $skip = $unmatched = 0;
        $periodeMin = null;
        $periodeMax = null;
        $totalNilai = 0;

        DB::beginTransaction();
        try {
            // Buat batch record
            $batchId = DB::table('brimola_import_batch')->insertGetId([
                'nama_file'       => $file->getClientOriginalName(),
                'periode_dari'    => now()->toDateString(),
                'periode_sampai'  => now()->toDateString(),
                'total_transaksi' => count($rows),
                'diimport_oleh'   => auth()->id(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            foreach ($rows as $row) {
                $namaPangkalan = trim($row[0] ?? '');
                $noBriva       = trim((string)($row[1] ?? ''));
                $tglBayar      = $row[2] ?? null;
                $jumlahTabung  = (int)($row[3] ?? 0);

                if (empty($namaPangkalan) || empty($noBriva) || !$jumlahTabung) {
                    $skip++;
                    continue;
                }

                // Skip jika no_briva sudah ada
                if (DB::table('brimola_transaksi')->where('no_briva', $noBriva)->exists()) {
                    $skip++;
                    continue;
                }

                // Parse tanggal
                if ($tglBayar instanceof \DateTime || $tglBayar instanceof \DateTimeInterface) {
                    $tglBayar = Carbon::instance($tglBayar);
                } else {
                    $tglBayar = Carbon::parse($tglBayar);
                }

                // Auto-match pangkalan berdasarkan nama (fuzzy)
                $pangkalan = $this->matchPangkalan($namaPangkalan, $pangkalans);
                $status    = $pangkalan ? 'matched' : 'unmatched';

                $totalBayar = $jumlahTabung * $harga;
                $totalNilai += $totalBayar;

                DB::table('brimola_transaksi')->insert([
                    'pangkalan_id'    => $pangkalan?->id,
                    'nama_pangkalan'  => $namaPangkalan,
                    'no_briva'        => $noBriva,
                    'tanggal_bayar'   => $tglBayar,
                    'jumlah_tabung'   => $jumlahTabung,
                    'harga_per_tabung'=> $harga,
                    'total_bayar'     => $totalBayar,
                    'status'          => $status,
                    'import_batch_id' => $batchId,
                    'sumber_file'     => $file->getClientOriginalName(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                $ok++;
                if (!$pangkalan) $unmatched++;

                // Track periode
                if (!$periodeMin || $tglBayar < $periodeMin) $periodeMin = $tglBayar;
                if (!$periodeMax || $tglBayar > $periodeMax) $periodeMax = $tglBayar;
            }

            // Update batch
            DB::table('brimola_import_batch')->where('id', $batchId)->update([
                'periode_dari'    => $periodeMin?->toDateString() ?? now()->toDateString(),
                'periode_sampai'  => $periodeMax?->toDateString() ?? now()->toDateString(),
                'total_transaksi' => $ok,
                'total_matched'   => $ok - $unmatched,
                'total_unmatched' => $unmatched,
                'total_nilai'     => $totalNilai,
                'updated_at'      => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['file' => 'Gagal import: '.$e->getMessage()]);
        }

        $msg = "{$ok} transaksi berhasil diimport";
        if ($skip)      $msg .= ", {$skip} dilewati (duplikat/kosong)";
        if ($unmatched) $msg .= ", {$unmatched} tidak cocok dengan pangkalan (perlu dicocokkan manual)";

        return back()->with('success', $msg);
    }

    // ─────────────────────────────────────────────────────────────────
    // MATCH — Cocokkan transaksi unmatched ke pangkalan
    // ─────────────────────────────────────────────────────────────────
    public function match(Request $request)
    {
        $request->validate([
            'transaksi_id' => 'required|integer',
            'pangkalan_id' => 'required|exists:pangkalans,id',
        ]);

        DB::table('brimola_transaksi')
            ->where('id', $request->transaksi_id)
            ->update([
                'pangkalan_id' => $request->pangkalan_id,
                'status'       => 'matched',
                'updated_at'   => now(),
            ]);

        return back()->with('success', 'Transaksi berhasil dicocokkan ke pangkalan.');
    }

    // ─────────────────────────────────────────────────────────────────
    // VERIFY — Verifikasi transaksi matched
    // ─────────────────────────────────────────────────────────────────
    public function verify(Request $request)
    {
        $request->validate(['transaksi_ids' => 'required|array']);

        DB::table('brimola_transaksi')
            ->whereIn('id', $request->transaksi_ids)
            ->where('status', 'matched')
            ->update(['status' => 'verified', 'updated_at' => now()]);

        return back()->with('success', count($request->transaksi_ids).' transaksi diverifikasi.');
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT — Export ke Excel (XLSX)
    // ─────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);

        $data = DB::table('brimola_transaksi as bt')
            ->leftJoin('pangkalans as p', 'bt.pangkalan_id', '=', 'p.id')
            ->whereYear('bt.tanggal_bayar', $tahun)
            ->whereMonth('bt.tanggal_bayar', $bulan)
            ->select('bt.*','p.nama_pangkalan as nama_db','p.no_reg')
            ->orderByDesc('bt.tanggal_bayar')
            ->get();

        $bulanStr = Carbon::create($tahun, $bulan)->format('Y-m');
        $filename = "brimola_{$bulanStr}.xlsx";

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BRImola');

        // Header
        $headers = ['Pangkalan', 'No BRIVA', 'Tanggal Bayar', 'Jumlah Tabung',
                    'Harga/Tabung', 'Total Bayar', 'Status', 'No Reg DB'];
        $sheet->fromArray($headers, null, 'A1');

        // Style header
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($data as $r) {
            $sheet->setCellValue("A{$row}", $r->nama_pangkalan);
            $sheet->setCellValue("B{$row}", $r->no_briva);
            $sheet->setCellValue("C{$row}", Carbon::parse($r->tanggal_bayar)->format('d/m/Y H:i'));
            $sheet->setCellValue("D{$row}", $r->jumlah_tabung);
            $sheet->setCellValue("E{$row}", $r->harga_per_tabung);
            $sheet->setCellValue("F{$row}", $r->total_bayar);
            $sheet->setCellValue("G{$row}", $r->status);
            $sheet->setCellValue("H{$row}", $r->no_reg ?? '');
            $row++;
        }

        // Auto-width columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format number columns
        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle("D2:F{$lastRow}")->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    /** Auto-match nama pangkalan dari file dengan database */
    private function matchPangkalan(string $nama, $pangkalans): ?Pangkalan
    {
        $nama = strtolower(trim($nama));

        // 1. Exact match
        $match = $pangkalans->first(fn($p) =>
            strtolower(trim($p->nama_pangkalan)) === $nama
        );
        if ($match) return $match;

        // 2. Contains match (nama file ada di nama db atau sebaliknya)
        $match = $pangkalans->first(fn($p) =>
            str_contains(strtolower($p->nama_pangkalan), $nama) ||
            str_contains($nama, strtolower($p->nama_pangkalan))
        );
        if ($match) return $match;

        // 3. Similarity match (min 80%)
        $best = null;
        $bestScore = 0;
        foreach ($pangkalans as $p) {
            similar_text($nama, strtolower($p->nama_pangkalan), $pct);
            if ($pct > $bestScore && $pct >= 75) {
                $bestScore = $pct;
                $best = $p;
            }
        }
        return $best;
    }

    /** Baca XLSX dengan ZipArchive + DOMDocument */
    private function readXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return [];

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
        if (!$sheetXml) return [];

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($sheetXml)) return [];

        $rows = [];
        foreach ($dom->getElementsByTagName('row') as $rowEl) {
            $rowIdx = (int)$rowEl->getAttribute('r');
            if ($rowIdx <= 1) continue; // skip header

            $rowData = array_fill(0, 4, null);
            foreach ($rowEl->getElementsByTagName('c') as $cell) {
                $ref  = $cell->getAttribute('r');
                preg_match('/([A-Z]+)/', strtoupper($ref), $m);
                $col  = $this->colIndex($m[1] ?? 'A') - 1;
                if ($col >= 4) continue;

                $type  = $cell->getAttribute('t');
                $vList = $cell->getElementsByTagName('v');
                $value = $vList->length > 0 ? $vList->item(0)->nodeValue : '';

                if ($type === 's') {
                    $rowData[$col] = $sharedStrings[(int)$value] ?? '';
                } elseif ($col === 2 && is_numeric($value)) {
                    // Kolom tanggal — Excel serial date
                    $rowData[$col] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)
                        ?? Carbon::createFromTimestamp(($value - 25569) * 86400);
                } else {
                    $rowData[$col] = $value;
                }
            }
            if (array_filter($rowData)) $rows[] = $rowData;
        }
        return $rows;
    }

    /** Konversi Excel date serial tanpa PhpSpreadsheet */
    private function excelDate(float $serial): Carbon
    {
        // Excel epoch: 1 Jan 1900 = serial 1 (dengan bug Lotus 1-2-3)
        return Carbon::createFromTimestamp(($serial - 25569) * 86400);
    }

    private function colIndex(string $col): int
    {
        $idx = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - 64);
        }
        return $idx;
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) fseek($handle, 0);
        $line = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (++$line === 1) continue;
            if (array_filter($row)) $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }
}
