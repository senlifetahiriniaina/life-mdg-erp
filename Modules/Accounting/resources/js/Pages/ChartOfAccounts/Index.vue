<template>
  <AppLayout>
    <Head title="Chart of Accounts" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Chart of Accounts
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Manage your accounting structure
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="New Account"
          @click="openCreateModal"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <div class="flex-1 min-w-48">
            <InputText
              v-model="filters.search"
              placeholder="Search by code or name..."
              class="w-full"
              @input="onSearchInput"
            />
          </div>
          <Select
            v-model="filters.type"
            :options="accountTypeOptions"
            option-label="label"
            option-value="value"
            placeholder="Account type"
            show-clear
            class="w-40"
            @change="applyFilters"
          />
          <Select
            v-model="filters.is_active"
            :options="activeOptions"
            option-label="label"
            option-value="value"
            placeholder="Status"
            show-clear
            class="w-36"
            @change="applyFilters"
          />
          <Button
            icon="pi pi-filter-slash"
            outlined
            @click="clearFilters"
          />
        </div>
      </div>

      <!-- DataTable tree view -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="accounts"
          :loading="loading"
          striped-rows
          scrollable
          scroll-height="600px"
          class="p-datatable-sm"
        >
          <Column field="code" header="Code" style="width: 10rem" sortable>
            <template #body="{ data }">
              <code class="text-xs bg-surface-100 dark:bg-surface-700 px-2 py-0.5 rounded">{{ data.code }}</code>
            </template>
          </Column>
          <Column field="name" header="Name" sortable>
            <template #body="{ data }">
              <div
                class="font-medium text-surface-900 dark:text-surface-50"
                :style="data.parent_id ? 'padding-left: 1.5rem' : ''"
              >
                <span v-if="data.parent_id" class="text-surface-400 mr-1">↳</span>
                {{ data.name }}
              </div>
              <div v-if="data.parent" class="text-xs text-surface-400 pl-4">
                {{ data.parent.code }} {{ data.parent.name }}
              </div>
            </template>
          </Column>
          <Column field="type" header="Type">
            <template #body="{ data }">
              <Tag
                :value="accountTypeLabel(data.type)"
                :severity="accountTypeSeverity(data.type)"
              />
            </template>
          </Column>
          <Column field="is_active" header="Active">
            <template #body="{ data }">
              <Tag
                :value="data.is_active ? 'Active' : 'Inactive'"
                :severity="data.is_active ? 'success' : 'secondary'"
              />
            </template>
          </Column>
          <Column header="Actions" style="width: 8rem">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-pencil"
                  outlined
                  size="small"
                  @click="editAccount(data)"
                />
                <Button
                  icon="pi pi-trash"
                  outlined
                  severity="danger"
                  size="small"
                  @click="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              No accounts found.
            </div>
          </template>
        </DataTable>

        <!-- Pagination -->
        <div
          v-if="pagination.total > pagination.per_page"
          class="flex items-center justify-between p-4 border-t border-surface-200 dark:border-surface-700"
        >
          <span class="text-sm text-surface-500">
            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }}
          </span>
          <Paginator
            :rows="pagination.per_page"
            :total-records="pagination.total"
            :first="(pagination.current_page - 1) * pagination.per_page"
            :rows-per-page-options="[10, 25, 50, 100]"
            @page="onPageChange"
            @rows-change="onRowsChange"
          />
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingAccount ? 'Edit Account' : 'New Account'"
      modal
      class="w-full max-w-lg"
    >
      <form class="space-y-4" @submit.prevent="submitAccount">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Code <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="accountForm.code"
              :class="{ 'p-invalid': accountErrors.code }"
              placeholder="e.g. 1000"
            />
            <small v-if="accountErrors.code" class="text-red-500">{{ accountErrors.code }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Name <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="accountForm.name"
              :class="{ 'p-invalid': accountErrors.name }"
              placeholder="Account name"
            />
            <small v-if="accountErrors.name" class="text-red-500">{{ accountErrors.name }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Type <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="accountForm.type"
              :options="accountTypeOptions"
              option-label="label"
              option-value="value"
              class="w-full"
              :class="{ 'p-invalid': accountErrors.type }"
            />
            <small v-if="accountErrors.type" class="text-red-500">{{ accountErrors.type }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Parent Account</label>
            <Select
              v-model="accountForm.parent_id"
              :options="parentAccounts"
              option-label="label"
              option-value="value"
              placeholder="No parent"
              show-clear
              filter
              class="w-full"
            />
          </div>

          <div class="flex flex-col gap-1 md:col-span-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Description</label>
            <Textarea
              v-model="accountForm.description"
              rows="2"
              class="w-full"
            />
          </div>

          <div class="flex items-center gap-2 md:col-span-2">
            <ToggleSwitch v-model="accountForm.is_active" />
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Active</label>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-surface-200 dark:border-surface-700">
          <Button
            type="button"
            label="Cancel"
            outlined
            @click="showModal = false"
          />
          <Button
            type="submit"
            :label="editingAccount ? 'Update Account' : 'Create Account'"
            :loading="submitting"
          />
        </div>
      </form>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import ToggleSwitch from 'primevue/toggleswitch'
import Textarea from 'primevue/textarea'
import Paginator from 'primevue/paginator'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Account {
  id: number
  code: string
  name: string
  type: string
  description: string | null
  parent_id: number | null
  parent: { id: number; code: string; name: string } | null
  is_active: boolean
}

interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

const confirm = useConfirm()

const accounts = ref<Account[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingAccount = ref<Account | null>(null)
const submitting = ref(false)

const pagination = reactive<Pagination>({
  current_page: 1,
  per_page: 25,
  total: 0,
  last_page: 1,
})

const filters = reactive({
  search: '',
  type: null as string | null,
  is_active: null as boolean | null,
})

const accountForm = reactive({
  code: '',
  name: '',
  type: 'asset',
  description: '',
  parent_id: null as number | null,
  is_active: true,
})

const accountErrors = reactive<Record<string, string>>({})

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

const accountTypeOptions = [
  { label: 'Asset', value: 'asset' },
  { label: 'Liability', value: 'liability' },
  { label: 'Equity', value: 'equity' },
  { label: 'Revenue', value: 'revenue' },
  { label: 'Expense', value: 'expense' },
]

const activeOptions = [
  { label: 'Active', value: true },
  { label: 'Inactive', value: false },
]

const parentAccounts = computed(() =>
  accounts.value
    .filter(a => !editingAccount.value || a.id !== editingAccount.value.id)
    .map(a => ({ label: `${a.code} – ${a.name}`, value: a.id }))
)

const accountTypeLabel = (type: string): string => {
  return accountTypeOptions.find(o => o.value === type)?.label ?? type
}

const accountTypeSeverity = (type: string): string => {
  switch (type) {
    case 'asset': return 'info'
    case 'liability': return 'danger'
    case 'equity': return 'warn'
    case 'revenue': return 'success'
    case 'expense': return 'secondary'
    default: return 'secondary'
  }
}

const fetchAccounts = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(pagination.per_page))
    if (filters.search) params.set('search', filters.search)
    if (filters.type) params.set('type', filters.type)
    if (filters.is_active != null) params.set('is_active', String(filters.is_active))

    const response = await fetch(`/api/v1/accounting/chart-of-accounts?${params}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    accounts.value = data.data
    pagination.current_page = data.current_page
    pagination.total = data.total
    pagination.last_page = data.last_page
  } finally {
    loading.value = false
  }
}

const onSearchInput = () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => applyFilters(), 400)
}

const applyFilters = () => fetchAccounts(1)

const clearFilters = () => {
  filters.search = ''
  filters.type = null
  filters.is_active = null
  fetchAccounts(1)
}

const onPageChange = (event: { page: number }) => fetchAccounts(event.page + 1)

const onRowsChange = (event: { rows: number }) => {
  pagination.per_page = event.rows
  pagination.current_page = 1
  fetchAccounts(1)
}

const openCreateModal = () => {
  editingAccount.value = null
  accountForm.code = ''
  accountForm.name = ''
  accountForm.type = 'asset'
  accountForm.description = ''
  accountForm.parent_id = null
  accountForm.is_active = true
  Object.keys(accountErrors).forEach(k => delete accountErrors[k])
  showModal.value = true
}

const editAccount = (account: Account) => {
  editingAccount.value = account
  accountForm.code = account.code
  accountForm.name = account.name
  accountForm.type = account.type
  accountForm.description = account.description ?? ''
  accountForm.parent_id = account.parent_id
  accountForm.is_active = account.is_active
  Object.keys(accountErrors).forEach(k => delete accountErrors[k])
  showModal.value = true
}

const confirmDelete = (account: Account) => {
  confirm.require({
    message: `Are you sure you want to delete account "${account.code} – ${account.name}"?`,
    header: 'Delete Account',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/accounting/chart-of-accounts/${account.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
      })
      fetchAccounts(pagination.current_page)
    },
  })
}

const submitAccount = async () => {
  submitting.value = true
  Object.keys(accountErrors).forEach(k => delete accountErrors[k])

  const url = editingAccount.value
    ? `/api/v1/accounting/chart-of-accounts/${editingAccount.value.id}`
    : '/api/v1/accounting/chart-of-accounts'

  const method = editingAccount.value ? 'PUT' : 'POST'

  try {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ ...accountForm }),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        Object.assign(accountErrors, data.errors)
      }
      return
    }

    showModal.value = false
    fetchAccounts(pagination.current_page)
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchAccounts()
})
</script>
