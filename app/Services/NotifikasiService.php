<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk membuat dan membaca notifikasi dalam aplikasi.
 * Dipanggil dari controller setelah event penting terjadi.
 */
class NotifikasiService
{
    /**
     * Buat notifikasi untuk satu atau banyak user berdasarkan role.
     *
     * @param array|string $roles  Role yang menerima: 'direktur', 'manajer', ['direktur','manajer']
     * @param string $tipe
     * @param string $judul
     * @param string $pesan
     * @param string|null $url
     * @param string|null $refTipe
     * @param int|null $refId
     */
    public static function kirim(
        array|string $roles,
        string $tipe,
        string $judul,
        string $pesan,
        ?string $url = null,
        ?string $refTipe = null,
        ?int $refId = null
    ): void {
        $roles = (array) $roles;

        $users = DB::table('users')
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->pluck('id');

        $now = now();
        $rows = $users->map(fn($uid) => [
            'user_id'        => $uid,
            'tipe'           => $tipe,
            'judul'          => $judul,
            'pesan'          => $pesan,
            'url'            => $url,
            'referensi_tipe' => $refTipe,
            'referensi_id'   => $refId,
            'is_read'        => false,
            'created_at'     => $now,
            'updated_at'     => $now,
        ])->toArray();

        if (!empty($rows)) {
            DB::table('notifikasis')->insert($rows);
        }
    }

    /** Shortcut: kirim ke direktur dan manajer */
    public static function keDirektur(string $tipe, string $judul, string $pesan,
                                       ?string $url = null, ?string $refTipe = null, ?int $refId = null): void
    {
        self::kirim(['direktur','manajer'], $tipe, $judul, $pesan, $url, $refTipe, $refId);
    }

    /** Shortcut: kirim ke semua role kecuali driver */
    public static function keAdmin(string $tipe, string $judul, string $pesan, ?string $url = null): void
    {
        self::kirim(['direktur','manajer','admin'], $tipe, $judul, $pesan, $url);
    }

    /** Jumlah notifikasi belum dibaca untuk user */
    public static function countBelumBaca(int $userId): int
    {
        return DB::table('notifikasis')
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /** Ambil notifikasi terbaru untuk user */
    public static function terbaru(int $userId, int $limit = 15): \Illuminate\Support\Collection
    {
        return DB::table('notifikasis')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /** Tandai semua sebagai dibaca */
    public static function bacaSemua(int $userId): void
    {
        DB::table('notifikasis')
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);
    }

    /** Tandai satu notifikasi sebagai dibaca */
    public static function baca(int $notifId, int $userId): void
    {
        DB::table('notifikasis')
            ->where('id', $notifId)
            ->where('user_id', $userId)
            ->update(['is_read' => true, 'read_at' => now(), 'updated_at' => now()]);
    }

    /** Icon per tipe notifikasi */
    public static function icon(string $tipe): string
    {
        return match($tipe) {
            'tebusan_baru'         => '📋',
            'sj_selesai'           => '🚛',
            'piutang_jatuh_tempo'  => '⚠️',
            'brimola_unmatched'    => '💳',
            'stok_gudang_rendah'   => '📦',
            'scraping_selesai'     => '✓',
            'scraping_gagal'       => '✗',
            'jurnal_tidak_balance' => '⚖️',
            default                => 'ℹ️',
        };
    }

    /** Warna badge per tipe */
    public static function warna(string $tipe): string
    {
        return match($tipe) {
            'piutang_jatuh_tempo','brimola_unmatched',
            'stok_gudang_rendah','jurnal_tidak_balance','scraping_gagal'
                => '#DC2626',
            'tebusan_baru','sj_selesai' => '#0EA5E9',
            'scraping_selesai'          => '#059669',
            default                     => '#6B7280',
        };
    }
}
