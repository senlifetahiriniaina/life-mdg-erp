<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Security', 'view_dashboard')
const stats = ref({ open_incidents: 0, compliance_score: null, auth_failures_24h: 0, critical_threats: 0 })
const incidents = ref([])
const threats = ref([])
const loading = ref(true)

// Chantier 32.3: stats now come from the one server-computed, cached
// (5 min) GET /security/summary endpoint (SecurityAuditService::
// getSecuritySummary()) instead of being recomputed client-side from 2
// extra full-payload list calls (auth-events/summary, compliance/controls
// at per_page=100) — same 3 numbers, plus a real critical_threats count
// that was previously never fetched at all. incidents/threat-indicators
// are still fetched separately since the page also renders their lists.
onMounted(async () => {
  try {
    const [i, t, summary] = await Promise.all([
      axios.get('/api/v1/security/incidents', { params: { status: 'open', per_page: 5 } }).then(r => r.data).catch(() => ({ data: [], total: 0 })),
      axios.get('/api/v1/security/threat-indicators', { params: { per_page: 5 } }).then(r => r.data).catch(() => ({ data: [] })),
      axios.get('/api/v1/security/summary').then(r => r.data).catch(() => ({ data: {} })),
    ])
    incidents.value = i.data ?? []
    threats.value = t.data ?? []

    stats.value = {
      open_incidents: summary.data?.open_incidents ?? (i.total ?? incidents.value.length),
      compliance_score: summary.data?.compliance_score ?? null,
      auth_failures_24h: summary.data?.auth_failures_24h ?? 0,
      critical_threats: summary.data?.critical_threats ?? 0,
    }
  } finally { loading.value = false }
})

const severityColor = (s) => ({ critical: 'red', high: 'orange', medium: 'yellow', low: 'blue' }[s] ?? 'gray')
</script>

<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Sécurité & Conformité</h1>
        <span class="text-sm text-gray-500">Surveillance temps réel</span>
      </div>
      <AIAssistantPanel v-if="guidance" :guidance="guidance" />
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
          <p class="text-sm text-gray-500">Incidents ouverts</p>
          <p class="text-3xl font-bold text-red-600">{{ stats.open_incidents }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
          <p class="text-sm text-gray-500">Score conformité</p>
          <p class="text-3xl font-bold text-green-600">{{ stats.compliance_score !== null ? stats.compliance_score + '%' : '—' }}</p>
          <p v-if="stats.compliance_score === null" class="text-xs text-gray-400">Aucun contrôle de conformité enregistré</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
          <p class="text-sm text-gray-500">Échecs auth (24h)</p>
          <p class="text-3xl font-bold text-yellow-600">{{ stats.auth_failures_24h }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
          <p class="text-sm text-gray-500">Menaces critiques actives</p>
          <p class="text-3xl font-bold text-purple-600">{{ stats.critical_threats }}</p>
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-3">Incidents ouverts</h2>
          <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>
          <ul v-else class="divide-y">
            <li v-for="incident in incidents" :key="incident.id" class="py-2 flex justify-between items-start">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ incident.incident_type }}</p>
                <p class="text-xs text-gray-500">{{ incident.description?.slice(0, 80) }}</p>
              </div>
              <span :class="`text-xs px-2 py-0.5 rounded-full bg-${severityColor(incident.severity)}-100 text-${severityColor(incident.severity)}-700`">{{ incident.severity }}</span>
            </li>
            <li v-if="!incidents.length" class="py-4 text-center text-gray-400 text-sm">Aucun incident ouvert ✓</li>
          </ul>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-3">Indicateurs de menaces</h2>
          <ul class="divide-y">
            <li v-for="threat in threats" :key="threat.id" class="py-2 flex justify-between items-center">
              <div>
                <p class="text-sm font-mono text-gray-800">{{ threat.indicator_value }}</p>
                <p class="text-xs text-gray-500">{{ threat.indicator_type }} · {{ threat.source }}</p>
              </div>
              <span :class="`text-xs px-2 py-0.5 rounded-full bg-${severityColor(threat.threat_level)}-100 text-${severityColor(threat.threat_level)}-700`">{{ threat.threat_level }}</span>
            </li>
            <li v-if="!threats.length" class="py-4 text-center text-gray-400 text-sm">Aucune menace détectée</li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
