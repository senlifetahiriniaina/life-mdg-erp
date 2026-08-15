<template>
  <AppLayout>
    <Head :title="$t('accounting.journal_entries.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('accounting.journal_entries.title') }}</h1>
        <p class="wh-page-subtitle">{{ entries.total }} {{ $t('common.total').toLowerCase() }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="resetFilters">
          <i class="pi pi-filter-slash" style="font-size:13px" /> {{ $t('common.reset_filters') }}
        </button>
        <button class="btn btn-primary" @click="showCreateModal = true">
          <i class="pi pi-plus" style="font-size:13px" /> {{ $t('accounting.journal_entries.new') }}
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
      <Select
        v-model="filterJournal"
        :options="journalOptions"
        option-label="label"
        option-value="value"
        :placeholder="$t('accounting.journal_entries.all_journals')"
        class="filter-select"
        @change="applyFilters"
      />
      <Select
        v-model="filterStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        :placeholder="$t('common.all_statuses')"
        class="filter-select"
        @change="applyFilters"
      />
      <InputText
        v-model="filterDateFrom"
        type="date"
        class="filter-input"
        style="width:160px"
        @change="applyFilters"
      />
      <span style="align-self:center;color:var(--fg-3);font-size:13px">→</span>
      <InputText
        v-model="filterDateTo"
        type="date"
        class="filter-input"
        style="width:160px"
        @change="applyFilters"
      />
      <button class="btn btn-secondary" @click="resetFilters">
        <i class="pi pi-times" style="font-size:12px" />
      </button>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('accounting.journal_entries.reference') }}</th>
            <th>{{ $t('accounting.journal_entries.journal') }}</th>
            <th>{{ $t('accounting.journal_entries.date') }}</th>
            <th>{{ $t('accounting.journal_entries.description') }}</th>
            <th class="num">{{ $t('accounting.journal_entries.total_debit') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('accounting.journal_entries.created_by') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in entries.data" :key="entry.id" class="wh-dt-row">
            <td style="font-weight:600;font-family:var(--font-mono);font-size:13px;color:var(--halo-600)">
              {{ entry.reference ?? '—' }}
            </td>
            <td>
              <span v-if="entry.journal" class="journal-tag">
                {{ entry.journal.code }}
              </span>
              <span v-else style="color:var(--fg-3)">—</span>
            </td>
            <td style="color:var(--fg-2)">{{ entry.entry_date ?? '—' }}</td>
            <td style="color:var(--fg-2);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              {{ entry.description ?? '—' }}
            </td>
            <td class="num" style="font-variant-numeric:tabular-nums;font-weight:500">
              {{ formatCurrency(entry.total_debit) }}
            </td>
            <td>
              <span :class="['wh-badge', statusBadgeClass(entry.status)]">
                <span class="wh-badge-dot" />
                {{ statusLabel(entry.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ entry.created_by ?? '—' }}</td>
            <td>
              <div class="row-actions">
                <button class="action-btn" :title="$t('common.view')">
                  <i class="pi pi-eye" style="font-size:13px" />
                </button>
                <button
                  v-if="entry.status === 'draft'"
                  class="action-btn"
                  :title="$t('common.edit')"
                >
                  <i class="pi pi-pencil" style="font-size:13px" />
                </button>
                <button
                  v-if="entry.status === 'draft'"
                  class="action-btn action-btn-danger"
                  :title="$t('common.delete')"
                  @click="confirmDelete(entry)"
                >
                  <i class="pi pi-trash" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="entries.data.length === 0">
            <td colspan="8" class="empty-state">
              <i class="pi pi-book" style="font-size:32px;color:var(--fg-3);display:block;margin-bottom:8px" />
              {{ $t('common.no_results') }}
            </td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ entries.total }} {{ $t('common.total').toLowerCase() }}</span>
        <Paginator
          :rows="entries.per_page"
          :total-records="entries.total"
          :first="(entries.current_page - 1) * entries.per_page"
          @page="onPage"
        />
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog
      v-model:visible="showCreateModal"
      modal
      :header="$t('accounting.journal_entries.new')"
      style="width:560px"
    >
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('accounting.journal_entries.journal') }}</label>
          <Select
            v-model="form.journal_id"
            :options="journals"
            option-label="name"
            option-value="id"
            :placeholder="$t('accounting.journal_entries.select_journal')"
            class="w-full"
          />
          <span v-if="errors.journal_id" class="field-error">{{ errors.journal_id[0] }}</span>
        </div>
        <div class="form-field">
          <label>{{ $t('accounting.journal_entries.date') }}</label>
          <InputText v-model="form.entry_date" type="date" class="w-full" />
          <span v-if="errors.entry_date" class="field-error">{{ errors.entry_date[0] }}</span>
        </div>
        <div class="form-field" style="grid-column:1/-1">
          <label>{{ $t('accounting.journal_entries.description') }}</label>
          <Textarea v-model="form.description" rows="3" class="w-full" />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showCreateModal = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveEntry">
          <i v-if="saving" class="pi pi-spin pi-spinner" style="font-size:13px" />
          {{ $t('common.save') }}
        </button>
      </template>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Select, InputText, Paginator, Dialog, Textarea, ConfirmDialog } from 'primevue'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()
const confirm = useConfirm()

const props = defineProps({
  entries:  { type: Object, required: true },
  journals: { type: Array,  default: () => [] },
  stats:    { type: Object, required: true },
  filters:  { type: Object, default: () => ({}) },
})

const filterJournal  = ref(props.filters.journal_id ?? null)
const filterStatus   = ref(props.filters.status ?? null)
const filterDateFrom = ref(props.filters.date_from ?? '')
const filterDateTo   = ref(props.filters.date_to ?? '')

const showCreateModal = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = reactive({
  journal_id:  null as number | null,
  entry_date:  new Date().toISOString().slice(0, 10),
  description: '',
})

const journalOptions = computed(() => [
  { label: t('accounting.journal_entries.all_journals'), value: null },
  ...(props.journals as Array<{ id: number; code: string; name: string }>).map(j => ({
    label: `${j.code} — ${j.name}`,
    value: j.id,
  })),
])

const statusOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: 'Draft',          value: 'draft' },
  { label: 'Posted',         value: 'posted' },
  { label: 'Cancelled',      value: 'cancelled' },
])

const kpiCards = computed(() => [
  { label: t('common.total'), value: props.stats.total },
  { label: 'Draft',           value: props.stats.draft },
  { label: 'Posted',          value: props.stats.posted },
])

const applyFilters = () => {
  router.get(
    route('accounting.journal-entries.index'),
    {
      journal_id: filterJournal.value  || undefined,
      status:     filterStatus.value   || undefined,
      date_from:  filterDateFrom.value || undefined,
      date_to:    filterDateTo.value   || undefined,
    },
    { preserveState: true, replace: true },
  )
}

const resetFilters = () => {
  filterJournal.value  = null
  filterStatus.value   = null
  filterDateFrom.value = ''
  filterDateTo.value   = ''
  router.get(route('accounting.journal-entries.index'), {}, { preserveState: false })
}

const onPage = (event: { page: number }) => {
  router.get(
    route('accounting.journal-entries.index'),
    {
      journal_id: filterJournal.value  || undefined,
      status:     filterStatus.value   || undefined,
      date_from:  filterDateFrom.value || undefined,
      date_to:    filterDateTo.value   || undefined,
      page: event.page + 1,
    },
    { preserveState: true, replace: true },
  )
}

const saveEntry = async () => {
  saving.value = true
  errors.value = {}
  try {
    const response = await fetch('/api/v1/accounting/journal-entries', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(form),
    })
    if (!response.ok) {
      const data = await response.json()
      if (data.errors) errors.value = data.errors
      return
    }
    showCreateModal.value = false
    router.reload({ only: ['entries', 'stats'] })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (entry: { id: number; reference?: string }) => {
  confirm.require({
    message: t('common.confirm_delete_message', { name: entry.reference ?? `#${entry.id}` }),
    header: t('common.confirm_delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/accounting/journal-entries/${entry.id}`, { method: 'DELETE' })
      router.reload({ only: ['entries', 'stats'] })
    },
  })
}

const formatCurrency = (value: number | null) => {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(value))
}

const statusBadgeClass = (status: string) => ({
  draft:     'wh-badge-slate',
  posted:    'wh-badge-green',
  cancelled: 'wh-badge-red',
}[status] ?? 'wh-badge-slate')

const statusLabel = (status: string) => ({
  draft:     'Brouillon',
  posted:    'Comptabilisé',
  cancelled: 'Annulé',
}[status] ?? status)
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn:disabled { opacity:.5; cursor:not-allowed; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }

.filter-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.filter-input { flex:1; min-width:140px; }
.filter-select { width:220px; }

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

.journal-tag { display:inline-flex; align-items:center; padding:2px 8px; border-radius:var(--r-md); background:var(--halo-50); color:var(--halo-700); font-size:12px; font-weight:600; font-family:var(--font-mono); letter-spacing:0.04em; }

.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-red    { background:var(--danger-bg,#fef2f2); color:var(--danger-fg,#dc2626); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }

.row-actions { display:flex; gap:4px; }
.action-btn { background:none; border:1px solid var(--border-subtle); border-radius:var(--r-md); padding:4px 8px; cursor:pointer; color:var(--fg-2); display:inline-flex; align-items:center; transition:background var(--dur-fast); }
.action-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }
.action-btn-danger:hover { background:var(--danger-bg,#fef2f2); color:var(--danger-fg,#dc2626); border-color:var(--danger-fg,#dc2626); }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:8px 0; }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-field label { font-size:13px; font-weight:500; color:var(--fg-2); }
.field-error { font-size:12px; color:var(--danger-fg,#dc2626); }
.w-full { width:100%; }

.empty-state { text-align:center; padding:48px; color:var(--fg-3); font-size:14px; }
</style>
