<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pangkalans', function (Blueprint $table) {
            // Alokasi kontrak distribusi (hari-hari pengiriman)
            // Disimpan sebagai JSON array: ["senin","kamis"] atau ["selasa","jumat"]
            if (!Schema::hasColumn('pangkalans', 'hari_distribusi')) {
                $table->json('hari_distribusi')->nullable()
                      ->comment('Array hari distribusi: ["senin","kamis"]')
                      ->after('nomor_bukti_pinjaman');
            }
            // Kuota per pengiriman (jumlah tabung per SJ)
            if (!Schema::hasColumn('pangkalans', 'kuota_per_pengiriman')) {
                $table->integer('kuota_per_pengiriman')->default(0)
                      ->after('hari_distribusi');
            }
            // Catatan kontrak
            if (!Schema::hasColumn('pangkalans', 'catatan_kontrak')) {
                $table->text('catatan_kontrak')->nullable()
                      ->after('kuota_per_pengiriman');
            }
        });
    }
    public function down(): void {
        Schema::table('pangkalans', function (Blueprint $table) {
            $table->dropColumn(['hari_distribusi','kuota_per_pengiriman','catatan_kontrak']);
        });
    }
};
