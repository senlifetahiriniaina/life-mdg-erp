<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Interrogation en Langage Naturel — BI</h1>
        <p class="text-surface-500 text-sm mt-1">Posez vos questions en français, l'IA génère et exécute la requête SQL automatiquement</p>
      </div>
    </div>

    <Card>
      <template #content>
        <div class="flex gap-3">
          <div class="flex-1">
            <div class="relative">
              <InputText v-model="query" class="w-full pr-12" placeholder="Ex: Quels sont les 5 meilleurs clients par CA ce mois ?" @keyup.enter="runQuery" />
              <Button icon="pi pi-send" class="absolute right-1 top-1/2 -translate-y-1/2" text rounded :loading="loading" @click="runQuery" />
            </div>
            <div class="flex gap-2 mt-2 flex-wrap">
              <span v-for="ex in examples" :key="ex" class="px-3 py-1 bg-surface-100 hover:bg-surface-200 rounded-full text-xs cursor-pointer" @click="query = ex; runQuery()">{{ ex }}</span>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <div v-if="result">
      <Card>
        <template #header><div class="px-4 pt-4 flex items-center justify-between">
          <span class="font-semibold">Résultats</span>
          <div class="flex gap-2">
            <Button icon="pi pi-code" label="SQL" size="small" text @click="showSQL = !showSQL" />
            <Button icon="pi pi-download" label="Export CSV" size="small" outlined />
          </div>
        </div></template>
        <template #content>
          <div v-if="showSQL" class="mb-4 p-3 bg-gray-900 rounded text-green-400 text-xs font-mono overflow-x-auto">{{ result.sql }}</div>
          <div class="text-sm text-surface-500 mb-3">{{ result.rows.length }} résultats · exécuté en {{ result.execTime }}</div>
          <DataTable :value="result.rows" stripedRows>
            <Column v-for="col in result.columns" :key="col" :field="col" :header="col" />
          </DataTable>
        </template>
      </Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Requêtes sauvegardées</div></template>
      <template #content>
        <DataTable :value="savedQueries" stripedRows>
          <Column field="name" header="Nom" />
          <Column field="query" header="Question" />
          <Column field="lastRun" header="Dernier usage" />
          <Column field="createdBy" header="Créé par" />
          <Column header="">
            <template #body="{ data }">
              <Button label="Réexécuter" size="small" text @click="query = data.query; runQuery()" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed} from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'

const page = usePage()
const { isAdmin, isElevated } = useRoleAccess()
const canManage = computed(() => isElevated.value)
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const query = ref('')
const loading = ref(false)
const showSQL = ref(false)
const result = ref(null)

const examples = [
  'Top 5 clients par CA ce mois',
  'Produits en rupture de stock',
  'Tickets support non résolus > 48h',
  'Ventes par région Afrique Ouest',
  'Agents avec le meilleur taux de conversion',
]

const mockResults = {
  'Top 5 clients par CA ce mois': {
    sql: `SELECT c.name AS "Client", SUM(o.total_amount) AS "CA (XOF)"
FROM orders o
JOIN customers c ON c.id = o.customer_id
WHERE o.created_at >= DATE_TRUNC('month', NOW())
GROUP BY c.name
ORDER BY 2 DESC
LIMIT 5;`,
    columns: ['Client', 'CA (XOF)', 'Commandes', 'Ticket moyen'],
    rows: [
      { Client: 'Groupe Sonatel', 'CA (XOF)': '18 450 000', Commandes: '12', 'Ticket moyen': '1 537 500' },
      { Client: 'Ecobank Sénégal', 'CA (XOF)': '14 200 000', Commandes: '8', 'Ticket moyen': '1 775 000' },
      { Client: 'Orange CI', 'CA (XOF)': '11 800 000', Commandes: '15', 'Ticket moyen': '786 667' },
      { Client: 'MTN Cameroun', 'CA (XOF)': '9 500 000', Commandes: '6', 'Ticket moyen': '1 583 333' },
      { Client: 'BCEAO', 'CA (XOF)': '7 200 000', Commandes: '4', 'Ticket moyen': '1 800 000' },
    ],
    execTime: '124ms',
  },
  default: {
    sql: `SELECT * FROM results WHERE condition = true LIMIT 10;`,
    columns: ['Résultat', 'Valeur', 'Date', 'Détail'],
    rows: [
      { Résultat: 'Élément 1', Valeur: '42 000', Date: '2026-05-24', Détail: 'Description' },
      { Résultat: 'Élément 2', Valeur: '38 500', Date: '2026-05-23', Détail: 'Description' },
    ],
    execTime: '98ms',
  },
}

const runQuery = async () => {
  if (!query.value.trim()) return
  loading.value = true
  await new Promise(r => setTimeout(r, 1500))
  result.value = mockResults[query.value] || mockResults.default
  loading.value = false
}

const savedQueries = ref([
  { name: 'CA mensuel par commercial', query: 'CA par commercial ce mois', lastRun: 'il y a 2h', createdBy: 'Directeur commercial' },
  { name: 'Taux de retour produits', query: 'Taux de retour par catégorie ce trimestre', lastRun: 'il y a 1j', createdBy: 'Qualité' },
  { name: 'KPI Support mensuel', query: 'Tickets support non résolus > 48h', lastRun: 'il y a 3j', createdBy: 'Manager support' },
])
</script>
