<template>
  <AppLayout>
    <Head title="Warehouses" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Warehouses
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Manage your storage locations
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="New Warehouse"
          @click="openCreateModal"
        />
      </div>

      <!-- DataTable -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="warehouses"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
        >
          <Column field="name" header="Name" sortable>
            <template #body="{ data }">
              <div class="font-medium text-surface-900 dark:text-surface-50">{{ data.name }}</div>
            </template>
          </Column>
          <Column field="code" header="Code">
            <template #body="{ data }">
              <code class="text-xs bg-surface-100 dark:bg-surface-700 px-2 py-0.5 rounded">{{ data.code }}</code>
            </template>
          </Column>
          <Column field="type" header="Type">
            <template #body="{ data }">
              {{ data.type ?? '—' }}
            </template>
          </Column>
          <Column field="city" header="City">
            <template #body="{ data }">
              {{ data.city ?? '—' }}
            </template>
          </Column>
          <Column field="country" header="Country">
            <template #body="{ data }">
              {{ data.country ?? '—' }}
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
                  @click="editWarehouse(data)"
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
              No warehouses found.
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
            @page="onPageChange"
          />
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingWarehouse ? 'Edit Warehouse' : 'New Warehouse'"
      modal
      class="w-full max-w-lg"
    >
      <form class="space-y-4" @submit.prevent="submitWarehouse">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1 md:col-span-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Name <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="warehouseForm.name"
              :class="{ 'p-invalid': warehouseErrors.name }"
              placeholder="Warehouse name"
            />
            <small v-if="warehouseErrors.name" class="text-red-500">{{ warehouseErrors.name }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Code <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="warehouseForm.code"
              :class="{ 'p-invalid': warehouseErrors.code }"
              placeholder="e.g. WH-001"
            />
            <small v-if="warehouseErrors.code" class="text-red-500">{{ warehouseErrors.code }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Type</label>
            <Select
              v-model="warehouseForm.type"
              :options="warehouseTypeOptions"
              option-label="label"
              option-value="value"
              placeholder="Select type"
              show-clear
              class="w-full"
            />
          </div>

          <div class="flex flex-col gap-1 md:col-span-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Address</label>
            <InputText
              v-model="warehouseForm.address"
              placeholder="Street address"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">City</label>
            <InputText
              v-model="warehouseForm.city"
              placeholder="City"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Country</label>
            <InputText
              v-model="warehouseForm.country"
              placeholder="Country"
            />
          </div>

          <div class="flex items-center gap-2 md:col-span-2">
            <ToggleSwitch v-model="warehouseForm.is_active" />
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
            :label="editingWarehouse ? 'Update Warehouse' : 'Create Warehouse'"
            :loading="submitting"
          />
        </div>
      </form>
    </Dialog>

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
import ToggleSwitch from 'primevue/toggleswitch'
import Paginator from 'primevue/paginator'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Warehouse {
  id: number
  name: string
  code: string
  type: string | null
  address: string | null
  city: string | null
  country: string | null
  is_active: boolean
}

interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

const confirm = useConfirm()

const warehouses = ref<Warehouse[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingWarehouse = ref<Warehouse | null>(null)
const submitting = ref(false)

const pagination = reactive<Pagination>({
  current_page: 1,
  per_page: 25,
  total: 0,
  last_page: 1,
})

const warehouseForm = reactive({
  name: '',
  code: '',
  type: null as string | null,
  address: '',
  city: '',
  country: '',
  is_active: true,
})

const warehouseErrors = reactive<Record<string, string>>({})

const warehouseTypeOptions = [
  { label: 'Standard', value: 'standard' },
  { label: 'Transit', value: 'transit' },
  { label: 'Virtual', value: 'virtual' },
  { label: 'Customer', value: 'customer' },
  { label: 'Vendor', value: 'vendor' },
]

const fetchWarehouses = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(pagination.per_page))

    const response = await fetch(`/api/v1/inventory/warehouses?${params}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    warehouses.value = data.data
    pagination.current_page = data.current_page
    pagination.total = data.total
    pagination.last_page = data.last_page
  } finally {
    loading.value = false
  }
}

const onPageChange = (event: { page: number }) => fetchWarehouses(event.page + 1)

const openCreateModal = () => {
  editingWarehouse.value = null
  warehouseForm.name = ''
  warehouseForm.code = ''
  warehouseForm.type = null
  warehouseForm.address = ''
  warehouseForm.city = ''
  warehouseForm.country = ''
  warehouseForm.is_active = true
  Object.keys(warehouseErrors).forEach(k => delete warehouseErrors[k])
  showModal.value = true
}

const editWarehouse = (warehouse: Warehouse) => {
  editingWarehouse.value = warehouse
  warehouseForm.name = warehouse.name
  warehouseForm.code = warehouse.code
  warehouseForm.type = warehouse.type
  warehouseForm.address = warehouse.address ?? ''
  warehouseForm.city = warehouse.city ?? ''
  warehouseForm.country = warehouse.country ?? ''
  warehouseForm.is_active = warehouse.is_active
  Object.keys(warehouseErrors).forEach(k => delete warehouseErrors[k])
  showModal.value = true
}

const confirmDelete = (warehouse: Warehouse) => {
  confirm.require({
    message: `Are you sure you want to delete "${warehouse.name}"?`,
    header: 'Delete Warehouse',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/inventory/warehouses/${warehouse.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
      })
      fetchWarehouses(pagination.current_page)
    },
  })
}

const submitWarehouse = async () => {
  submitting.value = true
  Object.keys(warehouseErrors).forEach(k => delete warehouseErrors[k])

  const url = editingWarehouse.value
    ? `/api/v1/inventory/warehouses/${editingWarehouse.value.id}`
    : '/api/v1/inventory/warehouses'

  const method = editingWarehouse.value ? 'PUT' : 'POST'

  try {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ ...warehouseForm }),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        Object.assign(warehouseErrors, data.errors)
      }
      return
    }

    showModal.value = false
    fetchWarehouses(pagination.current_page)
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchWarehouses()
})
</script>
