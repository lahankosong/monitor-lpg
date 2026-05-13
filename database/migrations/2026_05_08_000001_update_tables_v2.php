<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // ── SPBE: hapus ship_to, no_rekening, nama_bank ──────────
        Schema::table('spbes', function (Blueprint $table) {
            $cols = ['no_rekening','nama_bank'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('spbes', $c)) $table->dropColumn($c);
            }
        });

        // ── PANGKALAN: tambah nama_pemilik, NIK, lat/lng, MAP creds ──
        Schema::table('pangkalans', function (Blueprint $table) {
            if (! Schema::hasColumn('pangkalans', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('alamat');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            // MAP scraping credentials
            if (! Schema::hasColumn('pangkalans', 'map_email')) {
                $table->string('map_email', 100)->nullable()->after('longitude');
                $table->text('map_pin_encrypted')->nullable()->after('map_email');
                $table->string('map_pangkalan_id', 100)->nullable()->after('map_pin_encrypted');
            }
        });

        // ── KARYAWAN: tambah role co-driver, security ─────────────
        // Ubah enum menjadi varchar agar bisa tambah nilai
        Schema::table('karyawans', function (Blueprint $table) {
            $table->string('role', 30)->default('admin')->change();
        });

        // ── ARMADA: hapus kapasitas, tambah kernet, dokumen, STNK ──
        Schema::table('armadas', function (Blueprint $table) {
            if (Schema::hasColumn('armadas', 'kapasitas')) {
                $table->dropColumn('kapasitas');
            }
            if (! Schema::hasColumn('armadas', 'kernet_id')) {
                $table->foreignId('kernet_id')->nullable()
                      ->constrained('karyawans')->nullOnDelete()
                      ->after('sopir_id');
            }
            if (! Schema::hasColumn('armadas', 'no_rangka')) {
                $table->string('no_rangka', 50)->nullable()->after('jenis');
                $table->string('no_mesin', 50)->nullable()->after('no_rangka');
                $table->year('tahun_pembuatan')->nullable()->after('no_mesin');
                // STNK
                $table->date('stnk_tahunan')->nullable()->after('tahun_pembuatan');    // pajak tahunan
                $table->date('stnk_5tahunan')->nullable()->after('stnk_tahunan');     // pajak 5 tahunan
            }
        });
    }

    public function down(): void {
        Schema::table('spbes', function (Blueprint $table) {
            $table->string('no_rekening', 50)->nullable();
            $table->string('nama_bank', 50)->nullable();
        });
        Schema::table('pangkalans', function (Blueprint $table) {
            $table->dropColumn(['latitude','longitude','map_email','map_pin_encrypted','map_pangkalan_id']);
        });
        Schema::table('karyawans', function (Blueprint $table) {
            $table->enum('role',['owner','direktur','manager','admin','driver'])->default('admin')->change();
        });
        Schema::table('armadas', function (Blueprint $table) {
            $table->integer('kapasitas')->default(0);
            $table->dropColumn(['kernet_id','no_rangka','no_mesin','tahun_pembuatan','stnk_tahunan','stnk_5tahunan']);
        });
    }
};
