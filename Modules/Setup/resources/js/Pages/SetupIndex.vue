<template>
  <AppLayout>
    <Head title="Configuration — WideHalo ERP" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Configuration
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Gérez l'onboarding et les imports de données de votre ERP
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="Nouvel import"
          @click="startNewImport"
        />
      </div>

      <!-- Setup progress card (if no setup done yet) -->
      <div
        v-if="!setupComplete"
        class="bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-xl p-6"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="space-y-2">
            <h2 class="text-lg font-semibold">Configurez votre ERP en 5 minutes</h2>
            <p class="text-primary-100 text-sm">
              Suivez l'assistant de configuration pour importer vos données et personnaliser WideHalo pour votre activité.
            </p>
            <div class="flex flex-wrap gap-3 pt-2">
              <div v-for="task in setupTasks" :key="task.label" class="flex items-center gap-1.5 text-sm">
                <i
                  :class="[
                    'text-base',
                    task.done ? 'pi pi-check-circle text-green-300' : 'pi pi-circle text-primary-300'
                  ]"
                />
                <span :class="task.done ? 'line-through text-primary-200' : 'text-white'">{{ task.label }}</span>
              </div>
            </div>
          </div>
          <Button
            label="Lancer l'assistant"
            icon="pi pi-play"
            severity="contrast"
            @click="startNewImport"
          />
        </div>
        <ProgressBar
          :value="setupProgress"
          class="mt-4 h-2"
          :show-value="false"
        />
        <p class="text-primary-200 text-xs mt-1">{{ setupProgress }}% complété</p>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 font-medium">Sessions d'import</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : sessions.length }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
              <i class="pi pi-upload text-blue-600 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 font-medium">Lignes importées</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : totalImportedRows.toLocaleString() }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
              <i class="pi pi-database text-green-600 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 font-medium">Dernier import</p>
              <p class="text-lg font-semibold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : (lastSessionDate ?? 'Aucun') }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
              <i class="pi pi-calendar text-violet-600 text-lg" />
            </div>
          </div>
        </div>
      </div>

      <!-- Import history -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
        <div class="flex items-center justify-between px-5 py-4 border-b border-surface-200 dark:border-surface-700">
          <h2 class="font-semibold text-surface-900 dark:text-surface-50">Historique des imports</h2>
          <Button
            icon="pi pi-refresh"
            outlined
            size="small"
            :loading="loading"
            @click="fetchSessions"
          />
        </div>

        <div v-if="loading" class="p-5 space-y-3">
          <Skeleton v-for="i in 3" :key="i" height="3rem" />
        </div>

        <DataTable
          v-else
          :value="sessions"
          class="p-datatable-sm"
          striped-rows
        >
          <Column field="id" header="#" style="width: 60px">
            <template #body="{ data }">
              <span class="text-surface-400 text-xs font-mono">{{ data.id }}</span>
            </template>
          </Column>
          <Column field="filename" header="Fichier / Source">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <i class="pi pi-file text-surface-400" />
                <span class="font-medium text-surface-900 dark:text-surface-50">{{ data.filename ?? 'Import manuel' }}</span>
              </div>
            </template>
          </Column>
          <Column field="status" header="Statut" style="width: 120px">
            <template #body="{ data }">
              <Tag
                :value="statusLabel(data.status)"
                :severity="statusSeverity(data.status)"
              />
            </template>
          </Column>
          <Column field="imported_rows" header="Lignes importées" style="width: 160px">
            <template #body="{ data }">
              {{ data.imported_rows?.toLocaleString() ?? '—' }}
            </template>
          </Column>
          <Column field="created_at" header="Date" style="width: 180px">
            <template #body="{ data }">
              {{ formatDate(data.created_at) }}
            </template>
          </Column>
          <Column header="Actions" style="width: 80px">
            <template #body="{ data }">
              <Button
                icon="pi pi-eye"
                outlined
                size="small"
                @click="viewSession(data)"
              />
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-16 text-surface-400">
              <i class="pi pi-inbox text-5xl mb-4 block" />
              <p class="font-medium">Aucun import effectué</p>
              <p class="text-sm mt-1">Cliquez sur <strong>Nouvel import</strong> pour démarrer l'assistant.</p>
            </div>
          </template>
        </DataTable>
      </div>
    </div>
    <AIAssistantPanel v-if="guidance" :guidance="guidance" class="fixed bottom-4 right-4 z-50" />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, router, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import ProgressBar from 'primevue/progressbar'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


interface ImportSession {
  id: number
  filename: string | null
  status: 'pending' | 'running' | 'completed' | 'failed'
  imported_rows: number | null
  created_at: string
}

const { guidance } = useAiAssistant('Setup', 'import_file')

const loading = ref(false)
const sessions = ref<ImportSession[]>([])
const setupComplete = ref(false)

const setupTasks = [
  { label: 'Infos entreprise', done: false },
  { label: 'Devise et pays', done: false },
  { label: 'Premier import', done: false },
]

const setupProgress = computed(() => {
  const done = setupTasks.filter(t => t.done).length
  return Math.round((done / setupTasks.length) * 100)
})

const totalImportedRows = computed(() =>
  sessions.value.reduce((sum, s) => sum + (s.imported_rows ?? 0), 0),
)

const lastSessionDate = computed(() => {
  if (!sessions.value.length) return null
  const sorted = [...sessions.value].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
  )
  return formatDate(sorted[0].created_at)
})

const fetchSessions = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/v1/setup/sessions', {
      headers: { Accept: 'application/json' },
    })
    const data = await res.json()
    sessions.value = data.data ?? data ?? []
    setupComplete.value = sessions.value.some(s => s.status === 'completed')
  } catch (e) {
    console.error('Erreur chargement sessions', e)
  } finally {
    loading.value = false
  }
}

const formatDate = (dateStr: string): string => {
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const statusLabel = (status: string): string => {
  const labels: Record<string, string> = {
    pending: 'En attente',
    running: 'En cours',
    completed: 'Terminé',
    failed: 'Échoué',
  }
  return labels[status] ?? status
}

const statusSeverity = (status: string): string => {
  switch (status) {
    case 'completed': return 'success'
    case 'running': return 'info'
    case 'failed': return 'danger'
    default: return 'secondary'
  }
}

const startNewImport = () => {
  router.visit('/setup/wizard')
}

const viewSession = (session: ImportSession) => {
  router.visit(`/setup/sessions/${session.id}`)
}

onMounted(() => {
  fetchSessions()
})
</script>
