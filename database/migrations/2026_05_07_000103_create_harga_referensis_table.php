<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('harga_referensis', function (Blueprint $table) {
            $table->id();
            $table->string('nama_item', 100);
            // mis: Harga Tebus Refil, Harga Jual Pangkalan, Tabung Perdana, Sewa Tabung
            $table->enum('kategori', [
                'tebus_refil',
                'jual_pangkalan',
                'tabung_perdana',
                'sewa_tabung',
                'lainnya',
            ])->default('lainnya');
            $table->bigInteger('harga');
            $table->string('satuan', 30)->default('tabung');
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['kategori','berlaku_mulai']);
        });
    }
    public function down(): void { Schema::dropIfExists('harga_referensis'); }
};
