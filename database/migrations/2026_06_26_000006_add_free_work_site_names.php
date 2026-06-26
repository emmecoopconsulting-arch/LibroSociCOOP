<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_order_sites') && ! Schema::hasColumn('work_order_sites', 'work_site_name')) {
            Schema::table('work_order_sites', function (Blueprint $table): void {
                $table->string('work_site_name')->nullable()->after('work_site_id');
            });
        }

        if (Schema::hasTable('work_reports') && ! Schema::hasColumn('work_reports', 'work_site_name')) {
            Schema::table('work_reports', function (Blueprint $table): void {
                $table->string('work_site_name')->nullable()->after('work_site_id');
            });
        }

        $this->backfillWorkSiteNames();
    }

    public function down(): void
    {
        if (Schema::hasTable('work_order_sites') && Schema::hasColumn('work_order_sites', 'work_site_name')) {
            Schema::table('work_order_sites', function (Blueprint $table): void {
                $table->dropColumn('work_site_name');
            });
        }

        if (Schema::hasTable('work_reports') && Schema::hasColumn('work_reports', 'work_site_name')) {
            Schema::table('work_reports', function (Blueprint $table): void {
                $table->dropColumn('work_site_name');
            });
        }
    }

    private function backfillWorkSiteNames(): void
    {
        if (! Schema::hasTable('work_sites')) {
            return;
        }

        $labels = DB::table('work_sites')
            ->select('id', 'nome', 'luogo')
            ->get()
            ->mapWithKeys(fn ($site): array => [
                $site->id => filled($site->luogo) ? "{$site->nome} - {$site->luogo}" : $site->nome,
            ]);

        if ($labels->isEmpty()) {
            return;
        }

        foreach (['work_order_sites', 'work_reports'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'work_site_name')) {
                continue;
            }

            DB::table($table)
                ->whereNull('work_site_name')
                ->whereNotNull('work_site_id')
                ->select('id', 'work_site_id')
                ->orderBy('id')
                ->get()
                ->each(function ($row) use ($labels, $table): void {
                    $label = $labels->get($row->work_site_id);

                    if (blank($label)) {
                        return;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['work_site_name' => $label]);
                });
        }
    }
};
