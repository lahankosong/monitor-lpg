<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pangkalans', function (Blueprint $table) {
            $table->id();
            $table->string('no_reg', 20)->unique();
            $table->string('nama_pangkalan', 100);
            $table->string('alamat', 255)->nullable();
            $table->string('telepon', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pangkalans'); }
};
