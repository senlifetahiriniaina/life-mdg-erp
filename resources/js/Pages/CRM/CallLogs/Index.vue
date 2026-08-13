<template>
  <AppLayout>
    <Head :title="$t('crm.voip.call_logs')" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('crm.voip.call_logs') }}</h1>
        <p class="wh-page-subtitle">{{ pagination.total }} {{ $t('crm.voip.call_logs').toLowerCase() }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px;overflow:visible">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <Select
          v-model="filters.direction"
          :options="directionOptions"
          option-label="label"
          option-value="value"
          :placeholder="$t('crm.voip.direction')"
          show-clear
          style="width:160px"
          @change="fetchLogs(1)"
        />
        <Select
          v-model="filters.status"
          :options="callStatusOptions"
          option-label="label"
          option-value="value"
          :placeholder="$t('common.status')"
          show-clear
          style="width:160px"
          @change="fetchLogs(1)"
        />
        <input
          v-model="filters.date_from"
          type="date"
          class="wh-filter-input"
          style="width:160px"
          @change="fetchLogs(1)"
        />
        <input
          v-model="filters.date_to"
          type="date"
          class="wh-filter-input"
          style="width:160px"
          @change="fetchLogs(1)"
        />
        <button class="btn btn-secondary" @click="clearFilters">
          <i class="pi pi-filter-slash" style="font-size:13px" />
          {{ $t('common.reset_filters') }}
        </button>
      </div>
    </div>

    <!-- Data table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th>{{ $t('common.contact') }}</th>
            <th>{{ $t('common.phone') }}</th>
            <th>{{ $t('crm.voip.direction') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('crm.voip.duration') }}</th>
            <th>{{ $t('crm.voip.agent') }}</th>
            <th>{{ $t('common.date') }}</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id" class="wh-dt-row">
            <td>
              <span style="font-weight:500">{{ log.contact?.full_name ?? '—' }}</span>
            </td>
            <td style="font-variant-numeric:tabular-nums">{{ log.phone_number }}</td>
            <td>
              <span :class="['wh-badge', log.direction === 'inbound' ? 'wh-badge-blue' : 'wh-badge-slate']">
                <i :class="['pi', log.direction === 'inbound' ? 'pi-arrow-down-left' : 'pi-arrow-up-right']" style="font-size:10px" />
                {{ $t('crm.voip.' + log.direction) }}
              </span>
            </td>
            <td>
              <span :class="['wh-badge', callStatusClass(log.status)]">
                <span class="wh-badge-dot" />
                {{ $t('crm.voip.status_' + log.status) }}
              </span>
            </td>
            <td style="color:var(--fg-2)">{{ formatDuration(log.duration_seconds) }}</td>
            <td style="color:var(--fg-2)">{{ log.user?.name ?? '—' }}</td>
            <td style="color:var(--fg-3);font-size:13px">{{ formatDate(log.called_at) }}</td>
            <td>
              <a
                v-if="log.recording_url"
                :href="log.recording_url"
                target="_blank"
                class="wh-row-btn"
                :title="$t('crm.voip.play_recording')"
              >
                <i class="pi pi-play" style="font-size:13px" />
              </a>
            </td>
          </tr>
          <tr v-if="!loading && logs.length === 0">
            <td colspan="8" style="text-align:center;padding:48px 18px;color:var(--fg-3);font-size:14px">
              {{ $t('common.no_results') }}
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(247,248,251,0.6)">
        <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination.total > pagination.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">{{ $t('common.of') }} {{ pagination.total }}</span>
        <Paginator
          :rows="pagination.per_page"
          :total-records="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          @page="(e) => fetchLogs(e.page + 1)"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const logs = ref([])
const loading = ref(false)
const pagination = reactive({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
const filters = reactive({ direction: null, status: null, date_from: '', date_to: '' })

const directionOptions = computed(() => [
  { label: t('crm.voip.inbound'),  value: 'inbound' },
  { label: t('crm.voip.outbound'), value: 'outbound' },
])

const callStatusOptions = computed(() => [
  { label: t('crm.voip.status_initiated'), value: 'initiated' },
  { label: t('crm.voip.status_ringing'),   value: 'ringing' },
  { label: t('crm.voip.status_answered'),  value: 'answered' },
  { label: t('crm.voip.status_missed'),    value: 'missed' },
  { label: t('crm.voip.status_failed'),    value: 'failed' },
])

const callStatusClass = (status) => {
  switch (status) {
    case 'answered':  return 'wh-badge-green'
    case 'missed':    return 'wh-badge-yellow'
    case 'failed':    return 'wh-badge-red'
    default:          return 'wh-badge-slate'
  }
}

const formatDuration = (seconds) => {
  if (!seconds) return '—'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

const formatDate = (isoStr) => {
  if (!isoStr) return '—'
  return new Date(isoStr).toLocaleString()
}

const fetchLogs = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams({ page: String(page) })
    if (filters.direction) params.set('direction', filters.direction)
    if (filters.status) params.set('status', filters.status)
    if (filters.date_from) params.set('date_from', filters.date_from)
    if (filters.date_to) params.set('date_to', filters.date_to)
    const res = await fetch(`/api/v1/crm/voip/call-logs?${params}`, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    logs.value = data.data
    pagination.current_page = data.current_page
    pagination.total = data.total
    pagination.last_page = data.last_page
  } finally { loading.value = false }
}

const clearFilters = () => {
  filters.direction = null
  filters.status = null
  filters.date_from = ''
  filters.date_to = ''
  fetchLogs(1)
}

onMounted(() => fetchLogs())
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-filter-input { padding:7px 12px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; }
.wh-filter-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); color:var(--fg-1); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-dt-loading { opacity:0.5; pointer-events:none; }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-yellow { background:#fef9c3; color:#854d0e; }
.wh-badge-red    { background:#fee2e2; color:#991b1b; }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.wh-panel { background:var(--bg-canvas); border-radius:var(--r-lg); border:1px solid var(--border-subtle); }
</style>
