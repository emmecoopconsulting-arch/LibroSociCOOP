<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assemblee', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable()->after('stato');
            $table->timestamp('closed_at')->nullable()->after('started_at');
            $table->string('presidente')->nullable()->after('note');
            $table->string('segretario')->nullable()->after('presidente');
            $table->string('luogo')->nullable()->after('segretario');
            $table->string('modalita', 30)->default('presenza')->after('luogo');
        });

        Schema::create('assemblea_presenze', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assemblea_id')->constrained('assemblee')->cascadeOnDelete();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->string('stato', 30)->default('assente');
            $table->timestamp('presente_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['assemblea_id', 'socio_id']);
            $table->index(['assemblea_id', 'stato']);
        });

        Schema::create('assemblea_punti_odg', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assemblea_id')->constrained('assemblee')->cascadeOnDelete();
            $table->unsignedInteger('ordine')->default(1);
            $table->string('titolo');
            $table->text('descrizione')->nullable();
            $table->text('discussione')->nullable();
            $table->string('esito', 30)->default('da_discutere');
            $table->unsignedInteger('voti_favorevoli')->nullable();
            $table->unsignedInteger('voti_contrari')->nullable();
            $table->unsignedInteger('astenuti')->nullable();
            $table->timestamps();

            $table->index(['assemblea_id', 'ordine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assemblea_punti_odg');
        Schema::dropIfExists('assemblea_presenze');

        Schema::table('assemblee', function (Blueprint $table): void {
            $table->dropColumn([
                'started_at',
                'closed_at',
                'presidente',
                'segretario',
                'luogo',
                'modalita',
            ]);
        });
    }
};
