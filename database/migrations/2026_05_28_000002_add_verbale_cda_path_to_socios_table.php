<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table): void {
            $table->string('verbale_cda_path')->nullable()->after('data_ammissione');
        });
    }

    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table): void {
            $table->dropColumn('verbale_cda_path');
        });
    }
};
