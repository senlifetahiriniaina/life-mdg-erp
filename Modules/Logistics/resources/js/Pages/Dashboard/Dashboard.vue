<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Tableau de bord Logistique</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">Vue d'ensemble des opérations logistiques — {{ today }}</p>
      </div>
      <div class="flex gap-2">
        <Button label="Actualiser" icon="pi pi-refresh" severity="secondary" outlined @click="refresh" />
        <Button v-if="canCreate" label="Nouvelle expédition" icon="pi pi-plus" @click="showNewShipment = true" />
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card v-for="kpi in kpis" :key="kpi.label">
        <template #content>
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ kpi.label }}</p>
              <p class="mt-1 text-2xl font-bold" :class="kpi.color">{{ kpi.value }}</p>
              <p class="mt-1 text-xs" :class="kpi.trend >= 0 ? 'text-green-600' : 'text-red-600'">
                <i :class="kpi.trend >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down'" class="mr-1 text-xs"></i>
                {{ Math.abs(kpi.trend) }}% vs mois dernier
              </p>
            </div>
            <span class="flex h-12 w-12 items-center justify-center rounded-xl" :class="kpi.bg">
              <i :class="kpi.icon" class="text-xl" :style="{ color: kpi.iconColor }"></i>
            </span>
          </div>
        </template>
      </Card>
    </div>

    <!-- Carte GPS + Alertes -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Carte GPS placeholder -->
      <Card class="lg:col-span-2">
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-map text-blue-600"></i>
            <span>Suivi GPS en temps réel</span>
          </div>
        </template>
        <template #content>
          <div class="relative flex h-72 items-center justify-center rounded-xl bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-blue-950 dark:to-indigo-900 border border-blue-200 dark:border-blue-800">
            <div class="text-center">
              <i class="pi pi-map-marker text-5xl text-blue-400 mb-3 block"></i>
              <p class="text-lg font-semibold text-blue-700 dark:text-blue-300">Carte GPS en temps réel</p>
              <p class="text-sm text-blue-500 dark:text-blue-400 mt-1">Afrique de l'Ouest — Dakar · Abidjan · Douala · Lomé</p>
            </div>
            <!-- Légende -->
            <div class="absolute bottom-3 left-3 rounded-lg bg-white/90 dark:bg-gray-800/90 p-3 shadow text-xs space-y-1">
              <div v-for="leg in mapLegend" :key="leg.label" class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full" :style="{ background: leg.color }"></span>
                <span class="text-gray-700 dark:text-gray-300">{{ leg.label }}</span>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Alertes -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-exclamation-triangle text-orange-500"></i>
            <span>Alertes actives</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-3 max-h-72 overflow-y-auto">
            <div
              v-for="alert in alerts"
              :key="alert.id"
              class="rounded-lg border p-3 text-sm"
              :class="alert.severity === 'critical'
                ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                : alert.severity === 'warning'
                  ? 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950'
                  : 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950'"
            >
              <div class="flex items-start gap-2">
                <i
                  :class="alert.severity === 'critical' ? 'pi pi-times-circle text-red-500'
                    : alert.severity === 'warning' ? 'pi pi-exclamation-circle text-orange-500'
                    : 'pi pi-info-circle text-blue-500'"
                  class="mt-0.5 shrink-0"
                ></i>
                <div>
                  <p class="font-medium text-gray-800 dark:text-gray-200">{{ alert.title }}</p>
                  <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">{{ alert.detail }}</p>
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Tournées du jour -->
    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="pi pi-truck text-green-600"></i>
            <span>Tournées de livraison — Aujourd'hui</span>
          </div>
          <Button label="Voir toutes les tournées" icon="pi pi-arrow-right" iconPos="right" severity="secondary" outlined size="small" />
        </div>
      </template>
      <template #content>
        <div class="space-y-4">
          <div v-for="round in deliveryRounds" :key="round.id" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
              <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 font-bold text-sm">
                  {{ round.id }}
                </span>
                <div>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ round.driver }}</p>
                  <p class="text-xs text-gray-500">{{ round.vehicle }} · {{ round.stops }} arrêts · {{ round.km }} km</p>
                </div>
              </div>
              <Tag :value="round.statusLabel" :severity="round.statusSeverity" />
            </div>
            <div>
              <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Avancement</span>
                <span>{{ round.done }}/{{ round.stops }} livraisons</span>
              </div>
              <ProgressBar :value="Math.round((round.done / round.stops) * 100)" class="h-2" />
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Résumé statuts expéditions -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-chart-bar text-purple-600"></i>
            <span>Répartition des expéditions</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div v-for="st in shipmentStatuses" :key="st.label" class="flex items-center gap-3">
              <Tag :value="st.label" :severity="st.severity" class="min-w-[130px] justify-center" />
              <ProgressBar :value="Math.round((st.count / totalShipments) * 100)" class="flex-1 h-3" />
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 w-8 text-right">{{ st.count }}</span>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-dollar text-yellow-600"></i>
            <span>Coût transport — Ce mois</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <div class="rounded-xl bg-yellow-50 dark:bg-yellow-950 p-4 text-center">
              <p class="text-sm text-yellow-600 dark:text-yellow-400">Total transport ce mois</p>
              <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">14 820 000 XOF</p>
            </div>
            <div class="space-y-2">
              <div v-for="cost in costBreakdown" :key="cost.type" class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                  <i :class="cost.icon" class="text-gray-400"></i>
                  <span class="text-gray-700 dark:text-gray-300">{{ cost.type }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <ProgressBar :value="cost.pct" class="w-24 h-2" />
                  <span class="font-semibold text-gray-800 dark:text-gray-200 w-28 text-right">{{ cost.amount }}</span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['logistics-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const page = usePage()
const user = computed(() => page.props.auth?.user)

const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const showNewShipment = ref(false)

function refresh() {
  window.location.reload()
}

const kpis = ref([
  { label: 'Expéditions en cours', value: '147', trend: 12, color: 'text-blue-600', bg: 'bg-blue-100 dark:bg-blue-900', icon: 'pi pi-send', iconColor: '#2563eb' },
  { label: 'Livraisons du jour', value: '38', trend: 5, color: 'text-green-600', bg: 'bg-green-100 dark:bg-green-900', icon: 'pi pi-check-circle', iconColor: '#16a34a' },
  { label: 'Retards actifs', value: '9', trend: -3, color: 'text-red-600', bg: 'bg-red-100 dark:bg-red-900', icon: 'pi pi-clock', iconColor: '#dc2626' },
  { label: 'Coût transport (mois)', value: '14,82 M XOF', trend: -8, color: 'text-yellow-600', bg: 'bg-yellow-100 dark:bg-yellow-900', icon: 'pi pi-wallet', iconColor: '#ca8a04' },
])

const mapLegend = ref([
  { label: 'En transit', color: '#2563eb' },
  { label: 'Livraison en cours', color: '#16a34a' },
  { label: 'Retard signalé', color: '#dc2626' },
  { label: 'En douane', color: '#d97706' },
  { label: 'Dépôt / Entrepôt', color: '#7c3aed' },
])

const alerts = ref([
  { id: 1, severity: 'critical', title: 'Expédition EXP-2405 bloquée en douane', detail: 'Abidjan Port — Documents manquants: certificat origine' },
  { id: 2, severity: 'critical', title: '3 livraisons en retard > 48h', detail: 'EXP-2398, EXP-2401, EXP-2409 — Dakar zone nord' },
  { id: 3, severity: 'warning', title: 'BL manquant — EXP-2411', detail: 'Transporteur Sahel Express — Envoi Douala → Lagos' },
  { id: 4, severity: 'warning', title: 'Véhicule SN-4821-DK — maintenance requise', detail: 'Kilométrage dépassé, prochain entretien J+2' },
  { id: 5, severity: 'info', title: 'Nouveau transporteur approuvé', detail: 'DHL West Africa — intégré au réseau Dakar' },
])

const deliveryRounds = ref([
  { id: 'T01', driver: 'Moussa Diallo', vehicle: 'Ford Transit SN-4812-DK', stops: 12, km: 87, done: 9, statusLabel: 'En cours', statusSeverity: 'info' },
  { id: 'T02', driver: 'Fatou Konaté', vehicle: 'Toyota Hiace SN-2234-DK', stops: 8, km: 62, done: 8, statusLabel: 'Terminée', statusSeverity: 'success' },
  { id: 'T03', driver: 'Ibrahima Sow', vehicle: 'Mercedes Sprinter SN-6701-DK', stops: 15, km: 110, done: 5, statusLabel: 'En cours', statusSeverity: 'info' },
  { id: 'T04', driver: 'Aminata Traoré', vehicle: 'Renault Master CI-9983-AB', stops: 10, km: 74, done: 0, statusLabel: 'Planifiée', statusSeverity: 'secondary' },
])

const shipmentStatuses = ref([
  { label: 'En préparation', count: 24, severity: 'secondary' },
  { label: 'Collecté', count: 18, severity: 'info' },
  { label: 'En transit', count: 67, severity: 'info' },
  { label: 'En douane', count: 11, severity: 'warn' },
  { label: 'Livré', count: 205, severity: 'success' },
  { label: 'Retourné', count: 7, severity: 'danger' },
])

const totalShipments = computed(() => shipmentStatuses.value.reduce((s, x) => s + x.count, 0))

const costBreakdown = ref([
  { type: 'Routier', icon: 'pi pi-truck', pct: 54, amount: '7 980 000 XOF' },
  { type: 'Maritime', icon: 'pi pi-star', pct: 28, amount: '4 150 000 XOF' },
  { type: 'Aérien', icon: 'pi pi-send', pct: 13, amount: '1 930 000 XOF' },
  { type: 'Express', icon: 'pi pi-bolt', pct: 5, amount: '760 000 XOF' },
])
</script>
