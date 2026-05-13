<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel utama transaksi — satu baris = satu pembelian tabung
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('customer_report_id', 128)->unique(); // ID unik dari API
            $table->string('nationality_id', 20);               // NIK (nationalityId)
            $table->string('name', 100);                        // Nama pembeli
            $table->string('category', 50)->default('Rumah Tangga'); // categories[0]
            $table->unsignedTinyInteger('total')->default(1);   // jumlah tabung
            $table->timestamp('transaction_at');                // createdAt dari API
            $table->date('transaction_date');                   // extracted date
            $table->string('pangkalan_id', 50)->nullable();     // ID pangkalan (sub JWT)
            $table->timestamps();

            $table->index('nationality_id');
            $table->index('transaction_date');
            $table->index(['nationality_id', 'transaction_date']);
        });

        // Rekap harian per pangkalan (summaryReport)
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('pangkalan_id', 50)->nullable();
            $table->date('summary_date');
            $table->unsignedInteger('sold')->default(0);        // tabung terjual
            $table->unsignedInteger('modal')->default(0);       // modal (Rp)
            $table->unsignedInteger('profit')->default(0);      // keuntungan (Rp)
            $table->unsignedInteger('gross')->default(0);       // omzet bruto (Rp)
            $table->timestamps();

            $table->unique(['pangkalan_id', 'summary_date']);
        });

        // Log scraping — rekam kapan scraper dijalankan dan hasilnya
        Schema::create('scrape_logs', function (Blueprint $table) {
            $table->id();
            $table->string('pangkalan_id', 50)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['success', 'failed', 'partial'])->default('success');
            $table->unsignedInteger('records_fetched')->default(0);
            $table->unsignedInteger('records_saved')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('scraped_at');
            $table->timestamps();
        });

        // Tabel violations — pelanggaran interval pembelian per NIK
        Schema::create('nik_violations', function (Blueprint $table) {
            $table->id();
            $table->string('nationality_id', 20);
            $table->string('name', 100);
            $table->date('prev_transaction_date');
            $table->date('curr_transaction_date');
            $table->unsignedTinyInteger('gap_days');            // jarak hari
            $table->unsignedTinyInteger('min_interval_days');   // threshold saat deteksi
            $table->enum('severity', ['warn', 'alert'])->default('warn');
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();

            $table->index('nationality_id');
            $table->index('severity');
        });

        // Token per pangkalan — simpan Bearer token dan expire time
        Schema::create('pangkalan_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('pangkalan_id', 50);
            $table->string('label', 100)->nullable();           // nama pangkalan
            $table->text('token');                              // Bearer token (JWT)
            $table->timestamp('token_issued_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('pangkalan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nik_violations');
        Schema::dropIfExists('scrape_logs');
        Schema::dropIfExists('daily_summaries');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('pangkalan_tokens');
    }
};
