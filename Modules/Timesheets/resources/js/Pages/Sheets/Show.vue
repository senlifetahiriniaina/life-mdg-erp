<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Timesheet</h1>
          <p class="text-surface-600 dark:text-surface-400 mt-1">
            {{ formatDate(sheet.period_start) }} to {{ formatDate(sheet.period_end) }}
          </p>
        </div>
        <div class="flex gap-2">
          <Link
            v-if="sheet.status === 'draft'"
            :href="`/timesheets/sheets/${sheet.id}/edit`"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          >
            Edit
          </Link>
          <button
            v-if="canSubmit && sheet.status === 'draft'"
            @click="submitSheet"
            :disabled="loading"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 transition"
          >
            Submit for Approval
          </button>
          <button
            v-if="canApprove && sheet.status === 'submitted'"
            @click="approveSheet"
            :disabled="loading"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 transition"
          >
            Approve
          </button>
          <button
            v-if="canApprove && sheet.status === 'submitted'"
            @click="rejectSheet"
            :disabled="loading"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 transition"
          >
            Reject
          </button>
        </div>
      </div>
    </template>

    <div class="grid grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="col-span-2 space-y-6">
        <!-- Summary Card -->
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Summary</h2>
          <div class="grid grid-cols-3 gap-4">
            <div class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-4">
              <p class="text-sm text-surface-600 dark:text-surface-400">Total Hours</p>
              <p class="text-2xl font-bold text-primary-700 dark:text-primary-300">{{ sheet.total_hours }}</p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
              <p class="text-sm text-surface-600 dark:text-surface-400">Billable Hours</p>
              <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ sheet.billable_hours }}</p>
            </div>
            <div class="bg-amber-50 rounded-lg p-4">
              <p class="text-sm text-surface-600 dark:text-surface-400">Billable Amount</p>
              <p class="text-2xl font-bold text-amber-600">${{ billableAmount }}</p>
            </div>
          </div>
        </div>

        <!-- Time Entries -->
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Time Entries ({{ entries.length }})</h2>

          <div v-if="entries.length === 0" class="text-center py-8 text-surface-500 dark:text-surface-400">
            No time entries for this period
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="entry in entries"
              :key="entry.id"
              class="border rounded-lg p-4 hover:bg-gray-50 dark:bg-surface-800 transition"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <span class="font-semibold">{{ entry.task_description }}</span>
                    <Badge :value="entry.billable ? 'Billable' : 'Non-billable'" :severity="entry.billable ? 'success' : 'secondary'" />
                  </div>
                  <p class="text-sm text-surface-600 dark:text-surface-400">
                    {{ formatDate(entry.work_date) }}
                    <span v-if="entry.project">• {{ entry.project.name }}</span>
                  </p>
                  <p class="text-sm text-surface-900 dark:text-surface-50 mt-1">
                    {{ entry.hours }} hours
                    <span v-if="entry.rate">• ${{ entry.rate }}/hr = ${{ (entry.hours * entry.rate).toFixed(2) }}</span>
                  </p>
                  <p v-if="entry.notes" class="text-xs text-surface-600 dark:text-surface-400 mt-2 italic">{{ entry.notes }}</p>
                </div>
                <div v-if="sheet.status === 'draft'" class="flex gap-2 ml-4">
                  <Link
                    :href="`/timesheets/entries/${entry.id}/edit`"
                    class="text-amber-600 hover:text-amber-800 text-sm"
                  >
                    Edit
                  </Link>
                  <button
                    @click="deleteEntry(entry.id)"
                    class="text-red-700 dark:text-red-300 hover:text-red-800 text-sm"
                  >
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>

          <Link
            v-if="sheet.status === 'draft'"
            :href="`/timesheets/entries/create?sheet_id=${sheet.id}`"
            class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          >
            Add Entry
          </Link>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Status Card -->
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-4">
          <p class="text-sm font-semibold text-surface-700 dark:text-surface-300 mb-2">Status</p>
          <Tag :value="sheet.status" :severity="statusSeverity(sheet.status)" class="text-lg" />
        </div>

        <!-- Employee Info -->
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-4">
          <p class="text-sm font-semibold text-surface-700 dark:text-surface-300 mb-2">Employee</p>
          <p class="text-surface-900 dark:text-surface-50 font-semibold">{{ sheet.employee?.name }}</p>
          <p class="text-xs text-surface-600 dark:text-surface-400">{{ sheet.employee?.email }}</p>
        </div>

        <!-- Submission Info -->
        <div v-if="sheet.submitted_at" class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-4">
          <p class="text-sm font-semibold text-surface-700 dark:text-surface-300 mb-2">Submitted</p>
          <p class="text-sm text-surface-900 dark:text-surface-50">{{ formatDate(sheet.submitted_at) }}</p>
          <p class="text-xs text-surface-600 dark:text-surface-400">by {{ sheet.submitter?.name }}</p>
        </div>

        <!-- Approval Info -->
        <div v-if="sheet.approved_at" class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-4 border-l-4 border-green-600">
          <p class="text-sm font-semibold text-green-700 mb-2">Approved</p>
          <p class="text-sm text-surface-900 dark:text-surface-50">{{ formatDate(sheet.approved_at) }}</p>
          <p class="text-xs text-surface-600 dark:text-surface-400">by {{ sheet.approver?.name }}</p>
        </div>

        <!-- Rejection Info -->
        <div v-if="sheet.rejected_reason" class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-4 border-l-4 border-red-600">
          <p class="text-sm font-semibold text-red-700 mb-2">Rejection Reason</p>
          <p class="text-xs text-surface-900 dark:text-surface-50">{{ sheet.rejected_reason }}</p>
        </div>

        <!-- Dates -->
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-4 space-y-2 text-sm">
          <div>
            <p class="text-xs text-surface-600 dark:text-surface-400">Period Start</p>
            <p class="font-semibold">{{ formatDate(sheet.period_start) }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-600 dark:text-surface-400">Period End</p>
            <p class="font-semibold">{{ formatDate(sheet.period_end) }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-600 dark:text-surface-400">Created</p>
            <p class="font-semibold">{{ formatDate(sheet.created_at) }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tag from 'primevue/tag'
import Badge from 'primevue/badge'
import { useRoleAccess } from '@/composables/useRoleAccess'

const props = defineProps({
  sheet: Object,
  entries: Array,
})

const page = usePage()
const user = page.props.auth.user
const { isAdmin, isElevated } = useRoleAccess()
const loading = ref(false)

const canSubmit = computed(() => {
  return user.id === props.sheet.employee_id || isAdmin.value
})

const canApprove = computed(() => isElevated.value)

const billableAmount = computed(() => {
  return props.entries
    .filter(e => e.billable)
    .reduce((sum, e) => sum + (parseFloat(e.hours || 0) * parseFloat(e.rate || 0)), 0)
    .toFixed(2)
})

const statusSeverity = (status) => {
  return {
    draft: 'warning',
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
  }[status] || 'secondary'
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const submitSheet = async () => {
  if (!confirm('Submit this timesheet for approval?')) return
  loading.value = true
  try {
    await axios.post(`/api/v1/timesheets/sheets/${props.sheet.id}/submit`)
    router.reload()
  } catch (error) {
    console.error('Error submitting sheet:', error)
  } finally {
    loading.value = false
  }
}

const approveSheet = async () => {
  if (!confirm('Approve this timesheet?')) return
  loading.value = true
  try {
    await axios.post(`/api/v1/timesheets/sheets/${props.sheet.id}/approve`)
    router.reload()
  } catch (error) {
    console.error('Error approving sheet:', error)
  } finally {
    loading.value = false
  }
}

const rejectSheet = async () => {
  const reason = prompt('Please provide a rejection reason:')
  if (!reason) return
  loading.value = true
  try {
    await axios.post(`/api/v1/timesheets/sheets/${props.sheet.id}/reject`, { reason })
    router.reload()
  } catch (error) {
    console.error('Error rejecting sheet:', error)
  } finally {
    loading.value = false
  }
}

const deleteEntry = async (id) => {
  if (!confirm('Remove this entry from the timesheet?')) return
  try {
    await axios.delete(`/api/v1/timesheets/entries/${id}`)
    router.reload()
  } catch (error) {
    console.error('Error deleting entry:', error)
  }
}
</script>
