<template>
  <div class="wh-approval-panel">
    <!-- Loading state -->
    <div v-if="loading" class="wh-ap-loading">
      <i class="pi pi-spin pi-spinner" />
      <span>Chargement...</span>
    </div>

    <!-- No active workflow -->
    <div v-else-if="!instance" class="wh-ap-empty">
      <i class="pi pi-shield" style="font-size: 20px; color: var(--fg-4)" />
      <span>Aucune validation en cours</span>
      <button v-if="canInitiate" class="wh-ap-start-btn" @click="$emit('start')">
        Démarrer une validation
      </button>
    </div>

    <!-- Active approval instance -->
    <div v-else class="wh-ap-content">
      <!-- Status badge -->
      <div class="wh-ap-status-row">
        <span :class="['wh-ap-badge', `status-${instance.status}`]">
          <i :class="statusIcon(instance.status)" />
          {{ statusLabel(instance.status) }}
        </span>
        <span class="wh-ap-step-info">
          Étape {{ instance.current_step }} / {{ steps.length }}
        </span>
      </div>

      <!-- Steps timeline -->
      <div class="wh-ap-timeline">
        <div
          v-for="(step, idx) in steps"
          :key="idx"
          :class="['wh-ap-timeline-item', getStepClass(idx + 1)]"
        >
          <!-- Vertical connector -->
          <div class="wh-ap-connector">
            <div :class="['wh-ap-dot', getStepClass(idx + 1)]" />
            <div v-if="idx < steps.length - 1" class="wh-ap-line" />
          </div>

          <!-- Step content -->
          <div class="wh-ap-step-body">
            <div class="wh-ap-step-header">
              <strong>{{ step.label }}</strong>
              <span class="wh-ap-approver-tag">
                <i :class="approverIcon(step.approver_type)" />
                {{ formatApprover(step) }}
              </span>
            </div>

            <!-- Decision already made for this step -->
            <div v-if="getDecisionForStep(idx + 1)" class="wh-ap-decision">
              <div :class="['wh-ap-decision-badge', `dec-${getDecisionForStep(idx + 1)?.decision}`]">
                <i :class="decisionIcon(getDecisionForStep(idx + 1)?.decision)" />
                {{ decisionLabel(getDecisionForStep(idx + 1)?.decision) }}
              </div>
              <span class="wh-ap-decision-meta">
                par {{ getDecisionForStep(idx + 1)?.approver_name }}
                · {{ formatDate(getDecisionForStep(idx + 1)?.decided_at) }}
              </span>
              <p v-if="getDecisionForStep(idx + 1)?.comment" class="wh-ap-comment">
                "{{ getDecisionForStep(idx + 1)?.comment }}"
              </p>
            </div>

            <!-- Current step — show action form if user can approve -->
            <div v-else-if="idx + 1 === instance.current_step && canApprove && instance.status === 'pending'" class="wh-ap-action-form">
              <textarea
                v-model="comment"
                class="wh-ap-comment-input"
                placeholder="Commentaire (optionnel)..."
                rows="2"
              />
              <div class="wh-ap-form-actions">
                <button
                  class="wh-ap-btn wh-ap-btn-approve"
                  :disabled="submitting"
                  @click="submitDecision('approved')"
                >
                  <i class="pi pi-check" />
                  Approuver
                </button>
                <button
                  class="wh-ap-btn wh-ap-btn-reject"
                  :disabled="submitting"
                  @click="submitDecision('rejected')"
                >
                  <i class="pi pi-times" />
                  Rejeter
                </button>
              </div>
            </div>

            <!-- Pending step -->
            <div v-else-if="idx + 1 === instance.current_step" class="wh-ap-pending-note">
              <i class="pi pi-clock" />
              En attente d'approbation
            </div>
          </div>
        </div>
      </div>

      <!-- Cancel button (initiator can cancel) -->
      <div v-if="instance.status === 'pending' && canCancel" class="wh-ap-footer">
        <button class="wh-ap-cancel-btn" :disabled="submitting" @click="$emit('cancel')">
          <i class="pi pi-times-circle" />
          Annuler la validation
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface ApprovalStep {
  order: number
  label: string
  approver_type: 'user' | 'role' | 'department'
  approver_value: string
  timeout_hours?: number | null
  requires_all?: boolean
}

interface ApprovalDecision {
  step_order: number
  approver_id: number
  approver_name?: string
  decision: 'approved' | 'rejected' | 'escalated'
  comment?: string | null
  decided_at: string
}

interface ApprovalInstance {
  id: number
  current_step: number
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  initiated_by: number
  completed_at?: string | null
  decisions?: ApprovalDecision[]
}

const props = defineProps<{
  instance: ApprovalInstance | null
  steps: ApprovalStep[]
  decisions?: ApprovalDecision[]
  canApprove?: boolean
  canInitiate?: boolean
  canCancel?: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  start: []
  cancel: []
  decide: [{ decision: 'approved' | 'rejected'; comment: string }]
}>()

const comment = ref('')
const submitting = ref(false)

const submitDecision = async (decision: 'approved' | 'rejected') => {
  submitting.value = true
  try {
    emit('decide', { decision, comment: comment.value })
    comment.value = ''
  } finally {
    submitting.value = false
  }
}

const getDecisionForStep = (stepOrder: number): ApprovalDecision | undefined =>
  (props.decisions ?? props.instance?.decisions ?? []).find(d => d.step_order === stepOrder)

const getStepClass = (stepOrder: number) => {
  if (!props.instance) return 'future'
  const decision = getDecisionForStep(stepOrder)
  if (decision?.decision === 'rejected') return 'rejected'
  if (decision?.decision === 'approved') return 'completed'
  if (stepOrder === props.instance.current_step) return 'current'
  if (stepOrder < props.instance.current_step) return 'completed'
  return 'future'
}

const statusLabel = (status: string) => ({
  pending: 'En attente', approved: 'Approuvé',
  rejected: 'Rejeté', cancelled: 'Annulé',
})[status] ?? status

const statusIcon = (status: string) => ({
  pending: 'pi pi-clock',
  approved: 'pi pi-check-circle',
  rejected: 'pi pi-times-circle',
  cancelled: 'pi pi-ban',
})[status] ?? 'pi pi-circle'

const decisionLabel = (d?: string) => ({
  approved: 'Approuvé', rejected: 'Rejeté', escalated: 'Escaladé',
})[d ?? ''] ?? ''

const decisionIcon = (d?: string) => ({
  approved: 'pi pi-check', rejected: 'pi pi-times', escalated: 'pi pi-arrow-up',
})[d ?? ''] ?? 'pi pi-circle'

const approverIcon = (type: string) => ({
  user: 'pi pi-user', role: 'pi pi-users', department: 'pi pi-building',
})[type] ?? 'pi pi-user'

const formatApprover = (step: ApprovalStep) => {
  if (step.approver_type === 'role') return `Rôle : ${step.approver_value}`
  if (step.approver_type === 'department') return `Dép. : ${step.approver_value}`
  return `Utilisateur #${step.approver_value}`
}

const formatDate = (d?: string) => d
  ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
  : ''
</script>

<style scoped>
.wh-approval-panel {
  border: 1px solid var(--border-subtle);
  border-radius: 10px;
  padding: 16px;
  background: #fff;
}
.dark .wh-approval-panel {
  background: var(--fg-1);
  border-color: var(--fg-2);
}

/* Loading / empty */
.wh-ap-loading, .wh-ap-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px;
  color: var(--fg-3);
  font-size: 13px;
}

/* Status row */
.wh-ap-status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.wh-ap-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
}
.wh-ap-badge.status-pending { background: var(--warn-bg); color: var(--warn-fg); }
.wh-ap-badge.status-approved { background: var(--success-bg); color: var(--success-fg); }
.wh-ap-badge.status-rejected { background: var(--danger-bg); color: var(--danger-fg); }
.wh-ap-badge.status-cancelled { background: var(--bg-subtle); color: var(--fg-3); }
.wh-ap-step-info { font-size: 12px; color: var(--fg-4); }

/* Timeline */
.wh-ap-timeline { display: flex; flex-direction: column; gap: 0; }
.wh-ap-timeline-item { display: flex; gap: 12px; }

.wh-ap-connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 20px;
  flex-shrink: 0;
}
.wh-ap-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid var(--border-subtle);
  background: #fff;
  flex-shrink: 0;
  margin-top: 2px;
}
.wh-ap-dot.completed { background: var(--green-500); border-color: var(--green-500); }
.wh-ap-dot.current { background: var(--halo-500); border-color: var(--halo-500); box-shadow: 0 0 0 3px var(--halo-100); }
.wh-ap-dot.rejected { background: var(--red-500); border-color: var(--red-500); }
.wh-ap-line { width: 2px; flex: 1; background: var(--border-subtle); min-height: 16px; }

.wh-ap-step-body { flex: 1; padding-bottom: 16px; }
.wh-ap-step-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 6px;
  font-size: 13px;
}
.wh-ap-approver-tag {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--fg-3);
  background: var(--bg-subtle);
  padding: 2px 8px;
  border-radius: 4px;
}

/* Decision display */
.wh-ap-decision { margin-top: 4px; }
.wh-ap-decision-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}
.wh-ap-decision-badge.dec-approved { background: var(--success-bg); color: var(--success-fg); }
.wh-ap-decision-badge.dec-rejected { background: var(--danger-bg); color: var(--danger-fg); }
.wh-ap-decision-badge.dec-escalated { background: var(--warn-bg); color: var(--warn-fg); }
.wh-ap-decision-meta { font-size: 11px; color: var(--fg-4); margin-left: 8px; }
.wh-ap-comment {
  font-size: 12px;
  font-style: italic;
  color: var(--fg-3);
  margin-top: 4px;
  padding-left: 8px;
  border-left: 2px solid var(--border-subtle);
}

/* Action form */
.wh-ap-action-form { margin-top: 6px; }
.wh-ap-comment-input {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  font-size: 12px;
  resize: vertical;
  color: inherit;
  background: inherit;
}
.wh-ap-form-actions { display: flex; gap: 8px; margin-top: 6px; }
.wh-ap-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 5px 14px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}
.wh-ap-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-ap-btn-approve { background: var(--green-500); color: #fff; }
.wh-ap-btn-approve:hover:not(:disabled) { background: var(--green-500); }
.wh-ap-btn-reject { background: var(--red-500); color: #fff; }
.wh-ap-btn-reject:hover:not(:disabled) { background: var(--red-500); }

/* Pending note */
.wh-ap-pending-note {
  font-size: 12px;
  color: var(--warn-fg);
  background: var(--warn-bg);
  padding: 4px 10px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Footer */
.wh-ap-footer { margin-top: 8px; border-top: 1px solid var(--bg-subtle); padding-top: 10px; }
.wh-ap-cancel-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--fg-3);
  background: none;
  border: 1px solid var(--border-subtle);
  padding: 4px 12px;
  border-radius: 6px;
  cursor: pointer;
}
.wh-ap-cancel-btn:hover:not(:disabled) { color: var(--red-500); border-color: var(--red-500); }

/* Start button */
.wh-ap-start-btn {
  background: var(--halo-500);
  color: #fff;
  border: none;
  padding: 6px 16px;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  margin-top: 4px;
}
</style>
