<template>
  <AppLayout>
    <Head :title="$t('crm.contacts.title')" />

    <!-- Welcome banner (first visit) -->
    <WelcomeBanner
      dismiss-key="crm:contacts:welcome"
      icon="pi pi-users"
      :title="$t('crm.contacts.welcome_title')"
      :description="$t('crm.contacts.welcome_desc')"
      :actions="[
        { label: $t('crm.contacts.create_first'), icon: 'pi pi-plus', primary: true, onClick: openCreateModal },
        { label: $t('common.take_tour'), icon: 'pi pi-play', onClick: () => help.startTour('crm-contacts') },
        { label: $t('common.learn_more'), icon: 'pi pi-book', onClick: () => help.dismiss('crm:contacts:welcome') },
      ]"
    />

    <GuidedTour tour-id="crm-contacts" :steps="crmContactsTourSteps" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <HelpTooltip text="Les contacts sont les personnes et organisations avec qui vous faites des affaires. Filtrez par statut, type ou propriétaire.">
          <h1 class="wh-page-title">{{ $t('crm.contacts.title') }}</h1>
        </HelpTooltip>
        <p class="wh-page-subtitle">{{ pagination.total }} {{ pagination.total !== 1 ? 'contacts' : $t('common.contact').toLowerCase() }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="clearFilters">
          <i class="pi pi-filter-slash" style="font-size:13px" />
          {{ $t('common.reset_filters') }}
        </button>
        <button class="btn btn-primary" @click="openCreateModal">
          <i class="pi pi-plus" style="font-size:13px" />
          {{ $t('crm.contacts.new') }}
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
            :placeholder="$t('crm.contacts.search_placeholder')"
            class="wh-filter-input"
            @input="onSearchInput"
          />
        </div>
        <Select
          v-model="filters.status"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          :placeholder="$t('common.status')"
          show-clear
          style="width:160px"
          @change="applyFilters"
        />
      </div>
    </div>

    <!-- Data table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th>{{ $t('common.contact') }}</th>
            <th>{{ $t('common.email') }}</th>
            <th>{{ $t('common.phone') }}</th>
            <th>{{ $t('crm.job_title') }}</th>
            <th>{{ $t('crm.account') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="contact in contacts" :key="contact.id" class="wh-dt-row">
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="contact-avatar">{{ nameInitials(contact.full_name) }}</div>
                <span style="font-weight:500;color:var(--fg-1)">{{ contact.full_name }}</span>
              </div>
            </td>
            <td>
              <a v-if="contact.email" :href="`mailto:${contact.email}`" class="wh-link">{{ contact.email }}</a>
              <span v-else class="wh-null">—</span>
            </td>
            <td style="font-variant-numeric:tabular-nums">{{ contact.phone ?? '—' }}</td>
            <td style="color:var(--fg-2)">{{ contact.job_title ?? '—' }}</td>
            <td style="color:var(--fg-2)">{{ contact.account?.name ?? '—' }}</td>
            <td>
              <span :class="['wh-badge', statusClass(contact.status)]">
                <span class="wh-badge-dot" />
                {{ contact.status }}
              </span>
            </td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="wh-row-btn" title="Modifier" @click="editContact(contact)">
                  <i class="pi pi-pencil" style="font-size:13px" />
                </button>
                <button class="wh-row-btn wh-row-btn-danger" title="Supprimer" @click="confirmDelete(contact)">
                  <i class="pi pi-trash" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && contacts.length === 0">
            <td colspan="7" style="text-align:center;padding:48px 18px;color:var(--fg-3);font-size:14px">
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
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (pagination.current_page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
          sur {{ pagination.total }}
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
      :header="editingContact ? $t('common.edit') + ' ' + $t('common.contact').toLowerCase() : $t('crm.contacts.new')"
      modal
      class="w-full max-w-2xl"
    >
      <ContactForm
        :contact="editingContact"
        :accounts="accounts"
        @saved="onContactSaved"
        @cancel="showModal = false"
      />
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContactForm from './Form.vue'
import WelcomeBanner from '@/Components/UI/WelcomeBanner.vue'
import HelpTooltip from '@/Components/UI/HelpTooltip.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import { useHelpStore } from '@/stores/help'
import { useI18n } from 'vue-i18n'

interface Account  { id: number; name: string }
interface Contact  {
  id: number; full_name: string; first_name: string; last_name: string
  email: string | null; phone: string | null; mobile: string | null
  job_title: string | null; department: string | null; source: string | null
  status: string; account: Account | null
}
interface Pagination { current_page: number; per_page: number; total: number; last_page: number }

const confirm = useConfirm()
const help    = useHelpStore()
const { t }   = useI18n()

const crmContactsTourSteps = [
  { tag: 'CRM', icon: 'pi pi-users',       title: 'Contact List',   description: 'All your contacts — customers, leads and partners — are here. Use filters to narrow down by status, type or owner.' },
  { tag: 'CRM', icon: 'pi pi-filter',      title: 'Smart Filters',  description: 'Filter by status (active / inactive / prospect), contact type or account owner. Combine filters for precise results.' },
  { tag: 'CRM', icon: 'pi pi-plus-circle', title: 'Create Contact', description: 'Click "New Contact" to add a person or organisation. Link them to an account and assign an owner right away.' },
  { tag: 'CRM', icon: 'pi pi-trash',       title: 'Bulk Actions',   description: 'Select multiple contacts with the checkboxes and use bulk actions to reassign, tag or delete them at once.' },
  { tag: 'CRM', icon: 'pi pi-sparkles',    title: 'AI Lead Scoring', description: 'The AI analyses interaction history, company size and engagement to score leads automatically — no manual work needed.' },
]

const contacts       = ref<Contact[]>([])
const accounts       = ref<Account[]>([])
const loading        = ref(false)
const showModal      = ref(false)
const editingContact = ref<Contact | null>(null)

const pagination = reactive<Pagination>({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
const filters    = reactive({ search: '', status: null as string | null })

let searchTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = computed(() => [
  { label: t('common.active'),   value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
  { label: t('crm.prospect'),    value: 'prospect' },
])

const nameInitials = (name: string) =>
  name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()

const statusClass = (status: string) => {
  switch (status) {
    case 'active':   return 'wh-badge-green'
    case 'inactive': return 'wh-badge-slate'
    case 'prospect': return 'wh-badge-blue'
    default:         return 'wh-badge-slate'
  }
}

const fetchContacts = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(pagination.per_page))
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    const res  = await fetch(`/api/v1/crm/contacts?${params}`, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    contacts.value          = data.data
    pagination.current_page = data.current_page
    pagination.total        = data.total
    pagination.last_page    = data.last_page
  } finally { loading.value = false }
}

const fetchAccounts = async () => {
  const res  = await fetch('/api/v1/crm/accounts?per_page=100', { headers: { Accept: 'application/json' } })
  const data = await res.json()
  accounts.value = data.data ?? []
}

const onSearchInput  = () => { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 400) }
const applyFilters   = () => fetchContacts(1)
const clearFilters   = () => { filters.search = ''; filters.status = null; fetchContacts(1) }
const onPageChange   = (e: { page: number }) => fetchContacts(e.page + 1)
const openCreateModal = () => { editingContact.value = null; showModal.value = true }
const editContact    = (c: Contact) => { editingContact.value = c; showModal.value = true }
const onContactSaved = () => { showModal.value = false; fetchContacts(pagination.current_page) }

const confirmDelete = (contact: Contact) => {
  confirm.require({
    message: `${t('common.delete')} ${contact.full_name} ?`,
    header: `${t('common.delete')} ${t('common.contact').toLowerCase()}`,
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/crm/contacts/${contact.id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
      fetchContacts(pagination.current_page)
    },
  })
}

onMounted(() => { fetchContacts(); fetchAccounts() })
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out), border-color var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-filter-input { width:100%; padding:7px 12px 7px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; transition:border-color var(--dur-base), box-shadow var(--dur-base); }
.wh-filter-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.wh-filter-input::placeholder { color:var(--fg-4); }
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
.contact-avatar { width:28px; height:28px; border-radius:50%; background:var(--halo-50); color:var(--halo-700); font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; text-transform:capitalize; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
.wh-badge-blue  { background:var(--halo-50); color:var(--halo-700); }
.wh-link { color:var(--fg-link); text-decoration:none; }
.wh-link:hover { text-decoration:underline; text-underline-offset:2px; }
.wh-null { color:var(--fg-4); }
</style>
