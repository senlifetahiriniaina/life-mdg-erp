<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Patch validation_approval_workflows table
        if (Schema::hasTable('validation_approval_workflows')) {
            Schema::table('validation_approval_workflows', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_approval_workflows', 'module_name')) {
                    $table->string('module_name')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_workflows', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_workflows', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_workflows', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('validation_approval_requests')) {
            Schema::table('validation_approval_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_approval_requests', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'approver_id')) {
                    $table->unsignedBigInteger('approver_id')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'approvable_type')) {
                    $table->string('approvable_type')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'approvable_id')) {
                    $table->unsignedBigInteger('approvable_id')->nullable();
                }
            });
        }

        // Also patch validation_approval_actions table
        if (Schema::hasTable('validation_approval_actions')) {
            Schema::table('validation_approval_actions', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_approval_actions', 'request_id')) {
                    $table->unsignedBigInteger('request_id')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_actions', 'approver_id')) {
                    $table->unsignedBigInteger('approver_id')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_actions', 'action')) {
                    $table->string('action')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_actions', 'comment')) {
                    $table->text('comment')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_actions', 'acted_at')) {
                    $table->timestamp('acted_at')->nullable();
                }
            });
        } else {
            Schema::create('validation_approval_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->nullable()->index();
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->string('action')->nullable();
                $table->text('comment')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Patch validation_approval_history table
        if (Schema::hasTable('validation_approval_history')) {
            Schema::table('validation_approval_history', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_approval_history', 'request_id')) {
                    $table->unsignedBigInteger('request_id')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_history', 'action')) {
                    $table->string('action')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_history', 'old_status')) {
                    $table->string('old_status')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_history', 'new_status')) {
                    $table->string('new_status')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_history', 'changed_by')) {
                    $table->unsignedBigInteger('changed_by')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_history', 'changed_at')) {
                    $table->timestamp('changed_at')->nullable();
                }
            });
        } else {
            Schema::create('validation_approval_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->nullable()->index();
                $table->string('action')->nullable();
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('changed_at')->nullable();
                $table->timestamps();
            });
        }

        // HR shifts table
        if (! Schema::hasTable('hr_shifts')) {
            Schema::create('hr_shifts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('shift_type')->nullable();
                $table->date('date')->nullable();
                $table->string('start_time')->nullable();
                $table->string('end_time')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void {}
};
