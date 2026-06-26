<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_reports')) {
            return;
        }

        Schema::table('work_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('work_reports', 'operator_hours')) {
                $table->json('operator_hours')->nullable()->after('socio_ids');
            }

            if (! Schema::hasColumn('work_reports', 'total_hours')) {
                $table->decimal('total_hours', 8, 2)->default(0)->after('operator_hours');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_reports')) {
            return;
        }

        Schema::table('work_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('work_reports', 'total_hours')) {
                $table->dropColumn('total_hours');
            }

            if (Schema::hasColumn('work_reports', 'operator_hours')) {
                $table->dropColumn('operator_hours');
            }
        });
    }
};
