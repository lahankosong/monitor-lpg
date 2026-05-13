<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Tabel pengalihan — bisa lebih dari 1 pangkalan per detail SJ
        if (!Schema::hasTable('sj_detail_pengalihan')) {
            Schema::create('sj_detail_pengalihan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sj_detail_id')->constrained('surat_jalan_details')->cascadeOnDelete();
                $table->foreignId('pangkalan_id')->constrained('pangkalans')->cascadeOnDelete();
                $table->integer('qty');
                $table->string('keterangan', 255)->nullable();
                $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('sj_detail_pengalihan');
    }
};
