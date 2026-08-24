<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Analytics', 'view_dashboard')
const forecasts = ref([])
const anomalies = ref([])
const alerts = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [f, an, al] = await Promise.all([
      axios.get('/api/v1/forecasting/models', { params: { active: true, per_page: 12 } }).then(r => r.data).catch(() => ({ data: [] })),
      axios.get('/api/v1/ai/anomalies').then(r => r.data).catch(() => ({ data: [] })),
      axios.get('/api/v1/forecasting/alerts').then(r => r.data).catch(() => ({ data: [] })),
    ])
    forecasts.value = f.data ?? []
    anomalies.value = an.data ?? []
    alerts.value = al.data ?? []
  } finally { loading.value = false }
  loadPredictions()
  loadMlModels()
})

const alertColor = (t) => ({ stockout_risk:'red', cashflow_deficit:'orange', demand_surge:'yellow', production_bottleneck:'purple', hr_shortage:'blue' }[t] ?? 'gray')

const tabs = [
  { key: 'overview', label: "Vue d'ensemble" },
  { key: 'predictions', label: 'Prédictions' },
  { key: 'recommendations', label: 'Recommandations' },
  { key: 'ml-models', label: 'Modèles ML' },
]
const activeTab = ref('overview')

// ---- Predictions ----
const predictions = ref([])
const predictionsLoading = ref(false)
const predictionsError = ref(null)
async function loadPredictions() {
  predictionsLoading.value = true
  predictionsError.value = null
  try {
    const { data } = await axios.get('/api/v1/analytics/predictions')
    predictions.value = data.data ?? []
  } catch (e) {
    predictionsError.value = e.response?.status === 403
      ? "Vous n'avez pas accès aux modèles de prédiction."
      : 'Impossible de charger les modèles de prédiction.'
  } finally {
    predictionsLoading.value = false
  }
}

// ---- Recommendations (per business entity: recipient_type + recipient_id) ----
const recipientType = ref('Customer')
const recipientId = ref('')
const recommendations = ref([])
const recommendationsLoading = ref(false)
const recommendationsError = ref(null)
const recommendationsSearched = ref(false)
async function loadRecommendations() {
  if (!recipientId.value) return
  recommendationsLoading.value = true
  recommendationsError.value = null
  recommendationsSearched.value = true
  try {
    const { data } = await axios.get('/api/v1/analytics/recommendations/for-user', {
      params: { user_type: recipientType.value, user_id: recipientId.value },
    })
    recommendations.value = data.data ?? []
  } catch (e) {
    recommendationsError.value = 'Impossible de charger les recommandations.'
  } finally {
    recommendationsLoading.value = false
  }
}

// ---- ML Models ----
const mlModels = ref([])
const mlModelsLoading = ref(false)
const mlModelsError = ref(null)
async function loadMlModels() {
  mlModelsLoading.value = true
  mlModelsError.value = null
  try {
    const { data } = await axios.get('/api/v1/analytics/ml-models')
    mlModels.value = data.data ?? []
  } catch (e) {
    mlModelsError.value = e.response?.status === 403
      ? "Vous n'avez pas accès aux modèles ML."
      : 'Impossible de charger les modèles ML.'
  } finally {
    mlModelsLoading.value = false
  }
}
</script>

<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-900">Analytics & Prévisions IA</h1>
        <!-- Chantier 32.25 (audit 14 couches, Analytics — couche 3, découvrabilité) :
             la page Prévisions de trésorerie (Chantier 26A) était réelle et
             fonctionnelle mais atteignable uniquement par URL directe depuis sa
             création — aucun lien depuis le hub Analytics, la seule page du
             module déjà dans la navigation principale de l'app. -->
        <Link
          href="/analytics/cashflow-forecast"
          class="text-sm px-3 py-1.5 rounded border border-indigo-200 text-indigo-700 hover:bg-indigo-50"
        >
          Prévisions de trésorerie →
        </Link>
      </div>
      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              'py-2 px-1 border-b-2 text-sm font-medium',
              activeTab === tab.key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            ]"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <div v-if="activeTab === 'overview'" class="space-y-6">
        <div v-if="alerts.length" class="bg-amber-50 border border-amber-200 rounded-lg p-4">
          <h2 class="text-sm font-semibold text-amber-800 mb-2">{{ alerts.length }} alerte(s) active(s)</h2>
          <ul class="space-y-1">
            <li v-for="alert in alerts" :key="alert.id" class="flex items-center gap-2 text-sm">
              <span :class="`w-2 h-2 rounded-full bg-${alertColor(alert.alert_type)}-500`" />
              <span class="font-medium">{{ alert.alert_type?.replace(/_/g,' ') }}</span>
              <span class="text-gray-500">— {{ alert.message?.slice(0,80) }}</span>
            </li>
          </ul>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-3">Modèles de prévision actifs</h2>
          <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div v-for="f in forecasts" :key="f.id" class="border rounded p-3 space-y-1">
              <p class="font-medium text-sm">{{ f.name }}</p>
              <p class="text-xs text-gray-500">{{ f.module }} · {{ f.algorithm }}</p>
              <div class="flex justify-between items-center">
                <span class="text-xs text-gray-400">Horizon : {{ f.horizon_days }}j</span>
                <span :class="`text-xs px-1.5 py-0.5 rounded ${f.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`">{{ f.is_active ? 'Actif' : 'Inactif' }}</span>
              </div>
            </div>
            <div v-if="!forecasts.length" class="col-span-3 text-center text-gray-400 py-6 text-sm">Aucun modèle configuré</div>
          </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-3">Anomalies détectées</h2>
          <ul class="divide-y">
            <li v-for="a in anomalies" :key="a.id" class="py-2 flex justify-between">
              <div>
                <p class="text-sm font-medium">{{ a.title ?? a.entity_type ?? a.module }}</p>
                <p class="text-xs text-gray-500">{{ a.description }}</p>
              </div>
              <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 h-fit">{{ a.severity }}</span>
            </li>
            <li v-if="!anomalies.length" class="py-4 text-center text-gray-400 text-sm">Aucune anomalie détectée</li>
          </ul>
        </div>
      </div>

      <div v-else-if="activeTab === 'predictions'" class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-3">Modèles de prédiction</h2>
        <p v-if="predictionsLoading" class="text-gray-400 text-sm">Chargement…</p>
        <p v-else-if="predictionsError" class="text-red-600 text-sm">{{ predictionsError }}</p>
        <ul v-else class="divide-y">
          <li v-for="p in predictions" :key="p.id" class="py-2 flex justify-between items-center">
            <div>
              <p class="text-sm font-medium">{{ p.model_name }}</p>
              <p class="text-xs text-gray-500">{{ p.model_type }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ p.status }}</span>
          </li>
          <li v-if="!predictions.length" class="py-4 text-center text-gray-400 text-sm">Aucun modèle de prédiction</li>
        </ul>
      </div>

      <div v-else-if="activeTab === 'recommendations'" class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-3">Recommandations IA par entité</h2>
        <form class="flex flex-wrap gap-3 items-end mb-4" @submit.prevent="loadRecommendations">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Type</label>
            <input v-model="recipientType" type="text" class="border rounded px-2 py-1 text-sm" placeholder="Customer" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">ID</label>
            <input v-model="recipientId" type="number" class="border rounded px-2 py-1 text-sm w-28" placeholder="ex: 12" />
          </div>
          <button type="submit" class="bg-indigo-600 text-white text-sm px-3 py-1.5 rounded hover:bg-indigo-700">Charger</button>
        </form>
        <p v-if="recommendationsLoading" class="text-gray-400 text-sm">Chargement…</p>
        <p v-else-if="recommendationsError" class="text-red-600 text-sm">{{ recommendationsError }}</p>
        <ul v-else-if="recommendationsSearched" class="divide-y">
          <li v-for="r in recommendations" :key="r.id" class="py-2">
            <p class="text-sm font-medium">{{ r.title ?? r.recommendation_type }}</p>
            <p class="text-xs text-gray-500">{{ r.description ?? r.reason }}</p>
          </li>
          <li v-if="!recommendations.length" class="py-4 text-center text-gray-400 text-sm">Aucune recommandation en attente pour cette entité</li>
        </ul>
        <p v-else class="text-gray-400 text-sm">Renseignez un type et un identifiant pour voir ses recommandations.</p>
      </div>

      <div v-else-if="activeTab === 'ml-models'" class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-3">Modèles Machine Learning</h2>
        <p v-if="mlModelsLoading" class="text-gray-400 text-sm">Chargement…</p>
        <p v-else-if="mlModelsError" class="text-red-600 text-sm">{{ mlModelsError }}</p>
        <ul v-else class="divide-y">
          <li v-for="m in mlModels" :key="m.id" class="py-2 flex justify-between items-center">
            <div>
              <p class="text-sm font-medium">{{ m.model_name }}</p>
              <p class="text-xs text-gray-500">{{ m.model_category }} · {{ m.framework }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ m.status }}</span>
          </li>
          <li v-if="!mlModels.length" class="py-4 text-center text-gray-400 text-sm">Aucun modèle ML enregistré</li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
