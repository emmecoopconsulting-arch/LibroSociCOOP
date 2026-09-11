<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('socio_documents', 'socio_medical_visit_id')) {
            Schema::table('socio_documents', function (Blueprint $table): void {
                $table->foreignId('socio_medical_visit_id')->nullable()->unique()
                    ->constrained('socio_medical_visits')->cascadeOnDelete();
            });
        }

        // Reuse the original private file. No filesystem copies or remote archive calls.
        DB::table('socio_medical_visits')->whereNotNull('pdf_path')->orderBy('id')
            ->chunkById(200, function ($visits): void {
                foreach ($visits as $visit) {
                    if (trim($visit->pdf_path) === '') {
                        continue;
                    }

                    $existing = DB::table('socio_documents')->where('socio_medical_visit_id', $visit->id)->first()
                        ?? DB::table('socio_documents')
                            ->where('socio_id', $visit->socio_id)
                            ->where('tipo', 'visita_medica')
                            ->where('file_path', $visit->pdf_path)
                            ->whereNull('socio_medical_visit_id')->first();

                    $values = [
                        'socio_medical_visit_id' => $visit->id,
                        'socio_id' => $visit->socio_id,
                        'tipo' => 'visita_medica',
                        'file_path' => $visit->pdf_path,
                        'data_rilascio' => $visit->data_visita,
                        'data_scadenza' => $visit->scadenza,
                        'note' => $visit->note,
                        'updated_at' => now(),
                    ];

                    if ($existing) {
                        DB::table('socio_documents')->where('id', $existing->id)->update($values);
                    } else {
                        DB::table('socio_documents')->updateOrInsert(
                            ['socio_medical_visit_id' => $visit->id],
                            $values + ['created_at' => $visit->created_at ?? now()],
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('socio_documents', function (Blueprint $table): void {
            $table->dropUnique(['socio_medical_visit_id']);
            $table->dropConstrainedForeignId('socio_medical_visit_id');
        });
    }
};
