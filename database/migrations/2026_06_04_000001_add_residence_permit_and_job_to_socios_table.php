<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->boolean('ha_permesso_soggiorno')->default(false)->after('data_uscita');
            $table->date('scadenza_permesso_soggiorno')->nullable()->after('ha_permesso_soggiorno');
            $table->string('mansione')->nullable()->after('scadenza_permesso_soggiorno');

            $table->index('scadenza_permesso_soggiorno');
            $table->index('mansione');
        });
    }

    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->dropIndex(['scadenza_permesso_soggiorno']);
            $table->dropIndex(['mansione']);
            $table->dropColumn([
                'ha_permesso_soggiorno',
                'scadenza_permesso_soggiorno',
                'mansione',
            ]);
        });
    }
};
