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
        Schema::create('socio_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->foreignId('verbale_id')->nullable()->constrained('verbales')->nullOnDelete();
            $table->string('tipo', 50);
            $table->enum('stato', ['bozza', 'applicata'])->default('applicata');
            $table->date('data_verbale');
            $table->date('data_effetto');
            $table->enum('tipo_contratto', ['determinato', 'indeterminato'])->nullable();
            $table->date('data_inizio')->nullable();
            $table->date('data_fine')->nullable();
            $table->decimal('ore_settimanali', 5, 2)->nullable();
            $table->json('snapshot_precedente')->nullable();
            $table->json('snapshot_successivo')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['socio_id', 'tipo']);
            $table->index('data_effetto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('socio_variations');
    }
};
