<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // ── 1. stok_armada ─────────────────────────────────────────────
        // Satu record per armada per trip (per SJ)
        // Sisa_akhir = gendongan yg wajib habis sebelum ambil DO berikutnya
        if (!Schema::hasTable('stok_armada')) {
            Schema::create('stok_armada', function (Blueprint $table) {
                $table->id();
                $table->foreignId('armada_id')->constrained('armadas')->cascadeOnDelete();
                $table->foreignId('sj_header_id')->constrained('surat_jalan_headers')->cascadeOnDelete();
                $table->date('tanggal');

                // Tiga sumber input
                $table->integer('gendongan_masuk')->default(0); // sisa_akhir trip sebelumnya
                $table->integer('ambil_do')->default(0);        // qty_refil dari SJ
                $table->integer('ambil_gudang')->default(0);    // diambil dari gudang_stok

                // Hasil distribusi
                $table->integer('total_tersedia')->storedAs(
                    'gendongan_masuk + ambil_do + ambil_gudang'
                );
                $table->integer('total_terkirim')->default(0);  // Σ qty_terima semua pangkalan
                $table->integer('turun_gudang')->default(0);    // sengaja disimpan ke gudang

                // Sisa akhir = gendongan untuk trip berikutnya
                $table->integer('sisa_akhir')->default(0);      // total_tersedia - total_terkirim - turun_gudang
                $table->enum('status', ['jalan','selesai','ada_sisa'])->default('jalan');
                $table->timestamps();

                $table->unique(['armada_id','sj_header_id']);
                $table->index(['armada_id','tanggal']);
            });
        }

        // ── 2. gudang_stok ─────────────────────────────────────────────
        // Ledger masuk/keluar stok gudang agen
        // Sumber masuk: sisa SJ (turun_gudang) atau titipan agen lain
        if (!Schema::hasTable('gudang_stok')) {
            Schema::create('gudang_stok', function (Blueprint $table) {
                $table->id();

                // Milik agen mana — support multi-tenant & titip agen lain
                $table->foreignId('agen_id')->constrained('agens')->cascadeOnDelete();

                // Asal tabung
                $table->enum('sumber', [
                    'sisa_sj',       // dari sisa distribusi yang sengaja diturunkan
                    'titipan_agen',  // agen lain titipkan tabung isi di gudang ini
                    'manual',        // input manual admin
                ])->default('sisa_sj');

                // Referensi ke sumber
                $table->unsignedBigInteger('sj_header_id')->nullable();  // jika sumber = sisa_sj
                $table->unsignedBigInteger('agen_asal_id')->nullable();  // jika sumber = titipan_agen
                $table->foreign('sj_header_id')->references('id')
                      ->on('surat_jalan_headers')->nullOnDelete();
                $table->foreign('agen_asal_id')->references('id')
                      ->on('agens')->nullOnDelete();

                $table->date('tgl_masuk');
                $table->integer('qty_masuk');
                $table->integer('qty_keluar')->default(0);
                $table->integer('sisa_stok');  // diupdate setiap ada pengambilan
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index(['agen_id','sisa_stok']);
            });
        }

        // ── 3. sj_sisa_distribusi ──────────────────────────────────────
        // TABEL PUSAT — setiap "nasib sisa" dari satu pangkalan = satu baris
        // Validasi: SUM(qty) per sj_detail_id = qty_jadwal - qty_terima
        if (!Schema::hasTable('sj_sisa_distribusi')) {
            Schema::create('sj_sisa_distribusi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sj_detail_id')
                      ->constrained('surat_jalan_details')->cascadeOnDelete();
                $table->integer('qty');  // berapa tabung yang "diperlakukan" dengan cara ini

                $table->enum('tipe', [
                    'alih_pangkalan',  // dialihkan ke pangkalan lain (→ sj_pengalihan)
                    'stok_armada',     // tetap di armada (gendongan)
                    'gudang_sendiri',  // turun ke gudang agen ini
                    'titip_agen_lain', // dititipkan ke gudang agen lain
                ]);

                // Polymorphic reference ke tabel hasil
                // alih_pangkalan → sj_pengalihan.id
                // stok_armada    → stok_armada.id
                // gudang_sendiri → gudang_stok.id
                // titip_agen_lain→ transaksi_antar_agen.id
                $table->unsignedBigInteger('referensi_id')->nullable();
                $table->string('referensi_tipe', 60)->nullable();

                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index(['sj_detail_id','tipe']);
            });
        }

        // ── 4. sj_pengalihan ───────────────────────────────────────────
        // Detail pengalihan ke pangkalan lain (1 record per pengalihan)
        if (!Schema::hasTable('sj_pengalihan')) {
            Schema::create('sj_pengalihan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sj_detail_id')   // dari detail SJ pangkalan asal
                      ->constrained('surat_jalan_details')->cascadeOnDelete();
                $table->foreignId('pangkalan_id')   // pangkalan tujuan pengalihan
                      ->constrained('pangkalans')->cascadeOnDelete();
                $table->integer('qty');              // qty yang dialihkan
                $table->integer('qty_terima_aktual')->default(0); // berapa yang benar-benar diterima
                $table->tinyInteger('urutan')->default(1);        // urutan pengalihan (1,2,3...)
                $table->enum('status', ['pending','terima','batal'])->default('pending');
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index(['sj_detail_id','urutan']);
            });
        }

        // ── 5. transaksi_antar_agen ────────────────────────────────────
        // Agen A titip tabung isi di gudang Agen B + pinjam tabung kosong dari B
        if (!Schema::hasTable('transaksi_antar_agen')) {
            Schema::create('transaksi_antar_agen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agen_asal_id')    // agen yang punya tabung isi
                      ->constrained('agens')->cascadeOnDelete();
                $table->foreignId('agen_tujuan_id')  // agen yang menerima titipan
                      ->constrained('agens')->cascadeOnDelete();

                // Tabung isi dititipkan ke gudang agen tujuan
                $table->integer('qty_tabung_isi');

                // Tabung kosong dipinjam dari agen tujuan (boleh beda jumlah)
                $table->integer('qty_tabung_kosong')->default(0);

                $table->date('tgl_titip');
                $table->date('tgl_ambil_kembali')->nullable(); // null = belum diambil
                $table->enum('status', ['aktif','selesai','sebagian'])->default('aktif');
                $table->unsignedBigInteger('gudang_stok_id')->nullable(); // record di gudang tujuan
                $table->foreign('gudang_stok_id')->references('id')
                      ->on('gudang_stok')->nullOnDelete();
                $table->unsignedBigInteger('sj_header_id')->nullable(); // SJ asal
                $table->foreign('sj_header_id')->references('id')
                      ->on('surat_jalan_headers')->nullOnDelete();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index(['agen_asal_id','status']);
                $table->index(['agen_tujuan_id','status']);
            });
        }

        // ── 6. Tambah kolom ke surat_jalan_headers ────────────────────
        Schema::table('surat_jalan_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('surat_jalan_headers', 'qty_gendongan_masuk'))
                $table->integer('qty_gendongan_masuk')->default(0)->after('qty_tabung_baru');
            if (!Schema::hasColumn('surat_jalan_headers', 'qty_ambil_gudang'))
                $table->integer('qty_ambil_gudang')->default(0)->after('qty_gendongan_masuk');
        });
    }

    public function down(): void {
        Schema::dropIfExists('transaksi_antar_agen');
        Schema::dropIfExists('sj_sisa_distribusi');
        Schema::dropIfExists('sj_pengalihan');
        Schema::dropIfExists('gudang_stok');
        Schema::dropIfExists('stok_armada');
        Schema::table('surat_jalan_headers', function (Blueprint $table) {
            $table->dropColumn(['qty_gendongan_masuk','qty_ambil_gudang']);
        });
    }
};
