<template>
  <AppLayout>
    <Head title="Shift Schedule" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Shift Schedule
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Manage employee shifts and schedules
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="Assign Shift"
          @click="showAssignShiftDialog = true"
        />
      </div>

      <!-- Controls -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <Button
            icon="pi pi-chevron-left"
            text
            rounded
            @click="previousMonth"
          />
          <h2 class="text-lg font-semibold w-48 text-center">
            {{ formatMonth(currentDate) }}
          </h2>
          <Button
            icon="pi pi-chevron-right"
            text
            rounded
            @click="nextMonth"
          />
          <Button
            icon="pi pi-refresh"
            text
            rounded
            @click="goToToday"
          />
        </div>

        <div class="flex gap-2">
          <Select
            v-model="selectedDepartment"
            :options="departments"
            option-label="name"
            option-value="id"
            placeholder="All Departments"
            show-clear
            class="w-56"
            @change="loadSchedules"
          />
          <ToggleButton
            v-model="viewMode"
            on-label="Month"
            off-label="Week"
            on-icon="pi pi-calendar"
            off-icon="pi pi-arrow-right"
          />
        </div>
      </div>

      <!-- Month View Calendar -->
      <div v-if="viewMode" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <div class="grid grid-cols-7 gap-2 mb-2">
          <div
            v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
            :key="day"
            class="text-center font-semibold text-surface-600 dark:text-surface-300 py-2"
          >
            {{ day }}
          </div>

          <div
            v-for="day in days"
            :key="day.date"
            class="bg-surface-50 dark:bg-surface-700/30 rounded-lg p-2 min-h-32 relative border border-surface-200 dark:border-surface-700"
            :class="{ 'ring-2 ring-primary-400': isToday(day.date) }"
          >
            <!-- Day header -->
            <div class="flex items-start justify-between mb-2">
              <span class="text-sm font-medium" :class="getDateClass(day.date)">
                {{ day.day }}
              </span>
              <span v-if="day.weekendClass" class="text-xs text-orange-600 font-semibold">
                {{ day.weekendClass }}
              </span>
            </div>

            <!-- Shifts -->
            <div class="space-y-1 text-xs">
              <div
                v-for="shift in getShiftsForDate(day.date)"
                :key="shift.id"
                class="bg-blue-50 dark:bg-blue-900/40 border-l-4 border-blue-500 p-1.5 rounded truncate cursor-pointer hover:shadow-md transition"
                :title="shift.employee_name + ' - ' + shift.shift_type"
              >
                <p class="font-medium truncate text-blue-900 dark:text-blue-200">{{ shift.employee_name }}</p>
                <p class="text-blue-700 dark:text-blue-300">{{ shift.shift_type }}</p>
              </div>

              <Button
                v-if="!isDisabledDate(day.date)"
                icon="pi pi-plus"
                text
                size="small"
                class="w-full text-xs mt-2"
                @click="showShiftDialog(day.date)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Week View -->
      <div v-else class="space-y-4">
        <div v-for="employee in weekViewEmployees" :key="employee.id" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
          <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-200 dark:border-surface-700">
            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
              <span class="text-sm font-semibold text-primary-700 dark:text-primary-300">
                {{ employee.full_name.charAt(0) }}
              </span>
            </div>
            <div>
              <p class="font-medium text-surface-900 dark:text-surface-50">{{ employee.full_name }}</p>
              <p class="text-xs text-surface-500">{{ employee.department?.name }}</p>
            </div>
          </div>

          <!-- Week days -->
          <div class="grid grid-cols-7 gap-2">
            <div
              v-for="day in getWeekDays()"
              :key="day.date"
              class="bg-surface-50 dark:bg-surface-700/30 rounded p-2 min-h-24 border border-surface-200 dark:border-surface-700"
            >
              <p class="text-xs font-semibold mb-1">{{ day.dayName }}</p>
              <p class="text-xs text-surface-500 mb-2">{{ formatDate(day.date) }}</p>

              <div
                v-for="shift in getEmployeeShiftsForDate(employee.id, day.date)"
                :key="shift.id"
                class="bg-green-50 dark:bg-green-900/40 border-l-4 border-green-500 p-1 rounded mb-1 text-xs"
              >
                <p class="font-medium text-green-900 dark:text-green-200">{{ shift.shift_type }}</p>
                <p class="text-green-700 dark:text-green-300">{{ shift.start_time }} - {{ shift.end_time }}</p>
              </div>

              <Button
                v-if="!getEmployeeShiftsForDate(employee.id, day.date).length"
                icon="pi pi-plus"
                text
                size="small"
                class="w-full text-xs"
                @click="showAssignShiftForEmployee(employee.id, day.date)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Assign Shift Dialog -->
      <Dialog
        v-model:visible="showAssignShiftDialog"
        header="Assign Shift"
        modal
        class="w-full max-w-2xl"
      >
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
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Shift Type</label>
              <Select
                v-model="newShift.shift_type"
                :options="shiftTypes"
                placeholder="Select shift type"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Date</label>
              <Calendar
                v-model="newShift.date"
                show-icon
                date-format="yy-mm-dd"
                required
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Start Time</label>
              <InputMask
                v-model="newShift.start_time"
                mask="99:99"
                placeholder="HH:MM"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">End Time</label>
              <InputMask
                v-model="newShift.end_time"
                mask="99:99"
                placeholder="HH:MM"
                required
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <Textarea
              v-model="newShift.notes"
              placeholder="Add any notes..."
              rows="2"
            />
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <Button
              label="Cancel"
              severity="secondary"
              @click="showAssignShiftDialog = false"
            />
            <Button
              label="Assign Shift"
              icon="pi pi-check"
              :loading="savingShift"
            />
          </div>
        </form>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Calendar from 'primevue/calendar'
import InputMask from 'primevue/inputmask'
import Textarea from 'primevue/textarea'
import ToggleButton from 'primevue/togglebutton'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Shift {
  id: number
  employee_id: number
  employee_name: string
  shift_type: string
  date: string
  start_time: string
  end_time: string
  notes: string | null
}

interface Employee {
  id: number
  full_name: string
  department?: { id: number; name: string }
}

interface Department {
  id: number
  name: string
}

const currentDate = ref(new Date())
const viewMode = ref(true) // true = month, false = week
const showAssignShiftDialog = ref(false)
const savingShift = ref(false)
const selectedDepartment = ref<number | null>(null)

const shifts = ref<Shift[]>([])
const employees = ref<Employee[]>([])
const departments = ref<Department[]>([])

const shiftTypes = ['Morning (6AM-2PM)', 'Afternoon (2PM-10PM)', 'Night (10PM-6AM)', 'Flexible', 'Off']

const newShift = ref({
  employee_id: null as number | null,
  shift_type: '',
  date: new Date(),
  start_time: '09:00',
  end_time: '17:00',
  notes: '',
})

const weekViewEmployees = computed(() =>
  selectedDepartment.value
    ? employees.value.filter(e => e.department?.id === selectedDepartment.value)
    : employees.value.slice(0, 5)
)

const days = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - firstDay.getDay())

  const days = []
  for (let i = 0; i < 42; i++) {
    const date = new Date(startDate)
    date.setDate(date.getDate() + i)
    days.push({
      date: date.toISOString().split('T')[0],
      day: date.getDate(),
      weekendClass: date.getDay() === 0 || date.getDay() === 6 ? 'Weekend' : '',
    })
  }
  return days
})

const formatMonth = (date: Date): string =>
  date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })

const formatDate = (date: string): string =>
  new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })

const isToday = (dateStr: string): boolean => {
  const today = new Date().toISOString().split('T')[0]
  return dateStr === today
}

const getDateClass = (dateStr: string): string => {
  const date = new Date(dateStr)
  const currentMonth = currentDate.value.getMonth()
  return date.getMonth() === currentMonth
    ? 'text-surface-900 dark:text-surface-50'
    : 'text-surface-400 dark:text-surface-500'
}

const isDisabledDate = (dateStr: string): boolean => {
  const date = new Date(dateStr)
  return date.getMonth() !== currentDate.value.getMonth()
}

const getShiftsForDate = (dateStr: string): Shift[] =>
  shifts.value.filter(s => s.date === dateStr)

const getWeekDays = () => {
  const days = []
  const current = new Date(currentDate.value)
  const first = current.getDate() - current.getDay()

  for (let i = 0; i < 7; i++) {
    const date = new Date(current.setDate(first + i))
    days.push({
      date: date.toISOString().split('T')[0],
      dayName: date.toLocaleDateString('en-US', { weekday: 'short' }),
    })
  }
  return days
}

const getEmployeeShiftsForDate = (employeeId: number, dateStr: string): Shift[] =>
  shifts.value.filter(s => s.employee_id === employeeId && s.date === dateStr)

const previousMonth = () => {
  currentDate.value.setMonth(currentDate.value.getMonth() - 1)
  currentDate.value = new Date(currentDate.value)
}

const nextMonth = () => {
  currentDate.value.setMonth(currentDate.value.getMonth() + 1)
  currentDate.value = new Date(currentDate.value)
}

const goToToday = () => {
  currentDate.value = new Date()
}

const showShiftDialog = (date: string) => {
  newShift.value.date = new Date(date)
  showAssignShiftDialog.value = true
}

const showAssignShiftForEmployee = (employeeId: number, date: string) => {
  newShift.value.employee_id = employeeId
  newShift.value.date = new Date(date)
  showAssignShiftDialog.value = true
}

const loadSchedules = async () => {
  const month = currentDate.value.getMonth() + 1
  const year = currentDate.value.getFullYear()

  try {
    const params = new URLSearchParams()
    params.set('year', String(year))
    params.set('month', String(month))
    if (selectedDepartment.value) {
      params.set('department_id', String(selectedDepartment.value))
    }

    const response = await fetch(`/api/v1/hr/shifts?${params}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    shifts.value = data.data || []
  } catch (error) {
    console.error('Failed to load shifts:', error)
  }
}

const saveShift = async () => {
  if (!newShift.value.employee_id || !newShift.value.shift_type) return

  savingShift.value = true
  try {
    const payload = {
      employee_id: newShift.value.employee_id,
      shift_type: newShift.value.shift_type,
      date: newShift.value.date instanceof Date
        ? newShift.value.date.toISOString().split('T')[0]
        : newShift.value.date,
      start_time: newShift.value.start_time,
      end_time: newShift.value.end_time,
      notes: newShift.value.notes,
    }

    const response = await fetch('/api/v1/hr/shifts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })

    if (response.ok) {
      showAssignShiftDialog.value = false
      newShift.value = {
        employee_id: null,
        shift_type: '',
        date: new Date(),
        start_time: '09:00',
        end_time: '17:00',
        notes: '',
      }
      await loadSchedules()
    } else {
      const error = await response.json()
      console.error('Failed to save shift:', error)
    }
  } catch (error) {
    console.error('Error saving shift:', error)
  } finally {
    savingShift.value = false
  }
}

onMounted(() => {
  Promise.all([
    fetch('/api/v1/hr/employees', {
      headers: { Accept: 'application/json' },
    }).then(r => r.json()).then(d => {
      employees.value = d.data || []
    }),
    fetch('/api/v1/hr/departments', {
      headers: { Accept: 'application/json' },
    }).then(r => r.json()).then(d => {
      departments.value = d.data || []
    }),
  ]).then(() => loadSchedules()).catch(error => {
    console.error('Failed to load initial data:', error)
  })
})
</script>
