<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('agens', function (Blueprint $table) {
            $table->id();
            $table->string('nama_agen', 100);
            $table->string('kode_agen', 20)->nullable();
            $table->string('sold_to', 30)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('logo', 255)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('agens'); }
};
