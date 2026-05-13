<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Hapus tabel anak dulu sebelum drop kitirs
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('tebusan_kitir_details');
        Schema::dropIfExists('tebusan_kitirs');
        Schema::dropIfExists('kitir_details');
        Schema::dropIfExists('kitirs');
        Schema::enableForeignKeyConstraints();

        Schema::create('kitirs', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_sa', 20)->unique();
            $table->string('sold_to', 20)->nullable();
            $table->string('ship_to', 20)->nullable();
            $table->foreignId('spbe_id')->constrained('spbes');
            $table->enum('jenis', ['reguler', 'fakultatif'])->default('reguler');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->integer('total_kuota');
            $table->enum('status', ['draft','aktif','selesai','batal'])->default('draft');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['valid_from','valid_to']);
        });

        Schema::create('kitir_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitir_id')->constrained('kitirs')->onDelete('cascade');
            $table->date('tanggal');
            $table->integer('kuota_tabung');
            $table->integer('harga_tebus')->default(0);
            $table->enum('status', ['belum_tebus','sudah_tebus','diambil'])->default('belum_tebus');
            $table->timestamps();
            $table->unique(['kitir_id', 'tanggal']);
        });
    }

    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('kitir_details');
        Schema::dropIfExists('kitirs');
        Schema::enableForeignKeyConstraints();
    }
};
