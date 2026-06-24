<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_vehicles')) {
            Schema::create('work_vehicles', function (Blueprint $table) {
                $table->id();
                $table->string('nome');
                $table->enum('tipo', ['proprio', 'ditta'])->default('ditta');
                $table->string('targa')->nullable();
                $table->boolean('attivo')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['attivo', 'tipo']);
            });
        }

        if (! Schema::hasTable('work_orders')) {
            Schema::create('work_orders', function (Blueprint $table) {
                $table->id();
                $table->date('data_servizio');
                $table->string('titolo');
                $table->enum('stato', ['bozza', 'archiviato'])->default('bozza');
                $table->text('note')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamp('archiviato_il')->nullable();
                $table->timestamps();

                $table->index(['data_servizio', 'stato']);
            });
        }

        if (! Schema::hasTable('work_sites')) {
            Schema::create('work_sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('work_vehicle_id')->nullable()->constrained()->nullOnDelete();
                $table->string('nome');
                $table->string('luogo');
                $table->time('orario_inizio');
                $table->time('orario_fine');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['work_order_id', 'orario_inizio', 'orario_fine']);
            });
        }

        if (! Schema::hasTable('work_site_assignments')) {
            Schema::create('work_site_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_site_id')->constrained()->cascadeOnDelete();
                $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['work_site_id', 'socio_id']);
            });
        }

        if (! Schema::hasTable('work_absences')) {
            Schema::create('work_absences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
                $table->enum('tipo', ['malattia', 'ferie', 'permesso', 'altra']);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['work_order_id', 'socio_id']);
                $table->index(['work_order_id', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_absences');
        Schema::dropIfExists('work_site_assignments');
        Schema::dropIfExists('work_sites');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('work_vehicles');
    }
};
