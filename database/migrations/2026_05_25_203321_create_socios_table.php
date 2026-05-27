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
        Schema::create('socios', function (Blueprint $table) {
            $table->id();
            $table->string('codice_socio', 20)->unique();
            $table->enum('tipologia', ['fondatore', 'volontario', 'ordinario']);
            $table->string('nome');
            $table->string('cognome');
            $table->string('codice_fiscale', 16)->unique();
            $table->date('data_nascita')->nullable();
            $table->string('luogo_nascita')->nullable();
            $table->foreignId('comune_nascita_id')->nullable()->constrained('comunes')->nullOnDelete();
            $table->foreignId('comune_residenza_id')->nullable()->constrained('comunes')->nullOnDelete();
            $table->string('comune_residenza')->nullable();
            $table->string('indirizzo')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->date('data_ammissione')->nullable();
            $table->enum('stato', ['attivo', 'recesso', 'escluso', 'sospeso'])->default('attivo');
            $table->date('data_uscita')->nullable();
            $table->decimal('quota_sociale', 10, 2)->default(0);
            $table->decimal('capitale_versato', 10, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stato', 'tipologia']);
            $table->index('data_ammissione');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('socios');
    }
};
