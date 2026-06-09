<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assemblee', function (Blueprint $table): void {
            $table->id();
            $table->date('data_assemblea');
            $table->string('titolo');
            $table->text('note')->nullable();
            $table->string('stato', 30)->default('generata');
            $table->string('file_path')->nullable();
            $table->timestamp('generato_il')->nullable();
            $table->timestamps();

            $table->index(['data_assemblea', 'stato']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assemblee');
    }
};
