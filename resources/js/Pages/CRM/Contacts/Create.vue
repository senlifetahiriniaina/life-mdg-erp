<template>
  <form class="space-y-4" @submit.prevent="submit">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- First Name -->
      <div class="flex flex-col gap-1">
        <label id="first_name_label" class="text-sm font-medium text-surface-700 dark:text-surface-200">
          First Name <span class="text-red-500">*</span>
        </label>
        <InputText
          v-model="form.first_name"
          :class="{ 'p-invalid': errors.first_name }"
          placeholder="First name"
          aria-label="First Name"
          aria-labelledby="first_name_label"
          :aria-describedby="errors.first_name ? 'first_name_error' : undefined"
          :aria-invalid="!!errors.first_name"
        />
        <small v-if="errors.first_name" id="first_name_error" class="text-red-500" role="alert">{{ errors.first_name }}</small>
      </div>

      <!-- Last Name -->
      <div class="flex flex-col gap-1">
        <label id="last_name_label" class="text-sm font-medium text-surface-700 dark:text-surface-200">
          Last Name <span class="text-red-500">*</span>
        </label>
        <InputText
          v-model="form.last_name"
          :class="{ 'p-invalid': errors.last_name }"
          placeholder="Last name"
          aria-label="Last Name"
          aria-labelledby="last_name_label"
          :aria-describedby="errors.last_name ? 'last_name_error' : undefined"
          :aria-invalid="!!errors.last_name"
        />
        <small v-if="errors.last_name" id="last_name_error" class="text-red-500" role="alert">{{ errors.last_name }}</small>
      </div>

      <!-- Email -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Email</label>
        <InputText
          v-model="form.email"
          type="email"
          :class="{ 'p-invalid': errors.email }"
          placeholder="email@example.com"
        />
        <small v-if="errors.email" class="text-red-500">{{ errors.email }}</small>
      </div>

      <!-- Phone -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Phone</label>
        <InputText
          v-model="form.phone"
          placeholder="+1 555 000 0000"
        />
      </div>

      <!-- Mobile -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Mobile</label>
        <InputText
          v-model="form.mobile"
          placeholder="+1 555 000 0001"
        />
      </div>

      <!-- Job Title -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Job Title</label>
        <InputText
          v-model="form.job_title"
          placeholder="e.g. Sales Manager"
        />
      </div>

      <!-- Department -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Department</label>
        <InputText
          v-model="form.department"
          placeholder="e.g. Sales"
        />
      </div>

      <!-- Account -->
      <div class="flex flex-col gap-1">
        <label id="account_id_label" class="text-sm font-medium text-surface-700 dark:text-surface-200">Account</label>
        <Select
          v-model="form.account_id"
          :options="accounts"
          option-label="name"
          option-value="id"
          placeholder="Select account"
          show-clear
          filter
          class="w-full"
          aria-label="Select Account"
          aria-labelledby="account_id_label"
        />
      </div>

      <!-- Status -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Status</label>
        <Select
          v-model="form.status"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <!-- Source -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Source</label>
        <Select
          v-model="form.source"
          :options="sourceOptions"
          option-label="label"
          option-value="value"
          placeholder="Select source"
          show-clear
          class="w-full"
        />
      </div>
    </div>

    <div class="flex justify-end gap-2 pt-2 border-t border-surface-200 dark:border-surface-700">
      <Button
        type="button"
        label="Cancel"
        outlined
        @click="$emit('cancel')"
      />
      <Button
        type="submit"
        :label="contact ? 'Update Contact' : 'Create Contact'"
        :loading="submitting"
      />
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

interface Account {
  id: number
  name: string
}

interface Contact {
  id: number
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  mobile: string | null
  job_title: string | null
  department: string | null
  account: Account | null
  status: string
  source: string | null
}

const props = defineProps<{
  contact: Contact | null
  accounts: Account[]
}>()

const emit = defineEmits<{
  saved: []
  cancel: []
}>()

const submitting = ref(false)
const errors = reactive<Record<string, string>>({})

const statusOptions = [
  { label: t('common.active'), value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
  { label: 'Prospect', value: 'prospect' },
]

const sourceOptions = [
  { label: 'Website', value: 'website' },
  { label: 'Referral', value: 'referral' },
  { label: 'Cold Call', value: 'cold_call' },
  { label: 'Event', value: 'event' },
  { label: 'Social Media', value: 'social_media' },
  { label: 'Other', value: 'other' },
]

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  mobile: '',
  job_title: '',
  department: '',
  account_id: null as number | null,
  status: 'active',
  source: null as string | null,
})

const populateForm = () => {
  if (props.contact) {
    form.first_name = props.contact.first_name
    form.last_name = props.contact.last_name
    form.email = props.contact.email ?? ''
    form.phone = props.contact.phone ?? ''
    form.mobile = props.contact.mobile ?? ''
    form.job_title = props.contact.job_title ?? ''
    form.department = props.contact.department ?? ''
    form.account_id = props.contact.account?.id ?? null
    form.status = props.contact.status
    form.source = props.contact.source ?? null
  } else {
    form.first_name = ''
    form.last_name = ''
    form.email = ''
    form.phone = ''
    form.mobile = ''
    form.job_title = ''
    form.department = ''
    form.account_id = null
    form.status = 'active'
    form.source = null
  }
}

watch(() => props.contact, populateForm, { immediate: true })

const submit = async () => {
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  const url = props.contact
    ? `/api/v1/crm/contacts/${props.contact.id}`
    : '/api/v1/crm/contacts'

  const method = props.contact ? 'PUT' : 'POST'

  const payload: Record<string, unknown> = { ...form }
  if (!payload.email) delete payload.email

  try {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        Object.assign(errors, data.errors)
      }
      return
    }

    emit('saved')
  } finally {
    submitting.value = false
  }
}
</script>
