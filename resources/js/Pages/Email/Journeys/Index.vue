<template>
  <AppLayout title="Customer Journeys">
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Customer Journeys</h1>
        <p class="wh-page-subtitle">Automatisez l'expérience client avec des parcours intelligents</p>
      </div>
      <Button label="Nouveau Journey" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <DataTable :value="journeys" :loading="loading" class="wh-table" stripedRows>
      <Column field="name" header="Nom" />
      <Column field="trigger" header="Déclencheur">
        <template #body="{ data }">
          <Tag :value="data.trigger" :severity="triggerSeverity(data.trigger)" />
        </template>
      </Column>
      <Column field="status" header="Statut">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>
      <Column field="steps_count" header="Étapes">
        <template #body="{ data }">
          <Badge :value="data.steps_count ?? 0" severity="secondary" />
        </template>
      </Column>
      <Column header="Actions">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button icon="pi pi-eye" size="small" text @click="openJourney(data)" title="Voir le flow" />
            <Button
              v-if="data.status !== 'active'"
              icon="pi pi-play"
              size="small"
              text
              severity="success"
              @click="activateJourney(data)"
              title="Activer"
            />
            <Button
              v-if="data.status === 'active'"
              icon="pi pi-pause"
              size="small"
              text
              severity="warning"
              @click="pauseJourney(data)"
              title="Mettre en pause"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau Journey" :style="{ width: '480px' }" modal>
      <div class="space-y-4">
        <div class="field">
          <label class="field-label">Nom <span class="required">*</span></label>
          <InputText v-model="form.name" class="w-full" placeholder="ex. Onboarding nouveaux inscrits" />
        </div>
        <div class="field">
          <label class="field-label">Déclencheur</label>
          <Dropdown
            v-model="form.trigger"
            :options="triggerOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>
        <div class="field">
          <label class="field-label">Description</label>
          <Textarea v-model="form.description" class="w-full" rows="3" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Créer" :loading="saving" @click="createJourney" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown, Badge } from 'primevue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const journeys = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const form = ref({ name: '', trigger: 'signup', description: '' })

const triggerOptions = [
  { label: 'Inscription', value: 'signup' },
  { label: 'Achat', value: 'purchase' },
  { label: 'Anniversaire', value: 'birthday' },
  { label: 'Inactivité', value: 'inactivity' },
  { label: 'Personnalisé', value: 'custom' },
]

function triggerSeverity(trigger: string): string {
  const map: Record<string, string> = { signup: 'success', purchase: 'info', birthday: 'warn', inactivity: 'danger', custom: 'secondary' }
  return map[trigger] ?? 'secondary'
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = { active: 'success', paused: 'warn', draft: 'secondary' }
  return map[status] ?? 'secondary'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/email/journeys')
    journeys.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function createJourney() {
  saving.value = true
  try {
    const { data } = await axios.post('/api/v1/email/journeys', form.value)
    journeys.value.unshift(data)
    showCreateDialog.value = false
    form.value = { name: '', trigger: 'signup', description: '' }
  } finally {
    saving.value = false
  }
}

function openJourney(journey: any) {
  router.visit(`/email/journeys/${journey.id}`)
}

async function activateJourney(journey: any) {
  await axios.post(`/api/v1/email/journeys/${journey.id}/activate`)
  journey.status = 'active'
}

async function pauseJourney(journey: any) {
  await axios.post(`/api/v1/email/journeys/${journey.id}/pause`)
  journey.status = 'paused'
}

onMounted(load)
</script>
