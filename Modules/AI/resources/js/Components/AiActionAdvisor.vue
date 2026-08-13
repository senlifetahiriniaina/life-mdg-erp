<template>
  <Dialog v-model:visible="visible" :header="null" :modal="true" :closable="false"
    :style="{ width: '520px', 'border-radius': '12px' }" :pt="{ content: { class: 'p-0' } }">
    <div class="p-6">
      <!-- Header with risk indicator -->
      <div class="flex items-start gap-3 mb-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
          :class="riskBgClass">
          <i :class="riskIcon" class="text-lg"></i>
        </div>
        <div>
          <h3 class="font-bold text-lg text-surface-900 dark:text-surface-50">
            Analyse IA — {{ actionLabel }}
          </h3>
          <p class="text-sm text-surface-500 mt-0.5">{{ riskLabel }}</p>
        </div>
        <Tag :value="riskLevelLabel" :severity="riskSeverity" class="ml-auto flex-shrink-0" />
      </div>

      <!-- Loading state -->
      <div v-if="loading" class="py-8 text-center">
        <i class="pi pi-spin pi-spinner text-3xl text-blue-500 mb-3 block"></i>
        <p class="text-surface-500 text-sm">Analyse en cours...</p>
      </div>

      <!-- Budget exceeded -->
      <div v-else-if="budgetExceeded" class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
        <div class="flex items-center gap-2 text-orange-700 font-medium mb-1">
          <i class="pi pi-wallet"></i> Budget IA épuisé
        </div>
        <p class="text-sm text-orange-600">Votre quota AI pour cette période est atteint. Vous pouvez quand même procéder sans analyse.</p>
      </div>

      <!-- AI Advice -->
      <div v-else-if="advice" class="space-y-4">
        <!-- Warnings -->
        <div v-if="advice.warnings?.length" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
          <div class="flex items-center gap-2 text-red-700 dark:text-red-400 font-semibold text-sm mb-2">
            <i class="pi pi-exclamation-triangle"></i> Risques identifiés
          </div>
          <ul class="space-y-1">
            <li v-for="w in advice.warnings" :key="w" class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300">
              <i class="pi pi-circle-fill text-[6px] mt-2 flex-shrink-0"></i>{{ w }}
            </li>
          </ul>
        </div>

        <!-- Consequences -->
        <div v-if="advice.consequences?.length">
          <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300 font-semibold text-sm mb-2">
            <i class="pi pi-arrow-right text-blue-500"></i> Ce qui va se passer
          </div>
          <ul class="space-y-1">
            <li v-for="c in advice.consequences" :key="c" class="flex items-start gap-2 text-sm text-surface-600 dark:text-surface-400">
              <i class="pi pi-circle-fill text-[6px] mt-2 flex-shrink-0 text-blue-400"></i>{{ c }}
            </li>
          </ul>
        </div>

        <!-- Options -->
        <div v-if="advice.options?.length" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
          <div class="flex items-center gap-2 text-blue-700 dark:text-blue-400 font-semibold text-sm mb-2">
            <i class="pi pi-lightbulb"></i> Alternatives à considérer
          </div>
          <ul class="space-y-1">
            <li v-for="o in advice.options" :key="o" class="flex items-start gap-2 text-sm text-blue-700 dark:text-blue-300">
              <i class="pi pi-check text-[10px] mt-1.5 flex-shrink-0"></i>{{ o }}
            </li>
          </ul>
        </div>

        <!-- Considerations -->
        <div v-if="advice.considerations?.length">
          <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300 font-semibold text-sm mb-2">
            <i class="pi pi-question-circle text-amber-500"></i> Vérifier d'abord
          </div>
          <ul class="space-y-1">
            <li v-for="c in advice.considerations" :key="c" class="flex items-start gap-2 text-sm text-surface-600 dark:text-surface-400">
              <i class="pi pi-circle-fill text-[6px] mt-2 flex-shrink-0 text-amber-400"></i>{{ c }}
            </li>
          </ul>
        </div>

        <!-- Budget remaining -->
        <div v-if="budget" class="flex items-center justify-between text-xs text-surface-400 pt-1 border-t border-surface-200">
          <span>Budget IA restant ce mois</span>
          <span :class="budget.usage_pct > 80 ? 'text-orange-500 font-medium' : 'text-surface-500'">
            ${{ budget.remaining_usd?.toFixed(3) }} · {{ Math.round(budget.usage_pct) }}% utilisé
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 mt-5 pt-4 border-t border-surface-200 dark:border-surface-700">
        <Button label="Annuler" severity="secondary" class="flex-1" @click="cancel" />
        <Button
          :label="advice?.recommendation === 'reconsider' ? '⚠ Procéder quand même' : 'Procéder'"
          :severity="advice?.recommendation === 'reconsider' ? 'danger' : advice?.recommendation === 'caution' ? 'warn' : 'primary'"
          class="flex-1"
          :loading="loading"
          @click="proceed"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import axios from 'axios'

const visible = ref(false)
const loading = ref(false)
const advice = ref(null)
const budget = ref(null)
const budgetExceeded = ref(false)
const pendingAction = ref(null)
const actionLabel = ref('')
const module = ref('')

const riskBgClass = computed(() => {
  const r = advice.value?.risk_level
  if (r === 'critical') return 'bg-red-100 text-red-600'
  if (r === 'high') return 'bg-orange-100 text-orange-600'
  if (r === 'medium') return 'bg-yellow-100 text-yellow-600'
  return 'bg-blue-100 text-blue-600'
})

const riskIcon = computed(() => {
  const r = advice.value?.risk_level
  if (r === 'critical' || r === 'high') return 'pi pi-exclamation-triangle'
  if (r === 'medium') return 'pi pi-info-circle'
  return 'pi pi-check-circle'
})

const riskLabel = computed(() => {
  const r = advice.value?.recommendation
  if (r === 'reconsider') return 'L\'IA recommande de reconsidérer cette action'
  if (r === 'caution') return 'Procédez avec prudence'
  return 'L\'IA a analysé les impacts potentiels'
})

const riskLevelLabel = computed(() => {
  const r = advice.value?.risk_level
  return { critical: 'Critique', high: 'Risque élevé', medium: 'Risque modéré', low: 'Faible risque' }[r] || 'Analyse'
})

const riskSeverity = computed(() => {
  const r = advice.value?.risk_level
  return { critical: 'danger', high: 'warn', medium: 'warn', low: 'success' }[r] || 'info'
})

async function show(opts) {
  module.value = opts.module
  actionLabel.value = opts.actionLabel || opts.action
  pendingAction.value = opts.onConfirm
  advice.value = null
  budget.value = null
  budgetExceeded.value = false
  visible.value = true
  loading.value = true

  try {
    const locale = document.documentElement.lang || 'fr'
    const { data } = await axios.post('/api/v1/ai/advise', {
      module: opts.module,
      action: opts.action,
      context: opts.context || {},
      locale,
    })
    advice.value = data.advice
    budget.value = data.budget
    budgetExceeded.value = data.budget?.limit_exceeded || false
  } catch {
    advice.value = {
      warnings: ['Vérifiez que vous avez les droits nécessaires'],
      consequences: ['Cette action sera enregistrée dans les logs d\'audit'],
      options: [],
      considerations: [],
      risk_level: 'low',
      recommendation: 'proceed',
    }
  } finally {
    loading.value = false
  }
}

function proceed() {
  visible.value = false
  pendingAction.value?.()
}

function cancel() {
  visible.value = false
  pendingAction.value = null
}

defineExpose({ show })
</script>
