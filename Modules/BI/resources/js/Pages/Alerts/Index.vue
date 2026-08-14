<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Alertes BI</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Surveillez vos métriques et recevez des notifications proactives</p>
      </div>
      <div class="flex gap-2">
        <Button label="Historique" icon="pi pi-history" severity="secondary" @click="showHistoryDrawer = true" />
        <Button v-if="canCreate" label="Nouvelle alerte" icon="pi pi-plus" @click="showCreateDialog = true" />
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

    <!-- Alerts DataTable -->
    <Card>
      <template #header>
        <div class="flex items-center justify-between px-4 pt-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Toutes les alertes</h2>
          <div class="flex gap-2">
            <InputText v-model="searchQuery" placeholder="Rechercher..." class="w-56" size="small" />
            <Select v-model="filterType" :options="alertTypeOptions" optionLabel="label" optionValue="value"
              placeholder="Type" class="w-44" size="small" />
            <Select v-model="filterStatus" :options="statusFilterOptions" optionLabel="label" optionValue="value"
              placeholder="Statut" class="w-36" size="small" />
          </div>
        </div>
      </template>
      <template #content>
        <DataTable :value="filteredAlerts" paginator :rows="10" :rowsPerPageOptions="[10,25,50]"
          stripedRows class="text-sm">
          <Column field="name" header="Alerte" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded flex items-center justify-center flex-shrink-0"
                  :class="data.severity === 'critical' ? 'bg-red-100' : data.severity === 'warning' ? 'bg-yellow-100' : 'bg-blue-100'">
                  <i :class="[data.icon, 'text-sm', data.severity === 'critical' ? 'text-red-600' : data.severity === 'warning' ? 'text-yellow-600' : 'text-blue-600']"></i>
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
              <Tag :value="data.type" :severity="getAlertTypeSeverity(data.type)" />
            </template>
          </Column>
          <Column field="metric" header="Métrique surveillée" />
          <Column header="Condition">
            <template #body="{ data }">
              <code class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded font-mono">{{ data.condition }}</code>
            </template>
          </Column>
          <Column field="frequency" header="Vérification" />
          <Column field="lastTriggered" header="Dernier déclenchement">
            <template #body="{ data }">
              <span v-if="data.lastTriggered" class="text-xs font-medium"
                :class="data.severity === 'critical' ? 'text-red-600' : 'text-yellow-600'">
                {{ data.lastTriggered }}
              </span>
              <span v-else class="text-xs text-gray-400">Jamais</span>
            </template>
          </Column>
          <Column header="Destinataires">
            <template #body="{ data }">
              <div class="flex gap-1 flex-wrap">
                <i v-if="data.channels.includes('email')" class="pi pi-envelope text-sm text-blue-500" v-tooltip="'Email'" />
                <i v-if="data.channels.includes('sms')" class="pi pi-mobile text-sm text-green-500" v-tooltip="'SMS'" />
                <i v-if="data.channels.includes('whatsapp')" class="pi pi-whatsapp text-sm text-green-600" v-tooltip="'WhatsApp'" />
                <i v-if="data.channels.includes('slack')" class="pi pi-hashtag text-sm text-purple-500" v-tooltip="'Slack'" />
                <span class="text-xs text-gray-500 ml-1">{{ data.recipients }} pers.</span>
              </div>
            </template>
          </Column>
          <Column header="Statut">
            <template #body="{ data }">
              <ToggleSwitch :modelValue="data.active" @update:modelValue="toggleAlert(data)" size="small" />
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-history" size="small" text severity="secondary" v-tooltip="'Historique'" @click="viewHistory(data)" />
                <Button icon="pi pi-pencil" size="small" text severity="secondary" v-tooltip="'Modifier'" @click="editAlert(data)" />
                <Button icon="pi pi-trash" size="small" text severity="danger" v-tooltip="'Supprimer'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Create Alert Dialog -->
    <Dialog v-model:visible="showCreateDialog"
      :header="editingAlert ? 'Modifier l\'alerte' : 'Nouvelle alerte BI'"
      :style="{ width: '640px' }" modal>
      <div class="space-y-4 py-2">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom de l'alerte *</label>
            <InputText v-model="form.name" class="w-full" placeholder="Ex: CA journalier < seuil" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type d'alerte</label>
            <Select v-model="form.type" :options="['Seuil dépassé', 'Anomalie IA', 'Variation %', 'Valeur nulle', 'Données manquantes']" class="w-full" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Métrique surveillée *</label>
            <Select v-model="form.metric" :options="availableMetrics" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sévérité</label>
            <Select v-model="form.severity" :options="['Critique', 'Avertissement', 'Information']" class="w-full" />
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Opérateur</label>
            <Select v-model="form.operator" :options="['< inférieur à', '> supérieur à', '= égal à', '≠ différent de', '% variation de']" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valeur seuil</label>
            <InputText v-model="form.threshold" class="w-full" placeholder="Ex: 1000000" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unité</label>
            <Select v-model="form.unit" :options="['XOF', '%', 'unités', 'jours', 'heures']" class="w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fréquence de vérification</label>
          <Select v-model="form.frequency" :options="['Toutes les 15 min', 'Toutes les heures', 'Quotidienne (08h00)', 'Hebdomadaire (lundi)', 'Après chaque synchro']" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Canaux de notification</label>
          <div class="flex flex-wrap gap-3">
            <div v-for="channel in notificationChannels" :key="channel.key"
              class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors"
              :class="form.channels.includes(channel.key) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700'"
              @click="toggleChannel(channel.key)" role="button" tabindex="0" @keydown.enter.prevent="toggleChannel(channel.key)">
              <i :class="[channel.icon, 'text-sm', channel.color]"></i>
              <span class="text-sm">{{ channel.label }}</span>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destinataires</label>
          <Textarea v-model="form.recipients" class="w-full" rows="2" placeholder="email1@exemple.com, email2@exemple.com ou numéros téléphone" />
        </div>
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Activer immédiatement</p>
          </div>
          <ToggleSwitch v-model="form.active" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="closeDialog" />
        <Button :label="editingAlert ? 'Mettre à jour' : 'Créer l\'alerte'" icon="pi pi-check" @click="saveAlert" :loading="saving" />
      </template>
    </Dialog>

    <!-- History Drawer -->
    <Drawer v-model:visible="showHistoryDrawer" position="right" header="Historique des alertes" style="width: 480px">
      <div class="space-y-4">
        <InputText v-model="historySearch" placeholder="Filtrer l'historique..." class="w-full" size="small" />
        <div class="space-y-3">
          <div v-for="event in alertHistory" :key="event.id"
            class="flex items-start gap-3 p-3 rounded-lg border"
            :class="event.severity === 'critical' ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10' : 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/10'">
            <div class="flex-shrink-0 mt-1">
              <i :class="event.severity === 'critical' ? 'pi pi-exclamation-circle text-red-500' : 'pi pi-exclamation-triangle text-yellow-500'" class="text-lg"></i>
            </div>
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <p class="text-sm font-semibold" :class="event.severity === 'critical' ? 'text-red-700 dark:text-red-300' : 'text-yellow-700 dark:text-yellow-300'">{{ event.alertName }}</p>
                <Tag :value="event.severity === 'critical' ? 'Critique' : 'Avertissement'" :severity="event.severity === 'critical' ? 'danger' : 'warn'" class="text-xs" />
              </div>
              <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ event.message }}</p>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                <span><i class="pi pi-clock mr-1"></i>{{ event.triggeredAt }}</span>
                <span><i class="pi pi-check mr-1"></i>{{ event.resolvedAt ?? 'Non résolu' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const { isElevated } = useRoleAccess()
const canCreate = computed(() => isElevated.value)

const showCreateDialog = ref(false)
const showHistoryDrawer = ref(false)
const searchQuery = ref('')
const filterType = ref('')
const filterStatus = ref('')
const historySearch = ref('')
const editingAlert = ref<any>(null)
const saving = ref(false)

const stats = ref([
  { label: 'Alertes actives', value: '14', sub: '2 critiques non résolues', icon: 'pi pi-bell', bg: 'bg-red-100', iconColor: 'text-red-600', color: 'text-red-600' },
  { label: 'Déclenchées ce mois', value: '47', sub: '12 de plus qu\'en avril', icon: 'pi pi-chart-line', bg: 'bg-orange-100', iconColor: 'text-orange-600', color: 'text-orange-600' },
  { label: 'Critiques', value: '3', sub: 'nécessitent une action immédiate', icon: 'pi pi-exclamation-circle', bg: 'bg-red-100', iconColor: 'text-red-600', color: 'text-red-600' },
  { label: 'En attente validation', value: '5', sub: 'alertes IA à confirmer', icon: 'pi pi-check-circle', bg: 'bg-yellow-100', iconColor: 'text-yellow-600', color: 'text-yellow-600' },
])

const alerts = ref([
  { id: 1, name: 'CA journalier sous seuil', description: 'CA < 2 000 000 XOF par jour', icon: 'pi pi-chart-bar', type: 'Seuil dépassé', severity: 'critical', metric: 'Chiffre d\'affaires (XOF)', condition: 'CA < 2 000 000', frequency: 'Quotidienne', lastTriggered: 'Auj. 08h42', channels: ['email','slack'], recipients: 5, active: true },
  { id: 2, name: 'Rupture stock imminente', description: 'Stock < 50 unités sur produit critique', icon: 'pi pi-box', type: 'Seuil dépassé', severity: 'warning', metric: 'Niveau stock SKU', condition: 'stock < 50', frequency: 'Toutes les heures', lastTriggered: 'Auj. 09h10', channels: ['email','sms'], recipients: 3, active: true },
  { id: 3, name: 'Anomalie IA — Dépenses', description: 'Variation anormale détectée par IA', icon: 'pi pi-sparkles', type: 'Anomalie IA', severity: 'warning', metric: 'Charges opérationnelles', condition: 'anomalie > 2σ', frequency: 'Quotidienne', lastTriggered: '22/05/2026', channels: ['email'], recipients: 2, active: true },
  { id: 4, name: 'DSO > 45 jours', description: 'Délai moyen de recouvrement trop long', icon: 'pi pi-calendar', type: 'Seuil dépassé', severity: 'critical', metric: 'DSO (jours)', condition: 'DSO > 45', frequency: 'Hebdomadaire', lastTriggered: '21/05/2026', channels: ['email','whatsapp'], recipients: 4, active: true },
  { id: 5, name: 'Taux conversion < 5%', description: 'Conversion leads → clients en baisse', icon: 'pi pi-percentage', type: 'Variation %', severity: 'warning', metric: 'Taux conversion CRM', condition: 'conversion < 5%', frequency: 'Quotidienne', lastTriggered: 'Auj. 08h00', channels: ['email','slack'], recipients: 3, active: true },
  { id: 6, name: 'Masse salariale > budget', description: 'Dépassement du budget RH mensuel', icon: 'pi pi-users', type: 'Seuil dépassé', severity: 'critical', metric: 'Masse salariale (XOF)', condition: 'salaires > budget_rh', frequency: 'Mensuelle', lastTriggered: null, channels: ['email'], recipients: 2, active: false },
])

const alertHistory = ref([
  { id: 1, alertName: 'CA journalier sous seuil', message: 'CA = 1 842 000 XOF — seuil 2 000 000 XOF non atteint', severity: 'critical', triggeredAt: 'Auj. 08h42', resolvedAt: null },
  { id: 2, alertName: 'Rupture stock SKU-1045', message: 'Stock actuel = 38 unités (seuil : 50)', severity: 'warning', triggeredAt: 'Auj. 09h10', resolvedAt: null },
  { id: 3, alertName: 'DSO > 45 jours', message: 'DSO moyen = 47,3 jours ce mois', severity: 'critical', triggeredAt: '21/05/2026 14h00', resolvedAt: '22/05/2026 09h30' },
  { id: 4, alertName: 'Taux conversion < 5%', message: 'Taux = 3,8% sur les 7 derniers jours', severity: 'warning', triggeredAt: '20/05/2026 08h00', resolvedAt: '21/05/2026 08h00' },
  { id: 5, alertName: 'Anomalie IA — Dépenses', message: 'Hausse inhabituelle des charges (+38%) détectée', severity: 'warning', triggeredAt: '22/05/2026 06h15', resolvedAt: '22/05/2026 11h00' },
])

const alertTypeOptions = ref([
  { label: 'Tous les types', value: '' },
  { label: 'Seuil dépassé', value: 'Seuil dépassé' },
  { label: 'Anomalie IA', value: 'Anomalie IA' },
  { label: 'Variation %', value: 'Variation %' },
  { label: 'Valeur nulle', value: 'Valeur nulle' },
])
const statusFilterOptions = ref([
  { label: 'Tous', value: '' },
  { label: 'Actif', value: 'active' },
  { label: 'Inactif', value: 'inactive' },
])

const availableMetrics = ref([
  { label: 'Chiffre d\'affaires (XOF)', value: 'ca_xof' },
  { label: 'Charges opérationnelles (XOF)', value: 'charges_xof' },
  { label: 'Niveau stock SKU', value: 'stock_level' },
  { label: 'DSO (jours)', value: 'dso_days' },
  { label: 'Taux conversion CRM (%)', value: 'crm_conversion' },
  { label: 'Masse salariale (XOF)', value: 'payroll_xof' },
  { label: 'Marge brute (%)', value: 'gross_margin' },
  { label: 'Délai livraison moyen (jours)', value: 'delivery_days' },
])

const notificationChannels = ref([
  { key: 'email', label: 'Email', icon: 'pi pi-envelope', color: 'text-blue-500' },
  { key: 'sms', label: 'SMS', icon: 'pi pi-mobile', color: 'text-green-500' },
  { key: 'whatsapp', label: 'WhatsApp', icon: 'pi pi-whatsapp', color: 'text-green-600' },
  { key: 'slack', label: 'Slack', icon: 'pi pi-hashtag', color: 'text-purple-500' },
])

const form = ref({ name: '', type: 'Seuil dépassé', metric: '', severity: 'Avertissement', operator: '< inférieur à', threshold: '', unit: 'XOF', frequency: 'Quotidienne (08h00)', channels: ['email'] as string[], recipients: '', active: true })

const filteredAlerts = computed(() => {
  let list = alerts.value
  if (searchQuery.value) list = list.filter(a => a.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
  if (filterType.value) list = list.filter(a => a.type === filterType.value)
  if (filterStatus.value === 'active') list = list.filter(a => a.active)
  if (filterStatus.value === 'inactive') list = list.filter(a => !a.active)
  return list
})

function getAlertTypeSeverity(type: string) {
  const map: Record<string, string> = { 'Seuil dépassé': 'danger', 'Anomalie IA': 'warn', 'Variation %': 'info', 'Valeur nulle': 'secondary' }
  return map[type] ?? 'secondary'
}
function toggleChannel(key: string) {
  const idx = form.value.channels.indexOf(key)
  if (idx > -1) form.value.channels.splice(idx, 1)
  else form.value.channels.push(key)
}
function toggleAlert(alert: any) { alert.active = !alert.active }
function editAlert(alert: any) { editingAlert.value = alert; showCreateDialog.value = true }
function viewHistory(alert: any) { showHistoryDrawer.value = true }
function closeDialog() { showCreateDialog.value = false; editingAlert.value = null; form.value = { name: '', type: 'Seuil dépassé', metric: '', severity: 'Avertissement', operator: '< inférieur à', threshold: '', unit: 'XOF', frequency: 'Quotidienne (08h00)', channels: ['email'], recipients: '', active: true } }
function saveAlert() { saving.value = true; setTimeout(() => { saving.value = false; closeDialog() }, 1200) }
</script>
