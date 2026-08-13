<template>
  <div :class="['wh-workflow-stepper', `orientation-${orientation}`]">
    <!-- Steps track -->
    <div class="wh-stepper-track">
      <div
        v-for="(step, idx) in steps"
        :key="step.key"
        :class="['wh-step', getStepStatus(step.key)]"
      >
        <!-- Connector line (before step, skip first) -->
        <div v-if="idx > 0" class="wh-step-connector" :class="getStepStatus(steps[idx - 1].key)" />

        <!-- Step indicator -->
        <div
          class="wh-step-indicator"
          :style="{ '--step-color': step.color || 'var(--fg-3)' }"
          :title="step.alert_note || step.label"
          @click="orientation === 'horizontal' ? null : null"
        >
          <span v-if="getStepStatus(step.key) === 'completed'" class="wh-step-icon">
            <i class="pi pi-check" />
          </span>
          <span v-else-if="getStepStatus(step.key) === 'rejected'" class="wh-step-icon">
            <i class="pi pi-times" />
          </span>
          <span v-else class="wh-step-icon">
            <i :class="step.icon || 'pi pi-circle'" />
          </span>
        </div>

        <!-- Step label -->
        <div class="wh-step-label-wrap">
          <span class="wh-step-label">{{ step.label }}</span>
          <span v-if="step.alert_note && getStepStatus(step.key) === 'current'" class="wh-step-alert">
            <i class="pi pi-exclamation-triangle" />
            {{ step.alert_note }}
          </span>
        </div>
      </div>
    </div>

    <!-- Available transitions -->
    <div v-if="availableTransitions.length > 0 && showActions" class="wh-workflow-actions">
      <span class="wh-workflow-actions-label">Actions disponibles :</span>
      <button
        v-for="transition in availableTransitions"
        :key="transition.to"
        :class="['wh-transition-btn', getTransitionVariant(transition)]"
        :disabled="loading"
        @click="$emit('transition', transition)"
      >
        <i v-if="loading && pendingTransition === transition.to" class="pi pi-spin pi-spinner" />
        {{ transition.label }}
      </button>
    </div>

    <!-- Current step details card -->
    <div v-if="currentStepData && showDetails" class="wh-current-step-card">
      <div class="wh-csc-header" :style="{ borderLeftColor: currentStepData.color || 'var(--fg-3)' }">
        <i :class="currentStepData.icon || 'pi pi-circle'" />
        <strong>{{ currentStepData.label }}</strong>
      </div>
      <p v-if="currentStepData.alert_note" class="wh-csc-alert">
        <i class="pi pi-exclamation-triangle" style="color: var(--yellow-500)" />
        {{ currentStepData.alert_note }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface WorkflowStep {
  key: string
  label: string
  color?: string
  icon?: string
  alert_note?: string | null
  is_terminal?: boolean
}

interface WorkflowTransition {
  from: string
  to: string
  label: string
  requires_approval?: boolean
  allowed_roles?: string[]
}

const props = withDefaults(defineProps<{
  steps: WorkflowStep[]
  currentStep: string
  transitions?: WorkflowTransition[]
  /** Roles of the current user — used to filter available transitions */
  userRoles?: string[]
  orientation?: 'horizontal' | 'vertical'
  showActions?: boolean
  showDetails?: boolean
  loading?: boolean
  pendingTransition?: string | null
  /** For rejected state (e.g. approval rejected) */
  isRejected?: boolean
}>(), {
  transitions: () => [],
  userRoles: () => [],
  orientation: 'horizontal',
  showActions: true,
  showDetails: true,
  loading: false,
  pendingTransition: null,
  isRejected: false,
})

defineEmits<{
  transition: [transition: WorkflowTransition]
}>()

type StepStatus = 'completed' | 'current' | 'future' | 'rejected'

const stepIndex = computed(() => props.steps.findIndex(s => s.key === props.currentStep))

const getStepStatus = (key: string): StepStatus => {
  const idx = props.steps.findIndex(s => s.key === key)
  if (key === props.currentStep) {
    return props.isRejected ? 'rejected' : 'current'
  }
  if (idx < stepIndex.value) return 'completed'
  return 'future'
}

const currentStepData = computed(() =>
  props.steps.find(s => s.key === props.currentStep) ?? null
)

const availableTransitions = computed(() => {
  if (!props.transitions) return []
  return props.transitions.filter(t => {
    if (t.from !== props.currentStep) return false
    if (!t.allowed_roles || t.allowed_roles.length === 0) return true
    return props.userRoles.some(r => t.allowed_roles!.includes(r))
  })
})

const getTransitionVariant = (t: WorkflowTransition) => {
  const dangerWords = ['annul', 'rejet', 'cancel', 'reject', 'lost', 'perdu']
  const successWords = ['complet', 'approu', 'valid', 'terminé', 'livr', 'paid', 'payé', 'won', 'gagn']
  const label = t.label.toLowerCase()
  if (dangerWords.some(w => label.includes(w))) return 'btn-danger'
  if (successWords.some(w => label.includes(w))) return 'btn-success'
  return 'btn-primary'
}
</script>

<style scoped>
.wh-workflow-stepper {
  width: 100%;
}

/* Horizontal track */
.orientation-horizontal .wh-stepper-track {
  display: flex;
  align-items: flex-start;
  gap: 0;
  overflow-x: auto;
  padding: 8px 0 4px;
}

.orientation-horizontal .wh-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  flex: 1;
  min-width: 80px;
}

/* Vertical track */
.orientation-vertical .wh-stepper-track {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.orientation-vertical .wh-step {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 12px;
  position: relative;
  padding-bottom: 20px;
}

/* Connector */
.orientation-horizontal .wh-step-connector {
  position: absolute;
  top: 14px;
  right: 50%;
  width: 100%;
  height: 2px;
  background: var(--border-subtle);
  z-index: 0;
}
.orientation-horizontal .wh-step-connector.completed {
  background: var(--green-500);
}

.orientation-vertical .wh-step-connector {
  position: absolute;
  left: 14px;
  top: -20px;
  width: 2px;
  height: 20px;
  background: var(--border-subtle);
}
.orientation-vertical .wh-step-connector.completed {
  background: var(--green-500);
}

/* Step indicator */
.wh-step-indicator {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--border-subtle);
  color: var(--fg-3);
  font-size: 12px;
  position: relative;
  z-index: 1;
  border: 2px solid var(--border-subtle);
  transition: all 0.2s;
  flex-shrink: 0;
}

.wh-step.completed .wh-step-indicator {
  background: var(--green-500);
  border-color: var(--green-500);
  color: #fff;
}
.wh-step.current .wh-step-indicator {
  background: var(--step-color, var(--halo-500));
  border-color: var(--step-color, var(--halo-500));
  color: #fff;
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--step-color, var(--halo-500)) 25%, transparent);
}
.wh-step.rejected .wh-step-indicator {
  background: var(--red-500);
  border-color: var(--red-500);
  color: #fff;
}
.wh-step.future .wh-step-indicator {
  background: var(--bg-subtle);
  border-color: var(--border-subtle);
  color: var(--fg-4);
}

/* Labels */
.orientation-horizontal .wh-step-label-wrap {
  margin-top: 6px;
  text-align: center;
}
.orientation-vertical .wh-step-label-wrap {
  padding-top: 4px;
}
.wh-step-label {
  font-size: 11px;
  font-weight: 500;
  color: var(--fg-2);
  white-space: nowrap;
}
.wh-step.future .wh-step-label {
  color: var(--fg-4);
}
.wh-step.current .wh-step-label {
  color: var(--fg-1);
  font-weight: 600;
}
.wh-step-alert {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  color: var(--warn-fg);
  background: var(--warn-bg);
  border-radius: 4px;
  padding: 2px 6px;
  margin-top: 4px;
  max-width: 120px;
  white-space: normal;
  text-align: left;
}

/* Actions */
.wh-workflow-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}
.wh-workflow-actions-label {
  font-size: 12px;
  color: var(--fg-3);
}
.wh-transition-btn {
  padding: 5px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: opacity 0.15s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.wh-transition-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-transition-btn.btn-primary { background: var(--halo-500); color: #fff; }
.wh-transition-btn.btn-primary:hover:not(:disabled) { background: var(--halo-600); }
.wh-transition-btn.btn-success { background: var(--green-500); color: #fff; }
.wh-transition-btn.btn-success:hover:not(:disabled) { background: var(--green-500); }
.wh-transition-btn.btn-danger { background: var(--red-500); color: #fff; }
.wh-transition-btn.btn-danger:hover:not(:disabled) { background: var(--red-500); }

/* Current step card */
.wh-current-step-card {
  margin-top: 10px;
  padding: 10px 14px;
  border-radius: 8px;
  background: var(--bg-subtle);
  border: 1px solid var(--border-subtle);
}
.wh-csc-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  border-left: 3px solid var(--fg-3);
  padding-left: 10px;
}
.wh-csc-alert {
  font-size: 12px;
  color: var(--warn-fg);
  margin-top: 6px;
  display: flex;
  align-items: flex-start;
  gap: 6px;
}

/* Dark mode */
.dark .wh-step-label { color: var(--border-subtle); }
.dark .wh-step.future .wh-step-label { color: var(--fg-3); }
.dark .wh-step.current .wh-step-label { color: var(--bg-subtle); }
.dark .wh-current-step-card { background: var(--fg-1); border-color: var(--fg-2); }
.dark .wh-step-connector { background: var(--fg-2); }
</style>
