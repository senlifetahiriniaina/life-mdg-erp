<template>
  <AppLayout>
    <Head :title="plan.name" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <Link href="/strategy/plans" class="wh-back-link">← Plans</Link>
          <h1 class="wh-page-title">{{ plan.name }}</h1>
          <p class="wh-page-subtitle">{{ plan.vision || 'Aucune vision définie.' }}</p>
        </div>
        <div class="wh-health-badge" :class="healthClass(healthScore)">
          <span class="wh-health-badge-value">{{ healthScore }}%</span>
          <span class="wh-health-badge-label">Santé</span>
        </div>
      </div>

      <!-- Plan meta -->
      <div class="wh-card">
        <div class="wh-meta-grid">
          <div>
            <p class="wh-meta-label">Mission</p>
            <p class="wh-meta-value">{{ plan.mission || '—' }}</p>
          </div>
          <div>
            <p class="wh-meta-label">Framework</p>
            <p class="wh-meta-value">{{ frameworkLabel(plan.framework) }}</p>
          </div>
          <div>
            <p class="wh-meta-label">Période</p>
            <p class="wh-meta-value">{{ plan.period_start }}–{{ plan.period_end }}</p>
          </div>
          <div>
            <p class="wh-meta-label">Statut</p>
            <p class="wh-meta-value"><span class="wh-badge" :class="statusClass(plan.status)">{{ statusLabel(plan.status) }}</span></p>
          </div>
        </div>
      </div>

      <!-- Pillars -->
      <div class="wh-card" v-if="plan.pillars && plan.pillars.length">
        <h2 class="wh-card-title">Piliers stratégiques</h2>
        <div class="wh-pillar-grid">
          <div v-for="pillar in plan.pillars" :key="pillar.id" class="wh-pillar-tile" :style="pillar.color ? `border-left-color:${pillar.color}` : ''">
            <p class="wh-pillar-name">{{ pillar.name }}</p>
            <p class="wh-pillar-desc">{{ pillar.description }}</p>
          </div>
        </div>
      </div>

      <!-- Objectives tree with key results -->
      <div class="wh-card">
        <h2 class="wh-card-title">Objectifs &amp; Résultats clés</h2>
        <div v-if="!topObjectives.length" class="wh-empty-state">Aucun objectif défini pour ce plan.</div>
        <ul v-else class="wh-obj-tree">
          <ObjectiveNode v-for="obj in topObjectives" :key="obj.id" :objective="obj" :depth="0" />
        </ul>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, h } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ProgressBar from 'primevue/progressbar'

const props = defineProps({
  plan: { type: Object, required: true },
  tree: { type: Object, default: () => ({}) },
  healthScore: { type: Number, default: 0 },
})

// `tree` (StrategyPlanService::getFullTree) nests top-level objectives with
// children already loaded (whereNull('parent_id') at the root). `plan.objectives`
// is the flatter eager-loaded relation used as a fallback if tree is empty.
const topObjectives = computed(() => {
  if (props.tree?.objectives?.length) return props.tree.objectives
  return (props.plan.objectives ?? []).filter((o) => !o.parent_id)
})

function healthClass(score) {
  if (score >= 70) return 'wh-health-green'
  if (score >= 40) return 'wh-health-amber'
  return 'wh-health-red'
}
function statusLabel(status) {
  return { draft: 'Brouillon', active: 'Actif', archived: 'Archivé' }[status] ?? status
}
function statusClass(status) {
  return { draft: 'wh-badge-slate', active: 'wh-badge-green', archived: 'wh-badge-gray' }[status] ?? 'wh-badge-slate'
}
function frameworkLabel(fw) {
  return { okr: 'OKR', bsc: 'Balanced Scorecard', hoshin: 'Hoshin Kanri', hybrid: 'Hybride' }[fw] ?? (fw || '—')
}

// Recursive objective node rendered via a small functional component so the
// same status/progress styling logic isn't duplicated per nesting depth.
const statusLabels = { draft: 'Brouillon', active: 'Actif', at_risk: 'À risque', behind: 'En retard', completed: 'Terminé', cancelled: 'Annulé' }
const statusClasses = {
  draft: 'wh-badge-slate', active: 'wh-badge-green', at_risk: 'wh-badge-amber',
  behind: 'wh-badge-red', completed: 'wh-badge-blue', cancelled: 'wh-badge-gray',
}

const ObjectiveNode = {
  name: 'ObjectiveNode',
  props: { objective: Object, depth: { type: Number, default: 0 } },
  setup(nodeProps) {
    return () => h('li', { class: 'wh-obj-node', style: `margin-left:${nodeProps.depth * 20}px` }, [
      h('div', { class: 'wh-obj-row' }, [
        h('span', { class: 'wh-obj-title' }, nodeProps.objective.title),
        h('span', { class: ['wh-badge', statusClasses[nodeProps.objective.status] ?? 'wh-badge-slate'] }, statusLabels[nodeProps.objective.status] ?? nodeProps.objective.status),
        h(ProgressBar, { value: nodeProps.objective.progress ?? 0, showValue: true, style: 'height:8px;width:140px' }),
      ]),
      nodeProps.objective.keyResults && nodeProps.objective.keyResults.length
        ? h('ul', { class: 'wh-kr-list' }, nodeProps.objective.keyResults.map((kr) =>
          h('li', { key: kr.id, class: 'wh-kr-item' }, [
            h('span', { class: 'wh-kr-title' }, kr.title),
            h('span', { class: 'wh-kr-value' }, `${kr.current_value ?? 0}${kr.unit ? ' ' + kr.unit : ''} / ${kr.target_value ?? '—'}${kr.unit ? ' ' + kr.unit : ''}`),
            h('span', { class: ['wh-badge', kr.confidence === 'on_track' ? 'wh-badge-green' : kr.confidence === 'at_risk' ? 'wh-badge-amber' : 'wh-badge-red'] },
              kr.confidence === 'on_track' ? 'En bonne voie' : kr.confidence === 'at_risk' ? 'À risque' : 'En retard'),
          ])
        ))
        : null,
      nodeProps.objective.children && nodeProps.objective.children.length
        ? h('ul', { class: 'wh-obj-children' }, nodeProps.objective.children.map((child) =>
          h(ObjectiveNode, { key: child.id, objective: child, depth: nodeProps.depth + 1 })
        ))
        : null,
    ])
  },
}
</script>

<style scoped>
.wh-page { max-width: 1000px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.wh-back-link { font-size: 12px; color: #6B7280; text-decoration: none; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 6px 0 2px; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin: 0; }
.wh-health-badge { display: flex; flex-direction: column; align-items: center; padding: 10px 18px; border-radius: 10px; background: #F9FAFB; flex-shrink: 0; }
.wh-health-badge-value { font-size: 24px; font-weight: 800; }
.wh-health-badge-label { font-size: 10px; color: #9CA3AF; text-transform: uppercase; }
.wh-health-green { color: #16A34A; }
.wh-health-amber { color: #D97706; }
.wh-health-red { color: #DC2626; }
.wh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px; }
.wh-card-title { font-size: 15px; font-weight: 700; margin: 0 0 12px; color: #111827; }
.wh-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
.wh-meta-label { font-size: 11px; color: #9CA3AF; text-transform: uppercase; margin: 0 0 3px; }
.wh-meta-value { font-size: 13px; color: #111827; margin: 0; }
.wh-pillar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.wh-pillar-tile { border-left: 4px solid #9CA3AF; background: #F9FAFB; border-radius: 8px; padding: 10px 12px; }
.wh-pillar-name { font-size: 13px; font-weight: 700; margin: 0 0 4px; color: #111827; }
.wh-pillar-desc { font-size: 12px; color: #6B7280; margin: 0; }
.wh-empty-state { text-align: center; padding: 24px; color: #9CA3AF; font-size: 13px; }
.wh-obj-tree { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.wh-obj-node { padding: 8px 0; border-bottom: 1px solid #F3F4F6; }
.wh-obj-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.wh-obj-title { font-size: 13px; font-weight: 600; color: #111827; flex: 1; min-width: 160px; }
.wh-kr-list { list-style: none; margin: 6px 0 0 20px; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.wh-kr-item { display: flex; align-items: center; gap: 10px; font-size: 12px; color: #4B5563; }
.wh-kr-title { flex: 1; }
.wh-kr-value { color: #6B7280; }
.wh-obj-children { list-style: none; margin: 6px 0 0; padding: 0; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; white-space: nowrap; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-amber { background: #FEF3C7; color: #92400E; }
.wh-badge-red { background: #FEE2E2; color: #991B1B; }
.wh-badge-blue { background: #DBEAFE; color: #1E40AF; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-badge-gray { background: #E5E7EB; color: #4B5563; }
</style>
