<template>
  <AppLayout>
    <Head title="Contacts" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">
            Contacts
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Manage your CRM contacts
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="New Contact"
          @click="openCreateModal"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <div class="flex-1 min-w-48">
            <InputText
              v-model="filters.search"
              placeholder="Search contacts..."
              class="w-full"
              @input="onSearchInput"
            />
          </div>
          <Select
            v-model="filters.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            placeholder="Filter by status"
            show-clear
            class="w-48"
            @change="applyFilters"
          />
          <Button
            icon="pi pi-filter-slash"
            outlined
            @click="clearFilters"
          />
        </div>
      </div>

      <!-- DataTable with Virtual Scrolling -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="contacts"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
          scroll-height="600px"
          scroll-direction="vertical"
        >
          <Column field="full_name" header="Name" sortable>
            <template #body="{ data }">
              <div class="font-medium text-surface-900 dark:text-surface-50">
                {{ data.full_name }}
              </div>
            </template>
          </Column>
          <Column field="email" header="Email">
            <template #body="{ data }">
              <a
                v-if="data.email"
                :href="`mailto:${data.email}`"
                class="text-primary-600 hover:underline"
              >
                {{ data.email }}
              </a>
              <span v-else class="text-surface-400">—</span>
            </template>
          </Column>
          <Column field="phone" header="Phone">
            <template #body="{ data }">
              {{ data.phone ?? '—' }}
            </template>
          </Column>
          <Column field="job_title" header="Job Title">
            <template #body="{ data }">
              {{ data.job_title ?? '—' }}
            </template>
          </Column>
          <Column field="account" header="Account">
            <template #body="{ data }">
              {{ data.account?.name ?? '—' }}
            </template>
          </Column>
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="statusSeverity(data.status)"
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
                  @click="editContact(data)"
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
              No contacts found.
            </div>
          </template>
        </DataTable>

        <!-- Summary -->
        <div
          v-if="contacts.length > 0"
          class="flex items-center justify-between p-4 border-t border-surface-200 dark:border-surface-700 text-sm text-surface-500"
        >
          <span>
            Displaying {{ contacts.length }} of {{ pagination.total }} contacts
          </span>
          <span v-if="loading" class="text-primary-600">Loading...</span>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingContact ? 'Edit Contact' : 'New Contact'"
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

    <!-- Delete Confirm -->
    <ConfirmDialog />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContactForm from './Form.vue'

interface Account {
  id: number
  name: string
}

interface Contact {
  id: number
  full_name: string
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  job_title: string | null
  status: string
  account: Account | null
}

interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

const confirm = useConfirm()

const contacts = ref<Contact[]>([])
const accounts = ref<Account[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingContact = ref<Contact | null>(null)

const pagination = reactive<Pagination>({
  current_page: 1,
  per_page: 500,  // Load up to 500 records at once for virtual scrolling
  total: 0,
  last_page: 1,
})

const filters = reactive({
  search: '',
  status: null as string | null,
})

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Prospect', value: 'prospect' },
]

const statusSeverity = (status: string): string => {
  switch (status) {
    case 'active': return 'success'
    case 'inactive': return 'secondary'
    case 'prospect': return 'info'
    default: return 'secondary'
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

    const response = await fetch(`/api/v1/crm/contacts?${params}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    contacts.value = data.data
    pagination.current_page = data.current_page
    pagination.total = data.total
    pagination.last_page = data.last_page
  } finally {
    loading.value = false
  }
}

const fetchAccounts = async () => {
  const response = await fetch('/api/v1/crm/accounts?per_page=100', {
    headers: { Accept: 'application/json' },
  })
  const data = await response.json()
  accounts.value = data.data ?? []
}

const onSearchInput = () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => applyFilters(), 400)
}

const applyFilters = () => {
  pagination.current_page = 1
  fetchContacts(1)
}

const clearFilters = () => {
  filters.search = ''
  filters.status = null
  pagination.current_page = 1
  fetchContacts(1)
}

const openCreateModal = () => {
  editingContact.value = null
  showModal.value = true
}

const editContact = (contact: Contact) => {
  editingContact.value = contact
  showModal.value = true
}

const confirmDelete = (contact: Contact) => {
  confirm.require({
    message: `Are you sure you want to delete ${contact.full_name}?`,
    header: 'Delete Contact',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/crm/contacts/${contact.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
      })
      fetchContacts(1)
    },
  })
}

const onContactSaved = () => {
  showModal.value = false
  fetchContacts(1)
}

onMounted(() => {
  fetchContacts()
  fetchAccounts()
})
</script>
