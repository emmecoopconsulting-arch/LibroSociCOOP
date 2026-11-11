<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assemblee', function (Blueprint $table): void {
            $table->string('tipo_assemblea', 20)->default('soci')->after('data_assemblea');
        });

        Schema::create('socio_variation_cda_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('socio_variation_id')->constrained('socio_variations')->cascadeOnDelete();
            $table->foreignId('assemblea_id')->constrained('assemblee')->cascadeOnDelete();
            $table->string('decisione', 20);
            $table->date('data_delibera');
            $table->timestamps();
            $table->unique(['socio_variation_id', 'assemblea_id'], 'variation_cda_decision_unique');
        });

        Schema::table('socio_variations', function (Blueprint $table): void {
            $table->foreignId('cda_assemblea_id')
                ->nullable()
                ->after('assemblea_id')
                ->constrained('assemblee')
                ->nullOnDelete();
            $table->string('decisione_cda', 20)->nullable()->after('cda_assemblea_id');
            $table->date('data_delibera_cda')->nullable()->after('decisione_cda');
            $table->string('approvazione_cda', 20)->default('storica')->after('stato');
            $table->index(['approvazione_cda', 'cda_assemblea_id'], 'socio_variations_cda_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::table('socio_variations', function (Blueprint $table): void {
            $table->dropIndex('socio_variations_cda_workflow_idx');
            $table->dropConstrainedForeignId('cda_assemblea_id');
            $table->dropColumn(['decisione_cda', 'data_delibera_cda', 'approvazione_cda']);
        });

        Schema::table('assemblee', function (Blueprint $table): void {
            $table->dropColumn('tipo_assemblea');
        });

        Schema::dropIfExists('socio_variation_cda_decisions');
    }
};
