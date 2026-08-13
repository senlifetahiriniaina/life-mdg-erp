<template>
  <div class="space-y-6">
    <!-- WhatsApp Metrics -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Active Conversations -->
      <Card class="bg-white dark:bg-surface-800 dark:bg-surface-800">
        <template #title>Conversations Actives</template>
        <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ metrics.active_conversations }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">À répondre</p>
        <Button label="Gérer" icon="pi pi-arrow-right" class="mt-4 w-full p-button-sm" />
      </Card>

      <!-- Avg Response Time -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Temps de Réponse</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.avg_response_time }}s</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Réponse moyenne</p>
      </Card>

      <!-- Sentiment Analysis -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Sentiment Global</template>
        <div class="text-3xl font-bold" :class="metrics.positive_sentiment > 70 ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300'">
          {{ metrics.positive_sentiment }}%
        </div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Messages positifs</p>
      </Card>

      <!-- Broadcast Campaigns -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>Campagnes Actives</template>
        <div class="text-3xl font-bold text-violet-700 dark:text-violet-300">{{ metrics.active_campaigns }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">En cours</p>
      </Card>
    </div>

    <!-- Conversations & Content -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Pending Conversations -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>💬 Conversations en Attente</template>
        <div class="space-y-3">
          <div v-for="conv in pendingConversations" :key="conv.id" class="border-l-4 border-yellow-500 pl-3 py-2 hover:bg-yellow-50 dark:bg-yellow-900/20 rounded cursor-pointer">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-surface-900 dark:text-surface-50">{{ conv.customer }}</p>
                <p class="text-xs text-surface-600 dark:text-surface-400">{{ conv.message }}</p>
                <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">⏱️ {{ conv.wait_time }}</p>
              </div>
              <SentimentBadge :sentiment="conv.sentiment" />
            </div>
          </div>
        </div>
      </Card>

      <!-- Broadcasting Templates -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>📢 Modèles de Campagne</template>
        <div class="space-y-3">
          <div v-for="template in templates" :key="template.id" class="flex items-center justify-between border-b pb-2 hover:bg-gray-50 dark:bg-surface-800 p-2 rounded">
            <div>
              <p class="font-semibold text-surface-900 dark:text-surface-50">{{ template.name }}</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ template.type }} | {{ template.recipients }} destinataires</p>
            </div>
            <Button icon="pi pi-send" class="p-button-rounded p-button-sm p-button-info p-button-text" />
          </div>
        </div>
      </Card>
    </div>

    <!-- AI-Powered Insights -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>🤖 Insights IA et Suggestions</template>
      <div class="grid grid-cols-1 gap-3">
        <div v-for="insight in aiInsights" :key="insight.id" class="bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded p-3">
          <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">{{ insight.title }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ insight.description }}</p>
          <Button v-if="insight.action" :label="insight.action" class="mt-2 p-button-sm p-button-text" />
        </div>
      </div>
    </Card>

    <!-- Campaign Performance -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>📊 Performance des Campagnes Récentes</template>
      <div class="space-y-3">
        <div v-for="campaign in recentCampaigns" :key="campaign.id" class="border-b pb-3 last:border-b-0">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <p class="font-semibold text-surface-900 dark:text-surface-50">{{ campaign.name }}</p>
              <ProgressBar :value="campaign.engagement" class="mt-2" />
            </div>
            <div class="text-right ml-4">
              <p class="text-sm font-bold text-surface-900 dark:text-surface-50">{{ campaign.engagement }}%</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">Engagement</p>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- Status Indicator -->
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 rounded-lg p-4">
      <p class="text-sm text-green-900">
        <strong>✓ Tous les systèmes sont actifs.</strong> Vous avez {{ metrics.unread_messages }} messages non lus et {{ metrics.pending_approvals }} approbations en attente.
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

const pendingConversations = computed(() => [
  { id: 1, customer: 'Ahmed', message: 'Quand livrez-vous?', wait_time: '5 min', sentiment: 'neutral' },
  { id: 2, customer: 'Fatima', message: 'Je veux commander maintenant', wait_time: '2 min', sentiment: 'positive' },
])

const templates = computed(() => [
  { id: 1, name: 'Promotion Flash', type: 'Promotion', recipients: 5200 },
  { id: 2, name: 'Suivi de Commande', type: 'Notification', recipients: 1850 },
  { id: 3, name: 'Feedback Client', type: 'Survey', recipients: 3420 },
])

const aiInsights = computed(() => [
  { id: 1, title: 'Meilleur moment pour envoyer', description: 'Les messages envoyés entre 14h-16h ont 45% plus d\'engagement', action: 'Voir analyse' },
  { id: 2, title: 'Segment de clients inactifs', description: '230 clients n\'ont pas interagi depuis 7 jours', action: 'Créer campagne' },
])

const recentCampaigns = computed(() => [
  { id: 1, name: 'Vente du Week-end', engagement: 78 },
  { id: 2, name: 'Nouveau Produit', engagement: 65 },
  { id: 3, name: 'Fidélité Client', engagement: 82 },
])

const SentimentBadge = {
  template: '<span :class="[\'inline-block px-2 py-1 text-xs rounded font-semibold\', getClass(sentiment)]">{{ sentiment }}</span>',
  props: ['sentiment'],
  methods: {
    getClass(sentiment) {
      const classes = {
        positive: 'bg-green-100 text-green-800',
        neutral: 'bg-gray-100 text-gray-800',
        negative: 'bg-red-100 text-red-800',
      }
      return classes[sentiment] || classes.neutral
    },
  },
}
</script>
