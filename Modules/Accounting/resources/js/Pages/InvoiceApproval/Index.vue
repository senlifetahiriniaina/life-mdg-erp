<template>
  <div class="accounting-invoice-approval-index">
    <!-- Header -->
    <div class="page-header">
      <div class="header-content">
        <h1>Approbation des factures</h1>
        <p class="text-muted">Gérer et suivre les factures en attente d'approbation</p>
      </div>
      <div class="header-stats">
        <div class="stat-card pending">
          <span class="label">En attente</span>
          <span class="value">{{ stats.pending_count }}</span>
        </div>
        <div class="stat-card urgent">
          <span class="label">Urgent (&gt;5 jours)</span>
          <span class="value">{{ stats.urgent_count }}</span>
        </div>
        <div class="stat-card this-month">
          <span class="label">Taux d'approbation (30j)</span>
          <span class="value">{{ stats.approval_rate_percent }}%</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters">
      <div class="filter-group">
        <select v-model="filters.approval_level" class="form-control">
          <option value="">Tous les niveaux</option>
          <option v-for="l in levels" :key="l.level" :value="l.level">{{ l.label }}</option>
        </select>
      </div>
      <div class="filter-group">
        <input
          v-model="filters.search"
          type="text"
          class="form-control"
          placeholder="Rechercher un numéro de facture ou un tiers..."
        />
      </div>
    </div>

    <!-- Invoices Table -->
    <div class="table-container">
      <table class="table table-hover">
        <thead>
          <tr>
            <th scope="col">Facture #</th>
            <th scope="col">Tiers</th>
            <th scope="col">Montant</th>
            <th scope="col">Niveau requis</th>
            <th scope="col">Jours en attente</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invoice in filteredInvoices" :key="invoice.id" :class="invoiceRowClass(invoice)">
            <td>
              <Link :href="`/invoices/${invoice.id}/approval`" class="invoice-link">
                {{ invoice.invoice_number }}
              </Link>
            </td>
            <td>{{ invoice.supplier_name }}</td>
            <td class="text-right">
              <span class="currency">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</span>
            </td>
            <td>
              <span class="badge-level">{{ invoice.required_approval_label }}</span>
            </td>
            <td class="text-center">
              <span :class="daysStyle(invoice.days_pending)">
                {{ invoice.days_pending }} jours
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button
                  @click="openApproveModal(invoice)"
                  class="btn-sm btn-success"
                  title="Approuver"
                >
                  Approuver
                </button>
                <button
                  @click="openRejectModal(invoice)"
                  class="btn-sm btn-danger"
                  title="Rejeter"
                >
                  Rejeter
                </button>
                <Link :href="`/invoices/${invoice.id}/approval`" class="btn-sm btn-info" title="Voir le détail">
                  Voir
                </Link>
              </div>
            </td>
          </tr>
          <tr v-if="!filteredInvoices.length">
            <td colspan="6" class="text-center text-muted">Aucune facture en attente d'approbation.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="error" class="error-message">{{ error }}</p>

    <!-- Approve Modal -->
    <div v-if="showApproveModal" class="modal-overlay">
      <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
          <h3>Approuver la facture</h3>
          <button @click="showApproveModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <p><strong>Facture :</strong> {{ selectedInvoice?.invoice_number }}</p>
          <p><strong>Montant :</strong> {{ formatCurrency(selectedInvoice?.total_amount, selectedInvoice?.currency) }}</p>
          <p><strong>Tiers :</strong> {{ selectedInvoice?.supplier_name }}</p>
          <div class="form-group">
            <label for="approve-comments">Commentaire (optionnel)</label>
            <textarea id="approve-comments" v-model="approveComments" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showApproveModal = false" class="btn btn-secondary">Annuler</button>
          <button @click="submitApprove" :disabled="actionPending" class="btn btn-success">Approuver</button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="modal-overlay">
      <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
          <h3>Rejeter la facture</h3>
          <button @click="showRejectModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <p><strong>Facture :</strong> {{ selectedInvoice?.invoice_number }}</p>
          <p><strong>Montant :</strong> {{ formatCurrency(selectedInvoice?.total_amount, selectedInvoice?.currency) }}</p>
          <div class="form-group">
            <label for="reject-reason">Motif du rejet</label>
            <textarea id="reject-reason" v-model="rejectReason" class="form-control" rows="4" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showRejectModal = false" class="btn btn-secondary">Annuler</button>
          <button @click="submitReject" :disabled="actionPending || !rejectReason" class="btn btn-danger">Rejeter</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  invoices: { type: Array, default: () => [] },
  levels: { type: Array, default: () => [] },
  stats: {
    type: Object,
    default: () => ({ pending_count: 0, urgent_count: 0, approval_rate_percent: 0 }),
  },
})

const filters = ref({
  approval_level: '',
  search: '',
})
const selectedInvoice = ref(null)
const approveComments = ref('')
const rejectReason = ref('')
const showApproveModal = ref(false)
const showRejectModal = ref(false)
const actionPending = ref(false)
const error = ref('')

const filteredInvoices = computed(() => {
  return props.invoices.filter(inv => {
    if (filters.value.approval_level && inv.required_approval_level !== filters.value.approval_level) return false
    if (filters.value.search) {
      const search = filters.value.search.toLowerCase()
      return (
        inv.invoice_number?.toLowerCase().includes(search) ||
        inv.supplier_name?.toLowerCase().includes(search)
      )
    }
    return true
  })
})

const formatCurrency = (amount, currency) => new Intl.NumberFormat('fr-FR', {
  style: 'currency', currency: currency || 'XOF',
}).format(amount || 0)

const invoiceRowClass = (invoice) => ({
  'row-urgent': invoice.days_pending > 5,
})

const daysStyle = (days) => ({
  'text-danger': days > 5,
  'text-warning': days > 2 && days <= 5,
})

const openApproveModal = (invoice) => {
  selectedInvoice.value = invoice
  approveComments.value = ''
  showApproveModal.value = true
}

const openRejectModal = (invoice) => {
  selectedInvoice.value = invoice
  rejectReason.value = ''
  showRejectModal.value = true
}

const submitApprove = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/accounting/invoices/${selectedInvoice.value.id}/approve`, {
      comment: approveComments.value,
    })
    showApproveModal.value = false
    router.reload({ only: ['invoices', 'stats'] })
  } catch (err) {
    error.value = err.response?.data?.message || "Échec de l'approbation."
  } finally {
    actionPending.value = false
  }
}

const submitReject = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/accounting/invoices/${selectedInvoice.value.id}/reject`, {
      reason: rejectReason.value,
    })
    showRejectModal.value = false
    router.reload({ only: ['invoices', 'stats'] })
  } catch (err) {
    error.value = err.response?.data?.message || 'Échec du rejet.'
  } finally {
    actionPending.value = false
  }
}
</script>

<style scoped>
.accounting-invoice-approval-index {
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

.header-content h1 {
  margin: 0;
  font-size: 28px;
  color: var(--slate-700);
}

.text-muted {
  color: var(--slate-500);
  margin-top: 5px;
}

.header-stats {
  display: flex;
  gap: 20px;
}

.stat-card {
  padding: 15px 20px;
  border-radius: 6px;
  background: var(--slate-50);
  border-left: 4px solid var(--slate-300);
}

.stat-card.pending {
  border-left-color: var(--amber-400);
}

.stat-card.urgent {
  border-left-color: var(--red-500);
}

.stat-card.this-month {
  border-left-color: var(--green-500);
}

.stat-card .label {
  display: block;
  font-size: 12px;
  color: var(--slate-500);
  text-transform: uppercase;
  margin-bottom: 5px;
}

.stat-card .value {
  font-size: 24px;
  font-weight: bold;
  color: var(--slate-700);
}

.filters {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  background: white;
  padding: 15px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.filter-group {
  flex: 1;
  min-width: 200px;
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--slate-200);
  border-radius: 4px;
  font-size: 14px;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}

.btn-secondary {
  background: var(--slate-500);
  color: white;
}

.btn-secondary:hover {
  background: var(--slate-600);
}

.table-container {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin: 0;
}

.table th {
  background: var(--slate-50);
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: var(--slate-700);
  border-bottom: 2px solid var(--slate-200);
}

.table td {
  padding: 12px;
  border-bottom: 1px solid var(--slate-100);
}

.table tr:hover {
  background: var(--slate-50);
}

.row-urgent {
  background: var(--yellow-50);
}

.invoice-link {
  color: var(--halo-500);
  text-decoration: none;
  font-weight: 500;
}

.invoice-link:hover {
  text-decoration: underline;
}

.currency {
  font-weight: 600;
  color: var(--slate-700);
}

.badge-level {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 3px;
  font-size: 12px;
  font-weight: 600;
  background: var(--halo-100);
  color: var(--halo-800);
}

.text-danger {
  color: var(--red-500);
  font-weight: 600;
}

.text-warning {
  color: var(--amber-400);
  font-weight: 600;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.action-buttons {
  display: flex;
  gap: 5px;
}

.btn-sm {
  padding: 4px 8px;
  font-size: 12px;
  border: none;
  border-radius: 3px;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
}

.btn-success {
  background: var(--green-500);
  color: white;
}

.btn-success:hover {
  background: var(--green-600);
}

.btn-danger {
  background: var(--red-500);
  color: white;
}

.btn-danger:hover {
  background: var(--red-600);
}

.btn-info {
  background: var(--halo-600);
  color: white;
}

.btn-info:hover {
  background: var(--halo-700);
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  min-width: 400px;
  max-width: 500px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid var(--slate-100);
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--slate-700);
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: var(--slate-500);
}

.modal-body {
  padding: 20px;
}

.modal-body p {
  margin: 10px 0;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
  color: var(--slate-700);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 15px 20px;
  border-top: 1px solid var(--slate-100);
}

.error-message {
  margin-top: 12px;
  color: var(--red-500);
  font-size: 13px;
}
</style>
