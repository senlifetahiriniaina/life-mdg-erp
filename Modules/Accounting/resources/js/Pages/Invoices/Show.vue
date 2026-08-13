<template>
  <AppLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Invoice {{ invoice.invoice_number }}</h1>
        <div class="space-x-2">
          <Button v-if="invoice.status === 'draft'" label="Send" icon="pi pi-send" />
          <Button v-if="invoice.status === 'sent'" label="Record Payment" icon="pi pi-check" severity="success" />
          <Link :href="`/accounting/invoices/${invoice.id}/edit`" class="px-4 py-2 bg-yellow-600 text-white rounded-lg">
            Edit
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-4 gap-4 mb-6">
        <Card>
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Status</p>
            <Tag :value="invoice.status" :severity="getStatusSeverity(invoice.status)" class="mt-2" />
          </template>
        </Card>
        <Card>
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Total Amount</p>
            <p class="text-2xl font-bold text-primary-700 dark:text-primary-300 mt-2">{{ formatCurrency(invoice.total) }}</p>
          </template>
        </Card>
        <Card>
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Paid Amount</p>
            <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-2">{{ formatCurrency(invoice.paid_amount) }}</p>
          </template>
        </Card>
        <Card>
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Balance Due</p>
            <p class="text-2xl font-bold text-red-700 dark:text-red-300 mt-2">{{ formatCurrency(invoice.total - invoice.paid_amount) }}</p>
          </template>
        </Card>
      </div>

      <div class="grid grid-cols-3 gap-6">
        <!-- Invoice Details -->
        <div class="col-span-2">
          <Card class="mb-6">
            <template #title>Invoice Details</template>
            <template #content>
              <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Invoice Number</p>
                  <p class="font-semibold">{{ invoice.invoice_number }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Invoice Date</p>
                  <p class="font-semibold">{{ formatDate(invoice.invoice_date) }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Due Date</p>
                  <p class="font-semibold">{{ formatDate(invoice.due_date) }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Customer</p>
                  <Link :href="`/crm/accounts/${invoice.customer.id}`" class="font-semibold text-primary-700 dark:text-primary-300 hover:underline">
                    {{ invoice.customer.name }}
                  </Link>
                </div>
              </div>
            </template>
          </Card>

          <Card class="mb-6">
            <template #title>Line Items</template>
            <template #content>
              <DataTable :value="invoice.line_items" class="p-datatable-sm">
                <Column field="description" header="Description" />
                <Column field="quantity" header="Qty" align="right" />
                <Column field="unit_price" header="Unit Price" align="right">
                  <template #body="{ data }">
                    {{ formatCurrency(data.unit_price) }}
                  </template>
                </Column>
                <Column field="tax_rate" header="Tax" align="right">
                  <template #body="{ data }">
                    {{ data.tax_rate }}%
                  </template>
                </Column>
                <Column field="line_total" header="Total" align="right">
                  <template #body="{ data }">
                    {{ formatCurrency(data.line_total) }}
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>

          <Card>
            <template #title>Amounts</template>
            <template #content>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span>Subtotal</span>
                  <span>{{ formatCurrency(invoice.subtotal) }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Tax</span>
                  <span>{{ formatCurrency(invoice.tax_amount) }}</span>
                </div>
                <Divider />
                <div class="flex justify-between text-lg font-bold">
                  <span>Total</span>
                  <span>{{ formatCurrency(invoice.total) }}</span>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Sidebar -->
        <div>
          <Card class="mb-6">
            <template #title>Payment History</template>
            <template #content>
              <Timeline :value="invoice.payments" align="left">
                <template #content="slotProps">
                  <div class="text-sm">
                    <p class="font-semibold">{{ formatCurrency(slotProps.item.amount) }}</p>
                    <p class="text-surface-600 dark:text-surface-400">{{ formatDate(slotProps.item.payment_date) }}</p>
                    <Tag :value="slotProps.item.method" class="mt-1" />
                  </div>
                </template>
              </Timeline>
              <p v-if="invoice.payments.length === 0" class="text-surface-600 dark:text-surface-400 text-sm">No payments recorded</p>
            </template>
          </Card>

          <Card>
            <template #title>Notes</template>
            <template #content>
              <p class="text-sm">{{ invoice.notes || 'No notes' }}</p>
            </template>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Divider from 'primevue/divider'
import Timeline from 'primevue/timeline'

defineProps({
  invoice: Object
})

const getStatusSeverity = (status) => {
  const map = { draft: 'info', sent: 'warning', paid: 'success', overdue: 'danger' }
  return map[status] || 'info'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
