<template>
  <AppLayout>
    <Head :title="`${report?.name ?? 'Rapport'} — WideHalo ERP`" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ report?.name ?? 'Rapport' }}</h1>
          <p class="text-surface-500 text-sm mt-1">{{ report?.description }}</p>
        </div>
        <div class="flex gap-2">
          <Button label="Retour" outlined icon="pi pi-arrow-left" @click="router.visit('/reporting')" />
          <Button label="Exécuter" icon="pi pi-play" :loading="running" :disabled="!report" @click="runNow" />
        </div>
      </div>

      <div v-if="loading" class="text-center py-16 text-surface-400">Chargement…</div>

      <template v-else-if="report">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
          <div>
            <span class="text-surface-400 block mb-1">Module</span>
            <Tag :value="report.module" severity="secondary" />
          </div>
          <div>
            <span class="text-surface-400 block mb-1">Type</span>
            {{ report.report_type }}
          </div>
          <div>
            <span class="text-surface-400 block mb-1">Format</span>
            {{ report.output_format }}
          </div>
          <div>
            <span class="text-surface-400 block mb-1">Système</span>
            {{ report.is_system ? 'Oui' : 'Non' }}
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
          <div class="flex items-center justify-between px-5 py-4 border-b border-surface-200 dark:border-surface-700">
            <h2 class="font-semibold text-surface-900 dark:text-surface-50">Historique des exécutions</h2>
            <Button icon="pi pi-refresh" outlined size="small" :loading="loadingExecutions" @click="fetchExecutions" />
          </div>

          <DataTable :value="executions" :loading="loadingExecutions" class="p-datatable-sm" striped-rows>
            <Column field="id" header="#" style="width: 70px" />
            <Column field="status" header="Statut" style="width: 130px">
              <template #body="{ data }">
                <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" class="text-xs" />
              </template>
            </Column>
            <Column field="result_count" header="Lignes" style="width: 100px" />
            <Column field="completed_at" header="Terminé le">
              <template #body="{ data }">
                {{ data.completed_at ? new Date(data.completed_at).toLocaleString('fr-FR') : '—' }}
              </template>
            </Column>
            <Column header="Actions" style="width: 100px">
              <template #body="{ data }">
                <Button
                  icon="pi pi-download"
                  outlined
                  size="small"
                  :disabled="data.status !== 'completed'"
                  @click="download(data)"
                />
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-10 text-surface-400">
                <p>Aucune exécution pour ce rapport.</p>
                <p class="text-sm mt-1">Cliquez sur <strong>Exécuter</strong> pour lancer le rapport.</p>
              </div>
            </template>
          </DataTable>
        </div>
      </template>

      <div v-else class="text-center py-16 text-surface-400">
        <i class="pi pi-exclamation-circle text-4xl mb-3 block" />
        <p>Rapport introuvable.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'

// Chantier 8 (Reporting): ReportsIndex.vue's "eye" (view) action navigated
// to /reporting/reports/{id}, a route that didn't exist anywhere in the
// app. Minimal wrapper page — self-fetches the report definition + its
// execution history via the real, already-routed reporting API.

interface ReportDefinitionData {
  id: number
  name: string
  description: string | null
  module: string
  report_type: string
  output_format: string
  is_system: boolean
}

const props = defineProps<{ reportId: number }>()

const loading = ref(true)
const loadingExecutions = ref(false)
const running = ref(false)
const report = ref<ReportDefinitionData | null>(null)
const executions = ref<Record<string, any>[]>([])

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''

const fetchReport = async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/v1/reporting/reports/${props.reportId}`, { headers: { Accept: 'application/json' } })
    report.value = res.ok ? await res.json() : null
  } catch (e) {
    console.error('Erreur chargement rapport', e)
    report.value = null
  } finally {
    loading.value = false
  }
}

const fetchExecutions = async () => {
  loadingExecutions.value = true
  try {
    const res = await fetch(`/api/v1/reporting/reports/${props.reportId}/executions`, { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const data = await res.json()
      executions.value = data.data ?? []
    }
  } catch (e) {
    console.error('Erreur chargement exécutions', e)
  } finally {
    loadingExecutions.value = false
  }
}

const runNow = async () => {
  running.value = true
  try {
    await fetch(`/api/v1/reporting/reports/${props.reportId}/run`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({}),
    })
    await fetchExecutions()
  } catch (e) {
    console.error('Erreur exécution rapport', e)
  } finally {
    running.value = false
  }
}

const download = (execution: Record<string, any>) => {
  window.open(`/api/v1/reporting/executions/${execution.id}/download`, '_blank')
}

const statusLabel = (s: string) => ({ pending: 'En attente', running: 'En cours', completed: 'Terminé', failed: 'Échoué' }[s] ?? s)
const statusSeverity = (s: string) => ({ pending: 'secondary', running: 'info', completed: 'success', failed: 'danger' }[s] ?? 'secondary')

onMounted(() => {
  fetchReport()
  fetchExecutions()
})
</script>
