<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Dashboard</h2>
      <Button
        icon="pi pi-refresh"
        rounded
        text
        @click="refreshData"
        :loading="isLoading"
      />
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-4">
      <Skeleton height="200px" />
      <Skeleton height="200px" />
      <Skeleton height="200px" />
    </div>

    <!-- Error State -->
    <div v-else-if="isError" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 rounded">
      <p class="text-red-800">
        <strong>Error loading dashboard:</strong> {{ error?.message }}
      </p>
      <Button label="Retry" @click="refreshData" class="mt-4" />
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card class="bg-primary-50 dark:bg-primary-900/20">
          <template #content>
            <div class="text-sm text-surface-600 dark:text-surface-400">Total Contacts</div>
            <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ contacts.length }}</div>
          </template>
        </Card>

        <Card class="bg-green-50 dark:bg-green-900/20">
          <template #content>
            <div class="text-sm text-surface-600 dark:text-surface-400">Recent Activities</div>
            <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ activities.length }}</div>
          </template>
        </Card>

        <Card class="bg-violet-50 dark:bg-violet-900/20">
          <template #content>
            <div class="text-sm text-surface-600 dark:text-surface-400">Revenue</div>
            <div class="text-3xl font-bold text-violet-700 dark:text-violet-300">
              ${{ summary?.revenue || '0' }}
            </div>
          </template>
        </Card>

        <Card class="bg-amber-50 dark:bg-amber-900/20">
          <template #content>
            <div class="text-sm text-surface-600 dark:text-surface-400">Conversion Rate</div>
            <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">
              {{ summary?.conversion_rate || '0' }}%
            </div>
          </template>
        </Card>
      </div>

      <!-- Recent Contacts Table -->
      <Card>
        <template #title>Recent Contacts</template>
        <template #content>
          <DataTable :value="contacts" :rows="10" paginator>
            <Column field="name" header="Name" />
            <Column field="email" header="Email" />
            <Column field="company" header="Company" />
            <Column field="stage" header="Stage">
              <template #body="{ data }">
                <Tag
                  :value="data.stage"
                  :severity="getStageSeverity(data.stage)"
                />
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>

      <!-- Recent Activities -->
      <Card>
        <template #title>Recent Activities</template>
        <template #content>
          <div class="space-y-3">
            <div
              v-for="activity in activities"
              :key="activity.id"
              class="flex items-start gap-3 pb-3 border-b last:border-0"
            >
              <i :class="`pi pi-${getActivityIcon(activity.type)}`" class="text-surface-400 dark:text-surface-500" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ activity.description }}</p>
                <p class="text-xs text-surface-500 dark:text-surface-400">{{ formatDate(activity.created_at) }}</p>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDashboardData } from '@/composables/useDashboardData'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'

// Fetch all dashboard data in parallel (no waterfall!)
const {
  contacts,
  activities,
  stats,
  summary,
  isLoading,
  isError,
  error,
  refetch: refreshData,
} = useDashboardData()

// Helpers
const getStageSeverity = (stage: string) => {
  const severityMap: Record<string, string> = {
    lead: 'info',
    prospect: 'warning',
    customer: 'success',
    lost: 'danger',
  }
  return severityMap[stage.toLowerCase()] || 'info'
}

const getActivityIcon = (type: string) => {
  const iconMap: Record<string, string> = {
    call: 'phone',
    email: 'envelope',
    meeting: 'calendar',
    note: 'file-text',
    deal: 'shopping-cart',
  }
  return iconMap[type.toLowerCase()] || 'circle-fill'
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<style scoped>
/* Optional: Add custom styles if needed */
</style>
