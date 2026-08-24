<template>
  <form class="space-y-4" @submit.prevent="submit">
    <!-- Name -->
    <div class="flex flex-col gap-1">
      <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-200">
        Opportunity Name <span class="text-red-500">*</span>
      </label>
      <InputText
        v-model="form.name"
        :class="{ 'p-invalid': errors.name }"
        placeholder="e.g. Enterprise Deal Q3"
      />
      <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <!-- Pipeline -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-200">
          Pipeline <span class="text-red-500">*</span>
        </label>
        <Select
          v-model="form.pipeline_id"
          :options="pipelines"
          option-label="name"
          option-value="id"
          class="w-full"
          @change="onPipelineChange"
        />
        <small v-if="errors.pipeline_id" class="text-red-500">{{ errors.pipeline_id }}</small>
      </div>

      <!-- Stage -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-200">
          Stage <span class="text-red-500">*</span>
        </label>
        <Select
          v-model="form.stage"
          :options="stageOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
        <small v-if="errors.stage" class="text-red-500">{{ errors.stage }}</small>
      </div>

      <!-- Amount -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-200">Amount</label>
        <InputText
          v-model="form.amount"
          type="number"
          min="0"
          step="0.01"
          placeholder="0.00"
        />
      </div>

      <!-- Currency -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-200">Currency</label>
        <Select
          v-model="form.currency"
          :options="currencyOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <!-- Probability -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-200">Probability (%)</label>
        <InputText
          v-model="form.probability"
          type="number"
          min="0"
          max="100"
          placeholder="50"
        />
      </div>

      <!-- Expected Close -->
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-200">Expected Close Date</label>
        <InputText
          v-model="form.expected_close_date"
          type="date"
        />
      </div>
    </div>

    <div class="flex justify-end gap-2 pt-2 border-t border-surface-200 dark:border-surface-700">
      <Button type="button" label="Cancel" outlined @click="$emit('cancel')" />
      <Button type="submit" label="Create Opportunity" :loading="submitting" />
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

interface Pipeline {
  id: number
  name: string
  is_default: boolean
  stages?: Array<{ name: string; order: number; probability: number }>
}

const props = defineProps<{
  pipelines: Pipeline[]
  selectedPipelineId: number | null
}>()

const emit = defineEmits<{
  saved: []
  cancel: []
}>()

const submitting = ref(false)
const errors = reactive<Record<string, string>>({})

const form = reactive({
  name: '',
  pipeline_id: props.selectedPipelineId as number | null,
  stage: '',
  amount: '',
  currency: 'USD',
  probability: '',
  expected_close_date: '',
})

const currencyOptions = [
  { label: 'USD', value: 'USD' },
  { label: 'EUR', value: 'EUR' },
  { label: 'GBP', value: 'GBP' },
  { label: 'BRL', value: 'BRL' },
]

const selectedPipeline = computed(() =>
  props.pipelines.find(p => p.id === form.pipeline_id) ?? null,
)

const stageOptions = computed(() => {
  if (!selectedPipeline.value?.stages) return []
  return selectedPipeline.value.stages
    .sort((a, b) => a.order - b.order)
    .map(s => ({ label: s.name, value: s.name }))
})

const onPipelineChange = () => {
  form.stage = stageOptions.value[0]?.value ?? ''
}

watch(() => props.selectedPipelineId, (id) => {
  form.pipeline_id = id
  onPipelineChange()
}, { immediate: true })

// Chantier 32.15: missing-CSRF-token fetch() bug — see resources/js/Pages/CRM/Contacts/
// Form.vue's comment.
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

const submit = async () => {
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  const payload: Record<string, unknown> = {
    name: form.name,
    pipeline_id: form.pipeline_id,
    stage: form.stage,
    currency: form.currency,
  }
  if (form.amount) payload.amount = parseFloat(form.amount)
  if (form.probability) payload.probability = parseInt(form.probability)
  if (form.expected_close_date) payload.expected_close_date = form.expected_close_date

  try {
    const response = await fetch('/api/v1/crm/opportunities', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrf(),
      },
      body: JSON.stringify(payload),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) Object.assign(errors, data.errors)
      return
    }

    emit('saved')
  } finally {
    submitting.value = false
  }
}
</script>
