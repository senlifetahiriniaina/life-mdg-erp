<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Factures de fret</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">Réception, validation et paiement des factures transporteurs</p>
      </div>
      <div class="flex gap-2">
        <Button label="Exporter" icon="pi pi-download" severity="secondary" outlined @click="exportData" />
        <Button label="Enregistrer une facture" icon="pi pi-plus" @click="showCreateDialog = true" />
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
          <InputText v-model="search" placeholder="Rechercher une facture..." class="w-64" />
          <Select v-model="filterStatut" :options="statutOptions" optionLabel="label" optionValue="value" placeholder="Tous les statuts" class="w-48" />
          <Select v-model="filterCarrier" :options="carrierOptions" optionLabel="label" optionValue="value" placeholder="Tous les transporteurs" class="w-52" />
          <Button label="Réinitialiser" icon="pi pi-filter-slash" severity="secondary" outlined @click="resetFilters" />
        </div>
      </template>
    </Card>

    <!-- Tableau factures fret -->
    <Card>
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-file-edit text-purple-600"></i>
          <span>Factures de fret ({{ filteredInvoices.length }})</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredInvoices"
          paginator
          :rows="12"
          stripedRows
          rowHover
          @row-click="openDetail"
          class="cursor-pointer"
        >
          <Column field="ref" header="N° Facture" style="width:140px">
            <template #body="{ data }">
              <span class="font-mono text-sm font-bold text-purple-700 dark:text-purple-400">{{ data.ref }}</span>
            </template>
          </Column>
          <Column field="transporteur" header="Transporteur" style="min-width:160px">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 text-xs font-bold">
                  {{ data.transporteur.charAt(0) }}
                </span>
                <span class="text-sm font-medium">{{ data.transporteur }}</span>
              </div>
            </template>
          </Column>
          <Column field="expedition" header="Expédition liée" style="width:140px">
            <template #body="{ data }">
              <span class="font-mono text-sm text-blue-700 dark:text-blue-400">{{ data.expedition }}</span>
            </template>
          </Column>
          <Column field="montant" header="Montant (XOF)" style="width:180px">
            <template #body="{ data }">
              <span class="font-semibold text-gray-800 dark:text-gray-200">{{ data.montant.toLocaleString('fr-FR') }} XOF</span>
            </template>
          </Column>
          <Column field="date" header="Date" style="width:110px" />
          <Column field="statut" header="Statut" style="width:160px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="invoiceStatutSeverity(data.statut)" />
            </template>
          </Column>
          <Column header="Contrôle 3 voies" style="width:150px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <span
                  v-for="match in data.threeWayMatch"
                  :key="match.label"
                  class="flex h-6 w-6 items-center justify-center rounded text-xs"
                  :class="match.ok ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
                  v-tooltip.top="match.label"
                >
                  <i :class="match.ok ? 'pi pi-check' : 'pi pi-times'" class="text-xs"></i>
                </span>
                <span class="text-xs text-gray-500 ml-1">{{ data.threeWayMatch.filter((m: any) => m.ok).length }}/3</span>
              </div>
            </template>
          </Column>
          <Column header="Actions" style="width:150px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  v-if="data.statut === 'Reçue' || data.statut === 'En validation'"
                  icon="pi pi-check" rounded text size="small" severity="success"
                  @click.stop="approveInvoice(data)"
                  v-tooltip.top="'Approuver'"
                />
                <Button
                  v-if="data.statut === 'Reçue' || data.statut === 'En validation'"
                  icon="pi pi-times" rounded text size="small" severity="danger"
                  @click.stop="openDisputeDialog(data)"
                  v-tooltip.top="'Contester'"
                />
                <Button icon="pi pi-eye" rounded text size="small" severity="secondary" @click.stop="openDetail({ data })" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Drawer détail facture -->
    <Drawer v-model:visible="showDetailDrawer" position="right" style="width:600px" :header="selectedInvoice?.ref || 'Détail facture de fret'">
      <div v-if="selectedInvoice" class="space-y-5">
        <Card>
          <template #title>Informations de la facture</template>
          <template #content>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div><p class="text-gray-500">N° Facture</p><p class="font-mono font-bold">{{ selectedInvoice.ref }}</p></div>
              <div><p class="text-gray-500">Transporteur</p><p class="font-semibold">{{ selectedInvoice.transporteur }}</p></div>
              <div><p class="text-gray-500">Expédition liée</p><p class="font-mono text-blue-600">{{ selectedInvoice.expedition }}</p></div>
              <div><p class="text-gray-500">Date de réception</p><p class="font-semibold">{{ selectedInvoice.date }}</p></div>
              <div><p class="text-gray-500">Montant facturé</p><p class="font-bold text-lg text-gray-900 dark:text-white">{{ selectedInvoice.montant.toLocaleString('fr-FR') }} XOF</p></div>
              <div><p class="text-gray-500">Statut</p><Tag :value="selectedInvoice.statut" :severity="invoiceStatutSeverity(selectedInvoice.statut)" /></div>
            </div>
          </template>
        </Card>

        <!-- Contrôle 3 voies -->
        <Card>
          <template #title><i class="pi pi-check-square mr-2 text-blue-500"></i>Contrôle 3 voies</template>
          <template #content>
            <div class="space-y-3">
              <div v-for="match in selectedInvoice.threeWayMatch" :key="match.label"
                class="flex items-center justify-between rounded-lg border p-3"
                :class="match.ok ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'"
              >
                <div class="flex items-center gap-2">
                  <i :class="match.ok ? 'pi pi-check-circle text-green-500' : 'pi pi-times-circle text-red-500'"></i>
                  <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ match.label }}</p>
                    <p class="text-xs text-gray-500">{{ match.detail }}</p>
                  </div>
                </div>
                <Tag :value="match.ok ? 'Conforme' : 'Écart'" :severity="match.ok ? 'success' : 'danger'" size="small" />
              </div>
            </div>
          </template>
        </Card>

        <!-- Actions -->
        <div v-if="selectedInvoice.statut === 'Reçue' || selectedInvoice.statut === 'En validation'" class="flex gap-2">
          <Button label="Approuver la facture" icon="pi pi-check" severity="success" class="flex-1" @click="approveInvoice(selectedInvoice)" />
          <Button label="Contester" icon="pi pi-times" severity="danger" outlined class="flex-1" @click="openDisputeDialog(selectedInvoice)" />
        </div>
        <div v-else-if="selectedInvoice.statut === 'Approuvée'" class="flex gap-2">
          <Button label="Marquer comme payée" icon="pi pi-wallet" class="flex-1" />
          <Button label="Télécharger la facture" icon="pi pi-download" severity="secondary" outlined class="flex-1" />
        </div>
      </div>
    </Drawer>

    <!-- Dialog contestation -->
    <Dialog v-model:visible="showDisputeDialog" header="Contester la facture" style="width:500px" :modal="true">
      <div class="space-y-4 pt-2">
        <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 p-3 text-sm">
          <p class="font-semibold text-red-700 dark:text-red-300">Facture: {{ disputeTarget?.ref }}</p>
          <p class="text-red-600 dark:text-red-400 mt-1">Montant: {{ disputeTarget?.montant?.toLocaleString('fr-FR') }} XOF</p>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Motif de la contestation</label>
          <Select v-model="disputeForm.motif" :options="disputeMotifOptions" placeholder="Sélectionner un motif..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Montant attendu (XOF)</label>
          <InputText v-model="disputeForm.montantAttendu" type="number" placeholder="Montant correct selon bon de commande" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Détail de la contestation</label>
          <Textarea v-model="disputeForm.detail" rows="3" placeholder="Expliquer l'écart constaté..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showDisputeDialog = false" />
        <Button label="Soumettre la contestation" icon="pi pi-send" severity="danger" @click="submitDispute" />
      </template>
    </Dialog>

    <!-- Dialog création facture -->
    <Dialog v-model:visible="showCreateDialog" header="Enregistrer une facture de fret" style="width:640px" :modal="true">
      <div class="grid grid-cols-2 gap-4 pt-2">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Transporteur</label>
          <Select v-model="form.transporteur" :options="carrierOptions" optionLabel="label" optionValue="label" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Expédition liée</label>
          <Select v-model="form.expedition" :options="expeditionOptions" placeholder="Sélectionner..." />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">N° Facture transporteur</label>
          <InputText v-model="form.numFacture" placeholder="Ex: INV-2025-4421" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date de la facture</label>
          <InputText v-model="form.date" type="date" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Montant facturé (XOF)</label>
          <InputText v-model="form.montant" type="number" placeholder="Ex: 1250000" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Devise</label>
          <Select v-model="form.devise" :options="['XOF', 'XAF', 'EUR', 'USD']" placeholder="XOF" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showCreateDialog = false" />
        <Button label="Enregistrer la facture" icon="pi pi-check" @click="createInvoice" />
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
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['logistics-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const user = computed(() => page.props.auth?.user)

const search = ref('')
const filterStatut = ref(null)
const filterCarrier = ref(null)
const showCreateDialog = ref(false)
const showDetailDrawer = ref(false)
const showDisputeDialog = ref(false)
const selectedInvoice = ref<any>(null)
const disputeTarget = ref<any>(null)

const form = ref({ transporteur: '', expedition: '', numFacture: '', date: '', montant: '', devise: 'XOF' })
const disputeForm = ref({ motif: '', montantAttendu: '', detail: '' })

const statutOptions = [
  { label: 'Reçue', value: 'Reçue' },
  { label: 'En validation', value: 'En validation' },
  { label: 'Approuvée', value: 'Approuvée' },
  { label: 'Payée', value: 'Payée' },
  { label: 'Litigieuse', value: 'Litigieuse' },
]

const carrierOptions = [
  { label: 'Sahel Express', value: 'Sahel Express' },
  { label: 'DHL West Africa', value: 'DHL West Africa' },
  { label: 'Maersk Afrique', value: 'Maersk Afrique' },
  { label: 'Air Ivoire Cargo', value: 'Air Ivoire Cargo' },
  { label: 'Bolloré Transport', value: 'Bolloré Transport' },
]

const expeditionOptions = ['EXP-2501', 'EXP-2502', 'EXP-2503', 'EXP-2504', 'EXP-2505', 'EXP-2506']

const disputeMotifOptions = [
  'Montant supérieur au bon de commande',
  'Service non conforme aux conditions',
  'Double facturation',
  'Délai non respecté — pénalité applicable',
  'Frais non convenus',
]

const kpis = ref([
  { label: 'Factures reçues', value: '47', sub: 'Ce mois', color: 'text-blue-600', bg: 'bg-blue-100 dark:bg-blue-900', icon: 'pi pi-file', iconColor: '#2563eb' },
  { label: 'À valider', value: '12', sub: 'En attente approbation', color: 'text-orange-600', bg: 'bg-orange-100 dark:bg-orange-900', icon: 'pi pi-clock', iconColor: '#ea580c' },
  { label: 'Validées ce mois', value: '28', sub: '15,4 M XOF payés', color: 'text-green-600', bg: 'bg-green-100 dark:bg-green-900', icon: 'pi pi-check-circle', iconColor: '#16a34a' },
  { label: 'Écart budget', value: '+3,2 M XOF', sub: '+22% dépassement', color: 'text-red-600', bg: 'bg-red-100 dark:bg-red-900', icon: 'pi pi-chart-line', iconColor: '#dc2626' },
])

const invoices = ref([
  {
    ref: 'FRT-2501', transporteur: 'Sahel Express', expedition: 'EXP-2501', montant: 1250000, date: '23/05/2026', statut: 'En validation',
    threeWayMatch: [
      { label: 'Bon de commande', detail: 'BC-0441 — 1 200 000 XOF', ok: false },
      { label: 'Bon de livraison', detail: 'BL confirmé le 24/05/2026', ok: true },
      { label: 'Contrat transporteur', detail: 'Tarif grille 2026 — 850 XOF/kg', ok: false },
    ],
  },
  {
    ref: 'FRT-2502', transporteur: 'DHL West Africa', expedition: 'EXP-2504', montant: 380000, date: '20/05/2026', statut: 'Approuvée',
    threeWayMatch: [
      { label: 'Bon de commande', detail: 'BC-0438 — 380 000 XOF', ok: true },
      { label: 'Bon de livraison', detail: 'BL confirmé le 19/05/2026', ok: true },
      { label: 'Contrat transporteur', detail: 'Tarif zone CEDEAO J+1', ok: true },
    ],
  },
  {
    ref: 'FRT-2503', transporteur: 'Maersk Afrique', expedition: 'EXP-2502', montant: 2800000, date: '18/05/2026', statut: 'Litigieuse',
    threeWayMatch: [
      { label: 'Bon de commande', detail: 'BC-0432 — 2 400 000 XOF', ok: false },
      { label: 'Bon de livraison', detail: 'Livraison non confirmée', ok: false },
      { label: 'Contrat transporteur', detail: 'Frais supplémentaires non prévus', ok: false },
    ],
  },
  {
    ref: 'FRT-2504', transporteur: 'Air Ivoire Cargo', expedition: 'EXP-2506', montant: 720000, date: '23/05/2026', statut: 'Reçue',
    threeWayMatch: [
      { label: 'Bon de commande', detail: 'BC-0445 — 720 000 XOF', ok: true },
      { label: 'Bon de livraison', detail: 'En cours de transit', ok: false },
      { label: 'Contrat transporteur', detail: 'LTA conforme', ok: true },
    ],
  },
  {
    ref: 'FRT-2505', transporteur: 'Sahel Express', expedition: 'EXP-2503', montant: 980000, date: '22/05/2026', statut: 'Payée',
    threeWayMatch: [
      { label: 'Bon de commande', detail: 'BC-0440 — 980 000 XOF', ok: true },
      { label: 'Bon de livraison', detail: 'BL signé le 26/05/2026', ok: true },
      { label: 'Contrat transporteur', detail: 'Tarif Dakar → Lomé', ok: true },
    ],
  },
])

const filteredInvoices = computed(() => {
  return invoices.value.filter(inv => {
    const matchSearch = !search.value || [inv.ref, inv.transporteur, inv.expedition].some(v => v.toLowerCase().includes(search.value.toLowerCase()))
    const matchStatut = !filterStatut.value || inv.statut === filterStatut.value
    const matchCarrier = !filterCarrier.value || inv.transporteur === filterCarrier.value
    return matchSearch && matchStatut && matchCarrier
  })
})

function invoiceStatutSeverity(statut: string) {
  const map: Record<string, string> = {
    'Reçue': 'secondary',
    'En validation': 'warn',
    'Approuvée': 'success',
    'Payée': 'success',
    'Litigieuse': 'danger',
  }
  return map[statut] || 'secondary'
}

function openDetail(event: any) {
  selectedInvoice.value = event.data
  showDetailDrawer.value = true
}

function approveInvoice(data: any) {
  const inv = invoices.value.find(i => i.ref === data.ref)
  if (inv) inv.statut = 'Approuvée'
}

function openDisputeDialog(data: any) {
  disputeTarget.value = data
  showDisputeDialog.value = true
}

function submitDispute() {
  const inv = invoices.value.find(i => i.ref === disputeTarget.value?.ref)
  if (inv) inv.statut = 'Litigieuse'
  showDisputeDialog.value = false
  disputeForm.value = { motif: '', montantAttendu: '', detail: '' }
}

function resetFilters() {
  search.value = ''
  filterStatut.value = null
  filterCarrier.value = null
}

function exportData() {}

function createInvoice() {
  showCreateDialog.value = false
  form.value = { transporteur: '', expedition: '', numFacture: '', date: '', montant: '', devise: 'XOF' }
}
</script>
