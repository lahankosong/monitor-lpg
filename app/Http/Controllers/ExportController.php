<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use App\Models\NikViolation;
use App\Services\NikMonitorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function __construct(private NikMonitorService $monitor) {}

    /**
     * Entry point — deteksi format dan data type dari request
     * GET /dashboard/export?type=transaksi&format=pdf&from=...&to=...&pangkalan_id=...
     */
    public function export(Request $request)
    {
        $format      = $request->get('format', 'csv');   // csv | xlsx | pdf | jpg
        $type        = $request->get('type', 'transaksi'); // transaksi | nik | pangkalan | stok
        $from        = $request->get('from', now()->startOfMonth()->toDateString());
        $to          = $request->get('to',   now()->toDateString());
        $pangkalanId = $request->get('pangkalan_id', '');

        $data = $this->getData($type, $from, $to, $pangkalanId);

        return match($format) {
            'xlsx' => $this->exportXlsx($data, $type, $from, $to),
            'pdf'  => $this->exportPdf($data, $type, $from, $to),
            'jpg'  => $this->exportJpg($data, $type, $from, $to),
            default=> $this->exportCsv($data, $type, $from, $to),
        };
    }

    // ── Ambil data sesuai tipe ────────────────────────────────

    private function getData(string $type, string $from, string $to, string $pangkalanId): array
    {
        return match($type) {
            'nik'       => $this->dataNik($from, $to, $pangkalanId),
            'pangkalan' => $this->dataPangkalan($from, $to),
            default     => $this->dataTransaksi($from, $to, $pangkalanId),
        };
    }

    private function dataTransaksi(string $from, string $to, string $pangkalanId): array
    {
        $query = Transaction::whereBetween('transaction_date', [$from, $to])
            ->when($pangkalanId, fn($q) => $q->where('pangkalan_id', $pangkalanId))
            ->orderBy('transaction_date')
            ->orderBy('transaction_at')
            ->get();

        $headers = ['Pangkalan', 'NIK (sensor)', 'Nama', 'Tipe Konsumen', 'Tabung', 'Tanggal', 'Waktu (WIB)'];
        $rows    = $query->map(fn($t) => [
            $t->store_name ?? $t->pangkalan_id,
            $t->nationality_id,
            $t->name,
            $t->category ?? 'Rumah Tangga',
            $t->total,
            Carbon::parse($t->transaction_date)->format('d/m/Y'),
            Carbon::parse($t->transaction_at)->setTimezone('Asia/Jakarta')->format('H:i:s'),
        ])->toArray();

        return [
            'title'   => 'Data Transaksi LPG Subsidi',
            'headers' => $headers,
            'rows'    => $rows,
            'summary' => [
                'Total transaksi' => count($rows),
                'Total tabung'    => $query->sum('total'),
                'Periode'         => "{$from} s/d {$to}",
            ],
        ];
    }

    private function dataNik(string $from, string $to, string $pangkalanId): array
    {
        $txns = Transaction::whereBetween('transaction_date', [$from, $to])
            ->when($pangkalanId, fn($q) => $q->where('pangkalan_id', $pangkalanId))
            ->orderBy('transaction_date')
            ->get();

        $groups  = $txns->groupBy(fn($t) => $t->nationality_id . '||' . $t->name);
        $headers = ['Pangkalan', 'NIK (sensor)', 'Nama', 'Tipe Konsumen', 'Txn', 'Total Tabung', 'Jarak Rata²', 'Status', 'Pelanggaran'];
        $rows    = [];

        foreach ($groups as $g) {
            $analysis = $this->monitor->analyzeIndividu($g);
            if (empty($analysis)) continue;

            $violations = collect($analysis['all_alerts'] ?? [])
                ->pluck('pesan')
                ->implode('; ');

            $rows[] = [
                $analysis['store_name'] ?? '—',
                $analysis['nik'],
                $analysis['nama'],
                $analysis['kategori'],
                $analysis['total_txn'],
                $analysis['total_tabung'],
                $analysis['avg_gap'] ?? '—',
                strtoupper($analysis['status']),
                $violations ?: '—',
            ];
        }

        return [
            'title'   => 'Monitor NIK LPG Subsidi',
            'headers' => $headers,
            'rows'    => $rows,
            'summary' => [
                'Total NIK'    => count($rows),
                'Pelanggaran'  => collect($rows)->filter(fn($r) => in_array($r[7], ['ALERT','WARN']))->count(),
                'Periode'      => "{$from} s/d {$to}",
            ],
        ];
    }

    private function dataPangkalan(string $from, string $to): array
    {
        $sessions = PangkalanSession::orderBy('label')->get();
        $headers  = ['Nama Pangkalan', 'Registration ID', 'Total NIK', 'Total Txn', 'Total Tabung', 'Status', 'Aktif'];
        $rows     = [];

        foreach ($sessions as $s) {
            $txns  = Transaction::where('pangkalan_id', $s->pangkalan_id)
                ->whereBetween('transaction_date', [$from, $to])->get();
            $niks  = $txns->groupBy(fn($t) => $t->nationality_id . '||' . $t->name)->count();
            $token = PangkalanToken::where('pangkalan_id', $s->pangkalan_id)->first();

            $rows[] = [
                $s->label,
                $s->registration_id ?? '—',
                $niks,
                $txns->count(),
                $txns->sum('total'),
                $token?->token_expires_at?->isFuture() ? 'Token Aktif' : 'Token Expired',
                $s->is_active ? 'Ya' : 'Tidak',
            ];
        }

        return [
            'title'   => 'Data Pangkalan LPG Subsidi',
            'headers' => $headers,
            'rows'    => $rows,
            'summary' => ['Total Pangkalan' => count($rows), 'Periode' => "{$from} s/d {$to}"],
        ];
    }

    // ── Export CSV ────────────────────────────────────────────

    private function exportCsv(array $data, string $type, string $from, string $to)
    {
        $filename = "lpg_{$type}_{$from}_{$to}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($data, $from, $to) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            // Judul
            fputcsv($out, [$data['title']]);
            fputcsv($out, ["Periode: {$from} s/d {$to}", 'Diekspor: ' . now()->format('d/m/Y H:i')]);
            fputcsv($out, []);

            // Summary
            foreach ($data['summary'] as $k => $v) {
                fputcsv($out, [$k, $v]);
            }
            fputcsv($out, []);

            // Data
            fputcsv($out, $data['headers']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Export XLSX ───────────────────────────────────────────

    private function exportXlsx(array $data, string $type, string $from, string $to)
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return back()->withErrors(['msg' => 'PhpSpreadsheet belum terinstall. Jalankan: composer require phpoffice/phpspreadsheet']);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        // Style header
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'],   // ← 'color' tidak dikenal di v2+
            ],
            'alignment' => ['horizontal' => 'center'],
        ];
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A8A']],
        ];

        // Judul
        $sheet->setCellValue('A1', $data['title']);
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->setCellValue('A2', "Periode: {$from} s/d {$to}  |  Diekspor: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A2')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B7280'));

        // Summary
        $row = 4;
        foreach ($data['summary'] as $k => $v) {
            $sheet->setCellValue("A{$row}", $k);
            $sheet->setCellValue("B{$row}", $v);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        // Header kolom
        $col = 'A';
        foreach ($data['headers'] as $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
            $col++;
        }
        $lastCol = chr(ord('A') + count($data['headers']) - 1);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($headerStyle);
        $row++;

        // Data rows
        foreach ($data['rows'] as $i => $dataRow) {
            $col = 'A';
            foreach ($dataRow as $cell) {
                $sheet->setCellValue("{$col}{$row}", $cell);
                $col++;
            }
            // Alternating row color
            if ($i % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()       // ← getColor() dihapus di v2+
                    ->setRGB('F8FAFC');
            }
            $row++;
        }

        // Auto width kolom
        foreach (range('A', $lastCol) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        // Freeze header
        $sheet->freezePane('A' . (count($data['summary']) + 6));

        $filename = "lpg_{$type}_{$from}_{$to}.xlsx";
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'lpg_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── Export PDF ────────────────────────────────────────────

    private function exportPdf(array $data, string $type, string $from, string $to)
    {
        // Cek lewat Dompdf core class, bukan facade (facade selalu ada jika di-register)
        if (! class_exists(\Dompdf\Dompdf::class)) {
            return back()->withErrors(['msg' => 'DomPDF belum terinstall. Jalankan: composer require barryvdh/laravel-dompdf']);
        }

        $html = $this->buildPdfHtml($data, $from, $to);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape');

        $filename = "lpg_{$type}_{$from}_{$to}.pdf";
        return $pdf->download($filename);
    }

    private function buildPdfHtml(array $data, string $from, string $to): string
    {
        $rows = collect($data['rows'])->map(function ($row) {
            $cells = collect($row)->map(fn($c) => "<td>{$c}</td>")->implode('');
            return "<tr>{$cells}</tr>";
        })->implode('');

        $headers = collect($data['headers'])->map(fn($h) => "<th>{$h}</th>")->implode('');

        $summary = collect($data['summary'])->map(fn($v, $k) => "<span><strong>{$k}:</strong> {$v}</span>")->implode(' &nbsp;|&nbsp; ');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
  body { margin: 20px; color: #1a1a1a; }
  h1 { font-size: 16px; color: #1e3a8a; margin-bottom: 4px; }
  .meta { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
  .summary { background: #eff6ff; padding: 8px 12px; border-radius: 6px; margin-bottom: 16px; font-size: 9px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1e40af; color: #fff; padding: 6px 8px; text-align: left; font-size: 9px; }
  td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
  tr:nth-child(even) td { background: #f8fafc; }
  .footer { margin-top: 16px; font-size: 8px; color: #9ca3af; text-align: right; }
</style>
</head>
<body>
  <h1>{$data['title']}</h1>
  <p class="meta">Periode: {$from} s/d {$to} &nbsp;|&nbsp; Diekspor: {$this->now()}</p>
  <div class="summary">{$summary}</div>
  <table>
    <thead><tr>{$headers}</tr></thead>
    <tbody>{$rows}</tbody>
  </table>
  <p class="footer">LPG Monitor Subsidi — {$this->now()}</p>
</body>
</html>
HTML;
    }

    // ── Export JPG (screenshot tabel via HTML) ────────────────

    private function exportJpg(array $data, string $type, string $from, string $to)
    {
        // JPG: generate HTML lalu kirim sebagai halaman untuk di-screenshot
        // Browser bisa print/save as image dari halaman ini
        $html = $this->buildPdfHtml($data, $from, $to);

        // Tambahkan tombol cetak dan instruksi
        $printHtml = str_replace(
            '<body>',
            '<body><div style="background:#fef3c7;padding:8px 16px;font-size:12px;margin-bottom:16px">
                📸 Untuk export JPG: Tekan <kbd>Ctrl+P</kbd> → Simpan sebagai PDF → Konversi ke JPG, 
                atau klik kanan halaman → "Save as image" (di browser yang mendukung)
                <button onclick="window.print()" style="margin-left:16px;background:#1e40af;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer">🖨 Print / Save PDF</button>
            </div>',
            $html
        );

        return response($printHtml)->header('Content-Type', 'text/html');
    }

    private function now(): string
    {
        return Carbon::now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');
    }
}
