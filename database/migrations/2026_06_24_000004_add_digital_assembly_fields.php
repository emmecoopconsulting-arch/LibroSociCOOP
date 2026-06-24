<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assemblee', function (Blueprint $table): void {
            if (! Schema::hasColumn('assemblee', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('stato');
            }

            if (! Schema::hasColumn('assemblee', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('started_at');
            }

            if (! Schema::hasColumn('assemblee', 'presidente')) {
                $table->string('presidente')->nullable()->after('note');
            }

            if (! Schema::hasColumn('assemblee', 'segretario')) {
                $table->string('segretario')->nullable()->after('presidente');
            }

            if (! Schema::hasColumn('assemblee', 'luogo')) {
                $table->string('luogo')->nullable()->after('segretario');
            }

            if (! Schema::hasColumn('assemblee', 'modalita')) {
                $table->string('modalita', 30)->default('presenza')->after('luogo');
            }
        });

        if (! Schema::hasTable('assemblea_presenze')) {
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
        }

        if (! Schema::hasTable('assemblea_punti_odg')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('assemblea_punti_odg');
        Schema::dropIfExists('assemblea_presenze');

        $columns = array_values(array_filter([
            Schema::hasColumn('assemblee', 'started_at') ? 'started_at' : null,
            Schema::hasColumn('assemblee', 'closed_at') ? 'closed_at' : null,
            Schema::hasColumn('assemblee', 'presidente') ? 'presidente' : null,
            Schema::hasColumn('assemblee', 'segretario') ? 'segretario' : null,
            Schema::hasColumn('assemblee', 'luogo') ? 'luogo' : null,
            Schema::hasColumn('assemblee', 'modalita') ? 'modalita' : null,
        ]));

        if ($columns !== []) {
            Schema::table('assemblee', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
