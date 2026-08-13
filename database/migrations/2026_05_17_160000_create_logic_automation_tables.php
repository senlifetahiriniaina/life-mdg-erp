<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Logic Rules - Define automation rules without code
        if (!Schema::hasTable('logic_rules')) {
            Schema::create('logic_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('trigger', 100); // order_created, task_updated, etc.
                $table->json('conditions'); // Condition tree: {type: AND/OR, rules: [...]}
                $table->json('actions'); // Action list: [{type: update_field, ...}, ...]
                $table->boolean('is_enabled')->default(true)->index();
                $table->unsignedInteger('execution_count')->default(0);
                $table->timestamp('last_executed_at')->nullable();
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
                $table->index(['trigger', 'is_enabled']);
                $table->index('created_at');
            });
        }

        // Logic Rule Execution Log - Track when rules execute
        if (!Schema::hasTable('logic_rule_executions')) {
            Schema::create('logic_rule_executions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->index();
                $table->json('trigger_data'); // Data that triggered the rule
                $table->boolean('conditions_met')->default(false);
                $table->json('actions_executed'); // Results of each action
                $table->text('error_message')->nullable();
                $table->unsignedSmallInteger('duration_ms')->default(0);
                $table->timestamp('executed_at')->index();

                $table->foreign('rule_id')->references('id')->on('logic_rules')->onDelete('cascade');
                $table->index(['rule_id', 'executed_at']);
                $table->index('conditions_met');
            });
        }

        // Logic Conditions Reference - Available conditions for builder UI
        if (!Schema::hasTable('logic_condition_types')) {
            Schema::create('logic_condition_types', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 100)->unique(); // equals, contains, greater_than, etc.
                $table->string('label', 255);
                $table->string('description', 500);
                $table->json('applicable_types'); // data types this applies to
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Logic Actions Reference - Available actions for builder UI
        if (!Schema::hasTable('logic_action_types')) {
            Schema::create('logic_action_types', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 100)->unique(); // update_field, send_email, create_task, etc.
                $table->string('label', 255);
                $table->string('description', 500);
                $table->json('required_params'); // {param_name => {type, label, options}}
                $table->json('target_models')->nullable(); // which models this applies to
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Insert default condition types
        $this->insertConditionTypes();

        // Insert default action types
        $this->insertActionTypes();
    }

    private function insertConditionTypes(): void
    {
        $conditions = [
            ['slug' => 'equals', 'label' => 'Equals', 'applicable_types' => ['string', 'number', 'boolean', 'date']],
            ['slug' => 'not_equals', 'label' => 'Not Equals', 'applicable_types' => ['string', 'number', 'boolean', 'date']],
            ['slug' => 'contains', 'label' => 'Contains', 'applicable_types' => ['string']],
            ['slug' => 'not_contains', 'label' => 'Does Not Contain', 'applicable_types' => ['string']],
            ['slug' => 'starts_with', 'label' => 'Starts With', 'applicable_types' => ['string']],
            ['slug' => 'ends_with', 'label' => 'Ends With', 'applicable_types' => ['string']],
            ['slug' => 'greater_than', 'label' => 'Greater Than', 'applicable_types' => ['number', 'date']],
            ['slug' => 'greater_than_or_equal', 'label' => 'Greater Than or Equal', 'applicable_types' => ['number', 'date']],
            ['slug' => 'less_than', 'label' => 'Less Than', 'applicable_types' => ['number', 'date']],
            ['slug' => 'less_than_or_equal', 'label' => 'Less Than or Equal', 'applicable_types' => ['number', 'date']],
            ['slug' => 'is_empty', 'label' => 'Is Empty', 'applicable_types' => ['string', 'number']],
            ['slug' => 'is_not_empty', 'label' => 'Is Not Empty', 'applicable_types' => ['string', 'number']],
            ['slug' => 'is_true', 'label' => 'Is True', 'applicable_types' => ['boolean']],
            ['slug' => 'is_false', 'label' => 'Is False', 'applicable_types' => ['boolean']],
            ['slug' => 'in_list', 'label' => 'In List', 'applicable_types' => ['string', 'number']],
            ['slug' => 'not_in_list', 'label' => 'Not In List', 'applicable_types' => ['string', 'number']],
        ];

        $table = \Illuminate\Support\Facades\DB::table('logic_condition_types');
        foreach ($conditions as $i => $condition) {
            $table->insert(array_merge($condition, [
                'description' => $condition['label'] . ' condition',
                'applicable_types' => json_encode($condition['applicable_types']),
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function insertActionTypes(): void
    {
        $actions = [
            [
                'slug' => 'update_field',
                'label' => 'Update Field',
                'description' => 'Update a field value on the record',
                'required_params' => ['field' => ['type' => 'string', 'label' => 'Field'], 'value' => ['type' => 'string', 'label' => 'Value']],
            ],
            [
                'slug' => 'update_status',
                'label' => 'Update Status',
                'description' => 'Change status to a specific value',
                'required_params' => ['status' => ['type' => 'select', 'label' => 'Status']],
            ],
            [
                'slug' => 'send_email',
                'label' => 'Send Email',
                'description' => 'Send notification email to recipients',
                'required_params' => ['template' => ['type' => 'select', 'label' => 'Template'], 'recipients' => ['type' => 'array', 'label' => 'Recipients']],
            ],
            [
                'slug' => 'create_task',
                'label' => 'Create Task',
                'description' => 'Create a new task in projects',
                'required_params' => ['title' => ['type' => 'string', 'label' => 'Task Title'], 'project_id' => ['type' => 'select', 'label' => 'Project']],
            ],
            [
                'slug' => 'assign_to_user',
                'label' => 'Assign to User',
                'description' => 'Assign record to a specific user',
                'required_params' => ['user_id' => ['type' => 'select', 'label' => 'User']],
            ],
            [
                'slug' => 'add_tag',
                'label' => 'Add Tag',
                'description' => 'Add a tag/label to the record',
                'required_params' => ['tag' => ['type' => 'string', 'label' => 'Tag']],
            ],
            [
                'slug' => 'add_comment',
                'label' => 'Add Comment',
                'description' => 'Add an internal comment to the record',
                'required_params' => ['text' => ['type' => 'text', 'label' => 'Comment']],
            ],
            [
                'slug' => 'trigger_webhook',
                'label' => 'Trigger Webhook',
                'description' => 'Call an external webhook with the record data',
                'required_params' => ['webhook_url' => ['type' => 'string', 'label' => 'Webhook URL']],
            ],
        ];

        $table = \Illuminate\Support\Facades\DB::table('logic_action_types');
        foreach ($actions as $i => $action) {
            $table->insert(array_merge($action, [
                'required_params' => json_encode($action['required_params']),
                'target_models' => json_encode([]),
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logic_rule_executions');
        Schema::dropIfExists('logic_rules');
        Schema::dropIfExists('logic_condition_types');
        Schema::dropIfExists('logic_action_types');
    }
};
