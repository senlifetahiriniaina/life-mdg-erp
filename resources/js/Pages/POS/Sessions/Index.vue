<template>
  <AppLayout>
    <Head :title="$t('pos.sessions_page.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('pos.sessions_page.title') }}</h1>
        <p class="wh-page-subtitle">{{ sessions.total }} session{{ sessions.total !== 1 ? 's' : '' }}</p>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in kpiList" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar" style="margin-bottom:12px">
      <Select
        v-model="filterStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        :placeholder="$t('common.status')"
        style="min-width:160px"
        @change="applyFilters"
      />
      <button v-if="filterStatus" class="btn btn-ghost" @click="resetFilters">
        <i class="pi pi-times" style="font-size:12px" /> {{ $t('common.reset_filters') }}
      </button>
    </div>

    <!-- Sessions table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('pos.sessions_page.cashier') }}</th>
            <th>{{ $t('pos.sessions_page.config') }}</th>
            <th class="num">{{ $t('pos.sessions_page.opening_balance') }}</th>
            <th class="num">{{ $t('pos.sessions_page.closing_balance') }}</th>
            <th class="num">{{ $t('pos.sessions_page.expected_balance') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>Opened At</th>
            <th>Closed At</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="session in sessions.data"
            :key="session.id"
            class="wh-dt-row"
          >
            <td style="font-weight:500;color:var(--fg-1)">{{ session.cashier_name }}</td>
            <td style="color:var(--fg-2)">{{ session.config_name }}</td>
            <td class="num">{{ formatCurrency(session.opening_balance) }}</td>
            <td class="num">{{ session.closing_balance != null ? formatCurrency(session.closing_balance) : '—' }}</td>
            <td class="num">{{ session.expected_balance != null ? formatCurrency(session.expected_balance) : '—' }}</td>
            <td>
              <span :class="statusBadge(session.status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ statusLabel(session.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ session.opened_at ?? '—' }}</td>
            <td style="color:var(--fg-3)">{{ session.closed_at ?? '—' }}</td>
            <td>
              <a
                :href="`/pos/sessions/${session.id}`"
                class="btn btn-ghost btn-sm"
                @click.prevent="viewSession(session)"
              >
                <i class="pi pi-eye" style="font-size:12px" /> {{ $t('common.view') }}
              </a>
            </td>
          </tr>
          <tr v-if="!sessions.data.length">
            <td colspan="9" class="empty-state">
              <i class="pi pi-inbox" style="font-size:28px;color:var(--fg-4);display:block;margin-bottom:8px" />
              No sessions found.
            </td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ sessions.total }} result{{ sessions.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="sessions.per_page"
          :total-records="sessions.total"
          :first="(sessions.current_page - 1) * sessions.per_page"
          @page="onPage"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Paginator, Select } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  sessions: { type: Object, required: true },
  stats:    { type: Object, required: true },
  filters:  { type: Object, default: () => ({}) },
})

const filterStatus = ref(props.filters.status ?? '')

const statusOptions = [
  { label: 'All',    value: '' },
  { label: 'Open',   value: 'open' },
  { label: 'Closed', value: 'closed' },
]

const kpiList = computed(() => [
  { label: 'Total',  value: props.stats.total },
  { label: 'Open',   value: props.stats.open },
  { label: 'Closed', value: props.stats.closed },
])

const statusBadge = (status) => ({
  open:   'wh-badge-green',
  closed: 'wh-badge-slate',
}[status] ?? 'wh-badge-slate')

const statusLabel = (status) => ({
  open:   'Open',
  closed: 'Closed',
}[status] ?? status)

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value ?? 0))

const applyFilters = () => {
  router.get('/pos/sessions', { status: filterStatus.value || undefined }, { preserveState: true, replace: true })
}

const resetFilters = () => {
  filterStatus.value = ''
  router.get('/pos/sessions', {}, { preserveState: true, replace: true })
}

const onPage = (event) => {
  const page = Math.floor(event.first / event.rows) + 1
  router.get('/pos/sessions', { status: filterStatus.value || undefined, page }, { preserveState: true })
}

const viewSession = (session) => {
  router.visit(`/pos/sessions/${session.id}`)
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; text-decoration:none; }
.btn-ghost { background:transparent; color:var(--fg-2); border-color:var(--border-subtle); }
.btn-ghost:hover { background:var(--bg-sunken); color:var(--fg-1); }
.btn-sm { font-size:12px; padding:4px 10px; }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
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
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.empty-state { text-align:center; color:var(--fg-3); padding:48px 18px; }
</style>
