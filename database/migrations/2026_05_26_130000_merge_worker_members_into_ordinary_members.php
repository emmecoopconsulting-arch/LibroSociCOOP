<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('socios')
            ->where('tipologia', 'lavoratore')
            ->update(['tipologia' => 'ordinario']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE socios MODIFY tipologia ENUM('fondatore', 'volontario', 'ordinario') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE socios MODIFY tipologia ENUM('fondatore', 'lavoratore', 'volontario', 'ordinario') NOT NULL");
        }
    }
};
