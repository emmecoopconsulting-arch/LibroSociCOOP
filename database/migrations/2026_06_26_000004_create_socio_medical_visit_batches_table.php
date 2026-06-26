<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('socio_medical_visit_batches')) {
            Schema::create('socio_medical_visit_batches', function (Blueprint $table) {
                $table->id();
                $table->date('data_visita');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('data_visita');
            });
        }

        if (! Schema::hasColumn('socio_medical_visits', 'batch_id')) {
            Schema::table('socio_medical_visits', function (Blueprint $table) {
                $table->foreignId('batch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('socio_medical_visit_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('socio_medical_visits', 'batch_id')) {
            Schema::table('socio_medical_visits', function (Blueprint $table) {
                $table->dropConstrainedForeignId('batch_id');
            });
        }

        Schema::dropIfExists('socio_medical_visit_batches');
    }
};
