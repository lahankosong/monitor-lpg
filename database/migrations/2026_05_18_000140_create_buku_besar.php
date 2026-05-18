<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {

        // ── Master Akun ───────────────────────────────────────────
        if (!Schema::hasTable('akun_keuangan')) {
            Schema::create('akun_keuangan', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 10)->unique();
                $table->string('nama', 100);
                $table->enum('kelompok', [
                    'aset','kewajiban','modal','pendapatan','beban'
                ]);
                $table->enum('posisi_normal', ['debit','kredit']);
                $table->boolean('is_aktif')->default(true);
                $table->boolean('is_sistem')->default(false); // tidak bisa dihapus
                $table->integer('urutan')->default(0);
                $table->timestamps();
            });

            // Seeder akun default
            $akuns = [
                // ASET
                ['1001','Kas Kecil',           'aset','debit', true, 10],
                ['1002','Rekening Giro BRI',   'aset','debit', true, 20],
                ['1003','Piutang Dagang',       'aset','debit', true, 30],
                ['1004','Piutang Kerjasama',    'aset','debit', true, 40],
                ['1005','Persediaan Tabung',    'aset','debit', true, 50],
                // KEWAJIBAN
                ['2001','Utang ke Pertamina',   'kewajiban','kredit', true, 10],
                ['2002','Titipan Pangkalan',    'kewajiban','kredit', true, 20],
                ['2003','Utang ke Pemilik',     'kewajiban','kredit', true, 30],
                // MODAL
                ['3001','Modal Disetor Pemilik','modal','kredit', true, 10],
                ['3002','Prive / Penarikan',    'modal','debit',  true, 20],
                ['3003','Laba Ditahan',         'modal','kredit', true, 30],
                // PENDAPATAN
                ['4001','Penjualan Refil LPG',  'pendapatan','kredit', true, 10],
                ['4002','Uang Kerjasama Tabung','pendapatan','kredit', true, 20],
                // BEBAN
                ['5001','HPP Tebusan Pertamina','beban','debit', true, 10],
                ['5002','Beban BBM Armada',     'beban','debit', true, 20],
                ['5003','Beban Gaji Karyawan',  'beban','debit', true, 30],
                ['5004','Beban Servis Armada',  'beban','debit', true, 40],
                ['5005','Beban STNK & Pajak',   'beban','debit', true, 50],
                ['5006','Beban Operasional',    'beban','debit', true, 60],
                ['5007','Beban Lain-lain',      'beban','debit', true, 70],
            ];
            foreach ($akuns as [$kode,$nama,$kel,$pos,$sis,$urut]) {
                DB::table('akun_keuangan')->insert([
                    'kode'         => $kode,
                    'nama'         => $nama,
                    'kelompok'     => $kel,
                    'posisi_normal'=> $pos,
                    'is_sistem'    => $sis,
                    'urutan'       => $urut,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        // ── Jurnal Header ─────────────────────────────────────────
        if (!Schema::hasTable('jurnal_headers')) {
            Schema::create('jurnal_headers', function (Blueprint $table) {
                $table->id();
                $table->string('no_jurnal', 20)->unique();
                $table->date('tanggal');
                $table->string('keterangan', 255);
                $table->enum('modul', [
                    'modal_masuk',    // owner setor modal
                    'prive',          // owner tarik uang
                    'utang_pemilik',  // pinjaman dari/ke owner
                    'tebusan',        // bayar ke Pertamina
                    'distribusi',     // penjualan refil ke pangkalan
                    'brimola',        // pembayaran masuk dari pangkalan
                    'kerjasama',      // penerimaan uang kerjasama
                    'kas_kecil',      // pengeluaran operasional
                    'penyesuaian',    // jurnal manual/koreksi
                ])->default('penyesuaian');
                $table->string('referensi', 50)->nullable(); // no SJ, no SA, dll
                $table->unsignedBigInteger('referensi_id')->nullable();
                $table->boolean('is_otomatis')->default(false);
                $table->boolean('is_diverifikasi')->default(false);
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['tanggal','modul']);
            });
        }

        // ── Jurnal Detail (debit/kredit) ──────────────────────────
        if (!Schema::hasTable('jurnal_details')) {
            Schema::create('jurnal_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jurnal_id')
                      ->constrained('jurnal_headers')->cascadeOnDelete();
                $table->string('kode_akun', 10);
                $table->foreign('kode_akun')
                      ->references('kode')->on('akun_keuangan');
                $table->enum('posisi', ['debit','kredit']);
                $table->decimal('jumlah', 16, 0);
                $table->string('keterangan', 255)->nullable();
                $table->timestamps();
                $table->index('jurnal_id');
                $table->index('kode_akun');
            });
        }

        // ── Saldo Awal per Akun ───────────────────────────────────
        if (!Schema::hasTable('saldo_awal_akun')) {
            Schema::create('saldo_awal_akun', function (Blueprint $table) {
                $table->id();
                $table->string('kode_akun', 10);
                $table->foreign('kode_akun')
                      ->references('kode')->on('akun_keuangan');
                $table->date('per_tanggal');
                $table->decimal('saldo', 16, 0)->default(0);
                $table->text('keterangan')->nullable();
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['kode_akun','per_tanggal']);
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('saldo_awal_akun');
        Schema::dropIfExists('jurnal_details');
        Schema::dropIfExists('jurnal_headers');
        Schema::dropIfExists('akun_keuangan');
    }
};
