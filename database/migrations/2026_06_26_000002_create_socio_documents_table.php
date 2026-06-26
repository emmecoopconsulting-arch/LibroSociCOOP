<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socio_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('numero_documento')->nullable();
            $table->date('data_rilascio')->nullable();
            $table->date('data_scadenza')->nullable();
            $table->string('file_path');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['socio_id', 'tipo']);
            $table->index('data_scadenza');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socio_documents');
    }
};
