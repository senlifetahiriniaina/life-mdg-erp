<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Surveillance des Risques Supply Chain — IA</h1>
        <p class="text-surface-500 text-sm mt-1">Détection précoce des disruptions fournisseurs, transporteurs et douanes</p>
      </div>
      <div class="flex items-center gap-2">
        <Tag :value="'Dernier scan: il y a 4 min'" severity="success" />
        <Button icon="pi pi-refresh" text @click="refresh" :loading="refreshing" />
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Alertes actives</div></template>
        <template #content>
          <div class="space-y-3">
            <div v-for="alert in alerts" :key="alert.id" class="p-3 rounded-lg border-l-4 cursor-pointer" :class="alert.borderClass" @click="selectedAlert = alert; showAlertDrawer = true" role="button" tabindex="0" @keydown.enter.prevent="selectedAlert = alert; showAlertDrawer = true">
              <div class="flex items-center justify-between mb-1">
                <Tag :value="alert.severity" :severity="{ Critique: 'danger', Élevé: 'warn', Moyen: 'info' }[alert.severity]" size="small" />
                <span class="text-xs text-surface-400">{{ alert.ts }}</span>
              </div>
              <div class="font-medium text-sm">{{ alert.title }}</div>
              <div class="text-xs text-surface-500 mt-0.5">{{ alert.detail }}</div>
              <div class="text-xs text-blue-600 mt-1">Impact estimé: {{ alert.impact }}</div>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Score de risque fournisseurs</div></template>
        <template #content>
          <div class="space-y-3">
            <div v-for="sup in suppliers" :key="sup.name" class="flex items-center gap-3">
              <div class="flex-1">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-sm font-medium">{{ sup.name }}</span>
                  <span class="text-sm font-bold" :class="sup.score >= 80 ? 'text-green-600' : sup.score >= 60 ? 'text-orange-500' : 'text-red-600'">{{ sup.score }}/100</span>
                </div>
                <ProgressBar :value="sup.score" :style="{ height: '8px' }" :class="sup.score >= 80 ? '[&_.p-progressbar-value]:!bg-green-500' : sup.score >= 60 ? '[&_.p-progressbar-value]:!bg-orange-500' : '[&_.p-progressbar-value]:!bg-red-500'" />
                <div class="text-xs text-surface-400 mt-0.5">{{ sup.reason }}</div>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Sources de risques surveillées</div></template>
      <template #content>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <div v-for="source in riskSources" :key="source.name" class="p-3 rounded-lg border text-center">
            <div class="text-2xl mb-1">{{ source.icon }}</div>
            <div class="text-xs font-medium">{{ source.name }}</div>
            <Tag :value="source.status" :severity="{ OK: 'success', Alerte: 'warn', Critique: 'danger' }[source.status]" size="small" class="mt-1" />
          </div>
        </div>
      </template>
    </Card>

    <Drawer v-model:visible="showAlertDrawer" :header="selectedAlert?.title" position="right" :style="{ width: '500px' }">
      <div v-if="selectedAlert" class="space-y-4 text-sm">
        <div class="flex items-center gap-2">
          <Tag :value="selectedAlert.severity" :severity="{ Critique: 'danger', Élevé: 'warn', Moyen: 'info' }[selectedAlert.severity]" />
          <span class="text-surface-500 text-xs">{{ selectedAlert.ts }}</span>
        </div>
        <div class="p-3 bg-surface-50 rounded">{{ selectedAlert.detail }}</div>
        <div class="p-3 bg-orange-50 border border-orange-200 rounded">
          <strong>Impact estimé:</strong> {{ selectedAlert.impact }}
        </div>
        <div>
          <strong>Recommandations IA:</strong>
          <ul class="mt-2 space-y-1 list-disc list-inside text-surface-600">
            <li v-for="rec in selectedAlert.recommendations || []" :key="rec">{{ rec }}</li>
          </ul>
        </div>
        <div class="flex gap-2">
          <Button label="Créer un plan d'action" icon="pi pi-check" size="small" />
          <Button label="Marquer comme traité" icon="pi pi-check-circle" size="small" outlined @click="showAlertDrawer = false" />
        </div>
      </div>
    </Drawer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Drawer from 'primevue/drawer'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['logistics-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const refreshing = ref(false)
const showAlertDrawer = ref(false)
const selectedAlert = ref(null)
const refresh = async () => { refreshing.value = true; await new Promise(r => setTimeout(r, 1500)); refreshing.value = false }

const stats = [
  { label: 'Risques détectés', value: '7', color: 'text-red-600' },
  { label: 'Fournisseurs surveillés', value: '42', color: 'text-blue-600' },
  { label: 'Routes à risque', value: '3', color: 'text-orange-600' },
  { label: 'Score moyen supply chain', value: '72/100', color: 'text-green-600' },
]

const alerts = ref([
  { id: 1, severity: 'Critique', title: 'Grève portuaire — Port de Dakar', detail: 'Perturbation prévue 72h. 12 expéditions impactées.', impact: '3 200 000 XOF · délai +5j', ts: 'il y a 20 min', borderClass: 'border-red-500 bg-red-50', recommendations: ['Basculer vers Port de Lomé pour les expéditions urgentes', 'Prévenir clients concernés', 'Activer stock tampon entrepôt Thiès'] },
  { id: 2, severity: 'Élevé', title: 'Fournisseur Shenzhen Electronics — retard production', detail: 'Délai usine annoncé +21j sur commande BPO-2026-089', impact: '5 800 000 XOF · rupture stock partielle', ts: 'il y a 2h', borderClass: 'border-orange-400 bg-orange-50', recommendations: ['Sourcing alternatif: TechParts Lagos', 'Ajuster prévision MRP', 'Notifier équipe commerciale'] },
  { id: 3, severity: 'Moyen', title: 'Hausse tarifs fret aérien +18%', detail: 'Air France Cargo — routes Paris-Dakar. Effet dès 1er juin.', impact: '840 000 XOF/mois supplémentaires', ts: 'il y a 5h', borderClass: 'border-blue-400 bg-blue-50', recommendations: ['Renégocier contrat fret', 'Basculer sur fret maritime pour commandes non urgentes'] },
  { id: 4, severity: 'Moyen', title: 'Instabilité monétaire — NGN/XOF', detail: 'Taux NGN en baisse de 8% cette semaine. Impact achats Nigeria.', impact: '320 000 XOF de risque change', ts: 'il y a 8h', borderClass: 'border-yellow-400 bg-yellow-50', recommendations: ['Hedging via Orange Money Business', 'Négocier factures en XOF'] },
])

const suppliers = [
  { name: 'Shenzhen Electronics', score: 48, reason: 'Retard récurrent · dépendance élevée' },
  { name: 'Lagos TechParts', score: 72, reason: 'Fiable mais délai long' },
  { name: 'Casablanca Distrib.', score: 88, reason: 'Excellent historique' },
  { name: 'Port de Dakar Transitaire', score: 55, reason: 'Grève en cours' },
  { name: 'Air France Cargo', score: 79, reason: 'Hausse tarifs récente' },
]

const riskSources = [
  { name: 'Fournisseurs', icon: '🏭', status: 'Alerte' },
  { name: 'Transport', icon: '🚢', status: 'Critique' },
  { name: 'Douanes', icon: '🛃', status: 'OK' },
  { name: 'Météo', icon: '🌧️', status: 'OK' },
  { name: 'Devises', icon: '💱', status: 'Alerte' },
  { name: 'Géopolitique', icon: '🌍', status: 'OK' },
]
</script>
