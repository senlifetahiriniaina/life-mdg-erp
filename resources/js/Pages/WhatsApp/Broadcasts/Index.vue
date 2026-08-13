<template>
  <AppLayout title="Diffusions WhatsApp">
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Diffusions avec segmentation</h1>
        <p class="wh-page-subtitle">Envoyez des messages ciblés à des segments de contacts</p>
      </div>
      <Button label="Nouvelle diffusion" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <DataTable :value="broadcasts" :loading="loading" class="wh-table" stripedRows>
      <Column field="name" header="Nom" />
      <Column header="Segment">
        <template #body="{ data }">
          <span class="text-sm text-gray-600 dark:text-surface-300 dark:text-surface-300">{{ segmentSummary(data.segment) }}</span>
        </template>
      </Column>
      <Column field="status" header="Statut">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="statusSeverity(data.status)" />
        </template>
      </Column>
      <Column field="recipient_count" header="Destinataires" />
      <Column header="Taux lecture">
        <template #body="{ data }">
          <span class="font-semibold">
            {{ data.recipient_count > 0 ? Math.round(data.read_count / data.recipient_count * 100) : 0 }}%
          </span>
        </template>
      </Column>
      <Column header="Actions">
        <template #body="{ data }">
          <Button
            v-if="data.status === 'draft'"
            icon="pi pi-calendar"
            size="small"
            text
            title="Planifier"
            @click="scheduleItem(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouvelle diffusion" :style="{ width: '560px' }" modal>
      <div class="space-y-4">
        <div class="field">
          <label class="field-label">Nom <span class="required">*</span></label>
          <InputText v-model="form.name" class="w-full" placeholder="ex. Promo été 2026" />
        </div>

        <!-- Segment conditions -->
        <div class="field">
          <label class="field-label">Conditions de segment</label>
          <div class="space-y-2">
            <div class="flex gap-2">
              <span class="text-sm w-28 text-gray-500 dark:text-surface-400 mt-2">Tag</span>
              <InputText v-model="form.segment.tag" class="flex-1" placeholder="ex. vip" />
            </div>
            <div class="flex gap-2">
              <span class="text-sm w-28 text-gray-500 dark:text-surface-400 mt-2">Pays</span>
              <InputText v-model="form.segment.country" class="flex-1" placeholder="ex. FR" />
            </div>
            <div class="flex gap-2">
              <span class="text-sm w-28 text-gray-500 dark:text-surface-400 mt-2">Inactif (jours)</span>
              <InputText v-model.number="form.segment.last_purchase_days" type="number" class="flex-1" placeholder="ex. 30" />
            </div>
          </div>
          <button
            class="btn btn-secondary mt-2"
            style="font-size:12px"
            :disabled="estimating"
            @click="estimateAudience"
          >
            <i class="pi pi-users" style="font-size:11px" />
            {{ estimating ? 'Calcul...' : 'Estimer l\'audience' }}
          </button>
          <p v-if="estimatedCount !== null" class="text-sm text-blue-600 mt-1">
            Audience estimée : <strong>{{ estimatedCount }}</strong> contacts
          </p>
        </div>

        <div class="field">
          <label class="field-label">Planifier l'envoi</label>
          <input v-model="form.scheduled_at" type="datetime-local" class="wh-input w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Créer" :loading="saving" @click="createBroadcast" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText } from 'primevue'
import axios from 'axios'

const broadcasts = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const estimating = ref(false)
const estimatedCount = ref<number | null>(null)
const showCreateDialog = ref(false)

const form = ref({
  name: '',
  segment: { tag: '', country: '', last_purchase_days: null as number | null },
  scheduled_at: '',
})

function statusSeverity(status: string): string {
  const map: Record<string, string> = { draft: 'secondary', scheduled: 'info', sending: 'warn', sent: 'success', failed: 'danger' }
  return map[status] ?? 'secondary'
}

function segmentSummary(segment: any): string {
  if (!segment) return 'Tous les contacts'
  const parts: string[] = []
  if (segment.tag) parts.push(`Tag: ${segment.tag}`)
  if (segment.country) parts.push(`Pays: ${segment.country}`)
  if (segment.last_purchase_days) parts.push(`Inactif ${segment.last_purchase_days}j`)
  return parts.length > 0 ? parts.join(', ') : 'Tous les contacts'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/whatsapp/advanced-broadcasts')
    broadcasts.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function createBroadcast() {
  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      segment: form.value.segment,
      scheduled_at: form.value.scheduled_at || null,
    }
    const { data } = await axios.post('/api/v1/whatsapp/advanced-broadcasts', payload)
    broadcasts.value.unshift(data)
    showCreateDialog.value = false
    form.value = { name: '', segment: { tag: '', country: '', last_purchase_days: null }, scheduled_at: '' }
    estimatedCount.value = null
  } finally {
    saving.value = false
  }
}

async function estimateAudience() {
  estimating.value = true
  try {
    const { data } = await axios.post('/api/v1/whatsapp/advanced-broadcasts/estimate', {
      segment: form.value.segment,
    })
    estimatedCount.value = data.estimated_count
  } finally {
    estimating.value = false
  }
}

async function scheduleItem(broadcast: any) {
  await axios.post(`/api/v1/whatsapp/advanced-broadcasts/${broadcast.id}/schedule`)
  broadcast.status = 'scheduled'
}

onMounted(load)
</script>
