<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socio_medical_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->date('data_visita');
            $table->date('scadenza');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['socio_id', 'scadenza']);
            $table->index('scadenza');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socio_medical_visits');
    }
};
