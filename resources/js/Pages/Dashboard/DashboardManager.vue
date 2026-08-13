<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <Card class="bg-primary-50 dark:bg-primary-900/20">
        <template #title>Équipe</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.team_size }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Collaborateurs</p>
      </Card>

      <Card class="bg-amber-50 dark:bg-amber-900/20">
        <template #title>Tâches en Retard</template>
        <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ metrics.overdue_tasks }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">À suivre</p>
      </Card>

      <Card class="bg-violet-50 dark:bg-violet-900/20">
        <template #title>Performance Moyenne</template>
        <div class="text-3xl font-bold text-violet-700 dark:text-violet-300">{{ (metrics.team_performance_avg || 0).toFixed(1) }}/5</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Score équipe</p>
      </Card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Team KPIs -->
      <Card>
        <template #title>KPIs Équipe</template>
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-surface-700 dark:text-surface-300">Tâches complétées ce mois</span>
            <strong class="text-green-700 dark:text-green-300">47</strong>
          </div>
          <div class="flex justify-between">
            <span class="text-surface-700 dark:text-surface-300">Demandes de congés en attente</span>
            <strong class="text-amber-700 dark:text-amber-300">{{ metrics.pending_leave_requests }}</strong>
          </div>
          <div class="flex justify-between">
            <span class="text-surface-700 dark:text-surface-300">Budget consommé</span>
            <strong class="text-surface-900 dark:text-surface-50">{{ formatCurrency(metrics.budget_consumed) }}</strong>
          </div>
        </div>
      </Card>

      <!-- Pending Approvals -->
      <Card>
        <template #title>Actions Requises</template>
        <div class="space-y-2">
          <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded">
            <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">{{ metrics.pending_leave_requests }} demandes de congés</p>
            <p class="text-xs text-surface-600 dark:text-surface-400 mt-1">Demandent votre approbation</p>
          </div>
          <Button label="Approuver" icon="pi pi-check" class="w-full p-button-sm" />
        </div>
      </Card>
    </div>

    <!-- Cross-Module Insights -->
    <Card>
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-sparkles text-amber-500 dark:text-amber-400"></i>
          Insights IA cette Semaine
        </div>
      </template>
      <div class="space-y-3">
        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded">
          <p class="text-sm text-surface-900 dark:text-surface-50"><strong>✓ Productivité en hausse</strong> - Votre équipe a complété 12% de tâches supplémentaires cette semaine</p>
        </div>
        <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded">
          <p class="text-sm text-surface-900 dark:text-surface-50"><strong>⚠️ 2 employés</strong> ont un risque élevé de départ (turnover alert)</p>
        </div>
        <div class="p-3 bg-primary-50 dark:bg-primary-900/20 rounded">
          <p class="text-sm text-surface-900 dark:text-surface-50"><strong>📊 Recommandation</strong> - Augmentez les ressources sur le projet CRM (retard de 5 jours)</p>
        </div>
      </div>
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
