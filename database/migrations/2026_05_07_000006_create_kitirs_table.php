<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kitirs', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['reguler', 'fakultatif'])->default('reguler');
            $table->date('tanggal');
            $table->foreignId('spbe_id')->constrained('spbes');
            $table->string('nomor_sa', 50)->nullable();
            $table->integer('kuota_tabung');
            $table->integer('harga_per_tabung')->default(0);
            $table->enum('status', [
                'belum_bayar','sudah_bayar','diambil','selesai'
            ])->default('belum_bayar');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['tanggal','spbe_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('kitirs'); }
};
