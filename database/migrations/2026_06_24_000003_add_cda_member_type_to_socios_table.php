<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE socios MODIFY tipologia ENUM('fondatore', 'volontario', 'ordinario', 'membro_cda') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::table('socios')
            ->where('tipologia', 'membro_cda')
            ->update([
                'tipologia' => 'ordinario',
                'is_cda_member' => true,
            ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE socios MODIFY tipologia ENUM('fondatore', 'volontario', 'ordinario') NOT NULL");
        }
    }
};
