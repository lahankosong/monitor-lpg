<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('brimolas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pangkalan_id')->constrained('pangkalans');
            $table->date('tanggal_bayar');
            $table->integer('qty_bayar');
            $table->string('no_briva', 50)->nullable();
            $table->bigInteger('jumlah_bayar')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['pangkalan_id','tanggal_bayar']);
        });
    }
    public function down(): void { Schema::dropIfExists('brimolas'); }
};
