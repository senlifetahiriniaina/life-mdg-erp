<template>
  <div class="rule-preview">
    <div class="preview-section">
      <h3>Rule Summary</h3>
      <div class="rule-summary">
        <div class="summary-item">
          <span class="label">Name:</span>
          <span class="value">{{ rule.name }}</span>
        </div>
        <div class="summary-item">
          <span class="label">Trigger:</span>
          <span class="trigger-badge">{{ rule.trigger }}</span>
        </div>
        <div class="summary-item">
          <span class="label">Status:</span>
          <span :class="['status-badge', rule.is_enabled ? 'enabled' : 'disabled']">
            {{ rule.is_enabled ? 'Enabled' : 'Disabled' }}
          </span>
        </div>
        <div class="summary-item">
          <span class="label">Executions:</span>
          <span class="value">{{ rule.execution_count || 0 }}</span>
        </div>
      </div>
    </div>

    <div class="preview-section">
      <h3>Conditions</h3>
      <div class="conditions-preview">
        <div class="condition-text">
          {{ conditionText }}
        </div>
      </div>
    </div>

    <div class="preview-section">
      <h3>Actions</h3>
      <div class="actions-preview">
        <div v-for="(action, index) in rule.actions" :key="index" class="action-preview">
          <span class="action-number">{{ index + 1 }}.</span>
          <span class="action-text">{{ actionText(action) }}</span>
        </div>
      </div>
    </div>

    <div class="preview-section">
      <h3>Full Rule Logic</h3>
      <div class="rule-logic">
        <p class="logic-text">
          IF {{ conditionText }} THEN:
        </p>
        <ul class="actions-list">
          <li v-for="(action, index) in rule.actions" :key="index">
            {{ actionText(action) }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface ConditionValue {
  type?: string
  field?: string
  operator?: string
  value?: unknown
  rules?: ConditionValue[]
}

interface ActionValue {
  type?: string
  field?: string
  value?: unknown
  status?: string
  template?: string
  title?: string
  user_id?: number | string
  tag?: string
  text?: string
  webhook_url?: string
}

interface RuleShape {
  id: number | null
  name?: string
  trigger?: string
  is_enabled?: boolean
  execution_count?: number
  conditions?: ConditionValue
  actions?: ActionValue[]
}

const props = defineProps<{
  rule: RuleShape
}>()

const conditionText = computed(() => {
  const conditions = props.rule.conditions
  if (!conditions || !conditions.rules) {
    return 'No conditions'
  }
  return buildConditionText(conditions)
})

const buildConditionText = (conditions: ConditionValue): string => {
  if (!conditions.rules || conditions.rules.length === 0) {
    return 'true'
  }

  const parts = conditions.rules.map((rule) => {
    if (rule.type) {
      return '(' + buildConditionText(rule) + ')'
    } else {
      return `${rule.field} ${rule.operator} "${rule.value}"`
    }
  })

  return parts.join(` ${conditions.type} `)
}

const actionText = (action: ActionValue): string => {
  const type = action.type ?? ''

  const labels: Record<string, string> = {
    update_field: `Update ${action.field} to "${action.value}"`,
    update_status: `Update status to "${action.status}"`,
    send_email: `Send email using "${action.template}" template`,
    create_task: `Create task: "${action.title}"`,
    assign_to_user: `Assign to user #${action.user_id}`,
    add_tag: `Add tag: "${action.tag}"`,
    add_comment: `Add comment: "${action.text}"`,
    trigger_webhook: `Call webhook: ${action.webhook_url}`,
  }
  return labels[type] || type
}
</script>

<style scoped lang="scss">
.rule-preview {
  display: flex;
  flex-direction: column;
  gap: 2rem;

  .preview-section {
    h3 {
      font-size: 1.1rem;
      font-weight: 600;
      margin: 0 0 1rem 0;
      color: #1f2937;
    }

    .rule-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;

      .summary-item {
        background: #f9fafb;
        padding: 1rem;
        border-radius: 0.375rem;
        border: 1px solid #e5e7eb;

        .label {
          font-weight: 600;
          color: #6b7280;
          display: block;
          margin-bottom: 0.25rem;
        }

        .value {
          font-size: 1.1rem;
          color: #1f2937;
        }

        .trigger-badge {
          background: #f3e8ff;
          color: #7c3aed;
          padding: 0.25rem 0.75rem;
          border-radius: 9999px;
          font-size: 0.85rem;
          font-weight: 600;
        }

        .status-badge {
          padding: 0.25rem 0.75rem;
          border-radius: 0.25rem;
          font-weight: 600;
          display: inline-block;

          &.enabled {
            background: #d1fae5;
            color: #065f46;
          }

          &.disabled {
            background: #fee2e2;
            color: #991b1b;
          }
        }
      }
    }

    .conditions-preview {
      background: #f9fafb;
      padding: 1.5rem;
      border-radius: 0.375rem;
      border: 1px solid #e5e7eb;
      font-family: 'Monaco', 'Courier New', monospace;

      .condition-text {
        word-break: break-word;
        line-height: 1.6;
      }
    }

    .actions-preview {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;

      .action-preview {
        background: white;
        border: 1px solid #e5e7eb;
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        display: flex;
        gap: 1rem;

        .action-number {
          font-weight: 600;
          color: #7c3aed;
          flex-shrink: 0;
        }

        .action-text {
          color: #1f2937;
        }
      }
    }

    .rule-logic {
      background: #f9fafb;
      padding: 1.5rem;
      border-radius: 0.375rem;
      border: 1px solid #e5e7eb;
      font-family: 'Monaco', 'Courier New', monospace;
      font-size: 0.9rem;

      .logic-text {
        margin: 0 0 1rem 0;
        color: #1f2937;
      }

      .actions-list {
        margin: 0;
        padding-left: 1.5rem;

        li {
          color: #4b5563;
          line-height: 1.6;
        }
      }
    }
  }
}
</style>
