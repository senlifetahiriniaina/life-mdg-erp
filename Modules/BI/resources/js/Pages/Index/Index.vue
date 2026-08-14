<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Business Intelligence</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilotez votre activité avec des données en temps réel</p>
      </div>
      <div class="flex gap-2">
        <Button label="Nouveau tableau de bord" icon="pi pi-plus" severity="secondary" @click="showDashboardDialog = true" />
        <Button label="Nouveau rapport" icon="pi pi-file-edit" @click="showReportDialog = true" />
      </div>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent dashboards -->
      <div class="lg:col-span-2 space-y-4">
        <Card>
          <template #header>
            <div class="px-4 pt-4 flex items-center justify-between">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Tableaux de bord récents</h2>
              <Button label="Voir tout" icon="pi pi-arrow-right" iconPos="right" size="small" severity="secondary" text />
            </div>
          </template>
          <template #content>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div v-for="db in recentDashboards" :key="db.id"
                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer group">
                <div class="flex items-start justify-between mb-3">
                  <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="db.iconBg">
                    <i :class="[db.icon, 'text-lg', db.iconColor]"></i>
                  </div>
                  <Button icon="pi pi-star" size="small" text
                    :severity="db.starred ? 'warn' : 'secondary'"
                    class="opacity-0 group-hover:opacity-100 transition-opacity" />
                </div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">{{ db.name }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ db.description }}</p>
                <div class="flex items-center justify-between text-xs text-gray-400">
                  <span>{{ db.views }} vues</span>
                  <span>{{ db.lastViewed }}</span>
                </div>
                <div class="mt-2 flex items-center gap-1">
                  <div class="w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-xs font-medium">
                    {{ db.owner[0] }}
                  </div>
                  <span class="text-xs text-gray-500">{{ db.owner }}</span>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Scheduled reports -->
        <Card>
          <template #header>
            <div class="px-4 pt-4 flex items-center justify-between">
              <h2 class="text-base font-semibold text-gray-900 dark:text-white">Rapports planifiés</h2>
              <Button label="Gérer" size="small" severity="secondary" text />
            </div>
          </template>
          <template #content>
            <DataTable :value="scheduledReports" class="text-sm">
              <Column field="name" header="Rapport">
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    <i :class="[data.icon, 'text-sm', data.iconColor]"></i>
                    <span class="font-medium">{{ data.name }}</span>
                  </div>
                </template>
              </Column>
              <Column field="frequency" header="Fréquence" />
              <Column field="nextRun" header="Prochain envoi">
                <template #body="{ data }">
                  <span class="text-blue-600 font-medium">{{ data.nextRun }}</span>
                </template>
              </Column>
              <Column field="recipients" header="Destinataires">
                <template #body="{ data }">
                  <span class="text-xs text-gray-500">{{ data.recipients }} personne(s)</span>
                </template>
              </Column>
              <Column header="Statut">
                <template #body="{ data }">
                  <Tag :value="data.active ? 'Actif' : 'Pausé'" :severity="data.active ? 'success' : 'secondary'" />
                </template>
              </Column>
              <Column header="">
                <template #body>
                  <Button icon="pi pi-play" size="small" text severity="success" v-tooltip="'Exécuter maintenant'" />
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>
      </div>

      <!-- Right sidebar -->
      <div class="space-y-4">
        <!-- Quick actions -->
        <Card>
          <template #header>
            <div class="px-4 pt-4">
              <h2 class="text-base font-semibold text-gray-900 dark:text-white">Actions rapides</h2>
            </div>
          </template>
          <template #content>
            <div class="space-y-2">
              <Button v-for="action in quickActions" :key="action.label"
                :label="action.label" :icon="action.icon"
                :severity="action.severity"
                class="w-full justify-start"
                size="small"
                @click="action.handler" />
            </div>
          </template>
        </Card>

        <!-- Recently viewed reports -->
        <Card>
          <template #header>
            <div class="px-4 pt-4">
              <h2 class="text-base font-semibold text-gray-900 dark:text-white">Consultés récemment</h2>
            </div>
          </template>
          <template #content>
            <div class="space-y-3">
              <div v-for="report in recentlyViewed" :key="report.id"
                class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                <div class="w-8 h-8 rounded flex items-center justify-center flex-shrink-0" :class="report.iconBg">
                  <i :class="[report.icon, 'text-sm', report.iconColor]"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ report.name }}</p>
                  <p class="text-xs text-gray-500">{{ report.viewedAt }}</p>
                </div>
                <i class="pi pi-chevron-right text-xs text-gray-400"></i>
              </div>
            </div>
          </template>
        </Card>

        <!-- Alerts summary -->
        <Card>
          <template #header>
            <div class="px-4 pt-4 flex items-center gap-2">
              <i class="pi pi-bell text-orange-500"></i>
              <h2 class="text-base font-semibold text-gray-900 dark:text-white">Alertes actives</h2>
            </div>
          </template>
          <template #content>
            <div class="space-y-2">
              <div v-for="alert in activeAlerts" :key="alert.id"
                class="flex items-start gap-2 p-2 rounded-lg"
                :class="alert.severity === 'critical' ? 'bg-red-50 dark:bg-red-900/20' : 'bg-yellow-50 dark:bg-yellow-900/20'">
                <i :class="alert.severity === 'critical' ? 'pi pi-exclamation-circle text-red-500' : 'pi pi-exclamation-triangle text-yellow-500'" class="mt-0.5 text-sm flex-shrink-0"></i>
                <div>
                  <p class="text-xs font-medium" :class="alert.severity === 'critical' ? 'text-red-700 dark:text-red-300' : 'text-yellow-700 dark:text-yellow-300'">{{ alert.name }}</p>
                  <p class="text-xs text-gray-500 mt-0.5">{{ alert.triggeredAt }}</p>
                </div>
              </div>
              <Button label="Voir toutes les alertes" icon="pi pi-bell" severity="secondary" size="small" class="w-full" />
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Create Dashboard Dialog -->
    <Dialog v-model:visible="showDashboardDialog" header="Nouveau tableau de bord" :style="{ width: '480px' }" modal>
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom du tableau de bord *</label>
          <InputText v-model="newDashboard.name" class="w-full" placeholder="Ex: Suivi commercial Mai 2026" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
          <Textarea v-model="newDashboard.description" class="w-full" rows="2" placeholder="Description optionnelle" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modules source</label>
          <Select v-model="newDashboard.module" :options="moduleOptions" class="w-full" />
        </div>
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tableau de bord partagé</p>
            <p class="text-xs text-gray-500">Visible par tous les membres de l'équipe</p>
          </div>
          <ToggleSwitch v-model="newDashboard.shared" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showDashboardDialog = false" />
        <Button label="Créer le tableau de bord" icon="pi pi-plus" @click="createDashboard" />
      </template>
    </Dialog>

    <!-- Create Report Dialog -->
    <Dialog v-model:visible="showReportDialog" header="Nouveau rapport" :style="{ width: '480px' }" modal>
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom du rapport *</label>
          <InputText v-model="newReport.name" class="w-full" placeholder="Ex: CA par région — Mai 2026" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type de rapport</label>
          <Select v-model="newReport.type" :options="reportTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Module source</label>
          <Select v-model="newReport.module" :options="moduleOptions" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showReportDialog = false" />
        <Button label="Créer le rapport" icon="pi pi-file-edit" @click="createReport" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
// AI Assisted First
// import { useAiAssistant } from '@widehalo/composables/vue'
// const { guidance } = useAiAssistant('BI', 'view_dashboard')

const page = usePage()
const { isElevated } = useRoleAccess()
const canCreate = computed(() => isElevated.value)

const showDashboardDialog = ref(false)
const showReportDialog = ref(false)

const stats = ref([
  { label: 'Tableaux de bord actifs', value: '24', sub: '6 partagés', icon: 'pi pi-chart-bar', bg: 'bg-blue-100', iconColor: 'text-blue-600', color: 'text-blue-600' },
  { label: 'Rapports planifiés', value: '12', sub: 'prochaine exécution dans 2h', icon: 'pi pi-calendar', bg: 'bg-green-100', iconColor: 'text-green-600', color: 'text-green-600' },
  { label: 'Alertes actives', value: '7', sub: '2 critiques', icon: 'pi pi-bell', bg: 'bg-orange-100', iconColor: 'text-orange-600', color: 'text-orange-600' },
  { label: 'Sources de données', value: '8', sub: '7 connectées', icon: 'pi pi-database', bg: 'bg-purple-100', iconColor: 'text-purple-600', color: 'text-purple-600' },
])

const recentDashboards = ref([
  { id: 1, name: 'Performance commerciale', description: 'CA, pipeline, conversions', icon: 'pi pi-chart-line', iconBg: 'bg-blue-100', iconColor: 'text-blue-600', views: 142, lastViewed: 'Auj. 09h30', owner: 'Aminata D.', starred: true },
  { id: 2, name: 'Stock & Inventaire', description: 'Niveaux stock, rotations', icon: 'pi pi-box', iconBg: 'bg-green-100', iconColor: 'text-green-600', views: 89, lastViewed: 'Hier 16h15', owner: 'Kofi A.', starred: false },
  { id: 3, name: 'RH & Effectifs', description: 'Headcount, absences, paie', icon: 'pi pi-users', iconBg: 'bg-purple-100', iconColor: 'text-purple-600', views: 67, lastViewed: '22/05/2026', owner: 'Fatou B.', starred: false },
  { id: 4, name: 'Trésorerie', description: 'Flux de trésorerie, DSO', icon: 'pi pi-wallet', iconBg: 'bg-yellow-100', iconColor: 'text-yellow-600', views: 201, lastViewed: 'Auj. 08h00', owner: 'Moussa K.', starred: true },
  { id: 5, name: 'CRM & Leads', description: 'Entonnoir, scoring, activité', icon: 'pi pi-users', iconBg: 'bg-pink-100', iconColor: 'text-pink-600', views: 45, lastViewed: '21/05/2026', owner: 'Seun O.', starred: false },
  { id: 6, name: 'Logistique', description: 'Délais livraison, transporteurs', icon: 'pi pi-truck', iconBg: 'bg-orange-100', iconColor: 'text-orange-600', views: 33, lastViewed: '20/05/2026', owner: 'Ibrahim T.', starred: false },
])

const scheduledReports = ref([
  { id: 1, name: 'Rapport CA Hebdo', icon: 'pi pi-chart-bar', iconColor: 'text-blue-500', frequency: 'Hebdomadaire', nextRun: 'Lun. 07h00', recipients: 5, active: true },
  { id: 2, name: 'Bilan stock mensuel', icon: 'pi pi-box', iconColor: 'text-green-500', frequency: 'Mensuel', nextRun: '01/06 08h00', recipients: 3, active: true },
  { id: 3, name: 'Rapport RH quotidien', icon: 'pi pi-users', iconColor: 'text-purple-500', frequency: 'Quotidien', nextRun: 'Dem. 06h30', recipients: 2, active: true },
  { id: 4, name: 'Synthèse CRM', icon: 'pi pi-address-book', iconColor: 'text-pink-500', frequency: 'Hebdomadaire', nextRun: 'Paused', recipients: 4, active: false },
])

const recentlyViewed = ref([
  { id: 1, name: 'CA par produit — Avr. 2026', icon: 'pi pi-chart-pie', iconBg: 'bg-blue-100', iconColor: 'text-blue-600', viewedAt: 'il y a 15 min' },
  { id: 2, name: 'Effectifs par département', icon: 'pi pi-users', iconBg: 'bg-purple-100', iconColor: 'text-purple-600', viewedAt: 'il y a 2h' },
  { id: 3, name: 'Stock critique — Dakar', icon: 'pi pi-box', iconBg: 'bg-red-100', iconColor: 'text-red-600', viewedAt: 'Hier 14h00' },
  { id: 4, name: 'Pipeline commercial Mai', icon: 'pi pi-chart-line', iconBg: 'bg-green-100', iconColor: 'text-green-600', viewedAt: 'Hier 09h30' },
])

const activeAlerts = ref([
  { id: 1, name: 'Taux de conversion < 5% — Seuil critique', severity: 'critical', triggeredAt: 'Auj. 08h42' },
  { id: 2, name: 'Stock produit SKU-1045 < 50 unités', severity: 'warning', triggeredAt: 'Auj. 09h10' },
  { id: 3, name: 'DSO > 45 jours ce mois', severity: 'warning', triggeredAt: 'Hier 18h00' },
])

const quickActions = ref([
  { label: 'Nouveau tableau de bord', icon: 'pi pi-plus', severity: 'primary', handler: () => { showDashboardDialog.value = true } },
  { label: 'Nouveau rapport', icon: 'pi pi-file-edit', severity: 'secondary', handler: () => { showReportDialog.value = true } },
  { label: 'Nouvelle alerte BI', icon: 'pi pi-bell', severity: 'secondary', handler: () => {} },
  { label: 'Importer une source', icon: 'pi pi-database', severity: 'secondary', handler: () => {} },
  { label: 'Éditeur SQL', icon: 'pi pi-code', severity: 'secondary', handler: () => {} },
])

const moduleOptions = ref(['Comptabilité', 'CRM', 'Inventaire', 'RH', 'Ventes', 'POS', 'Logistique', 'Tous les modules'])
const reportTypeOptions = ref([
  { label: 'Tableau', value: 'table' },
  { label: 'Graphique', value: 'chart' },
  { label: 'Pivot', value: 'pivot' },
  { label: 'KPI', value: 'kpi' },
])

const newDashboard = ref({ name: '', description: '', module: '', shared: false })
const newReport = ref({ name: '', type: 'table', module: '' })

function createDashboard() { showDashboardDialog.value = false; newDashboard.value = { name: '', description: '', module: '', shared: false } }
function createReport() { showReportDialog.value = false; newReport.value = { name: '', type: 'table', module: '' } }
</script>
