<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Include soft-deleted members so restoring one cannot restore capital.
        DB::table('socios')
            ->where('stato', 'recesso')
            ->where(fn ($query) => $query->where('quota_sociale', '!=', 0)->orWhere('capitale_versato', '!=', 0))
            ->orderBy('id')
            ->chunkById(200, function ($socios): void {
                foreach ($socios as $candidate) {
                    DB::transaction(function () use ($candidate): void {
                        // Recheck after taking the lock, in case the status changed.
                        $socio = DB::table('socios')->where('id', $candidate->id)->lockForUpdate()->first();

                        if (! $socio || $socio->stato !== 'recesso') {
                            return;
                        }

                        $old = [];
                        $attributes = [];
                        $timestamp = now();

                        foreach (['quota_sociale', 'capitale_versato'] as $field) {
                            if ((float) $socio->{$field} === 0.0) {
                                continue;
                            }

                            $old[$field] = $socio->{$field};
                            $attributes[$field] = '0.00';

                            DB::table('socio_changes')->insert([
                                'socio_id' => $socio->id,
                                'user_id' => null,
                                'field' => $field,
                                'old_value' => $socio->{$field},
                                'new_value' => '0.00',
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ]);
                        }

                        if ($attributes === []) {
                            return;
                        }

                        // Use the query builder to avoid unrelated anagraphic/file hooks.
                        DB::table('socios')->where('id', $socio->id)->update([
                            ...$attributes,
                            'updated_at' => $timestamp,
                        ]);

                        DB::table('activity_log')->insert([
                            'log_name' => 'bonifica_finanziaria',
                            'description' => 'Azzeramento quota e capitale del socio in recesso',
                            'subject_type' => 'App\\Models\\Socio',
                            'subject_id' => $socio->id,
                            'event' => 'bonifica_recesso',
                            'attribute_changes' => json_encode(['old' => $old, 'attributes' => $attributes], JSON_THROW_ON_ERROR),
                            'properties' => json_encode(['migration' => '2026_10_16_000001_zero_recesso_share_capital'], JSON_THROW_ON_ERROR),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                    });
                }
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: never restore capital to a withdrawn member.
        // Original amounts remain in socio_changes and activity_log for review.
    }
};
