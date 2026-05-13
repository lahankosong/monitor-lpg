<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('surat_jalan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('header_id')->constrained('surat_jalan_headers')->onDelete('cascade');
            $table->foreignId('pangkalan_id')->constrained('pangkalans');
            $table->integer('qty_jadwal');
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('surat_jalan_details'); }
};
