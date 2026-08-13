<template>
  <AppLayout>
    <Head title="WIP Report" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Manufacturing · WIP Report</h1>
        <p class="wh-page-subtitle">Work-In-Progress — active orders cost overview</p>
      </div>
      <Button label="Refresh" icon="pi pi-refresh" severity="secondary" @click="load" />
    </div>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px" v-if="kpis">
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Total WIP Value</div>
        <div class="wh-kpi-value">{{ formatCurrency(kpis.total_wip_value) }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Active Orders</div>
        <div class="wh-kpi-value">{{ kpis.active_orders }}</div>
      </div>
      <div class="wh-kpi-card" :class="{ 'wh-kpi-danger': kpis.over_budget_count > 0 }">
        <div class="wh-kpi-label">Over Budget</div>
        <div class="wh-kpi-value">{{ kpis.over_budget_count }}</div>
      </div>
    </div>

    <div class="wh-panel">
      <DataTable :value="orders" :loading="loading" paginator :rows="20" dataKey="id">
        <Column field="reference" header="Order Ref" />
        <Column field="product" header="Product" />
        <Column field="status" header="Status" style="width:110px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column field="completion_pct" header="Progress" style="width:120px">
          <template #body="{ data }">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;height:6px;background:var(--bg-2);border-radius:3px;overflow:hidden">
                <div :style="{ width: data.completion_pct + '%', height: '100%', background: 'var(--primary)' }" />
              </div>
              <span style="font-size:12px;min-width:36px">{{ data.completion_pct }}%</span>
            </div>
          </template>
        </Column>
        <Column field="engaged_cost" header="Engaged Cost" style="width:130px">
          <template #body="{ data }">{{ formatCurrency(data.engaged_cost) }}</template>
        </Column>
        <Column field="estimated_total" header="Est. Total" style="width:120px">
          <template #body="{ data }">{{ formatCurrency(data.estimated_total) }}</template>
        </Column>
        <Column field="variance" header="Variance" style="width:110px">
          <template #body="{ data }">
            <span :style="{ color: data.variance > 0 ? 'var(--danger)' : 'var(--success)' }">
              {{ formatCurrency(data.variance) }}
            </span>
          </template>
        </Column>
        <Column field="deadline" header="Deadline" style="width:110px" />
      </DataTable>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Tag } from 'primevue'
import axios from 'axios'

interface Kpis {
  total_wip_value: number
  active_orders: number
  over_budget_count: number
}

interface WipRow {
  id: number
  reference: string
  product: string
  status: string
  completion_pct: number
  engaged_cost: number
  estimated_total: number
  variance: number
  deadline: string | null
}

const loading = ref(false)
const kpis    = ref<Kpis | null>(null)
const orders  = ref<WipRow[]>([])

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/manufacturing/wip')
    kpis.value   = data.kpis
    orders.value = data.orders
  } finally {
    loading.value = false
  }
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = { confirmed: 'info', in_progress: 'success' }
  return map[status] ?? 'secondary'
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value)
}

onMounted(load)
</script>

<style scoped>
.wh-kpi-card {
  background: var(--bg-1);
  border: 1px solid var(--border-subtle);
  border-radius: 12px;
  padding: 20px 24px;
}

.wh-kpi-card.wh-kpi-danger {
  border-color: var(--danger, #ef4444);
  background: rgba(239,68,68,.05);
}

.wh-kpi-label { font-size: 13px; color: var(--fg-3); margin-bottom: 6px; }
.wh-kpi-value { font-size: 28px; font-weight: 700; color: var(--fg-1); }
</style>
