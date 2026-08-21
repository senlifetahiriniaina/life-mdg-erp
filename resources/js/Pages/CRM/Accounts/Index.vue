<template>
  <AppLayout>
    <Head :title="$t('crm.accounts.title')" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('crm.accounts.title') }}</h1>
        <p class="wh-page-subtitle">{{ pagination.total }} {{ pagination.total !== 1 ? 'accounts' : 'account' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="clearFilters">
          <i class="pi pi-filter-slash" style="font-size:13px" />
          {{ $t('common.reset_filters') }}
        </button>
        <button class="btn btn-primary" @click="openCreateModal">
          <i class="pi pi-plus" style="font-size:13px" />
          {{ $t('crm.accounts.new') }}
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
            :placeholder="$t('crm.accounts.search_placeholder')"
            class="wh-filter-input"
            @input="onSearchInput"
          />
        </div>
        <Select
          v-model="filters.industry"
          :options="industryOptions"
          option-label="label"
          option-value="value"
          :placeholder="$t('crm.accounts.industry')"
          show-clear
          style="width:200px"
          @change="applyFilters"
        />
      </div>
    </div>

    <!-- Accounts grid -->
    <div class="accounts-grid">
      <div v-for="account in accountsList" :key="account.id" class="account-card" role="article">
        <div class="account-card-header">
          <div class="account-avatar">{{ nameInitials(account.name) }}</div>
          <div style="flex:1;min-width:0">
            <h3 class="account-name">{{ account.name }}</h3>
            <p class="account-industry">{{ account.industry ?? '—' }}</p>
          </div>
        </div>
        <div class="account-stats">
          <div class="stat">
            <span class="stat-value">{{ account.contacts_count ?? 0 }}</span>
            <span class="stat-label">Contacts</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{ account.open_deals ?? 0 }}</span>
            <span class="stat-label">{{ $t('crm.accounts.deals') }}</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{ account.revenue ? '€' + Number(account.revenue).toLocaleString('fr-FR') : '—' }}</span>
            <span class="stat-label">{{ $t('crm.accounts.revenue') }}</span>
          </div>
        </div>
        <div class="account-footer">
          <div style="display:flex;gap:4px;align-items:center;color:var(--fg-3);font-size:12px;flex:1;min-width:0">
            <i v-if="account.website" class="pi pi-globe" style="font-size:11px;flex-shrink:0" />
            <a v-if="account.website" :href="account.website" target="_blank" rel="noopener noreferrer" class="wh-link" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              {{ account.website.replace(/^https?:\/\//, '') }}
            </a>
            <span v-else style="color:var(--fg-4)">—</span>
          </div>
          <div style="display:flex;gap:4px">
            <button class="wh-row-btn" :title="$t('common.edit')" @click="editAccount(account)">
              <i class="pi pi-pencil" style="font-size:13px" />
            </button>
            <button class="wh-row-btn wh-row-btn-danger" :title="$t('common.delete')" @click="confirmDelete(account)">
              <i class="pi pi-trash" style="font-size:13px" />
            </button>
          </div>
        </div>
      </div>

      <!-- Loading state -->
      <div v-if="loading && accountsList.length === 0" class="accounts-grid-empty">
        <i class="pi pi-spin pi-spinner" style="font-size:24px;color:var(--halo-500)" />
      </div>

      <!-- Empty state -->
      <div v-if="!loading && accountsList.length === 0" class="accounts-grid-empty">
        <i class="pi pi-building" style="font-size:32px;opacity:0.4" />
        <p style="font-size:14px;margin:0">{{ $t('common.no_results') }}</p>
        <button class="btn btn-primary" @click="openCreateModal">
          <i class="pi pi-plus" style="font-size:13px" />
          {{ $t('crm.accounts.new') }}
        </button>
      </div>
    </div>

    <!-- Pagination -->
    <div
      v-if="pagination.total > pagination.per_page"
      class="wh-panel"
      style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;margin-top:16px"
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

    <!-- Create/Edit modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingAccount ? $t('common.edit') + ' account' : $t('crm.accounts.new')"
      modal
      style="width:520px"
    >
      <div class="form-grid">
        <div class="form-field" style="grid-column:1/-1">
          <label>{{ $t('common.name') }} *</label>
          <InputText v-model="form.name" class="w-full" :placeholder="$t('common.name')" />
          <span v-if="errors.name" class="field-error">{{ errors.name[0] }}</span>
        </div>
        <div class="form-field">
          <label>{{ $t('crm.accounts.industry') }}</label>
          <Select
            v-model="form.industry"
            :options="industryOptions.filter(o => o.value)"
            option-label="label"
            option-value="value"
            :placeholder="$t('crm.accounts.industry')"
            class="w-full"
          />
        </div>
        <div class="form-field">
          <label>{{ $t('common.phone') }}</label>
          <InputText v-model="form.phone" type="tel" class="w-full" />
        </div>
        <div class="form-field">
          <label>{{ $t('common.email') }}</label>
          <InputText v-model="form.email" type="email" class="w-full" />
          <span v-if="errors.email" class="field-error">{{ errors.email[0] }}</span>
        </div>
        <div class="form-field">
          <label>Website</label>
          <InputText v-model="form.website" type="url" class="w-full" placeholder="https://" />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showModal = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveAccount">
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
  accounts: Object,
  filters:  Object,
})

const confirm  = useConfirm()
const { t }    = useI18n()

const accountsData   = ref(props.accounts?.data ?? [])
const loading        = ref(false)
const showModal      = ref(false)
const saving         = ref(false)
const editingAccount = ref(null)
const errors         = ref({})

const pagination = reactive({
  current_page: props.accounts?.current_page ?? 1,
  per_page:     props.accounts?.per_page     ?? 24,
  total:        props.accounts?.total        ?? 0,
  last_page:    props.accounts?.last_page    ?? 1,
})

const filters = reactive({
  search:   props.filters?.search   ?? '',
  industry: props.filters?.industry ?? null,
})

const form = reactive({ name: '', industry: null, email: '', phone: '', website: '' })

let searchTimer = null

const accountsList = computed(() => accountsData.value)

const industryOptions = computed(() => [
  { label: t('common.all'),   value: null },
  { label: 'Technology',      value: 'technology' },
  { label: 'Finance',         value: 'finance' },
  { label: 'Healthcare',      value: 'healthcare' },
  { label: 'Retail',          value: 'retail' },
  { label: 'Manufacturing',   value: 'manufacturing' },
  { label: 'Real Estate',     value: 'real_estate' },
  { label: 'Education',       value: 'education' },
  { label: 'Services',        value: 'services' },
  { label: 'Other',           value: 'other' },
])

const nameInitials = (name) =>
  name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()

const buildQuery = (page) => {
  const params = {}
  if (page && page > 1) params.page = page
  if (filters.search)   params.search   = filters.search
  if (filters.industry) params.industry = filters.industry
  return params
}

const applyFilters = (page) => {
  loading.value = true
  router.visit('/crm/accounts', {
    data: buildQuery(page),
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      const inertiaAccounts   = page.props.accounts
      accountsData.value          = inertiaAccounts?.data ?? []
      pagination.current_page     = inertiaAccounts?.current_page ?? 1
      pagination.total            = inertiaAccounts?.total        ?? 0
      pagination.last_page        = inertiaAccounts?.last_page    ?? 1
    },
    onFinish: () => { loading.value = false },
  })
}

const onSearchInput   = () => { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(() => applyFilters(1), 400) }
const clearFilters    = () => { filters.search = ''; filters.industry = null; applyFilters(1) }
const onPageChange    = (e) => applyFilters(e.page + 1)
const openCreateModal = () => {
  editingAccount.value = null
  errors.value = {}
  Object.assign(form, { name: '', industry: null, email: '', phone: '', website: '' })
  showModal.value = true
}
const editAccount = (a) => {
  editingAccount.value = a
  errors.value = {}
  Object.assign(form, { name: a.name, industry: a.industry ?? null, email: a.email ?? '', phone: a.phone ?? '', website: a.website ?? '' })
  showModal.value = true
}

// Chantier 32.15: missing-CSRF-token fetch() bug — see Contacts/Form.vue's comment.
function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

const saveAccount = async () => {
  saving.value = true
  errors.value = {}
  try {
    const url    = editingAccount.value ? `/api/v1/crm/accounts/${editingAccount.value.id}` : '/api/v1/crm/accounts'
    const method = editingAccount.value ? 'PUT' : 'POST'
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
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

const confirmDelete = (account) => {
  confirm.require({
    message: `${t('common.delete')} "${account.name}"?`,
    header: `${t('common.delete')} account`,
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/crm/accounts/${account.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() } })
      applyFilters(pagination.current_page)
    },
  })
}

onMounted(() => {
  accountsData.value      = props.accounts?.data ?? []
  pagination.current_page = props.accounts?.current_page ?? 1
  pagination.total        = props.accounts?.total        ?? 0
  pagination.last_page    = props.accounts?.last_page    ?? 1
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

/* Grid */
.accounts-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; }
.account-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px; display:flex; flex-direction:column; gap:12px; transition:box-shadow var(--dur-fast); }
.account-card:hover { box-shadow:0 2px 10px rgba(0,0,0,.07); }
.account-card-header { display:flex; gap:10px; align-items:center; }
.account-avatar { width:36px; height:36px; border-radius:var(--r-md); background:var(--halo-50); color:var(--halo-700); font-size:14px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.account-name { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.account-industry { margin:2px 0 0; font-size:12px; color:var(--fg-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.account-stats { display:flex; gap:0; border:1px solid var(--border-subtle); border-radius:var(--r-md); overflow:hidden; }
.stat { flex:1; text-align:center; padding:8px 4px; border-right:1px solid var(--border-subtle); }
.stat:last-child { border-right:none; }
.stat-value { display:block; font-weight:700; font-size:14px; color:var(--fg-1); font-variant-numeric:tabular-nums; }
.stat-label { font-size:10px; color:var(--fg-4); text-transform:uppercase; letter-spacing:.04em; }
.account-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.accounts-grid-empty { grid-column:1/-1; display:flex; flex-direction:column; align-items:center; gap:12px; padding:48px; color:var(--fg-3); }

/* Modal */
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-row-btn-danger:hover { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--red-500); }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:8px 0; }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-field label { font-size:13px; font-weight:500; color:var(--fg-2); }
.field-error { font-size:12px; color:var(--danger-fg,#dc2626); }
.w-full { width:100%; }
.wh-link { color:var(--fg-link); text-decoration:none; }
.wh-link:hover { text-decoration:underline; text-underline-offset:2px; }
</style>
