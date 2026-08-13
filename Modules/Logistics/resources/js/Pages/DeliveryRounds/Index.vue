<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Tournées de livraison</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">Planification et suivi des tournées — {{ today }}</p>
      </div>
      <div class="flex gap-2">
        <Button label="Optimiser les tournées" icon="pi pi-cog" severity="secondary" outlined @click="optimizeAll" />
        <Button v-if="canCreate" label="Nouvelle tournée" icon="pi pi-plus" @click="showCreateDialog = true" />
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

    <!-- Tableau des tournées -->
    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="pi pi-truck text-green-600"></i>
            <span>Tournées du jour ({{ rounds.length }})</span>
          </div>
          <div class="flex gap-2">
            <InputText v-model="search" placeholder="Rechercher..." class="w-52" size="small" />
            <Select v-model="filterStatut" :options="statutOptions" optionLabel="label" optionValue="value" placeholder="Tous statuts" class="w-40" size="small" />
          </div>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredRounds"
          paginator
          :rows="10"
          stripedRows
          rowHover
          @row-click="openDetail"
          class="cursor-pointer"
        >
          <Column field="ref" header="Tournée" style="width:110px">
            <template #body="{ data }">
              <span class="font-mono text-sm font-bold text-green-700 dark:text-green-400">{{ data.ref }}</span>
            </template>
          </Column>
          <Column field="chauffeur" header="Chauffeur" style="min-width:160px">
            <template #body="{ data }">
              <div>
                <p class="font-semibold text-gray-900 dark:text-white">{{ data.chauffeur }}</p>
                <p class="text-xs text-gray-500">{{ data.telephone }}</p>
              </div>
            </template>
          </Column>
          <Column field="vehicule" header="Véhicule" style="min-width:180px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i class="pi pi-car text-xs text-gray-400"></i>
                <span class="text-sm">{{ data.vehicule }}</span>
              </div>
            </template>
          </Column>
          <Column field="depart" header="Heure départ" style="width:120px" />
          <Column field="nbArrets" header="Arrêts" style="width:80px">
            <template #body="{ data }">
              <span class="font-semibold">{{ data.nbArrets }}</span>
            </template>
          </Column>
          <Column field="km" header="Distance" style="width:90px">
            <template #body="{ data }">
              <span class="text-sm">{{ data.km }} km</span>
            </template>
          </Column>
          <Column field="statut" header="Statut" style="width:130px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="statutSeverity(data.statut)" />
            </template>
          </Column>
          <Column header="Avancement" style="min-width:180px">
            <template #body="{ data }">
              <div>
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                  <span>{{ data.done }}/{{ data.nbArrets }} livraisons</span>
                  <span>{{ Math.round((data.done / data.nbArrets) * 100) }}%</span>
                </div>
                <ProgressBar :value="Math.round((data.done / data.nbArrets) * 100)" class="h-2" />
              </div>
            </template>
          </Column>
          <Column header="Actions" style="width:130px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-eye" rounded text size="small" @click.stop="openDetail({ data })" v-tooltip.top="'Voir détail'" />
                <Button icon="pi pi-map" rounded text size="small" severity="info" @click.stop="optimizeRound(data)" v-tooltip.top="'Optimiser l\'itinéraire'" />
                <Button icon="pi pi-pencil" rounded text size="small" severity="secondary" @click.stop="editRound(data)" v-tooltip.top="'Modifier'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Drawer détail tournée -->
    <Drawer v-model:visible="showDetailDrawer" position="right" style="width:560px" :header="selectedRound ? `Tournée ${selectedRound.ref} — ${selectedRound.chauffeur}` : 'Détail tournée'">
      <div v-if="selectedRound" class="space-y-5">
        <!-- Info véhicule -->
        <Card>
          <template #title>Informations de la tournée</template>
          <template #content>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div><p class="text-gray-500">Chauffeur</p><p class="font-semibold">{{ selectedRound.chauffeur }}</p></div>
              <div><p class="text-gray-500">Téléphone</p><p class="font-semibold">{{ selectedRound.telephone }}</p></div>
              <div><p class="text-gray-500">Véhicule</p><p class="font-semibold">{{ selectedRound.vehicule }}</p></div>
              <div><p class="text-gray-500">Départ</p><p class="font-semibold">{{ selectedRound.depart }}</p></div>
              <div><p class="text-gray-500">Distance totale</p><p class="font-semibold">{{ selectedRound.km }} km</p></div>
              <div><p class="text-gray-500">Statut</p><Tag :value="selectedRound.statut" :severity="statutSeverity(selectedRound.statut)" /></div>
            </div>
            <div class="mt-4">
              <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Avancement global</span>
                <span>{{ selectedRound.done }}/{{ selectedRound.nbArrets }} livraisons</span>
              </div>
              <ProgressBar :value="Math.round((selectedRound.done / selectedRound.nbArrets) * 100)" class="h-3" />
            </div>
          </template>
        </Card>

        <!-- Liste des arrêts -->
        <Card>
          <template #title><i class="pi pi-map-marker mr-2 text-blue-500"></i>Arrêts de livraison</template>
          <template #content>
            <div class="space-y-3">
              <div
                v-for="(stop, idx) in selectedRound.stops"
                :key="idx"
                class="flex gap-3 rounded-lg border p-3 text-sm"
                :class="stop.statut === 'Livré' ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                  : stop.statut === 'Absent' ? 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950'
                  : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800'"
              >
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                  :class="stop.statut === 'Livré' ? 'bg-green-600 text-white' : stop.statut === 'Absent' ? 'bg-orange-500 text-white' : 'bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300'">
                  {{ idx + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <p class="font-semibold text-gray-900 dark:text-white">{{ stop.client }}</p>
                      <p class="text-xs text-gray-500">{{ stop.adresse }}</p>
                    </div>
                    <Tag :value="stop.statut" :severity="stop.statut === 'Livré' ? 'success' : stop.statut === 'Absent' ? 'warn' : 'secondary'" size="small" />
                  </div>
                  <p class="text-xs text-gray-400 mt-1"><i class="pi pi-clock mr-1"></i>Prévu: {{ stop.heurePrevue }}</p>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <div class="flex gap-2">
          <Button label="Optimiser l'itinéraire" icon="pi pi-map" class="flex-1" @click="optimizeRound(selectedRound)" />
          <Button label="Contacter chauffeur" icon="pi pi-phone" severity="secondary" outlined class="flex-1" />
        </div>
      </div>
    </Drawer>

    <!-- Dialog création tournée -->
    <Dialog v-model:visible="showCreateDialog" header="Nouvelle tournée de livraison" style="width:640px" :modal="true">
      <div class="grid grid-cols-2 gap-4 pt-2">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date de la tournée</label>
          <InputText v-model="form.date" type="date" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Heure de départ</label>
          <InputText v-model="form.depart" type="time" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Chauffeur</label>
          <Select v-model="form.chauffeur" :options="chauffeurOptions" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Véhicule</label>
          <Select v-model="form.vehicule" :options="vehiculeOptions" placeholder="Sélectionner..." />
        </div>
        <div class="col-span-2 flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Zone de livraison</label>
          <Select v-model="form.zone" :options="zoneOptions" placeholder="Sélectionner la zone..." />
        </div>
        <div class="col-span-2 flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Notes / Instructions</label>
          <Textarea v-model="form.notes" rows="2" placeholder="Instructions pour le chauffeur..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showCreateDialog = false" />
        <Button label="Créer la tournée" icon="pi pi-check" @click="createRound" />
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
import ProgressBar from 'primevue/progressbar'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['logistics-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const user = computed(() => page.props.auth?.user)

const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const search = ref('')
const filterStatut = ref(null)
const showCreateDialog = ref(false)
const showDetailDrawer = ref(false)
const selectedRound = ref<any>(null)
const form = ref({ date: '', depart: '', chauffeur: '', vehicule: '', zone: '', notes: '' })

const statutOptions = [
  { label: 'Planifiée', value: 'Planifiée' },
  { label: 'En cours', value: 'En cours' },
  { label: 'Terminée', value: 'Terminée' },
  { label: 'Annulée', value: 'Annulée' },
]

const chauffeurOptions = ['Moussa Diallo', 'Fatou Konaté', 'Ibrahima Sow', 'Aminata Traoré', 'Boubacar Bah']
const vehiculeOptions = ['Ford Transit SN-4812-DK', 'Toyota Hiace SN-2234-DK', 'Mercedes Sprinter SN-6701-DK', 'Renault Master CI-9983-AB']
const zoneOptions = ['Dakar Plateau', 'Dakar Banlieue Nord', 'Dakar Banlieue Sud', 'Thiès', 'Mbour', 'Abidjan Centre', 'Abidjan Périphérie']

const kpis = ref([
  { label: "Tournées aujourd'hui", value: '6', sub: '4 en cours, 2 planifiées', color: 'text-green-600', bg: 'bg-green-100 dark:bg-green-900', icon: 'pi pi-truck', iconColor: '#16a34a' },
  { label: 'Livraisons planifiées', value: '78', sub: 'Ce jour', color: 'text-blue-600', bg: 'bg-blue-100 dark:bg-blue-900', icon: 'pi pi-box', iconColor: '#2563eb' },
  { label: 'Taux réalisation', value: '84%', sub: '+3% vs hier', color: 'text-purple-600', bg: 'bg-purple-100 dark:bg-purple-900', icon: 'pi pi-chart-bar', iconColor: '#7c3aed' },
  { label: 'CO₂ économisé', value: '12 kg', sub: 'Vs. tournées individuelles', color: 'text-teal-600', bg: 'bg-teal-100 dark:bg-teal-900', icon: 'pi pi-leaf', iconColor: '#0d9488' },
])

const rounds = ref([
  {
    ref: 'T-0124', chauffeur: 'Moussa Diallo', telephone: '+221 77 432 1001', vehicule: 'Ford Transit SN-4812-DK',
    depart: '07:30', nbArrets: 12, km: 87, done: 9, statut: 'En cours',
    stops: [
      { client: 'Pharmacie du Centre', adresse: 'Av. Pompidou, Dakar Plateau', heurePrevue: '08:00', statut: 'Livré' },
      { client: 'Supermarché Casino', adresse: 'Rue de Thiong, Dakar', heurePrevue: '08:30', statut: 'Livré' },
      { client: 'Librairie Clairafrique', adresse: 'Pl. de l\'Indépendance, Dakar', heurePrevue: '09:00', statut: 'Livré' },
      { client: 'Restaurant La Savane', adresse: 'Almadies, Dakar', heurePrevue: '10:00', statut: 'À faire' },
      { client: 'Hotel Terrou-Bi', adresse: 'Corniche, Dakar', heurePrevue: '10:45', statut: 'À faire' },
      { client: 'Boutique Sokhna', adresse: 'Médina, Dakar', heurePrevue: '11:30', statut: 'Absent' },
    ],
  },
  {
    ref: 'T-0125', chauffeur: 'Fatou Konaté', telephone: '+221 76 888 4422', vehicule: 'Toyota Hiace SN-2234-DK',
    depart: '08:00', nbArrets: 8, km: 62, done: 8, statut: 'Terminée',
    stops: [
      { client: 'Clinique Pasteur', adresse: 'Fann, Dakar', heurePrevue: '08:30', statut: 'Livré' },
      { client: 'École Sainte-Marie', adresse: 'Hann, Dakar', heurePrevue: '09:15', statut: 'Livré' },
      { client: 'SGBS Agence Centrale', adresse: 'Pl. de l\'Indépendance, Dakar', heurePrevue: '10:00', statut: 'Livré' },
    ],
  },
  {
    ref: 'T-0126', chauffeur: 'Ibrahima Sow', telephone: '+221 70 211 3344', vehicule: 'Mercedes Sprinter SN-6701-DK',
    depart: '06:45', nbArrets: 15, km: 110, done: 5, statut: 'En cours',
    stops: [
      { client: 'Grands Moulins de Dakar', adresse: 'Zone Industrielle, Dakar', heurePrevue: '07:30', statut: 'Livré' },
      { client: 'CFAO Dakar', adresse: 'Autoroute, Dakar', heurePrevue: '08:15', statut: 'Livré' },
      { client: 'Total Energies Dakar', adresse: 'Rue Colbert, Dakar', heurePrevue: '09:00', statut: 'À faire' },
      { client: 'Auchan Dakar', adresse: 'Zone Sacré-Cœur, Dakar', heurePrevue: '10:00', statut: 'À faire' },
    ],
  },
  {
    ref: 'T-0127', chauffeur: 'Aminata Traoré', telephone: '+225 07 112 3456', vehicule: 'Renault Master CI-9983-AB',
    depart: '09:00', nbArrets: 10, km: 74, done: 0, statut: 'Planifiée',
    stops: [
      { client: 'Cocody Supermarché', adresse: 'Cocody, Abidjan', heurePrevue: '09:30', statut: 'À faire' },
      { client: 'Pharmacie Blondey', adresse: 'Plateau, Abidjan', heurePrevue: '10:15', statut: 'À faire' },
      { client: 'CFAO Abidjan', adresse: 'Marcory, Abidjan', heurePrevue: '11:00', statut: 'À faire' },
    ],
  },
])

const filteredRounds = computed(() => {
  return rounds.value.filter(r => {
    const matchSearch = !search.value || [r.ref, r.chauffeur, r.vehicule].some(v => v.toLowerCase().includes(search.value.toLowerCase()))
    const matchStatut = !filterStatut.value || r.statut === filterStatut.value
    return matchSearch && matchStatut
  })
})

function statutSeverity(statut: string) {
  const map: Record<string, string> = { 'Planifiée': 'secondary', 'En cours': 'info', 'Terminée': 'success', 'Annulée': 'danger' }
  return map[statut] || 'secondary'
}

function openDetail(event: any) {
  selectedRound.value = event.data
  showDetailDrawer.value = true
}

function optimizeRound(_data: any) {
  // Appel optimisation itinéraire
}

function optimizeAll() {
  // Optimisation globale de toutes les tournées
}

function editRound(_data: any) {}

function createRound() {
  showCreateDialog.value = false
  form.value = { date: '', depart: '', chauffeur: '', vehicule: '', zone: '', notes: '' }
}
</script>
