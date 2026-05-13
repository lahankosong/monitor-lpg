<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus duplikat terlebih dahulu
        DB::statement('
            DELETE t1 FROM transactions t1
            INNER JOIN transactions t2
            WHERE t1.id > t2.id
              AND t1.nationality_id = t2.nationality_id
              AND t1.name           = t2.name
              AND t1.transaction_at = t2.transaction_at
        ');

        // Hapus index lama jika ada (raw SQL, tidak error jika tidak ada)
        DB::statement("
            DROP INDEX IF EXISTS transactions_customer_report_id_unique
            ON transactions
        ");

        // Hapus index baru jika sudah ada (hindari duplikat)
        DB::statement("
            DROP INDEX IF EXISTS uniq_person_time
            ON transactions
        ");

        // Buat unique constraint yang benar
        DB::statement("
            ALTER TABLE transactions
            ADD UNIQUE INDEX uniq_person_time (nationality_id, name, transaction_at)
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS uniq_person_time ON transactions");
    }
};