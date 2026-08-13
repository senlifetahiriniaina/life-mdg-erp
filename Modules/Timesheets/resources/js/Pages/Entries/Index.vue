<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">Timesheet Entries</h1>
        <Button
          label="New Entry"
          icon="pi pi-plus"
          class="p-button-primary"
          @click="navigateTo('/timesheets/entries/new')"
        />
      </div>
    </template>

    <Card class="bg-surface-0 dark:bg-surface-800">
      <template #header>
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">All Entries</h2>
          <div class="flex gap-3">
            <Dropdown
              v-model="selectedStatus"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              placeholder="Filter by status"
              @change="filterEntries"
              class="w-32"
            />
            <Button
              icon="pi pi-refresh"
              class="p-button-text"
              @click="refreshEntries"
            />
          </div>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredEntries"
          :loading="entriesLoading"
          :paginator="true"
          rows="10"
          responsive-layout="scroll"
        >
          <Column field="entry_date" header="Date" />
          <Column field="hours_worked" header="Hours" class="w-24" />
          <Column field="description" header="Description" />
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Badge :value="data.status" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Actions" class="w-32">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  icon="pi pi-pencil"
                  class="p-button-rounded p-button-text"
                  @click="editEntry(data.id)"
                  v-tooltip.top="'Edit'"
                />
                <Button
                  icon="pi pi-trash"
                  class="p-button-rounded p-button-danger p-button-text"
                  @click="deleteEntry(data.id)"
                  v-tooltip.top="'Delete'"
                  :disabled="data.status !== 'draft'"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTimesheetsStore } from '../../stores/timesheetsStore'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'

const router = useRouter()
const store = useTimesheetsStore()

const selectedStatus = ref(null)
const statusOptions = [
  { label: 'All', value: null },
  { label: 'Draft', value: 'draft' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
]

const entries = computed(() => store.entries)
const entriesLoading = computed(() => store.entriesLoading)

const filteredEntries = computed(() => {
  if (!selectedStatus.value) {
    return entries.value
  }
  return entries.value.filter(e => e.status === selectedStatus.value)
})

const getStatusSeverity = (status: string) => {
  const severities: Record<string, string> = {
    draft: 'info',
    submitted: 'warning',
    approved: 'success',
    rejected: 'danger',
  }
  return severities[status] || 'info'
}

const navigateTo = (path: string) => {
  router.push(path)
}

const editEntry = (id: number) => {
  router.push(`/timesheets/entries/${id}/edit`)
}

const deleteEntry = async (id: number) => {
  if (confirm('Are you sure you want to delete this entry?')) {
    await store.deleteEntryFromStore(id)
  }
}

const filterEntries = () => {
  // Filtering is done in the computed property
}

const refreshEntries = () => {
  store.loadEntries(true)
}

onMounted(async () => {
  await store.loadEntries()
})
</script>
