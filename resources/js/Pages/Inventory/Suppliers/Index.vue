<template>
  <AppLayout>
    <Head title="Suppliers" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Suppliers</h1>
        <p class="wh-page-subtitle">{{ suppliers.total }} supplier{{ suppliers.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" style="font-size:13px" /> New Supplier
        </button>
      </div>
    </div>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <div style="position:relative;flex:1;min-width:200px;max-width:320px">
          <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
          <input v-model="search" placeholder="Search suppliers..." class="wh-filter-input" @input="onSearch" />
        </div>
        <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Status" show-clear style="width:140px" @change="reload" />
      </div>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Supplier</th>
            <th>Code</th>
            <th>Country</th>
            <th>Currency</th>
            <th>Payment Terms</th>
            <th class="num">POs</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in suppliers.data" :key="s.id" class="wh-dt-row">
            <td>
              <div style="font-weight:500;color:var(--fg-1)">{{ s.name }}</div>
              <div v-if="s.email" style="font-size:12px;color:var(--fg-3)">{{ s.email }}</div>
            </td>
            <td><span style="font-family:var(--font-mono);font-size:12px">{{ s.code || '—' }}</span></td>
            <td>{{ s.country || '—' }}</td>
            <td>{{ s.currency }}</td>
            <td>{{ s.payment_terms }}</td>
            <td class="num">{{ s.purchase_orders_count }}</td>
            <td>
              <span :class="['wh-badge', s.is_active ? 'badge-success' : 'badge-danger']">
                {{ s.is_active ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px;justify-content:flex-end">
                <button class="btn-icon" title="Edit" @click="editSupplier(s)"><i class="pi pi-pencil" /></button>
                <button class="btn-icon btn-icon-danger" title="Delete" @click="deleteSupplier(s)"><i class="pi pi-trash" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!suppliers.data.length">
            <td colspan="8" style="text-align:center;padding:40px;color:var(--fg-3)">No suppliers found.</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="suppliers.last_page > 1" style="padding:12px 16px;display:flex;justify-content:center">
        <Paginator :rows="suppliers.per_page" :total-records="suppliers.total" :first="(suppliers.current_page - 1) * suppliers.per_page" @page="onPage" />
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="showCreate" :header="editingSupplier ? 'Edit Supplier' : 'New Supplier'" modal style="width:520px">
      <div style="display:grid;gap:14px;padding:4px 0">
        <div class="field">
          <label>Name *</label>
          <InputText v-model="form.name" class="w-full" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="field">
            <label>Code</label>
            <InputText v-model="form.code" class="w-full" placeholder="e.g. SUP-001" />
          </div>
          <div class="field">
            <label>Currency</label>
            <InputText v-model="form.currency" class="w-full" maxlength="3" placeholder="EUR" />
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="field">
            <label>Email</label>
            <InputText v-model="form.email" type="email" class="w-full" />
          </div>
          <div class="field">
            <label>Phone</label>
            <InputText v-model="form.phone" class="w-full" />
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="field">
            <label>Country (2-letter)</label>
            <InputText v-model="form.country" class="w-full" maxlength="2" placeholder="FR" />
          </div>
          <div class="field">
            <label>Payment Terms</label>
            <Select v-model="form.payment_terms" :options="paymentTermsOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>
        <div class="field">
          <label>Notes</label>
          <Textarea v-model="form.notes" rows="3" class="w-full" />
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <Checkbox v-model="form.is_active" binary />
          <label>{{ $t('common.active') }}</label>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="closeDialog">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveSupplier">
          {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { Select, Dialog, InputText, Textarea, Checkbox, Paginator } from 'primevue'
import axios from 'axios'

const { guidance } = useAiAssistant('Inventory', 'manage_suppliers')

interface Supplier {
  id: number
  name: string
  code: string | null
  email: string | null
  phone: string | null
  country: string | null
  currency: string
  payment_terms: string
  is_active: boolean
  purchase_orders_count: number
}

interface PaginatedSuppliers {
  data: Supplier[]
  total: number
  per_page: number
  current_page: number
  last_page: number
}

const props = defineProps<{
  suppliers: PaginatedSuppliers
  filters: { search?: string; status?: string }
}>()

const { t } = useI18n()

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || null)
const showCreate = ref(false)
const editingSupplier = ref<Supplier | null>(null)
const saving = ref(false)

const statusOptions = [
  { label: t('common.active'), value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
]

const paymentTermsOptions = [
  { label: 'Net 15', value: 'net15' },
  { label: 'Net 30', value: 'net30' },
  { label: 'Net 60', value: 'net60' },
  { label: 'Prepaid', value: 'prepaid' },
]

const form = reactive({
  name: '',
  code: '',
  email: '',
  phone: '',
  country: '',
  currency: 'EUR',
  payment_terms: 'net30',
  notes: '',
  is_active: true,
})

let searchTimer: ReturnType<typeof setTimeout>
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(reload, 400)
}

function reload() {
  router.get(route('inventory.suppliers.index'), {
    search: search.value || undefined,
    status: statusFilter.value || undefined,
  }, { preserveState: true, replace: true })
}

function editSupplier(s: Supplier) {
  editingSupplier.value = s
  Object.assign(form, {
    name: s.name,
    code: s.code || '',
    email: s.email || '',
    phone: s.phone || '',
    country: s.country || '',
    currency: s.currency,
    payment_terms: s.payment_terms,
    notes: '',
    is_active: s.is_active,
  })
  showCreate.value = true
}

function closeDialog() {
  showCreate.value = false
  editingSupplier.value = null
  Object.assign(form, { name: '', code: '', email: '', phone: '', country: '', currency: 'EUR', payment_terms: 'net30', notes: '', is_active: true })
}

async function saveSupplier() {
  saving.value = true
  try {
    if (editingSupplier.value) {
      await axios.put(`/api/v1/inventory/suppliers/${editingSupplier.value.id}`, form)
    } else {
      await axios.post('/api/v1/inventory/suppliers', form)
    }
    closeDialog()
    router.reload()
  } finally {
    saving.value = false
  }
}

async function deleteSupplier(s: Supplier) {
  if (!confirm(`Delete supplier "${s.name}"?`)) return
  await axios.delete(`/api/v1/inventory/suppliers/${s.id}`)
  router.reload()
}

function onPage(event: { page: number }) {
  router.get(route('inventory.suppliers.index'), {
    ...props.filters,
    page: event.page + 1,
  }, { preserveState: true })
}
</script>
