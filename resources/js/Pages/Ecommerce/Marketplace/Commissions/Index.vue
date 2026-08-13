<template>
  <AppLayout>
    <Head title="Commission Management" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Marketplace · Commissions</h1>
        <p class="wh-page-subtitle">Track and pay vendor commissions</p>
      </div>
      <div style="display:flex;gap:8px">
        <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        <Button
          label="Mark as Paid"
          icon="pi pi-check"
          severity="success"
          :disabled="selectedIds.length === 0"
          @click="markPaid"
        />
      </div>
    </div>

    <!-- KPI -->
    <div class="wh-panel" style="margin-bottom:16px;background:rgba(239,68,68,.07);border-color:#ef4444">
      <div style="display:flex;align-items:center;gap:16px">
        <div>
          <div style="font-size:13px;color:var(--fg-3)">Total commissions to pay this month</div>
          <div style="font-size:28px;font-weight:700;color:#ef4444">
            {{ formatCurrency(totalPending) }}
          </div>
        </div>
        <div style="margin-left:auto;font-size:13px;color:var(--fg-3)">
          {{ pendingCount }} pending entries
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:0;border-bottom:1px solid var(--border-subtle);margin-bottom:16px">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ active: currentTab === tab.key }"
        @click="currentTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- By Vendor tab -->
    <div v-if="currentTab === 'vendor'" class="wh-panel">
      <div style="display:flex;gap:12px;margin-bottom:16px;align-items:flex-end">
        <div>
          <label class="wh-label">Vendor ID</label>
          <InputText v-model="filterVendor" placeholder="Vendor ID" style="width:160px" />
        </div>
        <div>
          <label class="wh-label">Period (YYYY-MM)</label>
          <InputText v-model="filterPeriod" placeholder="2026-05" style="width:140px" />
        </div>
        <Button label="Filter" @click="loadCommissions" />
      </div>

      <DataTable
        v-model:selection="selected"
        :value="commissions"
        :loading="loading"
        dataKey="id"
        selectionMode="multiple"
        paginator
        :rows="30"
      >
        <Column selectionMode="multiple" style="width:50px" />
        <Column field="vendor_id" header="Vendor ID" style="width:90px" />
        <Column field="order_id" header="Order ID" style="width:90px" />
        <Column field="period" header="Period" style="width:90px" />
        <Column field="order_amount" header="Order Amount" style="width:130px">
          <template #body="{ data }">{{ formatCurrency(data.order_amount) }}</template>
        </Column>
        <Column field="commission_pct" header="Comm. %" style="width:100px">
          <template #body="{ data }">{{ data.commission_pct }}%</template>
        </Column>
        <Column field="commission_amount" header="Commission" style="width:120px">
          <template #body="{ data }">{{ formatCurrency(data.commission_amount) }}</template>
        </Column>
        <Column field="status" header="Status" style="width:100px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="data.status === 'paid' ? 'success' : 'warning'" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- By Period tab -->
    <div v-else class="wh-panel">
      <div style="display:flex;gap:12px;margin-bottom:16px;align-items:flex-end">
        <div>
          <label class="wh-label">Vendor ID *</label>
          <InputText v-model="stmtVendor" style="width:160px" />
        </div>
        <div>
          <label class="wh-label">Period *</label>
          <InputText v-model="stmtPeriod" placeholder="2026-05" style="width:140px" />
        </div>
        <Button label="Generate Statement" @click="loadStatement" />
      </div>

      <div v-if="statement" style="background:var(--bg-2);border-radius:8px;padding:16px;margin-bottom:16px">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center">
          <div>
            <div style="font-size:12px;color:var(--fg-3)">Total Orders</div>
            <div style="font-size:20px;font-weight:700">{{ formatCurrency(statement.total_orders) }}</div>
          </div>
          <div>
            <div style="font-size:12px;color:var(--fg-3)">Total Commission</div>
            <div style="font-size:20px;font-weight:700;color:#ef4444">{{ formatCurrency(statement.total_commission) }}</div>
          </div>
          <div>
            <div style="font-size:12px;color:var(--fg-3)">Payout Due</div>
            <div style="font-size:20px;font-weight:700;color:var(--success)">{{ formatCurrency(statement.payout_due) }}</div>
          </div>
        </div>
      </div>

      <DataTable v-if="statement" :value="statement.commissions" dataKey="id" paginator :rows="20">
        <Column field="period" header="Period" style="width:90px" />
        <Column field="order_amount" header="Order Amount">
          <template #body="{ data }">{{ formatCurrency(data.order_amount) }}</template>
        </Column>
        <Column field="commission_amount" header="Commission">
          <template #body="{ data }">{{ formatCurrency(data.commission_amount) }}</template>
        </Column>
        <Column field="status" header="Status" style="width:100px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="data.status === 'paid' ? 'success' : 'warning'" />
          </template>
        </Column>
      </DataTable>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Tag, InputText } from 'primevue'
import axios from 'axios'

interface Commission {
  id: number
  vendor_id: number
  order_id: number | null
  period: string
  order_amount: string
  commission_pct: string
  commission_amount: string
  status: string
}

interface Statement {
  total_orders: number
  total_commission: number
  payout_due: number
  commissions: Commission[]
}

const currentTab     = ref<'vendor' | 'period'>('vendor')
const commissions    = ref<Commission[]>([])
const loading        = ref(false)
const selected       = ref<Commission[]>([])
const filterVendor   = ref('')
const filterPeriod   = ref(new Date().toISOString().slice(0, 7))
const stmtVendor     = ref('')
const stmtPeriod     = ref(new Date().toISOString().slice(0, 7))
const statement      = ref<Statement | null>(null)

const tabs = [
  { key: 'vendor', label: 'By Vendor' },
  { key: 'period', label: 'By Period' },
]

const selectedIds = computed(() => selected.value.map(c => c.id))

const totalPending = computed(() =>
  commissions.value
    .filter(c => c.status === 'pending')
    .reduce((s, c) => s + parseFloat(c.commission_amount), 0)
)

const pendingCount = computed(() =>
  commissions.value.filter(c => c.status === 'pending').length
)

async function loadCommissions(): Promise<void> {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (filterVendor.value) params.vendor_id = filterVendor.value
    if (filterPeriod.value) params.period    = filterPeriod.value

    const { data } = await axios.get('/api/v1/ecommerce/commissions', { params })
    commissions.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function loadStatement(): Promise<void> {
  if (!stmtVendor.value || !stmtPeriod.value) return
  const { data } = await axios.get('/api/v1/ecommerce/commissions/statement', {
    params: { vendor_id: stmtVendor.value, period: stmtPeriod.value },
  })
  statement.value = data
}

async function markPaid(): Promise<void> {
  await axios.post('/api/v1/ecommerce/commissions/mark-paid', { ids: selectedIds.value })
  selected.value = []
  await loadCommissions()
}

function exportCsv(): void {
  const headers = ['ID', 'Vendor', 'Period', 'Order Amount', 'Commission %', 'Commission', 'Status']
  const rows    = commissions.value.map(c => [
    c.id, c.vendor_id, c.period, c.order_amount, c.commission_pct, c.commission_amount, c.status,
  ])

  const csv = [headers, ...rows].map(r => r.join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `commissions-${filterPeriod.value}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function formatCurrency(value: string | number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value))
}

onMounted(loadCommissions)
</script>

<style scoped>
.tab-btn {
  padding: 10px 20px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-size: 14px;
  color: var(--fg-3);
  transition: all .15s;
}

.tab-btn.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
  font-weight: 600;
}
</style>
