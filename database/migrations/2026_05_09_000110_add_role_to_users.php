<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {

        // Tambah kolom role & status ke tabel users yang sudah ada
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role'))
                $table->enum('role', ['direktur','manajer','admin','driver'])
                      ->default('admin')->after('email');
            if (!Schema::hasColumn('users', 'is_active'))
                $table->boolean('is_active')->default(true)->after('role');
            if (!Schema::hasColumn('users', 'last_login_at'))
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            if (!Schema::hasColumn('users', 'karyawan_id'))
                $table->unsignedBigInteger('karyawan_id')->nullable()->after('last_login_at');
        });

        // Seeder user awal — hanya jika belum ada
        if (DB::table('users')->count() === 0) {
            DB::table('users')->insert([
                [
                    'name'              => 'Direktur',
                    'email'             => 'direktur@lpgmonitor.local',
                    'password'          => Hash::make('direktur123'),
                    'role'              => 'direktur',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ],
                [
                    'name'              => 'Manajer',
                    'email'             => 'manajer@lpgmonitor.local',
                    'password'          => Hash::make('manajer123'),
                    'role'              => 'manajer',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ],
                [
                    'name'              => 'Admin',
                    'email'             => 'admin@lpgmonitor.local',
                    'password'          => Hash::make('admin123'),
                    'role'              => 'admin',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ],
            ]);
        }
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role','is_active','last_login_at','karyawan_id']);
        });
    }
};
