<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Tracking setiap kelebihan qty_terima dari sumber yang jelas
        if (!Schema::hasTable('sj_detail_tambahan')) {
            Schema::create('sj_detail_tambahan', function (Blueprint $table) {
                $table->id();

                // Detail yang MENERIMA tambahan (penerima)
                $table->foreignId('sj_detail_id')
                      ->constrained('surat_jalan_details')->cascadeOnDelete();

                $table->integer('qty'); // berapa tabung tambahan

                $table->enum('sumber_tipe', [
                    'gendongan',              // dari stok armada
                    'gudang',                 // dari gudang_stok
                    'pengalihan_pangkalan',   // dialihkan dari pangkalan lain di SJ yang sama
                ]);

                // Referensi sumber (nullable, untuk audit trail)
                $table->unsignedBigInteger('sumber_sj_detail_id')->nullable(); // jika dari pengalihan pangkalan lain
                $table->unsignedBigInteger('stok_armada_id')->nullable();      // jika dari gendongan
                $table->unsignedBigInteger('gudang_stok_id')->nullable();      // jika dari gudang

                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index('sj_detail_id');
                $table->index('sumber_sj_detail_id');
            });
        }

        // Tambah kolom qty_tambahan ke sj_detail untuk quick sum
        Schema::table('surat_jalan_details', function (Blueprint $table) {
            if (!Schema::hasColumn('surat_jalan_details', 'qty_tambahan'))
                $table->integer('qty_tambahan')->default(0)->after('qty_terima');
            if (!Schema::hasColumn('surat_jalan_details', 'qty_maks'))
                // qty_maks = qty_jadwal + semua tambahan yang sudah dikonfirmasi
                $table->integer('qty_maks')->default(0)->after('qty_tambahan');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sj_detail_tambahan');
        Schema::table('surat_jalan_details', function (Blueprint $table) {
            $table->dropColumn(['qty_tambahan','qty_maks']);
        });
    }
};
