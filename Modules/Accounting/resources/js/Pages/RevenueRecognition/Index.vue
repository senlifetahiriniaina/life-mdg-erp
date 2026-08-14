<template>
  <AppLayout>
    <Head title="Reconnaissance des Revenus" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Reconnaissance des Revenus — ASC 606 / IFRS 15
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Gestion des contrats et reconnaissance automatique selon les normes internationales
          </p>
        </div>
        <Button
          v-if="canCreate"
          icon="pi pi-plus"
          label="Créer un contrat"
          @click="showCreateDialog = true"
        />
      </div>

      <!-- IFRS 15 compliance banner -->
      <div class="flex items-center gap-3 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl text-blue-800 dark:text-blue-200 text-sm">
        <i class="pi pi-info-circle text-blue-500 text-lg shrink-0" />
        <span>
          <strong>Conformité IFRS 15</strong> — les revenus sont reconnus selon le principe de transfert de contrôle.
          Chaque obligation de prestation est identifiée, son prix de transaction alloué et reconnu lorsque (ou au fur et à mesure que) le contrôle est transféré au client.
        </span>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Revenus différés total</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ formatXof(stats.totalDeferred) }}</div>
          <div class="text-surface-400 text-xs mt-1">XOF</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Reconnus ce mois</div>
          <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ formatXof(stats.recognizedThisMonth) }}</div>
          <div class="text-surface-400 text-xs mt-1">XOF</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Contrats actifs</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ stats.activeContracts }}</div>
          <div class="text-surface-400 text-xs mt-1">contrats en cours</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Reconnaissances planifiées</div>
          <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.scheduledRecognitions }}</div>
          <div class="text-surface-400 text-xs mt-1">ce mois à passer</div>
        </div>
      </div>

      <!-- Contracts table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="p-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
          <h2 class="font-semibold text-surface-900 dark:text-surface-50">Contrats de reconnaissance</h2>
          <div class="flex gap-2">
            <Select
              v-model="filterStatus"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              placeholder="Tous les statuts"
              show-clear
              class="w-44"
              @change="applyFilters"
            />
            <Select
              v-model="filterMethod"
              :options="methodOptions"
              option-label="label"
              option-value="value"
              placeholder="Méthode"
              show-clear
              class="w-40"
              @change="applyFilters"
            />
          </div>
        </div>

        <DataTable
          :value="filteredContracts"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
        >
          <Column field="client" header="Client">
            <template #body="{ data }">
              <div class="font-medium text-surface-900 dark:text-surface-50">{{ data.client }}</div>
              <div class="text-xs text-surface-400">{{ data.reference }}</div>
            </template>
          </Column>
          <Column field="description" header="Description obligation" style="min-width: 200px">
            <template #body="{ data }">
              <span class="text-sm">{{ data.description }}</span>
            </template>
          </Column>
          <Column field="totalValue" header="Valeur totale" style="text-align: right">
            <template #body="{ data }">
              <span class="font-mono font-medium">{{ formatXof(data.totalValue) }}</span>
              <div class="text-xs text-surface-400">XOF</div>
            </template>
          </Column>
          <Column field="deferred" header="Différé restant" style="text-align: right">
            <template #body="{ data }">
              <span class="font-mono text-amber-700 dark:text-amber-300">{{ formatXof(data.deferred) }}</span>
            </template>
          </Column>
          <Column field="method" header="Méthode">
            <template #body="{ data }">
              <Tag
                :value="data.method === 'linear' ? 'Linéaire' : 'Achèvement'"
                :severity="data.method === 'linear' ? 'info' : 'secondary'"
              />
            </template>
          </Column>
          <Column field="startDate" header="Période">
            <template #body="{ data }">
              <div class="text-sm">{{ data.startDate }}</div>
              <div class="text-xs text-surface-400">→ {{ data.endDate }}</div>
            </template>
          </Column>
          <Column field="status" header="Statut">
            <template #body="{ data }">
              <Tag
                :value="statusLabel(data.status)"
                :severity="statusSeverity(data.status)"
              />
            </template>
          </Column>
          <Column header="Actions" style="width: 13rem">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-calendar"
                  outlined
                  size="small"
                  v-tooltip.top="'Voir calendrier'"
                  @click="openSchedule(data)"
                />
                <Button
                  v-if="canCreate && data.status === 'active'"
                  icon="pi pi-check-circle"
                  outlined
                  severity="success"
                  size="small"
                  v-tooltip.top="'Passer écriture'"
                  @click="confirmRecognize(data)"
                />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              Aucun contrat de reconnaissance trouvé.
            </div>
          </template>
        </DataTable>
      </div>

      <!-- AI Assistant -->
      <AiAssistantPanel v-if="guidance" :guidance="guidance" />
    </div>

    <!-- Amortization schedule drawer -->
    <Drawer
      v-model:visible="showScheduleDrawer"
      position="right"
      style="width: 580px"
      :header="selectedContract ? `Calendrier — ${selectedContract.client}` : 'Calendrier de reconnaissance'"
    >
      <div v-if="selectedContract" class="space-y-4">
        <div class="p-3 bg-surface-50 dark:bg-surface-700 rounded-lg text-sm">
          <div class="font-medium text-surface-900 dark:text-surface-50 mb-1">{{ selectedContract.description }}</div>
          <div class="text-surface-400">
            Valeur totale : <strong>{{ formatXof(selectedContract.totalValue) }} XOF</strong>
            &nbsp;·&nbsp; Méthode : <strong>{{ selectedContract.method === 'linear' ? 'Linéaire' : 'Achèvement' }}</strong>
          </div>
        </div>

        <DataTable :value="scheduleRows" class="p-datatable-sm" striped-rows>
          <Column field="month" header="Mois" />
          <Column field="deferred" header="Différé début">
            <template #body="{ data }">
              <span class="font-mono text-sm">{{ formatXof(data.deferred) }}</span>
            </template>
          </Column>
          <Column field="recognized" header="Reconnu">
            <template #body="{ data }">
              <span class="font-mono text-sm text-green-700 dark:text-green-400">{{ formatXof(data.recognized) }}</span>
            </template>
          </Column>
          <Column field="remaining" header="Solde fin">
            <template #body="{ data }">
              <span class="font-mono text-sm text-amber-700 dark:text-amber-400">{{ formatXof(data.remaining) }}</span>
            </template>
          </Column>
          <Column field="status" header="Statut">
            <template #body="{ data }">
              <Tag
                :value="data.status === 'recognized' ? 'Comptabilisé' : data.status === 'current' ? 'En cours' : 'Planifié'"
                :severity="data.status === 'recognized' ? 'success' : data.status === 'current' ? 'warn' : 'secondary'"
                class="text-xs"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </Drawer>

    <!-- Create contract dialog -->
    <Dialog
      v-model:visible="showCreateDialog"
      header="Créer un contrat de reconnaissance"
      :style="{ width: '560px' }"
      modal
    >
      <div class="space-y-4 pt-2">
        <div class="field">
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Client *</label>
          <InputText v-model="form.client" class="w-full" placeholder="Nom du client" />
        </div>
        <div class="field">
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Description de l'obligation de prestation *</label>
          <Textarea v-model="form.description" class="w-full" rows="2" placeholder="Ex: Licence logicielle 12 mois + support inclus" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="field">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Montant total (XOF) *</label>
            <InputNumber v-model="form.totalValue" class="w-full" :min="0" :max-fraction-digits="0" placeholder="0" />
          </div>
          <div class="field">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Méthode de reconnaissance *</label>
            <Select
              v-model="form.method"
              :options="methodOptions"
              option-label="label"
              option-value="value"
              class="w-full"
              placeholder="Choisir…"
            />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="field">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Date de début *</label>
            <DatePicker v-model="form.startDate" class="w-full" date-format="yy-mm-dd" placeholder="AAAA-MM-JJ" />
          </div>
          <div class="field">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Date de fin *</label>
            <DatePicker v-model="form.endDate" class="w-full" date-format="yy-mm-dd" placeholder="AAAA-MM-JJ" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Créer le contrat" icon="pi pi-check" :disabled="!isFormValid" @click="createContract" />
      </template>
    </Dialog>

    <ConfirmDialog />
    <Toast />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Drawer from 'primevue/drawer'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import ConfirmDialog from 'primevue/confirmdialog'
import Toast from 'primevue/toast'
import AppLayout from '@/Layouts/AppLayout.vue'
import AiAssistantPanel from '@/Components/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useRoleAccess } from '@/composables/useRoleAccess'
const { isElevated, hasAnyRole } = useRoleAccess()

// --- Types ---

interface RevenueContract {
  id: number
  client: string
  reference: string
  description: string
  totalValue: number
  deferred: number
  method: 'linear' | 'completion'
  startDate: string
  endDate: string
  status: 'active' | 'completed' | 'draft'
}

interface ScheduleRow {
  month: string
  deferred: number
  recognized: number
  remaining: number
  status: 'recognized' | 'current' | 'planned'
}

// --- RBAC ---
// Role check: create/execute = accountant + finance-manager + admin; view = all accounting roles

const canCreate = computed(() =>
  isElevated.value || hasAnyRole(['accountant', 'finance-manager'])
)

// --- AI assistant ---
const { guidance } = useAiAssistant('Accounting', 'post_invoice')

// --- State ---
const confirm = useConfirm()
const toast = useToast()

const loading = ref(false)
const showCreateDialog = ref(false)
const showScheduleDrawer = ref(false)
const selectedContract = ref<RevenueContract | null>(null)
const filterStatus = ref<string | null>(null)
const filterMethod = ref<string | null>(null)

const stats = reactive({
  totalDeferred: 284_750_000,
  recognizedThisMonth: 47_300_000,
  activeContracts: 5,
  scheduledRecognitions: 8,
})

// --- Mock data: 5 contracts with varied states ---
const contracts = ref<RevenueContract[]>([
  {
    id: 1,
    client: 'Orange Sénégal SA',
    reference: 'CONT-2025-001',
    description: 'Licence ERP WideHalo 24 mois — modules CRM, RH, Comptabilité',
    totalValue: 96_000_000,
    deferred: 56_000_000,
    method: 'linear',
    startDate: '2025-01-01',
    endDate: '2026-12-31',
    status: 'active',
  },
  {
    id: 2,
    client: 'SOTELMA Mali',
    reference: 'CONT-2025-012',
    description: 'Déploiement et intégration système de facturation',
    totalValue: 38_500_000,
    deferred: 12_833_333,
    method: 'completion',
    startDate: '2025-03-15',
    endDate: '2025-09-15',
    status: 'active',
  },
  {
    id: 3,
    client: 'Banque Atlantique CI',
    reference: 'CONT-2025-027',
    description: 'Support & maintenance annuel — modules Comptabilité & Sécurité',
    totalValue: 24_000_000,
    deferred: 16_000_000,
    method: 'linear',
    startDate: '2025-04-01',
    endDate: '2026-03-31',
    status: 'active',
  },
  {
    id: 4,
    client: 'NSIA Assurances',
    reference: 'CONT-2024-089',
    description: 'Migration données + formation équipes (5 jours)',
    totalValue: 18_250_000,
    deferred: 0,
    method: 'completion',
    startDate: '2024-10-01',
    endDate: '2024-12-31',
    status: 'completed',
  },
  {
    id: 5,
    client: 'MTN Côte d\'Ivoire',
    reference: 'CONT-2025-043',
    description: 'Abonnement SaaS WideHalo — 36 mois, 200 utilisateurs',
    totalValue: 200_000_000,
    deferred: 200_000_000,
    method: 'linear',
    startDate: '2026-01-01',
    endDate: '2028-12-31',
    status: 'draft',
  },
])

// --- Computed ---
const filteredContracts = computed(() => {
  return contracts.value.filter(c => {
    if (filterStatus.value && c.status !== filterStatus.value) return false
    if (filterMethod.value && c.method !== filterMethod.value) return false
    return true
  })
})

// Generate schedule rows for selected contract
const scheduleRows = computed<ScheduleRow[]>(() => {
  if (!selectedContract.value) return []
  const contract = selectedContract.value
  const start = new Date(contract.startDate)
  const end = new Date(contract.endDate)

  const months: ScheduleRow[] = []
  const totalMonths = Math.max(
    1,
    (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth()) + 1
  )
  const monthlyAmount = Math.round(contract.totalValue / totalMonths)
  const today = new Date()

  let deferred = contract.totalValue
  for (let i = 0; i < totalMonths; i++) {
    const d = new Date(start.getFullYear(), start.getMonth() + i, 1)
    const recognized = i < totalMonths - 1 ? monthlyAmount : deferred
    const remaining = Math.max(0, deferred - recognized)
    const isPast = d < new Date(today.getFullYear(), today.getMonth(), 1)
    const isCurrent = d.getFullYear() === today.getFullYear() && d.getMonth() === today.getMonth()
    months.push({
      month: d.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long' }),
      deferred,
      recognized,
      remaining,
      status: isPast ? 'recognized' : isCurrent ? 'current' : 'planned',
    })
    deferred = remaining
  }
  return months
})

const isFormValid = computed(() =>
  form.client.trim().length > 0 &&
  form.description.trim().length > 0 &&
  form.totalValue > 0 &&
  form.method !== '' &&
  form.startDate !== null &&
  form.endDate !== null
)

// --- Options ---
const statusOptions = [
  { label: 'En cours', value: 'active' },
  { label: 'Terminé', value: 'completed' },
  { label: 'Brouillon', value: 'draft' },
]

const methodOptions = [
  { label: 'Linéaire', value: 'linear' },
  { label: 'Achèvement', value: 'completion' },
]

// --- Form ---
const form = reactive({
  client: '',
  description: '',
  totalValue: 0,
  method: '' as 'linear' | 'completion' | '',
  startDate: null as Date | null,
  endDate: null as Date | null,
})

// --- Helpers ---
const formatXof = (value: number): string =>
  new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value)

const statusLabel = (status: string): string => {
  return statusOptions.find(o => o.value === status)?.label ?? status
}

const statusSeverity = (status: string): string => {
  switch (status) {
    case 'active': return 'success'
    case 'completed': return 'secondary'
    case 'draft': return 'warn'
    default: return 'secondary'
  }
}

const applyFilters = () => { /* reactive computed handles it */ }

// --- Actions ---
const openSchedule = (contract: RevenueContract) => {
  selectedContract.value = contract
  showScheduleDrawer.value = true
}

const confirmRecognize = (contract: RevenueContract) => {
  confirm.require({
    message: `Passer l'écriture de reconnaissance pour ${contract.client} — montant différé restant : ${formatXof(contract.deferred)} XOF ?`,
    header: 'Passer l\'écriture de reconnaissance',
    icon: 'pi pi-check-circle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-success',
    accept: async () => {
      try {
        await fetch(`/api/v1/accounting/revenue-recognition/contracts/${contract.id}/recognize`, {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ period: new Date().toISOString().slice(0, 7) }),
        })
        toast.add({ severity: 'success', summary: 'Écriture passée', detail: 'La reconnaissance de revenu a été comptabilisée.', life: 4000 })
      } catch {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de passer l\'écriture.', life: 4000 })
      }
    },
  })
}

const createContract = async () => {
  const payload = {
    client: form.client,
    description: form.description,
    total_value: form.totalValue,
    method: form.method,
    start_date: form.startDate ? (form.startDate as Date).toISOString().slice(0, 10) : null,
    end_date: form.endDate ? (form.endDate as Date).toISOString().slice(0, 10) : null,
  }
  try {
    await fetch('/api/v1/accounting/revenue-recognition/contracts', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
    toast.add({ severity: 'success', summary: 'Contrat créé', detail: 'Le contrat de reconnaissance a été enregistré.', life: 4000 })
    showCreateDialog.value = false
    // In production, fetchContracts() would refresh the list
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de créer le contrat.', life: 4000 })
  }
}

onMounted(() => {
  // In production: fetchContracts() and fetchStats()
})
</script>
