<template>
  <AppLayout>
    <Head title="Import assisté par IA — Life MDG ERP" />

    <div class="space-y-6 max-w-4xl mx-auto">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Import assisté par IA</h1>
        <p class="text-surface-500 text-sm mt-1">
          Déposez un fichier, l'IA détecte l'entité et propose les correspondances de colonnes — une
          alternative rapide, en une seule page, à l'assistant d'import pas-à-pas.
        </p>
      </div>

      <!-- Phase: upload -->
      <div v-if="phase === 'upload'" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
        <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Fichier (Excel, CSV ou PDF)</label>
        <FileUpload mode="basic" accept=".xlsx,.xls,.csv,.pdf,.txt" :max-file-size="52428800" choose-label="Parcourir…" class="w-full" @select="onFileSelect" />
        <div v-if="selectedFile" class="flex items-center gap-2 text-sm bg-surface-50 dark:bg-surface-700 rounded-lg p-3">
          <i class="pi pi-file text-primary-500" />
          <span>{{ selectedFile.name }}</span>
        </div>
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <div class="flex justify-end">
          <Button label="Analyser le fichier" icon="pi pi-sparkles" icon-pos="right" :loading="loading" :disabled="!selectedFile" @click="analyzeFile" />
        </div>
      </div>

      <!-- Phase: analyzing -->
      <div v-else-if="phase === 'analyzing'" class="text-center py-10">
        <ProgressSpinner />
        <p class="text-surface-500 mt-4">Analyse du fichier et détection de l'entité en cours…</p>
      </div>

      <!-- Phase: mapping -->
      <div v-else-if="phase === 'mapping'" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
        <div class="flex items-center gap-2 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
          <i class="pi pi-sparkles text-primary-600" />
          <span class="text-sm text-primary-800 dark:text-primary-200">
            Entité détectée : <strong>{{ entityLabel }}</strong> — {{ estimatedRows }} ligne(s) estimée(s).
          </span>
        </div>
        <p v-for="w in warnings" :key="w" class="text-xs text-amber-600 dark:text-amber-400">{{ w }}</p>

        <DataTable :value="columns" class="p-datatable-sm" striped-rows>
          <Column field="source" header="Colonne du fichier" style="min-width: 180px" />
          <Column header="Champ Life MDG" style="min-width: 200px">
            <template #body="{ data }">
              <Select v-model="data.target" :options="targetOptions" option-label="label" option-value="value" placeholder="Ignorer" show-clear class="w-full text-sm" />
            </template>
          </Column>
          <Column header="Confiance IA" style="width: 120px">
            <template #body="{ data }">
              <Tag v-if="data.confidence >= 0.7" value="Élevée" severity="success" class="text-xs" />
              <Tag v-else-if="data.confidence > 0" value="Faible" severity="warning" class="text-xs" />
              <Tag v-else value="—" severity="secondary" class="text-xs" />
            </template>
          </Column>
        </DataTable>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <div class="flex justify-between">
          <Button label="Recommencer" severity="secondary" outlined @click="reset" />
          <Button label="Valider et lancer l'import" icon="pi pi-play" :loading="loading" @click="validateAndExecute" />
        </div>
      </div>

      <!-- Phase: importing (queued, polling) -->
      <div v-else-if="phase === 'importing'" class="text-center py-10 space-y-3">
        <ProgressSpinner />
        <p class="text-surface-500">Import en cours en arrière-plan… ({{ progress }}%)</p>
      </div>

      <!-- Phase: done -->
      <div v-else-if="phase === 'done'" class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
        <i class="pi pi-check-circle text-green-600 text-2xl" />
        <div>
          <p class="font-semibold text-green-800 dark:text-green-200">Import terminé</p>
          <p class="text-sm text-green-700 dark:text-green-300">{{ imported }} ligne(s) importée(s){{ skipped ? ` · ${skipped} ignorée(s)` : '' }}</p>
        </div>
      </div>

      <!-- Phase: failed -->
      <div v-else-if="phase === 'failed'" class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
        <i class="pi pi-times-circle text-red-600 text-2xl mt-0.5" />
        <div>
          <p class="font-semibold text-red-800 dark:text-red-200">Erreur lors de l'import</p>
          <p class="text-sm text-red-700 dark:text-red-300">{{ error }}</p>
        </div>
      </div>

      <div v-if="(phase === 'done' || phase === 'failed')" class="flex justify-end">
        <Button label="Nouvel import" icon="pi pi-refresh" severity="secondary" outlined @click="reset" />
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" class="fixed bottom-4 right-4 z-50" />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
/**
 * Chantier 32.10 (deep 14-layer audit, layer 9 — fake/dead classification):
 * DataImportController/AiDataImportService/ImportDataJob (Modules\Setup)
 * were confirmed, via grep across the entire repo, to have ZERO real Vue
 * caller anywhere — a fully-built, RBAC-correct, routed pipeline (already
 * fixed this chantier: real target-field mismatches, a stock-entity
 * removal, a phantom-tenant-column write bug) that had never actually been
 * activated. Classified "activate" rather than "delete": it is a real,
 * distinct capability (single-shot AI/heuristic mapping with a real
 * fallback, vs. the manual step-by-step ImportDataFlow.vue pipeline) that
 * matches this app's own "AI Assisted First"/"Simplicity First" principles
 * — deleting a genuinely-working, just-fixed pipeline with no other
 * blocker would have been the wrong call. This page is the real producer.
 */
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Setup', 'import_file')

interface ColumnRow { source: string; target: string; confidence: number }

const phase = ref<'upload' | 'analyzing' | 'mapping' | 'importing' | 'done' | 'failed'>('upload')
const loading = ref(false)
const error = ref('')

const selectedFile = ref<File | null>(null)
const filePath = ref<string | null>(null)
const entityLabel = ref('')
const estimatedRows = ref(0)
const warnings = ref<string[]>([])
const columns = ref<ColumnRow[]>([])

const jobId = ref<string | null>(null)
const progress = ref(0)
const imported = ref(0)
const skipped = ref(0)

const targetOptions = computed(() => {
  const seen = new Set<string>()
  columns.value.forEach(c => { if (c.target && c.target !== 'ignore') seen.add(c.target) })
  return Array.from(seen).map(v => ({ label: v, value: v }))
})

const onFileSelect = (event: { files: File[] }) => {
  selectedFile.value = event.files[0] ?? null
}

const reset = () => {
  phase.value = 'upload'
  loading.value = false
  error.value = ''
  selectedFile.value = null
  filePath.value = null
  entityLabel.value = ''
  columns.value = []
  jobId.value = null
  progress.value = 0
  imported.value = 0
  skipped.value = 0
  stopPolling()
}

const analyzeFile = async () => {
  if (!selectedFile.value) return
  loading.value = true
  error.value = ''
  phase.value = 'analyzing'

  try {
    const form = new FormData()
    form.append('file', selectedFile.value)

    const { data } = await axios.post('/api/v1/setup/import/analyze', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    filePath.value = data.file_path
    entityLabel.value = data.detected_entity
    estimatedRows.value = data.estimated_rows ?? 0
    warnings.value = data.warnings ?? []
    columns.value = (data.columns ?? []).map((c: ColumnRow) => ({ ...c }))

    phase.value = 'mapping'
  } catch (e) {
    error.value = axios.isAxiosError(e) ? (e.response?.data?.details ?? e.response?.data?.error ?? 'Erreur inattendue.') : 'Erreur inattendue.'
    phase.value = 'upload'
  } finally {
    loading.value = false
  }
}

let pollTimer: ReturnType<typeof setInterval> | null = null
const stopPolling = () => {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
}

const pollStatus = () => {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (!jobId.value) return
    try {
      const { data } = await axios.get(`/api/v1/setup/import/status/${jobId.value}`)
      progress.value = data.progress ?? 0
      if (data.status === 'completed') {
        imported.value = data.imported ?? 0
        skipped.value = data.skipped ?? 0
        phase.value = 'done'
        stopPolling()
      } else if (data.status === 'failed') {
        error.value = (data.errors ?? [])[0] ?? 'L\'import a échoué.'
        phase.value = 'failed'
        stopPolling()
      }
    } catch {
      // transient — keep polling until the interval naturally stops on completion
    }
  }, 2000)
}

const validateAndExecute = async () => {
  if (!filePath.value) return
  loading.value = true
  error.value = ''

  try {
    const mapping = columns.value
      .filter(c => c.target && c.target !== 'ignore')
      .map(c => ({ source: c.source, target: c.target }))

    if (mapping.length === 0) throw new Error('Associez au moins une colonne avant de lancer l\'import.')

    const { data: validation } = await axios.post('/api/v1/setup/import/validate', {
      file_path: filePath.value,
      mapping,
    })

    if (validation.valid === false) {
      error.value = (validation.errors ?? []).join(' ') || 'Validation échouée.'
      loading.value = false
      return
    }

    const { data: exec } = await axios.post('/api/v1/setup/import/execute', {
      file_path: filePath.value,
      mapping,
    })

    jobId.value = exec.job_id
    phase.value = 'importing'
    pollStatus()
  } catch (e) {
    error.value = axios.isAxiosError(e) ? (e.response?.data?.details ?? e.response?.data?.error ?? 'Erreur inattendue.') : (e instanceof Error ? e.message : 'Erreur inattendue.')
  } finally {
    loading.value = false
  }
}
</script>
