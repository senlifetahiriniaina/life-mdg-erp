<template>
  <AppLayout>
    <Head title="Benchmarks sectoriels" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <Link href="/strategy" class="wh-back-link">← Cockpit</Link>
          <h1 class="wh-page-title">Benchmarks sectoriels</h1>
          <p class="wh-page-subtitle">Comparaison P25 / Médiane / P75 par pays et secteur d'activité</p>
        </div>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div class="wh-card">
        <form class="wh-filters" @submit.prevent="applyFilters">
          <input v-model="form.country" class="wh-input" placeholder="Pays (ex: WW, MG, SN...)" />
          <input v-model="form.industry" class="wh-input" placeholder="Secteur (ex: general, retail...)" />
          <input v-model.number="form.year" type="number" class="wh-input" placeholder="Année" />
          <button type="submit" class="wh-btn wh-btn-primary">Filtrer</button>
          <button type="button" class="wh-btn wh-btn-secondary" @click="resetFilters">Réinitialiser</button>
        </form>
      </div>

      <div class="wh-card">
        <div class="wh-table-wrap">
          <table class="wh-table">
            <thead>
              <tr>
                <th>Ratio</th>
                <th>Secteur</th>
                <th>Pays</th>
                <th>P25</th>
                <th>Médiane</th>
                <th>P75</th>
                <th>Année</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(b, i) in benchmarks" :key="b.id ?? i">
                <td class="wh-ratio-cell">{{ ratioLabel(b.ratio_name) }}</td>
                <td>{{ b.industry ?? '—' }}</td>
                <td>{{ b.country ?? '—' }}</td>
                <td>{{ formatNum(b.p25) }}</td>
                <td class="wh-median-cell">{{ formatNum(b.median) }}</td>
                <td>{{ formatNum(b.p75) }}</td>
                <td>{{ b.year ?? '—' }}</td>
                <td class="wh-source-cell">{{ b.source ?? '—' }}</td>
              </tr>
              <tr v-if="!benchmarks.length">
                <td colspan="8" class="wh-empty-cell">Aucun benchmark trouvé pour ces filtres.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Strategy', 'view_benchmarks')

const props = defineProps({
  benchmarks: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const form = ref({
  country: props.filters?.country ?? '',
  industry: props.filters?.industry ?? '',
  year: props.filters?.year ?? '',
})

function applyFilters() {
  const params = {}
  if (form.value.country) params.country = form.value.country
  if (form.value.industry) params.industry = form.value.industry
  if (form.value.year) params.year = form.value.year
  router.get('/strategy/benchmarks', params, { preserveState: true, preserveScroll: true })
}

function resetFilters() {
  form.value = { country: '', industry: '', year: '' }
  router.get('/strategy/benchmarks', {}, { preserveState: true, preserveScroll: true })
}

function ratioLabel(key) {
  return (key || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatNum(v) {
  if (v == null) return '—'
  const n = Number(v)
  return Number.isInteger(n) ? new Intl.NumberFormat('fr-FR').format(n) : new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(n)
}
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.wh-back-link { font-size: 12px; color: #6B7280; text-decoration: none; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 6px 0 2px; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin: 0; }
.wh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px; }
.wh-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-table-wrap { overflow-x: auto; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; white-space: nowrap; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; white-space: nowrap; }
.wh-ratio-cell { font-weight: 600; color: #111827; }
.wh-median-cell { font-weight: 700; color: #2563EB; }
.wh-source-cell { color: #9CA3AF; font-size: 11px; white-space: normal; }
.wh-empty-cell { text-align: center; padding: 30px; color: #9CA3AF; }
</style>
