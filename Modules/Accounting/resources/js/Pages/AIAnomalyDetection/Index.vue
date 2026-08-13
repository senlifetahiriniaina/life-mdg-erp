<template>
  <AppLayout>
    <Head title="Détection d'Anomalies IA" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Détection d'Anomalies Comptables (IA)
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Analyse automatisée par IA — identification des écarts, doublons et transactions suspectes
          </p>
        </div>
        <Button
          icon="pi pi-search"
          label="Lancer une analyse"
          severity="warning"
          @click="showScanDialog = true"
        />
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Anomalies détectées</div>
          <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.detected }}</div>
          <div class="text-surface-400 text-xs mt-1">dont {{ stats.critical }} critiques</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Résolues ce mois</div>
          <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.resolvedThisMonth }}</div>
          <div class="text-surface-400 text-xs mt-1">anomalies clôturées</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Faux positifs</div>
          <div class="text-2xl font-bold text-surface-600 dark:text-surface-300">{{ stats.falsePositives }}</div>
          <div class="text-surface-400 text-xs mt-1">marqués ce trimestre</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5">
          <div class="text-surface-400 text-xs font-semibold uppercase tracking-wide mb-1">Économies estimées</div>
          <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ formatXof(stats.estimatedSavings) }}</div>
          <div class="text-surface-400 text-xs mt-1">XOF évités</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <Select
            v-model="filterType"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            placeholder="Type d'anomalie"
            show-clear
            class="w-52"
            @change="applyFilters"
          />
          <Select
            v-model="filterSeverity"
            :options="severityOptions"
            option-label="label"
            option-value="value"
            placeholder="Gravité"
            show-clear
            class="w-40"
            @change="applyFilters"
          />
          <Select
            v-model="filterStatus"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            placeholder="Statut"
            show-clear
            class="w-40"
            @change="applyFilters"
          />
          <Select
            v-model="filterPeriod"
            :options="periodOptions"
            option-label="label"
            option-value="value"
            placeholder="Période"
            show-clear
            class="w-44"
            @change="applyFilters"
          />
          <Button
            icon="pi pi-filter-slash"
            outlined
            v-tooltip.top="'Réinitialiser les filtres'"
            @click="clearFilters"
          />
        </div>
      </div>

      <!-- Anomalies table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          v-model:selection="selectedAnomaly"
          :value="filteredAnomalies"
          :loading="loading"
          selection-mode="single"
          striped-rows
          class="p-datatable-sm"
          @row-select="openDetail"
        >
          <Column field="date" header="Date" sortable style="width: 110px">
            <template #body="{ data }">
              <span class="text-sm font-mono">{{ data.date }}</span>
            </template>
          </Column>
          <Column field="type" header="Type">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <i :class="typeIcon(data.type)" class="text-surface-400" />
                <span class="text-sm">{{ typeLabel(data.type) }}</span>
              </div>
            </template>
          </Column>
          <Column field="description" header="Description" style="min-width: 220px">
            <template #body="{ data }">
              <span class="text-sm text-surface-700 dark:text-surface-200">{{ data.description }}</span>
            </template>
          </Column>
          <Column field="amount" header="Montant (XOF)" style="text-align: right">
            <template #body="{ data }">
              <span class="font-mono font-medium">{{ formatXof(data.amount) }}</span>
            </template>
          </Column>
          <Column field="severity" header="Gravité" style="width: 110px">
            <template #body="{ data }">
              <Tag
                :value="severityLabel(data.severity)"
                :severity="severitySeverity(data.severity)"
              />
            </template>
          </Column>
          <Column field="status" header="Statut" style="width: 130px">
            <template #body="{ data }">
              <Tag
                :value="statusLabel(data.status)"
                :severity="statusSeverityFn(data.status)"
              />
            </template>
          </Column>
          <Column header="Actions" style="width: 140px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-eye"
                  outlined
                  size="small"
                  v-tooltip.top="'Voir explication IA'"
                  @click.stop="openDetail({ data })"
                />
                <Button
                  v-if="canResolve && data.status === 'open'"
                  icon="pi pi-check"
                  outlined
                  severity="success"
                  size="small"
                  v-tooltip.top="'Marquer comme résolue'"
                  @click.stop="resolveAnomaly(data, 'resolved')"
                />
                <Button
                  v-if="canResolve && data.status === 'open'"
                  icon="pi pi-times"
                  outlined
                  severity="secondary"
                  size="small"
                  v-tooltip.top="'Faux positif'"
                  @click.stop="resolveAnomaly(data, 'false_positive')"
                />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              <i class="pi pi-check-circle text-3xl text-green-400 mb-3 block" />
              Aucune anomalie détectée — comptabilité saine.
            </div>
          </template>
        </DataTable>
      </div>

      <!-- AI Assistant -->
      <AiAssistantPanel v-if="guidance" :guidance="guidance" />
    </div>

    <!-- Anomaly detail panel (side drawer) -->
    <Drawer
      v-model:visible="showDetailPanel"
      position="right"
      style="width: 560px"
      :header="selectedAnomaly ? `Anomalie — ${typeLabel(selectedAnomaly.type)}` : 'Détail anomalie'"
    >
      <div v-if="selectedAnomaly" class="space-y-5">
        <!-- Summary row -->
        <div class="flex items-center justify-between p-3 bg-surface-50 dark:bg-surface-700 rounded-lg">
          <div>
            <div class="text-xs text-surface-400 mb-0.5">Date</div>
            <div class="font-mono text-sm font-medium">{{ selectedAnomaly.date }}</div>
          </div>
          <div class="text-right">
            <div class="text-xs text-surface-400 mb-0.5">Montant</div>
            <div class="font-mono text-sm font-bold text-red-600 dark:text-red-400">{{ formatXof(selectedAnomaly.amount) }} XOF</div>
          </div>
          <Tag :value="severityLabel(selectedAnomaly.severity)" :severity="severitySeverity(selectedAnomaly.severity)" />
        </div>

        <!-- AI explanation -->
        <div class="space-y-2">
          <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 flex items-center gap-2">
            <i class="pi pi-sparkles text-primary-500" />
            Explication IA
          </h3>
          <div class="p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-lg text-sm text-primary-900 dark:text-primary-100 leading-relaxed">
            {{ selectedAnomaly.aiExplanation }}
          </div>
        </div>

        <!-- Suggested correction -->
        <div class="space-y-2">
          <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 flex items-center gap-2">
            <i class="pi pi-wrench text-amber-500" />
            Correction suggérée
          </h3>
          <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-sm text-amber-900 dark:text-amber-100 leading-relaxed">
            {{ selectedAnomaly.suggestedCorrection }}
          </div>
        </div>

        <!-- Related transactions -->
        <div class="space-y-2">
          <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 flex items-center gap-2">
            <i class="pi pi-link text-surface-400" />
            Transactions liées
          </h3>
          <div class="space-y-1">
            <div
              v-for="tx in selectedAnomaly.relatedTransactions"
              :key="tx.ref"
              class="flex justify-between items-center p-2.5 bg-surface-50 dark:bg-surface-700 rounded-lg text-sm"
            >
              <div>
                <span class="font-mono text-xs text-surface-400">{{ tx.ref }}</span>
                <span class="ml-2 text-surface-700 dark:text-surface-200">{{ tx.label }}</span>
              </div>
              <span class="font-mono font-medium text-surface-900 dark:text-surface-50">{{ formatXof(tx.amount) }} XOF</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div v-if="canResolve && selectedAnomaly.status === 'open'" class="flex gap-3 pt-2 border-t border-surface-200 dark:border-surface-700">
          <Button
            label="Marquer comme résolue"
            icon="pi pi-check"
            severity="success"
            class="flex-1"
            @click="resolveAnomaly(selectedAnomaly, 'resolved')"
          />
          <Button
            label="Faux positif"
            icon="pi pi-times"
            outlined
            severity="secondary"
            class="flex-1"
            @click="resolveAnomaly(selectedAnomaly, 'false_positive')"
          />
        </div>
      </div>
    </Drawer>

    <!-- Scan dialog -->
    <Dialog
      v-model:visible="showScanDialog"
      header="Lancer une analyse IA"
      :style="{ width: '460px' }"
      modal
    >
      <div class="space-y-4 pt-2">
        <p class="text-sm text-surface-500">
          L'IA va analyser toutes les écritures comptables sur la période sélectionnée et détecter les anomalies potentielles.
        </p>
        <div class="grid grid-cols-2 gap-4">
          <div class="field">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Date de début</label>
            <DatePicker v-model="scanForm.startDate" class="w-full" date-format="yy-mm-dd" placeholder="AAAA-MM-JJ" />
          </div>
          <div class="field">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Date de fin</label>
            <DatePicker v-model="scanForm.endDate" class="w-full" date-format="yy-mm-dd" placeholder="AAAA-MM-JJ" />
          </div>
        </div>
        <div v-if="scanning" class="flex items-center gap-3 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg text-sm text-primary-800 dark:text-primary-200">
          <i class="pi pi-spin pi-spinner" />
          Analyse en cours… cela peut prendre quelques secondes.
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showScanDialog = false" :disabled="scanning" />
        <Button
          label="Lancer l'analyse"
          icon="pi pi-search"
          severity="warning"
          :loading="scanning"
          :disabled="!scanForm.startDate || !scanForm.endDate"
          @click="triggerScan"
        />
      </template>
    </Dialog>

    <Toast />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { Head, usePage} from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Drawer from 'primevue/drawer'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import Toast from 'primevue/toast'
import AppLayout from '@/Layouts/AppLayout.vue'
import AiAssistantPanel from '@/Components/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['accounting-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


// --- Types ---

type AnomalyType = 'duplicate' | 'unusual_amount' | 'wrong_category' | 'inter_period' | 'suspicious_vendor'
type AnomalySeverity = 'critical' | 'high' | 'medium' | 'low'
type AnomalyStatus = 'open' | 'resolved' | 'false_positive'

interface RelatedTransaction {
  ref: string
  label: string
  amount: number
}

interface Anomaly {
  id: number
  date: string
  type: AnomalyType
  description: string
  amount: number
  severity: AnomalySeverity
  status: AnomalyStatus
  aiExplanation: string
  suggestedCorrection: string
  relatedTransactions: RelatedTransaction[]
}

// --- RBAC ---
// view = accountant+; resolve = finance-manager + admin
const userRoles: string[] = (window as any).__ROLES__ ?? []
const canResolve = computed(() =>
  userRoles.some(r => ['finance-manager', 'admin', 'super-admin'].includes(r))
)

// --- AI assistant ---
const { guidance } = useAiAssistant('Accounting', 'reconcile')

// --- State ---
const toast = useToast()

const loading = ref(false)
const scanning = ref(false)
const showScanDialog = ref(false)
const showDetailPanel = ref(false)
const selectedAnomaly = ref<Anomaly | null>(null)

const filterType = ref<string | null>(null)
const filterSeverity = ref<string | null>(null)
const filterStatus = ref<string | null>(null)
const filterPeriod = ref<string | null>(null)

const stats = reactive({
  detected: 8,
  critical: 2,
  resolvedThisMonth: 14,
  falsePositives: 3,
  estimatedSavings: 12_750_000,
})

const scanForm = reactive({
  startDate: null as Date | null,
  endDate: null as Date | null,
})

// --- Mock data: 8 anomalies of different types ---
const anomalies = ref<Anomaly[]>([
  {
    id: 1,
    date: '2026-05-18',
    type: 'duplicate',
    description: 'Facture FOURNISSEUR-2026-0234 saisie deux fois',
    amount: 4_850_000,
    severity: 'critical',
    status: 'open',
    aiExplanation: 'Deux écritures identiques ont été enregistrées le 18/05/2026 et le 19/05/2026 pour le même fournisseur (Imprimerie Moderne CI), le même montant (4 850 000 XOF) et la même référence de facture. La probabilité de doublon est de 98,7 %.',
    suggestedCorrection: 'Supprimer l\'écriture du 19/05/2026 (ID #4521) et vérifier que le paiement n\'a pas été effectué deux fois. Contrôler le rapprochement bancaire correspondant.',
    relatedTransactions: [
      { ref: 'ECR-4519', label: 'Facture Imprimerie Moderne CI', amount: 4_850_000 },
      { ref: 'ECR-4521', label: 'Facture Imprimerie Moderne CI (doublon)', amount: 4_850_000 },
    ],
  },
  {
    id: 2,
    date: '2026-05-15',
    type: 'unusual_amount',
    description: 'Charge de représentation 14× supérieure à la moyenne mensuelle',
    amount: 8_200_000,
    severity: 'high',
    status: 'open',
    aiExplanation: 'Les charges de représentation (compte 625) s\'élèvent à 8 200 000 XOF pour mai 2026, contre une moyenne des 12 derniers mois de 590 000 XOF. L\'écart de +1 290 % dépasse le seuil d\'alerte configurable (3 σ au-dessus de la moyenne).',
    suggestedCorrection: 'Demander un justificatif détaillé pour cette dépense. Vérifier si l\'entrée est correctement catégorisée ou si une partie doit être reclassée en immobilisation ou charge extraordinaire.',
    relatedTransactions: [
      { ref: 'ECR-4488', label: 'Frais réception client — Hôtel Ivoire', amount: 8_200_000 },
    ],
  },
  {
    id: 3,
    date: '2026-05-10',
    type: 'wrong_category',
    description: 'Achat matériel informatique enregistré en charges courantes',
    amount: 2_350_000,
    severity: 'medium',
    status: 'open',
    aiExplanation: 'Une facture d\'achat d\'ordinateurs portables (2 unités × 1 175 000 XOF) a été imputée au compte 606 (achats non stockés) au lieu du compte 244 (matériel informatique). Selon SYSCOHADA, les biens d\'une valeur > 500 000 XOF doivent être immobilisés.',
    suggestedCorrection: 'Contre-passer l\'écriture au compte 606 et la re-saisir au compte 244 (immobilisations corporelles). Calculer et comptabiliser l\'amortissement correspondant (taux standard 33 % dégressif).',
    relatedTransactions: [
      { ref: 'ECR-4460', label: 'Achat 2 laptops Dell — Informatique Plus', amount: 2_350_000 },
    ],
  },
  {
    id: 4,
    date: '2026-04-30',
    type: 'inter_period',
    description: 'Charge comptabilisée en avril sur une facture de mars non encore reçue',
    amount: 1_680_000,
    severity: 'medium',
    status: 'resolved',
    aiExplanation: 'Une facture de service (conseil juridique) datée du 31 mars 2026 a été enregistrée le 30 avril 2026. L\'analyse des dates de saisie vs dates de facture révèle un décalage inter-période affectant la clôture trimestrielle.',
    suggestedCorrection: 'Contre-passer et re-saisir au 31/03/2026. Mettre à jour l\'état de rapprochement Q1 si déjà soumis.',
    relatedTransactions: [
      { ref: 'ECR-4320', label: 'Honoraires Cabinet Diallo & Associés — mars', amount: 1_680_000 },
    ],
  },
  {
    id: 5,
    date: '2026-05-20',
    type: 'suspicious_vendor',
    description: 'Premier paiement vers un fournisseur créé il y a moins de 24 h',
    amount: 6_500_000,
    severity: 'critical',
    status: 'open',
    aiExplanation: 'Le fournisseur "Tech Solutions 2026 SARL" (ID V-1892) a été créé dans le système le 19/05/2026 à 16h43. Un paiement de 6 500 000 XOF a été initié le 20/05/2026 à 09h12 — soit moins de 17 heures après la création. Ce schéma correspond à un pattern de fraude détecté avec 87 % de confiance.',
    suggestedCorrection: 'Suspendre immédiatement le paiement. Vérifier l\'identité du fournisseur (RCCM, NIF, coordonnées bancaires). Escalader au responsable financier et à la direction générale si le fournisseur ne peut pas être vérifié.',
    relatedTransactions: [
      { ref: 'PAY-2892', label: 'Virement Tech Solutions 2026 SARL', amount: 6_500_000 },
    ],
  },
  {
    id: 6,
    date: '2026-05-05',
    type: 'duplicate',
    description: 'Note de frais soumise deux fois par le même employé',
    amount: 245_000,
    severity: 'low',
    status: 'false_positive',
    aiExplanation: 'Deux notes de frais de 245 000 XOF ont été soumises par M. Koné A. pour la même date (03/05/2026). Après vérification manuelle, il s\'agit de deux déplacements différents le même jour (matin et après-midi) — le système les a signalés par similarité de montant.',
    suggestedCorrection: 'Aucune action requise — les deux notes de frais sont légitimes. Recommandation : ajouter une description détaillée dans les prochaines soumissions pour éviter les faux positifs.',
    relatedTransactions: [
      { ref: 'NDF-0812', label: 'Déplacement Abidjan — matin', amount: 245_000 },
      { ref: 'NDF-0815', label: 'Déplacement Abidjan — après-midi', amount: 245_000 },
    ],
  },
  {
    id: 7,
    date: '2026-05-12',
    type: 'unusual_amount',
    description: 'Remise accordée sur facture client supérieure au plafond autorisé',
    amount: 3_100_000,
    severity: 'high',
    status: 'open',
    aiExplanation: 'Une remise commerciale de 31 % (3 100 000 XOF) a été accordée sur la facture CLI-2026-0441. La politique commerciale autorise un maximum de 15 % pour ce segment client (PME). L\'écart de +16 points de remise représente un manque à gagner non validé.',
    suggestedCorrection: 'Obtenir une validation écrite du directeur commercial pour toute remise > 15 %. Si non validée, émettre un avoir partiel et une nouvelle facture au taux correct.',
    relatedTransactions: [
      { ref: 'CLI-2026-0441', label: 'Facture Brasserie du Bénin — remise exceptionnelle', amount: 10_000_000 },
      { ref: 'REMISE-0441', label: 'Remise 31 % appliquée', amount: 3_100_000 },
    ],
  },
  {
    id: 8,
    date: '2026-04-25',
    type: 'inter_period',
    description: 'Produit constaté d\'avance non extourné au 1er mai',
    amount: 5_400_000,
    severity: 'medium',
    status: 'resolved',
    aiExplanation: 'Un produit constaté d\'avance (compte 487) de 5 400 000 XOF enregistré en fin mars pour la période avril–juin n\'a pas fait l\'objet de l\'extourne automatique attendue au 01/04/2026. L\'IA a détecté l\'absence de l\'écriture inverse en comparant le journal de mai avec les patterns habituels.',
    suggestedCorrection: 'Passer l\'extourne au 01/04/2026 : débit compte 487 / crédit compte 706. Vérifier que la règle d\'extourne automatique est bien configurée dans le paramétrage des clôtures.',
    relatedTransactions: [
      { ref: 'ECR-4180', label: 'Produit constaté d\'avance — abonnement T2', amount: 5_400_000 },
      { ref: 'ECR-4390', label: 'Extourne manuelle passée', amount: 5_400_000 },
    ],
  },
])

// --- Computed ---
const filteredAnomalies = computed(() => {
  return anomalies.value.filter(a => {
    if (filterType.value && a.type !== filterType.value) return false
    if (filterSeverity.value && a.severity !== filterSeverity.value) return false
    if (filterStatus.value && a.status !== filterStatus.value) return false
    if (filterPeriod.value) {
      const [y, m] = filterPeriod.value.split('-')
      if (!a.date.startsWith(`${y}-${m}`)) return false
    }
    return true
  })
})

// --- Options ---
const typeOptions = [
  { label: 'Doublon', value: 'duplicate' },
  { label: 'Montant inhabituel', value: 'unusual_amount' },
  { label: 'Catégorie incorrecte', value: 'wrong_category' },
  { label: 'Écart inter-période', value: 'inter_period' },
  { label: 'Fournisseur suspect', value: 'suspicious_vendor' },
]

const severityOptions = [
  { label: 'Critique', value: 'critical' },
  { label: 'Élevée', value: 'high' },
  { label: 'Moyenne', value: 'medium' },
  { label: 'Faible', value: 'low' },
]

const statusOptions = [
  { label: 'Ouverte', value: 'open' },
  { label: 'Résolue', value: 'resolved' },
  { label: 'Faux positif', value: 'false_positive' },
]

const periodOptions = [
  { label: 'Mai 2026', value: '2026-05' },
  { label: 'Avril 2026', value: '2026-04' },
  { label: 'Mars 2026', value: '2026-03' },
  { label: 'Février 2026', value: '2026-02' },
]

// --- Helpers ---
const formatXof = (value: number): string =>
  new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value)

const typeLabel = (type: AnomalyType): string => {
  return typeOptions.find(o => o.value === type)?.label ?? type
}

const typeIcon = (type: AnomalyType): string => {
  switch (type) {
    case 'duplicate': return 'pi pi-copy'
    case 'unusual_amount': return 'pi pi-chart-bar'
    case 'wrong_category': return 'pi pi-tags'
    case 'inter_period': return 'pi pi-calendar-times'
    case 'suspicious_vendor': return 'pi pi-exclamation-circle'
    default: return 'pi pi-question-circle'
  }
}

const severityLabel = (severity: AnomalySeverity): string => {
  return severityOptions.find(o => o.value === severity)?.label ?? severity
}

const severitySeverity = (severity: AnomalySeverity): string => {
  switch (severity) {
    case 'critical': return 'danger'
    case 'high': return 'warn'
    case 'medium': return 'info'
    case 'low': return 'secondary'
    default: return 'secondary'
  }
}

const statusLabel = (status: AnomalyStatus): string => {
  return statusOptions.find(o => o.value === status)?.label ?? status
}

const statusSeverityFn = (status: AnomalyStatus): string => {
  switch (status) {
    case 'open': return 'danger'
    case 'resolved': return 'success'
    case 'false_positive': return 'secondary'
    default: return 'secondary'
  }
}

const applyFilters = () => { /* reactive computed handles filtering */ }

const clearFilters = () => {
  filterType.value = null
  filterSeverity.value = null
  filterStatus.value = null
  filterPeriod.value = null
}

// --- Actions ---
const openDetail = ({ data }: { data: Anomaly }) => {
  selectedAnomaly.value = data
  showDetailPanel.value = true
}

const resolveAnomaly = async (anomaly: Anomaly, resolution: 'resolved' | 'false_positive') => {
  try {
    await fetch(`/api/v1/accounting/ai-anomalies/${anomaly.id}/resolve`, {
      method: 'PUT',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: resolution }),
    })
    anomaly.status = resolution
    if (resolution === 'resolved') {
      stats.resolvedThisMonth++
    } else {
      stats.falsePositives++
    }
    stats.detected = anomalies.value.filter(a => a.status === 'open').length
    stats.critical = anomalies.value.filter(a => a.status === 'open' && a.severity === 'critical').length
    showDetailPanel.value = false
    toast.add({
      severity: 'success',
      summary: resolution === 'resolved' ? 'Anomalie résolue' : 'Faux positif marqué',
      detail: resolution === 'resolved'
        ? 'L\'anomalie a été clôturée avec succès.'
        : 'L\'anomalie a été marquée comme faux positif.',
      life: 4000,
    })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour l\'anomalie.', life: 4000 })
  }
}

const triggerScan = async () => {
  if (!scanForm.startDate || !scanForm.endDate) return
  scanning.value = true
  try {
    await fetch('/api/v1/accounting/ai-anomalies/scan', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        start_date: (scanForm.startDate as Date).toISOString().slice(0, 10),
        end_date: (scanForm.endDate as Date).toISOString().slice(0, 10),
      }),
    })
    toast.add({
      severity: 'info',
      summary: 'Analyse lancée',
      detail: 'L\'analyse IA est en cours. Les résultats apparaîtront dans quelques instants.',
      life: 5000,
    })
    showScanDialog.value = false
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de lancer l\'analyse.', life: 4000 })
  } finally {
    scanning.value = false
  }
}
</script>
