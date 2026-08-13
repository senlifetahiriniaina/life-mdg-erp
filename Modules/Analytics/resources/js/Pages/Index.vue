<script setup>
import { ref, onMounted } from 'vue'
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
      fetch('/api/v1/analytics/forecast-models?is_active=true&per_page=12').then(r => r.json()).catch(() => ({ data: [] })),
      fetch('/api/v1/analytics/anomalies?status=open&limit=5').then(r => r.json()).catch(() => ({ data: [] })),
      fetch('/api/v1/analytics/alerts?status=active&limit=5').then(r => r.json()).catch(() => ({ data: [] })),
    ])
    forecasts.value = f.data ?? []
    anomalies.value = an.data ?? []
    alerts.value = al.data ?? []
  } finally { loading.value = false }
})

const alertColor = (t) => ({ stockout_risk:'red', cashflow_deficit:'orange', demand_surge:'yellow', production_bottleneck:'purple', hr_shortage:'blue' }[t] ?? 'gray')
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Analytics & Prévisions IA</h1>
    <AIAssistantPanel v-if="guidance" :guidance="guidance" />
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
            <p class="text-sm font-medium">{{ a.entity_type ?? a.module }}</p>
            <p class="text-xs text-gray-500">Déviation : {{ Number(a.deviation_score).toFixed(2) }}σ</p>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">{{ a.severity }}</span>
        </li>
        <li v-if="!anomalies.length" class="py-4 text-center text-gray-400 text-sm">Aucune anomalie détectée</li>
      </ul>
    </div>
  </div>
</template>
