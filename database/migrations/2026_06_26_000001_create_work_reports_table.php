<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_reports', function (Blueprint $table) {
            $table->id();
            $table->string('protocollo', 30)->unique();
            $table->date('data_intervento');
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('oggetto');
            $table->text('descrizione_lavoro')->nullable();
            $table->string('rapportino_path');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['data_intervento', 'work_site_id']);
            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_reports');
    }
};
