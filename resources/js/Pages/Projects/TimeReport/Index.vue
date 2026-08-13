<template>
  <AppLayout>
    <Head title="Time Report" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Time Report</h1>
        <p class="page-sub">Track logged hours across all projects</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel filter-panel">
      <div class="filter-row">
        <!-- Date Range Presets -->
        <div class="field">
          <label class="field-label">Period</label>
          <select v-model="period" class="field-input" @change="applyPeriod">
            <option value="this_week">This Week</option>
            <option value="this_month">This Month</option>
            <option value="last_month">Last Month</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <div v-if="period === 'custom'" class="field">
          <label class="field-label">From</label>
          <input v-model="dateFrom" type="date" class="field-input" />
        </div>
        <div v-if="period === 'custom'" class="field">
          <label class="field-label">To</label>
          <input v-model="dateTo" type="date" class="field-input" />
        </div>
        <!-- Group by -->
        <div class="field">
          <label class="field-label">Group by</label>
          <select v-model="groupBy" class="field-input">
            <option value="member">Member</option>
            <option value="project">Project</option>
            <option value="task">Task</option>
          </select>
        </div>
        <button class="btn btn-primary" @click="fetchReport">Apply</button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="cards" v-if="report">
      <div class="card">
        <div class="card-label">Total Hours</div>
        <div class="card-value">{{ report.total_hours.toFixed(1) }}h</div>
      </div>
      <div class="card">
        <div class="card-label">Billable Hours</div>
        <div class="card-value">{{ report.billable_hours.toFixed(1) }}h</div>
      </div>
      <div class="card">
        <div class="card-label">Billable Amount</div>
        <div class="card-value">{{ formatMoney(report.billable_amount) }}</div>
      </div>
      <div class="card">
        <div class="card-label">Members</div>
        <div class="card-value">{{ report.rows.length }}</div>
      </div>
    </div>

    <!-- Data Table -->
    <div class="wh-panel" v-if="report">
      <div class="wh-panel-head"><h3>Details</h3></div>
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ groupByLabel }}</th>
            <th v-if="groupBy !== 'member'">Member</th>
            <th v-if="groupBy !== 'project'">Project</th>
            <th v-if="groupBy !== 'task'">Task</th>
            <th class="num">Hours</th>
            <th class="num">Billable</th>
            <th class="num">Rate</th>
            <th class="num">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in report.rows" :key="row.key" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ row.label }}</td>
            <td v-if="groupBy !== 'member'" style="color:var(--fg-2)">{{ row.member ?? '—' }}</td>
            <td v-if="groupBy !== 'project'" style="color:var(--fg-2)">{{ row.project ?? '—' }}</td>
            <td v-if="groupBy !== 'task'" style="color:var(--fg-2)">{{ row.task ?? '—' }}</td>
            <td class="num">{{ row.hours.toFixed(1) }}h</td>
            <td class="num">{{ row.billable_hours.toFixed(1) }}h</td>
            <td class="num" style="color:var(--fg-3)">{{ row.rate ? formatMoney(row.rate) : '—' }}</td>
            <td class="num" style="font-weight:600">{{ row.amount ? formatMoney(row.amount) : '—' }}</td>
          </tr>
          <tr v-if="!report.rows.length">
            <td :colspan="5" style="text-align:center;color:var(--fg-3);padding:32px 18px">No time logs found for this period.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty state -->
    <div v-if="!report && !loading" class="wh-panel" style="padding:48px;text-align:center;color:var(--fg-3)">
      Select a period and click Apply to load the report.
    </div>

    <div v-if="loading" class="wh-panel" style="padding:48px;text-align:center;color:var(--fg-3)">
      Loading...
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface ReportRow {
  key: string
  label: string
  member?: string
  project?: string
  task?: string
  hours: number
  billable_hours: number
  rate?: number
  amount?: number
}

interface Report {
  total_hours: number
  billable_hours: number
  billable_amount: number
  rows: ReportRow[]
}

const period  = ref('this_week')
const dateFrom = ref('')
const dateTo   = ref('')
const groupBy  = ref('member')
const report   = ref<Report | null>(null)
const loading  = ref(false)

const groupByLabel = computed(() => ({
  member: 'Member', project: 'Project', task: 'Task',
}[groupBy.value] ?? 'Group'))

function applyPeriod() {
  const now = new Date()
  if (period.value === 'this_week') {
    const day = now.getDay()
    const diff = now.getDate() - day + (day === 0 ? -6 : 1)
    const monday = new Date(now.setDate(diff))
    dateFrom.value = monday.toISOString().split('T')[0]
    dateTo.value   = new Date().toISOString().split('T')[0]
  } else if (period.value === 'this_month') {
    dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
    dateTo.value   = new Date().toISOString().split('T')[0]
  } else if (period.value === 'last_month') {
    const last = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    dateFrom.value = last.toISOString().split('T')[0]
    dateTo.value   = new Date(now.getFullYear(), now.getMonth(), 0).toISOString().split('T')[0]
  }
}

async function fetchReport() {
  loading.value = true
  try {
    // Aggregate from all projects time-logs
    // In a real implementation this would be a dedicated endpoint
    // For now we build from client-side aggregation using per-project data
    // This is a placeholder that returns mock structure
    const params = new URLSearchParams({
      date_from: dateFrom.value,
      date_to: dateTo.value,
      group_by: groupBy.value,
    })

    // Attempt to fetch aggregated data (endpoint may not exist yet, will handle gracefully)
    try {
      const { data } = await axios.get(`/api/v1/projects/time-report-global?${params}`)
      report.value = data
    } catch {
      // Fallback: empty report
      report.value = {
        total_hours: 0,
        billable_hours: 0,
        billable_amount: 0,
        rows: [],
      }
    }
  } finally {
    loading.value = false
  }
}

function formatMoney(v: number) {
  return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(() => {
  applyPeriod()
})
</script>

<style scoped>
.page-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:22px; font-weight:600; letter-spacing:-0.02em; color:var(--fg-1); }
.page-sub { font-size:13px; color:var(--fg-3); margin:4px 0 0; }

.filter-panel { padding:16px 18px; margin-bottom:20px; }
.filter-row { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; }
.field { display:flex; flex-direction:column; }
.field-label { font-size:11px; font-weight:500; color:var(--fg-2); margin-bottom:4px; }
.field-input { padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); font-size:13px; background:var(--bg-canvas); color:var(--fg-1); }

.cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
.card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px; }
.card-label { font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-3); margin-bottom:6px; }
.card-value { font-size:22px; font-weight:700; color:var(--fg-1); }

.wh-panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-panel-head h3 { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }

.btn { display:inline-flex; align-items:center; gap:6px; padding:7px 16px; border-radius:var(--r-md); font-size:13px; font-weight:500; cursor:pointer; border:none; }
.btn-primary { background:var(--halo-500); color:#fff; align-self:flex-end; }
.btn-primary:hover { background:var(--halo-600); }
</style>
