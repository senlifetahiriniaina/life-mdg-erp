<template>
  <AppLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Expenses</h1>
        <Link href="/accounting/expenses/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          New Expense
        </Link>
      </div>

      <div class="grid grid-cols-4 gap-4 mb-6">
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Total This Month</p>
            <p class="text-3xl font-bold text-primary-700 dark:text-primary-300 mt-2">{{ formatCurrency(stats.total_this_month) }}</p>
          </template>
        </Card>
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Total This Year</p>
            <p class="text-3xl font-bold text-green-700 dark:text-green-300 mt-2">{{ formatCurrency(stats.total_this_year) }}</p>
          </template>
        </Card>
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Pending Approval</p>
            <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300 mt-2">{{ stats.pending_count }}</p>
          </template>
        </Card>
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Approved</p>
            <p class="text-3xl font-bold text-green-700 dark:text-green-300 mt-2">{{ stats.approved_count }}</p>
          </template>
        </Card>
      </div>

      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow overflow-hidden">
        <DataTable :value="expenses.data" paginator :rows="15" class="p-datatable-striped" :loading="loading">
          <Column field="expense_number" header="Expense #" sortable>
            <template #body="{ data }">
              <Link :href="`/accounting/expenses/${data.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">
                {{ data.expense_number }}
              </Link>
            </template>
          </Column>
          <Column field="category" header="Category" sortable />
          <Column field="description" header="Description" />
          <Column field="amount" header="Amount" sortable align="right">
            <template #body="{ data }">
              {{ formatCurrency(data.amount) }}
            </template>
          </Column>
          <Column field="status" header="Status" sortable>
            <template #body="{ data }">
              <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column field="submitted_by" header="Submitted By" />
          <Column field="submitted_date" header="Date" sortable>
            <template #body="{ data }">
              {{ formatDate(data.submitted_date) }}
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <Link :href="`/accounting/expenses/${data.id}`" class="text-primary-700 dark:text-primary-300 hover:underline mr-2">
                View
              </Link>
              <Link v-if="data.status === 'draft'" :href="`/accounting/expenses/${data.id}/edit`" class="text-yellow-700 dark:text-yellow-300 hover:underline">
                Edit
              </Link>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import { ref } from 'vue'

defineProps({
  expenses: Object,
  stats: Object
})

const loading = ref(false)

const getStatusSeverity = (status) => {
  const map = { draft: 'info', submitted: 'warning', approved: 'success', rejected: 'danger', reimbursed: 'success' }
  return map[status] || 'info'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
