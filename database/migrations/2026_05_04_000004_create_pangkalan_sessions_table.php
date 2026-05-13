<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Simpan credentials + cookies session per pangkalan
        Schema::create('pangkalan_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('pangkalan_id', 50)->unique();
            $table->string('label', 100)->nullable();
            $table->string('username', 100)->nullable();      // nomor HP / email
            $table->text('password_encrypted')->nullable();   // password terenkripsi
            $table->longText('cookies')->nullable();          // cookies JSON dari browser
            $table->timestamp('cookies_captured_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangkalan_sessions');
    }
};
