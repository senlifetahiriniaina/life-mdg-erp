<template>
  <AppLayout>
    <Head title="WMS - Picking Orders" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Picking Orders</h1>
        <p class="wh-page-subtitle">{{ orders.total }} order{{ orders.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" style="font-size:13px" /> New Picking Order
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Status" show-clear style="width:160px" @change="reload" />
        <Select v-model="typeFilter" :options="typeOptions" option-label="label" option-value="value" placeholder="Type" show-clear style="width:140px" @change="reload" />
      </div>
    </div>

    <!-- Cards -->
    <div style="display:grid;gap:12px">
      <div v-for="o in orders.data" :key="o.id" class="wh-panel" style="padding:0">
        <div style="padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--border-1)">
          <!-- Priority indicator -->
          <div :class="['priority-dot', `priority-${o.priority}`]" :title="`Priority ${o.priority}`" />
          <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-weight:600;font-family:var(--font-mono);color:var(--halo-600)">{{ o.reference }}</span>
              <span :class="['wh-badge', typeBadgeClass(o.type)]">{{ o.type }}</span>
              <span :class="['wh-badge', statusBadgeClass(o.status)]">{{ o.status }}</span>
            </div>
            <div style="font-size:12px;color:var(--fg-3);margin-top:2px">
              {{ o.warehouse?.name }} &bull; {{ o.lines_count }} line{{ o.lines_count !== 1 ? 's' : '' }}
              <span v-if="o.assignee"> &bull; Assigned to {{ o.assignee.name }}</span>
            </div>
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <Select
              v-if="o.status !== 'completed' && o.status !== 'cancelled'"
              placeholder="Assign to..."
              :options="[]"
              style="width:160px;font-size:12px"
            />
            <button class="btn btn-sm btn-secondary" @click="viewOrder(o)">
              <i class="pi pi-list" style="font-size:12px" /> Details
            </button>
            <button
              v-if="o.status === 'in_progress'"
              class="btn btn-sm btn-success"
              @click="completeOrder(o)"
            >
              <i class="pi pi-check" style="font-size:12px" /> Complete
            </button>
          </div>
        </div>

        <!-- Progress bar -->
        <div style="padding:8px 16px;background:var(--surface-1)">
          <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--fg-3);margin-bottom:4px">
            <span>Progress</span>
            <span>{{ getProgress(o) }}%</span>
          </div>
          <div style="height:4px;background:var(--border-1);border-radius:2px;overflow:hidden">
            <div
              :style="{ width: getProgress(o) + '%', background: 'var(--halo-500)', height: '100%', borderRadius: '2px', transition: 'width .3s' }"
            />
          </div>
        </div>
      </div>

      <div v-if="!orders.data.length" class="wh-panel" style="text-align:center;padding:40px;color:var(--fg-3)">
        No picking orders found.
      </div>
    </div>

    <!-- Detail Dialog -->
    <Dialog v-model:visible="showDetail" header="Picking Order Detail" modal style="width:680px">
      <div v-if="selectedOrder">
        <div style="margin-bottom:16px">
          <span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ selectedOrder.reference }}</span>
          <span :class="['wh-badge', statusBadgeClass(selectedOrder.status)]" style="margin-left:8px">{{ selectedOrder.status }}</span>
        </div>
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Location</th>
              <th>Product</th>
              <th class="num">Requested</th>
              <th class="num">Picked</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in selectedOrder.lines" :key="line.id">
              <td>
                <span style="font-family:var(--font-mono);font-size:12px;background:var(--surface-1);padding:2px 6px;border-radius:4px">
                  {{ line.location?.code || '—' }}
                </span>
              </td>
              <td>
                <div style="font-weight:500">{{ line.product?.name }}</div>
                <div style="font-size:11px;color:var(--fg-3)">{{ line.product?.sku }}</div>
              </td>
              <td class="num">{{ line.quantity_requested }}</td>
              <td class="num">{{ line.quantity_picked }}</td>
              <td>
                <span :class="['wh-badge', lineStatusClass(line.status)]">{{ line.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Select, Dialog } from 'primevue'
import axios from 'axios'

interface PickingLine {
  id: number
  product: { id: number; name: string; sku: string } | null
  location: { id: number; name: string; code: string } | null
  quantity_requested: number
  quantity_picked: number
  status: string
}

interface PickingOrder {
  id: number
  reference: string
  type: string
  status: string
  priority: number
  lines_count: number
  lines?: PickingLine[]
  warehouse: { id: number; name: string } | null
  assignee: { id: number; name: string } | null
}

const props = defineProps<{
  orders: { data: PickingOrder[]; total: number; per_page: number; current_page: number; last_page: number }
  warehouses: { id: number; name: string }[]
  filters: { status?: string; type?: string }
}>()

const statusFilter = ref(props.filters.status || null)
const typeFilter = ref(props.filters.type || null)
const showCreate = ref(false)
const showDetail = ref(false)
const selectedOrder = ref<PickingOrder | null>(null)

const statusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
]

const typeOptions = [
  { label: 'Pick', value: 'pick' },
  { label: 'Pack', value: 'pack' },
  { label: 'Putaway', value: 'putaway' },
]

function statusBadgeClass(s: string): string {
  const map: Record<string, string> = { pending: 'badge-secondary', in_progress: 'badge-warning', completed: 'badge-success', cancelled: 'badge-danger' }
  return map[s] || 'badge-secondary'
}

function typeBadgeClass(t: string): string {
  const map: Record<string, string> = { pick: 'badge-info', pack: 'badge-primary', putaway: 'badge-warning' }
  return map[t] || 'badge-secondary'
}

function lineStatusClass(s: string): string {
  const map: Record<string, string> = { pending: 'badge-secondary', partial: 'badge-warning', done: 'badge-success' }
  return map[s] || 'badge-secondary'
}

function getProgress(o: PickingOrder): number {
  // Not calculated server-side on list; show 0 or 100 based on status
  if (o.status === 'completed') return 100
  if (o.status === 'pending') return 0
  return 50
}

function reload() {
  router.get(route('inventory.wms.picking'), {
    status: statusFilter.value || undefined,
    type: typeFilter.value || undefined,
  }, { preserveState: true, replace: true })
}

async function viewOrder(o: PickingOrder) {
  const { data } = await axios.get(`/api/v1/inventory/picking-orders/${o.id}`)
  selectedOrder.value = data
  showDetail.value = true
}

async function completeOrder(o: PickingOrder) {
  if (!confirm(`Mark picking order ${o.reference} as complete?`)) return
  await axios.post(`/api/v1/inventory/picking-orders/${o.id}/complete`)
  router.reload()
}
</script>

<style scoped>
.priority-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.priority-1 { background: #ef4444; }
.priority-2 { background: #f97316; }
.priority-3 { background: #eab308; }
.priority-4 { background: #22c55e; }
.priority-5 { background: #94a3b8; }
</style>
