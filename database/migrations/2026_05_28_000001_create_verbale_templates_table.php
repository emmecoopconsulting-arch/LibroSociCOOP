<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbale_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50)->unique();
            $table->json('contenuto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbale_templates');
    }
};
