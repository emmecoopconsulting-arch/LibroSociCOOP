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
        Schema::create('socio_work_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->foreignId('verbale_id')->nullable()->constrained('verbales')->nullOnDelete();
            $table->enum('tipo_contratto', ['determinato', 'indeterminato']);
            $table->date('data_inizio')->nullable();
            $table->date('data_fine')->nullable();
            $table->decimal('ore_settimanali', 5, 2)->nullable();
            $table->enum('stato', ['attivo', 'cessato'])->default('attivo');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['socio_id', 'stato']);
            $table->index('data_fine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('socio_work_contracts');
    }
};
