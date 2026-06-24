<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_order_sites')) {
            Schema::create('work_order_sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('work_vehicle_id')->nullable()->constrained()->nullOnDelete();
                $table->json('socio_ids')->nullable();
                $table->time('orario_inizio')->nullable();
                $table->time('orario_fine')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['work_order_id', 'orario_inizio', 'orario_fine']);
            });
        }

        if (Schema::hasTable('work_order_sites') && ! Schema::hasColumn('work_order_sites', 'socio_ids')) {
            Schema::table('work_order_sites', function (Blueprint $table) {
                $table->json('socio_ids')->nullable()->after('work_vehicle_id');
            });
        }

        if (Schema::hasTable('work_sites')) {
            Schema::table('work_sites', function (Blueprint $table) {
                foreach (['work_order_id', 'orario_inizio', 'orario_fine'] as $column) {
                    if (Schema::hasColumn('work_sites', $column)) {
                        $table->{$column === 'work_order_id' ? 'unsignedBigInteger' : 'time'}($column)->nullable()->change();
                    }
                }
            });
        }

        if (Schema::hasTable('work_site_assignments') && ! Schema::hasColumn('work_site_assignments', 'work_order_site_id')) {
            Schema::table('work_site_assignments', function (Blueprint $table) {
                $table->foreignId('work_order_site_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('work_site_assignments') && Schema::hasColumn('work_site_assignments', 'work_site_id')) {
            Schema::table('work_site_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('work_site_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('work_site_assignments') && ! $this->hasIndex('work_site_assignments', 'work_site_assignments_work_order_site_id_socio_id_unique')) {
            Schema::table('work_site_assignments', function (Blueprint $table) {
                $table->unique(['work_order_site_id', 'socio_id']);
            });
        }

        if (Schema::hasTable('work_absences')) {
            if (Schema::hasColumn('work_absences', 'socio_id')) {
                Schema::table('work_absences', function (Blueprint $table) {
                    $table->unsignedBigInteger('socio_id')->nullable()->change();
                });
            }

            Schema::table('work_absences', function (Blueprint $table) {
                if (! Schema::hasColumn('work_absences', 'socio_ids')) {
                    $table->json('socio_ids')->nullable()->after('socio_id');
                }

                if (! Schema::hasColumn('work_absences', 'data_inizio')) {
                    $table->date('data_inizio')->nullable()->after('tipo');
                }

                if (! Schema::hasColumn('work_absences', 'data_fine')) {
                    $table->date('data_fine')->nullable()->after('data_inizio');
                }
            });
        }

        $this->copyExistingOrderSites();
        $this->copyExistingAbsenceSocios();
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_sites');

        if (Schema::hasTable('work_site_assignments') && Schema::hasColumn('work_site_assignments', 'work_order_site_id')) {
            Schema::table('work_site_assignments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('work_order_site_id');
            });
        }

        if (Schema::hasTable('work_absences')) {
            Schema::table('work_absences', function (Blueprint $table) {
                foreach (['socio_ids', 'data_inizio', 'data_fine'] as $column) {
                    if (Schema::hasColumn('work_absences', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function copyExistingOrderSites(): void
    {
        if (! Schema::hasTable('work_sites') || ! Schema::hasTable('work_order_sites')) {
            return;
        }

        $legacySites = DB::table('work_sites')
            ->whereNotNull('work_order_id')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('work_order_sites')
                    ->whereColumn('work_order_sites.work_site_id', 'work_sites.id')
                    ->whereColumn('work_order_sites.work_order_id', 'work_sites.work_order_id');
            })
            ->get();

        foreach ($legacySites as $site) {
            $orderSiteId = DB::table('work_order_sites')->insertGetId([
                'work_order_id' => $site->work_order_id,
                'work_site_id' => $site->id,
                'work_vehicle_id' => $site->work_vehicle_id,
                'socio_ids' => json_encode(DB::table('work_site_assignments')
                    ->where('work_site_id', $site->id)
                    ->pluck('socio_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all()),
                'orario_inizio' => $site->orario_inizio,
                'orario_fine' => $site->orario_fine,
                'note' => $site->note,
                'created_at' => $site->created_at,
                'updated_at' => $site->updated_at,
            ]);

            if (Schema::hasColumn('work_site_assignments', 'work_order_site_id')) {
                DB::table('work_site_assignments')
                    ->where('work_site_id', $site->id)
                    ->whereNull('work_order_site_id')
                    ->update(['work_order_site_id' => $orderSiteId]);
            }
        }

        if (Schema::hasTable('work_order_sites') && Schema::hasColumn('work_order_sites', 'socio_ids')) {
            DB::table('work_order_sites')
                ->whereNull('socio_ids')
                ->orderBy('id')
                ->get()
                ->each(function ($site): void {
                    $socioIds = DB::table('work_site_assignments')
                        ->where('work_order_site_id', $site->id)
                        ->pluck('socio_id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    DB::table('work_order_sites')
                        ->where('id', $site->id)
                        ->update(['socio_ids' => json_encode($socioIds)]);
                });
        }
    }

    private function copyExistingAbsenceSocios(): void
    {
        if (! Schema::hasTable('work_absences') || ! Schema::hasColumn('work_absences', 'socio_ids')) {
            return;
        }

        DB::table('work_absences')
            ->whereNull('socio_ids')
            ->whereNotNull('socio_id')
            ->orderBy('id')
            ->get()
            ->each(function ($absence): void {
                DB::table('work_absences')
                    ->where('id', $absence->id)
                    ->update([
                        'socio_ids' => json_encode([(int) $absence->socio_id]),
                        'data_inizio' => $absence->data_inizio ?? DB::table('work_orders')->where('id', $absence->work_order_id)->value('data_servizio'),
                        'data_fine' => $absence->data_fine ?? DB::table('work_orders')->where('id', $absence->work_order_id)->value('data_servizio'),
                    ]);
            });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $existing): bool => ($existing['name'] ?? null) === $index);
    }
};
