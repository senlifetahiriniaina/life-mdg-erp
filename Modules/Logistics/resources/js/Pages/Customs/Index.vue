<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dédouanement</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">Gestion des dossiers douaniers import / export</p>
      </div>
      <div class="flex gap-2">
        <Button label="Exporter" icon="pi pi-download" severity="secondary" outlined @click="exportData" />
        <Button v-if="canCreate" label="Nouveau dossier douanier" icon="pi pi-plus" @click="showCreateDialog = true" />
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
          <InputText v-model="search" placeholder="Rechercher un dossier..." class="w-64" />
          <Select v-model="filterType" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Import / Export" class="w-44" />
          <Select v-model="filterStatut" :options="statutOptions" optionLabel="label" optionValue="value" placeholder="Tous les statuts" class="w-52" />
          <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" outlined @click="resetFilters" />
        </div>
      </template>
    </Card>

    <!-- Tableau dossiers douaniers -->
    <Card>
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-building text-orange-600"></i>
          <span>Dossiers douaniers ({{ filteredFiles.length }})</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredFiles"
          paginator
          :rows="12"
          stripedRows
          rowHover
          @row-click="openDetail"
          class="cursor-pointer"
        >
          <Column field="ref" header="Dossier" style="width:140px">
            <template #body="{ data }">
              <span class="font-mono text-sm font-bold text-orange-700 dark:text-orange-400">{{ data.ref }}</span>
            </template>
          </Column>
          <Column field="type" header="Type" style="width:100px">
            <template #body="{ data }">
              <Tag :value="data.type" :severity="data.type === 'Import' ? 'info' : 'warn'" />
            </template>
          </Column>
          <Column field="marchandise" header="Marchandise" style="min-width:160px">
            <template #body="{ data }">
              <div>
                <p class="font-medium text-gray-900 dark:text-white">{{ data.marchandise }}</p>
                <p class="text-xs text-gray-500">HS: {{ data.hsCode }}</p>
              </div>
            </template>
          </Column>
          <Column field="pays" header="Pays" style="min-width:180px">
            <template #body="{ data }">
              <div class="flex items-center gap-1 text-sm">
                <span class="text-gray-500">{{ data.paysOrigine }}</span>
                <i class="pi pi-arrow-right text-xs text-gray-400 mx-1"></i>
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ data.paysDestination }}</span>
              </div>
            </template>
          </Column>
          <Column field="valeurDeclaree" header="Valeur déclarée" style="width:180px">
            <template #body="{ data }">
              <span class="font-semibold text-gray-800 dark:text-gray-200">{{ data.valeurDeclaree.toLocaleString('fr-FR') }} XOF</span>
            </template>
          </Column>
          <Column field="droitsDouane" header="Droits douane" style="width:160px">
            <template #body="{ data }">
              <span :class="data.droitsDouane > 0 ? 'font-semibold text-red-600' : 'text-gray-400'">
                {{ data.droitsDouane > 0 ? data.droitsDouane.toLocaleString('fr-FR') + ' XOF' : '—' }}
              </span>
            </template>
          </Column>
          <Column field="statut" header="Statut" style="width:180px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="customsStatutSeverity(data.statut)" />
            </template>
          </Column>
          <Column header="Actions" style="width:100px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-eye" rounded text size="small" @click.stop="openDetail({ data })" />
                <Button icon="pi pi-upload" rounded text size="small" severity="info" @click.stop="uploadDoc(data)" v-tooltip.top="'Ajouter document'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Drawer détail dossier douanier -->
    <Drawer v-model:visible="showDetailDrawer" position="right" style="width:600px" :header="selectedFile?.ref || 'Détail dossier douanier'">
      <div v-if="selectedFile" class="space-y-5">
        <!-- Infos générales -->
        <Card>
          <template #title>Informations générales</template>
          <template #content>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div><p class="text-gray-500">Référence</p><p class="font-semibold">{{ selectedFile.ref }}</p></div>
              <div><p class="text-gray-500">Type opération</p><Tag :value="selectedFile.type" :severity="selectedFile.type === 'Import' ? 'info' : 'warn'" /></div>
              <div><p class="text-gray-500">Marchandise</p><p class="font-semibold">{{ selectedFile.marchandise }}</p></div>
              <div><p class="text-gray-500">Code HS</p><p class="font-mono font-semibold">{{ selectedFile.hsCode }}</p></div>
              <div><p class="text-gray-500">Pays d'origine</p><p class="font-semibold">{{ selectedFile.paysOrigine }}</p></div>
              <div><p class="text-gray-500">Pays de destination</p><p class="font-semibold">{{ selectedFile.paysDestination }}</p></div>
              <div><p class="text-gray-500">Valeur déclarée</p><p class="font-semibold">{{ selectedFile.valeurDeclaree.toLocaleString('fr-FR') }} XOF</p></div>
              <div><p class="text-gray-500">Droits de douane</p><p class="font-semibold text-red-600">{{ selectedFile.droitsDouane.toLocaleString('fr-FR') }} XOF</p></div>
              <div class="col-span-2"><p class="text-gray-500">Statut</p><Tag :value="selectedFile.statut" :severity="customsStatutSeverity(selectedFile.statut)" /></div>
            </div>
          </template>
        </Card>

        <!-- Documents requis / fournis -->
        <Card>
          <template #title><i class="pi pi-file mr-2 text-blue-500"></i>Documents douaniers</template>
          <template #content>
            <div class="space-y-2">
              <div
                v-for="doc in selectedFile.documents"
                :key="doc.nom"
                class="flex items-center justify-between rounded-lg border p-3"
                :class="doc.statut === 'Fourni' ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                  : doc.statut === 'Requis' ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                  : 'border-gray-200 dark:border-gray-700'"
              >
                <div class="flex items-center gap-2">
                  <i :class="doc.statut === 'Fourni' ? 'pi pi-check-circle text-green-500' : doc.statut === 'Requis' ? 'pi pi-times-circle text-red-500' : 'pi pi-clock text-gray-400'"></i>
                  <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ doc.nom }}</p>
                    <p v-if="doc.note" class="text-xs text-gray-500">{{ doc.note }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Tag :value="doc.statut" :severity="doc.statut === 'Fourni' ? 'success' : doc.statut === 'Requis' ? 'danger' : 'secondary'" size="small" />
                  <Button v-if="doc.statut !== 'Fourni'" icon="pi pi-upload" rounded text size="small" severity="info" />
                  <Button v-else icon="pi pi-download" rounded text size="small" />
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Actions -->
        <div class="flex gap-2">
          <Button label="Mettre à jour le statut" icon="pi pi-refresh" class="flex-1" />
          <Button label="Contacter le transitaire" icon="pi pi-phone" severity="secondary" outlined class="flex-1" />
        </div>
      </div>
    </Drawer>

    <!-- Dialog création dossier douanier -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau dossier douanier" style="width:680px" :modal="true">
      <div class="grid grid-cols-2 gap-4 pt-2">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type d'opération</label>
          <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Import ou Export" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Expédition liée</label>
          <Select v-model="form.expedition" :options="expeditionOptions" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nature de la marchandise</label>
          <InputText v-model="form.marchandise" placeholder="Ex: Équipements électroniques" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Code HS</label>
          <InputText v-model="form.hsCode" placeholder="Ex: 8471.30.00" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Pays d'origine</label>
          <Select v-model="form.paysOrigine" :options="paysOptions" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Pays de destination</label>
          <Select v-model="form.paysDestination" :options="paysOptions" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Valeur déclarée (XOF)</label>
          <InputText v-model="form.valeur" type="number" placeholder="Ex: 12500000" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Transitaire</label>
          <Select v-model="form.transitaire" :options="transitaireOptions" placeholder="Sélectionner..." />
        </div>
        <div class="col-span-2 flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Observations</label>
          <Textarea v-model="form.notes" rows="2" placeholder="Remarques particulières, conditions d'entreposage..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showCreateDialog = false" />
        <Button label="Créer le dossier" icon="pi pi-check" @click="createFile" />
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

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['logistics-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const user = computed(() => page.props.auth?.user)

const search = ref('')
const filterType = ref(null)
const filterStatut = ref(null)
const showCreateDialog = ref(false)
const showDetailDrawer = ref(false)
const selectedFile = ref<any>(null)

const form = ref({ type: '', expedition: '', marchandise: '', hsCode: '', paysOrigine: '', paysDestination: '', valeur: '', transitaire: '', notes: '' })

const typeOptions = [
  { label: 'Import', value: 'Import' },
  { label: 'Export', value: 'Export' },
]

const statutOptions = [
  { label: 'Déclaration déposée', value: 'Déclaration déposée' },
  { label: 'En attente inspection', value: 'En attente inspection' },
  { label: 'Dédouané', value: 'Dédouané' },
  { label: 'Bloqué', value: 'Bloqué' },
  { label: 'Litigieux', value: 'Litigieux' },
]

const paysOptions = ['Sénégal', 'Côte d\'Ivoire', 'Cameroun', 'Togo', 'Bénin', 'Mali', 'Burkina Faso', 'Ghana', 'Nigeria', 'Chine', 'France', 'Inde']
const expeditionOptions = ['EXP-2501', 'EXP-2502', 'EXP-2503', 'EXP-2504', 'EXP-2505']
const transitaireOptions = ['SAGA Sénégal', 'Getma International', 'CEVA Logistics', 'Bolloré Transit', 'GloboFreight']

const kpis = ref([
  { label: 'Dossiers en cours', value: '23', sub: 'Tous statuts confondus', color: 'text-orange-600', bg: 'bg-orange-100 dark:bg-orange-900', icon: 'pi pi-folder-open', iconColor: '#ea580c' },
  { label: 'En attente dédouanement', value: '8', sub: '3 bloqués', color: 'text-red-600', bg: 'bg-red-100 dark:bg-red-900', icon: 'pi pi-clock', iconColor: '#dc2626' },
  { label: 'Valeur en douane', value: '847 M XOF', sub: 'Ce mois', color: 'text-blue-600', bg: 'bg-blue-100 dark:bg-blue-900', icon: 'pi pi-chart-bar', iconColor: '#2563eb' },
  { label: 'Droits payés', value: '63,5 M XOF', sub: 'Ce mois', color: 'text-purple-600', bg: 'bg-purple-100 dark:bg-purple-900', icon: 'pi pi-wallet', iconColor: '#7c3aed' },
])

const customsFiles = ref([
  {
    ref: 'DOU-2501', type: 'Import', marchandise: 'Équipements informatiques', hsCode: '8471.30.00',
    paysOrigine: 'Chine', paysDestination: 'Sénégal', valeurDeclaree: 45000000, droitsDouane: 6750000, statut: 'Dédouané',
    documents: [
      { nom: 'Déclaration en douane (DHL)', statut: 'Fourni', note: null },
      { nom: 'Facture commerciale', statut: 'Fourni', note: null },
      { nom: 'Certificat d\'origine', statut: 'Fourni', note: 'Chambre de commerce CN' },
      { nom: 'Liste de colisage', statut: 'Fourni', note: null },
      { nom: 'Fiche technique produits', statut: 'Fourni', note: null },
    ],
  },
  {
    ref: 'DOU-2502', type: 'Import', marchandise: 'Produits pharmaceutiques', hsCode: '3004.20.00',
    paysOrigine: 'Inde', paysDestination: 'Côte d\'Ivoire', valeurDeclaree: 28500000, droitsDouane: 0, statut: 'Bloqué',
    documents: [
      { nom: 'Déclaration en douane (DHL)', statut: 'Fourni', note: null },
      { nom: 'Autorisation d\'importation médicaments', statut: 'Requis', note: 'Ministère de la Santé CI' },
      { nom: 'Certificat d\'analyse', statut: 'Requis', note: 'Laboratoire agréé' },
      { nom: 'Facture commerciale', statut: 'Fourni', note: null },
    ],
  },
  {
    ref: 'DOU-2503', type: 'Export', marchandise: 'Huile de palme brute', hsCode: '1511.10.00',
    paysOrigine: 'Côte d\'Ivoire', paysDestination: 'France', valeurDeclaree: 12000000, droitsDouane: 0, statut: 'Déclaration déposée',
    documents: [
      { nom: 'Déclaration export', statut: 'Fourni', note: null },
      { nom: 'Certificat phytosanitaire', statut: 'En attente', note: 'Ministère Agriculture CI' },
      { nom: 'Certificat d\'origine CEDEAO', statut: 'Fourni', note: null },
      { nom: 'Facture commerciale', statut: 'Fourni', note: null },
    ],
  },
  {
    ref: 'DOU-2504', type: 'Import', marchandise: 'Véhicules utilitaires', hsCode: '8704.31.90',
    paysOrigine: 'France', paysDestination: 'Cameroun', valeurDeclaree: 95000000, droitsDouane: 28500000, statut: 'En attente inspection',
    documents: [
      { nom: 'Déclaration en douane (DHL)', statut: 'Fourni', note: null },
      { nom: 'Carte grise / Titre de propriété', statut: 'Fourni', note: null },
      { nom: 'Procès-verbal d\'inspection BIVAC', statut: 'Requis', note: 'Visite physique prévue J+3' },
      { nom: 'Facture commerciale', statut: 'Fourni', note: null },
    ],
  },
  {
    ref: 'DOU-2505', type: 'Import', marchandise: 'Textiles et confections', hsCode: '6204.62.90',
    paysOrigine: 'Chine', paysDestination: 'Sénégal', valeurDeclaree: 8700000, droitsDouane: 2175000, statut: 'Litigieux',
    documents: [
      { nom: 'Déclaration en douane (DHL)', statut: 'Fourni', note: null },
      { nom: 'Facture commerciale', statut: 'Fourni', note: 'Valeur contestée par DGD' },
      { nom: 'Certificat d\'origine', statut: 'Requis', note: 'Litige sur l\'origine déclarée' },
    ],
  },
])

const filteredFiles = computed(() => {
  return customsFiles.value.filter(f => {
    const matchSearch = !search.value || [f.ref, f.marchandise, f.hsCode].some(v => v.toLowerCase().includes(search.value.toLowerCase()))
    const matchType = !filterType.value || f.type === filterType.value
    const matchStatut = !filterStatut.value || f.statut === filterStatut.value
    return matchSearch && matchType && matchStatut
  })
})

function customsStatutSeverity(statut: string) {
  const map: Record<string, string> = {
    'Déclaration déposée': 'secondary',
    'En attente inspection': 'warn',
    'Dédouané': 'success',
    'Bloqué': 'danger',
    'Litigieux': 'danger',
  }
  return map[statut] || 'secondary'
}

function openDetail(event: any) {
  selectedFile.value = event.data
  showDetailDrawer.value = true
}

function uploadDoc(_data: any) {}

function resetFilters() {
  search.value = ''
  filterType.value = null
  filterStatut.value = null
}

function exportData() {}

function createFile() {
  showCreateDialog.value = false
  form.value = { type: '', expedition: '', marchandise: '', hsCode: '', paysOrigine: '', paysDestination: '', valeur: '', transitaire: '', notes: '' }
}
</script>
