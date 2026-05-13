<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('surat_jalan_headers', function (Blueprint $table) {
            $table->id();
            $table->string('no_sj', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('kitir_id')->constrained('kitirs');
            $table->foreignId('armada_id')->constrained('armadas');
            $table->foreignId('sopir_id')->constrained('karyawans');
            $table->integer('total_kuota');
            $table->integer('total_terjadwal')->default(0);
            $table->enum('status',['draft','aktif','selesai','batal'])->default('draft');
            $table->timestamps();
            $table->index(['tanggal','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('surat_jalan_headers'); }
};
