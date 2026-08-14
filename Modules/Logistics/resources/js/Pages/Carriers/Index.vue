<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Transporteurs</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">Gestion des partenaires et prestataires de transport</p>
      </div>
      <div class="flex gap-2">
        <Button label="Exporter" icon="pi pi-download" severity="secondary" outlined @click="exportData" />
        <Button v-if="canCreate" label="Nouveau transporteur" icon="pi pi-plus" @click="showCreateDialog = true" />
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
          <InputText v-model="search" placeholder="Rechercher un transporteur..." class="w-64" />
          <Select v-model="filterType" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Tous les types" class="w-48" />
          <Select v-model="filterStatut" :options="statutOptions" optionLabel="label" optionValue="value" placeholder="Tous les statuts" class="w-44" />
          <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" outlined @click="resetFilters" />
        </div>
      </template>
    </Card>

    <!-- Tableau transporteurs -->
    <Card>
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-truck text-blue-600"></i>
          <span>Transporteurs partenaires ({{ filteredCarriers.length }})</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredCarriers"
          paginator
          :rows="12"
          stripedRows
          rowHover
          @row-click="openDetail"
          class="cursor-pointer"
        >
          <Column field="nom" header="Transporteur" style="min-width:180px">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 font-bold text-sm">
                  {{ data.nom.charAt(0) }}
                </span>
                <div>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ data.nom }}</p>
                  <p class="text-xs text-gray-500">{{ data.contact }}</p>
                </div>
              </div>
            </template>
          </Column>
          <Column field="type" header="Type" style="width:130px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i :class="typeIcon(data.type)" class="text-gray-500 text-sm"></i>
                <span class="text-sm">{{ data.type }}</span>
              </div>
            </template>
          </Column>
          <Column field="zones" header="Zones couvertes" style="min-width:200px">
            <template #body="{ data }">
              <div class="flex flex-wrap gap-1">
                <span v-for="zone in data.zones.slice(0, 3)" :key="zone" class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-400">{{ zone }}</span>
                <span v-if="data.zones.length > 3" class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500">+{{ data.zones.length - 3 }}</span>
              </div>
            </template>
          </Column>
          <Column field="delaiMoyen" header="Délai moyen" style="width:120px" />
          <Column field="ponctualite" header="Ponctualité" style="width:150px">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <ProgressBar :value="data.ponctualite" class="flex-1 h-2" />
                <span class="text-sm font-semibold w-10 text-right" :class="data.ponctualite >= 90 ? 'text-green-600' : data.ponctualite >= 75 ? 'text-yellow-600' : 'text-red-600'">{{ data.ponctualite }}%</span>
              </div>
            </template>
          </Column>
          <Column field="tarifKg" header="Tarif/kg (XOF)" style="width:150px">
            <template #body="{ data }">
              <span class="font-semibold text-gray-800 dark:text-gray-200">{{ data.tarifKg.toLocaleString('fr-FR') }} XOF</span>
            </template>
          </Column>
          <Column field="statut" header="Statut" style="width:120px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="data.statut === 'Actif' ? 'success' : data.statut === 'Suspendu' ? 'danger' : 'warn'" />
            </template>
          </Column>
          <Column header="Actions" style="width:100px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-eye" rounded text size="small" @click.stop="openDetail({ data })" />
                <Button icon="pi pi-pencil" rounded text size="small" severity="secondary" @click.stop="editCarrier(data)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Drawer détail transporteur -->
    <Drawer v-model:visible="showDetailDrawer" position="right" style="width:580px" :header="selectedCarrier?.nom || 'Détail transporteur'">
      <div v-if="selectedCarrier" class="space-y-5">
        <Card>
          <template #title>Informations générales</template>
          <template #content>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div><p class="text-gray-500">Type</p><p class="font-semibold">{{ selectedCarrier.type }}</p></div>
              <div><p class="text-gray-500">Contact</p><p class="font-semibold">{{ selectedCarrier.contact }}</p></div>
              <div><p class="text-gray-500">Délai moyen</p><p class="font-semibold">{{ selectedCarrier.delaiMoyen }}</p></div>
              <div><p class="text-gray-500">Tarif/kg</p><p class="font-semibold">{{ selectedCarrier.tarifKg.toLocaleString('fr-FR') }} XOF</p></div>
              <div class="col-span-2">
                <p class="text-gray-500 mb-1">Zones couvertes</p>
                <div class="flex flex-wrap gap-1">
                  <span v-for="zone in selectedCarrier.zones" :key="zone" class="rounded-full bg-blue-100 dark:bg-blue-900 px-2 py-0.5 text-xs text-blue-700 dark:text-blue-300">{{ zone }}</span>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <Card>
          <template #title><i class="pi pi-chart-line mr-2 text-green-500"></i>Métriques de performance</template>
          <template #content>
            <div class="space-y-4">
              <div v-for="metric in selectedCarrier.performance" :key="metric.label" class="flex items-center gap-3">
                <span class="w-40 text-sm text-gray-600 dark:text-gray-400">{{ metric.label }}</span>
                <ProgressBar :value="metric.value" class="flex-1 h-3" />
                <span class="text-sm font-bold w-12 text-right" :class="metric.value >= 85 ? 'text-green-600' : 'text-yellow-600'">{{ metric.value }}%</span>
              </div>
            </div>
          </template>
        </Card>

        <Card>
          <template #title><i class="pi pi-exclamation-triangle mr-2 text-orange-500"></i>Incidents récents</template>
          <template #content>
            <div v-if="selectedCarrier.incidents.length === 0" class="text-sm text-gray-400 italic">Aucun incident signalé.</div>
            <div v-else class="space-y-2">
              <div v-for="inc in selectedCarrier.incidents" :key="inc.id" class="rounded-lg border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-950 p-3 text-sm">
                <div class="flex items-start justify-between">
                  <div>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ inc.type }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ inc.ref }} — {{ inc.date }}</p>
                  </div>
                  <Tag :value="inc.resolution" :severity="inc.resolution === 'Résolu' ? 'success' : 'warn'" size="small" />
                </div>
              </div>
            </div>
          </template>
        </Card>

        <Card>
          <template #title><i class="pi pi-tag mr-2 text-purple-500"></i>Grille tarifaire</template>
          <template #content>
            <DataTable :value="selectedCarrier.tarifs" size="small">
              <Column field="zone" header="Zone" />
              <Column field="prixBase" header="Base (XOF)">
                <template #body="{ data }"><span class="font-semibold">{{ data.prixBase.toLocaleString('fr-FR') }}</span></template>
              </Column>
              <Column field="prixKg" header="Prix/kg (XOF)">
                <template #body="{ data }"><span>{{ data.prixKg.toLocaleString('fr-FR') }}</span></template>
              </Column>
            </DataTable>
          </template>
        </Card>
      </div>
    </Drawer>

    <!-- Dialog création transporteur -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau transporteur" style="width:640px" :modal="true">
      <div class="grid grid-cols-2 gap-4 pt-2">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom du transporteur</label>
          <InputText v-model="form.nom" placeholder="Ex: Sahel Express" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type de transport</label>
          <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Contact principal</label>
          <InputText v-model="form.contact" placeholder="Nom / email" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Téléphone</label>
          <InputText v-model="form.telephone" placeholder="+221 7x xxx xx xx" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tarif/kg (XOF)</label>
          <InputText v-model="form.tarifKg" type="number" placeholder="Ex: 850" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Délai moyen de livraison</label>
          <InputText v-model="form.delai" placeholder="Ex: 2–3 jours" />
        </div>
        <div class="col-span-2 flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Zones géographiques couvertes</label>
          <InputText v-model="form.zones" placeholder="Ex: Dakar, Abidjan, Bamako, Lomé…" />
        </div>
        <div class="col-span-2 flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
          <Textarea v-model="form.notes" rows="2" placeholder="Conditions particulières, certifications…" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showCreateDialog = false" />
        <Button label="Créer le transporteur" icon="pi pi-check" @click="createCarrier" />
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
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['logistics-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const user = computed(() => page.props.auth?.user)

const search = ref('')
const filterType = ref(null)
const filterStatut = ref(null)
const showCreateDialog = ref(false)
const showDetailDrawer = ref(false)
const selectedCarrier = ref<any>(null)

const form = ref({ nom: '', type: '', contact: '', telephone: '', tarifKg: '', delai: '', zones: '', notes: '' })

const typeOptions = [
  { label: 'Routier', value: 'Routier' },
  { label: 'Maritime', value: 'Maritime' },
  { label: 'Aérien', value: 'Aérien' },
  { label: 'Express', value: 'Express' },
]

const statutOptions = [
  { label: 'Actif', value: 'Actif' },
  { label: 'Suspendu', value: 'Suspendu' },
  { label: 'En révision', value: 'En révision' },
]

const kpis = ref([
  { label: 'Transporteurs actifs', value: '14', sub: 'Partenaires certifiés', color: 'text-blue-600', bg: 'bg-blue-100 dark:bg-blue-900', icon: 'pi pi-truck', iconColor: '#2563eb' },
  { label: 'Livraisons ce mois', value: '312', sub: 'Tous transporteurs', color: 'text-green-600', bg: 'bg-green-100 dark:bg-green-900', icon: 'pi pi-check-circle', iconColor: '#16a34a' },
  { label: 'Ponctualité moyenne', value: '87%', sub: 'OTIF global', color: 'text-purple-600', bg: 'bg-purple-100 dark:bg-purple-900', icon: 'pi pi-chart-bar', iconColor: '#7c3aed' },
  { label: 'Incidents ce mois', value: '7', sub: '3 résolus', color: 'text-orange-600', bg: 'bg-orange-100 dark:bg-orange-900', icon: 'pi pi-exclamation-triangle', iconColor: '#ea580c' },
])

const carriers = ref([
  {
    nom: 'Sahel Express', type: 'Routier', contact: 'M. Diallo — +221 77 432 1234',
    zones: ['Dakar', 'Abidjan', 'Bamako', 'Lomé', 'Cotonou'], delaiMoyen: '2–4 jours', ponctualite: 92, tarifKg: 850, statut: 'Actif',
    performance: [{ label: 'Ponctualité', value: 92 }, { label: 'Intégrité colis', value: 98 }, { label: 'Satisfaction client', value: 88 }],
    incidents: [
      { id: 1, type: 'Livraison retardée — panne véhicule', ref: 'EXP-2398', date: '15/05/2026', resolution: 'Résolu' },
      { id: 2, type: 'Colis endommagé', ref: 'EXP-2371', date: '02/05/2026', resolution: 'En cours' },
    ],
    tarifs: [{ zone: 'Dakar → Abidjan', prixBase: 35000, prixKg: 850 }, { zone: 'Dakar → Bamako', prixBase: 28000, prixKg: 720 }, { zone: 'Dakar → Lomé', prixBase: 42000, prixKg: 910 }],
  },
  {
    nom: 'DHL West Africa', type: 'Express', contact: 'Mme Koné — +225 27 22 401 400',
    zones: ['Abidjan', 'Dakar', 'Accra', 'Lagos', 'Libreville'], delaiMoyen: '1–2 jours', ponctualite: 97, tarifKg: 2800, statut: 'Actif',
    performance: [{ label: 'Ponctualité', value: 97 }, { label: 'Intégrité colis', value: 99 }, { label: 'Satisfaction client', value: 96 }],
    incidents: [{ id: 1, type: 'Retard douane', ref: 'EXP-2402', date: '10/05/2026', resolution: 'Résolu' }],
    tarifs: [{ zone: 'Zone CEDEAO J+1', prixBase: 18000, prixKg: 2800 }, { zone: 'Zone CEMAC J+2', prixBase: 22000, prixKg: 3200 }],
  },
  {
    nom: 'Maersk Afrique', type: 'Maritime', contact: 'Bureau Abidjan — +225 27 22 200 200',
    zones: ['Abidjan', 'Douala', 'Lagos', 'Dakar', 'Lomé', 'Cotonou'], delaiMoyen: '5–10 jours', ponctualite: 82, tarifKg: 320, statut: 'Actif',
    performance: [{ label: 'Ponctualité', value: 82 }, { label: 'Intégrité colis', value: 97 }, { label: 'Satisfaction client', value: 84 }],
    incidents: [
      { id: 1, type: 'Blocage douane Douala', ref: 'EXP-2502', date: '22/05/2026', resolution: 'En cours' },
      { id: 2, type: 'Retard chargement', ref: 'EXP-2488', date: '18/05/2026', resolution: 'Résolu' },
    ],
    tarifs: [{ zone: 'Abidjan → Douala', prixBase: 120000, prixKg: 320 }, { zone: 'Dakar → Lagos', prixBase: 145000, prixKg: 380 }],
  },
  {
    nom: 'Air Ivoire Cargo', type: 'Aérien', contact: 'Cargo — +225 27 21 275 000',
    zones: ['Abidjan', 'Dakar', 'Libreville', 'Ouagadougou', 'Bamako'], delaiMoyen: '24–48h', ponctualite: 89, tarifKg: 3500, statut: 'Actif',
    performance: [{ label: 'Ponctualité', value: 89 }, { label: 'Intégrité colis', value: 99 }, { label: 'Satisfaction client', value: 91 }],
    incidents: [],
    tarifs: [{ zone: 'Abidjan → Dakar', prixBase: 25000, prixKg: 3500 }, { zone: 'Abidjan → Libreville', prixBase: 22000, prixKg: 3200 }],
  },
  {
    nom: 'Bolloré Transport', type: 'Routier', contact: 'Direction — +225 27 20 251 500',
    zones: ['Abidjan', 'Douala', 'Libreville', 'Cotonou', 'Lomé'], delaiMoyen: '3–6 jours', ponctualite: 78, tarifKg: 680, statut: 'En révision',
    performance: [{ label: 'Ponctualité', value: 78 }, { label: 'Intégrité colis', value: 94 }, { label: 'Satisfaction client', value: 76 }],
    incidents: [
      { id: 1, type: 'Retard 72h — Lomé', ref: 'EXP-2380', date: '08/05/2026', resolution: 'Résolu' },
      { id: 2, type: 'Perte colis partielle', ref: 'EXP-2355', date: '01/05/2026', resolution: 'En cours' },
    ],
    tarifs: [{ zone: 'Abidjan → Douala', prixBase: 65000, prixKg: 680 }],
  },
])

const filteredCarriers = computed(() => {
  return carriers.value.filter(c => {
    const matchSearch = !search.value || c.nom.toLowerCase().includes(search.value.toLowerCase())
    const matchType = !filterType.value || c.type === filterType.value
    const matchStatut = !filterStatut.value || c.statut === filterStatut.value
    return matchSearch && matchType && matchStatut
  })
})

function typeIcon(type: string) {
  const map: Record<string, string> = { 'Routier': 'pi pi-truck', 'Maritime': 'pi pi-star', 'Aérien': 'pi pi-send', 'Express': 'pi pi-bolt' }
  return map[type] || 'pi pi-truck'
}

function openDetail(event: any) {
  selectedCarrier.value = event.data
  showDetailDrawer.value = true
}

function editCarrier(_data: any) {}

function resetFilters() {
  search.value = ''
  filterType.value = null
  filterStatut.value = null
}

function exportData() {}

function createCarrier() {
  showCreateDialog.value = false
  form.value = { nom: '', type: '', contact: '', telephone: '', tarifKg: '', delai: '', zones: '', notes: '' }
}
</script>
