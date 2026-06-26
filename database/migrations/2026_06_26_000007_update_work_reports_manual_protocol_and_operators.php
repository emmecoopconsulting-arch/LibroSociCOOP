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

        if (! Schema::hasColumn('work_reports', 'socio_ids')) {
            Schema::table('work_reports', function (Blueprint $table): void {
                $table->json('socio_ids')->nullable()->after('work_site_name');
            });
        }

        if (Schema::hasColumn('work_reports', 'work_order_id')) {
            Schema::table('work_reports', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('work_order_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_reports')) {
            return;
        }

        if (! Schema::hasColumn('work_reports', 'work_order_id')) {
            Schema::table('work_reports', function (Blueprint $table): void {
                $table->foreignId('work_order_id')->nullable()->after('data_intervento')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasColumn('work_reports', 'socio_ids')) {
            Schema::table('work_reports', function (Blueprint $table): void {
                $table->dropColumn('socio_ids');
            });
        }
    }
};
