<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Satisfaction client (CSAT / NPS)</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Scores de satisfaction, enquêtes et performance des agents</p>
      </div>
      <div class="flex gap-2">
        <Select v-model="periodFilter" :options="periodOptions" option-label="label" option-value="value" class="w-44" />
        <Button label="Exporter" icon="pi pi-download" severity="secondary" outlined />
      </div>
    </div>

    <!-- Main KPI stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card class="border-l-4 border-green-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
              <i class="pi pi-star-fill text-green-600 text-xl"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Score CSAT global</p>
              <p class="text-3xl font-bold text-green-600">{{ stats.csat }}%</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-blue-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="pi pi-chart-bar text-blue-600 text-xl"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">NPS</p>
              <p class="text-3xl font-bold text-blue-600">{{ stats.nps }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-purple-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
              <i class="pi pi-inbox text-purple-600 text-xl"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Réponses collectées</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.reponses }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-yellow-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
              <i class="pi pi-percentage text-yellow-600 text-xl"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Taux de réponse</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.tauxReponse }}%</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Rating distribution -->
      <Card>
        <template #header>
          <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
            <span class="font-semibold text-gray-700 dark:text-gray-200">Distribution des notes CSAT</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <div v-for="rating in ratingDistribution" :key="rating.score" class="flex items-center gap-3">
              <div class="flex items-center gap-1 w-20 flex-shrink-0">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ rating.score }}</span>
                <div class="flex gap-0.5">
                  <i v-for="n in rating.score" :key="n" class="pi pi-star-fill text-yellow-400 text-xs"></i>
                </div>
              </div>
              <div class="flex-1">
                <ProgressBar :value="rating.pct" style="height: 20px" :class="rating.progressClass" />
              </div>
              <div class="w-20 text-right flex-shrink-0">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ rating.count }}</span>
                <span class="text-xs text-gray-400 ml-1">({{ rating.pct }}%)</span>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- NPS breakdown -->
      <Card>
        <template #header>
          <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
            <span class="font-semibold text-gray-700 dark:text-gray-200">Net Promoter Score (NPS)</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-5">
            <!-- NPS score display -->
            <div class="text-center py-3">
              <p class="text-6xl font-bold text-blue-600">{{ stats.nps }}</p>
              <p class="text-sm text-gray-500 mt-1">
                NPS = Promoteurs – Détracteurs
                <span class="text-green-600 font-medium ml-1">({{ nps.promoteurs }}% – {{ nps.detracteurs }}%)</span>
              </p>
            </div>
            <!-- NPS bar -->
            <div class="relative h-6 rounded-full overflow-hidden flex">
              <div class="bg-red-400 flex items-center justify-center text-white text-xs font-medium transition-all" :style="{ width: nps.detracteurs + '%' }">
                {{ nps.detracteurs }}%
              </div>
              <div class="bg-gray-300 dark:bg-gray-500 flex items-center justify-center text-gray-700 dark:text-gray-200 text-xs font-medium transition-all" :style="{ width: nps.passifs + '%' }">
                {{ nps.passifs }}%
              </div>
              <div class="bg-green-500 flex items-center justify-center text-white text-xs font-medium transition-all" :style="{ width: nps.promoteurs + '%' }">
                {{ nps.promoteurs }}%
              </div>
            </div>
            <div class="flex justify-between text-xs">
              <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-400 inline-block"></span> Détracteurs (0–6)</div>
              <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-gray-300 inline-block"></span> Passifs (7–8)</div>
              <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> Promoteurs (9–10)</div>
            </div>
            <!-- Counts -->
            <div class="grid grid-cols-3 gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
              <div class="text-center">
                <p class="text-2xl font-bold text-red-500">{{ nps.countDetracteurs }}</p>
                <p class="text-xs text-gray-500">Détracteurs</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-bold text-gray-500">{{ nps.countPassifs }}</p>
                <p class="text-xs text-gray-500">Passifs</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-bold text-green-600">{{ nps.countPromoteurs }}</p>
                <p class="text-xs text-gray-500">Promoteurs</p>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Agent performance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card>
        <template #header>
          <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <i class="pi pi-trophy text-yellow-500"></i>
            <span class="font-semibold text-gray-700 dark:text-gray-200">Top agents — CSAT</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div v-for="(agent, i) in topAgents" :key="agent.name" class="flex items-center gap-3">
              <span
                class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white"
                :class="i === 0 ? 'bg-yellow-500' : i === 1 ? 'bg-gray-400' : 'bg-orange-400'"
              >{{ i + 1 }}</span>
              <div class="flex-1">
                <div class="flex justify-between mb-1">
                  <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ agent.name }}</span>
                  <span class="text-sm font-bold text-green-600">{{ agent.csat }}%</span>
                </div>
                <ProgressBar :value="agent.csat" class="[&_.p-progressbar-value]:bg-green-500" style="height: 6px" />
                <p class="text-xs text-gray-400 mt-0.5">{{ agent.tickets }} tickets • {{ agent.responses }} avis</p>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #header>
          <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <i class="pi pi-arrow-down text-red-500"></i>
            <span class="font-semibold text-gray-700 dark:text-gray-200">Agents à améliorer</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div v-for="agent in bottomAgents" :key="agent.name" class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                <i class="pi pi-user text-red-600 text-xs"></i>
              </div>
              <div class="flex-1">
                <div class="flex justify-between mb-1">
                  <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ agent.name }}</span>
                  <span class="text-sm font-bold text-red-500">{{ agent.csat }}%</span>
                </div>
                <ProgressBar :value="agent.csat" class="[&_.p-progressbar-value]:bg-red-400" style="height: 6px" />
                <p class="text-xs text-gray-400 mt-0.5">{{ agent.tickets }} tickets • {{ agent.responses }} avis</p>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Recent CSAT responses -->
    <Card>
      <template #header>
        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
          <span class="font-semibold text-gray-700 dark:text-gray-200">Dernières réponses CSAT</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="csatResponses"
          :paginator="true"
          :rows="10"
          responsive-layout="scroll"
          row-hover
          class="text-sm"
        >
          <Column field="ticket" header="Ticket" style="width: 90px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-blue-600 font-semibold">#{{ data.ticket }}</span>
            </template>
          </Column>
          <Column field="client" header="Client" />
          <Column field="agent" header="Agent" />
          <Column field="note" header="Note" style="width: 120px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <div class="flex gap-0.5 text-yellow-400">
                  <i v-for="n in 5" :key="n" class="pi text-xs" :class="n <= data.note ? 'pi-star-fill' : 'pi-star'"></i>
                </div>
                <span class="text-xs text-gray-500">({{ data.note }}/5)</span>
              </div>
            </template>
          </Column>
          <Column field="commentaire" header="Commentaire">
            <template #body="{ data }">
              <span class="text-xs text-gray-600 dark:text-gray-400 italic">{{ data.commentaire }}</span>
            </template>
          </Column>
          <Column field="date" header="Date" style="width: 110px">
            <template #body="{ data }">
              <span class="text-xs text-gray-500">{{ data.date }}</span>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['helpdesk-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const auth = computed(() => page.props.auth)

const periodFilter = ref('month')
const periodOptions = [
  { label: 'Ce mois', value: 'month' },
  { label: 'Ce trimestre', value: 'quarter' },
  { label: 'Cette année', value: 'year' },
]

const stats = ref({ csat: 87, nps: 42, reponses: 318, tauxReponse: 64 })

const nps = ref({
  detracteurs: 14, passifs: 30, promoteurs: 56,
  countDetracteurs: 44, countPassifs: 96, countPromoteurs: 178,
})

const ratingDistribution = ref([
  { score: 5, count: 201, pct: 63, progressClass: '[&_.p-progressbar-value]:bg-green-500' },
  { score: 4, count: 75, pct: 24, progressClass: '[&_.p-progressbar-value]:bg-lime-400' },
  { score: 3, count: 25, pct: 8, progressClass: '[&_.p-progressbar-value]:bg-yellow-400' },
  { score: 2, count: 11, pct: 3, progressClass: '[&_.p-progressbar-value]:bg-orange-400' },
  { score: 1, count: 6, pct: 2, progressClass: '[&_.p-progressbar-value]:bg-red-500' },
])

const topAgents = ref([
  { name: 'Aïssatou Bâ', csat: 96, tickets: 42, responses: 38 },
  { name: 'Seydou Ouédraogo', csat: 91, tickets: 35, responses: 29 },
  { name: 'Mariama Kouyaté', csat: 88, tickets: 28, responses: 24 },
])

const bottomAgents = ref([
  { name: 'Kofi Mensah', csat: 71, tickets: 19, responses: 14 },
  { name: 'Pierre-André Tabi', csat: 76, tickets: 23, responses: 17 },
])

const csatResponses = ref([
  { ticket: '1038', client: 'Mireille Tonga', agent: 'Ibrahim Traoré', note: 5, commentaire: 'Problème résolu très rapidement, excellent service !', date: '23/05/2026' },
  { ticket: '1036', client: 'Aminata Camara', agent: 'Aïssatou Bâ', note: 5, commentaire: 'Très réactive et compétente, merci !', date: '22/05/2026' },
  { ticket: '1034', client: 'Sali Ba', agent: 'Seydou Ouédraogo', note: 4, commentaire: 'Bon support, mais délai un peu long.', date: '21/05/2026' },
  { ticket: '1033', client: 'Rachid Khaldi', agent: 'Aïssatou Bâ', note: 4, commentaire: 'Réponse correcte mais je pensais que ce serait plus rapide.', date: '20/05/2026' },
  { ticket: '1031', client: 'Oumar Sanogo', agent: 'Ibrahim Traoré', note: 2, commentaire: 'Le problème n\'est pas encore totalement résolu.', date: '20/05/2026' },
  { ticket: '1029', client: 'Kouamé Assi', agent: 'Seydou Ouédraogo', note: 5, commentaire: 'Parfait, efficace !', date: '19/05/2026' },
  { ticket: '1027', client: 'Fatou Koné', agent: 'Aïssatou Bâ', note: 3, commentaire: 'Acceptable mais espère mieux la prochaine fois.', date: '18/05/2026' },
  { ticket: '1025', client: 'Ndéye Fall', agent: 'Ibrahim Traoré', note: 5, commentaire: 'Excellent ! L\'agent a été très patient.', date: '17/05/2026' },
])
</script>
