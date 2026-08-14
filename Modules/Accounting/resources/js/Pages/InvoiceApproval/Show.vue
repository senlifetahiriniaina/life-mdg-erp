<template>
  <div class="invoice-approval-show">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1>Invoice {{ invoice.invoice_number }}</h1>
        <p class="breadcrumb">
          <router-link to="/accounting/invoices">Invoices</router-link> /
          <router-link to="/accounting/invoice-approvals">Approvals</router-link> /
          {{ invoice.invoice_number }}
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
          <h3>Invoice Details</h3>
          <div class="detail-row">
            <span class="label">Invoice Number:</span>
            <span class="value">{{ invoice.invoice_number }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Supplier:</span>
            <span class="value">{{ invoice.supplier_name }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Amount:</span>
            <span class="value amount">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Invoice Date:</span>
            <span class="value">{{ formatDate(invoice.invoice_date) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Due Date:</span>
            <span class="value">{{ formatDate(invoice.due_date) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Approval Status:</span>
            <span :class="statusBadgeClass(invoice.approval_status)">
              {{ formatStatus(invoice.approval_status) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Right: Approval Chain -->
      <div class="approval-chain">
        <div class="card">
          <h3>Approval Chain</h3>
          <div class="chain-timeline">
            <div v-for="(approval, index) in approvalChain" :key="index" class="chain-item">
              <div :class="chainItemClass(approval)">
                <div class="chain-icon">
                  <span v-if="approval.status === 'approved'" class="icon-approved">✓</span>
                  <span v-else-if="approval.status === 'rejected'" class="icon-rejected">✕</span>
                  <span v-else class="icon-pending">⟳</span>
                </div>
                <div class="chain-content">
                  <div class="level-name">{{ formatLevel(approval.level) }}</div>
                  <div class="required-role">{{ approval.required_role }}</div>
                  <div v-if="approval.approver_name" class="approver">
                    by {{ approval.approver_name }}
                  </div>
                  <div v-if="approval.approved_at" class="timestamp">
                    {{ formatDate(approval.approved_at) }}
                  </div>
                  <div v-if="approval.rejected_at" class="timestamp rejection">
                    Rejected: {{ formatDate(approval.rejected_at) }}
                  </div>
                  <div v-if="approval.reason" class="reason">
                    <strong>Reason:</strong> {{ approval.reason }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="card actions">
          <div v-if="canApprove" class="approval-form">
            <h4>Your Action</h4>
            <textarea v-model="actionComments" class="form-control" placeholder="Comments (optional)" rows="3"></textarea>
            <div class="button-group">
              <button @click="approveInvoice" class="btn btn-success">Approve</button>
              <button @click="openRejectModal" class="btn btn-danger">Reject</button>
            </div>
          </div>
          <div v-else-if="invoice.approval_status === 'approved'" class="success-message">
            ✓ This invoice has been fully approved
          </div>
          <div v-else-if="invoice.approval_status === 'rejected'" class="error-message">
            ✕ This invoice has been rejected
          </div>
        </div>
      </div>
    </div>

    <!-- Rejection Modal -->
    <div v-if="showRejectModal" class="modal-overlay">
      <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
          <h3>Reject Invoice</h3>
          <button @click="showRejectModal = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <p>Please provide a reason for rejecting this invoice:</p>
          <textarea v-model="rejectReason" class="form-control" rows="5" placeholder="Rejection reason..."></textarea>
        </div>
        <div class="modal-footer">
          <button @click="showRejectModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="submitReject" class="btn btn-danger">Reject</button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-spinner">
      <div class="spinner"></div>
      <p>Loading...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouteId } from '@/composables/useRouteId'
import axios from 'axios'

const routeId = useRouteId()

const invoice = ref({
  invoice_number: '',
  supplier_name: '',
  total_amount: 0,
  currency: 'XOF',
  approval_status: 'pending',
})

const approvalChain = ref([])
const actionComments = ref('')
const rejectReason = ref('')
const showRejectModal = ref(false)
const loading = ref(true)
const userRole = ref('')

const canApprove = computed(() => {
  return invoice.value.approval_status === 'pending' && userRole.value !== 'viewer'
})

const loadInvoiceApprovalDetails = async () => {
  try {
    loading.value = true
    const response = await axios.get(`/api/v1/accounting/invoices/${routeId.value}/approvals`)
    invoice.value = response.data
    approvalChain.value = response.data.approvals || []
  } catch (error) {
    console.error('Failed to load invoice:', error)
  } finally {
    loading.value = false
  }
}

const formatStatus = (status) => {
  const map = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
    in_progress: 'In Progress',
  }
  return map[status] || status
}

const formatLevel = (level) => {
  const map = {
    manager: 'Manager Level',
    director: 'Director Level',
    ceo: 'CEO Level',
  }
  return map[level] || level
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

const formatCurrency = (amount, currency) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(amount)
}

const statusBadgeClass = (status) => {
  return {
    'badge': true,
    'badge-pending': status === 'pending',
    'badge-approved': status === 'approved',
    'badge-rejected': status === 'rejected',
    'badge-in-progress': status === 'in_progress',
  }
}

const chainItemClass = (approval) => {
  return {
    'chain-item-content': true,
    'status-approved': approval.status === 'approved',
    'status-rejected': approval.status === 'rejected',
    'status-pending': approval.status === 'pending',
  }
}

const approveInvoice = async () => {
  try {
    await axios.put(`/api/v1/accounting/invoices/${routeId.value}/approve`, {
      comments: actionComments.value,
    })
    await loadInvoiceApprovalDetails()
    actionComments.value = ''
  } catch (error) {
    alert('Failed to approve: ' + error.message)
  }
}

const openRejectModal = () => {
  rejectReason.value = ''
  showRejectModal.value = true
}

const submitReject = async () => {
  try {
    await axios.put(`/api/v1/accounting/invoices/${routeId.value}/reject`, {
      reason: rejectReason.value,
    })
    showRejectModal.value = false
    await loadInvoiceApprovalDetails()
  } catch (error) {
    alert('Failed to reject: ' + error.message)
  }
}

onMounted(() => {
  loadInvoiceApprovalDetails()
})
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

.breadcrumb a {
  color: var(--halo-500);
  text-decoration: none;
}

.breadcrumb a:hover {
  text-decoration: underline;
}

.header-status {
  text-align: right;
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

.card h4 {
  margin: 0 0 15px 0;
  font-size: 14px;
  color: var(--slate-700);
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

.badge-in-progress {
  background: var(--halo-600);
  color: white;
}

.chain-timeline {
  position: relative;
}

.chain-item {
  display: flex;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--slate-100);
}

.chain-item:last-child {
  border-bottom: none;
}

.chain-item-content {
  display: flex;
  width: 100%;
  gap: 15px;
}

.chain-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  font-weight: bold;
  font-size: 18px;
}

.chain-item-content.status-approved .chain-icon {
  background: var(--green-50);
  color: var(--green-500);
}

.chain-item-content.status-rejected .chain-icon {
  background: var(--red-50);
  color: var(--red-500);
}

.chain-item-content.status-pending .chain-icon {
  background: var(--yellow-50);
  color: var(--amber-400);
}

.icon-approved::before {
  content: '✓';
}

.icon-rejected::before {
  content: '✕';
}

.icon-pending::before {
  content: '⟳';
}

.chain-content {
  flex: 1;
}

.level-name {
  font-weight: 600;
  color: var(--slate-700);
  margin-bottom: 3px;
}

.required-role {
  font-size: 12px;
  color: var(--slate-400);
}

.approver {
  font-size: 13px;
  color: var(--slate-500);
  margin-top: 5px;
}

.timestamp {
  font-size: 12px;
  color: var(--slate-400);
  margin-top: 5px;
}

.timestamp.rejection {
  color: var(--red-500);
}

.reason {
  font-size: 13px;
  color: var(--slate-500);
  margin-top: 10px;
  padding: 8px;
  background: var(--slate-50);
  border-radius: 4px;
}

.actions {
  grid-column: 1 / -1;
}

.approval-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.form-control {
  padding: 10px;
  border: 1px solid var(--slate-200);
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
}

.button-group {
  display: flex;
  gap: 10px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
  flex: 1;
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

.btn-secondary {
  background: var(--slate-500);
  color: white;
}

.btn-secondary:hover {
  background: var(--slate-600);
}

.success-message {
  padding: 15px;
  background: var(--green-50);
  color: var(--green-700);
  border-radius: 4px;
  text-align: center;
  font-weight: 600;
}

.error-message {
  padding: 15px;
  background: var(--red-50);
  color: var(--red-800);
  border-radius: 4px;
  text-align: center;
  font-weight: 600;
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

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 15px 20px;
  border-top: 1px solid var(--slate-100);
}

.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}

.spinner {
  border: 4px solid var(--slate-100);
  border-top: 4px solid var(--halo-500);
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
