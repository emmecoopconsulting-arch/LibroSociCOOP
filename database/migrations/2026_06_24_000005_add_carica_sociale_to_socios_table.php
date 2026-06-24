<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table): void {
            if (! Schema::hasColumn('socios', 'carica_sociale')) {
                $table->string('carica_sociale', 30)->nullable()->after('is_cda_member');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('socios', 'carica_sociale')) {
            Schema::table('socios', function (Blueprint $table): void {
                $table->dropColumn('carica_sociale');
            });
        }
    }
};
