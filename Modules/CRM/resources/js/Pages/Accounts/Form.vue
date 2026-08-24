<template>
  <AppLayout>
    <main class="p-6">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ account ? 'Edit Account' : 'New Account' }}</h1>
      </header>

      <form @submit.prevent="submit" class="bg-surface-0 dark:bg-surface-800 rounded-lg shadow p-6 max-w-2xl">
        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Account Name *</label>
          <InputText v-model="form.name" type="text" class="w-full" required />
          <small class="text-red-700 dark:text-red-300" v-if="errors.name">{{ errors.name }}</small>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Account Type *</label>
            <Dropdown v-model="form.type" :options="accountTypes" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Industry</label>
            <InputText v-model="form.industry" type="text" class="w-full" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Annual Revenue</label>
            <InputNumber v-model="form.annual_revenue" mode="currency" currency="USD" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Number of Employees</label>
            <InputNumber v-model="form.employees" class="w-full" />
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Website</label>
          <InputText v-model="form.website" type="url" class="w-full" />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Email</label>
          <InputText v-model="form.email" type="email" class="w-full" />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Phone</label>
          <InputText v-model="form.phone" type="tel" class="w-full" />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-surface-900 dark:text-surface-50 mb-2">Description</label>
          <Textarea v-model="form.description" rows="4" class="w-full" />
        </div>

        <div class="flex gap-4">
          <Button type="submit" label="Save Account" icon="pi pi-check" loading={processing} />
          <Link href="/crm/accounts" class="px-4 py-2 border rounded-lg">Cancel</Link>
        </div>
      </form>
    </main>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Dropdown from 'primevue/select'

const props = defineProps({
  account: Object,
  errors: Object
})

const accountTypes = [
  { label: 'Prospect', value: 'prospect' },
  { label: 'Customer', value: 'customer' },
  { label: 'Partner', value: 'partner' }
]

const form = useForm({
  name: props.account?.name || '',
  type: props.account?.type || 'prospect',
  industry: props.account?.industry || '',
  annual_revenue: props.account?.annual_revenue || null,
  employees: props.account?.employees || null,
  website: props.account?.website || '',
  email: props.account?.email || '',
  phone: props.account?.phone || '',
  description: props.account?.description || ''
})

const submit = () => {
  if (props.account) {
    form.put(`/crm/accounts/${props.account.id}`)
  } else {
    form.post('/crm/accounts')
  }
}
</script>
