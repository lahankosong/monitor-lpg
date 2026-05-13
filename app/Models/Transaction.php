<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Transaction extends Model
{
    protected $fillable = [
        'customer_report_id',   // ID unik transaksi (pengganti NIK untuk grouping)
        'nationality_id',       // NIK tersensor mis: 330xxxxxxxxxx001
        'name',
        'category',
        'total',
        'transaction_at',
        'transaction_date',
        'pangkalan_id',         // sub dari JWT token
    ];

    protected $casts = [
        'transaction_at'   => 'datetime',
        'transaction_date' => 'date',
        'total'            => 'integer',
    ];

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeByCustomer(Builder $q, string $customerReportId): Builder
    {
        return $q->where('customer_report_id', $customerReportId);
    }

    public function scopeByNik(Builder $q, string $nik): Builder
    {
        // NIK tersensor: gunakan LIKE agar partial match tetap bekerja
        return $q->where('nationality_id', 'like', $nik);
    }

    public function scopeDateRange(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('transaction_date', [$from, $to]);
    }

    // ── Grouping: pakai customer_report_id sebagai kunci individu ──
    // NIK tersensor tidak bisa dijadikan kunci unik karena 'xxx' bisa
    // mewakili digit berbeda. customerReportId adalah hash konsisten
    // per individu yang diberikan API.

    public static function groupByCustomer(string $from, string $to): \Illuminate\Support\Collection
    {
        return static::dateRange($from, $to)
            ->orderBy('transaction_date')
            ->get()
            ->groupBy('customer_report_id');
    }

    /**
     * Ringkasan status semua customer dalam rentang tanggal.
     * Karena satu customer bisa beli berkali-kali, kita ambil semua
     * transaksi per customer_report_id dan analisis intervalnya.
     *
     * CATATAN: customer_report_id di sini adalah ID per-transaksi,
     * bukan per-orang. Untuk melacak orang yang sama lintas hari,
     * kita group by (nationality_id + name) karena customerReportId
     * berbeda tiap transaksi.
     */
    public static function groupByPerson(string $from, string $to): \Illuminate\Support\Collection
    {
        return static::dateRange($from, $to)
            ->orderBy('transaction_date')
            ->get()
            ->groupBy(fn($t) => $t->nationality_id . '||' . $t->name);
    }

    public static function nikStatusSummary(string $from, string $to, int $minInterval = 7): array
    {
        $groups = static::groupByPerson($from, $to);
        $counts = ['aman' => 0, 'warn' => 0, 'alert' => 0, 'new' => 0];

        foreach ($groups as $txns) {
            $counts[static::analyzeStatus($txns, $minInterval)]++;
        }

        return $counts;
    }

    public static function analyzeStatus(\Illuminate\Support\Collection $txns, int $minInterval = 7): string
    {
        if ($txns->count() === 1) return 'new';

        $dates     = $txns->pluck('transaction_date')->map(fn($d) => Carbon::parse($d))->sort()->values();
        $gaps      = [];
        $violations = 0;

        for ($i = 1; $i < $dates->count(); $i++) {
            $gap = $dates[$i - 1]->diffInDays($dates[$i]);
            $gaps[] = $gap;
            if ($gap < $minInterval) $violations++;
        }

        $avgGap = count($gaps) ? array_sum($gaps) / count($gaps) : null;

        if ($violations >= 3 || ($avgGap !== null && $avgGap < $minInterval * 0.5)) return 'alert';
        if ($violations >= 1 || ($avgGap !== null && $avgGap < $minInterval * 0.75)) return 'warn';
        return 'aman';
    }
}
