<template>
  <AppLayout>
    <Head title="Scheduling Board" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Manufacturing · Scheduling Board</h1>
        <p class="wh-page-subtitle">Visual production scheduling — week of {{ weekStart }}</p>
      </div>
      <Button label="Refresh" icon="pi pi-refresh" severity="secondary" @click="loadBoard" />
    </div>

    <!-- Legend -->
    <div class="wh-panel" style="margin-bottom:16px;padding:12px 16px;display:flex;gap:24px;align-items:center">
      <span style="font-size:13px;font-weight:600;color:var(--fg-2)">Legend:</span>
      <span class="legend-badge" style="background:#ef4444">Overdue</span>
      <span class="legend-badge" style="background:#22c55e">In Progress</span>
      <span class="legend-badge" style="background:#3b82f6">Planned</span>
      <span class="legend-badge" style="background:#f59e0b">Draft</span>
    </div>

    <div v-if="loading" style="text-align:center;padding:48px;color:var(--fg-3)">
      Loading board…
    </div>

    <div v-else-if="board.length === 0" style="text-align:center;padding:48px;color:var(--fg-3)">
      No active work centers found.
    </div>

    <div v-else class="wh-panel" style="overflow-x:auto">
      <table class="scheduling-table">
        <thead>
          <tr>
            <th style="min-width:160px;text-align:left">Work Center</th>
            <th v-for="day in weekDays" :key="day.date" style="min-width:140px;text-align:center">
              <div>{{ day.label }}</div>
              <div style="font-size:11px;color:var(--fg-3);font-weight:400">{{ day.date }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="wc in board" :key="wc.id" class="scheduling-row">
            <td class="wc-cell">
              <div style="font-weight:600;font-size:13px">{{ wc.name }}</div>
              <div style="font-size:11px;color:var(--fg-3)">{{ wc.code }}</div>
            </td>
            <td
              v-for="day in weekDays"
              :key="day.date"
              class="day-cell"
              :data-workcenter="wc.id"
              :data-date="day.date"
              @dragover.prevent="onDragOver"
              @drop="onDrop($event, wc.id, day.date)"
            >
              <div
                v-for="order in getOrdersForDay(wc, day.date)"
                :key="order.id"
                class="order-card"
                :class="getOrderClass(order)"
                draggable="true"
                @dragstart="onDragStart($event, order, wc.id)"
              >
                <div style="font-weight:600;font-size:12px">{{ order.reference }}</div>
                <div style="font-size:11px;margin-top:2px">Qty: {{ order.quantity_planned }}</div>
                <Tag :value="order.status" :severity="getStatusSeverity(order.status)" style="font-size:10px;margin-top:4px" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Drag confirmation toast -->
    <div v-if="assignMessage" class="assign-toast" :class="{ error: assignError }">
      {{ assignMessage }}
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag } from 'primevue'
import axios from 'axios'

interface WorkOrder {
  id: number
  reference: string
  status: string
  priority: string
  quantity_planned: string
  scheduled_start: string | null
  scheduled_end: string | null
  is_overdue: boolean
}

interface WorkCenter {
  id: number
  name: string
  code: string
  orders: WorkOrder[]
}

interface BoardData {
  date: string
  week_start: string
  week_end: string
  work_centers: WorkCenter[]
}

const board      = ref<WorkCenter[]>([])
const weekStart  = ref('')
const loading    = ref(false)
const assignMessage = ref('')
const assignError   = ref(false)
let draggedOrder: { order: WorkOrder; fromWorkcenterId: number } | null = null

const weekDays = computed(() => {
  if (!weekStart.value) return []
  const days = []
  const start = new Date(weekStart.value + 'T00:00:00')
  const labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']
  for (let i = 0; i < 5; i++) {
    const d = new Date(start)
    d.setDate(d.getDate() + i)
    days.push({
      label: labels[i],
      date: d.toISOString().slice(0, 10),
    })
  }
  return days
})

async function loadBoard(): Promise<void> {
  loading.value = true
  try {
    const { data } = await axios.get<BoardData>('/api/v1/manufacturing/scheduling/board')
    board.value      = data.work_centers
    weekStart.value  = data.week_start
  } catch {
    board.value = []
  } finally {
    loading.value = false
  }
}

function getOrdersForDay(wc: WorkCenter, date: string): WorkOrder[] {
  return wc.orders.filter(o => {
    if (!o.scheduled_start) return false
    return o.scheduled_start.slice(0, 10) === date
  })
}

function getOrderClass(order: WorkOrder): string {
  if (order.is_overdue) return 'order-overdue'
  if (order.status === 'in_progress') return 'order-inprogress'
  if (order.status === 'draft') return 'order-draft'
  return 'order-planned'
}

function getStatusSeverity(status: string): string {
  const map: Record<string, string> = {
    in_progress: 'success',
    confirmed:   'info',
    draft:       'warning',
    completed:   'secondary',
    cancelled:   'danger',
  }
  return map[status] ?? 'secondary'
}

function onDragStart(event: DragEvent, order: WorkOrder, workcenterId: number): void {
  draggedOrder = { order, fromWorkcenterId: workcenterId }
  event.dataTransfer?.setData('text/plain', String(order.id))
}

function onDragOver(event: DragEvent): void {
  event.preventDefault()
}

async function onDrop(event: DragEvent, workcenterId: number, date: string): Promise<void> {
  event.preventDefault()
  if (!draggedOrder) return

  const { order } = draggedOrder
  draggedOrder = null

  try {
    await axios.post('/api/v1/manufacturing/scheduling/assign', {
      work_order_id:  order.id,
      workcenter_id:  workcenterId,
      scheduled_date: date,
    })

    // Update local state
    board.value.forEach(wc => {
      wc.orders = wc.orders.filter(o => o.id !== order.id)
    })
    const targetWc = board.value.find(wc => wc.id === workcenterId)
    if (targetWc) {
      targetWc.orders.push({
        ...order,
        scheduled_start: date + 'T08:00:00',
        scheduled_end:   date + 'T16:00:00',
      })
    }

    showMessage('Work order reassigned.', false)
  } catch {
    showMessage('Failed to assign work order.', true)
  }
}

function showMessage(msg: string, isError: boolean): void {
  assignMessage.value = msg
  assignError.value   = isError
  setTimeout(() => { assignMessage.value = '' }, 3000)
}

onMounted(loadBoard)
</script>

<style scoped>
.scheduling-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.scheduling-table th,
.scheduling-table td {
  border: 1px solid var(--border-subtle);
  padding: 8px;
  vertical-align: top;
}

.scheduling-table th {
  background: var(--bg-2);
  font-weight: 600;
}

.wc-cell {
  background: var(--bg-1);
  white-space: nowrap;
}

.day-cell {
  min-height: 80px;
  background: var(--bg-0);
  transition: background .15s;
}

.day-cell:hover { background: var(--bg-2); }

.order-card {
  padding: 6px 8px;
  border-radius: 6px;
  margin-bottom: 4px;
  cursor: grab;
  color: #fff;
  font-size: 12px;
}

.order-card:active { cursor: grabbing; }

.order-overdue   { background: #ef4444; }
.order-inprogress { background: #22c55e; }
.order-planned   { background: #3b82f6; }
.order-draft     { background: #f59e0b; }

.legend-badge {
  color: #fff;
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.assign-toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: var(--success, #22c55e);
  color: #fff;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  z-index: 9999;
}

.assign-toast.error { background: var(--danger, #ef4444); }
</style>
