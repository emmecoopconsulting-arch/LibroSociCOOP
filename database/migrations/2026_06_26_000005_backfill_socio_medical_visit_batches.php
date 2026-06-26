<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('socio_medical_visit_batches')
            || ! Schema::hasTable('socio_medical_visits')
            || ! Schema::hasColumn('socio_medical_visits', 'batch_id')
        ) {
            return;
        }

        DB::table('socio_medical_visits')
            ->whereNull('batch_id')
            ->select('data_visita', 'note')
            ->groupBy('data_visita', 'note')
            ->orderBy('data_visita')
            ->get()
            ->each(function ($group): void {
                $batchId = DB::table('socio_medical_visit_batches')->insertGetId([
                    'data_visita' => $group->data_visita,
                    'note' => $group->note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('socio_medical_visits')
                    ->whereNull('batch_id')
                    ->where('data_visita', $group->data_visita)
                    ->where('note', $group->note)
                    ->update([
                        'batch_id' => $batchId,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
