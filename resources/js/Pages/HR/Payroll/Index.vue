<template>
  <AppLayout>
    <Head :title="$t('hr.payroll.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('hr.payroll.title') }}</h1>
        <p class="wh-page-subtitle">{{ records.total }} {{ $t('common.total').toLowerCase() }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="resetFilters">
          <i class="pi pi-filter-slash" style="font-size:13px" /> {{ $t('common.reset_filters') }}
        </button>
        <button class="btn btn-secondary" @click="exportPayroll('silae')">
          <i class="pi pi-download" style="font-size:13px" /> Export SILAE
        </button>
        <button class="btn btn-secondary" @click="exportPayroll('dsn')">
          <i class="pi pi-download" style="font-size:13px" /> Export DSN
        </button>
        <button class="btn btn-secondary" @click="exportPayroll('csv')">
          <i class="pi pi-file-excel" style="font-size:13px" /> Export CSV
        </button>
      </div>
    </div>

    <!-- KPI cards -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="card in kpiCards" :key="card.label">
        <div class="wh-kpi-label">{{ card.label }}</div>
        <div class="wh-kpi-num font-display">{{ card.value }}</div>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <InputText
        v-model="filterSearch"
        :placeholder="$t('hr.payroll.search_placeholder')"
        class="filter-input"
        @keyup.enter="applyFilters"
      />
      <Select
        v-model="filterStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        :placeholder="$t('common.all')"
        class="filter-select"
        @change="applyFilters"
      />
      <button class="btn btn-primary" @click="applyFilters">
        <i class="pi pi-search" style="font-size:13px" />
      </button>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('hr.payroll.period') }}</th>
            <th class="num">{{ $t('hr.payroll.gross') }}</th>
            <th class="num">{{ $t('hr.payroll.net') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('hr.payroll.payment_date') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records.data" :key="record.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ record.employee_name }}</td>
            <td style="color:var(--fg-2)">
              <span v-if="record.period_start && record.period_end">
                {{ record.period_start }} – {{ record.period_end }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="num" style="font-variant-numeric:tabular-nums;color:var(--fg-1)">
              {{ formatCurrency(record.gross_salary) }}
            </td>
            <td class="num" style="font-variant-numeric:tabular-nums;color:var(--fg-1)">
              {{ formatCurrency(record.net_salary) }}
            </td>
            <td>
              <span :class="['wh-badge', statusBadgeClass(record.status)]">
                <span class="wh-badge-dot" />
                {{ statusLabel(record.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ record.payment_date ?? '—' }}</td>
            <td>
              <div class="row-actions">
                <button class="action-btn" :title="$t('common.view')">
                  <i class="pi pi-eye" style="font-size:13px" />
                </button>
                <button class="action-btn" :title="$t('common.edit')">
                  <i class="pi pi-pencil" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="records.data.length === 0">
            <td colspan="7" class="empty-state">
              <i class="pi pi-inbox" style="font-size:32px;color:var(--fg-3);display:block;margin-bottom:8px" />
              {{ $t('common.no_results') }}
            </td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ records.total }} {{ $t('common.total').toLowerCase() }}</span>
        <Paginator
          :rows="records.per_page"
          :total-records="records.total"
          :first="(records.current_page - 1) * records.per_page"
          @page="onPage"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Paginator, Select, InputText } from 'primevue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()

const props = defineProps({
  records: { type: Object, required: true },
  stats:   { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const filterSearch = ref(props.filters.search ?? '')
const filterStatus = ref(props.filters.status ?? null)

const statusOptions = computed(() => [
  { label: t('common.all'),  value: null },
  { label: 'Draft',          value: 'draft' },
  { label: 'Approved',       value: 'approved' },
  { label: 'Paid',           value: 'paid' },
])

const kpiCards = computed(() => [
  { label: t('common.total'),  value: props.stats.total },
  { label: 'Draft',            value: props.stats.draft },
  { label: 'Approved',         value: props.stats.approved },
  { label: 'Paid',             value: props.stats.paid },
])

const applyFilters = () => {
  router.get(
    route('hr.payroll.index'),
    { search: filterSearch.value || undefined, status: filterStatus.value || undefined },
    { preserveState: true, replace: true },
  )
}

const resetFilters = () => {
  filterSearch.value = ''
  filterStatus.value = null
  router.get(route('hr.payroll.index'), {}, { preserveState: false })
}

const exportPayroll = (format: string) => {
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth() + 1
  window.location.href = `/api/v1/hr/payroll/export/${format}?year=${year}&month=${month}`
}

const onPage = (event: { page: number }) => {
  router.get(
    route('hr.payroll.index'),
    { search: filterSearch.value || undefined, status: filterStatus.value || undefined, page: event.page + 1 },
    { preserveState: true, replace: true },
  )
}

const formatCurrency = (value: number | string | null | undefined) => {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value))
}

const STATUS_BADGE_CLASSES: Record<string, string> = {
  draft:    'wh-badge-slate',
  approved: 'wh-badge-blue',
  paid:     'wh-badge-green',
}
const STATUS_LABELS: Record<string, string> = {
  draft:    'Draft',
  approved: 'Approved',
  paid:     'Paid',
}

const statusBadgeClass = (status: string) => STATUS_BADGE_CLASSES[status] ?? 'wh-badge-slate'
const statusLabel = (status: string) => STATUS_LABELS[status] ?? status
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }

.filter-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.filter-input { flex:1; min-width:220px; }
.filter-select { width:180px; }

.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
@media (max-width:640px) { .wh-kpi-grid { grid-template-columns:repeat(2,1fr); } }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin-bottom:6px; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }

.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }

.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }

.row-actions { display:flex; gap:4px; }
.action-btn { background:none; border:1px solid var(--border-subtle); border-radius:var(--r-md); padding:4px 8px; cursor:pointer; color:var(--fg-2); display:inline-flex; align-items:center; transition:background var(--dur-fast); }
.action-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }

.empty-state { text-align:center; padding:48px; color:var(--fg-3); font-size:14px; }
</style>
