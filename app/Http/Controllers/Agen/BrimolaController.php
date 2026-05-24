<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Pangkalan;
use App\Models\HargaReferensi;
use App\Services\JurnalService;
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
        $harga = HargaReferensi::aktif()?->harga_per_tabung ?? 0;

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

        $transaksis = DB::table('brimola_transaksi')
            ->whereIn('id', $request->transaksi_ids)
            ->where('status', 'matched')
            ->get();

        DB::table('brimola_transaksi')
            ->whereIn('id', $request->transaksi_ids)
            ->where('status', 'matched')
            ->update(['status' => 'verified', 'updated_at' => now()]);

        // Jurnal buku besar: Debit 1002 Giro, Kredit 1003 Piutang Dagang
        try {
            $jurnalSvc = app(JurnalService::class);
            foreach ($transaksis as $t) {
                if ($t->total_bayar > 0) {
                    $jurnalSvc->brimola(
                        \Carbon\Carbon::parse($t->tanggal_bayar),
                        (int)$t->total_bayar,
                        $t->no_briva,
                        $t->id
                    );
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[BRImola] Gagal jurnal: '.$e->getMessage());
        }

        return back()->with('success', count($request->transaksi_ids).' transaksi diverifikasi.');
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT — Export ke CSV
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
        $filename = "brimola_{$bulanStr}.csv";

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($handle, ['pangkalan','no_briva','tanggal_bayar','jumlah_tabung',
                              'harga_per_tabung','total_bayar','status','no_reg_db']);
            foreach ($data as $r) {
                fputcsv($handle, [
                    $r->nama_pangkalan,
                    $r->no_briva,
                    Carbon::parse($r->tanggal_bayar)->format('d/m/Y H:i'),
                    $r->jumlah_tabung,
                    $r->harga_per_tabung,
                    $r->total_bayar,
                    $r->status,
                    $r->no_reg ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
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
    
    public function store(Request $request)
    {
        $request->validate([
            'pangkalan_id'   => 'required|exists:pangkalans,id',
            'tanggal_bayar'  => 'required|date',
            'jumlah_tabung'  => 'required|integer|min:1',
            'harga_per_tabung' => 'nullable|integer|min:0',
            'total_bayar'    => 'nullable|integer|min:0',
            'no_briva'       => 'nullable|string|max:20',
            'status'         => 'required|in:verified,matched,unmatched',
        ]);
    
        \DB::table('brimolas')->insert([
            'pangkalan_id' => $request->pangkalan_id,
            'tanggal_bayar'=> $request->tanggal_bayar,
            'qty_bayar'    => $request->jumlah_tabung,
            'no_briva'     => $request->no_briva,
            'jumlah_bayar' => $request->total_bayar ?? 0,
            'created_by'   => auth()->id(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    
        return back()->with('success', 'Pembayaran manual berhasil disimpan.');
    }
}
