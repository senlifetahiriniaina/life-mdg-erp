<template>
  <AppLayout :title="`Journey — ${journey?.name ?? ''}`">
    <div class="page-head">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text @click="$inertia.visit('/email/journeys')" />
        <div>
          <h1 class="wh-page-title">{{ journey?.name }}</h1>
          <p class="wh-page-subtitle">{{ journey?.description }}</p>
        </div>
        <Tag :value="journey?.status" :severity="statusSeverity(journey?.status)" />
      </div>
      <div class="flex gap-2">
        <Button
          v-if="journey?.status !== 'active'"
          label="Activer"
          icon="pi pi-play"
          severity="success"
          :loading="toggling"
          @click="activate"
        />
        <Button
          v-else
          label="Mettre en pause"
          icon="pi pi-pause"
          severity="warning"
          :loading="toggling"
          @click="pause"
        />
      </div>
    </div>

    <!-- Flow builder -->
    <div class="flex flex-col items-center gap-0 py-6">
      <!-- Start node -->
      <div class="journey-node journey-node--start">
        <i class="pi pi-flag text-green-600" />
        <span class="font-semibold text-sm">Déclencheur: {{ journey?.trigger }}</span>
      </div>

      <template v-for="(step, idx) in steps" :key="step.id">
        <div class="journey-connector" />
        <div class="journey-node" :class="`journey-node--${step.type}`" @click="editStep(step)">
          <i :class="stepIcon(step.type)" />
          <div class="flex-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-surface-400">{{ step.type }}</p>
            <p class="text-sm text-gray-700 dark:text-surface-100 dark:text-surface-100">{{ stepSummary(step) }}</p>
          </div>
          <Button icon="pi pi-trash" size="small" text severity="danger" @click.stop="deleteStep(step)" />
        </div>

        <div class="journey-connector relative group">
          <button
            class="absolute left-1/2 -translate-x-1/2 -translate-y-1/2 top-1/2 bg-white dark:bg-surface-800 dark:bg-surface-800 border border-blue-300 text-blue-600 rounded-full w-6 h-6 text-xs opacity-0 group-hover:opacity-100 transition-opacity"
            @click="insertStep(idx + 1)"
          >+</button>
        </div>
      </template>

      <!-- Add step button -->
      <div class="journey-connector" />
      <button
        class="journey-node--add flex items-center gap-2 bg-blue-50 dark:bg-surface-800 border-2 border-dashed border-blue-300 text-blue-600 rounded-xl px-6 py-3 text-sm font-medium hover:bg-blue-100 transition-colors"
        @click="insertStep(steps.length)"
      >
        <i class="pi pi-plus" />
        Ajouter une étape
      </button>

      <div class="journey-connector" />
      <div class="journey-node journey-node--end">
        <i class="pi pi-check-circle text-gray-500 dark:text-surface-400" />
        <span class="text-sm text-gray-500 dark:text-surface-400">Fin du journey</span>
      </div>
    </div>

    <!-- Step config dialog -->
    <Dialog v-model:visible="showStepDialog" header="Configuration de l'étape" :style="{ width: '480px' }" modal>
      <div class="space-y-4">
        <div class="field">
          <label class="field-label">Type d'étape</label>
          <Dropdown v-model="stepForm.type" :options="stepTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div class="field">
          <label class="field-label">Ordre</label>
          <InputText v-model.number="stepForm.order" type="number" class="w-full" />
        </div>

        <!-- Email config -->
        <template v-if="stepForm.type === 'email'">
          <div class="field">
            <label class="field-label">Sujet</label>
            <InputText v-model="stepForm.config.subject" class="w-full" placeholder="Objet de l'email" />
          </div>
          <div class="field">
            <label class="field-label">Délai (jours)</label>
            <InputText v-model.number="stepForm.config.delay_days" type="number" class="w-full" />
          </div>
        </template>

        <!-- Wait config -->
        <template v-if="stepForm.type === 'wait'">
          <div class="field">
            <label class="field-label">Attendre (jours)</label>
            <InputText v-model.number="stepForm.config.delay_days" type="number" class="w-full" />
          </div>
        </template>

        <!-- Condition config -->
        <template v-if="stepForm.type === 'condition'">
          <div class="field">
            <label class="field-label">Champ à vérifier</label>
            <InputText v-model="stepForm.config.condition_field" class="w-full" placeholder="ex. opened_email" />
          </div>
          <div class="field">
            <label class="field-label">Valeur attendue</label>
            <InputText v-model="stepForm.config.condition_value" class="w-full" placeholder="ex. true" />
          </div>
        </template>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showStepDialog = false" />
        <Button label="Enregistrer" :loading="savingStep" @click="saveStep" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dialog, Tag, InputText, Dropdown } from 'primevue'
import axios from 'axios'

const props = defineProps<{ id: number }>()

const journey = ref<any>(null)
const steps = ref<any[]>([])
const toggling = ref(false)
const showStepDialog = ref(false)
const savingStep = ref(false)
const editingStep = ref<any>(null)
const insertAtOrder = ref(0)

const stepForm = ref<any>({ type: 'email', order: 0, config: {} })

const stepTypeOptions = [
  { label: 'Email', value: 'email' },
  { label: 'Attente', value: 'wait' },
  { label: 'Condition', value: 'condition' },
  { label: 'Action', value: 'action' },
]

function statusSeverity(status: string | undefined): string {
  const map: Record<string, string> = { active: 'success', paused: 'warn', draft: 'secondary' }
  return map[status ?? ''] ?? 'secondary'
}

function stepIcon(type: string): string {
  const map: Record<string, string> = {
    email: 'pi pi-envelope',
    wait: 'pi pi-clock',
    condition: 'pi pi-code-branch',
    action: 'pi pi-bolt',
  }
  return map[type] ?? 'pi pi-circle'
}

function stepSummary(step: any): string {
  if (step.type === 'email') return step.config?.subject ?? 'Email sans titre'
  if (step.type === 'wait') return `Attendre ${step.config?.delay_days ?? 1} jour(s)`
  if (step.type === 'condition') return `Si ${step.config?.condition_field ?? '...'}`
  return 'Action personnalisée'
}

async function load() {
  const { data } = await axios.get(`/api/v1/email/journeys/${props.id}`)
  journey.value = data
  steps.value = data.steps ?? []
}

async function activate() {
  toggling.value = true
  try {
    await axios.post(`/api/v1/email/journeys/${props.id}/activate`)
    if (journey.value) journey.value.status = 'active'
  } finally {
    toggling.value = false
  }
}

async function pause() {
  toggling.value = true
  try {
    await axios.post(`/api/v1/email/journeys/${props.id}/pause`)
    if (journey.value) journey.value.status = 'paused'
  } finally {
    toggling.value = false
  }
}

function insertStep(order: number) {
  insertAtOrder.value = order
  editingStep.value = null
  stepForm.value = { type: 'email', order, config: {} }
  showStepDialog.value = true
}

function editStep(step: any) {
  editingStep.value = step
  stepForm.value = { type: step.type, order: step.order, config: { ...(step.config ?? {}) } }
  showStepDialog.value = true
}

async function saveStep() {
  savingStep.value = true
  try {
    if (editingStep.value) {
      const { data } = await axios.put(`/api/v1/email/journeys/${props.id}/steps/${editingStep.value.id}`, stepForm.value)
      const idx = steps.value.findIndex((s: any) => s.id === editingStep.value.id)
      if (idx !== -1) steps.value[idx] = data
    } else {
      const { data } = await axios.post(`/api/v1/email/journeys/${props.id}/steps`, stepForm.value)
      steps.value.push(data)
      steps.value.sort((a: any, b: any) => a.order - b.order)
    }
    showStepDialog.value = false
  } finally {
    savingStep.value = false
  }
}

async function deleteStep(step: any) {
  await axios.delete(`/api/v1/email/journeys/${props.id}/steps/${step.id}`)
  steps.value = steps.value.filter((s: any) => s.id !== step.id)
}

onMounted(load)
</script>

<style scoped>
.journey-node {
  display: flex;
  align-items: center;
  gap: 12px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px 20px;
  width: 480px;
  cursor: pointer;
  transition: box-shadow 0.15s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.journey-node:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.journey-node--start { border-color: #86efac; background: #f0fdf4; cursor: default; }
.journey-node--end { background: #f9fafb; cursor: default; }
.journey-node--email { border-left: 4px solid #6366f1; }
.journey-node--wait { border-left: 4px solid #f59e0b; }
.journey-node--condition { border-left: 4px solid #06b6d4; }
.journey-node--action { border-left: 4px solid #10b981; }
.journey-connector { width: 2px; height: 32px; background: #e5e7eb; }
</style>
