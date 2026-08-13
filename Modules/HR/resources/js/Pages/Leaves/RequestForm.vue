<template>
  <AppLayout>
    <div class="p-6">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50 mb-6">{{ leaveRequest ? 'Edit Leave Request' : 'Submit Leave Request' }}</h1>

      <form @submit.prevent="submit" class="bg-surface-0 dark:bg-surface-800 rounded-lg shadow p-6 max-w-2xl">
        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Leave Type *</label>
          <Dropdown v-model="form.leave_type_id" :options="leaveTypes" optionLabel="name" optionValue="id" class="w-full" />
          <small class="text-red-700 dark:text-red-300" v-if="errors.leave_type_id">{{ errors.leave_type_id }}</small>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Available Balance</label>
          <p class="text-lg font-semibold text-primary-700 dark:text-primary-300">{{ availableBalance }} days</p>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">From Date *</label>
            <Calendar v-model="form.start_date" dateFormat="yy-mm-dd" class="w-full" />
            <small class="text-red-700 dark:text-red-300" v-if="errors.start_date">{{ errors.start_date }}</small>
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">To Date *</label>
            <Calendar v-model="form.end_date" dateFormat="yy-mm-dd" class="w-full" />
            <small class="text-red-700 dark:text-red-300" v-if="errors.end_date">{{ errors.end_date }}</small>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Number of Days</label>
          <InputNumber v-model="calculateDays" readonly class="w-full" />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Reason *</label>
          <Textarea v-model="form.reason" rows="4" class="w-full" required />
          <small class="text-red-700 dark:text-red-300" v-if="errors.reason">{{ errors.reason }}</small>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Cover Employee</label>
          <Dropdown v-model="form.cover_employee_id" :options="employees" optionLabel="name" optionValue="id" class="w-full" placeholder="Select employee to cover duties" />
        </div>

        <div class="mb-4">
          <label class="flex items-center gap-2">
            <Checkbox v-model="form.half_day" binary />
            <span class="text-sm font-medium text-surface-900 dark:text-surface-50">Half Day Leave</span>
          </label>
        </div>

        <div class="flex gap-4">
          <Button type="submit" label="Submit Request" icon="pi pi-check" :loading="processing" />
          <Link href="/hr/leaves" class="px-4 py-2 border rounded-lg">Cancel</Link>
        </div>
      </form>

      <Card class="mt-6 max-w-2xl">
        <template #title>Leave Balance</template>
        <template #content>
          <DataTable :value="leaveBalances" class="p-datatable-sm">
            <Column field="leave_type.name" header="Leave Type" />
            <Column field="total_days" header="Total Days" />
            <Column field="used_days" header="Used Days" />
            <Column field="remaining_days" header="Remaining">
              <template #body="{ data }">
                <Badge :value="data.remaining_days" :severity="data.remaining_days < 2 ? 'danger' : 'success'" />
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Checkbox from 'primevue/checkbox'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Badge from 'primevue/badge'
import Card from 'primevue/card'

const props = defineProps({
  leaveRequest: Object,
  leaveTypes: Array,
  employees: Array,
  leaveBalances: Array,
  errors: Object
})

const form = useForm({
  leave_type_id: props.leaveRequest?.leave_type_id || null,
  start_date: props.leaveRequest?.start_date ? new Date(props.leaveRequest.start_date) : null,
  end_date: props.leaveRequest?.end_date ? new Date(props.leaveRequest.end_date) : null,
  reason: props.leaveRequest?.reason || '',
  cover_employee_id: props.leaveRequest?.cover_employee_id || null,
  half_day: props.leaveRequest?.half_day || false
})

const calculateDays = computed(() => {
  if (!form.start_date || !form.end_date) return 0
  const start = new Date(form.start_date)
  const end = new Date(form.end_date)
  const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1
  return form.half_day ? days * 0.5 : days
})

const availableBalance = computed(() => {
  const selected = props.leaveBalances?.find(b => b.leave_type_id === form.leave_type_id)
  return selected?.remaining_days || 0
})

const submit = () => {
  if (props.leaveRequest) {
    form.put(`/hr/leaves/${props.leaveRequest.id}`)
  } else {
    form.post('/hr/leaves')
  }
}
</script>
