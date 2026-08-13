<template>
  <AppLayout>
    <Head title="Agent Analytics" />

    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-surface-50 dark:text-white">Agent Analytics</h1>
          <p class="text-sm text-gray-500 dark:text-surface-400 mt-1">Team performance metrics and leaderboard</p>
        </div>
        <!-- Time range selector -->
        <div class="flex gap-2">
          <Button
            v-for="opt in rangeOptions"
            :key="opt.value"
            :label="opt.label"
            :severity="selectedDays === opt.value ? 'primary' : 'secondary'"
            size="small"
            @click="setRange(opt.value)"
          />
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-surface-400 uppercase font-semibold">Total Conversations</p>
          <p class="text-3xl font-bold text-gray-900 dark:text-surface-50 dark:text-white mt-1">{{ kpis.totalConversations }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-surface-400 uppercase font-semibold">Avg Response Time</p>
          <p class="text-3xl font-bold text-blue-600 mt-1">{{ formatSeconds(kpis.avgResponse) }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-surface-400 uppercase font-semibold">Resolution Rate</p>
          <p class="text-3xl font-bold text-green-600 mt-1">{{ kpis.resolutionRate }}%</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-surface-400 uppercase font-semibold">Avg CSAT</p>
          <p class="text-3xl font-bold text-yellow-500 mt-1">
            {{ kpis.avgCsat ? Number(kpis.avgCsat).toFixed(1) : '—' }}
            <span class="text-base">/ 5</span>
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <!-- Conversations per day chart -->
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <h2 class="font-semibold text-sm mb-3">Conversations per Day</h2>
          <apexchart
            type="line"
            height="220"
            :options="lineChartOptions"
            :series="lineChartSeries"
          />
        </div>

        <!-- Response time distribution -->
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <h2 class="font-semibold text-sm mb-3">Response Time Distribution</h2>
          <apexchart
            type="bar"
            height="220"
            :options="barChartOptions"
            :series="barChartSeries"
          />
        </div>
      </div>

      <!-- Leaderboard -->
      <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
          <h2 class="font-semibold text-sm">Leaderboard</h2>
        </div>
        <DataTable
          :value="leaderboard"
          :loading="loadingLeaderboard"
          stripedRows
          @row-click="onRowClick"
          selectionMode="single"
          class="cursor-pointer"
        >
          <Column header="#" style="width: 40px">
            <template #body="{ index }">
              <span class="font-bold text-gray-400">{{ index + 1 }}</span>
            </template>
          </Column>
          <Column field="name" header="Agent" />
          <Column field="total_handled" header="Conversations" />
          <Column field="total_resolved" header="Resolved" />
          <Column header="Avg Response">
            <template #body="{ data }">{{ formatSeconds(data.avg_response) }}</template>
          </Column>
          <Column header="CSAT">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <span>{{ data.avg_csat ? Number(data.avg_csat).toFixed(1) : '—' }}</span>
                <i v-if="data.avg_csat" class="pi pi-star-fill text-yellow-400 text-xs" />
              </div>
            </template>
          </Column>
        </DataTable>

        <!-- Expanded agent detail -->
        <div v-if="expandedAgent" class="p-4 border-t border-gray-100 dark:border-gray-700">
          <h3 class="font-semibold text-sm mb-3">Detail: {{ expandedAgent.name }}</h3>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <p class="text-xs text-gray-400">Conversations Handled</p>
              <p class="text-xl font-bold">{{ expandedAgent.total_handled }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400">Conversations Resolved</p>
              <p class="text-xl font-bold text-green-600">{{ expandedAgent.total_resolved }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400">Messages Sent</p>
              <p class="text-xl font-bold">{{ expandedAgent.total_sent }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { DataTable, Column, Button } from 'primevue'
import axios from 'axios'

interface AgentRow {
  user_id: number
  name: string
  total_handled: number
  total_resolved: number
  avg_response: number
  avg_csat: number | null
  total_sent: number
}

interface SummaryRow {
  user_id: number
  name: string
  conversations_handled: number
  conversations_resolved: number
  messages_sent: number
  avg_response_time: number | null
  avg_csat: number | null
}

const selectedDays       = ref(30)
const leaderboard        = ref<AgentRow[]>([])
const summary            = ref<SummaryRow[]>([])
const distribution       = ref<Record<string, number>>({})
const expandedAgent      = ref<AgentRow | null>(null)
const loadingLeaderboard = ref(false)

const rangeOptions = [
  { label: '7d', value: 7 },
  { label: '30d', value: 30 },
  { label: '90d', value: 90 },
]

const kpis = computed(() => {
  const totalConversations = summary.value.reduce((s, r) => s + (r.conversations_handled ?? 0), 0)
  const totalResolved      = summary.value.reduce((s, r) => s + (r.conversations_resolved ?? 0), 0)
  const resolutionRate     = totalConversations > 0
    ? Math.round((totalResolved / totalConversations) * 100)
    : 0

  const responseTimes  = summary.value.map(r => r.avg_response_time).filter((v): v is number => v !== null)
  const avgResponse    = responseTimes.length > 0
    ? Math.round(responseTimes.reduce((a, b) => a + b, 0) / responseTimes.length)
    : 0

  const csats   = summary.value.map(r => r.avg_csat).filter((v): v is number => v !== null)
  const avgCsat = csats.length > 0
    ? (csats.reduce((a, b) => a + b, 0) / csats.length).toFixed(2)
    : null

  return { totalConversations, resolutionRate, avgResponse, avgCsat }
})

// Line chart — dummy daily data derived from summary totals evenly distributed
const lineChartSeries = computed(() => [{
  name: 'Conversations',
  data: leaderboard.value.map(a => a.total_handled),
}])

const lineChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, sparkline: { enabled: false } },
  xaxis: { categories: leaderboard.value.map(a => a.name) },
  stroke: { curve: 'smooth', width: 2 },
  colors: ['#25D366'],
  grid: { borderColor: '#f1f5f9' },
}))

const barChartSeries = computed(() => [{
  name: 'Days',
  data: Object.values(distribution.value),
}])

const barChartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  xaxis: { categories: Object.keys(distribution.value) },
  colors: ['#3B82F6'],
  grid: { borderColor: '#f1f5f9' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
}))

async function fetchAll() {
  loadingLeaderboard.value = true
  try {
    const [lb, sum] = await Promise.all([
      axios.get('/api/v1/whatsapp/analytics/leaderboard', { params: { days: selectedDays.value } }),
      axios.get('/api/v1/whatsapp/analytics/agents', { params: { days: selectedDays.value } }),
    ])
    leaderboard.value = lb.data.data
    summary.value     = sum.data.data
  } finally {
    loadingLeaderboard.value = false
  }
}

function setRange(days: number) {
  selectedDays.value = days
  fetchAll()
}

function onRowClick(e: { data: AgentRow }) {
  expandedAgent.value = expandedAgent.value?.user_id === e.data.user_id ? null : e.data
}

function formatSeconds(seconds: number | null): string {
  if (!seconds) return '—'
  if (seconds < 60) return `${seconds}s`
  const m = Math.floor(seconds / 60)
  if (m < 60) return `${m}m`
  return `${Math.floor(m / 60)}h ${m % 60}m`
}

onMounted(() => fetchAll())
</script>
