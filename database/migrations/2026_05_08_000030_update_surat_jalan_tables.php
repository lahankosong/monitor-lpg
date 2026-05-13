<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // ── surat_jalan_headers ───────────────────────────────────
        Schema::table('surat_jalan_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('surat_jalan_headers', 'no_lo')) {
                $table->string('no_lo', 30)->nullable()->after('no_sj');
            }
            if (!Schema::hasColumn('surat_jalan_headers', 'nomor_urut')) {
                $table->tinyInteger('nomor_urut')->default(1)->after('no_sj');
            }
            if (!Schema::hasColumn('surat_jalan_headers', 'kitir_detail_id')) {
                $table->unsignedBigInteger('kitir_detail_id')->nullable()->after('kitir_id');
                $table->foreign('kitir_detail_id')->references('id')->on('kitir_details')->nullOnDelete();
            }
            if (!Schema::hasColumn('surat_jalan_headers', 'kernet_id')) {
                $table->unsignedBigInteger('kernet_id')->nullable()->after('sopir_id');
                $table->foreign('kernet_id')->references('id')->on('karyawans')->nullOnDelete();
            }
            if (!Schema::hasColumn('surat_jalan_headers', 'qty_refil')) {
                $table->integer('qty_refil')->default(0)->after('total_terjadwal');
            }
            if (!Schema::hasColumn('surat_jalan_headers', 'qty_tabung_baru')) {
                $table->integer('qty_tabung_baru')->default(0)->after('qty_refil');
            }
            if (!Schema::hasColumn('surat_jalan_headers', 'alasan_batal')) {
                $table->text('alasan_batal')->nullable()->after('status');
            }
        });

        // ── surat_jalan_details ───────────────────────────────────
        Schema::table('surat_jalan_details', function (Blueprint $table) {
            if (!Schema::hasColumn('surat_jalan_details', 'qty_terima')) {
                $table->integer('qty_terima')->default(0)->after('qty_jadwal');
            }
            if (!Schema::hasColumn('surat_jalan_details', 'status')) {
                $table->enum('status', [
                    'terjadwal','terkirim','sebagian','dialihkan','batal'
                ])->default('terjadwal')->after('qty_terima');
            }
            if (!Schema::hasColumn('surat_jalan_details', 'qty_dialihkan')) {
                $table->integer('qty_dialihkan')->default(0)->after('status');
            }
            if (!Schema::hasColumn('surat_jalan_details', 'dialih_ke_pangkalan_id')) {
                $table->unsignedBigInteger('dialih_ke_pangkalan_id')->nullable()->after('qty_dialihkan');
                $table->foreign('dialih_ke_pangkalan_id')->references('id')->on('pangkalans')->nullOnDelete();
            }
            if (!Schema::hasColumn('surat_jalan_details', 'keterangan')) {
                $table->string('keterangan', 255)->nullable()->after('dialih_ke_pangkalan_id');
            }
        });
    }

    public function down(): void {
        Schema::table('surat_jalan_headers', function (Blueprint $table) {
            $table->dropColumn(['no_lo','nomor_urut','kitir_detail_id','kernet_id',
                               'qty_refil','qty_tabung_baru','alasan_batal']);
        });
        Schema::table('surat_jalan_details', function (Blueprint $table) {
            $table->dropColumn(['qty_terima','status','qty_dialihkan',
                               'dialih_ke_pangkalan_id','keterangan']);
        });
    }
};
