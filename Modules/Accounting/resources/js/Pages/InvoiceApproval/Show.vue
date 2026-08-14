<template>
  <div class="invoice-approval-show">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1>Facture {{ invoice.number }}</h1>
        <p class="breadcrumb">
          <Link href="/accounting/invoices">Factures</Link> / {{ invoice.number }}
        </p>
      </div>
      <div class="header-status">
        <span :class="statusBadgeClass(invoice.approval_status)">
          {{ formatStatus(invoice.approval_status) }}
        </span>
      </div>
    </div>

    <!-- Main Content -->
    <div class="content-grid">
      <!-- Left: Invoice Details -->
      <div class="invoice-details">
        <div class="card">
          <h3>Détails de la facture</h3>
          <div class="detail-row">
            <span class="label">Numéro:</span>
            <span class="value">{{ invoice.number }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Tiers:</span>
            <span class="value">{{ invoice.partner_name || invoice.customer_name || '—' }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Montant:</span>
            <span class="value amount">{{ formatCurrency(invoice.total, invoice.currency) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Date de facture:</span>
            <span class="value">{{ formatDate(invoice.invoice_date) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Échéance:</span>
            <span class="value">{{ formatDate(invoice.due_date) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Niveau requis:</span>
            <span class="value">{{ label }}</span>
          </div>
        </div>
      </div>

      <!-- Right: Approval Panel -->
      <div class="approval-chain">
        <div class="card">
          <h3>Progression de l'approbation</h3>
          <ApprovalPanel
            :instance="instance"
            :steps="steps"
            :decisions="decisions"
            :can-approve="canApprove"
            @decide="onDecide"
          />
        </div>
      </div>
    </div>

    <p v-if="error" class="error-message">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import ApprovalPanel from '@/Components/UI/ApprovalPanel.vue'

const props = defineProps({
  invoice: { type: Object, required: true },
  level: { type: Number, required: true },
  label: { type: String, required: true },
  levels: { type: Array, required: true },
  chain: { type: Array, default: () => [] },
  can_approve: { type: Boolean, default: false },
})

const error = ref('')
const canApprove = computed(() => props.can_approve)

// The 3 amount-tiered levels are fixed and shared by every invoice
// (InvoiceApprovalService::getLevelLabel) — unlike Achats' PO routing,
// invoices don't go through a per-request hierarchy.
const steps = computed(() => props.levels.map(l => ({
  order: l.level,
  label: l.label,
  approver_type: 'role',
  approver_value: l.label,
})))

// getApprovalChain() returns actions chronologically with no per-action
// level — each entry corresponds to the Nth decision made, which for a
// single-workflow invoice is level N (see InvoiceApprovalService docblock).
const decisions = computed(() => props.chain.map((c, idx) => ({
  step_order: idx + 1,
  approver_name: c.approver,
  decision: c.action,
  comment: c.comment,
  decided_at: c.acted_at,
})))

const instance = computed(() => {
  if (props.invoice.approval_status === 'draft' || !props.invoice.approval_status) return null
  return {
    id: props.invoice.id,
    current_step: props.invoice.approval_status === 'approved' ? steps.value.length + 1 : props.level,
    status: props.invoice.approval_status,
    initiated_by: null,
    decisions: decisions.value,
  }
})

const formatStatus = (status) => ({
  pending: 'En attente', approved: 'Approuvée', rejected: 'Rejetée', draft: 'Brouillon',
})[status] ?? (status || '—')

const formatDate = (date) => date
  ? new Date(date).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const formatCurrency = (amount, currency) => new Intl.NumberFormat('fr-FR', {
  style: 'currency', currency: currency || 'XOF',
}).format(amount || 0)

const statusBadgeClass = (status) => ({
  badge: true,
  'badge-pending': status === 'pending',
  'badge-approved': status === 'approved',
  'badge-rejected': status === 'rejected',
})

const onDecide = async ({ decision, comment }) => {
  error.value = ''
  try {
    if (decision === 'approved') {
      await axios.post(`/api/v1/accounting/invoices/${props.invoice.id}/approve`, { comment })
    } else {
      await axios.post(`/api/v1/accounting/invoices/${props.invoice.id}/reject`, { reason: comment || 'Rejetée' })
    }
    router.reload({ only: ['invoice', 'level', 'label', 'chain', 'can_approve'] })
  } catch (err) {
    error.value = err.response?.data?.message || 'Une erreur est survenue.'
  }
}
</script>

<style scoped>
.invoice-approval-show {
  padding: 20px;
  background: var(--slate-50);
  min-height: 100vh;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.page-header h1 {
  margin: 0 0 10px 0;
  font-size: 28px;
  color: var(--slate-700);
}

.breadcrumb {
  margin: 0;
  color: var(--slate-500);
  font-size: 14px;
}

.breadcrumb :deep(a) {
  color: var(--halo-500);
  text-decoration: none;
}

.breadcrumb :deep(a:hover) {
  text-decoration: underline;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card h3 {
  margin: 0 0 20px 0;
  font-size: 18px;
  color: var(--slate-700);
  border-bottom: 2px solid var(--slate-100);
  padding-bottom: 10px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid var(--slate-100);
}

.detail-row .label {
  color: var(--slate-500);
  font-weight: 500;
}

.detail-row .value {
  color: var(--slate-700);
  font-weight: 600;
}

.detail-row .amount {
  color: var(--green-500);
  font-size: 16px;
}

.badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 4px;
  font-weight: 600;
  font-size: 14px;
}

.badge-pending {
  background: var(--amber-400);
  color: var(--slate-700);
}

.badge-approved {
  background: var(--green-500);
  color: white;
}

.badge-rejected {
  background: var(--red-500);
  color: white;
}

.error-message {
  padding: 15px;
  background: var(--red-50);
  color: var(--red-800);
  border-radius: 4px;
  text-align: center;
  font-weight: 600;
  margin-top: 16px;
}
</style>
