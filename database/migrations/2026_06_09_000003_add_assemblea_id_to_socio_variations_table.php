<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socio_variations', function (Blueprint $table): void {
            $table->foreignId('assemblea_id')
                ->nullable()
                ->after('verbale_id')
                ->constrained('assemblee')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('socio_variations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assemblea_id');
        });
    }
};
