<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->boolean('socio_svantaggiato')->default(false)->after('stato');
            $table->string('svantaggio_categoria')->nullable()->after('socio_svantaggiato');
            $table->date('svantaggio_data_decorrenza')->nullable()->after('svantaggio_categoria');
            $table->date('svantaggio_data_cessazione')->nullable()->after('svantaggio_data_decorrenza');
            $table->string('svantaggio_ente')->nullable()->after('svantaggio_data_cessazione');
            $table->string('svantaggio_riferimento')->nullable()->after('svantaggio_ente');
            $table->date('svantaggio_data_documento')->nullable()->after('svantaggio_riferimento');
            $table->string('svantaggio_documento_path')->nullable()->after('svantaggio_data_documento');
            $table->text('svantaggio_note')->nullable()->after('svantaggio_documento_path');
        });
    }

    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->dropColumn([
                'socio_svantaggiato',
                'svantaggio_categoria',
                'svantaggio_data_decorrenza',
                'svantaggio_data_cessazione',
                'svantaggio_ente',
                'svantaggio_riferimento',
                'svantaggio_data_documento',
                'svantaggio_documento_path',
                'svantaggio_note',
            ]);
        });
    }
};
