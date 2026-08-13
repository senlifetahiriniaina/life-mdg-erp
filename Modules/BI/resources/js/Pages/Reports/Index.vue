<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Rapports</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Créez, planifiez et partagez vos rapports analytiques</p>
      </div>
      <Button v-if="canCreate" label="Nouveau rapport" icon="pi pi-plus" @click="showCreateDialog = true" />
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

    <!-- Reports DataTable -->
    <Card>
      <template #header>
        <div class="flex items-center justify-between px-4 pt-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Tous les rapports</h2>
          <div class="flex gap-2">
            <InputText v-model="searchQuery" placeholder="Rechercher..." class="w-56" size="small" />
            <Select v-model="filterModule" :options="moduleFilterOptions" optionLabel="label" optionValue="value"
              placeholder="Module" class="w-40" size="small" />
            <Select v-model="filterType" :options="typeFilterOptions" optionLabel="label" optionValue="value"
              placeholder="Type" class="w-36" size="small" />
          </div>
        </div>
      </template>
      <template #content>
        <DataTable :value="filteredReports" paginator :rows="10" :rowsPerPageOptions="[10,25,50]"
          stripedRows class="text-sm" selectionMode="single">
          <Column field="name" header="Rapport" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded flex items-center justify-center flex-shrink-0" :class="data.iconBg">
                  <i :class="[data.icon, 'text-sm', data.iconColor]"></i>
                </div>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">{{ data.name }}</p>
                  <p class="text-xs text-gray-500">{{ data.description }}</p>
                </div>
              </div>
            </template>
          </Column>
          <Column header="Type">
            <template #body="{ data }">
              <Tag :value="data.type" :severity="getTypeSeverity(data.type)" />
            </template>
          </Column>
          <Column field="module" header="Module" sortable />
          <Column field="author" header="Auteur" sortable />
          <Column header="Planification">
            <template #body="{ data }">
              <div v-if="data.schedule">
                <p class="text-xs font-medium text-green-600 dark:text-green-400">{{ data.schedule }}</p>
                <p class="text-xs text-gray-400">{{ data.nextRun }}</p>
              </div>
              <span v-else class="text-xs text-gray-400">Non planifié</span>
            </template>
          </Column>
          <Column header="Dernière exécution" sortable field="lastRun">
            <template #body="{ data }">
              <div>
                <p class="text-xs text-gray-700 dark:text-gray-300">{{ data.lastRun }}</p>
                <p class="text-xs text-gray-400">{{ data.lastDuration }}</p>
              </div>
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-play" size="small" severity="success" text v-tooltip="'Exécuter'" @click="runReport(data)" />
                <Button icon="pi pi-calendar" size="small" severity="info" text v-tooltip="'Planifier'" @click="openScheduleDialog(data)" />
                <Button icon="pi pi-share-alt" size="small" severity="secondary" text v-tooltip="'Partager'" />
                <Button icon="pi pi-download" size="small" severity="secondary" text v-tooltip="'Exporter'" />
                <Button icon="pi pi-trash" size="small" severity="danger" text v-tooltip="'Supprimer'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Create Report Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau rapport" :style="{ width: '600px' }" modal>
      <div class="space-y-4 py-2">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom du rapport *</label>
            <InputText v-model="form.name" class="w-full" placeholder="Ex: CA par région" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Module source *</label>
            <Select v-model="form.module" :options="moduleOptions" class="w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
          <Textarea v-model="form.description" class="w-full" rows="2" placeholder="Description du rapport" />
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type de rapport</label>
            <Select v-model="form.type" :options="reportTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dimensions</label>
            <Select v-model="form.dimension" :options="getDimensions(form.module)" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Métriques</label>
            <Select v-model="form.metric" :options="getMetrics(form.module)" class="w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filtres</label>
          <InputText v-model="form.filters" class="w-full" placeholder="Ex: date_debut=2026-01-01&pays=SN" size="small" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
        <Button label="Créer et exécuter" icon="pi pi-play" @click="createAndRun" :loading="creating" />
      </template>
    </Dialog>

    <!-- Schedule Dialog -->
    <Dialog v-model:visible="showScheduleDialog" :header="`Planifier — ${schedulingReport?.name}`" :style="{ width: '480px' }" modal>
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fréquence</label>
          <Select v-model="scheduleForm.frequency" :options="['Quotidien', 'Hebdomadaire', 'Mensuel', 'Trimestriel']" class="w-full" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Heure d'envoi</label>
            <InputText v-model="scheduleForm.time" class="w-full" type="time" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Format export</label>
            <Select v-model="scheduleForm.format" :options="['PDF', 'Excel (.xlsx)', 'CSV', 'JSON']" class="w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destinataires email</label>
          <Textarea v-model="scheduleForm.recipients" class="w-full" rows="2" placeholder="email1@exemple.com, email2@exemple.com" />
        </div>
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Activer la planification</p>
          </div>
          <ToggleSwitch v-model="scheduleForm.active" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showScheduleDialog = false" />
        <Button label="Sauvegarder la planification" icon="pi pi-calendar" @click="saveSchedule" :loading="savingSchedule" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import StrategicContext from '@/Components/StrategicContext.vue'
import { useStrategicLink } from '@/composables/useStrategicLink'

const page = usePage()
const userRoles: string[] = (page.props.auth as any)?.user?.roles ?? []
const canCreate = userRoles.some(r => ['super_admin','admin','bi_analyst','reporting_manager'].includes(r))

const { objective } = useStrategicLink('BI/Report', item?.id)

const showCreateDialog = ref(false)
const showScheduleDialog = ref(false)
const searchQuery = ref('')
const filterModule = ref('')
const filterType = ref('')
const creating = ref(false)
const savingSchedule = ref(false)
const schedulingReport = ref<any>(null)

const stats = ref([
  { label: 'Rapports total', value: '47', sub: '12 partagés avec l\'équipe', icon: 'pi pi-file', bg: 'bg-blue-100', iconColor: 'text-blue-600', color: 'text-blue-600' },
  { label: 'Planifiés', value: '12', sub: 'envois automatiques actifs', icon: 'pi pi-calendar', bg: 'bg-green-100', iconColor: 'text-green-600', color: 'text-green-600' },
  { label: 'Partagés', value: '18', sub: 'avec au moins 1 membre', icon: 'pi pi-share-alt', bg: 'bg-purple-100', iconColor: 'text-purple-600', color: 'text-purple-600' },
  { label: 'Exécutions ce mois', value: '284', sub: 'dont 12 en erreur', icon: 'pi pi-play', bg: 'bg-orange-100', iconColor: 'text-orange-600', color: 'text-orange-600' },
])

const reports = ref([
  { id: 1, name: 'Chiffre d\'affaires par région', description: 'CA mensuel segmenté par pays', icon: 'pi pi-chart-bar', iconBg: 'bg-blue-100', iconColor: 'text-blue-600', type: 'Graphique', module: 'Comptabilité', author: 'Aminata D.', schedule: 'Hebdomadaire', nextRun: 'Lun. 07h00', lastRun: '17/05/2026', lastDuration: '2.3s' },
  { id: 2, name: 'Stock par entrepôt', description: 'Niveaux de stock par site', icon: 'pi pi-box', iconBg: 'bg-green-100', iconColor: 'text-green-600', type: 'Tableau', module: 'Inventaire', author: 'Kofi A.', schedule: 'Mensuel', nextRun: '01/06 08h00', lastRun: '01/05/2026', lastDuration: '5.1s' },
  { id: 3, name: 'Pipeline CRM', description: 'Opportunités par étape', icon: 'pi pi-chart-line', iconBg: 'bg-pink-100', iconColor: 'text-pink-600', type: 'Pivot', module: 'CRM', author: 'Seun O.', schedule: null, nextRun: null, lastRun: '20/05/2026', lastDuration: '1.8s' },
  { id: 4, name: 'Masse salariale mensuelle', description: 'Coût salarial par département', icon: 'pi pi-users', iconBg: 'bg-purple-100', iconColor: 'text-purple-600', type: 'Tableau', module: 'RH', author: 'Fatou B.', schedule: 'Mensuel', nextRun: '01/06 06h30', lastRun: '01/05/2026', lastDuration: '8.7s' },
  { id: 5, name: 'Bilan comptable OHADA', description: 'Bilan selon le plan SYSCOHADA', icon: 'pi pi-receipt', iconBg: 'bg-yellow-100', iconColor: 'text-yellow-600', type: 'Tableau', module: 'Comptabilité', author: 'Moussa K.', schedule: 'Trimestriel', nextRun: '01/07 09h00', lastRun: '01/04/2026', lastDuration: '12.4s' },
  { id: 6, name: 'Performance vendeurs', description: 'KPI par commercial', icon: 'pi pi-star', iconBg: 'bg-orange-100', iconColor: 'text-orange-600', type: 'Graphique', module: 'CRM', author: 'Aminata D.', schedule: 'Hebdomadaire', nextRun: 'Ven. 17h00', lastRun: '10/05/2026', lastDuration: '3.2s' },
])

const moduleFilterOptions = ref([
  { label: 'Tous les modules', value: '' },
  { label: 'Comptabilité', value: 'Comptabilité' },
  { label: 'CRM', value: 'CRM' },
  { label: 'Inventaire', value: 'Inventaire' },
  { label: 'RH', value: 'RH' },
])
const typeFilterOptions = ref([
  { label: 'Tous les types', value: '' },
  { label: 'Tableau', value: 'Tableau' },
  { label: 'Graphique', value: 'Graphique' },
  { label: 'Pivot', value: 'Pivot' },
])
const moduleOptions = ref(['Comptabilité', 'CRM', 'Inventaire', 'RH', 'Ventes', 'POS', 'Logistique'])
const reportTypeOptions = ref([
  { label: 'Tableau', value: 'Tableau' },
  { label: 'Graphique', value: 'Graphique' },
  { label: 'Pivot', value: 'Pivot' },
  { label: 'KPI', value: 'KPI' },
])

const form = ref({ name: '', description: '', module: '', type: 'Tableau', dimension: '', metric: '', filters: '' })
const scheduleForm = ref({ frequency: 'Hebdomadaire', time: '07:00', format: 'PDF', recipients: '', active: true })

const filteredReports = computed(() => {
  let list = reports.value
  if (searchQuery.value) list = list.filter(r => r.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
  if (filterModule.value) list = list.filter(r => r.module === filterModule.value)
  if (filterType.value) list = list.filter(r => r.type === filterType.value)
  return list
})

function getTypeSeverity(type: string) {
  const map: Record<string, string> = { Tableau: 'info', Graphique: 'success', Pivot: 'warn', KPI: 'danger' }
  return map[type] ?? 'secondary'
}
function getDimensions(module: string) {
  const dims: Record<string, string[]> = {
    Comptabilité: ['Compte', 'Période', 'Devise', 'Département'],
    CRM: ['Commercial', 'Étape', 'Pays', 'Source lead'],
    Inventaire: ['Produit', 'Entrepôt', 'Catégorie', 'Fournisseur'],
    RH: ['Département', 'Poste', 'Contrat', 'Site'],
  }
  return dims[module] ?? ['Date', 'Catégorie', 'Région']
}
function getMetrics(module: string) {
  const metrics: Record<string, string[]> = {
    Comptabilité: ['Chiffre d\'affaires (XOF)', 'Charges', 'Marge brute', 'TVA collectée'],
    CRM: ['Nb opportunités', 'Valeur pipeline (XOF)', 'Taux conversion', 'Durée cycle vente'],
    Inventaire: ['Quantité en stock', 'Valeur stock (XOF)', 'Rotation', 'Ruptures'],
    RH: ['Effectif', 'Masse salariale (XOF)', 'Absences', 'Turnover %'],
  }
  return metrics[module] ?? ['Montant (XOF)', 'Quantité', 'Pourcentage']
}
function runReport(report: any) { /* Execute report */ }
function openScheduleDialog(report: any) { schedulingReport.value = report; showScheduleDialog.value = true }
function createAndRun() { creating.value = true; setTimeout(() => { creating.value = false; showCreateDialog.value = false }, 1500) }
function saveSchedule() { savingSchedule.value = true; setTimeout(() => { savingSchedule.value = false; showScheduleDialog.value = false }, 1000) }
</script>
