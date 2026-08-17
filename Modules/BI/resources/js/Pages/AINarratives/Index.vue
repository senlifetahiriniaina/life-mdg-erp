<template>
  <AppLayout>
    <Head title="BI · Narrations IA" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Narrations IA — Résumés Automatiques des Tableaux de Bord</h1>
          <p class="text-surface-500 text-sm mt-1">L'IA analyse vos indicateurs clés et génère des commentaires en langage naturel</p>
        </div>
        <Button label="Générer une synthèse IA" icon="pi pi-sparkles" :loading="generating" @click="generate" v-if="canManage" />
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card><template #content>
          <div class="text-2xl font-bold text-blue-600">{{ narratives.length }}</div>
          <div class="text-sm text-surface-500 mt-1">Modules avec insight</div>
        </template></Card>
        <Card><template #content>
          <div class="text-2xl font-bold text-green-600">{{ positiveCount }}</div>
          <div class="text-sm text-surface-500 mt-1">Signaux positifs</div>
        </template></Card>
        <Card><template #content>
          <div class="text-2xl font-bold text-orange-600">{{ attentionCount }}</div>
          <div class="text-sm text-surface-500 mt-1">Points d'attention</div>
        </template></Card>
        <Card><template #content>
          <div class="text-2xl font-bold text-purple-600">{{ lastLoadedLabel }}</div>
          <div class="text-sm text-surface-500 mt-1">Dernière mise à jour</div>
        </template></Card>
      </div>

      <Card v-if="aiSummary" class="border-2 border-primary-200">
        <template #header>
          <div class="px-4 pt-4 flex items-center gap-2">
            <i class="pi pi-sparkles text-primary-500" />
            <span class="font-semibold">Synthèse générée par l'IA</span>
          </div>
        </template>
        <template #content>
          <p class="text-surface-700 leading-relaxed whitespace-pre-line">{{ aiSummary }}</p>
        </template>
      </Card>

      <div class="space-y-4">
        <Card v-for="narrative in narratives" :key="narrative.id">
          <template #header>
            <div class="px-4 pt-4 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xl">{{ trendIcon(narrative.trend) }}</span>
                <span class="font-semibold">{{ narrative.module }}</span>
              </div>
              <Tag :value="narrative.severity" :severity="severityTag(narrative.severity)" size="small" />
            </div>
          </template>
          <template #content>
            <p class="font-medium text-surface-800 mb-1">{{ narrative.title }}</p>
            <div class="prose prose-sm max-w-none">
              <p class="text-surface-700 leading-relaxed">{{ narrative.text }}</p>
            </div>
            <div class="mt-3">
              <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full" :class="badgeClass(narrative.trend)">
                {{ narrative.trend === 'up' ? '↑' : narrative.trend === 'down' ? '↓' : '→' }} {{ narrative.value }}
              </span>
            </div>
          </template>
        </Card>
      </div>

      <div v-if="!loading && !narratives.length" class="text-center py-16 text-surface-400">
        <i class="pi pi-align-left text-5xl mb-4 block" />
        <p>Aucun insight disponible pour le moment.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useRoleAccess } from '@/composables/useRoleAccess'

const { isElevated } = useRoleAccess()
const canManage = computed(() => isElevated.value)

const loading = ref(false)
const generating = ref(false)
const narratives = ref([])
const aiSummary = ref('')
const lastLoaded = ref(null)

async function loadInsights() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/bi/insights')
    narratives.value = data?.data ?? []
    lastLoaded.value = new Date()
  } finally {
    loading.value = false
  }
}

async function generate() {
  generating.value = true
  try {
    const { data } = await axios.post('/api/v1/bi/ai/narrative', { data: narratives.value })
    aiSummary.value = data?.data?.narrative ?? ''
  } finally {
    generating.value = false
  }
}

onMounted(loadInsights)

const positiveCount = computed(() => narratives.value.filter(n => n.severity === 'success').length)
const attentionCount = computed(() => narratives.value.filter(n => n.severity === 'warning' || n.severity === 'danger').length)
const lastLoadedLabel = computed(() =>
  lastLoaded.value ? lastLoaded.value.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—'
)

function trendIcon(trend) {
  return { up: '📈', down: '📉' }[trend] ?? '➡️'
}

function severityTag(severity) {
  return { success: 'success', info: 'info', warning: 'warn', danger: 'danger' }[severity] ?? 'secondary'
}

function badgeClass(trend) {
  if (trend === 'up') return 'bg-green-50 text-green-700'
  if (trend === 'down') return 'bg-red-50 text-red-700'
  return 'bg-surface-100 text-surface-600'
}
</script>
