<template>
  <AppLayout>
    <Head title="Stock Movements" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Stock Movements
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Track all inventory movements
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="New Movement"
          @click="openNewMovementModal"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <Select
            v-model="filters.product_id"
            :options="products"
            option-label="name"
            option-value="id"
            placeholder="Filter by product"
            show-clear
            filter
            class="w-48"
            @change="applyFilters"
          />
          <Select
            v-model="filters.warehouse_id"
            :options="warehouses"
            option-label="name"
            option-value="id"
            placeholder="Filter by warehouse"
            show-clear
            class="w-48"
            @change="applyFilters"
          />
          <Select
            v-model="filters.type"
            :options="movementTypeOptions"
            option-label="label"
            option-value="value"
            placeholder="Movement type"
            show-clear
            class="w-40"
            @change="applyFilters"
          />
          <DatePicker
            v-model="filters.date_from"
            placeholder="From date"
            date-format="yy-mm-dd"
            class="w-40"
            @date-select="applyFilters"
          />
          <DatePicker
            v-model="filters.date_to"
            placeholder="To date"
            date-format="yy-mm-dd"
            class="w-40"
            @date-select="applyFilters"
          />
          <Button
            icon="pi pi-filter-slash"
            outlined
            @click="clearFilters"
          />
        </div>
      </div>

      <!-- DataTable -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="movements"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
        >
          <Column field="created_at" header="Date">
            <template #body="{ data }">
              {{ formatDate(data.created_at) }}
            </template>
          </Column>
          <Column field="product" header="Product">
            <template #body="{ data }">
              <div class="font-medium">{{ data.product?.name ?? '—' }}</div>
              <div class="text-xs text-surface-400">{{ data.product?.sku }}</div>
            </template>
          </Column>
          <Column field="warehouse" header="Warehouse">
            <template #body="{ data }">
              {{ data.warehouse?.name ?? '—' }}
            </template>
          </Column>
          <Column field="type" header="Type">
            <template #body="{ data }">
              <Tag
                :value="movementTypeLabel(data.type)"
                :severity="movementTypeSeverity(data.type)"
              />
            </template>
          </Column>
          <Column field="quantity" header="Qty" style="width: 6rem">
            <template #body="{ data }">
              <span :class="data.type === 'out' ? 'text-red-600' : 'text-green-600'" class="font-semibold">
                {{ data.type === 'out' ? '-' : '+' }}{{ data.quantity }}
              </span>
            </template>
          </Column>
          <Column field="unit_cost" header="Unit Cost">
            <template #body="{ data }">
              {{ data.unit_cost != null ? formatCurrency(data.unit_cost) : '—' }}
            </template>
          </Column>
          <Column field="reference" header="Reference">
            <template #body="{ data }">
              {{ data.reference ?? '—' }}
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              No movements found.
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

    <!-- New Movement Modal -->
    <Dialog
      v-model:visible="showModal"
      header="New Stock Movement"
      modal
      class="w-full max-w-xl"
    >
      <form class="space-y-4" @submit.prevent="submitMovement">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1 md:col-span-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Product <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="movementForm.product_id"
              :options="products"
              option-label="name"
              option-value="id"
              placeholder="Select product"
              filter
              class="w-full"
              :class="{ 'p-invalid': movementErrors.product_id }"
            />
            <small v-if="movementErrors.product_id" class="text-red-500">{{ movementErrors.product_id }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Warehouse <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="movementForm.warehouse_id"
              :options="warehouses"
              option-label="name"
              option-value="id"
              placeholder="Select warehouse"
              class="w-full"
              :class="{ 'p-invalid': movementErrors.warehouse_id }"
            />
            <small v-if="movementErrors.warehouse_id" class="text-red-500">{{ movementErrors.warehouse_id }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Type <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="movementForm.type"
              :options="movementTypeOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Quantity <span class="text-red-500">*</span>
            </label>
            <InputNumber
              v-model="movementForm.quantity"
              :min="0"
              :min-fraction-digits="0"
              class="w-full"
              :class="{ 'p-invalid': movementErrors.quantity }"
            />
            <small v-if="movementErrors.quantity" class="text-red-500">{{ movementErrors.quantity }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Unit Cost</label>
            <InputNumber
              v-model="movementForm.unit_cost"
              :min-fraction-digits="2"
              :max-fraction-digits="2"
              class="w-full"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Reference</label>
            <InputText
              v-model="movementForm.reference"
              placeholder="e.g. PO-0001"
            />
          </div>

          <div class="flex flex-col gap-1 md:col-span-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Notes</label>
            <Textarea
              v-model="movementForm.notes"
              rows="2"
              class="w-full"
            />
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
            label="Record Movement"
            :loading="submittingMovement"
          />
        </div>
      </form>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Product {
  id: number
  name: string
  sku: string
}

interface Warehouse {
  id: number
  name: string
  code: string
}

interface Movement {
  id: number
  type: string
  quantity: number
  unit_cost: number | null
  reference: string | null
  notes: string | null
  product: Product | null
  warehouse: Warehouse | null
  created_at: string
}

interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

const movements = ref<Movement[]>([])
const products = ref<Product[]>([])
const warehouses = ref<Warehouse[]>([])
const loading = ref(false)
const showModal = ref(false)
const submittingMovement = ref(false)

const pagination = reactive<Pagination>({
  current_page: 1,
  per_page: 25,
  total: 0,
  last_page: 1,
})

const filters = reactive({
  product_id: null as number | null,
  warehouse_id: null as number | null,
  type: null as string | null,
  date_from: null as Date | null,
  date_to: null as Date | null,
})

const movementForm = reactive({
  product_id: null as number | null,
  warehouse_id: null as number | null,
  type: 'in',
  quantity: null as number | null,
  unit_cost: null as number | null,
  reference: '',
  notes: '',
})

const movementErrors = reactive<Record<string, string>>({})

const movementTypeOptions = [
  { label: 'Receipt', value: 'in' },
  { label: 'Dispatch', value: 'out' },
  { label: 'Transfer', value: 'transfer' },
  { label: 'Adjustment', value: 'adjustment' },
]

const movementTypeLabel = (type: string): string => {
  return movementTypeOptions.find(o => o.value === type)?.label ?? type
}

const movementTypeSeverity = (type: string): string => {
  switch (type) {
    case 'in': return 'success'
    case 'out': return 'danger'
    case 'transfer': return 'info'
    case 'adjustment': return 'warn'
    default: return 'secondary'
  }
}

const formatDate = (iso: string): string => {
  return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value)
}

const formatDateParam = (date: Date | null): string | null => {
  if (!date) return null
  return date.toISOString().split('T')[0]
}

const fetchMovements = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(pagination.per_page))
    if (filters.product_id != null) params.set('product_id', String(filters.product_id))
    if (filters.warehouse_id != null) params.set('warehouse_id', String(filters.warehouse_id))
    if (filters.type) params.set('type', filters.type)
    const from = formatDateParam(filters.date_from)
    const to = formatDateParam(filters.date_to)
    if (from) params.set('from', from)
    if (to) params.set('to', to)

    const response = await fetch(`/api/v1/inventory/movements?${params}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    movements.value = data.data
    pagination.current_page = data.current_page
    pagination.total = data.total
    pagination.last_page = data.last_page
  } finally {
    loading.value = false
  }
}

const fetchProducts = async () => {
  const response = await fetch('/api/v1/inventory/products?per_page=200', {
    headers: { Accept: 'application/json' },
  })
  const data = await response.json()
  products.value = data.data ?? []
}

const fetchWarehouses = async () => {
  const response = await fetch('/api/v1/inventory/warehouses?per_page=100', {
    headers: { Accept: 'application/json' },
  })
  const data = await response.json()
  warehouses.value = data.data ?? []
}

const applyFilters = () => fetchMovements(1)

const clearFilters = () => {
  filters.product_id = null
  filters.warehouse_id = null
  filters.type = null
  filters.date_from = null
  filters.date_to = null
  fetchMovements(1)
}

const onPageChange = (event: { page: number }) => fetchMovements(event.page + 1)

const openNewMovementModal = () => {
  movementForm.product_id = null
  movementForm.warehouse_id = null
  movementForm.type = 'in'
  movementForm.quantity = null
  movementForm.unit_cost = null
  movementForm.reference = ''
  movementForm.notes = ''
  Object.keys(movementErrors).forEach(k => delete movementErrors[k])
  showModal.value = true
}

const submitMovement = async () => {
  submittingMovement.value = true
  Object.keys(movementErrors).forEach(k => delete movementErrors[k])

  try {
    const response = await fetch('/api/v1/inventory/movements', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        product_id: movementForm.product_id,
        warehouse_id: movementForm.warehouse_id,
        type: movementForm.type,
        quantity: movementForm.quantity,
        unit_cost: movementForm.unit_cost,
        reference: movementForm.reference || null,
        notes: movementForm.notes || null,
      }),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        Object.assign(movementErrors, data.errors)
      }
      return
    }

    showModal.value = false
    fetchMovements(pagination.current_page)
  } finally {
    submittingMovement.value = false
  }
}

onMounted(() => {
  fetchMovements()
  fetchProducts()
  fetchWarehouses()
})
</script>
