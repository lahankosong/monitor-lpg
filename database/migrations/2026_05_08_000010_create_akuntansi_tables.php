<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // ── Harga Referensi (sudah ada, pastikan ada data ini) ───
        // Tabel harga_referensis sudah dibuat di migration sebelumnya

        // ── Tebusan Kitir (Akuntansi PSO) ────────────────────────
        // tebusan_kitirs & tebusan_kitir_details sudah ada
        // Tambah kolom selisih pembulatan
        if (Schema::hasTable('tebusan_kitirs') && !Schema::hasColumn('tebusan_kitirs', 'selisih_pembulatan')) {
            Schema::table('tebusan_kitirs', function (Blueprint $table) {
                $table->decimal('selisih_pembulatan', 10, 2)->default(0)->after('total_bayar');
                $table->decimal('total_bayar_aktual', 15, 2)->default(0)->after('selisih_pembulatan');
                // total_bayar_aktual = total_bayar + (selisih × jumlah tabung)
            });
        }

        // ── Jurnal Akuntansi (general ledger sederhana) ──────────
        Schema::create('jurnal_akuntansis', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->enum('modul', [
                'tebusan',        // saldo masuk/keluar tebusan ke SPBE
                'penjualan',      // pendapatan penjualan refil ke pangkalan
                'operasional_gaji',
                'operasional_armada',
                'operasional_kantor',
                'lain_lain',
                'modal',          // penarikan margin oleh owner
            ]);
            $table->enum('jenis', ['masuk', 'keluar']);
            $table->bigInteger('jumlah');
            $table->string('keterangan', 255);
            $table->string('referensi', 50)->nullable(); // no SA, no SJ, dll
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['tanggal', 'modul']);
        });

        // ── Pembayaran Refil dari Pangkalan (Brimola) ─────────────
        // brimolas sudah ada — tambah kolom yang kurang
        if (Schema::hasTable('brimolas') && !Schema::hasColumn('brimolas', 'metode_bayar')) {
            Schema::table('brimolas', function (Blueprint $table) {
                $table->enum('metode_bayar', ['cashless','tunai','transfer'])->default('cashless')->after('jumlah_bayar');
                $table->string('bukti_bayar', 255)->nullable()->after('metode_bayar');
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('jurnal_akuntansis');
        if (Schema::hasTable('tebusan_kitirs')) {
            Schema::table('tebusan_kitirs', function($t) {
                $t->dropColumn(['selisih_pembulatan','total_bayar_aktual']);
            });
        }
    }
};
