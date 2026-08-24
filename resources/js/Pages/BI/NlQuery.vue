<template>
  <AppLayout title="Question &amp; Réponse IA">
    <div class="max-w-3xl mx-auto space-y-6 py-6 px-4">
      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Ask your data ✨</h1>
        <p class="text-surface-500 dark:text-surface-400">Posez une question en langage naturel, obtenez un graphique</p>
      </div>

      <!-- Suggestions rapides -->
      <div class="flex flex-wrap gap-2">
        <button
          v-for="s in suggestions"
          :key="s"
          @click="question = s"
          class="bg-primary-50 dark:bg-primary-900/20 text-blue-700 text-sm px-3 py-1.5 rounded-full hover:bg-blue-100 transition-colors"
        >
          {{ s }}
        </button>
      </div>

      <!-- Input -->
      <div class="flex gap-2">
        <InputText
          v-model="question"
          class="flex-1"
          placeholder="Ex: Montre-moi les ventes par mois..."
          @keydown.enter="ask"
        />
        <Button label="Analyser ✨" @click="ask" :loading="loading" />
      </div>

      <!-- Résultat -->
      <div v-if="result" class="bg-surface-0 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-6">
        <p class="text-xs text-surface-400 dark:text-surface-500 font-mono mb-4">{{ result.sql }}</p>

        <VueApexCharts
          v-if="result.chart_type === 'bar'"
          type="bar"
          height="250"
          :options="barOptions"
          :series="barSeries"
        />

        <VueApexCharts
          v-else-if="result.chart_type === 'donut'"
          type="donut"
          height="250"
          :options="donutOptions"
          :series="donutSeries"
        />

        <div v-else-if="result.chart_type === 'kpi'" class="text-center py-6">
          <p class="text-5xl font-bold text-primary-700 dark:text-primary-300">
            {{ result.data[0]?.total ?? result.data[0]?.count }}
          </p>
          <p class="text-surface-400 dark:text-surface-500 mt-2 text-sm">{{ question }}</p>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-16 text-surface-300 dark:text-surface-600">
        <i class="pi pi-chart-bar text-6xl mb-4 block" />
        <p class="text-surface-400 dark:text-surface-500">Posez une question pour voir les données</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, defineAsyncComponent } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, InputText } from 'primevue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import axios from 'axios'

const { guidance } = useAiAssistant('BI', 'natural_language_query')

// Lazy load heavy chart library - loaded only when component renders
const VueApexCharts = defineAsyncComponent(() => import('vue-apexcharts'))

const question = ref('')
const loading = ref(false)
const result = ref<any>(null)

const suggestions = [
  'Ventes par mois cette année',
  'Tickets par statut',
  'Employés par département',
  'CA top 5 clients',
]

async function ask(): Promise<void> {
  if (!question.value.trim()) return
  loading.value = true
  try {
    const { data } = await axios.post('/api/v1/bi/nl-query', { question: question.value })
    result.value = data
  } finally {
    loading.value = false
  }
}

const barSeries = computed(() => [
  {
    name: 'Valeur',
    data: result.value?.data?.map((d: any) => Object.values(d)[1]) ?? [],
  },
])

const barOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  xaxis: { categories: result.value?.data?.map((d: any) => Object.values(d)[0]) ?? [] },
}))

const donutSeries = computed(() => result.value?.data?.map((d: any) => d.count) ?? [])

const donutOptions = computed(() => ({
  labels: result.value?.data?.map((d: any) => d.status ?? d.label) ?? [],
}))
</script>
