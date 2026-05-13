<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pangkalans', function (Blueprint $table) {
            // Data pemilik
            $table->string('nama_pemilik', 100)->nullable()->after('nama_pangkalan');
            $table->string('nik_pemilik', 20)->nullable()->after('nama_pemilik');
            $table->string('foto_ktp_path', 255)->nullable()->after('nik_pemilik');
            $table->text('alamat_pemilik')->nullable()->after('foto_ktp_path');

            // Tipe pangkalan
            $table->enum('tipe', ['mandiri', 'kerjasama'])->default('mandiri')->after('telepon');
            $table->string('no_registrasi', 30)->nullable()->after('tipe');

            // Data tabung pinjaman (khusus kerjasama)
            $table->integer('jumlah_tabung_pinjaman')->default(0)->after('no_registrasi');
            $table->integer('harga_sewa_per_tabung')->default(0)->after('jumlah_tabung_pinjaman');
            $table->date('tanggal_mulai_pinjaman')->nullable()->after('harga_sewa_per_tabung');
            $table->integer('jangka_pinjaman_bulan')->default(12)->after('tanggal_mulai_pinjaman');
            $table->string('nomor_bukti_pinjaman', 50)->nullable()->after('jangka_pinjaman_bulan');
        });
    }
    public function down(): void {
        Schema::table('pangkalans', function (Blueprint $table) {
            $table->dropColumn([
                'nama_pemilik','nik_pemilik','foto_ktp_path','alamat_pemilik',
                'tipe','no_registrasi',
                'jumlah_tabung_pinjaman','harga_sewa_per_tabung',
                'tanggal_mulai_pinjaman','jangka_pinjaman_bulan','nomor_bukti_pinjaman',
            ]);
        });
    }
};
