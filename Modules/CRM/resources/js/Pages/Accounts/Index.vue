<template>
  <AppLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-fg-1">Accounts</h1>
        <Link href="/crm/accounts/create" class="px-4 py-2 bg-halo-600 text-white rounded-lg hover:bg-halo-700">
          New Account
        </Link>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-lg shadow overflow-hidden">
        <DataTable :value="accounts.data" paginator :rows="15" class="p-datatable-striped">
          <Column field="name" header="Account Name" sortable>
            <template #body="{ data }">
              <Link :href="`/crm/accounts/${data.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">
                {{ data.name }}
              </Link>
            </template>
          </Column>
          <Column field="industry" header="Industry" sortable />
          <Column field="type" header="Type" sortable>
            <template #body="{ data }">
              <Tag :value="data.type" :severity="getTypeSeverity(data.type)" />
            </template>
          </Column>
          <Column field="employees" header="Employees" sortable />
          <Column field="annual_revenue" header="Revenue" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.annual_revenue) }}
            </template>
          </Column>
          <Column field="created_at" header="Created" sortable>
            <template #body="{ data }">
              {{ formatDate(data.created_at) }}
            </template>
          </Column>
          <Column header="Actions" :exportable="false">
            <template #body="{ data }">
              <Link :href="`/crm/accounts/${data.id}`" class="text-primary-700 dark:text-primary-300 hover:underline mr-2">
                View
              </Link>
              <Link :href="`/crm/accounts/${data.id}/edit`" class="text-yellow-700 dark:text-yellow-300 hover:underline mr-2">
                Edit
              </Link>
              <button @click="deleteAccount(data.id)" class="text-red-700 dark:text-red-300 hover:underline">
                Delete
              </button>
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

defineProps({
  accounts: Object
})

const getTypeSeverity = (type) => {
  const map = { prospect: 'info', customer: 'success', partner: 'warning' }
  return map[type] || 'info'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const deleteAccount = (id) => {
  if (confirm('Are you sure?')) {
    router.delete(`/crm/accounts/${id}`)
  }
}
</script>
