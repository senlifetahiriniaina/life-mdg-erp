<template>
  <div class="test-runner">
    <div class="test-form">
      <h3>Test Rule (Dry-Run)</h3>
      <p class="description">Test your rule with sample data to see if conditions match and what actions would execute.</p>

      <div class="form-group">
        <label>Sample Data (JSON)</label>
        <textarea
          v-model="sampleData"
          class="code-textarea"
          placeholder='{"field_name": "value", "amount": 100}'
        ></textarea>
        <small class="hint">Enter JSON object with field values to test against</small>
      </div>

      <button @click="testRule" class="btn-test" :disabled="testing">
        {{ testing ? 'Testing...' : 'Run Test' }}
      </button>
    </div>

    <div v-if="testResult" class="test-result">
      <h3>Test Results</h3>

      <div class="result-item">
        <span class="label">Conditions Met:</span>
        <span :class="['value', testResult.conditions_met ? 'success' : 'failure']">
          {{ testResult.conditions_met ? '✓ Yes' : '✗ No' }}
        </span>
      </div>

      <div class="result-item">
        <span class="label">Actions to Execute:</span>
        <span class="value">{{ testResult.actions_to_execute?.length || 0 }}</span>
      </div>

      <div v-if="testResult.evaluated_conditions" class="conditions-detail">
        <h4>Condition Breakdown</h4>
        <div class="condition-item" v-for="(cond, idx) in flattenConditions(testResult.evaluated_conditions)" :key="idx">
          <span class="field">{{ cond.field }}</span>
          <span class="operator">{{ cond.operator }}</span>
          <span class="expected">{{ cond.expected_value }}</span>
          <span class="actual">= {{ cond.actual_value }}</span>
          <span :class="['met', cond.is_met ? 'yes' : 'no']">
            {{ cond.is_met ? '✓' : '✗' }}
          </span>
        </div>
      </div>

      <div v-if="testResult.actions_to_execute?.length > 0" class="actions-detail">
        <h4>Actions</h4>
        <div v-for="(action, idx) in testResult.actions_to_execute" :key="idx" class="action-item">
          <span class="action-type">{{ action.type }}</span>
          <span class="action-desc">{{ formatAction(action) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useApi } from '@/composables/useApi'

const props = defineProps<{
  rule: any
}>()

const { post } = useApi()

const sampleData = ref('{}')
const testing = ref(false)
const testResult = ref(null)

const testRule = async () => {
  testing.value = true
  try {
    const data = JSON.parse(sampleData.value)
    const response = await post(`/api/v1/automation/rules/${props.rule.id}/test`, {
      sample_data: data,
    })
    testResult.value = response
  } catch (error) {
    alert('Error testing rule: ' + (error as any).message)
  } finally {
    testing.value = false
  }
}

const flattenConditions = (conditions: any, flattened: any[] = []): any[] => {
  if (conditions.rules) {
    conditions.rules.forEach((rule: any) => {
      if (rule.type) {
        flattenConditions(rule, flattened)
      } else {
        flattened.push(rule)
      }
    })
  }
  return flattened
}

const formatAction = (action: any): string => {
  const type = action.type
  return {
    update_field: `Update ${action.field} = ${action.value}`,
    update_status: `Change status to ${action.status}`,
    send_email: `Email using template ${action.template}`,
    create_task: `Create task: ${action.title}`,
    assign_to_user: `Assign to user ${action.user_id}`,
    add_tag: `Add tag: ${action.tag}`,
    add_comment: `Add comment`,
    trigger_webhook: `Call ${action.webhook_url}`,
  }[type] || type
}
</script>

<style scoped lang="scss">
.test-runner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;

  h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
  }

  h4 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 1rem 0 0.75rem 0;
  }

  .test-form {
    .description {
      color: #666;
      margin-bottom: 1.5rem;
    }

    .form-group {
      margin-bottom: 1.5rem;

      label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
      }

      .code-textarea {
        width: 100%;
        min-height: 150px;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 0.375rem;
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 0.85rem;

        &:focus {
          outline: none;
          border-color: #7c3aed;
        }
      }

      .hint {
        display: block;
        color: #999;
        margin-top: 0.25rem;
      }
    }

    .btn-test {
      width: 100%;
      padding: 0.75rem;
      background: #7c3aed;
      color: white;
      border: none;
      border-radius: 0.375rem;
      font-weight: 600;
      cursor: pointer;

      &:hover:not(:disabled) {
        background: #6d28d9;
      }

      &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
    }
  }

  .test-result {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    padding: 1.5rem;

    .result-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 1rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid #e5e7eb;

      .label {
        font-weight: 600;
      }

      .value {
        font-weight: 600;

        &.success {
          color: #059669;
        }

        &.failure {
          color: #dc2626;
        }
      }
    }

    .conditions-detail,
    .actions-detail {
      margin-top: 1.5rem;

      .condition-item {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        padding: 0.5rem;
        background: white;
        border-radius: 0.25rem;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;

        .field {
          font-weight: 600;
          color: #7c3aed;
        }

        .operator {
          color: #666;
        }

        .expected {
          color: #059669;
        }

        .actual {
          color: #f59e0b;
        }

        .met {
          margin-left: auto;
          font-weight: 700;

          &.yes {
            color: #059669;
          }

          &.no {
            color: #dc2626;
          }
        }
      }

      .action-item {
        background: white;
        padding: 0.75rem;
        border-radius: 0.25rem;
        margin-bottom: 0.5rem;
        display: flex;
        gap: 1rem;

        .action-type {
          font-weight: 600;
          color: #7c3aed;
          flex-shrink: 0;
        }

        .action-desc {
          color: #1f2937;
        }
      }
    }
  }

  @media (max-width: 1024px) {
    grid-template-columns: 1fr;
  }
}
</style>
