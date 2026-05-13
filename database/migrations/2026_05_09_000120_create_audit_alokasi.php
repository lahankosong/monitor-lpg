<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // ── Tabel alokasi pembayaran ke distribusi ─────────────────
        // 1 distribusi bisa dialokasi dari N BRIVA (saat BRIVA terpakai sebagian)
        // 1 BRIVA bisa terpakai untuk N distribusi (saat sisa BRIVA besar)
        if (!Schema::hasTable('audit_distribusi_bayar')) {
            Schema::create('audit_distribusi_bayar', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sj_detail_id')
                      ->constrained('surat_jalan_details')->cascadeOnDelete();
                $table->foreignId('brimola_trx_id')
                      ->constrained('brimola_transaksi')->cascadeOnDelete();
                $table->foreignId('pangkalan_id')
                      ->constrained('pangkalans')->cascadeOnDelete();

                $table->integer('qty_dialokasi');  // berapa tabung dari BRIVA ini

                $table->enum('tipe_alokasi', ['otomatis_fifo','manual'])
                      ->default('otomatis_fifo');

                // Timestamp untuk ordering FIFO
                $table->date('tanggal_distribusi');
                $table->dateTime('tanggal_bayar');

                $table->text('catatan')->nullable();
                $table->unsignedBigInteger('dialokasi_oleh')->nullable();
                $table->timestamps();

                $table->index(['pangkalan_id','tanggal_distribusi']);
                $table->index('brimola_trx_id');
                $table->index('sj_detail_id');
            });
        }

        // ── Tabel saldo pangkalan (cache untuk performa) ───────────
        // Recalculate setiap kali ada perubahan distribusi/BRIVA
        if (!Schema::hasTable('saldo_pangkalan')) {
            Schema::create('saldo_pangkalan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pangkalan_id')->unique()
                      ->constrained('pangkalans')->cascadeOnDelete();

                $table->integer('total_dibayar')->default(0);     // Σ tabung dari BRIVA
                $table->integer('total_didistribusi')->default(0); // Σ tabung terdistribusi
                $table->integer('saldo_tabung')->default(0);       // selisih (kredit jika +, piutang jika -)

                $table->decimal('total_nilai_bayar', 14, 0)->default(0);
                $table->decimal('total_nilai_distribusi', 14, 0)->default(0);
                $table->decimal('saldo_nilai', 14, 0)->default(0);

                $table->enum('status', ['lunas','saldo_kredit','piutang'])->default('lunas');
                $table->timestamp('last_calculated_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        // ── Tambah kolom sisa_terpakai di brimola_transaksi ────────
        // Untuk tracking berapa tabung dari BRIVA ini yg sudah dialokasi
        Schema::table('brimola_transaksi', function (Blueprint $table) {
            if (!Schema::hasColumn('brimola_transaksi', 'qty_terpakai'))
                $table->integer('qty_terpakai')->default(0)->after('jumlah_tabung');
            if (!Schema::hasColumn('brimola_transaksi', 'qty_sisa'))
                $table->integer('qty_sisa')->default(0)->after('qty_terpakai')
                      ->comment('jumlah_tabung - qty_terpakai');
        });
    }

    public function down(): void {
        Schema::dropIfExists('audit_distribusi_bayar');
        Schema::dropIfExists('saldo_pangkalan');
        Schema::table('brimola_transaksi', function (Blueprint $table) {
            $table->dropColumn(['qty_terpakai','qty_sisa']);
        });
    }
};
