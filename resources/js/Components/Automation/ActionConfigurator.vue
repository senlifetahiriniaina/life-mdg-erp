<template>
  <div class="action-configurator">
    <div class="actions-list">
      <div v-for="(action, index) in modelValue" :key="index" class="action-item">
        <div class="action-select">
          <select v-model="action.type" class="type-select">
            <option value="">Select action</option>
            <option value="update_field">Update Field</option>
            <option value="update_status">Update Status</option>
            <option value="send_email">Send Email</option>
            <option value="create_task">Create Task</option>
            <option value="assign_to_user">Assign to User</option>
            <option value="add_tag">Add Tag</option>
            <option value="add_comment">Add Comment</option>
            <option value="trigger_webhook">Trigger Webhook</option>
          </select>
        </div>

        <!-- Update Field -->
        <div v-if="action.type === 'update_field'" class="action-params">
          <input
            v-model="action.field"
            type="text"
            placeholder="Field name"
            class="param-input"
          />
          <input
            v-model="action.value"
            type="text"
            placeholder="New value"
            class="param-input"
          />
        </div>

        <!-- Update Status -->
        <div v-if="action.type === 'update_status'" class="action-params">
          <select v-model="action.status" class="param-select">
            <option value="">Select status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        <!-- Send Email -->
        <div v-if="action.type === 'send_email'" class="action-params">
          <select v-model="action.template" class="param-select">
            <option value="">Select template</option>
            <option value="order_approved">Order Approved</option>
            <option value="order_rejected">Order Rejected</option>
            <option value="task_assigned">Task Assigned</option>
          </select>
          <input
            v-model="action.recipients"
            type="text"
            placeholder="Recipients (comma-separated)"
            class="param-input"
          />
        </div>

        <!-- Create Task -->
        <div v-if="action.type === 'create_task'" class="action-params">
          <input
            v-model="action.title"
            type="text"
            placeholder="Task title"
            class="param-input"
          />
          <select v-model="action.project_id" class="param-select">
            <option value="">Select project</option>
            <option value="1">Project 1</option>
            <option value="2">Project 2</option>
          </select>
        </div>

        <!-- Assign to User -->
        <div v-if="action.type === 'assign_to_user'" class="action-params">
          <select v-model="action.user_id" class="param-select">
            <option value="">Select user</option>
            <option value="1">User 1</option>
            <option value="2">User 2</option>
          </select>
        </div>

        <!-- Add Tag -->
        <div v-if="action.type === 'add_tag'" class="action-params">
          <input
            v-model="action.tag"
            type="text"
            placeholder="Tag name"
            class="param-input"
          />
        </div>

        <!-- Add Comment -->
        <div v-if="action.type === 'add_comment'" class="action-params">
          <textarea
            v-model="action.text"
            placeholder="Comment text"
            class="param-textarea"
          ></textarea>
        </div>

        <!-- Trigger Webhook -->
        <div v-if="action.type === 'trigger_webhook'" class="action-params">
          <input
            v-model="action.webhook_url"
            type="url"
            placeholder="Webhook URL"
            class="param-input"
          />
        </div>

        <button
          @click="removeAction(index)"
          class="btn-remove"
          title="Remove action"
        >
          <i class="pi pi-trash"></i>
        </button>
      </div>
    </div>

    <button @click="addAction" class="btn-add-action">
      <i class="pi pi-plus"></i> Add Action
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Action {
  type?: string
  [key: string]: any
}

const props = defineProps<{
  modelValue: Action[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Action[]]
}>()

const addAction = () => {
  emit('update:modelValue', [
    ...props.modelValue,
    {
      type: '',
      field: '',
      value: '',
    },
  ])
}

const removeAction = (index: number) => {
  emit(
    'update:modelValue',
    props.modelValue.filter((_, i) => i !== index)
  )
}
</script>

<style scoped lang="scss">
.action-configurator {
  .actions-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1rem;

    .action-item {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      padding: 1rem;
      display: flex;
      gap: 1rem;
      align-items: flex-start;

      .action-select {
        flex: 0 0 150px;

        .type-select {
          width: 100%;
          padding: 0.5rem;
          border: 1px solid #ddd;
          border-radius: 0.375rem;
          font-weight: 600;

          &:focus {
            outline: none;
            border-color: #7c3aed;
          }
        }
      }

      .action-params {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;

        .param-input,
        .param-select,
        .param-textarea {
          padding: 0.5rem;
          border: 1px solid #ddd;
          border-radius: 0.375rem;
          font-family: inherit;

          &:focus {
            outline: none;
            border-color: #7c3aed;
          }
        }

        .param-textarea {
          min-height: 80px;
          resize: vertical;
        }
      }

      .btn-remove {
        padding: 0.5rem;
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;

        &:hover {
          background: #fecaca;
        }
      }
    }
  }

  .btn-add-action {
    width: 100%;
    padding: 0.75rem;
    background: #e0e7ff;
    color: #7c3aed;
    border: 2px dashed #c7d2fe;
    border-radius: 0.375rem;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;

    &:hover {
      background: #c7d2fe;
    }

    i {
      margin-right: 0.5rem;
    }
  }
}
</style>
