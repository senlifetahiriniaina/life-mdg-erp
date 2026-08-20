<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Timesheets Dashboard</h1>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <!-- Total Hours Card -->
      <Card class="bg-white dark:bg-surface-800">
        <template #content>
          <div class="text-center">
            <p class="text-surface-600 dark:text-surface-400 text-sm font-medium">Total Hours</p>
            <p class="text-3xl font-bold text-primary-700 dark:text-primary-300 mt-2">{{ totalHours.toFixed(1) }}</p>
            <p class="text-xs text-surface-500 dark:text-surface-400 mt-2">{{ entries.length }} entries</p>
          </div>
        </template>
      </Card>

      <!-- Approved Hours Card -->
      <Card class="bg-white dark:bg-surface-800">
        <template #content>
          <div class="text-center">
            <p class="text-surface-600 dark:text-surface-400 text-sm font-medium">Approved Hours</p>
            <p class="text-3xl font-bold text-green-700 dark:text-green-300 mt-2">{{ approvedHours.toFixed(1) }}</p>
            <p class="text-xs text-surface-500 dark:text-surface-400 mt-2">{{ approvedEntries.length }} approved</p>
          </div>
        </template>
      </Card>

      <!-- Pending Approvals Card -->
      <Card class="bg-white dark:bg-surface-800">
        <template #content>
          <div class="text-center">
            <p class="text-surface-600 dark:text-surface-400 text-sm font-medium">Pending Approvals</p>
            <p class="text-3xl font-bold text-amber-600 mt-2">{{ pendingApprovals.length }}</p>
            <p class="text-xs text-surface-500 dark:text-surface-400 mt-2">Awaiting review</p>
          </div>
        </template>
      </Card>

      <!-- Total Cost Card -->
      <Card class="bg-white dark:bg-surface-800">
        <template #content>
          <div class="text-center">
            <p class="text-surface-600 dark:text-surface-400 text-sm font-medium">Total Cost</p>
            <p class="text-3xl font-bold text-violet-700 dark:text-violet-300 mt-2">${{ totalCost.toFixed(2) }}</p>
            <p class="text-xs text-surface-500 dark:text-surface-400 mt-2">{{ allocations.length }} allocations</p>
          </div>
        </template>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Recent Entries -->
      <Card class="bg-white dark:bg-surface-800">
        <template #header>
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Recent Entries</h2>
        </template>
        <template #content>
          <div v-if="entries.length === 0" class="text-center py-8">
            <p class="text-surface-500 dark:text-surface-400">No timesheet entries yet</p>
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="entry in entries.slice(0, 5)"
              :key="entry.id"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-surface-800 rounded"
            >
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ entry.entry_date }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400">{{ entry.hours_worked }} hours</p>
              </div>
              <Badge :value="entry.status" :severity="getStatusSeverity(entry.status)" />
            </div>
          </div>
        </template>
      </Card>

      <!-- Active Projects -->
      <Card class="bg-white dark:bg-surface-800">
        <template #header>
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Active Projects</h2>
        </template>
        <template #content>
          <div v-if="activeProjects.length === 0" class="text-center py-8">
            <p class="text-surface-500 dark:text-surface-400">No active projects</p>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="project in activeProjects.slice(0, 5)"
              :key="project.id"
              class="p-3 bg-gray-50 dark:bg-surface-800 rounded"
            >
              <div class="flex items-center justify-between mb-2">
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ project.name }}</p>
                <Badge
                  v-if="project.is_over_budget"
                  value="Over Budget"
                  severity="danger"
                />
              </div>
              <ProgressBar :value="project.percentage_used" show-value />
              <p class="text-xs text-surface-600 dark:text-surface-400 mt-2">
                {{ project.hours_tracked }} / {{ project.budget_hours }} hours
              </p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Quick Actions -->
    <Card class="bg-white dark:bg-surface-800">
      <template #header>
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Quick Actions</h2>
      </template>
      <template #content>
        <div class="flex gap-3 flex-wrap">
          <Button
            label="New Entry"
            icon="pi pi-plus"
            class="p-button-primary"
            @click="navigateTo('/timesheets/entries/create')"
          />
          <Button
            label="View All Entries"
            icon="pi pi-list"
            @click="navigateTo('/timesheets/entries')"
          />
          <Button
            label="Manage Projects"
            icon="pi pi-briefcase"
            @click="navigateTo('/timesheets/projects')"
          />
          <Button
            label="Pending Approvals"
            icon="pi pi-inbox"
            class="p-button-warning"
            @click="navigateTo('/timesheets/entries?status=submitted')"
          />
        </div>
      </template>
    </Card>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useTimesheetsStore } from '../stores/timesheetsStore'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'

const store = useTimesheetsStore()

const entries = computed(() => store.entries)
const allocations = computed(() => store.allocations)
const projects = computed(() => store.projects)
const pendingApprovals = computed(() => store.pendingApprovals)
const approvedEntries = computed(() => store.approvedEntries)
const activeProjects = computed(() => store.activeProjects)
const totalHours = computed(() => store.totalHours)
const approvedHours = computed(() => store.approvedHours)
const totalCost = computed(() => store.totalCost)

const getStatusSeverity = (status: string) => {
  const severities: Record<string, string> = {
    draft: 'info',
    submitted: 'warning',
    approved: 'success',
    rejected: 'danger',
  }
  return severities[status] || 'info'
}

// Chantier 19 (Lot 2): "New Entry" pointed at /timesheets/entries/new — the
// real web route is /timesheets/entries/create (routes/web.php) — a 404 on
// every click. "Pending Approvals" pointed at /timesheets/approvals, which
// has never had a route or page anywhere in this module — repointed at the
// real, already-routed entries list, pre-filtered to submitted status
// (TimeEntries/Index.vue reads the initial ?status= off the URL).
const navigateTo = (path: string) => {
  router.visit(path)
}

onMounted(async () => {
  await Promise.all([
    store.loadEntries(),
    store.loadProjects(),
    store.loadAllocations(),
    store.loadPendingApprovals(),
    store.loadMetrics(),
  ])
})
</script>
