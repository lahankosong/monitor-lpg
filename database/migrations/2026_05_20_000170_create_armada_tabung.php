<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {

        // ── Tambah kolom layak_sampai ke armadas ──────────────────
        if (!Schema::hasColumn('armadas', 'layak_sampai')) {
            Schema::table('armadas', function (Blueprint $table) {
                $table->date('layak_sampai')->nullable()
                      ->after('tahun_pembuatan')
                      ->comment('Masa pakai tabung 10 tahun dari tahun pembuatan kendaraan');
                $table->integer('kapasitas_tabung')->default(560)
                      ->after('layak_sampai')
                      ->comment('Jumlah tabung kosong yang dialokasikan ke armada ini');
            });

            // Isi layak_sampai otomatis dari tahun_pembuatan + 10 tahun
            DB::statement("
                UPDATE armadas
                SET layak_sampai = DATE_ADD(
                    MAKEDATE(tahun_pembuatan, 1),
                    INTERVAL 10 YEAR
                )
                WHERE tahun_pembuatan IS NOT NULL
                  AND layak_sampai IS NULL
            ");
        }

        // ── Ledger alokasi tabung kosong ke armada ─────────────────
        // Berbeda dari stok_armada (per-trip), ini PERMANEN
        if (!Schema::hasTable('armada_tabung')) {
            Schema::create('armada_tabung', function (Blueprint $table) {
                $table->id();
                $table->foreignId('armada_id')
                      ->constrained('armadas')->cascadeOnDelete();

                $table->enum('jenis', ['masuk','keluar']);
                $table->enum('sumber', [
                    'alokasi_gudang',  // dari buffer gudang ke armada
                    'kembali_gudang',  // dikembalikan ke buffer (armada pensiun/rusak)
                    'serah_armada',    // pindah antar armada
                    'penyesuaian',
                ]);

                $table->integer('qty');
                $table->date('tanggal');
                $table->text('keterangan')->nullable();
                $table->string('no_referensi', 50)->nullable();

                $table->foreignId('created_by')
                      ->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['armada_id','jenis']);
                $table->index('tanggal');
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('armada_tabung');
        Schema::table('armadas', function (Blueprint $table) {
            $table->dropColumn(['layak_sampai','kapasitas_tabung']);
        });
    }
};
