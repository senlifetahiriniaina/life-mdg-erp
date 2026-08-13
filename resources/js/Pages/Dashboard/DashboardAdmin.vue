<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <Card class="bg-primary-50 dark:bg-primary-900/20">
        <template #title>Utilisateurs</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.users_count }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Actifs aujourd'hui: {{ metrics.active_users_today }}</p>
      </Card>

      <Card class="bg-green-50 dark:bg-green-900/20">
        <template #title>Modules Activés</template>
        <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ metrics.modules_enabled }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Sur 16 disponibles</p>
      </Card>

      <Card class="bg-violet-50 dark:bg-violet-900/20">
        <template #title>Revenu Total</template>
        <div class="text-3xl font-bold text-violet-700 dark:text-violet-300">{{ formatCurrency(metrics.total_revenue) }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Toutes factures payées</p>
      </Card>

      <Card class="bg-amber-50 dark:bg-amber-900/20">
        <template #title>Tickets Ouverts</template>
        <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ metrics.open_tickets }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">À résoudre</p>
      </Card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- System Health -->
      <Card>
        <template #title>Santé du Système</template>
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-surface-900 dark:text-surface-50">Latence DB</span>
            <div class="flex items-center gap-2">
              <i class="pi pi-circle-fill text-green-500 text-xs"></i>
              <span class="text-surface-700 dark:text-surface-300">{{ metrics.db_latency_ms }}ms</span>
            </div>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-surface-900 dark:text-surface-50">Uptime</span>
            <div class="flex items-center gap-2">
              <i class="pi pi-circle-fill text-green-500 text-xs"></i>
              <span class="text-surface-700 dark:text-surface-300">99.9%</span>
            </div>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-surface-900 dark:text-surface-50">Cache Redis</span>
            <div class="flex items-center gap-2">
              <i class="pi pi-circle-fill text-green-500 text-xs"></i>
              <span class="text-surface-700 dark:text-surface-300">Connecté</span>
            </div>
          </div>
        </div>
      </Card>

      <!-- MRR & Metrics -->
      <Card>
        <template #title>Revenus Récurrents</template>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-surface-700 dark:text-surface-300">MRR Ce Mois</span>
            <strong class="text-green-700 dark:text-green-300">{{ formatCurrency(metrics.mrr) }}</strong>
          </div>
          <div class="flex justify-between">
            <span class="text-surface-700 dark:text-surface-300">Croissance MoM</span>
            <strong class="text-green-700 dark:text-green-300">+8.2%</strong>
          </div>
          <div class="flex justify-between">
            <span class="text-surface-700 dark:text-surface-300">Churn Rate</span>
            <strong class="text-amber-700 dark:text-amber-300">2.1%</strong>
          </div>
        </div>
      </Card>
    </div>

    <!-- System Alerts -->
    <Card>
      <template #title>Alertes et Anomalies (IA)</template>
      <div class="space-y-3">
        <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border-l-4 border-yellow-400 dark:border-yellow-600">
          <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">⚠️ Haute Charge CPU</p>
          <p class="text-xs text-surface-600 dark:text-surface-400 mt-1">Serveur principal à 87% depuis 20 minutes</p>
          <Button label="Voir les Détails" icon="pi pi-arrow-right" class="mt-2 p-button-xs" />
        </div>
        <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded border-l-4 border-orange-400 dark:border-orange-600">
          <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">🔍 Anomalie Détectée</p>
          <p class="text-xs text-surface-600 dark:text-surface-400 mt-1">1500 échecs d'authentification en 1 heure (IP 192.168.x.x)</p>
          <Button label="Analyser" icon="pi pi-arrow-right" class="mt-2 p-button-xs" />
        </div>
      </div>
    </Card>

    <!-- Module Management -->
    <Card>
      <template #title>Gestion des Modules</template>
      <Button label="Configurer les Modules" icon="pi pi-cog" class="w-full" />
    </Card>
  </div>
</template>

<script setup>
import Card from 'primevue/card'
import Button from 'primevue/button'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(value || 0)
}
</script>
