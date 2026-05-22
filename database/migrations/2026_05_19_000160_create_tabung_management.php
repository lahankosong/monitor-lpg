<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // ── 1. Master kepemilikan tabung kosong ───────────────────
        // Setiap kali beli tabung baru dari Pertamina → tambah di sini
        if (!Schema::hasTable('tabung_aset')) {
            Schema::create('tabung_aset', function (Blueprint $table) {
                $table->id();
                $table->date('tgl_beli');
                $table->integer('qty');                  // jumlah tabung beli
                $table->decimal('harga_per_tabung', 12, 0)->default(0);
                $table->string('no_faktur', 50)->nullable();
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // ── 2. Stok tabung kosong di gudang (buffer) ──────────────
        // Ledger masuk/keluar tabung KOSONG di gudang
        if (!Schema::hasTable('gudang_tabung_kosong')) {
            Schema::create('gudang_tabung_kosong', function (Blueprint $table) {
                $table->id();
                $table->enum('jenis', ['masuk','keluar']);
                $table->enum('sumber', [
                    'beli_pertamina',   // beli baru
                    'kembali_pangkalan',// pangkalan kembalikan tabung kosong
                    'kembali_armada',   // armada kembalikan sisa tabung kosong
                    'kembali_cabang',   // cabang lain kembalikan
                    'penyesuaian',      // opname/koreksi
                ]);
                $table->enum('tujuan', [
                    'ke_armada',        // armada ambil untuk DO
                    'pinjam_pangkalan', // dipinjamkan ke pangkalan
                    'pinjam_cabang',    // dipinjamkan ke cabang lain
                    'penyesuaian',      // opname/koreksi
                ])->nullable();        // null jika jenis = masuk

                $table->integer('qty');
                $table->date('tanggal');

                // Referensi
                $table->unsignedBigInteger('armada_id')->nullable();
                $table->unsignedBigInteger('pangkalan_id')->nullable();
                $table->unsignedBigInteger('sj_header_id')->nullable();
                $table->string('no_referensi', 50)->nullable();
                $table->text('keterangan')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tanggal','jenis']);
                $table->index(['sumber','tujuan']);
            });
        }

        // ── 3. Stok tabung ISI di gudang ──────────────────────────
        // Ledger masuk/keluar tabung ISI di gudang
        if (!Schema::hasTable('gudang_tabung_isi')) {
            Schema::create('gudang_tabung_isi', function (Blueprint $table) {
                $table->id();
                $table->enum('jenis', ['masuk','keluar']);
                $table->enum('sumber', [
                    'turun_armada',     // armada turunkan gendongan ke gudang
                    'penyesuaian',      // opname/koreksi
                ]);
                $table->enum('tujuan', [
                    'ke_armada',        // armada ambil tabung isi dari gudang
                    'penyesuaian',
                ])->nullable();

                $table->integer('qty');
                $table->date('tanggal');

                $table->unsignedBigInteger('armada_id')->nullable();
                $table->unsignedBigInteger('sj_header_id')->nullable();
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tanggal','jenis']);
            });
        }

        // ── 4. Pinjaman tabung ke pangkalan ───────────────────────
        // Surat perjanjian pinjaman tabung (diperbarui tiap tahun)
        if (!Schema::hasTable('pinjaman_tabung')) {
            Schema::create('pinjaman_tabung', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pangkalan_id')->constrained('pangkalans')->cascadeOnDelete();

                $table->enum('pihak', ['pangkalan','cabang'])->default('pangkalan');
                $table->string('nama_pihak', 100)->nullable(); // untuk cabang lain

                $table->integer('qty_pinjam');           // jumlah tabung dipinjamkan
                $table->integer('qty_kembali')->default(0); // sudah dikembalikan
                $table->integer('qty_aktif');            // qty_pinjam - qty_kembali

                $table->date('tgl_pinjam');
                $table->date('tgl_berlaku_sampai');      // biasanya +1 tahun
                $table->string('no_perjanjian', 50)->nullable();
                $table->enum('status', ['aktif','lunas','kadaluarsa'])->default('aktif');

                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['pangkalan_id','status']);
                $table->index(['tgl_berlaku_sampai','status']);
            });
        }

        // ── 5. Riwayat pengembalian pinjaman tabung ───────────────
        if (!Schema::hasTable('pinjaman_tabung_kembali')) {
            Schema::create('pinjaman_tabung_kembali', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pinjaman_id')
                      ->constrained('pinjaman_tabung')->cascadeOnDelete();
                $table->date('tanggal');
                $table->integer('qty');
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('pinjaman_tabung_kembali');
        Schema::dropIfExists('pinjaman_tabung');
        Schema::dropIfExists('gudang_tabung_isi');
        Schema::dropIfExists('gudang_tabung_kosong');
        Schema::dropIfExists('tabung_aset');
    }
};
