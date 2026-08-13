<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <!-- Pipeline Value -->
      <Card class="bg-white dark:bg-surface-800 dark:bg-surface-800">
        <template #title>
          <div class="flex items-center justify-between">
            <span>Mon Pipeline</span>
            <TrendIcon :trend="'up'" class="text-green-500" />
          </div>
        </template>
        <div class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(metrics.my_pipeline_value) }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">{{ metrics.my_opportunities_count }} opportunités actives</p>
      </Card>

      <!-- Quota Progress -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Progrès Quota</template>
        <ProgressBar :value="metrics.quota_progress" :show-value="true" class="mb-2" />
        <p class="text-sm text-surface-600 dark:text-surface-400">{{ metrics.quota_progress }}% atteint ce mois-ci</p>
      </Card>

      <!-- Deals Won This Month -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Affaires Gagnées</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.won_deals_this_month }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Ce mois-ci</p>
      </Card>
    </div>

    <!-- Leads & Activities Section -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Hot Leads (Score > 70) -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Mes Leads Chauds 🔥</template>
        <div class="space-y-3">
          <div v-if="hotLeads.length" class="space-y-2">
            <div v-for="lead in hotLeads" :key="lead.id" class="flex items-center justify-between border-b pb-2">
              <div>
                <p class="font-semibold text-surface-900 dark:text-surface-50">{{ lead.name }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400">Score: {{ lead.score }}/100</p>
              </div>
              <Button icon="pi pi-arrow-right" class="p-button-rounded p-button-sm p-button-text" />
            </div>
          </div>
          <p v-else class="text-surface-500 dark:text-surface-400 text-sm">Aucun lead chaud en ce moment</p>
        </div>
      </Card>

      <!-- Activities to Do -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Activités à Faire</template>
        <div class="space-y-3">
          <div v-if="metrics.open_activities > 0" class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-surface-900 dark:text-surface-50">Appels à passer</span>
              <Tag value="5" severity="warning" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-surface-900 dark:text-surface-50">Emails à envoyer</span>
              <Tag value="8" severity="info" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-surface-900 dark:text-surface-50">Suivis requis</span>
              <Tag value="3" severity="warning" />
            </div>
          </div>
          <p v-else class="text-green-700 dark:text-green-300 text-sm font-semibold">✓ Tout est à jour!</p>
        </div>
      </Card>
    </div>

    <!-- Pipeline Kanban View -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>Pipeline par Étape</template>
      <div class="flex gap-4 overflow-x-auto pb-4">
        <PipelineColumn v-for="stage in pipelineStages" :key="stage" :stage="stage" :deals="dealsByStage[stage] || []" />
      </div>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import ProgressBar from 'primevue/progressbar'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import PipelineColumn from '@/Components/CRM/PipelineColumn.vue'
import TrendIcon from '@/Components/TrendIcon.vue'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
})

const pipelineStages = ['nouveau', 'contacté', 'qualifié', 'proposition', 'gagné', 'perdu']

const hotLeads = computed(() => [
  { id: 1, name: 'Acme Corp', score: 85 },
  { id: 2, name: 'TechStartup Inc', score: 78 },
])

const dealsByStage = computed(() => ({
  nouveau: [{ id: 1, name: 'Deal 1', value: 50000 }],
  contacté: [{ id: 2, name: 'Deal 2', value: 75000 }],
  qualifié: [{ id: 3, name: 'Deal 3', value: 100000 }],
}))

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(value || 0)
}
</script>
