<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_employee_documents')) {
            Schema::create('hr_employee_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')
                    ->constrained('hr_employees')
                    ->cascadeOnDelete();
                $table->enum('document_type', [
                    'work_permit',
                    'residence_permit',
                    'professional_cert',
                    'medical_cert',
                    'driving_license',
                    'custom',
                ])->default('custom');
                $table->string('title');
                $table->string('reference_number')->nullable();
                $table->char('country', 2)->nullable();         // ISO alpha-2
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->enum('status', ['valid', 'expiring_soon', 'expired', 'pending_renewal'])
                    ->default('valid');
                // Alert thresholds (days before expiry)
                $table->unsignedSmallInteger('alert_days_before')->default(60);
                $table->boolean('alert_sent_60')->default(false);
                $table->boolean('alert_sent_30')->default(false);
                $table->boolean('alert_sent_7')->default(false);
                $table->string('file_path')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('employee_id');
                $table->index('expiry_date');
                $table->index('status');
                $table->index(['document_type', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_documents');
    }
};
