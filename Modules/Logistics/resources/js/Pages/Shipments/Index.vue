<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Expéditions</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">Gestion et suivi de toutes les expéditions</p>
      </div>
      <div class="flex gap-2">
        <Button label="Exporter" icon="pi pi-download" severity="secondary" outlined @click="exportData" />
        <Button v-if="canCreate" label="Nouvelle expédition" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card v-for="kpi in kpis" :key="kpi.label">
        <template #content>
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ kpi.label }}</p>
              <p class="mt-1 text-2xl font-bold" :class="kpi.color">{{ kpi.value }}</p>
              <p class="mt-1 text-xs text-gray-500">{{ kpi.sub }}</p>
            </div>
            <span class="flex h-12 w-12 items-center justify-center rounded-xl" :class="kpi.bg">
              <i :class="kpi.icon" class="text-xl" :style="{ color: kpi.iconColor }"></i>
            </span>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filtres -->
    <Card>
      <template #content>
        <div class="flex flex-wrap gap-3">
          <InputText v-model="search" placeholder="Rechercher une expédition..." class="w-64" />
          <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Tous les statuts" class="w-48" />
          <Select v-model="filterCarrier" :options="carrierOptions" optionLabel="label" optionValue="value" placeholder="Tous les transporteurs" class="w-52" />
          <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" outlined @click="resetFilters" />
        </div>
      </template>
    </Card>

    <!-- Tableau des expéditions -->
    <Card>
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-send text-blue-600"></i>
          <span>Liste des expéditions ({{ filteredShipments.length }})</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredShipments"
          paginator
          :rows="15"
          :rowsPerPageOptions="[10, 15, 25, 50]"
          stripedRows
          rowHover
          @row-click="openDetail"
          class="cursor-pointer"
        >
          <Column field="ref" header="#" style="width:120px">
            <template #body="{ data }">
              <span class="font-mono text-sm font-semibold text-blue-700 dark:text-blue-400">{{ data.ref }}</span>
            </template>
          </Column>
          <Column field="expediteur" header="Expéditeur" style="min-width:140px" />
          <Column field="destinataire" header="Destinataire" style="min-width:140px" />
          <Column field="origine" header="Origine" style="min-width:120px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i class="pi pi-map-marker text-xs text-gray-400"></i>
                <span>{{ data.origine }}</span>
              </div>
            </template>
          </Column>
          <Column field="destination" header="Destination" style="min-width:120px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i class="pi pi-map-marker text-xs text-green-500"></i>
                <span>{{ data.destination }}</span>
              </div>
            </template>
          </Column>
          <Column field="transporteur" header="Transporteur" style="min-width:140px" />
          <Column field="dateDepart" header="Départ" style="width:110px" />
          <Column field="datePrevue" header="Prévue" style="width:110px">
            <template #body="{ data }">
              <span :class="data.retard ? 'text-red-600 font-semibold' : ''">{{ data.datePrevue }}</span>
            </template>
          </Column>
          <Column field="statut" header="Statut" style="width:150px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="statusSeverity(data.statut)" />
            </template>
          </Column>
          <Column field="tracking" header="Tracking" style="width:130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ data.tracking }}</span>
            </template>
          </Column>
          <Column header="Actions" style="width:100px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-eye" rounded text size="small" @click.stop="openDetail({ data })" />
                <Button icon="pi pi-pencil" rounded text size="small" severity="secondary" @click.stop="editShipment(data)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Drawer détail expédition -->
    <Drawer v-model:visible="showDetailDrawer" position="right" style="width:600px" :header="selectedShipment?.ref || 'Détail expédition'">
      <div v-if="selectedShipment" class="space-y-6">
        <!-- Infos générales -->
        <Card>
          <template #title>Informations générales</template>
          <template #content>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div><p class="text-gray-500">Référence</p><p class="font-semibold">{{ selectedShipment.ref }}</p></div>
              <div><p class="text-gray-500">Tracking</p><p class="font-mono font-semibold">{{ selectedShipment.tracking }}</p></div>
              <div><p class="text-gray-500">Expéditeur</p><p class="font-semibold">{{ selectedShipment.expediteur }}</p></div>
              <div><p class="text-gray-500">Destinataire</p><p class="font-semibold">{{ selectedShipment.destinataire }}</p></div>
              <div><p class="text-gray-500">Origine</p><p class="font-semibold">{{ selectedShipment.origine }}</p></div>
              <div><p class="text-gray-500">Destination</p><p class="font-semibold">{{ selectedShipment.destination }}</p></div>
              <div><p class="text-gray-500">Transporteur</p><p class="font-semibold">{{ selectedShipment.transporteur }}</p></div>
              <div><p class="text-gray-500">Statut</p><Tag :value="selectedShipment.statut" :severity="statusSeverity(selectedShipment.statut)" /></div>
            </div>
          </template>
        </Card>

        <!-- Timeline suivi -->
        <Card>
          <template #title><i class="pi pi-history mr-2 text-blue-500"></i>Historique de suivi</template>
          <template #content>
            <div class="space-y-4">
              <div v-for="(evt, idx) in selectedShipment.timeline" :key="idx" class="flex gap-3">
                <div class="flex flex-col items-center">
                  <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="idx === 0 ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                    <i :class="evt.icon" class="text-xs"></i>
                  </span>
                  <div v-if="idx < selectedShipment.timeline.length - 1" class="w-0.5 flex-1 bg-gray-200 dark:bg-gray-700 mt-1"></div>
                </div>
                <div class="pb-4">
                  <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ evt.label }}</p>
                  <p class="text-xs text-gray-500">{{ evt.lieu }} — {{ evt.date }}</p>
                  <p v-if="evt.note" class="text-xs text-gray-400 mt-0.5">{{ evt.note }}</p>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Documents -->
        <Card>
          <template #title><i class="pi pi-file mr-2 text-green-500"></i>Documents</template>
          <template #content>
            <div class="space-y-2">
              <div v-for="doc in selectedShipment.documents" :key="doc.name" class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <div class="flex items-center gap-2">
                  <i class="pi pi-file-pdf text-red-500"></i>
                  <span class="text-sm font-medium">{{ doc.name }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <Tag :value="doc.statut" :severity="doc.statut === 'Disponible' ? 'success' : 'warn'" size="small" />
                  <Button v-if="doc.statut === 'Disponible'" icon="pi pi-download" rounded text size="small" />
                </div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </Drawer>

    <!-- Dialog création -->
    <Dialog v-model:visible="showCreateDialog" header="Nouvelle expédition" style="width:680px" :modal="true">
      <div class="grid grid-cols-2 gap-4 pt-2">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Expéditeur</label>
          <InputText v-model="form.expediteur" placeholder="Nom expéditeur" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Destinataire</label>
          <InputText v-model="form.destinataire" placeholder="Nom destinataire" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Ville d'origine</label>
          <Select v-model="form.origine" :options="villesOptions" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Ville de destination</label>
          <Select v-model="form.destination" :options="villesOptions" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Transporteur</label>
          <Select v-model="form.transporteur" :options="carrierOptions" optionLabel="label" optionValue="label" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date de départ prévue</label>
          <InputText v-model="form.dateDepart" type="date" />
        </div>
        <div class="col-span-2 flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Description marchandise</label>
          <Textarea v-model="form.description" rows="3" placeholder="Nature des marchandises, poids, volume..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showCreateDialog = false" />
        <Button label="Créer l'expédition" icon="pi pi-check" @click="createShipment" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
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
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import StrategicContext from '@/Components/StrategicContext.vue'
import { useStrategicLink } from '@/composables/useStrategicLink'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['logistics-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const user = computed(() => page.props.auth?.user)

const { objective } = useStrategicLink('Logistics/Shipment', item?.id)

const search = ref('')
const filterStatus = ref(null)
const filterCarrier = ref(null)
const showCreateDialog = ref(false)
const showDetailDrawer = ref(false)
const selectedShipment = ref<any>(null)

const form = ref({ expediteur: '', destinataire: '', origine: '', destination: '', transporteur: '', dateDepart: '', description: '' })

const statusOptions = [
  { label: 'En préparation', value: 'En préparation' },
  { label: 'Collecté', value: 'Collecté' },
  { label: 'En transit', value: 'En transit' },
  { label: 'En douane', value: 'En douane' },
  { label: 'Livré', value: 'Livré' },
  { label: 'Retourné', value: 'Retourné' },
]

const carrierOptions = [
  { label: 'Sahel Express', value: 'Sahel Express' },
  { label: 'DHL West Africa', value: 'DHL West Africa' },
  { label: 'Bolloré Transport', value: 'Bolloré Transport' },
  { label: 'Maersk Afrique', value: 'Maersk Afrique' },
  { label: 'Air Ivoire Cargo', value: 'Air Ivoire Cargo' },
]

const villesOptions = ['Dakar', 'Abidjan', 'Douala', 'Lomé', 'Cotonou', 'Bamako', 'Ouagadougou', 'Conakry', 'Lagos', 'Accra', 'Libreville']

const kpis = ref([
  { label: 'Expéditions actives', value: '147', sub: 'En cours de traitement', color: 'text-blue-600', bg: 'bg-blue-100 dark:bg-blue-900', icon: 'pi pi-send', iconColor: '#2563eb' },
  { label: 'Livrées ce mois', value: '312', sub: '+18% vs mois dernier', color: 'text-green-600', bg: 'bg-green-100 dark:bg-green-900', icon: 'pi pi-check-circle', iconColor: '#16a34a' },
  { label: 'En retard', value: '9', sub: '3 critiques', color: 'text-red-600', bg: 'bg-red-100 dark:bg-red-900', icon: 'pi pi-clock', iconColor: '#dc2626' },
  { label: 'Coût moyen XOF', value: '47 500', sub: 'Par expédition', color: 'text-yellow-600', bg: 'bg-yellow-100 dark:bg-yellow-900', icon: 'pi pi-wallet', iconColor: '#ca8a04' },
])

const shipments = ref([
  { ref: 'EXP-2501', expediteur: 'Sénélec', destinataire: 'CIE Abidjan', origine: 'Dakar', destination: 'Abidjan', transporteur: 'Sahel Express', dateDepart: '20/05/2026', datePrevue: '24/05/2026', statut: 'En transit', tracking: 'SX-884421', retard: false,
    timeline: [
      { label: 'En transit — Bamako', lieu: 'Bamako, Mali', date: '22/05/2026 14:30', icon: 'pi pi-truck', note: 'Passage frontière OK' },
      { label: 'Collecté — Dakar', lieu: 'Dakar, Sénégal', date: '20/05/2026 09:00', icon: 'pi pi-box', note: 'Poids vérifié: 340 kg' },
      { label: 'Commande créée', lieu: 'Système', date: '19/05/2026 16:45', icon: 'pi pi-plus', note: null },
    ],
    documents: [
      { name: 'Connaissement (BL)', statut: 'Disponible' },
      { name: 'Facture commerciale', statut: 'Disponible' },
      { name: 'Certificat d\'origine', statut: 'En attente' },
    ]
  },
  { ref: 'EXP-2502', expediteur: 'Unilever CI', destinataire: 'Bolloré Douala', origine: 'Abidjan', destination: 'Douala', transporteur: 'Maersk Afrique', dateDepart: '18/05/2026', datePrevue: '22/05/2026', statut: 'En douane', tracking: 'MK-229034', retard: true,
    timeline: [
      { label: 'Bloqué en douane', lieu: 'Port de Douala', date: '22/05/2026 08:00', icon: 'pi pi-times-circle', note: 'Certificat origine manquant' },
      { label: 'Arrivée port', lieu: 'Douala, Cameroun', date: '21/05/2026 20:00', icon: 'pi pi-map-marker', note: null },
      { label: 'Départ maritime', lieu: 'Port Abidjan', date: '18/05/2026 12:00', icon: 'pi pi-send', note: null },
    ],
    documents: [
      { name: 'Connaissement (BL)', statut: 'Disponible' },
      { name: 'Certificat d\'origine', statut: 'En attente' },
      { name: 'Liste de colisage', statut: 'Disponible' },
    ]
  },
  { ref: 'EXP-2503', expediteur: 'Sonatel', destinataire: 'Togocel Lomé', origine: 'Dakar', destination: 'Lomé', transporteur: 'Sahel Express', dateDepart: '21/05/2026', datePrevue: '25/05/2026', statut: 'En préparation', tracking: 'SX-884455', retard: false,
    timeline: [
      { label: 'Commande créée', lieu: 'Dakar', date: '21/05/2026 11:00', icon: 'pi pi-plus', note: null },
    ],
    documents: [
      { name: 'Bon de commande', statut: 'Disponible' },
    ]
  },
  { ref: 'EXP-2504', expediteur: 'Orange CI', destinataire: 'MTN Ghana', origine: 'Abidjan', destination: 'Accra', transporteur: 'DHL West Africa', dateDepart: '17/05/2026', datePrevue: '19/05/2026', statut: 'Livré', tracking: 'DH-771293', retard: false,
    timeline: [
      { label: 'Livré au destinataire', lieu: 'Accra, Ghana', date: '19/05/2026 14:00', icon: 'pi pi-check-circle', note: 'Signé par M. Asante' },
      { label: 'En transit', lieu: 'Frontière CI-GH', date: '18/05/2026 10:30', icon: 'pi pi-truck', note: null },
      { label: 'Collecté', lieu: 'Abidjan', date: '17/05/2026 08:00', icon: 'pi pi-box', note: null },
    ],
    documents: [
      { name: 'Connaissement (BL)', statut: 'Disponible' },
      { name: 'Bon de livraison signé', statut: 'Disponible' },
      { name: 'Facture commerciale', statut: 'Disponible' },
    ]
  },
  { ref: 'EXP-2505', expediteur: 'Castel Dakar', destinataire: 'Sobebra Cotonou', origine: 'Dakar', destination: 'Cotonou', transporteur: 'Bolloré Transport', dateDepart: '16/05/2026', datePrevue: '20/05/2026', statut: 'Retourné', tracking: 'BT-445678', retard: true,
    timeline: [
      { label: 'Retour initié', lieu: 'Cotonou', date: '21/05/2026 09:00', icon: 'pi pi-replay', note: 'Destinataire injoignable' },
      { label: 'Tentative de livraison', lieu: 'Cotonou', date: '20/05/2026 15:00', icon: 'pi pi-times', note: 'Absent à l\'adresse' },
    ],
    documents: [
      { name: 'Avis de non-livraison', statut: 'Disponible' },
    ]
  },
  { ref: 'EXP-2506', expediteur: 'CFAO Dakar', destinataire: 'Tractafric Libreville', origine: 'Dakar', destination: 'Libreville', transporteur: 'Air Ivoire Cargo', dateDepart: '22/05/2026', datePrevue: '23/05/2026', statut: 'Collecté', tracking: 'AI-339021', retard: false,
    timeline: [
      { label: 'Collecté aéroport', lieu: 'Aéroport LSS Dakar', date: '22/05/2026 16:00', icon: 'pi pi-box', note: 'Poids: 85 kg' },
    ],
    documents: [
      { name: 'Lettre de transport aérien (LTA)', statut: 'Disponible' },
    ]
  },
])

const filteredShipments = computed(() => {
  return shipments.value.filter(s => {
    const matchSearch = !search.value || [s.ref, s.expediteur, s.destinataire, s.tracking].some(v => v.toLowerCase().includes(search.value.toLowerCase()))
    const matchStatus = !filterStatus.value || s.statut === filterStatus.value
    const matchCarrier = !filterCarrier.value || s.transporteur === filterCarrier.value
    return matchSearch && matchStatus && matchCarrier
  })
})

function statusSeverity(statut: string) {
  const map: Record<string, string> = {
    'En préparation': 'secondary',
    'Collecté': 'info',
    'En transit': 'info',
    'En douane': 'warn',
    'Livré': 'success',
    'Retourné': 'danger',
  }
  return map[statut] || 'secondary'
}

function openDetail(event: any) {
  selectedShipment.value = event.data
  showDetailDrawer.value = true
}

function editShipment(data: any) {
  // Ouvrir formulaire d'édition
}

function resetFilters() {
  search.value = ''
  filterStatus.value = null
  filterCarrier.value = null
}

function exportData() {
  // Export CSV
}

function createShipment() {
  showCreateDialog.value = false
  form.value = { expediteur: '', destinataire: '', origine: '', destination: '', transporteur: '', dateDepart: '', description: '' }
}
</script>
