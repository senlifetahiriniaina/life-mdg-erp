<template>
  <div class="space-y-6">
    <!-- Session Summary -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Session Status -->
      <Card class="bg-white dark:bg-surface-800 dark:bg-surface-800">
        <template #title>Session</template>
        <div class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ currentSession }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">En cours</p>
        <Button label="Ouvrir Caisse" icon="pi pi-lock-open" class="mt-4 w-full p-button-sm" />
      </Card>

      <!-- Daily Revenue -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Chiffre d'affaires</template>
        <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ formatCurrency(metrics.daily_revenue) }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Aujourd'hui</p>
      </Card>

      <!-- Transactions Count -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Transactions</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.transaction_count }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Ticket moyen: {{ formatCurrency(metrics.average_ticket) }}</p>
      </Card>

      <!-- Discrepancies -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Alertes</template>
        <div class="text-3xl font-bold" :class="metrics.discrepancies > 0 ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300'">
          {{ metrics.discrepancies }}
        </div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Écarts détectés</p>
      </Card>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Payment Methods -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Modes de Paiement</template>
        <div class="space-y-3">
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-surface-900 dark:text-surface-50">Espèces</span>
            <span class="font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(metrics.cash) }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-surface-900 dark:text-surface-50">Cartes bancaires</span>
            <span class="font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(metrics.cards) }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-surface-900 dark:text-surface-50">Chèques</span>
            <span class="font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(metrics.checks) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-surface-900 dark:text-surface-50">Autres</span>
            <span class="font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(metrics.other) }}</span>
          </div>
        </div>
      </Card>

      <!-- Top Products -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Top Produits 🏆</template>
        <div class="space-y-3">
          <div v-for="product in topProducts" :key="product.id" class="flex items-center justify-between border-b pb-2">
            <div>
              <p class="font-semibold text-surface-900 dark:text-surface-50">{{ product.name }}</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ product.quantity }} vendus</p>
            </div>
            <span class="font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(product.revenue) }}</span>
          </div>
        </div>
      </Card>
    </div>

    <!-- Keyboard Shortcuts Hint -->
    <div class="bg-primary-50 dark:bg-primary-900/20 border border-blue-200 rounded-lg p-4">
      <p class="text-sm text-blue-900">
        <strong>💡 Raccourcis clavier:</strong> F1 = Nouveau ticket | F2 = Paiement | F3 = Remise | F4 = Clôturer
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
})

const currentSession = computed(() => 'Session #001 (09:30 - en cours)')

const topProducts = computed(() => [
  { id: 1, name: 'Café Espresso', quantity: 45, revenue: 90 },
  { id: 2, name: 'Croissant', quantity: 38, revenue: 76 },
  { id: 3, name: 'Sandwich', quantity: 25, revenue: 125 },
])

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(value || 0)
}
</script>
