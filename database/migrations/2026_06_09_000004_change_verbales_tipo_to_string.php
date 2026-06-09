<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verbales', function (Blueprint $table): void {
            $table->string('tipo', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('verbales', function (Blueprint $table): void {
            $table->enum('tipo', ['ammissione', 'recesso', 'esclusione', 'sospensione'])->change();
        });
    }
};
