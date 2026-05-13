<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pembayaran_spbes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitir_id')->constrained('kitirs');
            $table->date('tanggal_bayar');
            $table->bigInteger('jumlah_bayar');
            $table->string('no_rekening_spbe', 50)->nullable();
            $table->string('bukti_transfer', 255)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pembayaran_spbes'); }
};
