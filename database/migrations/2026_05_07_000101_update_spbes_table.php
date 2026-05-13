<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('spbes', 'ship_to')) {
            Schema::table('spbes', function (Blueprint $table) {
                $table->dropColumn('ship_to');
            });
        }
    }
    public function down(): void {
        Schema::table('spbes', function (Blueprint $table) {
            $table->string('ship_to', 30)->nullable();
        });
    }
};
