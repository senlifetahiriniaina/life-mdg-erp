<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Narrations IA — Résumés Automatiques des Tableaux de Bord</h1>
        <p class="text-surface-500 text-sm mt-1">L'IA analyse vos KPIs et génère des commentaires en langage naturel pour vos rapports</p>
      </div>
      <Button label="Générer les narrations" icon="pi pi-sparkles" :loading="generating" @click="generate" v-if="canManage" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="space-y-4">
      <Card v-for="narrative in narratives" :key="narrative.module">
        <template #header>
          <div class="px-4 pt-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-xl">{{ narrative.icon }}</span>
              <span class="font-semibold">{{ narrative.module }}</span>
              <Tag :value="narrative.period" severity="secondary" size="small" />
            </div>
            <div class="flex items-center gap-2">
              <Tag :value="narrative.sentiment" :severity="{ Positif: 'success', Neutre: 'info', Attention: 'warn', Critique: 'danger' }[narrative.sentiment]" size="small" />
              <Button icon="pi pi-copy" size="small" text title="Copier" />
              <Button icon="pi pi-refresh" size="small" text title="Régénérer" :loading="narrative.regenerating" @click="regenerateNarrative(narrative)" />
            </div>
          </div>
        </template>
        <template #content>
          <div class="prose prose-sm max-w-none">
            <p class="text-surface-700 leading-relaxed">{{ narrative.text }}</p>
          </div>
          <div class="flex gap-3 mt-3 flex-wrap">
            <div v-for="kpi in narrative.keyKPIs" :key="kpi.label" class="flex items-center gap-1 text-xs px-2 py-1 rounded-full" :class="kpi.trend > 0 ? 'bg-green-50 text-green-700' : kpi.trend < 0 ? 'bg-red-50 text-red-700' : 'bg-surface-100 text-surface-600'">
              {{ kpi.trend > 0 ? '↑' : kpi.trend < 0 ? '↓' : '→' }} {{ kpi.label }}: {{ kpi.value }}
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['analyst','admin','super-admin'].includes(r)))

const generating = ref(false)
const generate = async () => { generating.value = true; await new Promise(r => setTimeout(r, 2000)); generating.value = false }
const regenerateNarrative = async (n) => { n.regenerating = true; await new Promise(r => setTimeout(r, 1500)); n.regenerating = false }

const stats = [
  { label: 'Modules analysés', value: '8', color: 'text-blue-600' },
  { label: 'Narrations générées', value: '24', color: 'text-green-600' },
  { label: 'KPIs surveillés', value: '142', color: 'text-purple-600' },
  { label: 'Dernière mise à jour', value: 'il y a 4h', color: 'text-orange-600' },
]

const narratives = ref([
  {
    module: 'Performance Commerciale', icon: '💼', period: 'Mai 2026', sentiment: 'Positif', regenerating: false,
    text: `Le mois de mai 2026 marque une performance commerciale exceptionnelle avec un chiffre d'affaires en hausse de +18% par rapport à avril, portant le total mensuel à 284 millions FCFA. Cette croissance est principalement tirée par le segment B2B (+24%) grâce aux contrats signés avec Ecobank et MTN Cameroun. Le pipeline commercial reste solide avec 42 opportunités actives représentant un potentiel de 380M FCFA. Cependant, le taux de conversion leads-to-deal s'est légèrement contracté à 28% (vs 32% en avril), signalant un besoin de renforcement du processus de qualification. L'équipe Côte d'Ivoire affiche la meilleure progression avec +31% de CA.`,
    keyKPIs: [{ label: 'CA', value: '284M XOF', trend: 1 }, { label: 'Conversion', value: '28%', trend: -1 }, { label: 'Pipeline', value: '380M XOF', trend: 1 }],
  },
  {
    module: 'Stocks & Logistique', icon: '📦', period: 'Semaine 21', sentiment: 'Attention', regenerating: false,
    text: `La situation des stocks présente des signaux contrastés cette semaine. D'un côté, le taux de rotation global s'améliore à 4.2x (vs 3.8x la semaine précédente), signe d'une meilleure fluidité des approvisionnements. De l'autre, 8 références clés (dont Laptop Pro 15" et SSD 1To) sont en situation de stock critique avec moins de 3 semaines de couverture. L'alerte principale concerne le fournisseur Shenzhen Electronics dont le délai de livraison s'est allongé à 45 jours (+21j vs contrat), risquant une rupture de stock sur 3 références high-runners d'ici le 15 juin. Un plan d'approvisionnement d'urgence via Lagos TechParts a été initié.`,
    keyKPIs: [{ label: 'Rotation', value: '4.2x', trend: 1 }, { label: 'Références critiques', value: '8', trend: -1 }, { label: 'Couverture moy.', value: '6.2 sem', trend: 0 }],
  },
  {
    module: 'RH & Productivité', icon: '👥', period: 'Mai 2026', sentiment: 'Neutre', regenerating: false,
    text: `Les indicateurs RH de mai sont globalement stables avec un taux d'absentéisme maintenu à 3.2% (en ligne avec la norme du secteur de 3-4%). La masse salariale totale de 28.4M FCFA est conforme au budget prévisionnel à ±1.2%. Point d'attention : le département Commercial affiche un taux de turnover de 8.5% sur 12 mois glissants, supérieur à la cible de 6%. L'IA identifie une corrélation probable avec l'absence de plan de carrière structuré pour les commerciaux juniors. Les formations LMS progressent bien avec 84% du plan de formation complété (cible annuelle 90%). Recrutements en cours : 3 postes commerciaux, 1 ingénieur IT.`,
    keyKPIs: [{ label: 'Absentéisme', value: '3.2%', trend: 0 }, { label: 'Turnover', value: '8.5%', trend: -1 }, { label: 'Formation', value: '84%', trend: 1 }],
  },
])
</script>
