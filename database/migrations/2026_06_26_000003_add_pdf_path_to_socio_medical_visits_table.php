<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('socio_medical_visits', 'pdf_path')) {
            Schema::table('socio_medical_visits', function (Blueprint $table) {
                $table->string('pdf_path')->nullable()->after('scadenza');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('socio_medical_visits', 'pdf_path')) {
            Schema::table('socio_medical_visits', function (Blueprint $table) {
                $table->dropColumn('pdf_path');
            });
        }
    }
};
