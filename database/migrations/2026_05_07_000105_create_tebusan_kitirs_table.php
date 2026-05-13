<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Sudah dibuat ulang di migration sebelumnya jika perlu,
        // migration ini khusus jika tabel belum ada
        if (! Schema::hasTable('tebusan_kitirs')) {
            Schema::create('tebusan_kitirs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kitir_id')->constrained('kitirs');
                $table->date('tanggal_bayar');
                $table->integer('jumlah_tabung_ditebus');
                $table->bigInteger('total_bayar');
                $table->string('no_rekening_tujuan', 50)->nullable();
                $table->string('bukti_transfer', 255)->nullable();
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tebusan_kitir_details')) {
            Schema::create('tebusan_kitir_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tebusan_id')->constrained('tebusan_kitirs')->onDelete('cascade');
                $table->foreignId('kitir_detail_id')->constrained('kitir_details');
                $table->integer('jumlah_tabung');
                $table->bigInteger('subtotal');
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('tebusan_kitir_details');
        Schema::dropIfExists('tebusan_kitirs');
        Schema::enableForeignKeyConstraints();
    }
};
