<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('spbes', function (Blueprint $table) {
            $table->id();
            $table->string('kode_spbe', 20)->unique();
            $table->string('nama_spbe', 100);
            $table->string('ship_to', 30)->nullable();
            $table->string('kode_plant', 20)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('no_rekening', 50)->nullable();
            $table->string('nama_bank', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('spbes'); }
};
