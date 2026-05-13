<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('distribusi_realisasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sj_detail_id')->constrained('surat_jalan_details');
            $table->foreignId('header_id')->constrained('surat_jalan_headers');
            $table->foreignId('pangkalan_id')->constrained('pangkalans');
            $table->integer('qty_jadwal');
            $table->integer('qty_terima')->default(0);
            $table->integer('qty_sisa')->virtualAs('qty_jadwal - qty_terima');
            $table->enum('status_sisa',[
                'lunas','gendongan','gudang','alih_pangkalan'
            ])->default('lunas');
            $table->foreignId('alih_ke_pangkalan_id')
                ->nullable()
                ->constrained('pangkalans')
                ->nullOnDelete();
            $table->date('tanggal_realisasi');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['header_id','pangkalan_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('distribusi_realisasis'); }
};
