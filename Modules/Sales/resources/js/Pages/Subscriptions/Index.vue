<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Abonnements & Facturation Récurrente</h1>
        <p class="text-surface-500 text-sm mt-1">Gérez vos revenus récurrents et abonnements clients (IFRS 15)</p>
      </div>
      <Button label="Nouvel abonnement" icon="pi pi-plus" @click="showCreateDialog = true" v-if="canCreate" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div v-if="expiringCount > 0" class="p-3 bg-orange-50 border border-orange-200 rounded-lg flex items-center gap-2">
      <span class="text-orange-600">⚠️</span>
      <span class="text-orange-700 text-sm">{{ expiringCount }} abonnement(s) expirent dans les 30 prochains jours</span>
    </div>

    <Card>
      <template #header>
        <div class="px-4 pt-4 flex items-center justify-between">
          <span class="font-semibold">Abonnements actifs</span>
          <Select v-model="filterStatus" :options="['Tous','Actif','Suspendu','Résilié','En essai']" placeholder="Statut" class="w-40" />
        </div>
      </template>
      <template #content>
        <DataTable :value="filteredSubs" stripedRows responsiveLayout="scroll" selectionMode="single" v-model:selection="selectedSub" @row-select="showDetail = true">
          <Column field="client" header="Client" />
          <Column field="plan" header="Plan" />
          <Column field="cycle" header="Cycle" />
          <Column field="amount" header="Montant/période (XOF)" />
          <Column field="nextRenewal" header="Prochain renouvellement">
            <template #body="{ data }"><span :class="isExpiringSoon(data.nextRenewal) ? 'text-orange-500 font-semibold' : ''">{{ data.nextRenewal }}</span></template>
          </Column>
          <Column field="status" header="Statut">
            <template #body="{ data }"><Tag :value="data.status" :severity="subSeverity(data.status)" /></template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <Button icon="pi pi-refresh" size="small" text title="Renouveler" @click.stop="renewSub(data)" v-if="data.status === 'Actif'" />
              <Button icon="pi pi-ban" size="small" text severity="danger" title="Résilier" v-if="canCancel && data.status === 'Actif'" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="showCreateDialog" header="Créer un abonnement" :style="{ width: '550px' }" modal>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-sm font-medium">Client</label>
            <Select v-model="form.client" :options="['Groupe Sonatel','Orange CI','MTN Cameroun']" placeholder="Sélectionner" class="w-full mt-1" /></div>
          <div><label class="text-sm font-medium">Plan / Produit</label>
            <Select v-model="form.plan" :options="['WideHalo Starter','WideHalo Pro','WideHalo Enterprise']" placeholder="Sélectionner" class="w-full mt-1" /></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-sm font-medium">Cycle de facturation</label>
            <Select v-model="form.cycle" :options="['Mensuel','Trimestriel','Annuel']" class="w-full mt-1" /></div>
          <div><label class="text-sm font-medium">Prix (XOF)</label>
            <InputNumber v-model="form.price" :min="0" class="w-full mt-1" /></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-sm font-medium">Date de début</label>
            <DatePicker v-model="form.startDate" class="w-full mt-1" /></div>
          <div class="flex items-center gap-2 mt-6">
            <ToggleSwitch v-model="form.trial" />
            <label class="text-sm">Période d'essai (30 jours)</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Créer l'abonnement" @click="showCreateDialog = false" />
      </template>
    </Dialog>

    <Drawer v-model:visible="showDetail" position="right" :style="{ width: '480px' }" :header="selectedSub?.client + ' — ' + selectedSub?.plan">
      <div v-if="selectedSub" class="space-y-4">
        <div class="flex gap-2"><Tag :value="selectedSub.status" :severity="subSeverity(selectedSub.status)" /></div>
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div><span class="text-surface-500">Montant:</span><strong class="ml-1">{{ selectedSub.amount }} XOF</strong></div>
          <div><span class="text-surface-500">Cycle:</span><strong class="ml-1">{{ selectedSub.cycle }}</strong></div>
          <div><span class="text-surface-500">Prochain renouvellement:</span><strong class="ml-1">{{ selectedSub.nextRenewal }}</strong></div>
        </div>
        <div>
          <h4 class="font-semibold mb-2">Calendrier IFRS 15 — Reconnaissance des revenus</h4>
          <DataTable :value="recognitionSchedule" size="small">
            <Column field="period" header="Période" />
            <Column field="deferred" header="Différé (XOF)" />
            <Column field="recognized" header="Reconnu (XOF)" />
            <Column field="status" header="Statut"><template #body="{ data }"><Tag :value="data.status" :severity="data.status === 'Reconnu' ? 'success' : 'secondary'" size="small" /></template></Column>
          </DataTable>
        </div>
        <div>
          <h4 class="font-semibold mb-2">Factures générées</h4>
          <DataTable :value="selectedSub.invoices || []" size="small">
            <Column field="ref" header="Référence" />
            <Column field="amount" header="Montant (XOF)" />
            <Column field="date" header="Date" />
            <Column field="status" header="Statut"><template #body="{ data }"><Tag :value="data.status" :severity="data.status === 'Payée' ? 'success' : 'warn'" size="small" /></template></Column>
          </DataTable>
        </div>
      </div>
    </Drawer>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Drawer from 'primevue/drawer'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canCreate = computed(() => roles.value.some(r => ['sales-rep','sales-manager','manager','admin','super-admin'].includes(r)))
const canCancel = computed(() => roles.value.some(r => ['sales-manager','manager','admin','super-admin'].includes(r)))

const { guidance } = useAiAssistant('Sales', 'create_order')

const showCreateDialog = ref(false)
const showDetail = ref(false)
const selectedSub = ref(null)
const filterStatus = ref('Tous')
const form = ref({ client: null, plan: null, cycle: 'Mensuel', price: 500000, startDate: null, trial: false })

const stats = [
  { label: 'Abonnements actifs', value: '47', color: 'text-blue-600' },
  { label: 'MRR (XOF)', value: '23.4M', color: 'text-green-600' },
  { label: 'Renouvellements ce mois', value: '12', color: 'text-purple-600' },
  { label: 'Taux de churn', value: '2.1%', color: 'text-red-500' },
]

const subscriptions = ref([
  { client: 'Groupe Sonatel', plan: 'Enterprise', cycle: 'Annuel', amount: '8 400 000', nextRenewal: '2026-12-01', status: 'Actif', invoices: [{ ref: 'FAC-001', amount: '8 400 000', date: '2026-01-01', status: 'Payée' }] },
  { client: 'Orange CI', plan: 'Pro', cycle: 'Mensuel', amount: '450 000', nextRenewal: '2026-06-01', status: 'Actif', invoices: [{ ref: 'FAC-015', amount: '450 000', date: '2026-05-01', status: 'Payée' }] },
  { client: 'MTN Cameroun', plan: 'Pro', cycle: 'Trimestriel', amount: '1 200 000', nextRenewal: '2026-06-15', status: 'Actif', invoices: [] },
  { client: 'Ecobank Togo', plan: 'Starter', cycle: 'Mensuel', amount: '150 000', nextRenewal: '2026-06-03', status: 'En essai', invoices: [] },
  { client: 'Air Côte d\'Ivoire', plan: 'Enterprise', cycle: 'Annuel', amount: '5 000 000', nextRenewal: '2026-06-30', status: 'Suspendu', invoices: [] },
  { client: 'BCEAO', plan: 'Enterprise', cycle: 'Annuel', amount: '12 000 000', nextRenewal: '2026-12-31', status: 'Actif', invoices: [] },
  { client: 'Côte d\'Ivoire Télécom', plan: 'Pro', cycle: 'Mensuel', amount: '350 000', nextRenewal: '2025-11-01', status: 'Résilié', invoices: [] },
  { client: 'Gainde Intégral', plan: 'Starter', cycle: 'Mensuel', amount: '120 000', nextRenewal: '2026-06-10', status: 'Actif', invoices: [] },
])

const filteredSubs = computed(() => filterStatus.value === 'Tous' ? subscriptions.value : subscriptions.value.filter(s => s.status === filterStatus.value))
const expiringCount = computed(() => subscriptions.value.filter(s => s.status === 'Actif' && isExpiringSoon(s.nextRenewal)).length)

const recognitionSchedule = [
  { period: 'Jan 2026', deferred: '8 400 000', recognized: '700 000', status: 'Reconnu' },
  { period: 'Fév 2026', deferred: '7 700 000', recognized: '700 000', status: 'Reconnu' },
  { period: 'Mar 2026', deferred: '7 000 000', recognized: '700 000', status: 'Reconnu' },
  { period: 'Avr 2026', deferred: '6 300 000', recognized: '700 000', status: 'Reconnu' },
  { period: 'Mai 2026', deferred: '5 600 000', recognized: '700 000', status: 'En cours' },
  { period: 'Jun 2026', deferred: '4 900 000', recognized: '700 000', status: 'Planifié' },
]

const isExpiringSoon = (date) => {
  const d = new Date(date)
  const now = new Date()
  return (d - now) / (1000 * 60 * 60 * 24) <= 30 && d > now
}

const subSeverity = (s) => ({ Actif: 'success', 'En essai': 'info', Suspendu: 'warn', Résilié: 'danger' }[s] || 'secondary')
const renewSub = (s) => { /* trigger renewal */ }
</script>
