<template>
  <AppLayout>
    <Head title="Plans stratégiques" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Plans stratégiques</h1>
          <p class="wh-page-subtitle">Vision, mission et objectifs par plan — santé calculée sur la progression pondérée des objectifs</p>
        </div>
        <Link href="/strategy" class="wh-btn wh-btn-secondary">← Cockpit</Link>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div v-if="!plans.data.length" class="wh-empty-state">Aucun plan stratégique trouvé.</div>

      <div v-else class="wh-plan-grid">
        <Link
          v-for="plan in plans.data"
          :key="plan.id"
          :href="`/strategy/plans/${plan.id}`"
          class="wh-plan-card"
        >
          <div class="wh-plan-card-header">
            <h2 class="wh-plan-name">{{ plan.name }}</h2>
            <span class="wh-badge" :class="statusClass(plan.status)">{{ statusLabel(plan.status) }}</span>
          </div>
          <p class="wh-plan-vision">{{ plan.vision || 'Aucune vision définie.' }}</p>
          <div class="wh-plan-meta">
            <span>{{ plan.framework ? frameworkLabel(plan.framework) : '—' }}</span>
            <span v-if="plan.period_start">{{ plan.period_start }}–{{ plan.period_end }}</span>
            <span>{{ plan.objectives_count ?? plan.objectives?.length ?? 0 }} objectif(s)</span>
          </div>
          <div class="wh-plan-health">
            <ProgressBar :value="plan.health_score ?? 0" :showValue="false" style="height: 8px; flex: 1" :class="healthBarClass(plan.health_score)" />
            <span class="wh-plan-health-value" :class="healthTextClass(plan.health_score)">{{ plan.health_score ?? 0 }}%</span>
          </div>
        </Link>
      </div>

      <!-- Pagination -->
      <div v-if="plans.last_page > 1" class="wh-pagination">
        <Link
          v-for="page in plans.last_page"
          :key="page"
          :href="`/strategy/plans?page=${page}`"
          class="wh-page-link"
          :class="{ 'wh-page-link-active': page === plans.current_page }"
        >
          {{ page }}
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ProgressBar from 'primevue/progressbar'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Strategy', 'view_plans')

defineProps({
  plans: {
    type: Object,
    default: () => ({ data: [], current_page: 1, last_page: 1 }),
  },
})

function statusLabel(status) {
  return { draft: 'Brouillon', active: 'Actif', archived: 'Archivé' }[status] ?? status
}
function statusClass(status) {
  return {
    draft: 'wh-badge-slate',
    active: 'wh-badge-green',
    archived: 'wh-badge-gray',
  }[status] ?? 'wh-badge-slate'
}
function frameworkLabel(fw) {
  return { okr: 'OKR', bsc: 'Balanced Scorecard', hoshin: 'Hoshin Kanri', hybrid: 'Hybride' }[fw] ?? fw
}
function healthBarClass(score) {
  if (score >= 70) return 'wh-progress-green'
  if (score >= 40) return 'wh-progress-amber'
  return 'wh-progress-red'
}
function healthTextClass(score) {
  if (score >= 70) return 'wh-health-green'
  if (score >= 40) return 'wh-health-amber'
  return 'wh-health-red'
}
</script>

<style scoped>
.wh-page { max-width: 1200px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 0; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; background: #fff; border: 1px dashed #D1D5DB; border-radius: 10px; }
.wh-plan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.wh-plan-card { display: block; background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px 18px; text-decoration: none; color: inherit; transition: box-shadow 0.15s, border-color 0.15s; }
.wh-plan-card:hover { border-color: #2563EB; box-shadow: 0 2px 8px rgba(37,99,235,0.08); }
.wh-plan-card-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.wh-plan-name { font-size: 15px; font-weight: 700; margin: 0; color: #111827; }
.wh-plan-vision { font-size: 12px; color: #6B7280; margin: 0 0 12px; min-height: 32px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.wh-plan-meta { display: flex; gap: 12px; font-size: 11px; color: #9CA3AF; margin-bottom: 12px; flex-wrap: wrap; }
.wh-plan-health { display: flex; align-items: center; gap: 8px; }
.wh-plan-health-value { font-size: 13px; font-weight: 700; width: 40px; text-align: right; }
.wh-health-green { color: #16A34A; }
.wh-health-amber { color: #D97706; }
.wh-health-red { color: #DC2626; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-badge-gray { background: #E5E7EB; color: #4B5563; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-pagination { display: flex; gap: 4px; justify-content: center; }
.wh-page-link { padding: 6px 12px; border-radius: 6px; font-size: 13px; color: #374151; background: #F3F4F6; text-decoration: none; }
.wh-page-link-active { background: #2563EB; color: #fff; }
:deep(.wh-progress-green .p-progressbar-value) { background: #16A34A; }
:deep(.wh-progress-amber .p-progressbar-value) { background: #D97706; }
:deep(.wh-progress-red .p-progressbar-value) { background: #DC2626; }
</style>
