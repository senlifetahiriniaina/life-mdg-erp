<template>
  <AppLayout>
    <Head title="Shift Schedule" />

    <!-- Chantier 32.17 (HR deep 14-layer audit): this real, routed page
         never called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Shift Schedule
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Recurring weekly shift templates assigned to employees
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="Assign Shift"
          @click="openCreateDialog"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex flex-wrap gap-3">
        <Select
          v-model="filters.employee_id"
          :options="employees"
          option-label="full_name"
          option-value="id"
          placeholder="All Employees"
          show-clear
          filter
          class="w-64"
          @change="loadShifts"
        />
        <Select
          v-model="filters.status"
          :options="['active', 'inactive']"
          placeholder="All Statuses"
          show-clear
          class="w-48"
          @change="loadShifts"
        />
      </div>

      <!-- Shift templates table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
        <DataTable :value="shifts" :loading="loading" paginator :rows="perPage" :total-records="total" lazy @page="onPage">
          <Column field="employee" header="Employee">
            <template #body="{ data }">
              {{ data.employee ? `${data.employee.first_name} ${data.employee.last_name}` : '—' }}
            </template>
          </Column>
          <Column field="shift_name" header="Shift" />
          <Column header="Days">
            <template #body="{ data }">
              <div class="flex gap-1 flex-wrap">
                <Tag
                  v-for="d in data.days_of_week || []"
                  :key="d"
                  :value="DAY_LABELS[d] ?? d"
                  severity="secondary"
                />
              </div>
            </template>
          </Column>
          <Column header="Time">
            <template #body="{ data }">
              {{ formatTime(data.start_time) }} – {{ formatTime(data.end_time) }}
            </template>
          </Column>
          <Column field="working_hours" header="Hours" />
          <Column header="Effective">
            <template #body="{ data }">
              {{ data.effective_from }}<span v-if="data.effective_to"> → {{ data.effective_to }}</span>
            </template>
          </Column>
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="data.status === 'active' ? 'success' : 'secondary'" />
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              <i class="pi pi-calendar text-4xl mb-3 block" />
              <p>No shift schedules yet</p>
            </div>
          </template>
        </DataTable>
      </div>

      <!-- Assign Shift Dialog -->
      <Dialog v-model:visible="showCreateDialog" header="Assign Shift" modal class="w-full max-w-2xl">
        <form @submit.prevent="saveShift" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Employee</label>
            <Select
              v-model="newShift.employee_id"
              :options="employees"
              option-label="full_name"
              option-value="id"
              placeholder="Select employee"
              filter
              required
              class="w-full"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Shift Name</label>
              <InputText v-model="newShift.shift_name" placeholder="Morning" required class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Shift Code</label>
              <InputText v-model="newShift.shift_code" placeholder="MORN" class="w-full" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Start Time</label>
              <InputMask v-model="newShift.start_time" mask="99:99:99" placeholder="09:00:00" required />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">End Time</label>
              <InputMask v-model="newShift.end_time" mask="99:99:99" placeholder="17:00:00" required />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Working Hours</label>
            <InputNumber v-model="newShift.working_hours" :min="1" :max="24" required class="w-full" />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Days of Week</label>
            <div class="flex gap-2 flex-wrap">
              <ToggleButton
                v-for="(label, day) in DAY_LABELS"
                :key="day"
                :model-value="newShift.days_of_week.includes(Number(day))"
                :on-label="label"
                :off-label="label"
                @update:model-value="toggleDay(Number(day))"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Effective From</label>
              <Calendar v-model="newShift.effective_from" show-icon date-format="yy-mm-dd" required />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Effective To (optional)</label>
              <Calendar v-model="newShift.effective_to" show-icon date-format="yy-mm-dd" show-clear />
            </div>
          </div>

          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
              <ToggleSwitch v-model="newShift.is_night_shift" input-id="night" />
              <label for="night" class="text-sm cursor-pointer">Night shift</label>
            </div>
            <div class="flex items-center gap-2">
              <ToggleSwitch v-model="newShift.is_flexible" input-id="flexible" />
              <label for="flexible" class="text-sm cursor-pointer">Flexible</label>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <Textarea v-model="newShift.notes" placeholder="Add any notes..." rows="2" class="w-full" />
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <Button type="button" label="Cancel" severity="secondary" @click="showCreateDialog = false" />
            <Button type="submit" label="Assign Shift" icon="pi pi-check" :loading="savingShift" />
          </div>
        </form>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Calendar from 'primevue/calendar'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import InputMask from 'primevue/inputmask'
import Textarea from 'primevue/textarea'
import ToggleButton from 'primevue/togglebutton'
import ToggleSwitch from 'primevue/toggleswitch'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

// Chantier 8.3: this page originally modeled a per-date monthly calendar
// (a Shift with a single `date` field), but the only real backend —
// Modules\HR\Models\ShiftSchedule, behind GET/POST /api/v1/hr/shifts — models
// RECURRING WEEKLY TEMPLATES (days_of_week + effective_from/to), with no
// per-date instance concept at all. Rewritten to manage the real template
// shape instead of a calendar view the backend can't support.

interface ShiftScheduleRow {
  id: number
  employee_id: number
  employee?: { id: number; first_name: string; last_name: string }
  shift_name: string
  shift_code: string | null
  start_time: string
  end_time: string
  working_hours: number
  days_of_week: number[]
  is_night_shift: boolean
  is_flexible: boolean
  effective_from: string
  effective_to: string | null
  status: string
}

interface Employee {
  id: number
  full_name: string
}

const DAY_LABELS: Record<number, string> = {
  1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun',
}

// Chantier 32.17 (HR deep 14-layer audit): see the AIAssistantPanel comment
// in the template — this page never called useAiAssistant() at all before.
const showAiPanel = ref(true)
const { guidance } = useAiAssistant('HR', 'manage_shifts')

const shifts = ref<ShiftScheduleRow[]>([])
const employees = ref<Employee[]>([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const showCreateDialog = ref(false)
const savingShift = ref(false)

const filters = ref({
  employee_id: null as number | null,
  status: null as string | null,
})

const newShift = ref({
  employee_id: null as number | null,
  shift_name: '',
  shift_code: '',
  start_time: '09:00:00',
  end_time: '17:00:00',
  working_hours: 8,
  days_of_week: [1, 2, 3, 4, 5] as number[],
  is_night_shift: false,
  is_flexible: false,
  effective_from: new Date(),
  effective_to: null as Date | null,
  notes: '',
})

const formatTime = (time: string): string => (time || '').slice(0, 5)

const toggleDay = (day: number) => {
  const idx = newShift.value.days_of_week.indexOf(day)
  if (idx === -1) {
    newShift.value.days_of_week.push(day)
  } else {
    newShift.value.days_of_week.splice(idx, 1)
  }
}

const loadEmployees = async () => {
  const { data } = await axios.get('/api/v1/hr/employees', { params: { per_page: 200, status: 'active' } })
  employees.value = data.data ?? []
}

const loadShifts = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value }
    if (filters.value.employee_id) params.employee_id = filters.value.employee_id
    if (filters.value.status) params.status = filters.value.status

    const { data } = await axios.get('/api/v1/hr/shifts', { params })
    shifts.value = data.data ?? []
    total.value = data.total ?? shifts.value.length
    perPage.value = data.per_page ?? perPage.value
  } finally {
    loading.value = false
  }
}

const onPage = (event: { page: number }) => {
  page.value = event.page + 1
  loadShifts()
}

const openCreateDialog = () => {
  newShift.value = {
    employee_id: null,
    shift_name: '',
    shift_code: '',
    start_time: '09:00:00',
    end_time: '17:00:00',
    working_hours: 8,
    days_of_week: [1, 2, 3, 4, 5],
    is_night_shift: false,
    is_flexible: false,
    effective_from: new Date(),
    effective_to: null,
    notes: '',
  }
  showCreateDialog.value = true
}

const toDateString = (d: Date | null): string | undefined =>
  d ? d.toISOString().split('T')[0] : undefined

const saveShift = async () => {
  if (!newShift.value.employee_id || !newShift.value.shift_name) return

  savingShift.value = true
  try {
    await axios.post('/api/v1/hr/shifts', {
      employee_id: newShift.value.employee_id,
      shift_name: newShift.value.shift_name,
      shift_code: newShift.value.shift_code || undefined,
      start_time: newShift.value.start_time,
      end_time: newShift.value.end_time,
      working_hours: newShift.value.working_hours,
      days_of_week: newShift.value.days_of_week,
      is_night_shift: newShift.value.is_night_shift,
      is_flexible: newShift.value.is_flexible,
      effective_from: toDateString(newShift.value.effective_from),
      effective_to: toDateString(newShift.value.effective_to),
      notes: newShift.value.notes || undefined,
    })
    showCreateDialog.value = false
    await loadShifts()
  } finally {
    savingShift.value = false
  }
}

onMounted(async () => {
  await loadEmployees()
  await loadShifts()
})
</script>
