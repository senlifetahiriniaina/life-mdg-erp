<template>
  <AppLayout>
    <Head :title="$t('manufacturing.mrp.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('manufacturing.mrp.title') }}</h1>
        <p class="wh-page-subtitle">{{ $t('manufacturing.mrp.subtitle') }}</p>
      </div>
      <div class="page-actions">
        <Select
          v-model="horizon"
          :options="horizonOptions"
          option-label="label"
          option-value="value"
          class="filter-select"
          style="width:130px"
        />
        <button class="btn btn-primary" :disabled="running" @click="runMrp">
          <i class="pi pi-play" style="font-size:13px" />
          {{ running ? $t('manufacturing.mrp.running') : $t('manufacturing.mrp.run_btn') }}
        </button>
      </div>
    </div>

    <!-- Runs list -->
    <div class="wh-panel">
      <h2 class="panel-title">{{ $t('manufacturing.mrp.past_runs') }}</h2>
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('manufacturing.mrp.run_date') }}</th>
            <th class="num">{{ $t('manufacturing.mrp.horizon') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('manufacturing.mrp.created_by') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="run in runs.data" :key="run.id" class="wh-dt-row">
            <td>{{ formatDate(run.run_date) }}</td>
            <td class="num">{{ run.horizon_days }}d</td>
            <td>
              <span :class="['badge', statusClass(run.status)]">{{ run.status }}</span>
            </td>
            <td>{{ run.created_by?.name ?? '—' }}</td>
            <td>
              <button class="btn btn-sm btn-secondary" @click="loadSuggestions(run)">
                <i class="pi pi-list" /> {{ $t('manufacturing.mrp.suggestions') }}
              </button>
            </td>
          </tr>
          <tr v-if="runs.data.length === 0">
            <td colspan="5" class="empty-state">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Suggestions panel -->
    <div v-if="selectedRun" class="wh-panel mt-4">
      <h2 class="panel-title">{{ $t('manufacturing.mrp.suggestions_for') }} #{{ selectedRun.id }}</h2>
      <div v-if="loadingSuggestions" class="text-muted p-4">{{ $t('common.loading') }}…</div>
      <table v-else class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('manufacturing.mrp.product') }}</th>
            <th>{{ $t('manufacturing.mrp.type') }}</th>
            <th class="num">{{ $t('manufacturing.mrp.quantity') }}</th>
            <th>{{ $t('manufacturing.mrp.suggested_date') }}</th>
            <th>{{ $t('manufacturing.mrp.priority') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sug in suggestions" :key="sug.id" class="wh-dt-row">
            <td>{{ sug.product?.name ?? '—' }}</td>
            <td>
              <span :class="['badge', sug.suggestion_type === 'production' ? 'badge-info' : 'badge-warning']">
                {{ sug.suggestion_type }}
              </span>
            </td>
            <td class="num">{{ sug.quantity }}</td>
            <td>{{ sug.suggested_date }}</td>
            <td>
              <span v-for="n in sug.priority" :key="n" class="pi pi-star-fill" style="color:var(--primary);font-size:12px" />
            </td>
            <td>
              <button
                v-if="!sug.accepted"
                class="btn btn-sm btn-success"
                @click="accept(sug)"
              >
                {{ $t('manufacturing.mrp.accept') }}
              </button>
              <span v-else class="badge badge-success">{{ $t('manufacturing.mrp.accepted') }}</span>
            </td>
          </tr>
          <tr v-if="suggestions.length === 0">
            <td colspan="6" class="empty-state">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Select } from 'primevue'
import axios from 'axios'

interface MrpRun {
  id: number
  run_date: string
  horizon_days: number
  status: string
  created_by: { id: number; name: string } | null
}
interface MrpSuggestion {
  id: number
  product: { id: number; name: string } | null
  suggestion_type: string
  quantity: number
  suggested_date: string
  priority: number
  accepted: boolean
}
interface Pagination<T> { data: T[]; total: number; page: number; last_page: number }

defineProps<{ runs: Pagination<MrpRun> }>()

const horizon = ref(90)
const horizonOptions = [
  { label: '30 days', value: 30 },
  { label: '60 days', value: 60 },
  { label: '90 days', value: 90 },
]
const running = ref(false)

async function runMrp() {
  running.value = true
  try {
    await axios.post('/api/v1/manufacturing/mrp/run', { horizon_days: horizon.value })
    window.location.reload()
  } finally {
    running.value = false
  }
}

const selectedRun = ref<MrpRun | null>(null)
const suggestions = ref<MrpSuggestion[]>([])
const loadingSuggestions = ref(false)

async function loadSuggestions(run: MrpRun) {
  selectedRun.value = run
  loadingSuggestions.value = true
  try {
    const { data } = await axios.get(`/api/v1/manufacturing/mrp/runs/${run.id}/suggestions`)
    suggestions.value = data.data ?? []
  } finally {
    loadingSuggestions.value = false
  }
}

async function accept(sug: MrpSuggestion) {
  await axios.post(`/api/v1/manufacturing/mrp/suggestions/${sug.id}/accept`)
  sug.accepted = true
}

function statusClass(status: string): string {
  if (status === 'completed') return 'badge-success'
  if (status === 'running') return 'badge-info'
  return 'badge-error'
}

function formatDate(d: string): string {
  return new Date(d).toLocaleString()
}
</script>
