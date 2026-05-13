<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('surat_jalan_headers', function (Blueprint $table) {
            // Gendongan: sisa tabung yang dibawa armada ke hari berikutnya
            if (!Schema::hasColumn('surat_jalan_headers', 'qty_gendongan'))
                $table->integer('qty_gendongan')->default(0)->after('qty_tabung_baru');
            // Turun gudang: sisa yang disimpan di gudang agen
            if (!Schema::hasColumn('surat_jalan_headers', 'qty_gudang'))
                $table->integer('qty_gudang')->default(0)->after('qty_gendongan');
            // Total tersedia = ambil_do + gendongan_masuk + ambil_gudang
            if (!Schema::hasColumn('surat_jalan_headers', 'qty_gendongan_masuk'))
                $table->integer('qty_gendongan_masuk')->default(0)->after('qty_gudang');
        });
    }
    public function down(): void {
        Schema::table('surat_jalan_headers', function ($t) {
            $t->dropColumn(['qty_gendongan','qty_gudang','qty_gendongan_masuk']);
        });
    }
};
