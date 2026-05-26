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
        Schema::create('verbales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->string('tipo', 50);
            $table->enum('stato', ['da_generare', 'generato'])->default('da_generare');
            $table->string('titolo');
            $table->date('data_verbale');
            $table->string('file_path')->nullable();
            $table->timestamp('generato_il')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'stato']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verbales');
    }
};
