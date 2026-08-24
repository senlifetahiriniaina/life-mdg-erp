<template>
  <AppLayout>
  <div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Demande d'approbation #{{ approvalRequest.id }}</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">
          {{ approvalRequest.approvable_type?.split('\\').pop() || 'Type inconnu' }} #{{ approvalRequest.approvable_id }}
        </p>
      </div>
      <Link href="/approval-requests" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Retour aux approbations
      </Link>
    </div>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="space-y-6">
      <!-- Request Details -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Détails de la demande</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Workflow</p>
            <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ approvalRequest.workflow?.name || '—' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Demandeur</p>
            <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ approvalRequest.requester?.name || '—' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Date de soumission</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ formatDate(approvalRequest.created_at) }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Statut</p>
            <span :class="['px-3 py-1 rounded-full text-xs font-medium inline-block', statusClasses[approvalRequest.status] || 'bg-gray-100 text-gray-800']">
              {{ formatStatus(approvalRequest.status) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Approval Panel -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Progression de l'approbation</h3>
        <ApprovalPanel
          :instance="instance"
          :steps="steps"
          :decisions="decisions"
          :can-approve="approvalRequest.can_approve"
          @decide="onDecide"
        />
      </div>

      <p v-if="error" class="text-sm text-red-700 dark:text-red-300">{{ error }}</p>
    </div>
  </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import ApprovalPanel from '@/Components/UI/ApprovalPanel.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const props = defineProps({
  approvalRequest: { type: Object, required: true },
})

const { guidance } = useAiAssistant('Validation', 'view_approval_request')

const error = ref('')

const statusClasses = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  cancelled: 'bg-gray-100 text-gray-800',
}

const formatStatus = (status) => status ? status.charAt(0).toUpperCase() + status.slice(1) : '—'

const formatDate = (date) => date
  ? new Date(date).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  : '—'

// Prefer the real hierarchy levels when this request was routed through one
// (Achats/multi-level flows); fall back to a generic N-step list built from
// total_levels for requests that only ever have a single decision.
const steps = computed(() => {
  const levels = props.approvalRequest.hierarchy?.levels
  if (levels?.length) {
    return levels.map(l => ({ order: l.level_order, label: l.title, approver_type: 'role', approver_value: l.title }))
  }
  const total = props.approvalRequest.total_levels || 1
  return Array.from({ length: total }, (_, i) => ({
    order: i + 1,
    label: `Niveau ${i + 1}`,
    approver_type: 'user',
    approver_value: String(props.approvalRequest.approver_id ?? '—'),
  }))
})

// ApprovalHistory carries `level` (Phase C1) but not a comment; decisions
// are keyed by step_order so ApprovalPanel can place each one under the
// right step in the timeline. `changed_by` is eager-loaded as the
// `changedBy` relation server-side, which Eloquent's toArray() collapses
// onto the `changed_by` key (snake_case of the relation name) — it arrives
// here as the User object, not the raw FK integer.
const decisions = computed(() => (props.approvalRequest.history || [])
  .filter(h => h.action === 'approved' || h.action === 'rejected')
  .map(h => ({
    step_order: h.level || 1,
    approver_id: h.changed_by?.id,
    approver_name: h.changed_by?.name,
    decision: h.action,
    decided_at: h.changed_at,
  })))

// current_level never advances past the level a decision was made at (a
// known, pre-existing gap in the underlying multi-level state machine — see
// InvoiceApprovalService's threshold-boundary note) — once the request is
// fully approved, treat every step as completed rather than showing later
// steps stuck on "future".
const instance = computed(() => ({
  id: props.approvalRequest.id,
  current_step: props.approvalRequest.status === 'approved'
    ? steps.value.length + 1
    : (props.approvalRequest.current_level || 1),
  status: props.approvalRequest.status,
  initiated_by: props.approvalRequest.requested_by,
  decisions: decisions.value,
}))

const onDecide = async ({ decision, comment }) => {
  error.value = ''
  try {
    if (decision === 'approved') {
      await axios.post(`/api/v1/validation/approval-requests/${props.approvalRequest.id}/approve`, { comment })
    } else {
      await axios.post(`/api/v1/validation/approval-requests/${props.approvalRequest.id}/reject`, { reason: comment || 'Rejeté' })
    }
    router.reload({ only: ['approvalRequest'] })
  } catch (err) {
    error.value = err.response?.data?.message || 'Une erreur est survenue.'
  }
}
</script>
