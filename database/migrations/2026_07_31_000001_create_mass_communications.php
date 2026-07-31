<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_distributions', function (Blueprint $table) {
            $table->text('additional_message')->nullable()->after('period');
        });

        Schema::create('mass_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_description')->nullable();
            $table->json('filters');
            $table->string('status')->default('draft');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mass_communication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mass_communication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('socios')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['mass_communication_id', 'socio_id'], 'mass_communication_socio_unique');
        });

        Schema::table('socio_documents', function (Blueprint $table) {
            $table->foreignId('mass_communication_id')
                ->nullable()
                ->after('payroll_distribution_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('socio_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mass_communication_id');
        });
        Schema::dropIfExists('mass_communication_deliveries');
        Schema::dropIfExists('mass_communications');
        Schema::table('payroll_distributions', function (Blueprint $table) {
            $table->dropColumn('additional_message');
        });
    }
};
