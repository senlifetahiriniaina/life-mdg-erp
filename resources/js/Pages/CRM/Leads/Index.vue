<template>
  <AppLayout>
    <Head :title="$t('crm.leads.title')" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('crm.leads.title') }}</h1>
        <p class="wh-page-subtitle">{{ pagination.total }} {{ pagination.total !== 1 ? 'leads' : 'lead' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="toggleView">
          <i :class="viewMode === 'kanban' ? 'pi pi-list' : 'pi pi-th-large'" style="font-size:13px" />
          {{ viewMode === 'kanban' ? $t('common.list') : $t('crm.leads.pipeline') }}
        </button>
        <button class="btn btn-secondary" @click="clearFilters">
          <i class="pi pi-filter-slash" style="font-size:13px" />
          {{ $t('common.reset_filters') }}
        </button>
        <button class="btn btn-primary" @click="openCreateModal">
          <i class="pi pi-plus" style="font-size:13px" />
          {{ $t('crm.leads.new') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px;overflow:visible">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <div style="position:relative;flex:1;min-width:200px;max-width:320px">
          <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
          <input
            v-model="filters.search"
            :placeholder="$t('crm.leads.search_placeholder')"
            class="wh-filter-input"
            @input="onSearchInput"
          />
        </div>
        <Select
          v-model="filters.source"
          :options="sourceOptions"
          option-label="label"
          option-value="value"
          :placeholder="$t('crm.leads.source')"
          show-clear
          style="width:180px"
          @change="applyFilters"
        />
        <Select
          v-model="filters.score"
          :options="scoreFilterOptions"
          option-label="label"
          option-value="value"
          :placeholder="$t('crm.leads.all_scores')"
          show-clear
          style="width:160px"
          @change="applyFilters"
        />
      </div>
    </div>

    <!-- Kanban view -->
    <div v-if="viewMode === 'kanban'" class="leads-kanban">
      <div v-for="stage in pipeline" :key="stage.key" class="kanban-column">
        <div class="kanban-col-header">
          <span class="kanban-col-title">{{ stage.label }}</span>
          <span class="kanban-col-count">{{ stageLeads(stage.key).length }}</span>
        </div>
        <div class="kanban-cards">
          <div
            v-for="lead in stageLeads(stage.key)"
            :key="lead.id"
            class="lead-card"
            role="article"
            :aria-label="lead.title"
          >
            <div class="lead-card-header">
              <span class="lead-name">{{ lead.title }}</span>
              <span v-if="lead.score !== null" :class="['lead-score-badge', lead.score >= 70 ? 'hot' : lead.score >= 40 ? 'warm' : 'cold']">
                {{ lead.score }}
              </span>
            </div>
            <p class="lead-company">{{ lead.contact_name ?? '—' }}</p>
            <div class="lead-meta">
              <span v-if="lead.source"><i class="pi pi-tag" /> {{ lead.source }}</span>
              <span v-if="lead.owner"><i class="pi pi-user" /> {{ lead.owner.name }}</span>
            </div>
            <div class="lead-card-actions">
              <button class="wh-row-btn" :title="$t('common.edit')" @click="editLead(lead)">
                <i class="pi pi-pencil" style="font-size:13px" />
              </button>
              <button class="wh-row-btn wh-row-btn-danger" :title="$t('common.delete')" @click="confirmDelete(lead)">
                <i class="pi pi-trash" style="font-size:13px" />
              </button>
            </div>
          </div>
          <div v-if="stageLeads(stage.key).length === 0" class="kanban-empty">
            <p>{{ $t('common.no_results') }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- List view -->
    <div v-else class="wh-panel" style="position:relative">
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('crm.source') }}</th>
            <th>Score</th>
            <th>{{ $t('common.contact') }}</th>
            <th>Owner</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="lead in leadsList" :key="lead.id" class="wh-dt-row">
            <td>
              <span style="font-weight:500;color:var(--fg-1)">{{ lead.title }}</span>
            </td>
            <td>
              <span :class="['wh-badge', statusClass(lead.status)]">
                <span class="wh-badge-dot" />
                {{ lead.status }}
              </span>
            </td>
            <td style="color:var(--fg-2)">{{ lead.source ?? '—' }}</td>
            <td>
              <span v-if="lead.score !== null" :class="['score-badge', scoreClass(lead.score)]">
                {{ lead.score }}
              </span>
              <span v-else class="wh-null">—</span>
            </td>
            <td style="color:var(--fg-2)">{{ lead.contact_name ?? '—' }}</td>
            <td style="color:var(--fg-2)">{{ lead.owner?.name ?? '—' }}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="wh-row-btn" title="Edit" @click="editLead(lead)">
                  <i class="pi pi-pencil" style="font-size:13px" />
                </button>
                <button class="wh-row-btn wh-row-btn-danger" title="Delete" @click="confirmDelete(lead)">
                  <i class="pi pi-trash" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && leadsList.length === 0">
            <td colspan="7" style="text-align:center;padding:64px 18px">
              <div style="display:flex;flex-direction:column;align-items:center;gap:12px;color:var(--fg-3)">
                <i class="pi pi-inbox" style="font-size:32px;opacity:0.4" />
                <span style="font-size:14px">{{ $t('common.no_results') }}</span>
                <button class="btn btn-primary" style="margin-top:4px" @click="openCreateModal">
                  <i class="pi pi-plus" style="font-size:13px" />
                  {{ $t('crm.leads.new') }}
                </button>
              </div>
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
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (pagination.current_page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
          of {{ pagination.total }}
        </span>
        <Paginator
          :rows="pagination.per_page"
          :total-records="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          @page="onPageChange"
        />
      </div>
    </div>

    <!-- Create/Edit modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingLead ? $t('common.edit') + ' lead' : $t('crm.leads.new')"
      modal
      style="width:520px"
    >
      <div class="form-grid">
        <div class="form-field" style="grid-column:1/-1">
          <label>{{ $t('common.name') }} *</label>
          <InputText v-model="form.title" class="w-full" :placeholder="$t('common.name')" />
          <span v-if="errors.title" class="field-error">{{ errors.title[0] }}</span>
        </div>
        <div class="form-field">
          <label>{{ $t('crm.leads.company') }}</label>
          <InputText v-model="form.company" class="w-full" />
        </div>
        <div class="form-field">
          <label>{{ $t('common.email') }}</label>
          <InputText v-model="form.email" type="email" class="w-full" />
        </div>
        <div class="form-field">
          <label>{{ $t('crm.leads.source') }}</label>
          <Select v-model="form.source" :options="sources" class="w-full" />
        </div>
        <div class="form-field">
          <label>{{ $t('common.status') }}</label>
          <Select
            v-model="form.status"
            :options="pipeline.map(s => ({ label: s.label, value: s.key }))"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showModal = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveLead">
          <i v-if="saving" class="pi pi-spin pi-spinner" style="font-size:13px" />
          {{ $t('common.save') }}
        </button>
      </template>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import InputText from 'primevue/inputtext'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  leads: Object,
  filters: Object,
})

const confirm = useConfirm()
const { t }   = useI18n()

const leadsData   = ref(props.leads?.data ?? [])
const loading     = ref(false)
const showModal   = ref(false)
const saving      = ref(false)
const editingLead = ref(null)
const errors      = ref({})
const viewMode    = ref('kanban')

const pagination = reactive({
  current_page: props.leads?.current_page ?? 1,
  per_page:     props.leads?.per_page     ?? 25,
  total:        props.leads?.total        ?? 0,
  last_page:    props.leads?.last_page    ?? 1,
})

const filters = reactive({
  search: props.filters?.search ?? '',
  source: props.filters?.source ?? null,
  score:  props.filters?.score  ?? null,
})

const form = reactive({ title: '', company: '', email: '', source: 'website', status: 'new' })

let searchTimer = null

const sources = ['website', 'referral', 'cold_call', 'social_media', 'event', 'other']

const pipeline = [
  { key: 'new',       label: 'New' },
  { key: 'contacted', label: 'Contacted' },
  { key: 'qualified', label: 'Qualified' },
  { key: 'proposal',  label: 'Proposal' },
  { key: 'won',       label: 'Won' },
]

const sourceOptions = computed(() => [
  { label: t('common.all'), value: null },
  ...sources.map(s => ({ label: s, value: s })),
])

const scoreFilterOptions = computed(() => [
  { label: t('crm.leads.all_scores'), value: null },
  { label: t('crm.leads.hot'),  value: 'hot' },
  { label: t('crm.leads.warm'), value: 'warm' },
  { label: t('crm.leads.cold'), value: 'cold' },
])

const leadsList = computed(() => {
  let list = leadsData.value
  if (filters.score === 'hot')  list = list.filter(l => l.score >= 70)
  if (filters.score === 'warm') list = list.filter(l => l.score >= 40 && l.score < 70)
  if (filters.score === 'cold') list = list.filter(l => l.score < 40)
  return list
})

const stageLeads = (stage) => leadsList.value.filter(l => l.status === stage)

const statusClass = (status) => {
  switch (status) {
    case 'new':       return 'wh-badge-blue'
    case 'contacted': return 'wh-badge-yellow'
    case 'qualified': return 'wh-badge-green'
    case 'won':       return 'wh-badge-green'
    case 'lost':      return 'wh-badge-slate'
    default:          return 'wh-badge-slate'
  }
}

const scoreClass = (score) => {
  if (score >= 70) return 'score-green'
  if (score >= 40) return 'score-yellow'
  return 'score-red'
}

const toggleView = () => { viewMode.value = viewMode.value === 'kanban' ? 'list' : 'kanban' }

const buildQuery = (page) => {
  const params = {}
  if (page && page > 1) params.page = page
  if (filters.search) params.search = filters.search
  if (filters.source) params.source = filters.source
  return params
}

const applyFilters = (page) => {
  loading.value = true
  router.visit('/crm/leads', {
    data: buildQuery(page),
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      const inertiaLeads = page.props.leads
      leadsData.value          = inertiaLeads?.data ?? []
      pagination.current_page  = inertiaLeads?.current_page ?? 1
      pagination.total         = inertiaLeads?.total        ?? 0
      pagination.last_page     = inertiaLeads?.last_page    ?? 1
    },
    onFinish: () => { loading.value = false },
  })
}

const onSearchInput   = () => { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(() => applyFilters(1), 400) }
const clearFilters    = () => { filters.search = ''; filters.source = null; filters.score = null; applyFilters(1) }
const onPageChange    = (e) => applyFilters(e.page + 1)
const openCreateModal = () => {
  editingLead.value = null
  errors.value = {}
  Object.assign(form, { title: '', company: '', email: '', source: 'website', status: 'new' })
  showModal.value = true
}
const editLead = (l) => {
  editingLead.value = l
  errors.value = {}
  Object.assign(form, { title: l.title, company: l.company ?? '', email: l.email ?? '', source: l.source ?? 'website', status: l.status ?? 'new' })
  showModal.value = true
}

const saveLead = async () => {
  saving.value = true
  errors.value = {}
  try {
    const url    = editingLead.value ? `/api/v1/crm/leads/${editingLead.value.id}` : '/api/v1/crm/leads'
    const method = editingLead.value ? 'PUT' : 'POST'
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(form),
    })
    if (!res.ok) {
      const data = await res.json()
      if (data.errors) errors.value = data.errors
      return
    }
    showModal.value = false
    applyFilters(pagination.current_page)
  } finally {
    saving.value = false
  }
}

const confirmDelete = (lead) => {
  confirm.require({
    message: `${t('common.delete')} "${lead.title}"?`,
    header: `${t('common.delete')} lead`,
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/crm/leads/${lead.id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
      applyFilters(pagination.current_page)
    },
  })
}

onMounted(() => {
  leadsData.value         = props.leads?.data ?? []
  pagination.current_page = props.leads?.current_page ?? 1
  pagination.total        = props.leads?.total        ?? 0
  pagination.last_page    = props.leads?.last_page    ?? 1
})
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out), border-color var(--dur-base); line-height:1.2; }
.btn:disabled { opacity:.5; cursor:not-allowed; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-filter-input { width:100%; padding:7px 12px 7px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; transition:border-color var(--dur-base), box-shadow var(--dur-base); }
.wh-filter-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.wh-filter-input::placeholder { color:var(--fg-4); }

/* Kanban */
.leads-kanban { display:flex; gap:12px; overflow-x:auto; padding-bottom:12px; min-height:60vh; }
.kanban-column { min-width:220px; flex:0 0 220px; background:var(--bg-sunken); border-radius:var(--r-lg); padding:10px; }
.kanban-col-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.kanban-col-title { font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--fg-3); }
.kanban-col-count { background:var(--border-subtle); border-radius:var(--r-pill); padding:2px 8px; font-size:12px; color:var(--fg-2); font-weight:500; }
.kanban-cards { display:flex; flex-direction:column; gap:8px; }
.lead-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-md); padding:10px 12px; cursor:pointer; transition:box-shadow var(--dur-fast); }
.lead-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
.lead-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; gap:6px; }
.lead-name { font-weight:600; font-size:13px; color:var(--fg-1); flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lead-score-badge { font-size:11px; font-weight:700; padding:2px 6px; border-radius:var(--r-pill); flex-shrink:0; }
.lead-score-badge.hot  { background:var(--danger-bg,#fee2e2); color:var(--danger-fg,#991b1b); }
.lead-score-badge.warm { background:var(--warning-bg,#fef9c3); color:var(--warning-fg,#854d0e); }
.lead-score-badge.cold { background:var(--halo-50); color:var(--halo-700); }
.lead-company { font-size:12px; color:var(--fg-3); margin:0 0 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lead-meta { display:flex; gap:8px; font-size:11px; color:var(--fg-4); flex-wrap:wrap; }
.lead-meta i { font-size:10px; }
.lead-card-actions { margin-top:8px; display:flex; gap:4px; }
.kanban-empty { text-align:center; color:var(--fg-4); font-size:12px; padding:12px 0; }
.kanban-empty p { margin:0; }

/* Table */
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); color:var(--fg-1); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-dt-loading { opacity:0.5; pointer-events:none; }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-row-btn-danger:hover { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--red-500); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; text-transform:capitalize; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-yellow { background:var(--warning-bg,#fef9c3); color:var(--warning-fg,#854d0e); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.score-badge { display:inline-block; padding:2px 8px; border-radius:var(--r-pill); font-size:12px; font-weight:600; font-variant-numeric:tabular-nums; }
.score-green  { background:var(--success-bg); color:var(--success-fg); }
.score-yellow { background:var(--warning-bg,#fef9c3); color:var(--warning-fg,#854d0e); }
.score-red    { background:var(--danger-bg,#fee2e2); color:var(--danger-fg,#991b1b); }
.wh-null { color:var(--fg-4); }

/* Form */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:8px 0; }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-field label { font-size:13px; font-weight:500; color:var(--fg-2); }
.field-error { font-size:12px; color:var(--danger-fg,#dc2626); }
.w-full { width:100%; }
</style>
