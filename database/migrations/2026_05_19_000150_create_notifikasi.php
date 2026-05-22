<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('notifikasis')) {
            Schema::create('notifikasis', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('tipe', [
                    'tebusan_baru',        // ada kitir baru masuk
                    'sj_selesai',          // distribusi 1 trip selesai
                    'piutang_jatuh_tempo', // piutang kerjasama mendekati/lewat jatuh tempo
                    'brimola_unmatched',   // ada transaksi BRImola tidak cocok
                    'stok_gudang_rendah',  // stok gudang di bawah batas minimum
                    'scraping_selesai',    // batch scraping selesai
                    'scraping_gagal',      // batch scraping ada yang gagal
                    'jurnal_tidak_balance',// ada jurnal yang tidak balance
                    'info',                // notifikasi umum/info
                ]);
                $table->string('judul', 120);
                $table->text('pesan');
                $table->string('url', 255)->nullable();  // link ke halaman terkait
                $table->string('referensi_tipe', 50)->nullable();
                $table->unsignedBigInteger('referensi_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id','is_read','created_at']);
                $table->index('tipe');
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('notifikasis');
    }
};
