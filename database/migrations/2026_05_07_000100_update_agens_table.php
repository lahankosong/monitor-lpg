<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('agens', function (Blueprint $table) {
            $table->string('ship_to', 30)->nullable()->after('sold_to');
            $table->string('nama_pimpinan', 100)->nullable()->after('email');
            $table->string('jabatan_pimpinan', 50)->nullable()->after('nama_pimpinan');
            $table->string('logo_path', 255)->nullable()->after('jabatan_pimpinan');
            $table->string('logo_elpiji_path', 255)->nullable()->after('logo_path');
        });
    }
    public function down(): void {
        Schema::table('agens', function (Blueprint $table) {
            $table->dropColumn(['ship_to','nama_pimpinan','jabatan_pimpinan','logo_path','logo_elpiji_path']);
        });
    }
};
