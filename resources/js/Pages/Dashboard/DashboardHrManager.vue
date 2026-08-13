<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <Card class="bg-primary-50 dark:bg-primary-900/20">
        <template #title>Effectifs</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.headcount }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Employés actifs</p>
      </Card>

      <Card class="bg-green-50 dark:bg-green-900/20">
        <template #title>Postes Ouverts</template>
        <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ metrics.open_positions }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">En cours de recrutement</p>
      </Card>

      <Card class="bg-amber-50 dark:bg-amber-900/20">
        <template #title>Congés en Attente</template>
        <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ metrics.pending_leaves }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">À approuver</p>
      </Card>

      <Card class="bg-red-50 dark:bg-red-900/20">
        <template #title>Turnover Annuel</template>
        <div class="text-3xl font-bold text-red-700 dark:text-red-300">{{ metrics.turnover_rate }}%</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Taux de départ</p>
      </Card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Pending Leaves -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Congés en Attente</template>
        <div class="space-y-2">
          <div v-for="item in pendingLeaves" :key="item.id" class="flex justify-between items-center p-2 border-b">
            <div>
              <p class="font-semibold text-surface-900 dark:text-surface-50">{{ item.employee }}</p>
              <p class="text-sm text-surface-600 dark:text-surface-400">{{ item.days }} jours • {{ item.startDate }}</p>
            </div>
            <Button icon="pi pi-check" class="p-button-rounded p-button-sm p-button-success" />
          </div>
        </div>
      </Card>

      <!-- Upcoming Reviews -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Évaluations Prévues</template>
        <div class="space-y-2">
          <div class="flex justify-between items-center">
            <span class="text-surface-900 dark:text-surface-50">Cycles actifs</span>
            <Tag :value="`${metrics.upcoming_reviews} en cours`" severity="info" />
          </div>
          <div class="flex justify-between items-center">
            <span class="text-surface-900 dark:text-surface-50">Évaluations complétées</span>
            <Tag value="42/85" severity="success" />
          </div>
          <Button label="Gérer les Cycles" icon="pi pi-arrow-right" class="w-full mt-4" />
        </div>
      </Card>
    </div>

    <!-- Team Org Chart -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>Structure Organisationnelle</template>
      <div class="text-center p-4 text-surface-600 dark:text-surface-400">
        <i class="pi pi-sitemap text-2xl mb-2"></i>
        <p>Organigramme interactif</p>
        <Button label="Voir l'Organigramme" icon="pi pi-arrow-right" class="mt-4" />
      </div>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
})

const pendingLeaves = computed(() => [
  { id: 1, employee: 'Marie Dupont', days: 5, startDate: '2026-05-15' },
  { id: 2, employee: 'Jean Martin', days: 3, startDate: '2026-05-20' },
  { id: 3, employee: 'Sophie Bernard', days: 7, startDate: '2026-05-28' },
])
</script>
