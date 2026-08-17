<template>
  <AppLayout>
    <Head title="Détection d'Anomalies IA" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Détection d'Anomalies Comptables (IA)
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Analyse automatisée — anomalies détectées sur les modules Comptabilité, Stock et RH, filtrées ici sur Comptabilité
          </p>
        </div>
        <Button
          icon="pi pi-search"
          label="Lancer une analyse"
          severity="warning"
          :loading="scanning"
          @click="triggerScan"
        />
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Anomalies actives</div>
          <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ accountingAnomalies.length }}</div>
          <div class="text-surface-400 text-xs mt-1">dont {{ criticalCount }} critiques</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Alertes</div>
          <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ warningCount }}</div>
          <div class="text-surface-400 text-xs mt-1">niveau avertissement</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Détectées par IA</div>
          <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ aiPoweredCount }}</div>
          <div class="text-surface-400 text-xs mt-1">via Claude, le reste par règles</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <Select
            v-model="filterSeverity"
            :options="severityOptions"
            option-label="label"
            option-value="value"
            placeholder="Gravité"
            show-clear
            class="w-40"
          />
          <Button
            icon="pi pi-filter-slash"
            outlined
            v-tooltip.top="'Réinitialiser les filtres'"
            @click="filterSeverity = null"
          />
        </div>
      </div>

      <!-- Anomalies table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="filteredAnomalies"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
        >
          <Column field="detected_at" header="Détectée le" sortable style="width: 150px">
            <template #body="{ data }">
              <span class="text-sm font-mono">{{ formatDate(data.detected_at) }}</span>
            </template>
          </Column>
          <Column field="type" header="Type">
            <template #body="{ data }">
              <span class="text-sm">{{ data.type }}</span>
            </template>
          </Column>
          <Column field="title" header="Titre" style="min-width: 220px">
            <template #body="{ data }">
              <div class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ data.title }}</div>
              <div class="text-xs text-surface-400">{{ data.description }}</div>
            </template>
          </Column>
          <Column field="severity" header="Gravité" style="width: 110px">
            <template #body="{ data }">
              <Tag :value="severityLabel(data.severity)" :severity="severityTag(data.severity)" />
            </template>
          </Column>
          <Column field="ai_powered" header="Origine" style="width: 100px">
            <template #body="{ data }">
              <span class="text-xs" :class="data.ai_powered ? 'text-primary-600' : 'text-surface-400'">
                {{ data.ai_powered ? 'IA (Claude)' : 'Règle' }}
              </span>
            </template>
          </Column>
          <Column header="Actions" style="width: 90px">
            <template #body="{ data }">
              <Button
                icon="pi pi-times"
                outlined
                severity="secondary"
                size="small"
                v-tooltip.top="'Ignorer cette anomalie'"
                @click="dismissAnomaly(data)"
              />
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              <i class="pi pi-check-circle text-3xl text-green-400 mb-3 block" />
              Aucune anomalie détectée — comptabilité saine.
            </div>
          </template>
        </DataTable>
      </div>

      <!-- AI Assistant -->
      <AiAssistantPanel v-if="guidance" :guidance="guidance" />
    </div>

    <Toast />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import AppLayout from '@/Layouts/AppLayout.vue'
import AiAssistantPanel from '@/Components/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import axios from 'axios'

interface Anomaly {
  id: string
  module: string
  type: string
  severity: 'critical' | 'warning' | 'info'
  title: string
  description: string
  entity_id: number | null
  entity_type: string | null
  detected_at: string
  ai_powered: boolean
}

const toast = useToast()
const { guidance } = useAiAssistant('Accounting', 'reconcile')

const anomalies = ref<Anomaly[]>([])
const loading = ref(false)
const scanning = ref(false)
const filterSeverity = ref<string | null>(null)

const accountingAnomalies = computed(() => anomalies.value.filter(a => a.module === 'Accounting'))
const filteredAnomalies = computed(() =>
  filterSeverity.value ? accountingAnomalies.value.filter(a => a.severity === filterSeverity.value) : accountingAnomalies.value
)
const criticalCount = computed(() => accountingAnomalies.value.filter(a => a.severity === 'critical').length)
const warningCount = computed(() => accountingAnomalies.value.filter(a => a.severity === 'warning').length)
const aiPoweredCount = computed(() => accountingAnomalies.value.filter(a => a.ai_powered).length)

const severityOptions = [
  { label: 'Critique', value: 'critical' },
  { label: 'Avertissement', value: 'warning' },
  { label: 'Information', value: 'info' },
]

const severityLabel = (s: string) => ({ critical: 'Critique', warning: 'Avertissement', info: 'Information' }[s] ?? s)
const severityTag = (s: string) => ({ critical: 'danger', warning: 'warn', info: 'info' }[s] ?? 'secondary')
const formatDate = (d: string) => new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

const loadAnomalies = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/ai/anomalies')
    anomalies.value = data.data ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les anomalies.', life: 4000 })
  } finally {
    loading.value = false
  }
}

onMounted(loadAnomalies)

const triggerScan = async () => {
  scanning.value = true
  try {
    const { data } = await axios.post('/api/v1/ai/anomalies/detect')
    anomalies.value = data.data ?? []
    toast.add({ severity: 'success', summary: 'Analyse terminée', detail: `${data.count ?? 0} anomalie(s) active(s) au total.`, life: 4000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de lancer l\'analyse.', life: 4000 })
  } finally {
    scanning.value = false
  }
}

const dismissAnomaly = async (anomaly: Anomaly) => {
  try {
    await axios.delete(`/api/v1/ai/anomalies/${anomaly.id}`)
    anomalies.value = anomalies.value.filter(a => a.id !== anomaly.id)
    toast.add({ severity: 'success', summary: 'Ignorée', detail: 'L\'anomalie a été écartée.', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'ignorer l\'anomalie.', life: 4000 })
  }
}
</script>
