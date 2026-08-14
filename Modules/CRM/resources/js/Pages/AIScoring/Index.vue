<template>
  <AppLayout>
    <Head title="Scoring IA des Leads" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Scoring IA des Leads
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Priorisez vos leads grâce à l'intelligence artificielle
          </p>
        </div>
        <Button
          icon="pi pi-sync"
          label="Recalculer les scores"
          @click="recalculate"
          :loading="loading"
        />
      </div>

      <!-- AI Assistant Panel -->
      <AiAssistantPanel v-if="guidance" :guidance="guidance" />

      <!-- KPI Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Score moyen</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.avgScore }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
              <i class="pi pi-chart-bar text-blue-600 dark:text-blue-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Leads chauds (&gt;80)</p>
              <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.hotLeads }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
              <i class="pi pi-fire text-green-600 dark:text-green-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Conversions prédites ce mois</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.predictedConversions }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
              <i class="pi pi-arrow-right-arrow-left text-purple-600 dark:text-purple-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Précision modèle</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.modelAccuracy }}%</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center">
              <i class="pi pi-check-circle text-orange-600 dark:text-orange-400 text-lg" />
            </div>
          </div>
        </div>
      </div>

      <!-- Leaderboard -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">
            Classement des Leads par Score
          </h2>
          <Tag value="Mis à jour il y a 5 min" severity="info" />
        </div>

        <DataTable
          :value="leads"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
        >
          <Column field="name" header="Lead / Contact" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-xs font-bold text-primary-700 dark:text-primary-300">
                  {{ data.name.charAt(0) }}
                </div>
                <div>
                  <p class="font-medium text-surface-900 dark:text-surface-50">{{ data.name }}</p>
                  <p class="text-xs text-surface-400">{{ data.company }}</p>
                </div>
              </div>
            </template>
          </Column>

          <Column field="score" header="Score" sortable style="width: 10rem">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <Tag
                  :value="String(data.score)"
                  :severity="scoreSeverity(data.score)"
                  class="font-bold text-sm"
                />
                <div class="flex-1 bg-surface-200 dark:bg-surface-600 rounded-full h-2 w-16">
                  <div
                    :class="['h-2 rounded-full', scoreBarColor(data.score)]"
                    :style="{ width: data.score + '%' }"
                  />
                </div>
              </div>
            </template>
          </Column>

          <Column field="conversionProbability" header="Probabilité conversion" sortable style="width: 14rem">
            <template #body="{ data }">
              <span class="font-medium">{{ data.conversionProbability }}%</span>
            </template>
          </Column>

          <Column field="lastSignal" header="Dernier signal" style="width: 14rem">
            <template #body="{ data }">
              <div>
                <p class="text-sm text-surface-700 dark:text-surface-200">{{ data.lastSignal }}</p>
                <p class="text-xs text-surface-400">{{ data.signalDate }}</p>
              </div>
            </template>
          </Column>

          <Column header="Action" style="width: 8rem">
            <template #body="{ data }">
              <Button
                icon="pi pi-phone"
                label="Contacter"
                size="small"
                @click="contactLead(data)"
              />
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-12 text-surface-400">
              Aucun lead scoré pour le moment.
            </div>
          </template>
        </DataTable>
      </div>

      <!-- Scoring Config Panel -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">
          Critères de scoring et pondérations
        </h2>
        <p class="text-surface-500 text-sm mb-6">
          Ces critères sont utilisés par le modèle IA pour calculer le score de chaque lead.
        </p>

        <div class="space-y-5">
          <div v-for="criterion in scoringCriteria" :key="criterion.key" class="flex items-center gap-4">
            <div class="w-8 h-8 rounded-lg bg-surface-100 dark:bg-surface-700 flex items-center justify-center flex-shrink-0">
              <i :class="['text-sm text-surface-500', criterion.icon]" />
            </div>
            <div class="flex-1">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ criterion.label }}</span>
                <span class="text-sm font-bold text-primary-600">{{ criterion.weight }}%</span>
              </div>
              <Slider
                v-model="criterion.weight"
                :min="0"
                :max="50"
                :disabled="true"
                class="w-full"
              />
            </div>
          </div>
        </div>

        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
          <p class="text-sm text-blue-800 dark:text-blue-200">
            <i class="pi pi-info-circle mr-2" />
            Les pondérations sont ajustées automatiquement par le modèle IA selon les données historiques de conversion. Contactez votre administrateur pour les modifier.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, computed} from 'vue'
import { Head, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Slider from 'primevue/slider'
import AppLayout from '@/Layouts/AppLayout.vue'
import AiAssistantPanel from '@/Components/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['sales-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


interface Lead {
  id: number
  name: string
  company: string
  score: number
  conversionProbability: number
  lastSignal: string
  signalDate: string
}

interface ScoringCriterion {
  key: string
  label: string
  icon: string
  weight: number
}

const { guidance } = useAiAssistant('CRM', 'create_opportunity')

const loading = ref(false)

const stats = ref({
  avgScore: 68,
  hotLeads: 3,
  predictedConversions: 12,
  modelAccuracy: 87,
})

const leads = ref<Lead[]>([
  { id: 1, name: 'Aminata Diallo', company: 'Tech Dakar SARL', score: 94, conversionProbability: 82, lastSignal: 'Ouverture email', signalDate: 'Aujourd\'hui 09:14' },
  { id: 2, name: 'Kofi Mensah', company: 'GhanaLink Ltd', score: 88, conversionProbability: 74, lastSignal: 'Visite page tarifs', signalDate: 'Aujourd\'hui 08:47' },
  { id: 3, name: 'Fatou Ndiaye', company: 'Abidjan Négoce', score: 85, conversionProbability: 70, lastSignal: 'Demande de démo', signalDate: 'Hier 16:30' },
  { id: 4, name: 'Jean-Pierre Mbeki', company: 'Congo Trading SA', score: 72, conversionProbability: 55, lastSignal: 'Clic sur brochure', signalDate: 'Hier 14:12' },
  { id: 5, name: 'Laila Benali', company: 'Maroc Export SARL', score: 67, conversionProbability: 48, lastSignal: 'Ouverture email', signalDate: 'Il y a 2 jours' },
  { id: 6, name: 'Ibrahim Touré', company: 'Mali Digital', score: 61, conversionProbability: 40, lastSignal: 'Visite site web', signalDate: 'Il y a 3 jours' },
  { id: 7, name: 'Amira Hassan', company: 'Cairo Tech Group', score: 52, conversionProbability: 30, lastSignal: 'Aucun signal récent', signalDate: 'Il y a 5 jours' },
  { id: 8, name: 'Sylvain Kaboré', company: 'Ouaga Business', score: 45, conversionProbability: 22, lastSignal: 'Inscription newsletter', signalDate: 'Il y a 7 jours' },
])

const scoringCriteria = ref<ScoringCriterion[]>([
  { key: 'email_opens', label: 'Ouvertures d\'emails', icon: 'pi pi-envelope', weight: 20 },
  { key: 'page_visits', label: 'Visites de pages (site)', icon: 'pi pi-eye', weight: 25 },
  { key: 'firmographics', label: 'Firmographie (taille, secteur)', icon: 'pi pi-building', weight: 30 },
  { key: 'last_activity', label: 'Dernière activité (récence)', icon: 'pi pi-clock', weight: 25 },
])

const scoreSeverity = (score: number): string => {
  if (score >= 80) return 'success'
  if (score >= 60) return 'warn'
  return 'danger'
}

const scoreBarColor = (score: number): string => {
  if (score >= 80) return 'bg-green-500'
  if (score >= 60) return 'bg-yellow-500'
  return 'bg-red-500'
}

const recalculate = async () => {
  loading.value = true
  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 1200))
  loading.value = false
}

const contactLead = (lead: Lead) => {
  console.log('Contacter lead:', lead.name)
}

onMounted(() => {
  // Data is pre-loaded as mock
})
</script>
