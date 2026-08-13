<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Éditeur SQL</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Interrogez directement vos sources de données avec SQL</p>
      </div>
      <div class="flex gap-2">
        <Button label="Requêtes sauvegardées" icon="pi pi-bookmark" severity="secondary" @click="showSavedDrawer = true" />
        <Button label="Historique" icon="pi pi-history" severity="secondary" @click="showHistoryDrawer = true" />
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- Left Sidebar: Schema tree -->
      <div class="col-span-12 md:col-span-3">
        <Card style="min-height: 520px;">
          <template #header>
            <div class="px-4 pt-4">
              <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Schéma de base de données</h3>
              <div class="mt-2 mb-1">
                <Select v-model="selectedConnection" :options="connections" optionLabel="name"
                  placeholder="Connexion..." class="w-full mb-2" size="small" />
                <InputText v-model="schemaSearch" placeholder="Filtrer tables..." class="w-full" size="small" />
              </div>
            </div>
          </template>
          <template #content>
            <div class="space-y-0.5 overflow-y-auto" style="max-height: 420px;">
              <div v-for="table in filteredTables" :key="table.name">
                <div
                  class="flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 text-sm"
                  @click="table.expanded = !table.expanded" role="button" tabindex="0" @keydown.enter.prevent="table.expanded = !table.expanded">
                  <i :class="table.expanded ? 'pi pi-chevron-down' : 'pi pi-chevron-right'" class="text-xs text-gray-400 flex-shrink-0"></i>
                  <i class="pi pi-table text-xs text-blue-500 flex-shrink-0"></i>
                  <span class="font-medium text-gray-800 dark:text-gray-200 truncate flex-1">{{ table.name }}</span>
                  <Tag :value="table.rows" severity="secondary" size="small" class="flex-shrink-0 text-xs" />
                </div>
                <div v-if="table.expanded" class="ml-6 space-y-0.5 mb-1">
                  <div v-for="col in table.columns" :key="col.name"
                    class="flex items-center gap-2 px-2 py-1 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 text-xs"
                    @click="insertToken(`${table.name}.${col.name}`)" role="button" tabindex="0" @keydown.enter.prevent="insertToken(`${table.name}.${col.name}`)">
                    <i :class="col.pk ? 'pi pi-key text-yellow-500' : col.fk ? 'pi pi-link text-blue-400' : 'pi pi-minus text-gray-300'" class="text-xs flex-shrink-0"></i>
                    <span class="text-gray-700 dark:text-gray-300 truncate">{{ col.name }}</span>
                    <span class="ml-auto text-gray-400 font-mono flex-shrink-0">{{ col.type }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Saved queries card -->
        <Card class="mt-4">
          <template #header>
            <div class="px-4 pt-4 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Requêtes sauvegardées</h3>
              <Button icon="pi pi-external-link" size="small" text severity="secondary" @click="showSavedDrawer = true" />
            </div>
          </template>
          <template #content>
            <div class="space-y-2">
              <div v-for="q in savedQueries.slice(0, 3)" :key="q.name"
                class="p-2 rounded border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                @click="loadQuery(q)" role="button" tabindex="0" @keydown.enter.prevent="loadQuery(q)">
                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ q.name }}</div>
                <div class="text-xs text-gray-400 mt-0.5">{{ q.date }}</div>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Editor + Results -->
      <div class="col-span-12 md:col-span-9 space-y-4">
        <Card>
          <template #header>
            <div class="px-4 pt-4 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Select v-model="activeQueryTab" :options="queryTabs" optionLabel="name" optionValue="id"
                  class="w-48" size="small" />
                <Button icon="pi pi-plus" size="small" severity="secondary" text v-tooltip="'Nouvel onglet'" @click="addQueryTab" />
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 hidden md:block">Ctrl+Entrée pour exécuter</span>
                <Button label="Formater SQL" icon="pi pi-align-left" size="small" severity="secondary" @click="formatQuery" />
                <Button label="Enregistrer" icon="pi pi-save" size="small" severity="secondary" @click="showSaveDialog = true" />
                <Button label="Exécuter" icon="pi pi-play" size="small" @click="runQuery" :loading="running" />
              </div>
            </div>
          </template>
          <template #content>
            <!-- Dark SQL editor -->
            <div class="relative rounded-lg overflow-hidden">
              <!-- Line numbers gutter -->
              <div class="absolute left-0 top-0 bottom-0 flex flex-col items-end pr-2 pt-3 bg-gray-900 select-none z-10"
                style="width: 40px; min-height: 220px;">
                <span v-for="n in lineCount" :key="n" class="text-gray-500 text-xs leading-6 font-mono">{{ n }}</span>
              </div>
              <textarea
                v-model="sqlQuery"
                rows="10"
                class="w-full font-mono text-sm resize-y focus:outline-none focus:ring-1 focus:ring-blue-500"
                style="background-color: var(--slate-800); color: var(--slate-200); padding: 0.75rem 0.75rem 0.75rem 48px; min-height: 220px; line-height: 1.5rem;"
                placeholder="SELECT * FROM ventes WHERE date >= '2026-01-01' LIMIT 100;"
                @keydown.ctrl.enter.prevent="runQuery"
              ></textarea>
            </div>
          </template>
        </Card>

        <!-- Stats bar (shown after query runs) -->
        <div v-if="lastRunTime !== null"
          class="flex flex-wrap items-center gap-4 px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg text-xs text-gray-600 dark:text-gray-400">
          <div class="flex items-center gap-1.5">
            <i class="pi pi-list text-blue-500"></i>
            <span><strong class="text-blue-600">{{ queryResults.length }}</strong> lignes retournées</span>
          </div>
          <div class="flex items-center gap-1.5">
            <i class="pi pi-clock text-green-500"></i>
            <span>Exécuté en <strong class="text-green-600">{{ lastRunTime }}ms</strong></span>
          </div>
          <div class="flex items-center gap-1.5">
            <i class="pi pi-table text-purple-500"></i>
            <span>Tables: <strong class="text-purple-600">{{ usedTables }}</strong></span>
          </div>
          <div class="ml-auto flex gap-2">
            <Button label="CSV" icon="pi pi-download" size="small" severity="secondary" outlined />
            <Button label="Excel" icon="pi pi-file-excel" size="small" severity="secondary" outlined />
          </div>
        </div>

        <!-- Results DataTable -->
        <Card v-if="hasResults">
          <template #header>
            <div class="px-4 pt-4 flex items-center justify-between">
              <span class="text-sm font-semibold text-gray-900 dark:text-white">Résultats ({{ queryResults.length }} lignes)</span>
              <Tag :value="`${lastRunTime}ms`" severity="success" />
            </div>
          </template>
          <template #content>
            <DataTable :value="queryResults" scrollable scrollHeight="300px" size="small" stripedRows>
              <Column v-for="col in resultColumns" :key="col" :field="col" :header="col" sortable>
                <template #body="{ data }">
                  <span class="font-mono text-xs">{{ data[col] }}</span>
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>

        <!-- Error panel -->
        <Card v-if="queryError">
          <template #content>
            <div class="flex items-start gap-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
              <i class="pi pi-times-circle text-red-500 text-lg mt-0.5 flex-shrink-0"></i>
              <div>
                <p class="text-sm font-semibold text-red-700 dark:text-red-300">Erreur SQL</p>
                <pre class="text-xs text-red-600 dark:text-red-400 mt-1 font-mono whitespace-pre-wrap">{{ queryError }}</pre>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- History Drawer -->
    <Drawer v-model:visible="showHistoryDrawer" position="right" header="Historique des requêtes" style="width: 460px;">
      <div class="space-y-3">
        <div v-for="h in queryHistory" :key="h.id"
          class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
          @click="loadHistoryQuery(h)" role="button" tabindex="0" @keydown.enter.prevent="loadHistoryQuery(h)">
          <i class="pi pi-clock text-gray-400 text-sm mt-0.5 flex-shrink-0"></i>
          <div class="flex-1 min-w-0">
            <pre class="text-xs font-mono text-gray-700 dark:text-gray-300 truncate">{{ h.sql }}</pre>
            <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
              <span>{{ h.date }}</span>
              <span>{{ h.rows }} lignes</span>
              <span>{{ h.duration }}ms</span>
            </div>
          </div>
          <Tag :value="h.status" :severity="h.status === 'OK' ? 'success' : 'danger'" size="small" />
        </div>
      </div>
    </Drawer>

    <!-- Saved Queries Drawer -->
    <Drawer v-model:visible="showSavedDrawer" position="right" header="Requêtes sauvegardées" style="width: 460px;">
      <div class="space-y-3">
        <InputText v-model="savedSearch" placeholder="Rechercher..." class="w-full" size="small" />
        <div class="space-y-2">
          <div v-for="q in filteredSavedQueries" :key="q.name"
            class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-gray-800">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ q.name }}</p>
                <pre class="text-xs font-mono text-gray-500 truncate mt-0.5">{{ q.sql.substring(0, 60) }}...</pre>
                <p class="text-xs text-gray-400 mt-1">{{ q.date }}</p>
              </div>
              <div class="flex gap-1 flex-shrink-0">
                <Button icon="pi pi-play" size="small" text severity="success" v-tooltip="'Charger et exécuter'" @click="loadQuery(q); showSavedDrawer = false" />
                <Button icon="pi pi-trash" size="small" text severity="danger" v-tooltip="'Supprimer'" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </Drawer>

    <!-- Save Dialog -->
    <Dialog v-model:visible="showSaveDialog" header="Sauvegarder la requête" :style="{ width: '400px' }" modal>
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom de la requête *</label>
          <InputText v-model="saveName" class="w-full" placeholder="Ex: Ventes par région" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
          <Textarea v-model="saveDescription" rows="2" class="w-full" placeholder="Description optionnelle" />
        </div>
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <p class="text-sm text-gray-700 dark:text-gray-300">Partager avec l'équipe</p>
          <ToggleSwitch v-model="saveShared" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showSaveDialog = false" />
        <Button label="Sauvegarder" icon="pi pi-save" @click="saveQueryAction" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['bi-analyst', 'reporting-analyst', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const userRoles: string[] = (page.props.auth as any)?.user?.roles ?? []
const canRun = userRoles.some(r => ['super_admin','admin','bi_analyst','reporting_manager'].includes(r))

const showSaveDialog = ref(false)
const showHistoryDrawer = ref(false)
const showSavedDrawer = ref(false)
const saveName = ref('')
const saveDescription = ref('')
const saveShared = ref(false)
const schemaSearch = ref('')
const savedSearch = ref('')
const running = ref(false)
const lastRunTime = ref<number | null>(null)
const hasResults = ref(true)
const queryError = ref<string | null>(null)
const selectedConnection = ref<any>(null)
const activeQueryTab = ref(1)

const sqlQuery = ref(`-- Chiffre d'affaires mensuel par région
SELECT
  p.nom AS produit,
  SUM(v.quantite) AS quantite_totale,
  SUM(v.montant) AS ca_xof,
  ROUND(AVG(v.marge_pct), 2) AS marge_moyenne
FROM ventes v
JOIN produits p ON v.produit_id = p.id
WHERE v.date >= '2026-01-01'
GROUP BY p.nom
ORDER BY ca_xof DESC
LIMIT 20;`)

const lineCount = computed(() => sqlQuery.value.split('\n').length)
const usedTables = computed(() => {
  const matches = sqlQuery.value.match(/FROM\s+(\w+)|JOIN\s+(\w+)/gi) ?? []
  return matches.map(m => m.replace(/FROM\s+|JOIN\s+/i, '')).join(', ')
})

const queryTabs = ref([
  { id: 1, name: 'Requête 1' },
  { id: 2, name: 'CA mensuel' },
  { id: 3, name: 'Stock critique' },
])

const connections = ref([
  { name: 'PostgreSQL — Production' },
  { name: 'PostgreSQL — Reporting' },
  { name: 'MySQL — CRM' },
])

const schemaTables = ref([
  {
    name: 'ventes', rows: '84.2k', expanded: true,
    columns: [
      { name: 'id', type: 'uuid', pk: true, fk: false },
      { name: 'date', type: 'date', pk: false, fk: false },
      { name: 'produit_id', type: 'uuid', pk: false, fk: true },
      { name: 'client_id', type: 'uuid', pk: false, fk: true },
      { name: 'quantite', type: 'int', pk: false, fk: false },
      { name: 'montant', type: 'numeric', pk: false, fk: false },
      { name: 'marge_pct', type: 'numeric', pk: false, fk: false },
    ],
  },
  {
    name: 'produits', rows: '2.8k', expanded: false,
    columns: [
      { name: 'id', type: 'uuid', pk: true, fk: false },
      { name: 'nom', type: 'varchar', pk: false, fk: false },
      { name: 'categorie', type: 'varchar', pk: false, fk: false },
      { name: 'prix_ht', type: 'numeric', pk: false, fk: false },
      { name: 'stock', type: 'int', pk: false, fk: false },
    ],
  },
  {
    name: 'clients', rows: '12.4k', expanded: false,
    columns: [
      { name: 'id', type: 'uuid', pk: true, fk: false },
      { name: 'nom', type: 'varchar', pk: false, fk: false },
      { name: 'pays', type: 'char(2)', pk: false, fk: false },
      { name: 'region', type: 'varchar', pk: false, fk: false },
      { name: 'segment', type: 'varchar', pk: false, fk: false },
    ],
  },
  {
    name: 'commandes', rows: '31.1k', expanded: false,
    columns: [
      { name: 'id', type: 'uuid', pk: true, fk: false },
      { name: 'client_id', type: 'uuid', pk: false, fk: true },
      { name: 'date', type: 'date', pk: false, fk: false },
      { name: 'statut', type: 'varchar', pk: false, fk: false },
      { name: 'total_xof', type: 'numeric', pk: false, fk: false },
    ],
  },
  {
    name: 'stocks', rows: '5.6k', expanded: false,
    columns: [
      { name: 'produit_id', type: 'uuid', pk: false, fk: true },
      { name: 'entrepot_id', type: 'uuid', pk: false, fk: true },
      { name: 'quantite', type: 'int', pk: false, fk: false },
      { name: 'valeur_xof', type: 'numeric', pk: false, fk: false },
    ],
  },
  {
    name: 'employes', rows: '847', expanded: false,
    columns: [
      { name: 'id', type: 'uuid', pk: true, fk: false },
      { name: 'nom', type: 'varchar', pk: false, fk: false },
      { name: 'departement_id', type: 'uuid', pk: false, fk: true },
      { name: 'salaire_brut', type: 'numeric', pk: false, fk: false },
      { name: 'date_embauche', type: 'date', pk: false, fk: false },
    ],
  },
])

const savedQueries = ref([
  { name: 'Top produits du mois', date: 'Hier', sql: 'SELECT p.nom, SUM(v.quantite) AS qte, SUM(v.montant) AS ca_xof FROM ventes v JOIN produits p ON v.produit_id = p.id WHERE v.date >= NOW() - INTERVAL \'30 days\' GROUP BY p.nom ORDER BY ca_xof DESC LIMIT 10' },
  { name: 'Clients inactifs 90 jours', date: 'Il y a 3j', sql: 'SELECT id, nom, pays FROM clients WHERE last_order < NOW() - INTERVAL \'90 days\' ORDER BY last_order ASC' },
  { name: 'Stock sous seuil minimal', date: 'Il y a 1 sem', sql: 'SELECT p.nom, p.sku, s.quantite, p.seuil_min FROM stocks s JOIN produits p ON s.produit_id = p.id WHERE s.quantite < p.seuil_min ORDER BY s.quantite ASC' },
  { name: 'Factures impayées > 60j', date: 'Il y a 2 sem', sql: 'SELECT f.id, c.nom, f.montant_ht, EXTRACT(day FROM NOW() - f.date_echeance) AS jours_retard FROM factures f JOIN clients c ON f.client_id = c.id WHERE f.statut = \'impayee\' AND f.date_echeance < NOW() - INTERVAL \'60 days\'' },
  { name: 'Masse salariale département', date: 'Il y a 3 sem', sql: 'SELECT d.nom AS departement, COUNT(e.id) AS effectif, SUM(e.salaire_brut) AS masse_salariale_xof FROM employes e JOIN departements d ON e.departement_id = d.id GROUP BY d.nom ORDER BY masse_salariale_xof DESC' },
])

const queryHistory = ref([
  { id: 1, sql: 'SELECT p.nom, SUM(v.quantite), SUM(v.montant) FROM ventes v JOIN produits p...', date: 'À l\'instant', rows: 20, duration: 87, status: 'OK' },
  { id: 2, sql: 'SELECT COUNT(*) FROM clients WHERE pays = \'SN\'', date: 'Il y a 8 min', rows: 1, duration: 12, status: 'OK' },
  { id: 3, sql: 'SELECT * FROM commandes JOUN clients ON -- syntax error', date: 'Il y a 15 min', rows: 0, duration: 5, status: 'Erreur' },
  { id: 4, sql: 'SELECT produit_id, SUM(quantite) AS total FROM ventes GROUP BY produit_id ORDER BY total DESC', date: 'Il y a 1h', rows: 284, duration: 142, status: 'OK' },
  { id: 5, sql: 'SELECT s.*, p.nom FROM stocks s JOIN produits p ON s.produit_id = p.id WHERE s.quantite < p.seuil_min', date: 'Hier 16h30', rows: 12, duration: 34, status: 'OK' },
])

const resultColumns = ['produit', 'quantite_totale', 'ca_xof', 'marge_moyenne']
const queryResults = ref([
  { produit: 'Riz parfumé 25kg', quantite_totale: 1284, ca_xof: '6 420 000', marge_moyenne: '18.4' },
  { produit: 'Huile palme 5L', quantite_totale: 876, ca_xof: '3 504 000', marge_moyenne: '22.1' },
  { produit: 'Farine blé 50kg', quantite_totale: 642, ca_xof: '2 568 000', marge_moyenne: '15.7' },
  { produit: 'Sucre 1kg', quantite_totale: 2140, ca_xof: '2 140 000', marge_moyenne: '12.3' },
  { produit: 'Savon Marseille', quantite_totale: 1820, ca_xof: '1 820 000', marge_moyenne: '28.9' },
])

const filteredTables = computed(() => {
  if (!schemaSearch.value) return schemaTables.value
  return schemaTables.value.filter(t => t.name.toLowerCase().includes(schemaSearch.value.toLowerCase()))
})
const filteredSavedQueries = computed(() => {
  if (!savedSearch.value) return savedQueries.value
  return savedQueries.value.filter(q => q.name.toLowerCase().includes(savedSearch.value.toLowerCase()))
})

function addQueryTab() {
  const id = Date.now()
  queryTabs.value.push({ id, name: `Requête ${queryTabs.value.length + 1}` })
  activeQueryTab.value = id
}
function insertToken(token: string) { sqlQuery.value += ` ${token}` }
function loadQuery(q: any) { sqlQuery.value = q.sql }
function loadHistoryQuery(h: any) { sqlQuery.value = h.sql; showHistoryDrawer.value = false }
function formatQuery() { sqlQuery.value = sqlQuery.value.trim() }
function runQuery() {
  running.value = true
  hasResults.value = false
  queryError.value = null
  setTimeout(() => {
    running.value = false
    lastRunTime.value = Math.floor(Math.random() * 200) + 30
    hasResults.value = true
    queryHistory.value.unshift({
      id: Date.now(),
      sql: sqlQuery.value.trim().substring(0, 80),
      date: 'À l\'instant',
      rows: queryResults.value.length,
      duration: lastRunTime.value!,
      status: 'OK',
    })
  }, 600)
}
function saveQueryAction() {
  if (saveName.value) {
    savedQueries.value.unshift({ name: saveName.value, date: 'À l\'instant', sql: sqlQuery.value })
  }
  showSaveDialog.value = false
  saveName.value = ''
  saveDescription.value = ''
}
</script>
