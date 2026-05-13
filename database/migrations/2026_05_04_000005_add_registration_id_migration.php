<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah registration_id ke pangkalan_sessions
        Schema::table('pangkalan_sessions', function (Blueprint $table) {
            $table->string('registration_id', 50)->nullable()->after('pangkalan_id');
            $table->index('registration_id');
        });

        // Pastikan transactions punya pangkalan_id dan label pangkalan
        // (sudah ada dari migration awal, tapi tambah store_name)
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'store_name')) {
                $table->string('store_name', 100)->nullable()->after('pangkalan_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pangkalan_sessions', function (Blueprint $table) {
            $table->dropColumn('registration_id');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('store_name');
        });
    }
};
