<template>
  <AppLayout>
    <Head title="Cycle Counts" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Cycle Counts</h1>
        <p class="wh-page-subtitle">{{ counts.total }} count{{ counts.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" style="font-size:13px" /> New Cycle Count
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;gap:10px;align-items:center">
        <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Status" show-clear style="width:160px" @change="reload" />
      </div>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Warehouse</th>
            <th>Status</th>
            <th>Count Date</th>
            <th>Assigned To</th>
            <th class="num">Lines</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in counts.data" :key="c.id" class="wh-dt-row">
            <td><span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ c.reference }}</span></td>
            <td>{{ c.warehouse?.name }}</td>
            <td><span :class="['wh-badge', statusBadgeClass(c.status)]">{{ c.status }}</span></td>
            <td>{{ formatDate(c.count_date) }}</td>
            <td>{{ c.assignee?.name || '—' }}</td>
            <td class="num">{{ c.lines_count }}</td>
            <td>
              <div style="display:flex;gap:6px;justify-content:flex-end">
                <button class="btn btn-sm btn-secondary" @click="viewCount(c)">
                  <i class="pi pi-list" style="font-size:12px" /> Details
                </button>
                <button
                  v-if="c.status !== 'completed' && c.status !== 'cancelled'"
                  class="btn btn-sm btn-success"
                  @click="validateCount(c)"
                >
                  <i class="pi pi-check" style="font-size:12px" /> Validate
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!counts.data.length">
            <td colspan="7" style="text-align:center;padding:40px;color:var(--fg-3)">No cycle counts found.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="counts.last_page > 1" style="padding:12px 16px;display:flex;justify-content:center">
        <Paginator :rows="counts.per_page" :total-records="counts.total" :first="(counts.current_page - 1) * counts.per_page" @page="onPage" />
      </div>
    </div>

    <!-- Detail Dialog -->
    <Dialog v-model:visible="showDetail" header="Cycle Count Detail" modal style="width:760px">
      <div v-if="selectedCount">
        <div style="display:flex;gap:16px;margin-bottom:16px">
          <div>
            <span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ selectedCount.reference }}</span>
            <span :class="['wh-badge', statusBadgeClass(selectedCount.status)]" style="margin-left:8px">{{ selectedCount.status }}</span>
          </div>
        </div>
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Product</th>
              <th>Location</th>
              <th class="num">System Qty</th>
              <th class="num">Counted Qty</th>
              <th class="num">Variance</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in selectedCount.lines" :key="line.id">
              <td>
                <div style="font-weight:500">{{ line.product?.name }}</div>
                <div style="font-size:11px;color:var(--fg-3)">{{ line.product?.sku }}</div>
              </td>
              <td>{{ line.location?.name || '—' }}</td>
              <td class="num">{{ line.system_qty }}</td>
              <td class="num">
                <InputNumber
                  v-if="line.status === 'pending'"
                  v-model="line.counted_qty"
                  :min="0"
                  :min-fraction-digits="0"
                  :max-fraction-digits="4"
                  style="width:90px"
                  @blur="recordLine(line)"
                />
                <span v-else>{{ line.counted_qty ?? '—' }}</span>
              </td>
              <td class="num">
                <span v-if="line.variance !== null" :class="{ 'text-danger': Math.abs(line.variance) / Math.max(line.system_qty, 0.001) > 0.05 }">
                  {{ line.variance > 0 ? '+' : '' }}{{ line.variance }}
                </span>
                <span v-else>—</span>
              </td>
              <td><span :class="['wh-badge', lineStatusClass(line.status)]">{{ line.status }}</span></td>
            </tr>
          </tbody>
        </table>
        <div style="margin-top:16px;display:flex;justify-content:flex-end">
          <button
            v-if="selectedCount.status !== 'completed'"
            class="btn btn-primary"
            :disabled="validating"
            @click="validateSelectedCount"
          >
            {{ validating ? 'Validating...' : 'Validate & Apply Adjustments' }}
          </button>
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Select, Dialog, InputNumber, Paginator } from 'primevue'
import axios from 'axios'

interface CycleCountLine {
  id: number
  product: { id: number; name: string; sku: string } | null
  location: { id: number; name: string } | null
  system_qty: number
  counted_qty: number | null
  variance: number | null
  status: string
  cycle_count_id: number
}

interface CycleCount {
  id: number
  reference: string
  status: string
  count_date: string
  lines_count: number
  lines?: CycleCountLine[]
  warehouse: { id: number; name: string } | null
  assignee: { id: number; name: string } | null
}

const props = defineProps<{
  counts: { data: CycleCount[]; total: number; per_page: number; current_page: number; last_page: number }
  warehouses: { id: number; name: string }[]
  filters: { status?: string }
}>()

const statusFilter = ref(props.filters.status || null)
const showCreate = ref(false)
const showDetail = ref(false)
const selectedCount = ref<CycleCount | null>(null)
const validating = ref(false)

const statusOptions = [
  { label: 'Planned', value: 'planned' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
]

function statusBadgeClass(s: string): string {
  const map: Record<string, string> = { planned: 'badge-secondary', in_progress: 'badge-warning', completed: 'badge-success', cancelled: 'badge-danger' }
  return map[s] || 'badge-secondary'
}

function lineStatusClass(s: string): string {
  const map: Record<string, string> = { pending: 'badge-secondary', counted: 'badge-info', validated: 'badge-success' }
  return map[s] || 'badge-secondary'
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString()
}

function reload() {
  router.get(route('inventory.cycle_counts.index'), {
    status: statusFilter.value || undefined,
  }, { preserveState: true, replace: true })
}

async function viewCount(c: CycleCount) {
  const { data } = await axios.get(`/api/v1/inventory/cycle-counts/${c.id}`)
  selectedCount.value = data
  showDetail.value = true
}

async function recordLine(line: CycleCountLine) {
  if (line.counted_qty === null || line.counted_qty === undefined) return
  await axios.post(`/api/v1/inventory/cycle-counts/${line.cycle_count_id}/lines/${line.id}/count`, {
    counted_qty: line.counted_qty,
  })
  // Refresh selected count
  if (selectedCount.value) {
    const { data } = await axios.get(`/api/v1/inventory/cycle-counts/${selectedCount.value.id}`)
    selectedCount.value = data
  }
}

async function validateCount(c: CycleCount) {
  if (!confirm(`Validate cycle count ${c.reference} and apply stock adjustments?`)) return
  await axios.post(`/api/v1/inventory/cycle-counts/${c.id}/validate`)
  router.reload()
}

async function validateSelectedCount() {
  if (!selectedCount.value) return
  if (!confirm('Validate and apply all stock adjustments?')) return
  validating.value = true
  try {
    await axios.post(`/api/v1/inventory/cycle-counts/${selectedCount.value.id}/validate`)
    showDetail.value = false
    router.reload()
  } finally {
    validating.value = false
  }
}

function onPage(event: { page: number }) {
  router.get(route('inventory.cycle_counts.index'), {
    ...props.filters,
    page: event.page + 1,
  }, { preserveState: true })
}
</script>

<style scoped>
.text-danger { color: var(--color-danger, #ef4444); font-weight: 600; }
</style>
