<template>
  <div class="space-y-6">
    <!-- Queue Status -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Open Tickets -->
      <Card class="bg-white dark:bg-surface-800 dark:bg-surface-800">
        <template #title>Tickets Ouverts</template>
        <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ metrics.open_tickets }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">À traiter</p>
        <Button label="Voir File" icon="pi pi-list" class="mt-4 w-full p-button-sm" />
      </Card>

      <!-- Avg Response Time -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Temps de Réponse</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.avg_response_time }}min</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Moyenne aujourd'hui</p>
      </Card>

      <!-- Customer Satisfaction -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Satisfaction Client</template>
        <ProgressBar :value="metrics.satisfaction_score" :show-value="true" class="mb-2" />
        <p class="text-sm text-surface-600 dark:text-surface-400">{{ metrics.satisfaction_score }}%</p>
      </Card>

      <!-- Escalations -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Escalades</template>
        <div class="text-3xl font-bold" :class="metrics.escalations > 3 ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300'">
          {{ metrics.escalations }}
        </div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Tickets escaladés aujourd'hui</p>
      </Card>
    </div>

    <!-- Ticket Queue -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Priority Queue -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>🔴 Tickets Prioritaires</template>
        <div class="space-y-3">
          <div v-for="ticket in priorityTickets" :key="ticket.id" class="border-l-4 border-red-500 pl-3 py-2 hover:bg-red-50 dark:bg-red-900/20 rounded cursor-pointer">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-surface-900 dark:text-surface-50">#{{ ticket.id }} - {{ ticket.subject }}</p>
                <p class="text-xs text-surface-600 dark:text-surface-400">{{ ticket.customer }} | {{ ticket.wait_time }}</p>
              </div>
              <Tag value="URGENT" severity="danger" />
            </div>
          </div>
        </div>
      </Card>

      <!-- Recent Updates -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>ℹ️ Base de Connaissances</template>
        <div class="space-y-3">
          <div v-for="article in kbArticles" :key="article.id" class="flex items-center justify-between border-b pb-2 hover:bg-gray-50 dark:bg-surface-800 p-2 rounded">
            <div class="flex-1">
              <p class="font-semibold text-surface-900 dark:text-surface-50 text-sm">{{ article.title }}</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ article.views }} vues | 📊 Pertinence: {{ article.relevance }}%</p>
            </div>
            <Button icon="pi pi-external-link" class="p-button-rounded p-button-sm p-button-text" />
          </div>
        </div>
      </Card>
    </div>

    <!-- Suggested Responses -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>💡 Réponses Suggérées par IA</template>
      <div class="space-y-3">
        <div v-for="suggestion in aiSuggestions" :key="suggestion.id" class="bg-primary-50 dark:bg-primary-900/20 border border-blue-200 rounded p-3">
          <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">{{ suggestion.issue }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ suggestion.suggested_response }}</p>
          <Button label="Utiliser" icon="pi pi-check" class="mt-2 p-button-sm p-button-text p-button-success" />
        </div>
      </div>
    </Card>

    <!-- SLA Status -->
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-lg p-4">
      <p class="text-sm text-green-900">
        <strong>✓ SLA Status:</strong> 94% des réponses respectent l'objectif de 2h | 0 violation de SLA aujourd'hui
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
})

const priorityTickets = computed(() => [
  { id: 12345, subject: 'Paiement non reconnu', customer: 'Client A', wait_time: '23 min', priority: 'high' },
  { id: 12346, subject: 'Commande non livrée', customer: 'Client B', wait_time: '18 min', priority: 'high' },
])

const kbArticles = computed(() => [
  { id: 1, title: 'Comment réinitialiser mon mot de passe?', views: 1250, relevance: 95 },
  { id: 2, title: 'Politique de remboursement', views: 890, relevance: 88 },
  { id: 3, title: 'Suivi de commande', views: 650, relevance: 92 },
])

const aiSuggestions = computed(() => [
  { id: 1, issue: 'Client demande remboursement', suggested_response: 'Nous comprenons votre frustration. Veuillez fournir votre numéro de commande et nous traiterons votre remboursement en 24-48h...' },
  { id: 2, issue: 'Demande de délai de paiement', suggested_response: 'Nous proposons des plans de paiement flexibles. Contactez notre équipe financière pour discuter des options disponibles...' },
])
</script>
