<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Analytics', 'view_dashboard')

const horizon = ref(90)
const loading = ref(true)
const exporting = ref(false)
const result = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/forecasting/cashflow', { params: { days: horizon.value } })
    result.value = data
  } finally {
    loading.value = false
  }
}

watch(horizon, load)
onMounted(load)

const currency = computed(() => result.value?.summary?.currency ?? '')

function fmt(n) {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n ?? 0)
}

async function downloadExport(format) {
  exporting.value = true
  try {
    const accept = format === 'pdf'
      ? 'application/pdf'
      : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    const url = `/api/v1/forecasting/cashflow/export/${format}?days=${horizon.value}`
    const res = await fetch(url, { headers: { Accept: accept } })
    if (!res.ok) throw new Error('Export failed')
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `previsions-tresorerie.${format === 'pdf' ? 'pdf' : 'xlsx'}`
    a.click()
    URL.revokeObjectURL(a.href)
  } finally {
    exporting.value = false
  }
}

// Sparkline SVG du solde cumulé (même précédent léger que Strategy/Ratios/Index.vue)
const sparklinePoints = computed(() => {
  const daily = result.value?.daily ?? []
  if (daily.length < 2) return ''
  const values = daily.map((d) => Number(d.running_balance))
  const min = Math.min(...values, 0)
  const max = Math.max(...values, 0)
  const range = max - min || 1
  const w = 600
  const h = 120
  return daily
    .map((d, i) => {
      const x = (i / (daily.length - 1)) * w
      const y = h - ((Number(d.running_balance) - min) / range) * h
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
})

const zeroLineY = computed(() => {
  const daily = result.value?.daily ?? []
  if (daily.length < 2) return null
  const values = daily.map((d) => Number(d.running_balance))
  const min = Math.min(...values, 0)
  const max = Math.max(...values, 0)
  const range = max - min || 1
  return 120 - ((0 - min) / range) * 120
})
</script>

<template>
  <AppLayout>
    <Head title="Prévisions de trésorerie" />

    <div class="max-w-6xl mx-auto space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Prévisions de trésorerie</h1>
          <p class="text-surface-500 text-sm mt-1">
            Encaissements et décaissements projetés à partir des factures échues et de la moyenne réelle des 6 derniers mois.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <select
            v-model.number="horizon"
            class="rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm"
          >
            <option :value="30">30 jours</option>
            <option :value="60">60 jours</option>
            <option :value="90">90 jours</option>
            <option :value="180">180 jours</option>
          </select>
          <button
            class="px-3 py-2 border border-surface-300 dark:border-surface-600 rounded-lg text-sm font-medium hover:bg-surface-50 dark:hover:bg-surface-800 disabled:opacity-50"
            :disabled="exporting || loading"
            @click="downloadExport('pdf')"
          >
            Exporter PDF
          </button>
          <button
            class="px-3 py-2 border border-surface-300 dark:border-surface-600 rounded-lg text-sm font-medium hover:bg-surface-50 dark:hover:bg-surface-800 disabled:opacity-50"
            :disabled="exporting || loading"
            @click="downloadExport('excel')"
          >
            Exporter Excel
          </button>
        </div>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div v-if="loading" class="text-center py-12 text-surface-400">Chargement…</div>

      <template v-else-if="result">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
            <div class="text-xs text-surface-500 uppercase tracking-wide">Solde final estimé</div>
            <div class="text-xl font-bold mt-1" :class="result.summary.final_balance < 0 ? 'text-red-600' : 'text-surface-900 dark:text-surface-50'">
              {{ fmt(result.summary.final_balance) }} {{ currency }}
            </div>
          </div>
          <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
            <div class="text-xs text-surface-500 uppercase tracking-wide">Total encaissements</div>
            <div class="text-xl font-bold mt-1 text-green-600">{{ fmt(result.summary.total_inflow) }} {{ currency }}</div>
          </div>
          <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
            <div class="text-xs text-surface-500 uppercase tracking-wide">Total décaissements</div>
            <div class="text-xl font-bold mt-1 text-orange-600">{{ fmt(result.summary.total_outflow) }} {{ currency }}</div>
          </div>
          <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
            <div class="text-xs text-surface-500 uppercase tracking-wide">Solde minimum</div>
            <div class="text-xl font-bold mt-1" :class="result.summary.has_deficit ? 'text-red-600' : 'text-surface-900 dark:text-surface-50'">
              {{ fmt(result.summary.min_balance) }} {{ currency }}
            </div>
          </div>
        </div>

        <div class="bg-primary-50 dark:bg-primary-950/30 border-l-4 border-primary-500 rounded-r-lg p-4 text-sm">
          {{ result.narrative }}
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
          <h2 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-3">Solde cumulé projeté</h2>
          <svg viewBox="0 0 600 120" class="w-full h-32">
            <line
              v-if="zeroLineY !== null"
              x1="0" :y1="zeroLineY" x2="600" :y2="zeroLineY"
              stroke="#ef4444" stroke-width="1" stroke-dasharray="4 4"
            />
            <polyline :points="sparklinePoints" fill="none" stroke="#2e5be8" stroke-width="2" />
          </svg>
        </div>

        <div v-if="result.gaps.length" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
          <h2 class="text-sm font-semibold text-surface-700 dark:text-surface-200 p-4 pb-0">Périodes de déficit détectées</h2>
          <table class="w-full text-sm mt-3">
            <thead class="border-b border-surface-200 dark:border-surface-700">
              <tr>
                <th class="px-4 py-2 text-left">Du</th>
                <th class="px-4 py-2 text-left">Au</th>
                <th class="px-4 py-2 text-right">Solde minimum</th>
                <th class="px-4 py-2 text-left">Sévérité</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(gap, i) in result.gaps" :key="i" class="border-b border-surface-100 dark:border-surface-700">
                <td class="px-4 py-2">{{ gap.start_date }}</td>
                <td class="px-4 py-2">{{ gap.end_date }}</td>
                <td class="px-4 py-2 text-right text-red-600 font-medium">{{ fmt(gap.min_balance) }} {{ currency }}</td>
                <td class="px-4 py-2">
                  <span
                    class="px-2 py-0.5 rounded-full text-xs"
                    :class="{
                      'bg-red-100 text-red-700': gap.severity === 'critical',
                      'bg-yellow-100 text-yellow-700': gap.severity === 'warning',
                      'bg-blue-100 text-blue-700': gap.severity === 'info',
                    }"
                  >
                    {{ gap.severity }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
          <h2 class="text-sm font-semibold text-surface-700 dark:text-surface-200 p-4 pb-0">Projection journalière</h2>
          <div class="max-h-96 overflow-y-auto mt-3">
            <table class="w-full text-sm">
              <thead class="border-b border-surface-200 dark:border-surface-700 sticky top-0 bg-surface-0 dark:bg-surface-800">
                <tr>
                  <th class="px-4 py-2 text-left">Date</th>
                  <th class="px-4 py-2 text-right">Encaissement</th>
                  <th class="px-4 py-2 text-right">Décaissement</th>
                  <th class="px-4 py-2 text-right">Net</th>
                  <th class="px-4 py-2 text-right">Solde cumulé</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="day in result.daily" :key="day.date" class="border-b border-surface-100 dark:border-surface-700">
                  <td class="px-4 py-2">{{ day.date }}</td>
                  <td class="px-4 py-2 text-right text-green-600">{{ fmt(day.inflow) }}</td>
                  <td class="px-4 py-2 text-right text-orange-600">{{ fmt(day.outflow) }}</td>
                  <td class="px-4 py-2 text-right" :class="day.net < 0 ? 'text-red-600' : ''">{{ fmt(day.net) }}</td>
                  <td class="px-4 py-2 text-right font-medium" :class="day.running_balance < 0 ? 'text-red-600' : ''">{{ fmt(day.running_balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
