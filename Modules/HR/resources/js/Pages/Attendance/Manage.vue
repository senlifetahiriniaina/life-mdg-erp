<template>
  <AppLayout>
    <Head title="Attendance Tracking" />

    <!-- Chantier 32.17 (HR deep 14-layer audit): this real, routed page
         never called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Attendance Tracking
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Monitor employee attendance and track patterns
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="Mark Attendance"
          @click="showMarkDialog = true"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex items-center gap-4 flex-wrap">
        <Calendar
          v-model="selectedDate"
          date-format="yy-mm-dd"
          show-icon
          class="w-56"
          @date-select="loadAttendance"
        />
        <Select
          v-model="selectedDepartment"
          :options="departments"
          option-label="name"
          option-value="id"
          placeholder="All Departments"
          show-clear
          class="w-56"
          @change="loadAttendance"
        />
        <Select
          v-model="statusFilter"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          placeholder="All Status"
          show-clear
          class="w-48"
          @change="loadAttendance"
        />
      </div>

      <!-- Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">Present</p>
              <p class="text-3xl font-bold text-green-600 mt-2">{{ stats.present }}</p>
            </div>
            <i class="pi pi-check-circle text-2xl text-green-500 opacity-50" />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">Absent</p>
              <p class="text-3xl font-bold text-red-600 mt-2">{{ stats.absent }}</p>
            </div>
            <i class="pi pi-times-circle text-2xl text-red-500 opacity-50" />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">Late</p>
              <p class="text-3xl font-bold text-yellow-600 mt-2">{{ stats.late }}</p>
            </div>
            <i class="pi pi-hourglass text-2xl text-yellow-500 opacity-50" />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">On Leave</p>
              <p class="text-3xl font-bold text-blue-600 mt-2">{{ stats.onLeave }}</p>
            </div>
            <i class="pi pi-calendar text-2xl text-blue-500 opacity-50" />
          </div>
        </div>
      </div>

      <!-- Attendance Table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="attendanceRecords"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
          scroll-height="600px"
          scroll-direction="vertical"
          paginator
          :rows="20"
          :page-link-size="5"
          responsive-layout="scroll"
        >
          <Column field="employee_name" header="Employee" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                  <span class="text-xs font-bold text-primary-700 dark:text-primary-300">
                    {{ data.employee_name.charAt(0) }}
                  </span>
                </div>
                <span class="font-medium text-surface-900 dark:text-surface-50">
                  {{ data.employee_name }}
                </span>
              </div>
            </template>
          </Column>
          <Column field="department" header="Department">
            <template #body="{ data }">
              {{ data.department ?? '—' }}
            </template>
          </Column>
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="getStatusSeverity(data.status)"
              />
            </template>
          </Column>
          <Column field="check_in" header="Check In">
            <template #body="{ data }">
              <span v-if="data.check_in" class="text-green-600 dark:text-green-400">
                {{ formatTime(data.check_in) }}
              </span>
              <span v-else class="text-surface-400">—</span>
            </template>
          </Column>
          <Column field="check_out" header="Check Out">
            <template #body="{ data }">
              <span v-if="data.check_out" class="text-orange-600 dark:text-orange-400">
                {{ formatTime(data.check_out) }}
              </span>
              <span v-else class="text-surface-400">—</span>
            </template>
          </Column>
          <Column field="duration" header="Duration">
            <template #body="{ data }">
              <span v-if="data.duration" class="font-medium text-surface-900 dark:text-surface-50">
                {{ data.duration }}h
              </span>
              <span v-else class="text-surface-400">—</span>
            </template>
          </Column>
          <Column header="Actions" style="width: 8rem">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-pencil"
                  outlined
                  size="small"
                  @click="editAttendance(data)"
                />
                <Button
                  icon="pi pi-trash"
                  outlined
                  severity="danger"
                  size="small"
                  @click="deleteAttendance(data)"
                />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              No attendance records found.
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Mark Attendance Dialog -->
    <Dialog
      v-model:visible="showMarkDialog"
      :header="editingId ? 'Edit Attendance' : 'Mark Attendance'"
      modal
      class="w-full max-w-2xl"
      @hide="resetForm"
    >
      <form @submit.prevent="saveAttendance" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Employee</label>
          <Select
            v-model="newAttendance.employee_id"
            :options="employees"
            option-label="full_name"
            option-value="id"
            placeholder="Select employee"
            filter
            required
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Date</label>
            <Calendar
              v-model="newAttendance.date"
              show-icon
              date-format="yy-mm-dd"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <Select
              v-model="newAttendance.status"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              placeholder="Select status"
              required
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Check In Time</label>
            <InputMask
              v-model="newAttendance.check_in"
              mask="99:99"
              placeholder="HH:MM"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Check Out Time</label>
            <InputMask
              v-model="newAttendance.check_out"
              mask="99:99"
              placeholder="HH:MM"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Notes</label>
          <Textarea
            v-model="newAttendance.notes"
            placeholder="Add any notes..."
            rows="2"
          />
        </div>

        <div class="flex justify-end gap-2 pt-4">
          <Button
            label="Cancel"
            severity="secondary"
            @click="showMarkDialog = false"
          />
          <Button
            :label="editingId ? 'Update' : 'Save'"
            icon="pi pi-check"
            :loading="saving"
          />
        </div>
      </form>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import Calendar from 'primevue/datepicker'
import InputMask from 'primevue/inputmask'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

interface Department {
  id: number
  name: string
}

interface Employee {
  id: number
  full_name: string
}

interface AttendanceRecord {
  id: number
  employee_id: number
  employee_name: string
  department: string | null
  status: string
  check_in: string | null
  check_out: string | null
  duration: number | null
  date: string
  notes: string | null
}

const selectedDate = ref(new Date())
const selectedDepartment = ref<number | null>(null)
const statusFilter = ref<string | null>(null)
const departments = ref<Department[]>([])
const employees = ref<Employee[]>([])
const attendanceRecords = ref<AttendanceRecord[]>([])
const loading = ref(false)
const showMarkDialog = ref(false)
const saving = ref(false)

const stats = computed(() => {
  return {
    present: attendanceRecords.value.filter(r => r.status === 'present').length,
    absent: attendanceRecords.value.filter(r => r.status === 'absent').length,
    late: attendanceRecords.value.filter(r => r.status === 'late').length,
    onLeave: attendanceRecords.value.filter(r => r.status === 'on_leave').length,
  }
})

const statusOptions = [
  { label: 'Present', value: 'present' },
  { label: 'Absent', value: 'absent' },
  { label: 'Late', value: 'late' },
  { label: 'On Leave', value: 'on_leave' },
  { label: 'Half Day', value: 'half_day' },
]

const newAttendance = ref({
  employee_id: null as number | null,
  date: new Date(),
  status: 'present',
  check_in: '',
  check_out: '',
  notes: '',
})

const getStatusSeverity = (status: string): string => {
  switch (status) {
    case 'present': return 'success'
    case 'absent': return 'danger'
    case 'late': return 'warning'
    case 'on_leave': return 'info'
    case 'half_day': return 'warning'
    default: return 'secondary'
  }
}

const formatTime = (time: string): string => {
  if (!time) return ''
  const [hours, minutes] = time.split(':')
  return `${hours}:${minutes}`
}

// Chantier 32.17 (HR deep 14-layer audit): every mutating call on this page
// used raw fetch() with no CSRF header — this app runs Sanctum's
// statefulApi(), which activates real CSRF verification on same-origin
// browser requests; axios auto-attaches the X-XSRF-TOKEN header by default,
// raw fetch() does not. Confirmed the same bug class already found and
// fixed for 9 Inventory/Logistics pages at Chantier 19 Lots 4-5 — invisible
// to Pest (VerifyCsrfToken bypasses in APP_ENV=testing regardless of
// headers sent), only surfaces against a real browser-shaped request.
// Switched every call on this page to axios.
// Chantier 32.17 (HR deep 14-layer audit): see the AIAssistantPanel comment
// in the template — this page never called useAiAssistant() at all before.
const showAiPanel = ref(true)
const { guidance } = useAiAssistant('HR', 'manage_attendance')

const editingId = ref<number | null>(null)

const loadAttendance = async () => {
  loading.value = true
  try {
    const params: Record<string, string> = {
      date: selectedDate.value.toISOString().split('T')[0],
    }
    if (selectedDepartment.value) {
      params.department_id = String(selectedDepartment.value)
    }
    if (statusFilter.value) {
      params.status = statusFilter.value
    }

    const { data } = await axios.get('/api/v1/hr/attendance', { params })
    attendanceRecords.value = data.data || []
  } catch (error) {
    console.error('Failed to load attendance:', error)
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  editingId.value = null
  newAttendance.value = {
    employee_id: null,
    date: new Date(),
    status: 'present',
    check_in: '',
    check_out: '',
    notes: '',
  }
}

const saveAttendance = async () => {
  if (!newAttendance.value.employee_id) return

  saving.value = true
  try {
    const payload = {
      employee_id: newAttendance.value.employee_id,
      date: newAttendance.value.date instanceof Date
        ? newAttendance.value.date.toISOString().split('T')[0]
        : newAttendance.value.date,
      status: newAttendance.value.status,
      check_in: newAttendance.value.check_in,
      check_out: newAttendance.value.check_out,
      notes: newAttendance.value.notes,
    }

    if (editingId.value) {
      await axios.put(`/api/v1/hr/attendance/${editingId.value}`, payload)
    } else {
      await axios.post('/api/v1/hr/attendance', payload)
    }

    showMarkDialog.value = false
    resetForm()
    await loadAttendance()
  } catch (error) {
    console.error('Error saving attendance:', error)
  } finally {
    saving.value = false
  }
}

// Chantier 32.17: this was a bare console.log() — the real "Edit" button on
// every row did literally nothing visible to the user. Now opens the same
// dialog used for creation, pre-filled, and saveAttendance() branches to a
// PUT against the real (now-routed) update endpoint.
const editAttendance = (record: AttendanceRecord) => {
  editingId.value = record.id
  newAttendance.value = {
    employee_id: record.employee_id ?? null,
    date: record.date ? new Date(record.date) : new Date(),
    status: record.status,
    check_in: record.check_in ?? '',
    check_out: record.check_out ?? '',
    notes: record.notes ?? '',
  }
  showMarkDialog.value = true
}

const deleteAttendance = async (record: AttendanceRecord) => {
  if (!confirm('Delete this attendance record?')) return

  try {
    await axios.delete(`/api/v1/hr/attendance/${record.id}`)
    await loadAttendance()
  } catch (error) {
    console.error('Error deleting attendance:', error)
  }
}

onMounted(() => {
  Promise.all([
    axios.get('/api/v1/hr/departments').then(({ data }) => {
      departments.value = data.data || []
    }),
    axios.get('/api/v1/hr/employees').then(({ data }) => {
      employees.value = data.data || []
    }),
    loadAttendance(),
  ]).catch(error => {
    console.error('Failed to load initial data:', error)
  })
})
</script>
