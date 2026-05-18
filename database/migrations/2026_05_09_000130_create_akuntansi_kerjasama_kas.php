<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // ── Piutang Kerjasama ─────────────────────────────────────
        // Otomatis terbentuk setiap SJ ditutup untuk pangkalan kerjasama
        // Ditagih awal bulan depan, dilunasi saat pangkalan bayar
        if (!Schema::hasTable('piutang_kerjasama')) {
            Schema::create('piutang_kerjasama', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pangkalan_id')->constrained('pangkalans')->cascadeOnDelete();
                $table->foreignId('sj_header_id')->constrained('surat_jalan_headers')->cascadeOnDelete();

                $table->date('tanggal_distribusi');
                $table->date('bulan_tagih');          // awal bulan berikutnya
                $table->date('jatuh_tempo');          // biasanya tgl 5 bulan berikutnya

                $table->integer('qty_tabung');        // jumlah tabung kerjasama
                $table->decimal('harga_per_tabung', 10, 0);  // dari referensi harga
                $table->decimal('total_tagihan', 14, 0);     // qty × harga

                $table->decimal('total_bayar', 14, 0)->default(0);
                $table->decimal('sisa_tagihan', 14, 0)->default(0); // total_tagihan - total_bayar

                $table->enum('status', ['belum_bayar','sebagian','lunas'])->default('belum_bayar');
                $table->date('tanggal_lunas')->nullable();
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['pangkalan_id', 'bulan_tagih']);
                $table->index(['status', 'jatuh_tempo']);
            });
        }

        // ── Pembayaran Piutang Kerjasama ──────────────────────────
        // 1 piutang bisa dibayar sebagian-sebagian
        if (!Schema::hasTable('piutang_kerjasama_bayar')) {
            Schema::create('piutang_kerjasama_bayar', function (Blueprint $table) {
                $table->id();
                $table->foreignId('piutang_id')
                      ->constrained('piutang_kerjasama')->cascadeOnDelete();
                $table->foreignId('pangkalan_id')->constrained('pangkalans')->cascadeOnDelete();
                $table->date('tanggal_bayar');
                $table->decimal('jumlah', 14, 0);
                $table->enum('metode', ['tunai','transfer','briva'])->default('tunai');
                $table->string('referensi', 50)->nullable(); // no. transfer, no. BRIVA
                $table->text('keterangan')->nullable();
                $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['piutang_id','tanggal_bayar']);
            });
        }

        // ── Kas Kecil / Operasional ───────────────────────────────
        // Pengeluaran operasional harian: BBM, gaji, servis, dll
        if (!Schema::hasTable('kas_kecil')) {
            Schema::create('kas_kecil', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal');
                $table->enum('kategori', [
                    'bbm_armada',       // BBM kendaraan
                    'gaji_karyawan',    // gaji driver, kernet, admin
                    'servis_armada',    // servis, suku cadang, ban
                    'stnk_pajak',       // STNK, pajak kendaraan
                    'kantor',           // ATK, listrik, internet, sewa
                    'tabung',           // pembelian/perbaikan tabung
                    'lain_lain',        // lainnya
                ]);
                $table->string('keterangan', 255);
                $table->decimal('jumlah', 14, 0);
                $table->enum('jenis', ['keluar','masuk'])->default('keluar');
                // masuk = penerimaan kas kecil (isi kas), keluar = pengeluaran
                $table->string('bukti_foto', 255)->nullable();
                $table->unsignedBigInteger('armada_id')->nullable(); // jika terkait armada
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tanggal','kategori']);
            });
        }

        // ── Saldo Kas Kecil ───────────────────────────────────────
        // Cache saldo agar tidak perlu SUM tiap request
        if (!Schema::hasTable('kas_kecil_saldo')) {
            Schema::create('kas_kecil_saldo', function (Blueprint $table) {
                $table->id();
                $table->decimal('saldo_awal', 14, 0)->default(0);
                $table->decimal('total_masuk', 14, 0)->default(0);
                $table->decimal('total_keluar', 14, 0)->default(0);
                $table->decimal('saldo_akhir', 14, 0)->default(0);
                $table->date('per_tanggal');
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('piutang_kerjasama_bayar');
        Schema::dropIfExists('piutang_kerjasama');
        Schema::dropIfExists('kas_kecil_saldo');
        Schema::dropIfExists('kas_kecil');
    }
};
