<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah tabel stok harian per pangkalan
        Schema::create('pangkalan_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('pangkalan_id', 50);
            $table->string('store_name', 100)->nullable();
            $table->string('registration_id', 50)->nullable();
            $table->unsignedInteger('stock_available')->default(0);  // sisa stok
            $table->unsignedInteger('stock_redeem')->default(0);     // total ditebus
            $table->unsignedInteger('sold')->default(0);             // terjual
            $table->string('stock_date', 30)->nullable();            // "5 Mei 2026"
            $table->unsignedInteger('last_stock')->default(0);       // stok bulan lalu
            $table->string('last_stock_date', 20)->nullable();       // "Apr 2026"
            $table->date('recorded_at');
            $table->timestamps();

            $table->unique(['pangkalan_id', 'recorded_at']);
            $table->index('pangkalan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangkalan_stocks');
    }
};
