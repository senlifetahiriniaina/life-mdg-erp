<template>
  <AppLayout>
    <Head title="Invoices" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Invoices
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Create and manage customer invoices
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="New Invoice"
          @click="showNewInvoiceDialog = true"
        />
      </div>

      <!-- Financial Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <p class="text-surface-500 text-sm">Total Revenue</p>
          <p class="text-3xl font-bold text-green-600 mt-2">{{ formatCurrency(totalRevenue) }}</p>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <p class="text-surface-500 text-sm">Outstanding</p>
          <p class="text-3xl font-bold text-red-600 mt-2">{{ formatCurrency(outstandingAmount) }}</p>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <p class="text-surface-500 text-sm">Collection Rate</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-2">{{ collectionRate }}%</p>
        </div>
      </div>

      <!-- Invoices Table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="invoices"
          striped-rows
          class="p-datatable-sm"
          paginator
          :rows="15"
        >
          <Column field="invoice_number" header="Invoice #">
            <template #body="{ data }">
              <span class="font-medium text-primary-600">{{ data.invoice_number }}</span>
            </template>
          </Column>
          <Column field="customer" header="Customer" />
          <Column field="issued_date" header="Date">
            <template #body="{ data }">
              {{ formatDate(data.issued_date) }}
            </template>
          </Column>
          <Column field="amount" header="Amount">
            <template #body="{ data }">
              {{ formatCurrency(data.amount) }}
            </template>
          </Column>
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Actions" style="width: 8rem">
            <template #body="{ data }">
              <Button icon="pi pi-eye" outlined size="small" @click="viewInvoice(data)" />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- New Invoice Dialog -->
    <Dialog
      v-model:visible="showNewInvoiceDialog"
      header="New Invoice"
      modal
      class="w-full max-w-2xl"
    >
      <form @submit.prevent="saveInvoice" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Customer</label>
          <Select
            v-model="newInvoice.customer_id"
            :options="customers"
            option-label="name"
            option-value="id"
            placeholder="Select customer"
            required
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Invoice Date</label>
            <Calendar v-model="newInvoice.issued_date" show-icon date-format="yy-mm-dd" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Due Date</label>
            <Calendar v-model="newInvoice.due_date" show-icon date-format="yy-mm-dd" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Amount</label>
          <InputNumber v-model="newInvoice.amount" mode="currency" currency="USD" locale="en-US" />
        </div>
        <div class="flex justify-end gap-2 pt-4">
          <Button label="Cancel" severity="secondary" @click="showNewInvoiceDialog = false" />
          <Button label="Create" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Calendar from 'primevue/calendar'
import InputNumber from 'primevue/inputnumber'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Invoice {
  id: number
  invoice_number: string
  customer: string
  issued_date: string
  amount: number
  status: string
}

const saving = ref(false)
const showNewInvoiceDialog = ref(false)
const invoices = ref<Invoice[]>([])
const customers = ref([])

const newInvoice = ref({
  customer_id: null,
  issued_date: new Date(),
  due_date: new Date(),
  amount: 0,
})

const totalRevenue = computed(() =>
  invoices.value.reduce((sum, inv) => sum + (inv.status === 'paid' ? inv.amount : 0), 0)
)

const outstandingAmount = computed(() =>
  invoices.value.reduce((sum, inv) => sum + (inv.status !== 'paid' ? inv.amount : 0), 0)
)

const collectionRate = computed(() => {
  const total = invoices.value.reduce((sum, inv) => sum + inv.amount, 0)
  const paid = invoices.value.filter(inv => inv.status === 'paid').reduce((sum, inv) => sum + inv.amount, 0)
  return total > 0 ? Math.round((paid / total) * 100) : 0
})

const formatCurrency = (value: number): string =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value)

const formatDate = (date: string): string =>
  new Date(date).toLocaleDateString('en-US', { year: '2-digit', month: 'short', day: 'numeric' })

const getStatusSeverity = (status: string): string => {
  return status === 'paid' ? 'success' : status === 'draft' ? 'secondary' : 'info'
}

const saveInvoice = async () => {
  saving.value = true
  try {
    const response = await fetch('/api/v1/accounting/invoices', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newInvoice.value),
    })
    if (response.ok) {
      showNewInvoiceDialog.value = false
      await loadInvoices()
    }
  } catch (error) {
    console.error('Error:', error)
  } finally {
    saving.value = false
  }
}

const viewInvoice = (invoice: Invoice) => {
  console.log('View invoice:', invoice)
}

const loadInvoices = async () => {
  try {
    const response = await fetch('/api/v1/accounting/invoices', {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    invoices.value = data.data || []
  } catch (error) {
    console.error('Failed to load:', error)
  }
}

onMounted(() => {
  loadInvoices()
})
</script>
