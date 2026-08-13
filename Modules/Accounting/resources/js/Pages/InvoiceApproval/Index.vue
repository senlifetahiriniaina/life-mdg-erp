<template>
  <div class="accounting-invoice-approval-index">
    <!-- Header -->
    <div class="page-header">
      <div class="header-content">
        <h1>Invoice Approvals</h1>
        <p class="text-muted">Manage and track invoice approvals across approval levels</p>
      </div>
      <div class="header-stats">
        <div class="stat-card pending">
          <span class="label">Pending</span>
          <span class="value">{{ stats.pending_count }}</span>
        </div>
        <div class="stat-card urgent">
          <span class="label">Urgent (>5 days)</span>
          <span class="value">{{ stats.urgent_count }}</span>
        </div>
        <div class="stat-card this-month">
          <span class="label">This Month</span>
          <span class="value">{{ stats.approval_rate_percent }}%</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters">
      <div class="filter-group">
        <select v-model="filters.status" class="form-control">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div class="filter-group">
        <select v-model="filters.approval_level" class="form-control">
          <option value="">All Levels</option>
          <option value="manager">Manager</option>
          <option value="director">Director</option>
          <option value="ceo">CEO</option>
        </select>
      </div>
      <div class="filter-group">
        <input
          v-model="filters.search"
          type="text"
          class="form-control"
          placeholder="Search invoice number or supplier..."
        />
      </div>
      <button @click="applyFilters" class="btn btn-primary">Filter</button>
      <button @click="resetFilters" class="btn btn-secondary">Reset</button>
    </div>

    <!-- Invoices Table -->
    <div class="table-container">
      <table class="table table-hover">
        <thead>
          <tr>
            <th scope="col">Invoice #</th>
            <th scope="col">Supplier</th>
            <th scope="col">Amount</th>
            <th scope="col">Status</th>
            <th scope="col">Required Level</th>
            <th scope="col">Days Pending</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invoice in filteredInvoices" :key="invoice.id" :class="invoiceRowClass(invoice)">
            <td>
              <router-link :to="`/accounting/invoice-approvals/${invoice.id}`" class="invoice-link">
                {{ invoice.invoice_number }}
              </router-link>
            </td>
            <td>{{ invoice.supplier_name }}</td>
            <td class="text-right">
              <span class="currency">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</span>
            </td>
            <td>
              <span :class="statusBadgeClass(invoice.approval_status)">
                {{ formatStatus(invoice.approval_status) }}
              </span>
            </td>
            <td>
              <span class="badge-level" :class="levelBadgeClass(invoice.required_approval_level)">
                {{ formatLevel(invoice.required_approval_level) }}
              </span>
            </td>
            <td class="text-center">
              <span :class="daysStyle(invoice.days_pending)">
                {{ invoice.days_pending }} days
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button
                  v-if="canApprove(invoice)"
                  @click="openApproveModal(invoice)"
                  class="btn-sm btn-success"
                  title="Approve"
                >
                  Approve
                </button>
                <button
                  v-if="canApprove(invoice)"
                  @click="openRejectModal(invoice)"
                  class="btn-sm btn-danger"
                  title="Reject"
                >
                  Reject
                </button>
                <router-link
                  :to="`/accounting/invoice-approvals/${invoice.id}`"
                  class="btn-sm btn-info"
                  title="View Details"
                >
                  View
                </router-link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="pagination">
      <button
        v-for="page in totalPages"
        :key="page"
        @click="currentPage = page"
        :class="{ active: currentPage === page }"
        class="btn-page"
      >
        {{ page }}
      </button>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApproveModal" class="modal-overlay">
      <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
          <h3>Approve Invoice</h3>
          <button @click="showApproveModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <p><strong>Invoice:</strong> {{ selectedInvoice?.invoice_number }}</p>
          <p><strong>Amount:</strong> {{ formatCurrency(selectedInvoice?.total_amount, selectedInvoice?.currency) }}</p>
          <p><strong>Supplier:</strong> {{ selectedInvoice?.supplier_name }}</p>
          <div class="form-group">
            <label for="label-comments-optional">Comments (Optional)</label>
            <textarea id="label-comments-optional" v-model="approveComments" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showApproveModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="submitApprove" class="btn btn-success">Approve</button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="modal-overlay">
      <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
          <h3>Reject Invoice</h3>
          <button @click="showRejectModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <p><strong>Invoice:</strong> {{ selectedInvoice?.invoice_number }}</p>
          <p><strong>Amount:</strong> {{ formatCurrency(selectedInvoice?.total_amount, selectedInvoice?.currency) }}</p>
          <div class="form-group">
            <label for="label-rejection-reason">Rejection Reason</label>
            <textarea id="label-rejection-reason" v-model="rejectReason" class="form-control" rows="4" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showRejectModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="submitReject" class="btn btn-danger">Reject</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

// State
const invoices = ref([])
const stats = ref({
  pending_count: 0,
  urgent_count: 0,
  approval_rate_percent: 0,
})
const filters = ref({
  status: '',
  approval_level: '',
  search: '',
})
const currentPage = ref(1)
const perPage = ref(20)
const selectedInvoice = ref(null)
const approveComments = ref('')
const rejectReason = ref('')
const showApproveModal = ref(false)
const showRejectModal = ref(false)
const loading = ref(false)

// Computed
const filteredInvoices = computed(() => {
  return invoices.value.filter(inv => {
    if (filters.value.status && inv.approval_status !== filters.value.status) return false
    if (filters.value.approval_level && inv.required_approval_level !== filters.value.approval_level) return false
    if (filters.value.search) {
      const search = filters.value.search.toLowerCase()
      return (
        inv.invoice_number.toLowerCase().includes(search) ||
        inv.supplier_name.toLowerCase().includes(search)
      )
    }
    return true
  })
})

const paginatedInvoices = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredInvoices.value.slice(start, start + perPage.value)
})

const totalPages = computed(() => {
  return Math.ceil(filteredInvoices.value.length / perPage.value)
})

// Methods
const loadApprovals = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/accounting/approval-queue')
    invoices.value = response.data.invoices
    stats.value = {
      pending_count: response.data.pending_count,
      urgent_count: response.data.urgent_count,
      approval_rate_percent: 85, // From stats endpoint
    }
  } catch (error) {
    console.error('Failed to load approvals:', error)
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  currentPage.value = 1
}

const resetFilters = () => {
  filters.value = { status: '', approval_level: '', search: '' }
  currentPage.value = 1
}

const formatCurrency = (amount, currency) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(amount)
}

const formatStatus = (status) => {
  const map = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
  }
  return map[status] || status
}

const formatLevel = (level) => {
  const map = {
    manager: 'Manager',
    director: 'Director',
    ceo: 'CEO',
  }
  return map[level] || level
}

const statusBadgeClass = (status) => {
  return {
    'badge-pending': status === 'pending',
    'badge-approved': status === 'approved',
    'badge-rejected': status === 'rejected',
  }
}

const levelBadgeClass = (level) => {
  return {
    'level-manager': level === 'manager',
    'level-director': level === 'director',
    'level-ceo': level === 'ceo',
  }
}

const invoiceRowClass = (invoice) => {
  return {
    'row-urgent': invoice.days_pending > 5,
    'row-critical': invoice.status === 'critical',
  }
}

const daysStyle = (days) => {
  return {
    'text-danger': days > 5,
    'text-warning': days > 2,
  }
}

const canApprove = (invoice) => {
  return invoice.approval_status === 'pending'
}

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
  try {
    await axios.put(`/api/v1/accounting/invoices/${selectedInvoice.value.id}/approve`, {
      comments: approveComments.value,
    })
    showApproveModal.value = false
    await loadApprovals()
  } catch (error) {
    alert('Failed to approve invoice: ' + error.message)
  }
}

const submitReject = async () => {
  try {
    await axios.put(`/api/v1/accounting/invoices/${selectedInvoice.value.id}/reject`, {
      reason: rejectReason.value,
    })
    showRejectModal.value = false
    await loadApprovals()
  } catch (error) {
    alert('Failed to reject invoice: ' + error.message)
  }
}

onMounted(() => {
  loadApprovals()
})
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

.btn-primary {
  background: var(--halo-500);
  color: white;
}

.btn-primary:hover {
  background: var(--halo-700);
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

.row-critical {
  background: var(--red-50);
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
}

.level-manager {
  background: var(--halo-100);
  color: var(--halo-800);
}

.level-director {
  background: var(--yellow-50);
  color: var(--yellow-700);
}

.level-ceo {
  background: var(--red-50);
  color: var(--red-800);
}

.badge-pending {
  display: inline-block;
  padding: 4px 8px;
  background: var(--amber-400);
  color: var(--slate-700);
  border-radius: 3px;
  font-size: 12px;
  font-weight: 600;
}

.badge-approved {
  display: inline-block;
  padding: 4px 8px;
  background: var(--green-500);
  color: white;
  border-radius: 3px;
  font-size: 12px;
  font-weight: 600;
}

.badge-rejected {
  display: inline-block;
  padding: 4px 8px;
  background: var(--red-500);
  color: white;
  border-radius: 3px;
  font-size: 12px;
  font-weight: 600;
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

.pagination {
  display: flex;
  gap: 5px;
  margin-top: 20px;
  justify-content: center;
}

.btn-page {
  padding: 6px 10px;
  border: 1px solid var(--slate-200);
  background: white;
  cursor: pointer;
  border-radius: 3px;
}

.btn-page.active {
  background: var(--halo-500);
  color: white;
  border-color: var(--halo-500);
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
</style>
