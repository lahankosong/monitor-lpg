<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('armadas', function (Blueprint $table) {
            $table->id();
            $table->string('no_polisi', 20)->unique();
            $table->string('jenis', 50)->nullable();
            $table->integer('kapasitas')->default(0);
            $table->foreignId('sopir_id')->nullable()->constrained('karyawans')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('armadas'); }
};
