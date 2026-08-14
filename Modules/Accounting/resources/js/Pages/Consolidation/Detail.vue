<template>
  <div class="consolidation-detail">
    <div v-if="loading" class="spinner">Loading...</div>

    <div v-else class="detail-container">
      <div class="header">
        <h1>{{ group.name }}</h1>
        <span :class="`badge badge-${getStatusClass(group.status)}`">
          {{ group.status }}
        </span>
      </div>

      <div class="tabs">
        <button v-for="tab in tabs" :key="tab"
                :class="['tab', { active: activeTab === tab }]"
                @click="activeTab = tab">
          {{ tab }}
        </button>
      </div>

      <!-- Basic Info Tab -->
      <div v-if="activeTab === 'Information'" class="tab-content">
        <div class="info-grid">
          <div class="info-item">
            <label>Parent Company</label>
            <p>{{ group.parent_company.name }}</p>
          </div>
          <div class="info-item">
            <label>Consolidation Method</label>
            <p>{{ group.consolidation_method }}</p>
          </div>
          <div class="info-item">
            <label>Fiscal Year</label>
            <p>{{ group.fiscal_year }}</p>
          </div>
          <div class="info-item">
            <label>Consolidation Date</label>
            <p>{{ formatDate(group.consolidation_date) }}</p>
          </div>
          <div class="info-item">
            <label>Total Ownership %</label>
            <p>{{ getTotalOwnership() }}%</p>
          </div>
          <div class="info-item">
            <label for="created-by">Created By</label>
            <p>{{ group.creator.name }}</p>
          </div>
        </div>
        <div v-if="group.description" class="description">
          <h3>Description</h3>
          <p>{{ group.description }}</p>
        </div>
      </div>

      <!-- Members Tab -->
      <div v-if="activeTab === 'Members'" class="tab-content">
        <button @click="showAddMemberForm = !showAddMemberForm" class="btn btn-primary">
          Add Member
        </button>

        <form v-if="showAddMemberForm" @submit.prevent="addMember" class="member-form">
          <select id="created-by" v-model="newMember.subsidiary_company_id" class="form-control" required>
            <option value="">-- Select Company --</option>
            <option v-for="company in availableCompanies" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
          <input v-model.number="newMember.ownership_percentage" type="number" 
                 min="0" max="100" placeholder="Ownership %" class="form-control" required />
          <input v-model="newMember.acquisition_date" type="date" class="form-control" required />
          <input v-model.number="newMember.acquisition_price" type="number" 
                 placeholder="Acquisition Price" class="form-control" required />
          <button type="submit" class="btn btn-success">Add</button>
          <button type="button" @click="showAddMemberForm = false" class="btn btn-secondary">Cancel</button>
        </form>

        <table class="table">
          <thead>
            <tr>
              <th scope="col">Company</th>
              <th scope="col">Ownership</th>
              <th scope="col">Type</th>
              <th scope="col">Goodwill</th>
              <th scope="col">Status</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in group.members" :key="member.id">
              <td>{{ member.subsidiary.name }}</td>
              <td>{{ member.ownership_percentage }}%</td>
              <td>{{ member.relationship_type }}</td>
              <td>${{ member.fair_value_adjustment }}</td>
              <td>{{ member.consolidation_status }}</td>
              <td>
                <button @click="removeMember(member.id)" class="btn btn-sm btn-danger">
                  Remove
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Transactions Tab -->
      <div v-if="activeTab === 'Transactions'" class="tab-content">
        <button @click="showTransactionForm = !showTransactionForm" class="btn btn-primary">
          Record Transaction
        </button>

        <form v-if="showTransactionForm" @submit.prevent="recordTransaction" class="transaction-form">
          <select v-model="newTransaction.from_company_id" class="form-control" required>
            <option value="">-- From Company --</option>
            <option v-for="company in transactionCompanies" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
          <select v-model="newTransaction.to_company_id" class="form-control" required>
            <option value="">-- To Company --</option>
            <option v-for="company in transactionCompanies" :key="company.id" :value="company.id">
              {{ company.name }}
            </option>
          </select>
          <select v-model="newTransaction.transaction_type" class="form-control" required>
            <option value="sales">Sales</option>
            <option value="purchases">Purchases</option>
            <option value="services">Services</option>
            <option value="loans">Loans</option>
            <option value="dividends">Dividends</option>
          </select>
          <input v-model="newTransaction.reference_number" type="text" placeholder="Reference #" class="form-control" required />
          <input v-model.number="newTransaction.amount" type="number" placeholder="Amount" class="form-control" required />
          <input v-model="newTransaction.transaction_date" type="date" class="form-control" required />
          <button type="submit" class="btn btn-success">Record</button>
          <button type="button" @click="showTransactionForm = false" class="btn btn-secondary">Cancel</button>
        </form>

        <div class="actions">
          <button @click="eliminateTransactions" :disabled="eliminating" class="btn btn-warning">
            {{ eliminating ? 'Eliminating...' : 'Eliminate IC Transactions' }}
          </button>
        </div>

        <table class="table">
          <thead>
            <tr>
              <th scope="col">From</th>
              <th scope="col">To</th>
              <th scope="col">Type</th>
              <th scope="col">Amount</th>
              <th scope="col">Date</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tx in group.intercompany_transactions" :key="tx.id">
              <td>{{ getCompanyName(tx.from_company_id) }}</td>
              <td>{{ getCompanyName(tx.to_company_id) }}</td>
              <td>{{ tx.transaction_type }}</td>
              <td>${{ tx.amount }}</td>
              <td>{{ formatDate(tx.transaction_date) }}</td>
              <td>
                <span :class="`badge ${tx.is_eliminated ? 'badge-success' : 'badge-warning'}`">
                  {{ tx.is_eliminated ? 'Eliminated' : 'Pending' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Reports Tab -->
      <div v-if="activeTab === 'Reports'" class="tab-content">
        <button @click="generateReport" :disabled="generatingReport" class="btn btn-primary">
          {{ generatingReport ? 'Generating...' : 'Generate Consolidated Report' }}
        </button>

        <div v-if="group.reports.length > 0" class="reports-list">
          <h3>Generated Reports</h3>
          <div v-for="report in group.reports" :key="report.id" class="report-card">
            <h4>{{ report.report_type }}</h4>
            <p><strong>Status:</strong> {{ report.status }}</p>
            <p><strong>Generated:</strong> {{ formatDate(report.created_at) }}</p>
            <button @click="viewReport(report.id)" class="btn btn-sm btn-info">View</button>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div v-if="group.status === 'draft'" class="actions">
        <button @click="approveGroup" :disabled="approving" class="btn btn-success">
          {{ approving ? 'Approving...' : 'Approve Group' }}
        </button>
        <router-link to="/consolidations" class="btn btn-secondary">Cancel</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useRoute } from 'vue-router'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['finance-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const route = useRoute()
const group = ref(null)
const loading = ref(true)
const activeTab = ref('Information')
const tabs = ['Information', 'Members', 'Transactions', 'Reports']

const showAddMemberForm = ref(false)
const showTransactionForm = ref(false)
const eliminating = ref(false)
const approving = ref(false)
const generatingReport = ref(false)

const newMember = ref({
  subsidiary_company_id: '',
  ownership_percentage: 100,
  relationship_type: 'subsidiary',
  acquisition_date: '',
  acquisition_price: 0
})

const newTransaction = ref({
  from_company_id: '',
  to_company_id: '',
  transaction_type: 'sales',
  reference_number: '',
  amount: 0,
  transaction_date: new Date().toISOString().split('T')[0]
})

const availableCompanies = computed(() => {
  if (!group.value) return []
  const memberIds = group.value.members.map(m => m.subsidiary_company_id)
  return group.value.members.map(m => m.subsidiary).filter(c => !memberIds.includes(c.id))
})

const transactionCompanies = computed(() => {
  if (!group.value) return []
  return [group.value.parent_company, ...group.value.members.map(m => m.subsidiary)]
})

onMounted(async () => {
  const response = await fetch(`/api/consolidations/${route.params.id}`)
  group.value = await response.json()
  loading.value = false
})

const getTotalOwnership = () => {
  if (!group.value) return 0
  return group.value.members.reduce((sum, m) => sum + m.ownership_percentage, 0)
}

const getStatusClass = (status) => {
  const classes = { draft: 'warning', in_progress: 'info', completed: 'success', approved: 'success' }
  return classes[status] || 'secondary'
}

const getCompanyName = (companyId) => {
  if (!group.value) return 'Unknown'
  if (group.value.parent_company.id === companyId) return group.value.parent_company.name
  const member = group.value.members.find(m => m.subsidiary_company_id === companyId)
  return member ? member.subsidiary.name : 'Unknown'
}

const formatDate = (date) => new Date(date).toLocaleDateString()

const addMember = async () => {
  try {
    await fetch(`/api/consolidations/${group.value.id}/members`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newMember.value)
    })
    location.reload()
  } catch (error) {
    console.error('Error:', error)
  }
}

const removeMember = async (memberId) => {
  if (!confirm('Remove this member?')) return
  try {
    await fetch(`/api/consolidations/${group.value.id}/members/${memberId}`, { method: 'DELETE' })
    location.reload()
  } catch (error) {
    console.error('Error:', error)
  }
}

const recordTransaction = async () => {
  try {
    await fetch(`/api/consolidations/${group.value.id}/transactions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newTransaction.value)
    })
    location.reload()
  } catch (error) {
    console.error('Error:', error)
  }
}

const eliminateTransactions = async () => {
  eliminating.value = true
  try {
    const response = await fetch(`/api/consolidations/${group.value.id}/eliminate`, { method: 'POST' })
    const data = await response.json()
    alert(`${data.eliminated_count} transactions eliminated`)
    location.reload()
  } catch (error) {
    console.error('Error:', error)
  } finally {
    eliminating.value = false
  }
}

const generateReport = async () => {
  generatingReport.value = true
  try {
    await fetch(`/api/consolidations/${group.value.id}/reports`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ report_type: 'consolidated_balance_sheet' })
    })
    location.reload()
  } catch (error) {
    console.error('Error:', error)
  } finally {
    generatingReport.value = false
  }
}

const approveGroup = async () => {
  approving.value = true
  try {
    await fetch(`/api/consolidations/${group.value.id}/approve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: 'approved' })
    })
    location.reload()
  } catch (error) {
    console.error('Error:', error)
  } finally {
    approving.value = false
  }
}

const viewReport = (reportId) => {
  // Implementation for viewing detailed report
  alert(`Report ${reportId} - implementation pending`)
}
</script>

<style scoped>
.consolidation-detail {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.spinner {
  text-align: center;
  padding: 40px;
  font-size: 18px;
  color: var(--slate-500);
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--slate-100);
}

.header h1 {
  margin: 0;
}

.badge {
  padding: 6px 12px;
  border-radius: 4px;
  font-weight: bold;
  color: white;
}

.badge-warning {
  background-color: var(--amber-400);
  color: black;
}

.badge-success {
  background-color: var(--green-500);
}

.tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 30px;
  border-bottom: 2px solid var(--slate-100);
}

.tab {
  padding: 12px 20px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 16px;
  color: var(--slate-500);
  border-bottom: 3px solid transparent;
  transition: all 0.3s;
}

.tab.active {
  color: var(--halo-500);
  border-bottom-color: var(--halo-500);
}

.tab:hover {
  color: var(--halo-500);
}

.tab-content {
  margin-bottom: 30px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.info-item {
  padding: 15px;
  background-color: var(--slate-50);
  border-radius: 4px;
}

.info-item label {
  display: block;
  font-weight: bold;
  color: var(--slate-500);
  margin-bottom: 5px;
  font-size: 12px;
  text-transform: uppercase;
}

.info-item p {
  margin: 0;
  font-size: 16px;
  color: var(--slate-700);
}

.description {
  background-color: var(--slate-50);
  padding: 20px;
  border-left: 4px solid var(--halo-500);
  border-radius: 4px;
}

.member-form,
.transaction-form {
  background-color: var(--slate-50);
  padding: 20px;
  border-radius: 4px;
  margin-bottom: 20px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}

.form-control {
  padding: 8px 12px;
  border: 1px solid var(--slate-300);
  border-radius: 4px;
  font-size: 14px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

.table th,
.table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid var(--slate-200);
}

.table th {
  background-color: var(--slate-50);
  font-weight: bold;
}

.table tr:hover {
  background-color: var(--slate-50);
}

.report-card {
  padding: 15px;
  background-color: var(--slate-50);
  border-radius: 4px;
  margin-top: 15px;
  border-left: 4px solid var(--halo-500);
}

.report-card h4 {
  margin: 0 0 10px 0;
}

.report-card p {
  margin: 5px 0;
  font-size: 14px;
}

.actions {
  display: flex;
  gap: 10px;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--slate-200);
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background-color: var(--halo-500);
  color: white;
}

.btn-success {
  background-color: var(--green-500);
  color: white;
}

.btn-warning {
  background-color: var(--amber-400);
  color: black;
}

.btn-secondary {
  background-color: var(--slate-500);
  color: white;
}

.btn-info {
  background-color: var(--halo-600);
  color: white;
}

.btn-danger {
  background-color: var(--red-500);
  color: white;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn:hover:not(:disabled) {
  opacity: 0.9;
}

.btn-sm {
  padding: 5px 10px;
  font-size: 12px;
}
</style>
