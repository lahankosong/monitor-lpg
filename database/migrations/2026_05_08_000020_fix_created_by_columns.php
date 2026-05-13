<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Hapus FK jika masih ada, lalu jadikan nullable
        foreach (['tebusan_kitirs' => 'tebusan_kitirs_created_by_foreign',
                  'jurnal_akuntansis' => 'jurnal_akuntansis_created_by_foreign'] as $table => $fk) {
            if (!Schema::hasTable($table)) continue;

            // Cek apakah FK masih ada
            $fkExists = DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$table}'
                AND CONSTRAINT_NAME = '{$fk}'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");

            Schema::table($table, function (Blueprint $t) use ($fk, $fkExists) {
                if (!empty($fkExists)) {
                    $t->dropForeign($fk);
                }
                $t->unsignedBigInteger('created_by')->nullable()->change();
            });
        }
    }
    public function down(): void {}
};
