<template>
  <AppLayout>
    <Head title="KPI sectoriels textile/EPI" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <Link href="/strategy" class="wh-back-link">← Cockpit</Link>
          <h1 class="wh-page-title">KPI sectoriels textile/EPI</h1>
          <p class="wh-page-subtitle">Indicateurs spécifiques à la confection/EPI, calculés en direct sur les fiches de chiffrage et commandes de production réelles</p>
        </div>
      </div>

      <div class="wh-card">
        <h2 class="wh-card-title">Marge moyenne sur coût de revient</h2>
        <div v-if="margin.overall.sheet_count === 0" class="wh-empty-state">Aucune fiche de chiffrage chiffrée/approuvée pour l'instant.</div>
        <template v-else>
          <div class="wh-kpi-row">
            <div class="wh-kpi"><div class="wh-kpi-label">Marge moyenne globale</div><div class="wh-kpi-value">{{ margin.overall.avg_margin_percent }}%</div></div>
            <div class="wh-kpi"><div class="wh-kpi-label">Fiches prises en compte</div><div class="wh-kpi-value">{{ margin.overall.sheet_count }}</div></div>
          </div>
          <table class="wh-table">
            <thead><tr><th>Famille</th><th>Marge moyenne</th><th>Fiches</th></tr></thead>
            <tbody>
              <tr v-for="f in margin.by_family" :key="f.family">
                <td>{{ f.family }}</td>
                <td :style="{ color: f.avg_margin_percent >= 0 ? '#059669' : '#DC2626' }">{{ f.avg_margin_percent }}%</td>
                <td>{{ f.sheet_count }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <div class="wh-card">
        <h2 class="wh-card-title">Structure moyenne du coût de revient</h2>
        <div v-if="cost_structure.sheet_count === 0" class="wh-empty-state">Aucune donnée disponible.</div>
        <table v-else class="wh-table">
          <thead><tr><th>Poste</th><th>Part du coût de revient</th></tr></thead>
          <tbody>
            <tr v-for="(pct, label) in cost_structure.structure" :key="label">
              <td>{{ label }}</td>
              <td>
                <div class="wh-bar-track"><div class="wh-bar-fill" :style="{ width: pct + '%' }" /></div>
                {{ pct }}%
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="wh-card">
        <h2 class="wh-card-title">Délai de sous-traitance</h2>
        <div v-if="lead_time.overall.delivered_count === 0" class="wh-empty-state">Aucune commande de production livrée pour l'instant.</div>
        <template v-else>
          <div class="wh-kpi-row">
            <div class="wh-kpi"><div class="wh-kpi-label">Délai moyen</div><div class="wh-kpi-value">{{ lead_time.overall.avg_lead_time_days }} j</div></div>
            <div class="wh-kpi"><div class="wh-kpi-label">Respect des délais</div><div class="wh-kpi-value">{{ lead_time.overall.on_time_percent }}%</div></div>
            <div class="wh-kpi"><div class="wh-kpi-label">Commandes livrées</div><div class="wh-kpi-value">{{ lead_time.overall.delivered_count }}</div></div>
          </div>
          <table class="wh-table">
            <thead><tr><th>Sous-traitant</th><th>Délai moyen</th><th>Respect délai</th><th>Livrées</th></tr></thead>
            <tbody>
              <tr v-for="s in lead_time.by_subcontractor" :key="s.subcontractor">
                <td>{{ s.subcontractor }}</td>
                <td>{{ s.avg_lead_time_days }} j</td>
                <td>{{ s.on_time_percent }}%</td>
                <td>{{ s.delivered_count }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <div class="wh-card">
        <h2 class="wh-card-title">Répartition de la production par famille de produit</h2>
        <div v-if="production_mix.length === 0" class="wh-empty-state">Aucune commande de production en cours.</div>
        <table v-else class="wh-table">
          <thead><tr><th>Famille</th><th>Commandes</th><th>Quantité totale</th></tr></thead>
          <tbody>
            <tr v-for="m in production_mix" :key="m.family">
              <td>{{ m.family }}</td>
              <td>{{ m.order_count }}</td>
              <td>{{ m.total_quantity }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="wh-card">
        <h2 class="wh-card-title">Écart prix matière observé vs chiffré</h2>
        <div v-if="material_variance.compared_count === 0" class="wh-empty-state">
          Aucune observation de prix (Chantier 17) ne correspond encore à une matière chiffrée sur une fiche de chiffrage — l'indicateur se précisera avec l'usage.
        </div>
        <div v-else class="wh-kpi-row">
          <div class="wh-kpi">
            <div class="wh-kpi-label">Écart moyen (observé vs chiffré)</div>
            <div class="wh-kpi-value" :style="{ color: material_variance.avg_variance_percent > 0 ? '#DC2626' : '#059669' }">
              {{ material_variance.avg_variance_percent > 0 ? '+' : '' }}{{ material_variance.avg_variance_percent }}%
            </div>
          </div>
          <div class="wh-kpi"><div class="wh-kpi-label">Observations comparées</div><div class="wh-kpi-value">{{ material_variance.compared_count }}</div></div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  margin: { type: Object, required: true },
  cost_structure: { type: Object, required: true },
  lead_time: { type: Object, required: true },
  production_mix: { type: Array, required: true },
  material_variance: { type: Object, required: true },
})
</script>

<style scoped>
.wh-page { max-width: 1000px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.wh-back-link { font-size: 12px; color: #6B7280; text-decoration: none; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 6px 0 2px; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin: 0; }
.wh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px; }
.wh-card-title { font-size: 15px; font-weight: 700; margin: 0 0 12px; color: #111827; }
.wh-kpi-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.wh-kpi { flex: 1; min-width: 160px; padding: 14px 16px; border: 1px solid #E5E7EB; border-radius: 8px; }
.wh-kpi-label { font-size: 11px; text-transform: uppercase; color: #6B7280; margin-bottom: 4px; }
.wh-kpi-value { font-size: 18px; font-weight: 700; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 8px 10px; border-bottom: 1px solid #E5E7EB; }
.wh-table td { padding: 8px 10px; border-bottom: 1px solid #F3F4F6; }
.wh-bar-track { display: inline-block; width: 120px; height: 6px; background: #F3F4F6; border-radius: 3px; overflow: hidden; vertical-align: middle; margin-right: 8px; }
.wh-bar-fill { height: 100%; background: #2E5BE8; }
.wh-empty-state { text-align: center; padding: 30px; color: #9CA3AF; font-size: 13px; }
</style>
