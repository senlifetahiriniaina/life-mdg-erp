<template>
  <AppLayout>
    <Head title="Objectifs OKR" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <Link href="/strategy" class="wh-back-link">← Cockpit</Link>
          <h1 class="wh-page-title">Objectifs OKR</h1>
          <p class="wh-page-subtitle">Arbre Objectifs → Résultats clés → Ratios liés — cliquez un objectif pour voir/gérer ses ressources liées</p>
        </div>
        <select v-model="planId" class="wh-input" @change="switchPlan">
          <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div class="wh-content">
        <!-- OKR tree -->
        <div class="wh-card wh-tree-card">
          <h2 class="wh-card-title">{{ okrTree.plan_name || 'Aucun plan sélectionné' }}</h2>
          <div v-if="!okrTree.objectives || !okrTree.objectives.length" class="wh-empty-state">
            Aucun objectif pour ce plan.
          </div>
          <ul v-else class="wh-obj-tree">
            <ObjNode
              v-for="obj in okrTree.objectives"
              :key="obj.id"
              :objective="obj"
              :depth="0"
              :selected-id="selectedObjective?.id"
              @select="selectObjective"
            />
          </ul>
        </div>

        <!-- Side panel: linked resources for selected objective -->
        <div class="wh-card wh-panel-card" v-if="selectedObjective">
          <div class="wh-panel-header">
            <h2 class="wh-card-title">{{ selectedObjective.title }}</h2>
            <button class="wh-icon-btn" @click="selectedObjective = null" aria-label="Fermer">✕</button>
          </div>

          <div class="wh-panel-section">
            <p class="wh-panel-label">Contribution agrégée</p>
            <p v-if="loadingAggregated" class="wh-muted">Chargement...</p>
            <p v-else-if="aggregated" class="wh-agg-value">
              {{ formatNum(aggregated.total_value) }} — {{ aggregated.resource_count }} ressource(s) liée(s)
            </p>
          </div>

          <div class="wh-panel-section">
            <p class="wh-panel-label">Ressources liées</p>
            <p v-if="loadingLinks" class="wh-muted">Chargement...</p>
            <ul v-else-if="links.length" class="wh-link-list">
              <li v-for="link in links" :key="link.id" class="wh-link-item">
                <div>
                  <p class="wh-link-type">{{ link.linkable_type }} #{{ link.linkable_id }}</p>
                  <p class="wh-link-value">{{ link.contribution_value != null ? formatNum(link.contribution_value) + ' ' + (link.unit_type ?? '') : '—' }}</p>
                </div>
                <button class="wh-btn wh-btn-danger-outline" @click="unlink(link)">Délier</button>
              </li>
            </ul>
            <p v-else class="wh-muted">Aucune ressource liée.</p>
          </div>

          <div class="wh-panel-section">
            <p class="wh-panel-label">Lier une nouvelle ressource</p>
            <form class="wh-link-form" @submit.prevent="createLink">
              <input v-model="linkForm.linkable_type" class="wh-input" placeholder="Module/Modèle (ex: Accounting/Invoice)" required />
              <input v-model.number="linkForm.linkable_id" type="number" class="wh-input" placeholder="ID de la ressource" required />
              <input v-model.number="linkForm.contribution_value" type="number" step="0.01" class="wh-input" placeholder="Contribution (optionnel)" />
              <input v-model="linkForm.unit_type" class="wh-input" placeholder="Unité (ex: XOF)" />
              <button type="submit" class="wh-btn wh-btn-primary" :disabled="linking">{{ linking ? 'Liaison...' : 'Lier' }}</button>
            </form>
            <p v-if="linkError" class="wh-error-text">{{ linkError }}</p>
          </div>
        </div>
        <div class="wh-card wh-panel-card wh-panel-placeholder" v-else>
          <p class="wh-muted">Sélectionnez un objectif pour voir et gérer ses ressources liées.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, h } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  plans: { type: Array, default: () => [] },
  selectedPlanId: { type: [Number, String], default: null },
  okrTree: { type: Object, default: () => ({}) },
})

const planId = ref(props.selectedPlanId)
const selectedObjective = ref(null)
const links = ref([])
const aggregated = ref(null)
const loadingLinks = ref(false)
const loadingAggregated = ref(false)
const linking = ref(false)
const linkError = ref(null)
const linkForm = ref({ linkable_type: '', linkable_id: null, contribution_value: null, unit_type: '' })

function switchPlan() {
  router.get('/strategy/objectives', { plan_id: planId.value }, { preserveState: true, preserveScroll: true })
}

async function selectObjective(obj) {
  selectedObjective.value = obj
  linkError.value = null
  linkForm.value = { linkable_type: '', linkable_id: null, contribution_value: null, unit_type: '' }
  await Promise.all([loadLinks(obj.id), loadAggregated(obj.id)])
}

async function loadLinks(objectiveId) {
  loadingLinks.value = true
  try {
    const { data } = await axios.get(`/api/v1/strategy/objective/${objectiveId}/links`)
    links.value = data.data ?? []
  } catch (e) {
    links.value = []
  } finally {
    loadingLinks.value = false
  }
}

async function loadAggregated(objectiveId) {
  loadingAggregated.value = true
  try {
    const { data } = await axios.get(`/api/v1/strategy/objective/${objectiveId}/aggregated`)
    aggregated.value = data.aggregated ?? null
  } catch (e) {
    aggregated.value = null
  } finally {
    loadingAggregated.value = false
  }
}

async function unlink(link) {
  if (!confirm('Délier cette ressource de l\'objectif ?')) return
  try {
    await axios.delete(`/api/v1/strategy/objective-links/${link.id}`)
    if (selectedObjective.value) await Promise.all([loadLinks(selectedObjective.value.id), loadAggregated(selectedObjective.value.id)])
  } catch (e) {
    // no-op: link stays visible, user can retry
  }
}

async function createLink() {
  if (!selectedObjective.value) return
  linking.value = true
  linkError.value = null
  try {
    await axios.post('/api/v1/strategy/objective-links/link', {
      objective_id: selectedObjective.value.id,
      linkable_type: linkForm.value.linkable_type,
      linkable_id: linkForm.value.linkable_id,
      contribution_value: linkForm.value.contribution_value || null,
      unit_type: linkForm.value.unit_type || null,
    })
    linkForm.value = { linkable_type: '', linkable_id: null, contribution_value: null, unit_type: '' }
    await Promise.all([loadLinks(selectedObjective.value.id), loadAggregated(selectedObjective.value.id)])
  } catch (e) {
    linkError.value = e?.response?.data?.message ?? 'Échec de la liaison.'
  } finally {
    linking.value = false
  }
}

function formatNum(v) {
  if (v == null) return '—'
  const n = Number(v)
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(n)
}

// Recursive objective node, mirrors Plans/Show.vue's pattern but adds a
// click handler to select the objective for the linked-resources panel.
const statusLabels = { draft: 'Brouillon', active: 'Actif', at_risk: 'À risque', behind: 'En retard', completed: 'Terminé', cancelled: 'Annulé' }
const statusClasses = {
  draft: 'wh-badge-slate', active: 'wh-badge-green', at_risk: 'wh-badge-amber',
  behind: 'wh-badge-red', completed: 'wh-badge-blue', cancelled: 'wh-badge-gray',
}

const ObjNode = {
  name: 'ObjNode',
  props: { objective: Object, depth: { type: Number, default: 0 }, selectedId: [Number, String, null] },
  emits: ['select'],
  setup(nodeProps, { emit }) {
    return () => h('li', { class: 'wh-obj-node' }, [
      h('div', {
        class: ['wh-obj-row', nodeProps.objective.id === nodeProps.selectedId ? 'wh-obj-row-active' : ''],
        style: `margin-left:${nodeProps.depth * 20}px`,
        onClick: () => emit('select', nodeProps.objective),
      }, [
        h('span', { class: 'wh-obj-title' }, nodeProps.objective.title),
        h('span', { class: ['wh-badge', statusClasses[nodeProps.objective.status] ?? 'wh-badge-slate'] }, statusLabels[nodeProps.objective.status] ?? nodeProps.objective.status),
        h('span', { class: 'wh-obj-progress' }, `${Math.round(nodeProps.objective.progress ?? 0)}%`),
      ]),
      nodeProps.objective.keyResults && nodeProps.objective.keyResults.length
        ? h('ul', { class: 'wh-kr-list', style: `margin-left:${nodeProps.depth * 20 + 16}px` }, nodeProps.objective.keyResults.map((kr) =>
          h('li', { key: kr.id, class: 'wh-kr-item' }, `${kr.title} — ${kr.current_value ?? 0}${kr.unit ? ' ' + kr.unit : ''} / ${kr.target_value ?? '—'}${kr.unit ? ' ' + kr.unit : ''}`)
        ))
        : null,
      nodeProps.objective.children && nodeProps.objective.children.length
        ? h('ul', { class: 'wh-obj-children' }, nodeProps.objective.children.map((child) =>
          h(ObjNode, { key: child.id, objective: child, depth: nodeProps.depth + 1, selectedId: nodeProps.selectedId, onSelect: (o) => emit('select', o) })
        ))
        : null,
    ])
  },
}
</script>

<style scoped>
.wh-page { max-width: 1200px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.wh-back-link { font-size: 12px; color: #6B7280; text-decoration: none; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 6px 0 2px; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin: 0; max-width: 560px; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-content { display: grid; grid-template-columns: 1.6fr 1fr; gap: 20px; align-items: start; }
@media (max-width: 900px) { .wh-content { grid-template-columns: 1fr; } }
.wh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px; }
.wh-card-title { font-size: 15px; font-weight: 700; margin: 0 0 12px; color: #111827; }
.wh-empty-state { text-align: center; padding: 24px; color: #9CA3AF; font-size: 13px; }
.wh-obj-tree { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.wh-obj-node { padding: 4px 0; }
.wh-obj-row { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 6px; cursor: pointer; }
.wh-obj-row:hover { background: #F9FAFB; }
.wh-obj-row-active { background: #EFF6FF; }
.wh-obj-title { font-size: 13px; font-weight: 600; color: #111827; flex: 1; min-width: 120px; }
.wh-obj-progress { font-size: 12px; font-weight: 700; color: #2563EB; width: 40px; text-align: right; }
.wh-kr-list { list-style: none; margin: 2px 0 4px; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.wh-kr-item { font-size: 11px; color: #6B7280; padding-left: 8px; }
.wh-obj-children { list-style: none; margin: 0; padding: 0; }
.wh-panel-header { display: flex; align-items: center; justify-content: space-between; }
.wh-icon-btn { background: none; border: none; cursor: pointer; color: #9CA3AF; font-size: 14px; }
.wh-panel-section { margin-top: 14px; padding-top: 14px; border-top: 1px solid #F3F4F6; }
.wh-panel-section:first-of-type { margin-top: 0; padding-top: 0; border-top: none; }
.wh-panel-label { font-size: 11px; text-transform: uppercase; color: #9CA3AF; margin: 0 0 6px; font-weight: 600; }
.wh-muted { font-size: 12px; color: #9CA3AF; margin: 0; }
.wh-agg-value { font-size: 14px; font-weight: 700; color: #111827; margin: 0; }
.wh-link-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.wh-link-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #F9FAFB; border-radius: 6px; padding: 8px 10px; }
.wh-link-type { font-size: 12px; font-weight: 600; color: #111827; margin: 0; }
.wh-link-value { font-size: 11px; color: #6B7280; margin: 0; }
.wh-link-form { display: flex; flex-direction: column; gap: 8px; }
.wh-error-text { font-size: 12px; color: #DC2626; margin: 6px 0 0; }
.wh-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-danger-outline { background: #fff; color: #DC2626; border: 1px solid #FCA5A5; flex-shrink: 0; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; white-space: nowrap; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-amber { background: #FEF3C7; color: #92400E; }
.wh-badge-red { background: #FEE2E2; color: #991B1B; }
.wh-badge-blue { background: #DBEAFE; color: #1E40AF; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-badge-gray { background: #E5E7EB; color: #4B5563; }
.wh-panel-placeholder { display: flex; align-items: center; justify-content: center; min-height: 120px; }
</style>
