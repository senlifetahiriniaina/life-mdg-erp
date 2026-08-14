<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sources de données</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Connectez et synchronisez vos sources de données externes</p>
      </div>
      <Button v-if="canCreate" label="Ajouter une source" icon="pi pi-plus" @click="showAddDialog = true" />
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card v-for="stat in stats" :key="stat.label">
        <template #content>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ stat.label }}</p>
              <p class="text-2xl font-bold mt-1" :class="stat.color">{{ stat.value }}</p>
              <p class="text-xs mt-1 text-gray-400">{{ stat.sub }}</p>
            </div>
            <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="stat.bg">
              <i :class="[stat.icon, 'text-xl', stat.iconColor]"></i>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Data Sources Table -->
    <Card>
      <template #header>
        <div class="flex items-center justify-between px-4 pt-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Connexions</h2>
          <div class="flex gap-2">
            <InputText v-model="searchQuery" placeholder="Rechercher..." class="w-56" size="small" />
            <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
              placeholder="Statut" class="w-40" size="small" />
          </div>
        </div>
      </template>
      <template #content>
        <DataTable :value="filteredSources" paginator :rows="10" :rowsPerPageOptions="[10,25]"
          stripedRows class="text-sm">
          <Column field="name" header="Source" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="data.iconBg">
                  <i :class="[data.icon, 'text-lg', data.iconColor]"></i>
                </div>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">{{ data.name }}</p>
                  <p class="text-xs text-gray-500">{{ data.host }}</p>
                </div>
              </div>
            </template>
          </Column>
          <Column header="Type">
            <template #body="{ data }">
              <Tag :value="data.type" severity="info" />
            </template>
          </Column>
          <Column header="Statut">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full" :class="getStatusDot(data.status)"></div>
                <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
              </div>
            </template>
          </Column>
          <Column field="tables" header="Tables" sortable>
            <template #body="{ data }">
              <span class="font-semibold text-blue-600">{{ data.tables }}</span>
              <span class="text-xs text-gray-400 ml-1">tables</span>
            </template>
          </Column>
          <Column field="records" header="Enregistrements" sortable>
            <template #body="{ data }">
              <span class="text-sm">{{ data.records.toLocaleString('fr-FR') }}</span>
            </template>
          </Column>
          <Column field="lastSync" header="Dernière synchro" sortable />
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-refresh" size="small" severity="success" text v-tooltip="'Synchroniser maintenant'" @click="syncSource(data)" :loading="syncingId === data.id" />
                <Button icon="pi pi-wifi" size="small" severity="info" text v-tooltip="'Tester la connexion'" @click="testSource(data)" />
                <Button icon="pi pi-table" size="small" severity="secondary" text v-tooltip="'Parcourir les tables'" @click="browseSource(data)" />
                <Button icon="pi pi-pencil" size="small" severity="secondary" text v-tooltip="'Modifier'" @click="editSource(data)" />
                <Button icon="pi pi-trash" size="small" severity="danger" text v-tooltip="'Supprimer'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Add Source Dialog -->
    <Dialog v-model:visible="showAddDialog" header="Ajouter une source de données" :style="{ width: '640px' }" modal>
      <div class="space-y-4 py-2">
        <!-- Source type selection -->
        <div v-if="!form.type">
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Choisissez le type de source</p>
          <div class="grid grid-cols-3 gap-3">
            <div v-for="src in sourceTypes" :key="src.type"
              class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 cursor-pointer hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all text-center"
              @click="form.type = src.type" role="button" tabindex="0" @keydown.enter.prevent="form.type = src.type">
              <div class="w-12 h-12 mx-auto rounded-xl flex items-center justify-center mb-2" :class="src.iconBg">
                <i :class="[src.icon, 'text-2xl', src.iconColor]"></i>
              </div>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ src.label }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ src.description }}</p>
            </div>
          </div>
        </div>

        <!-- Connection form -->
        <div v-else class="space-y-4">
          <div class="flex items-center gap-2 mb-4">
            <Button icon="pi pi-arrow-left" size="small" severity="secondary" text @click="form.type = ''" />
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Configuration — {{ form.type }}</h3>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom de la connexion *</label>
              <InputText v-model="form.name" class="w-full" placeholder="Ex: Base de production" />
            </div>
            <div v-if="needsHost">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hôte</label>
              <InputText v-model="form.host" class="w-full" placeholder="db.exemple.com" />
            </div>
            <div v-if="needsPort">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Port</label>
              <InputText v-model="form.port" class="w-full" :placeholder="getDefaultPort(form.type)" />
            </div>
            <div v-if="needsDatabase">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base de données</label>
              <InputText v-model="form.database" class="w-full" placeholder="nom_base" />
            </div>
            <div v-if="needsCredentials">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Utilisateur</label>
              <InputText v-model="form.username" class="w-full" placeholder="utilisateur" />
            </div>
            <div v-if="needsCredentials">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mot de passe</label>
              <InputText v-model="form.password" class="w-full" type="password" placeholder="••••••••" />
            </div>
            <div v-if="form.type === 'API REST'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL de base</label>
              <InputText v-model="form.apiUrl" class="w-full" placeholder="https://api.exemple.com/v1" />
            </div>
            <div v-if="form.type === 'API REST'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Clé API</label>
              <InputText v-model="form.apiKey" class="w-full" type="password" placeholder="sk-..." />
            </div>
          </div>
          <!-- Sync schedule -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fréquence de synchro</label>
              <Select v-model="form.syncFrequency" :options="['Temps réel', 'Toutes les heures', 'Quotidienne', 'Hebdomadaire', 'Manuelle']" class="w-full" />
            </div>
            <div class="flex items-end">
              <Button label="Tester la connexion" icon="pi pi-wifi" severity="secondary" class="w-full" @click="testNewSource" :loading="testingNew" />
            </div>
          </div>
          <!-- Test result -->
          <div v-if="testNewResult" class="p-3 rounded-lg flex items-center gap-2"
            :class="testNewResult.success ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
            <i :class="testNewResult.success ? 'pi pi-check-circle text-green-500' : 'pi pi-times-circle text-red-500'"></i>
            <p class="text-sm" :class="testNewResult.success ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">{{ testNewResult.message }}</p>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="closeAddDialog" />
        <Button v-if="form.type" label="Ajouter et synchroniser" icon="pi pi-check" @click="addSource" :loading="adding" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const { isElevated } = useRoleAccess()
const canCreate = computed(() => isElevated.value)

const showAddDialog = ref(false)
const searchQuery = ref('')
const filterStatus = ref('')
const syncingId = ref<number | null>(null)
const testingNew = ref(false)
const adding = ref(false)
const testNewResult = ref<{ success: boolean; message: string } | null>(null)

const stats = ref([
  { label: 'Sources connectées', value: '7', sub: '1 en erreur', icon: 'pi pi-database', bg: 'bg-blue-100', iconColor: 'text-blue-600', color: 'text-blue-600' },
  { label: 'Dernière synchro', value: 'il y a 12 min', sub: 'Source : ERP Principal', icon: 'pi pi-refresh', bg: 'bg-green-100', iconColor: 'text-green-600', color: 'text-green-600' },
  { label: 'Tables disponibles', value: '284', sub: 'sur 7 connexions', icon: 'pi pi-table', bg: 'bg-purple-100', iconColor: 'text-purple-600', color: 'text-purple-600' },
  { label: 'Enregistrements total', value: '4,2M', sub: 'mis à jour quotidiennement', icon: 'pi pi-list', bg: 'bg-orange-100', iconColor: 'text-orange-600', color: 'text-orange-600' },
])

const sources = ref([
  { id: 1, name: 'ERP Principal', host: 'db-prod.widehalo.internal', icon: 'pi pi-server', iconBg: 'bg-blue-100', iconColor: 'text-blue-600', type: 'PostgreSQL', status: 'Connecté', tables: 142, records: 2800000, lastSync: 'Auj. 09h48' },
  { id: 2, name: 'Reporting CRM', host: 'crm-db.widehalo.internal', icon: 'pi pi-server', iconBg: 'bg-green-100', iconColor: 'text-green-600', type: 'MySQL', status: 'Connecté', tables: 38, records: 480000, lastSync: 'Auj. 08h00' },
  { id: 3, name: 'Import données Sénégal', host: 'data_senegal_q1.xlsx', icon: 'pi pi-file-excel', iconBg: 'bg-emerald-100', iconColor: 'text-emerald-600', type: 'Excel', status: 'Connecté', tables: 6, records: 15400, lastSync: '15/05/2026' },
  { id: 4, name: 'API Salesforce', host: 'login.salesforce.com', icon: 'pi pi-cloud', iconBg: 'bg-sky-100', iconColor: 'text-sky-600', type: 'Salesforce', status: 'Connecté', tables: 22, records: 95000, lastSync: 'Auj. 07h00' },
  { id: 5, name: 'Google Sheets Analytics', host: 'docs.google.com', icon: 'pi pi-table', iconBg: 'bg-yellow-100', iconColor: 'text-yellow-600', type: 'Google Sheets', status: 'En synchro', tables: 4, records: 8700, lastSync: 'En cours...' },
  { id: 6, name: 'API Logistique externe', host: 'api.logistique-partner.com', icon: 'pi pi-send', iconBg: 'bg-purple-100', iconColor: 'text-purple-600', type: 'API REST', status: 'Connecté', tables: 8, records: 320000, lastSync: 'Hier 23h00' },
  { id: 7, name: 'Ancienne base MySQL', host: 'legacy-db.widehalo.net', icon: 'pi pi-database', iconBg: 'bg-red-100', iconColor: 'text-red-600', type: 'MySQL', status: 'Erreur', tables: 0, records: 0, lastSync: 'Échec 12/05/2026' },
])

const sourceTypes = ref([
  { type: 'PostgreSQL', label: 'PostgreSQL', description: 'Base de données relationnelle', icon: 'pi pi-server', iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { type: 'MySQL', label: 'MySQL / MariaDB', description: 'Base de données SQL', icon: 'pi pi-database', iconBg: 'bg-orange-100', iconColor: 'text-orange-600' },
  { type: 'Excel', label: 'Excel / CSV', description: 'Fichiers tabulaires', icon: 'pi pi-file-excel', iconBg: 'bg-emerald-100', iconColor: 'text-emerald-600' },
  { type: 'API REST', label: 'API REST', description: 'Endpoint HTTP/JSON', icon: 'pi pi-send', iconBg: 'bg-purple-100', iconColor: 'text-purple-600' },
  { type: 'Google Sheets', label: 'Google Sheets', description: 'Feuilles de calcul Google', icon: 'pi pi-table', iconBg: 'bg-yellow-100', iconColor: 'text-yellow-600' },
  { type: 'Salesforce', label: 'Salesforce', description: 'CRM Salesforce Cloud', icon: 'pi pi-cloud', iconBg: 'bg-sky-100', iconColor: 'text-sky-600' },
])

const statusOptions = ref([
  { label: 'Tous les statuts', value: '' },
  { label: 'Connecté', value: 'Connecté' },
  { label: 'Erreur', value: 'Erreur' },
  { label: 'En synchro', value: 'En synchro' },
])

const form = ref({ type: '', name: '', host: '', port: '', database: '', username: '', password: '', apiUrl: '', apiKey: '', syncFrequency: 'Quotidienne' })

const needsHost = computed(() => ['PostgreSQL','MySQL','Salesforce'].includes(form.value.type))
const needsPort = computed(() => ['PostgreSQL','MySQL'].includes(form.value.type))
const needsDatabase = computed(() => ['PostgreSQL','MySQL'].includes(form.value.type))
const needsCredentials = computed(() => ['PostgreSQL','MySQL'].includes(form.value.type))

const filteredSources = computed(() => {
  let list = sources.value
  if (searchQuery.value) list = list.filter(s => s.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
  if (filterStatus.value) list = list.filter(s => s.status === filterStatus.value)
  return list
})

function getStatusDot(status: string) {
  const map: Record<string, string> = { 'Connecté': 'bg-green-500', 'Erreur': 'bg-red-500', 'En synchro': 'bg-yellow-500' }
  return map[status] ?? 'bg-gray-400'
}
function getStatusSeverity(status: string) {
  const map: Record<string, string> = { 'Connecté': 'success', 'Erreur': 'danger', 'En synchro': 'warn' }
  return map[status] ?? 'secondary'
}
function getDefaultPort(type: string) {
  const ports: Record<string, string> = { PostgreSQL: '5432', MySQL: '3306' }
  return ports[type] ?? ''
}
function syncSource(source: any) { syncingId.value = source.id; setTimeout(() => { syncingId.value = null }, 2000) }
function testSource(source: any) { /* Test */ }
function browseSource(source: any) { /* Browse */ }
function editSource(source: any) { /* Edit */ }
function testNewSource() {
  testingNew.value = true
  testNewResult.value = null
  setTimeout(() => {
    testingNew.value = false
    testNewResult.value = { success: true, message: 'Connexion établie avec succès — 38 tables détectées.' }
  }, 2000)
}
function addSource() { adding.value = true; setTimeout(() => { adding.value = false; closeAddDialog() }, 1500) }
function closeAddDialog() { showAddDialog.value = false; form.value = { type: '', name: '', host: '', port: '', database: '', username: '', password: '', apiUrl: '', apiKey: '', syncFrequency: 'Quotidienne' }; testNewResult.value = null }
</script>
