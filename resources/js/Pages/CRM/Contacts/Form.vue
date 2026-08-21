<template>
  <form @submit.prevent="onSubmit" style="display:flex;flex-direction:column;gap:16px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-field">
        <label class="form-label">{{ $t('common.first_name') }} <span style="color:var(--danger-fg)">*</span></label>
        <InputText v-model="first_name" v-bind="first_nameAttrs" :class="{ 'p-invalid': errors.first_name }" :placeholder="t('common.first_name')" :aria-required="true" :aria-invalid="!!errors.first_name" aria-describedby="error-first_name" />
        <small v-if="errors.first_name" class="form-error" role="alert" id="error-first_name">{{ errors.first_name }}</small>
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('common.last_name') }} <span style="color:var(--danger-fg)">*</span></label>
        <InputText v-model="last_name" v-bind="last_nameAttrs" :class="{ 'p-invalid': errors.last_name }" :placeholder="t('common.last_name')" :aria-required="true" :aria-invalid="!!errors.last_name" aria-describedby="error-last_name" />
        <small v-if="errors.last_name" class="form-error" role="alert" id="error-last_name">{{ errors.last_name }}</small>
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('common.email') }}</label>
        <InputText v-model="email" v-bind="emailAttrs" type="email" :class="{ 'p-invalid': errors.email }" placeholder="email@example.com" :aria-invalid="!!errors.email" aria-describedby="error-email" />
        <small v-if="errors.email" class="form-error" role="alert" id="error-email">{{ errors.email }}</small>
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('common.phone') }}</label>
        <InputText v-model="phone" v-bind="phoneAttrs" placeholder="+1 555 000 0000" />
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('common.mobile') }}</label>
        <InputText v-model="mobile" v-bind="mobileAttrs" placeholder="+1 555 000 0001" />
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('crm.job_title') }}</label>
        <InputText v-model="job_title" v-bind="job_titleAttrs" :placeholder="t('crm.job_title')" />
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('common.department') }}</label>
        <InputText v-model="department" v-bind="departmentAttrs" :placeholder="t('common.department')" />
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('crm.account') }}</label>
        <Select v-model="account_id" v-bind="account_idAttrs" :options="accounts" option-label="name" option-value="id" :placeholder="t('crm.account')" show-clear filter class="w-full" />
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('common.status') }}</label>
        <Select v-model="status" v-bind="statusAttrs" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
      </div>
      <div class="form-field">
        <label class="form-label">{{ $t('crm.source') }}</label>
        <Select v-model="source" v-bind="sourceAttrs" :options="sourceOptions" option-label="label" option-value="value" :placeholder="t('crm.source')" show-clear class="w-full" />
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:8px;border-top:1px solid var(--border-subtle)">
      <button type="button" class="btn btn-secondary" @click="$emit('cancel')">{{ $t('common.cancel') }}</button>
      <button type="submit" class="btn btn-primary" :disabled="submitting">
        <i v-if="submitting" class="pi pi-spin pi-spinner" style="font-size:13px" />
        {{ contact ? $t('common.update') : $t('crm.contacts.create') }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useI18n } from 'vue-i18n'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

const { t } = useI18n()

interface Account { id: number; name: string }
interface Contact {
  id: number; first_name: string; last_name: string; email: string | null;
  phone: string | null; mobile: string | null; job_title: string | null;
  department: string | null; account: Account | null; status: string; source: string | null;
}

const props = defineProps<{ contact: Contact | null; accounts: Account[] }>()
const emit = defineEmits<{ saved: []; cancel: [] }>()

const submitting = ref(false)

const statusOptions = computed(() => [
  { label: t('common.active'),   value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
  { label: t('crm.prospect'),    value: 'prospect' },
])

const sourceOptions = [
  { label: 'Site web',        value: 'website' },
  { label: 'Recommandation',  value: 'referral' },
  { label: 'Appel sortant',   value: 'cold_call' },
  { label: 'Événement',       value: 'event' },
  { label: 'Réseaux sociaux', value: 'social_media' },
  { label: 'Autre',           value: 'other' },
]

const schema = toTypedSchema(z.object({
  first_name: z.string().min(1, t('common.required')),
  last_name:  z.string().min(1, t('common.required')),
  email:      z.string().email(t('validation.email')).optional().or(z.literal('')),
  phone:      z.string().optional(),
  mobile:     z.string().optional(),
  job_title:  z.string().optional(),
  department: z.string().optional(),
  account_id: z.number().optional().nullable(),
  status:     z.string().default('active'),
  source:     z.string().optional().nullable(),
}))

const { handleSubmit, defineField, errors, setValues } = useForm({
  validationSchema: schema,
  initialValues: {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    mobile: '',
    job_title: '',
    department: '',
    account_id: null,
    status: 'active',
    source: null,
  },
})

const [first_name, first_nameAttrs]   = defineField('first_name')
const [last_name, last_nameAttrs]     = defineField('last_name')
const [email, emailAttrs]             = defineField('email')
const [phone, phoneAttrs]             = defineField('phone')
const [mobile, mobileAttrs]           = defineField('mobile')
const [job_title, job_titleAttrs]     = defineField('job_title')
const [department, departmentAttrs]   = defineField('department')
const [account_id, account_idAttrs]   = defineField('account_id')
const [status, statusAttrs]           = defineField('status')
const [source, sourceAttrs]           = defineField('source')

const populateForm = () => {
  if (props.contact) {
    setValues({
      first_name:  props.contact.first_name,
      last_name:   props.contact.last_name,
      email:       props.contact.email ?? '',
      phone:       props.contact.phone ?? '',
      mobile:      props.contact.mobile ?? '',
      job_title:   props.contact.job_title ?? '',
      department:  props.contact.department ?? '',
      account_id:  props.contact.account?.id ?? null,
      status:      props.contact.status,
      source:      props.contact.source ?? null,
    })
  } else {
    setValues({
      first_name: '', last_name: '', email: '', phone: '', mobile: '',
      job_title: '', department: '', account_id: null, status: 'active', source: null,
    })
  }
}
watch(() => props.contact, populateForm, { immediate: true })

// Chantier 32.15: this fetch() POST/PUT sent no CSRF token at all — unlike axios (used
// elsewhere in this app), which reads the XSRF-TOKEN cookie and attaches X-XSRF-TOKEN
// automatically, a raw fetch() does nothing on its own. This app runs Sanctum's
// statefulApi(), which activates real CSRF verification on every same-origin browser
// request — every real contact create/edit through this form/modal would 419. Pest can never
// catch this (VerifyCsrfToken::runningUnitTests() bypasses in APP_ENV=testing regardless of
// headers sent), same fix pattern already established elsewhere in this app (e.g.
// Modules/Inventory's Categories/Index.vue getCsrf() helper).
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true
  const url    = props.contact ? `/api/v1/crm/contacts/${props.contact.id}` : '/api/v1/crm/contacts'
  const method = props.contact ? 'PUT' : 'POST'
  const payload: Record<string, unknown> = { ...values }
  if (!payload.email) delete payload.email
  try {
    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(payload),
    })
    if (!response.ok) {
      // Server-side errors are surfaced via the existing p-invalid / form-error pattern
      return
    }
    emit('saved')
  } finally {
    submitting.value = false
  }
})
</script>

<style scoped>
.form-field { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:12px; font-weight:500; color:var(--fg-2); }
.form-error { font-size:11px; color:var(--danger-fg,#991B1B); }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
</style>
