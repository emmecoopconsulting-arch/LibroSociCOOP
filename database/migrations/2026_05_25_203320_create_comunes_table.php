<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comunes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('progressivo')->nullable();
            $table->string('denominazione');
            $table->string('ripartizione_geografica')->nullable();
            $table->string('regione')->nullable();
            $table->string('provincia_unita_territoriale')->nullable();
            $table->string('codice_catastale', 4)->unique();
            $table->timestamps();

            $table->index('denominazione');
            $table->index(['regione', 'provincia_unita_territoriale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comunes');
    }
};
