<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('brimola_transaksi')) {
            Schema::create('brimola_transaksi', function (Blueprint $table) {
                $table->id();

                // Relasi ke pangkalan (nullable - match by nama jika no_briva tidak terdaftar)
                $table->foreignId('pangkalan_id')->nullable()
                      ->constrained('pangkalans')->nullOnDelete();

                // Data dari file BRImola
                $table->string('nama_pangkalan', 100);   // nama di file (tidak selalu match)
                $table->string('no_briva', 20)->unique(); // nomor virtual account BRI
                $table->dateTime('tanggal_bayar');
                $table->integer('jumlah_tabung');         // berapa tabung yang dibayar

                // Harga & total
                $table->decimal('harga_per_tabung', 10, 0)->default(0);
                $table->decimal('total_bayar', 12, 0)->default(0);

                // Status rekonsiliasi
                $table->enum('status', [
                    'unmatched',  // nama tidak cocok dengan pangkalan manapun
                    'matched',    // sudah dicocokkan ke pangkalan
                    'verified',   // sudah diverifikasi admin
                ])->default('unmatched');

                // Import tracking
                $table->unsignedBigInteger('import_batch_id')->nullable();
                $table->string('sumber_file', 100)->nullable();

                $table->timestamps();

                $table->index(['pangkalan_id','tanggal_bayar']);
                $table->index('tanggal_bayar');
                $table->index('status');
            });
        }

        // Tabel batch import untuk tracking
        if (!Schema::hasTable('brimola_import_batch')) {
            Schema::create('brimola_import_batch', function (Blueprint $table) {
                $table->id();
                $table->string('nama_file');
                $table->date('periode_dari');
                $table->date('periode_sampai');
                $table->integer('total_transaksi');
                $table->integer('total_matched')->default(0);
                $table->integer('total_unmatched')->default(0);
                $table->decimal('total_nilai', 14, 0)->default(0);
                $table->unsignedBigInteger('diimport_oleh')->nullable();
                $table->timestamps();
            });
        }

        // Tambah kolom no_briva ke pangkalans untuk matching otomatis
        Schema::table('pangkalans', function (Blueprint $table) {
            if (!Schema::hasColumn('pangkalans', 'no_briva'))
                $table->string('no_briva_prefix', 20)->nullable()->after('map_email')
                      ->comment('Prefix no BRIVA pangkalan untuk auto-match');
        });
    }

    public function down(): void {
        Schema::dropIfExists('brimola_transaksi');
        Schema::dropIfExists('brimola_import_batch');
        Schema::table('pangkalans', fn($t) => $t->dropColumn('no_briva_prefix'));
    }
};
