<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold text-surface-900 dark:text-surface-50">Importer des données existantes (optionnel)</h3>
      <Button v-if="phase !== 'target'" label="Recommencer" text size="small" @click="reset" />
    </div>

    <!-- Phase: choose target + file -->
    <div v-if="phase === 'target'" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Type de données</label>
          <Select
            v-model="selectedTargetKey"
            :options="targetOptions"
            option-label="label"
            option-value="key"
            placeholder="Sélectionner un type de données"
            class="w-full"
          />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Fichier (Excel, CSV ou PDF)</label>
          <FileUpload mode="basic" accept=".xlsx,.xls,.csv,.pdf" :max-file-size="10000000" choose-label="Parcourir…" class="w-full" @select="onFileSelect" />
        </div>
      </div>
      <div v-if="selectedFile" class="flex items-center gap-2 text-sm bg-surface-50 dark:bg-surface-700 rounded-lg p-3">
        <i class="pi pi-file text-primary-500" />
        <span>{{ selectedFile.name }}</span>
      </div>
      <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      <div class="flex justify-end gap-2">
        <Button label="Ignorer l'import" severity="secondary" outlined @click="$emit('skip')" />
        <Button label="Analyser le fichier" icon="pi pi-arrow-right" icon-pos="right" :loading="loading" :disabled="!selectedTargetKey || !selectedFile" @click="createAndAnalyze" />
      </div>
    </div>

    <!-- Phase: analyzing / suggesting -->
    <div v-else-if="phase === 'analyzing'" class="text-center py-10">
      <ProgressSpinner />
      <p class="text-surface-500 mt-4">Analyse du fichier et suggestions IA en cours…</p>
    </div>

    <!-- Phase: mapping -->
    <div v-else-if="phase === 'mapping'" class="space-y-4">
      <div class="flex items-center gap-2 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
        <i class="pi pi-sparkles text-primary-600" />
        <span class="text-sm text-primary-800 dark:text-primary-200">
          {{ sourceSchema?.row_count ?? 0 }} ligne(s) détectée(s). Vérifiez les correspondances avant de lancer l'import.
        </span>
      </div>

      <DataTable :value="mappingRows" class="p-datatable-sm" striped-rows>
        <Column field="label" header="Champ Life MDG" style="min-width: 200px">
          <template #body="{ data }">
            <span class="text-sm">{{ data.label }}<span v-if="data.required" class="text-red-500"> *</span></span>
          </template>
        </Column>
        <Column field="source_field" header="Colonne source (votre fichier)" style="min-width: 220px">
          <template #body="{ data }">
            <Select
              v-model="data.source_field"
              :options="sourceColumnOptions"
              option-label="label"
              option-value="value"
              placeholder="Ignorer ce champ"
              show-clear
              class="w-full text-sm"
            />
          </template>
        </Column>
        <Column header="Statut" style="width: 100px">
          <template #body="{ data }">
            <Tag v-if="data.source_field" value="Mappé" severity="success" class="text-xs" />
            <Tag v-else-if="data.required" value="Manquant" severity="danger" class="text-xs" />
            <Tag v-else value="Ignoré" severity="secondary" class="text-xs" />
          </template>
        </Column>
      </DataTable>

      <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      <div class="flex justify-end gap-2">
        <Button label="Ignorer l'import" severity="secondary" outlined @click="$emit('skip')" />
        <Button label="Lancer l'import" icon="pi pi-play" :loading="loading" @click="saveMappingsAndExecute" />
      </div>
    </div>

    <!-- Phase: importing (queued, polling) -->
    <div v-else-if="phase === 'importing'" class="text-center py-10 space-y-3">
      <ProgressSpinner />
      <p class="text-surface-500">Import en cours en arrière-plan… ({{ jobStatus }})</p>
    </div>

    <!-- Phase: done -->
    <div v-else-if="phase === 'done'" class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
      <i class="pi pi-check-circle text-green-600 text-2xl" />
      <div>
        <p class="font-semibold text-green-800 dark:text-green-200">Import terminé</p>
        <p class="text-sm text-green-700 dark:text-green-300">{{ importedRows }} ligne(s) importée(s){{ failedRows ? ` · ${failedRows} erreur(s)` : '' }}</p>
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

    <!-- Phase: skipped -->
    <div v-else-if="phase === 'skipped'" class="text-center py-8 text-surface-400">
      <i class="pi pi-forward text-4xl mb-3 block" />
      <p>Import ignoré — vous pourrez importer vos données plus tard depuis la page Configuration.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'

/**
 * Real, job-centric import flow (Modules\Setup\Http\Controllers\Api\SetupController,
 * backed by ImportJob/SourceSchema/FieldMapping). Replaces the previous
 * SetupWizard.vue's single-shot calls to /api/v1/setup/analyze-file,
 * /suggest-mapping, /execute-import — none of which exist; the real API
 * is create-job -> analyze -> suggest-mappings -> save-mappings -> execute
 * (async, queued — polled via GET /import-jobs/{id}).
 */

defineEmits<{ skip: [] }>()

interface TargetField { field: string; label: string; type: string; required: boolean }
interface TargetOption { key: string; label: string; module: string; entity: string; fields: TargetField[] }

const phase = ref<'target' | 'analyzing' | 'mapping' | 'importing' | 'done' | 'failed' | 'skipped'>('target')
const loading = ref(false)
const error = ref('')

const targetOptions = ref<TargetOption[]>([])
const selectedTargetKey = ref<string | null>(null)
const selectedTarget = computed(() => targetOptions.value.find(t => t.key === selectedTargetKey.value) ?? null)

const selectedFile = ref<File | null>(null)
const jobId = ref<number | null>(null)
const sourceSchema = ref<{ detected_columns: string[]; row_count: number } | null>(null)
const jobStatus = ref('')
const importedRows = ref(0)
const failedRows = ref(0)

interface MappingRow { field: string; label: string; required: boolean; source_field: string | null }
const mappingRows = ref<MappingRow[]>([])

const sourceColumnOptions = computed(() =>
  (sourceSchema.value?.detected_columns ?? []).map(c => ({ label: c, value: c })),
)

const onFileSelect = (event: { files: File[] }) => {
  selectedFile.value = event.files[0] ?? null
}

// Chantier 32.10 (deep 14-layer audit, security layer): this app runs
// Sanctum's statefulApi(), which activates real CSRF verification on every
// same-origin browser request to /api/v1/* — a raw fetch() never attaches
// a CSRF header, unlike axios, which does so automatically. This is the
// exact same bug class already found and fixed for 9 Inventory/Logistics
// pages at Chantier 19 (invisible to any Pest test, since
// VerifyCsrfToken::runningUnitTests() unconditionally bypasses the check
// whenever APP_ENV=testing — only a real browser-shaped request can
// surface it) — this component is this module's own real, live import UI
// (mounted from both SetupIndex.vue and SetupWizard.vue), so every one of
// its mutating requests below (POST/PUT) was silently 419'ing in real use.
const getCsrf = (): string =>
  (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''

const inferSourceType = (file: File): string => {
  const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
  if (ext === 'csv') return 'csv'
  if (ext === 'pdf') return 'pdf'
  return 'excel'
}

const fetchTargetSchemas = async () => {
  const res = await fetch('/api/v1/setup/source-schemas', { headers: { Accept: 'application/json' } })
  const json = await res.json()
  targetOptions.value = (json.data ?? []).map((t: { module: string; entity: string; fields: TargetField[] }) => ({
    key: `${t.module}:${t.entity}`,
    label: `${t.module} — ${t.entity}`,
    module: t.module,
    entity: t.entity,
    fields: t.fields,
  }))
}

const createAndAnalyze = async () => {
  if (!selectedTarget.value || !selectedFile.value) return
  loading.value = true
  error.value = ''
  phase.value = 'analyzing'

  try {
    const form = new FormData()
    form.append('name', selectedFile.value.name)
    form.append('source_type', inferSourceType(selectedFile.value))
    form.append('target_module', selectedTarget.value.module)
    form.append('target_entity', selectedTarget.value.entity)
    form.append('file', selectedFile.value)

    const createRes = await fetch('/api/v1/setup/import-jobs', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() }, body: form })
    const createJson = await createRes.json()
    if (!createRes.ok) throw new Error(createJson.message ?? 'Création du job d\'import impossible.')
    jobId.value = createJson.data.id

    const analyzeRes = await fetch(`/api/v1/setup/import-jobs/${jobId.value}/analyze`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() } })
    const analyzeJson = await analyzeRes.json()
    if (!analyzeRes.ok) throw new Error(analyzeJson.message ?? 'Analyse du fichier impossible.')
    sourceSchema.value = analyzeJson.data

    const suggestRes = await fetch(`/api/v1/setup/import-jobs/${jobId.value}/suggest-mappings`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() } })
    const suggestJson = await suggestRes.json()
    const suggestions: Array<{ source_field: string; target_field: string; confidence: number }> = suggestJson.data ?? []

    mappingRows.value = (selectedTarget.value.fields ?? []).map(f => ({
      field: f.field,
      label: f.label,
      required: f.required,
      source_field: suggestions.find(s => s.target_field === f.field)?.source_field ?? null,
    }))

    phase.value = 'mapping'
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erreur inattendue.'
    phase.value = 'target'
  } finally {
    loading.value = false
  }
}

let pollTimer: ReturnType<typeof setInterval> | null = null

const stopPolling = () => {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
}

const pollJobStatus = () => {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (!jobId.value) return
    const res = await fetch(`/api/v1/setup/import-jobs/${jobId.value}`, { headers: { Accept: 'application/json' } })
    const json = await res.json()
    const job = json.data
    jobStatus.value = job.status

    if (job.status === 'completed') {
      importedRows.value = job.imported_rows ?? 0
      failedRows.value = job.failed_rows ?? 0
      phase.value = 'done'
      stopPolling()
    } else if (job.status === 'failed') {
      error.value = job.error_summary?.message ?? 'L\'import a échoué.'
      phase.value = 'failed'
      stopPolling()
    }
  }, 2000)
}

const saveMappingsAndExecute = async () => {
  if (!jobId.value || !selectedTarget.value) return
  loading.value = true
  error.value = ''

  try {
    const mapped = mappingRows.value.filter(m => m.source_field)
    if (mapped.length === 0) throw new Error('Associez au moins un champ avant de lancer l\'import.')

    const payload = {
      mappings: mapped.map(m => ({
        source_field: m.source_field,
        target_field: m.field,
        target_table: selectedTarget.value!.entity,
        transform_type: 'direct',
        is_required: m.required,
        is_confirmed: true,
      })),
    }

    const saveRes = await fetch(`/api/v1/setup/import-jobs/${jobId.value}/mappings`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(payload),
    })
    if (!saveRes.ok) {
      const saveJson = await saveRes.json()
      throw new Error(saveJson.message ?? 'Enregistrement des correspondances impossible.')
    }

    const execRes = await fetch(`/api/v1/setup/import-jobs/${jobId.value}/execute`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() } })
    const execJson = await execRes.json()
    if (!execRes.ok) throw new Error(execJson.message ?? 'Lancement de l\'import impossible.')

    phase.value = 'importing'
    pollJobStatus()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Erreur inattendue.'
  } finally {
    loading.value = false
  }
}

const reset = () => {
  stopPolling()
  phase.value = 'target'
  selectedTargetKey.value = null
  selectedFile.value = null
  jobId.value = null
  sourceSchema.value = null
  mappingRows.value = []
  error.value = ''
}

onMounted(() => {
  fetchTargetSchemas()
})

onBeforeUnmount(() => {
  stopPolling()
})
</script>
