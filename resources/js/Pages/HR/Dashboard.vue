<template>
  <AppLayout>
    <Head title="HR Dashboard" />

    <!-- Page header -->
    <div class="page-head">
      <div style="display:flex;align-items:center;gap:12px">
        <h1 class="wh-page-title">HR Dashboard</h1>
        <!-- LIVE badge -->
        <span style="display:inline-flex;align-items:center;gap:5px;background:#dcfce7;color:#15803d;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px">
          <span
            style="
              width:7px;height:7px;border-radius:50%;background:#22c55e;
              animation: pulse-dot 1.4s ease-in-out infinite;
            "
          />
          LIVE
        </span>
      </div>
      <p class="wh-page-subtitle" style="margin-top:4px">Real-time workforce overview · auto-refresh every 30s</p>
    </div>

    <!-- KPI Cards -->
    <div class="wh-kpi-row" style="margin-bottom:24px">
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Total Employees</div>
        <div class="wh-kpi-value">{{ stats.headcount ?? '—' }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Absences Today</div>
        <div class="wh-kpi-value" style="color:#ef4444">{{ stats.absences_today ?? '—' }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Open Positions</div>
        <div class="wh-kpi-value" style="color:#3b82f6">{{ stats.open_positions ?? '—' }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Avg. Tenure</div>
        <div class="wh-kpi-value">{{ avgTenureDisplay }}</div>
      </div>
    </div>

    <!-- Charts row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

      <!-- Dept distribution donut -->
      <div class="wh-panel" style="padding:16px">
        <div style="font-weight:600;font-size:15px;margin-bottom:12px">Department Distribution</div>
        <VueApexCharts
          v-if="deptChartReady"
          type="donut"
          height="280"
          :options="deptChartOptions"
          :series="deptChartSeries"
        />
        <div v-else style="height:280px;display:flex;align-items:center;justify-content:center;color:var(--fg-4)">
          Loading…
        </div>
      </div>

      <!-- Attendance bar chart -->
      <div class="wh-panel" style="padding:16px">
        <div style="font-weight:600;font-size:15px;margin-bottom:12px">Today's Attendance</div>
        <VueApexCharts
          v-if="attendanceChartReady"
          type="bar"
          height="280"
          :options="attendanceChartOptions"
          :series="attendanceChartSeries"
        />
        <div v-else style="height:280px;display:flex;align-items:center;justify-content:center;color:var(--fg-4)">
          Loading…
        </div>
      </div>
    </div>

    <!-- Pending leave requests table -->
    <div class="wh-panel">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border-1);font-weight:600;font-size:15px">
        Pending Leave Requests
        <span
          v-if="leaveStats.pending_requests"
          style="margin-left:8px;background:#fef3c7;color:#92400e;font-size:11px;padding:2px 7px;border-radius:10px;font-weight:600"
        >{{ leaveStats.pending_requests }}</span>
      </div>

      <DataTable
        :value="pendingLeaves"
        :loading="loadingLeaves"
        striped-rows
        style="font-size:13px"
      >
        <Column field="employee_name" header="Employee" />
        <Column field="leave_type" header="Type" />
        <Column field="start_date" header="From">
          <template #body="{ data }">{{ formatDate(data.start_date) }}</template>
        </Column>
        <Column field="end_date" header="To">
          <template #body="{ data }">{{ formatDate(data.end_date) }}</template>
        </Column>
        <Column field="days" header="Days" style="width:60px;text-align:center" />
        <Column header="Status" style="width:90px">
          <template #body>
            <Tag value="Pending" severity="warn" />
          </template>
        </Column>
        <Column header="Actions" style="width:160px">
          <template #body="{ data }">
            <div style="display:flex;gap:6px">
              <button class="btn btn-primary" style="padding:3px 10px;font-size:12px" @click="handleLeave(data.id, 'approve')">
                Approve
              </button>
              <button class="btn btn-danger" style="padding:3px 10px;font-size:12px" @click="handleLeave(data.id, 'reject')">
                Reject
              </button>
            </div>
          </template>
        </Column>
      </DataTable>

      <div v-if="pendingLeaves.length === 0 && !loadingLeaves" style="padding:24px;text-align:center;color:var(--fg-4);font-size:13px">
        No pending leave requests.
      </div>
    </div>

    <style>
      @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: .5; transform: scale(1.4); }
      }
    </style>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, defineAsyncComponent } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { DataTable, Column, Tag } from 'primevue'
import axios from 'axios'

// Lazy load ApexCharts
const VueApexCharts = defineAsyncComponent(() => import('vue-apexcharts'))

// ── State ─────────────────────────────────────────────────────────────────────

const stats           = ref({})
const deptDist        = ref({})
const leaveStats      = ref({ pending_requests: 0, approved_this_month: 0 })
const attendance      = ref({})
const pendingLeaves   = ref([])
const loadingLeaves   = ref(false)
const deptChartReady  = ref(false)
const attendanceChartReady = ref(false)

let refreshTimer = null

// ── Computed ──────────────────────────────────────────────────────────────────

const avgTenureDisplay = computed(() => {
  const m = stats.value.avg_tenure_months
  if (m == null) return '—'
  const years  = Math.floor(m / 12)
  const months = Math.round(m % 12)
  if (years === 0) return `${months}m`
  return `${years}y ${months}m`
})

// Department donut
const deptChartSeries = computed(() => Object.values(deptDist.value))
const deptChartOptions = computed(() => ({
  labels: Object.keys(deptDist.value),
  chart:  { fontFamily: 'inherit' },
  legend: { position: 'bottom' },
  colors: ['#3b82f6','#22c55e','#f97316','#a855f7','#06b6d4','#ec4899','#14b8a6','#eab308'],
  dataLabels: { enabled: false },
  plotOptions: { pie: { donut: { size: '65%' } } },
}))

// Attendance bar
const attendanceChartSeries = computed(() => [{
  name: 'Employees',
  data: [
    attendance.value.present  ?? 0,
    attendance.value.remote   ?? 0,
    attendance.value.on_leave ?? 0,
    attendance.value.absent   ?? 0,
  ],
}])
const attendanceChartOptions = {
  chart: { fontFamily: 'inherit', toolbar: { show: false } },
  xaxis: { categories: ['Present', 'Remote', 'On Leave', 'Absent'] },
  colors: ['#22c55e', '#3b82f6', '#f97316', '#ef4444'],
  plotOptions: { bar: { distributed: true, borderRadius: 4, columnWidth: '50%' } },
  legend: { show: false },
  dataLabels: { enabled: true },
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString() : '—'
}

// ── API ───────────────────────────────────────────────────────────────────────

async function loadDashboard() {
  try {
    const { data } = await axios.get('/api/v1/hr/dashboard')
    stats.value      = data.stats        ?? {}
    deptDist.value   = data.department_distribution ?? {}
    leaveStats.value = data.leave_stats  ?? {}
    attendance.value = data.attendance   ?? {}
    deptChartReady.value      = Object.keys(deptDist.value).length > 0
    attendanceChartReady.value = true
  } catch (e) {
    console.error('HR Dashboard load error', e)
  }
}

async function loadPendingLeaves() {
  loadingLeaves.value = true
  try {
    const { data } = await axios.get('/api/v1/hr/leaves', { params: { status: 'pending', per_page: 20 } })
    pendingLeaves.value = (data.data ?? data).map(l => ({
      id:            l.id,
      employee_name: l.employee?.first_name + ' ' + l.employee?.last_name,
      leave_type:    l.leaveType?.name ?? l.leave_type_id,
      start_date:    l.start_date,
      end_date:      l.end_date,
      days:          l.days_requested ?? l.days ?? '—',
    }))
  } finally {
    loadingLeaves.value = false
  }
}

async function loadRealtime() {
  try {
    const { data } = await axios.get('/api/v1/hr/dashboard/realtime')
    stats.value.headcount      = data.headcount      ?? stats.value.headcount
    stats.value.absences_today = data.absences_today ?? stats.value.absences_today
    attendance.value           = data.attendance     ?? attendance.value
  } catch {
    // silently ignore polling errors
  }
}

async function handleLeave(id, action) {
  try {
    await axios.post(`/api/v1/hr/leaves/${id}/${action}`)
    await loadPendingLeaves()
    await loadRealtime()
  } catch (e) {
    console.error('Leave action error', e)
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([loadDashboard(), loadPendingLeaves()])
  refreshTimer = setInterval(loadRealtime, 30_000)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>
