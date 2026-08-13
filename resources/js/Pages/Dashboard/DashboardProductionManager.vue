<template>
  <div class="space-y-6">
    <!-- Production Status Overview -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Orders in Production -->
      <Card class="bg-white dark:bg-surface-800 dark:bg-surface-800">
        <template #title>En Production</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.orders_in_production }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Commandes actives</p>
        <Button label="Voir Détail" icon="pi pi-arrow-right" class="mt-4 w-full p-button-sm" />
      </Card>

      <!-- On-Time Delivery Rate -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Taux de Ponctualité</template>
        <ProgressBar :value="metrics.on_time_delivery" :show-value="true" class="mb-2" />
        <p class="text-sm text-surface-600 dark:text-surface-400">{{ metrics.on_time_delivery }}% respectent les délais</p>
      </Card>

      <!-- Quality Score -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Qualité</template>
        <div class="text-3xl font-bold" :class="metrics.quality_score >= 95 ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300'">
          {{ metrics.quality_score }}%
        </div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Taux d'acceptation</p>
      </Card>

      <!-- Equipment Alerts -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Alertes Équipement</template>
        <div class="text-3xl font-bold" :class="metrics.equipment_alerts === 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
          {{ metrics.equipment_alerts }}
        </div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Maintenance requise</p>
      </Card>
    </div>

    <!-- Production Schedule & Alerts -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Current Production Orders -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>📋 Ordres en Cours</template>
        <div class="space-y-3">
          <div v-for="order in productionOrders" :key="order.id" class="border-l-4 pl-3 py-2 rounded" :class="getOrderStatusClass(order.status)">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p class="font-semibold text-surface-900 dark:text-surface-50">Cde #{{ order.id }}</p>
                <p class="text-xs text-surface-600 dark:text-surface-400">{{ order.product }} | {{ order.progress }}% complété</p>
                <ProgressBar :value="order.progress" class="mt-1" style="height: 6px;" />
              </div>
              <div class="text-right ml-4">
                <p class="text-xs font-bold" :class="isOverdue(order.due_date) ? 'text-red-700 dark:text-red-300' : 'text-surface-600 dark:text-surface-400'">
                  {{ order.due_date }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Maintenance Alerts -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>🔧 Maintenance Préventive</template>
        <div class="space-y-3">
          <div v-for="alert in maintenanceAlerts" :key="alert.id" class="border-l-4 border-orange-500 pl-3 py-2 hover:bg-amber-50 dark:bg-amber-900/20 rounded">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-surface-900 dark:text-surface-50">{{ alert.equipment }}</p>
                <p class="text-xs text-surface-600 dark:text-surface-400">{{ alert.reason }}</p>
                <p class="text-xs font-bold text-amber-700 dark:text-amber-300 mt-1">Prévu: {{ alert.scheduled_date }}</p>
              </div>
              <Button icon="pi pi-check" class="p-button-rounded p-button-sm p-button-text p-button-warning" />
            </div>
          </div>
        </div>
      </Card>
    </div>

    <!-- Quality Control -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>✅ Contrôle Qualité Aujourd'hui</template>
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded p-3">
          <p class="text-sm text-surface-600 dark:text-surface-400">Articles Acceptés</p>
          <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ metrics.quality_accepted }}</p>
        </div>
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 rounded p-3">
          <p class="text-sm text-surface-600 dark:text-surface-400">Articles Rejetés</p>
          <p class="text-2xl font-bold text-red-700 dark:text-red-300">{{ metrics.quality_rejected }}</p>
        </div>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 rounded p-3">
          <p class="text-sm text-surface-600 dark:text-surface-400">Rework Requis</p>
          <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ metrics.quality_rework }}</p>
        </div>
        <div class="bg-primary-50 dark:bg-primary-900/20 border border-blue-200 rounded p-3">
          <p class="text-sm text-surface-600 dark:text-surface-400">Taux Défaut</p>
          <p class="text-2xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.defect_rate }}%</p>
        </div>
      </div>
    </Card>

    <!-- Production Forecast -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>📈 Prévisions Prochains Jours</template>
      <div class="space-y-3">
        <div v-for="day in forecastDays" :key="day.date" class="flex items-center justify-between border-b pb-2 last:border-b-0">
          <div>
            <p class="font-semibold text-surface-900 dark:text-surface-50">{{ day.date }}</p>
            <p class="text-xs text-surface-600 dark:text-surface-400">{{ day.orders }} commandes | Charge: {{ day.capacity }}%</p>
          </div>
          <ProgressBar :value="day.capacity" class="w-24" />
        </div>
      </div>
    </Card>

    <!-- Status -->
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-lg p-4">
      <p class="text-sm text-green-900">
        <strong>✓ Production on track.</strong> {{ metrics.on_schedule_count }} commandes en retard sur schedule, {{ metrics.equipment_alerts }} alertes maintenance.
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

const productionOrders = computed(() => [
  { id: 'MFG-001', product: 'Widget A', progress: 65, status: 'in_progress', due_date: '15-05-2026' },
  { id: 'MFG-002', product: 'Widget B', progress: 45, status: 'in_progress', due_date: '18-05-2026' },
  { id: 'MFG-003', product: 'Widget C', progress: 90, status: 'ready', due_date: '12-05-2026' },
])

const maintenanceAlerts = computed(() => [
  { id: 1, equipment: 'Machine CNC-01', reason: '500h de maintenance préventive', scheduled_date: '16-05-2026' },
  { id: 2, equipment: 'Robot Soudure', reason: 'Inspection des joints', scheduled_date: '17-05-2026' },
])

const forecastDays = computed(() => [
  { date: '12-05-2026', orders: 8, capacity: 85 },
  { date: '13-05-2026', orders: 5, capacity: 60 },
  { date: '14-05-2026', orders: 10, capacity: 92 },
])

const getOrderStatusClass = (status) => {
  const classes = {
    in_progress: 'border-blue-500 bg-primary-50 dark:bg-primary-900/20',
    ready: 'border-green-500 bg-green-50 dark:bg-green-900/20',
    delayed: 'border-red-500 bg-red-50 dark:bg-red-900/20',
  }
  return classes[status] || 'border-gray-500 bg-gray-50'
}

const isOverdue = (dueDate) => {
  return false
}
</script>
