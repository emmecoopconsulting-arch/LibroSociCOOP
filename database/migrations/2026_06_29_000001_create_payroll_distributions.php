<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('source_path');
            $table->string('period')->nullable();
            $table->string('status')->default('processing');
            $table->unsignedInteger('total_pages')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_distribution_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('socios')->nullOnDelete();
            $table->unsignedInteger('page_number');
            $table->string('page_path');
            $table->unsignedTinyInteger('match_confidence')->default(0);
            $table->string('match_reason')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->timestamps();

            $table->unique(['payroll_distribution_id', 'page_number'], 'payroll_distribution_page_unique');
        });

        Schema::create('payroll_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('socios')->nullOnDelete();
            $table->string('email');
            $table->string('attachment_path');
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deliveries');
        Schema::dropIfExists('payroll_distribution_pages');
        Schema::dropIfExists('payroll_distributions');
    }
};
