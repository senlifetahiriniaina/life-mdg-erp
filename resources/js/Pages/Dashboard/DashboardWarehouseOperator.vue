<template>
  <div class="space-y-6">
    <!-- Daily Tasks Summary -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Pending Receptions -->
      <Card class="bg-white dark:bg-surface-800 dark:bg-surface-800">
        <template #title>À Réceptionner</template>
        <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ metrics.pending_receptions }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Articles en attente</p>
        <Button label="Commencer" icon="pi pi-arrow-right" class="mt-4 w-full p-button-sm p-button-warning" />
      </Card>

      <!-- Shipments Ready -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>À Expédier</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.ready_shipments }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Commandes prêtes</p>
        <Button label="Préparer" icon="pi pi-arrow-right" class="mt-4 w-full p-button-sm p-button-info" />
      </Card>

      <!-- Inventory Issues -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Problèmes Stock</template>
        <div class="text-3xl font-bold" :class="metrics.inventory_issues > 0 ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300'">
          {{ metrics.inventory_issues }}
        </div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Écarts détectés</p>
      </Card>

      <!-- Efficiency -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Efficacité</template>
        <ProgressBar :value="metrics.efficiency_rate" :show-value="true" class="mb-2" />
        <p class="text-sm text-surface-600 dark:text-surface-400">{{ metrics.efficiency_rate }}% objectif atteint</p>
      </Card>
    </div>

    <!-- Tasks by Type -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Reception Tasks -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>📦 Réceptions du Jour</template>
        <div class="space-y-3">
          <div v-for="task in receptionTasks" :key="task.id" class="flex items-center justify-between border-b pb-2 hover:bg-gray-50 dark:bg-surface-800 p-2 rounded">
            <div>
              <p class="font-semibold text-surface-900 dark:text-surface-50">{{ task.reference }}</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ task.quantity }} unités | {{ task.supplier }}</p>
            </div>
            <Button icon="pi pi-check" class="p-button-rounded p-button-sm p-button-success p-button-text" />
          </div>
        </div>
      </Card>

      <!-- Shipment Tasks -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>🚚 Expéditions du Jour</template>
        <div class="space-y-3">
          <div v-for="task in shipmentTasks" :key="task.id" class="flex items-center justify-between border-b pb-2 hover:bg-gray-50 dark:bg-surface-800 p-2 rounded">
            <div>
              <p class="font-semibold text-surface-900 dark:text-surface-50">Cde #{{ task.order_id }}</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ task.quantity }} articles | {{ task.destination }}</p>
            </div>
            <Button icon="pi pi-truck" class="p-button-rounded p-button-sm p-button-info p-button-text" />
          </div>
        </div>
      </Card>
    </div>

    <!-- Warehouse Status -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>État de l'Entrepôt</template>
      <div class="grid grid-cols-2 gap-4">
        <div class="border-l-4 border-blue-500 pl-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Taux d'Occupation</p>
          <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ metrics.occupancy_rate }}%</p>
        </div>
        <div class="border-l-4 border-green-500 pl-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Articles Rangés</p>
          <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ metrics.items_stored }}</p>
        </div>
        <div class="border-l-4 border-orange-500 pl-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Articles En Picking</p>
          <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ metrics.items_picking }}</p>
        </div>
        <div class="border-l-4 border-red-500 pl-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Articles Endommagés</p>
          <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ metrics.items_damaged }}</p>
        </div>
      </div>
    </Card>

    <!-- Instructions -->
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-lg p-4">
      <p class="text-sm text-green-900">
        <strong>✓ Instructions:</strong> Scannez les codes-barres, vérifiez les quantités, signez les documents. Tous les documents archivés dans le système.
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
})

const receptionTasks = computed(() => [
  { id: 1, reference: 'BL-2024-001', quantity: 50, supplier: 'Fournisseur A' },
  { id: 2, reference: 'BL-2024-002', quantity: 75, supplier: 'Fournisseur B' },
])

const shipmentTasks = computed(() => [
  { id: 1, order_id: '12345', quantity: 10, destination: 'Paris' },
  { id: 2, order_id: '12346', quantity: 5, destination: 'Lyon' },
])

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(value || 0)
}
</script>
