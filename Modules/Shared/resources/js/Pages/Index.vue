<script setup>
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
const { guidance } = useAiAssistant('Shared', 'view_dashboard')
</script>
<template>
  <div class="p-6 space-y-4">
    <h1 class="text-2xl font-bold text-gray-900">Composants partagés</h1>
    <AIAssistantPanel v-if="guidance" :guidance="guidance" />
    <div class="bg-white rounded-lg shadow p-6 text-gray-500 text-sm">
      <p>Ce module fournit des services et helpers partagés entre tous les modules WideHalo ERP.</p>
      <ul class="mt-4 list-disc list-inside space-y-1">
        <li>BaseService — contrat de service de base</li>
        <li>PersonalizationFramework — préférences utilisateur/tenant</li>
        <li>SentimentAnalysisService — analyse sentiment multilingue</li>
        <li>UnifiedForecastingService — agrégation des prévisions cross-modules</li>
      </ul>
    </div>
  </div>
</template>
