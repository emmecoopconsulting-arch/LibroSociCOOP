<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_distributions', function (Blueprint $table) {
            $table->unsignedInteger('skipped_count')->default(0)->after('failed_count');
        });

        Schema::table('socio_documents', function (Blueprint $table) {
            $table->foreignId('payroll_distribution_id')
                ->nullable()
                ->after('socio_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('periodo_riferimento')->nullable()->after('tipo');
            $table->unique(
                ['payroll_distribution_id', 'socio_id'],
                'socio_documents_payroll_distribution_socio_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('socio_documents', function (Blueprint $table) {
            $table->dropUnique('socio_documents_payroll_distribution_socio_unique');
            $table->dropConstrainedForeignId('payroll_distribution_id');
            $table->dropColumn('periodo_riferimento');
        });

        Schema::table('payroll_distributions', function (Blueprint $table) {
            $table->dropColumn('skipped_count');
        });
    }
};
