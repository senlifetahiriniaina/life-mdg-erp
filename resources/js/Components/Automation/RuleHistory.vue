<template>
  <div class="rule-history">
    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner"></i> Loading history...
    </div>

    <div v-else-if="executions.length === 0" class="empty">
      <i class="pi pi-inbox"></i>
      <p>No execution history yet</p>
    </div>

    <div v-else class="history-list">
      <div v-for="execution in executions" :key="execution.id" class="execution-item">
        <div class="execution-header">
          <div class="status-indicator" :class="execution.conditions_met ? 'success' : 'failure'"></div>
          <div class="execution-info">
            <span class="conditions">
              {{ execution.conditions_met ? '✓ Conditions Met' : '✗ Conditions Not Met' }}
            </span>
            <span class="timestamp">{{ formatDate(execution.executed_at) }}</span>
          </div>
          <div class="execution-meta">
            <span class="duration">{{ execution.duration_ms }}ms</span>
            <span v-if="execution.error_message" class="error-badge">Error</span>
          </div>
        </div>

        <div v-if="execution.error_message" class="execution-error">
          <i class="pi pi-exclamation-triangle"></i>
          {{ execution.error_message }}
        </div>

        <div v-if="execution.conditions_met && execution.actions_executed?.length" class="execution-actions">
          <span class="label">Actions Executed:</span>
          <ul>
            <li v-for="(action, idx) in execution.actions_executed" :key="idx">
              <span :class="['action-status', action.status]">{{ action.status }}</span>
              {{ action.type }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'

interface ExecutionAction {
  status: string
  type: string
}

interface Execution {
  id: number
  conditions_met: boolean
  executed_at: string
  duration_ms: number
  error_message?: string | null
  actions_executed?: ExecutionAction[]
}

const props = defineProps<{
  rule: { id: number | null }
}>()

const { get } = useApi()

const executions = ref<Execution[]>([])
const loading = ref(false)

onMounted(async () => {
  if (!props.rule.id) return
  loading.value = true
  try {
    const response = await get<{ data: Execution[] }>(`/api/v1/automation/rules/${props.rule.id}/executions`)
    executions.value = response.data || []
  } finally {
    loading.value = false
  }
})

const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleString()
}
</script>

<style scoped lang="scss">
.rule-history {
  .loading {
    text-align: center;
    padding: 2rem;
    color: #666;

    i {
      margin-right: 0.5rem;
    }
  }

  .empty {
    text-align: center;
    padding: 3rem;
    color: #999;

    i {
      font-size: 2rem;
      display: block;
      margin-bottom: 1rem;
      color: #ddd;
    }
  }

  .history-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;

    .execution-item {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      padding: 1.5rem;

      .execution-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;

        .status-indicator {
          width: 12px;
          height: 12px;
          border-radius: 50%;

          &.success {
            background: #10b981;
          }

          &.failure {
            background: #ef4444;
          }
        }

        .execution-info {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 0.25rem;

          .conditions {
            font-weight: 600;
          }

          .timestamp {
            color: #999;
            font-size: 0.85rem;
          }
        }

        .execution-meta {
          display: flex;
          gap: 1rem;
          align-items: center;

          .duration {
            background: #f3f4f6;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            font-weight: 600;
          }

          .error-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
          }
        }
      }

      .execution-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 0.75rem;
        border-radius: 0.375rem;
        margin-bottom: 1rem;
        display: flex;
        gap: 0.5rem;
        align-items: center;

        i {
          flex-shrink: 0;
        }
      }

      .execution-actions {
        font-size: 0.9rem;

        .label {
          display: block;
          font-weight: 600;
          margin-bottom: 0.5rem;
        }

        ul {
          margin: 0;
          padding-left: 1.5rem;
          list-style: none;

          li {
            padding: 0.25rem 0;
            display: flex;
            gap: 0.75rem;
            align-items: center;

            .action-status {
              padding: 0.125rem 0.5rem;
              border-radius: 0.25rem;
              font-size: 0.75rem;
              font-weight: 600;

              &.success {
                background: #d1fae5;
                color: #065f46;
              }

              &.failed {
                background: #fee2e2;
                color: #991b1b;
              }
            }
          }
        }
      }
    }
  }
}
</style>
