<template>
  <AppLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">{{ employee.name }}</h1>
        <Link :href="`/hr/employees/${employee.id}/edit`" class="px-4 py-2 bg-yellow-600 text-white rounded-lg">
          Edit Employee
        </Link>
      </div>

      <div class="grid grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="col-span-1">
          <Card class="mb-6">
            <template #content>
              <div class="text-center mb-4">
                <img :src="employee.avatar_url" :alt="employee.name" class="w-32 h-32 rounded-full mx-auto mb-4" />
                <h2 class="text-xl font-bold">{{ employee.name }}</h2>
                <p class="text-surface-600 dark:text-surface-400">{{ employee.job_title }}</p>
                <Tag :value="employee.status" :severity="getStatusSeverity(employee.status)" class="mt-2" />
              </div>

              <Divider />

              <div class="space-y-3">
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Employee ID</p>
                  <p class="font-semibold">{{ employee.employee_id }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Department</p>
                  <p class="font-semibold">{{ employee.department?.name }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Manager</p>
                  <p class="font-semibold">{{ employee.manager?.name || 'N/A' }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Email</p>
                  <p class="font-semibold">{{ employee.email }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Phone</p>
                  <p class="font-semibold">{{ employee.phone }}</p>
                </div>
              </div>
            </template>
          </Card>

          <Card>
            <template #title>Employment Details</template>
            <template #content>
              <div class="space-y-3">
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Hire Date</p>
                  <p class="font-semibold">{{ formatDate(employee.hire_date) }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Employment Type</p>
                  <p class="font-semibold">{{ employee.employment_type }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Salary</p>
                  <p class="font-semibold">{{ formatCurrency(employee.salary) }}</p>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Details -->
        <div class="col-span-2 space-y-6">
          <Card>
            <template #title>Contact Information</template>
            <template #content>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Email</p>
                  <p class="font-semibold">{{ employee.email }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Phone</p>
                  <p class="font-semibold">{{ employee.phone }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Address</p>
                  <p class="font-semibold">{{ employee.address }}</p>
                </div>
                <div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">Date of Birth</p>
                  <p class="font-semibold">{{ formatDate(employee.date_of_birth) }}</p>
                </div>
              </div>
            </template>
          </Card>

          <Card>
            <template #title>Leave Balance</template>
            <template #content>
              <DataTable :value="employee.leave_balances">
                <Column field="leave_type" header="Leave Type" />
                <Column field="total_days" header="Total Days" />
                <Column field="used_days" header="Used Days" />
                <Column field="remaining_days" header="Remaining">
                  <template #body="{ data }">
                    <Badge :value="data.remaining_days" :severity="getBalanceSeverity(data.remaining_days)" />
                  </template>
                </Column>
              </DataTable>
            </template>
          </Card>

          <Card>
            <template #title>Recent Activity</template>
            <template #content>
              <Timeline :value="employee.activities" align="left" class="w-full">
                <template #content="slotProps">
                  <p class="text-sm">{{ slotProps.item.description }}</p>
                  <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ formatDate(slotProps.item.date) }}</p>
                </template>
              </Timeline>
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
import Badge from 'primevue/badge'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Divider from 'primevue/divider'
import Timeline from 'primevue/timeline'

defineProps({
  employee: Object
})

const getStatusSeverity = (status) => {
  const map = { active: 'success', inactive: 'danger', on_leave: 'warning' }
  return map[status] || 'info'
}

const getBalanceSeverity = (days) => {
  if (days <= 2) return 'danger'
  if (days <= 5) return 'warning'
  return 'success'
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}
</script>
