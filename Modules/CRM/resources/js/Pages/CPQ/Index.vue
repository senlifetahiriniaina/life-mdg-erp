<template>
  <AppLayout>
    <Head title="Configurateur de Prix & Devis (CPQ)" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Configurateur de Prix & Devis (CPQ)
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Configurez des devis précis avec tarification dynamique et approbation automatique
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="Créer un Devis"
          @click="showQuoteDialog = true"
        />
      </div>

      <!-- AI Assistant Panel -->
      <AiAssistantPanel v-if="guidance" :guidance="guidance" />

      <!-- Product Catalog Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3 items-center">
          <h2 class="text-base font-semibold text-surface-900 dark:text-surface-50 mr-2">
            Catalogue produits
          </h2>
          <Select
            v-model="filterCategory"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            placeholder="Toutes catégories"
            show-clear
            class="w-44"
          />
          <Select
            v-model="filterPriceRange"
            :options="priceRangeOptions"
            option-label="label"
            option-value="value"
            placeholder="Gamme de prix"
            show-clear
            class="w-44"
          />
          <InputText
            v-model="productSearch"
            placeholder="Rechercher un produit…"
            class="flex-1 min-w-40"
          />
        </div>
      </div>

      <!-- Product Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="product in filteredProducts"
          :key="product.id"
          class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-5 shadow-sm"
        >
          <div class="flex items-start justify-between mb-3">
            <div>
              <h3 class="font-semibold text-surface-900 dark:text-surface-50">{{ product.name }}</h3>
              <p class="text-xs text-surface-400 mt-0.5">{{ product.category }}</p>
            </div>
            <Tag :value="product.category" severity="info" class="text-xs" />
          </div>

          <p class="text-sm text-surface-500 mb-4">{{ product.description }}</p>

          <!-- Pricing tiers -->
          <div class="space-y-2">
            <div
              v-for="tier in product.tiers"
              :key="tier.name"
              class="flex items-center justify-between p-2 rounded-lg bg-surface-50 dark:bg-surface-700"
            >
              <div class="flex items-center gap-2">
                <div
                  :class="[
                    'w-2 h-2 rounded-full',
                    tier.name === 'Basique' ? 'bg-surface-400' :
                    tier.name === 'Pro' ? 'bg-blue-500' : 'bg-purple-600',
                  ]"
                />
                <span class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ tier.name }}</span>
              </div>
              <span class="text-sm font-bold text-surface-900 dark:text-surface-50">
                {{ formatCurrency(tier.price) }}
                <span class="font-normal text-surface-400 text-xs">/mois</span>
              </span>
            </div>
          </div>

          <Button
            icon="pi pi-plus"
            label="Ajouter au devis"
            size="small"
            outlined
            class="w-full mt-4"
            @click="addProductToQuote(product)"
          />
        </div>
      </div>

      <!-- Quotes List -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Devis récents</h2>
          <Tag :value="quotes.length + ' devis'" severity="secondary" />
        </div>

        <DataTable :value="quotes" striped-rows class="p-datatable-sm">
          <Column field="client" header="Client" sortable>
            <template #body="{ data }">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ data.client }}</p>
                <p class="text-xs text-surface-400">{{ data.reference }}</p>
              </div>
            </template>
          </Column>

          <Column field="amount" header="Montant" sortable style="width: 14rem">
            <template #body="{ data }">
              <span class="font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(data.amount) }}</span>
            </template>
          </Column>

          <Column field="status" header="Statut" style="width: 10rem">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="quoteStatusSeverity(data.status)" />
            </template>
          </Column>

          <Column field="date" header="Date" sortable style="width: 10rem">
            <template #body="{ data }">
              <span class="text-sm text-surface-600 dark:text-surface-300">{{ data.date }}</span>
            </template>
          </Column>

          <Column header="Actions" style="width: 14rem">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-eye"
                  outlined
                  size="small"
                  v-tooltip="'Voir le devis'"
                  @click="viewQuote(data)"
                />
                <Button
                  v-if="data.status === 'Brouillon'"
                  icon="pi pi-send"
                  outlined
                  severity="info"
                  size="small"
                  v-tooltip="'Envoyer au client'"
                  @click="sendQuote(data)"
                />
                <Button
                  v-if="canDelete"
                  icon="pi pi-trash"
                  outlined
                  severity="danger"
                  size="small"
                  v-tooltip="'Supprimer'"
                  @click="deleteQuote(data)"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-12 text-surface-400">
              Aucun devis créé. Cliquez sur "Créer un Devis" pour commencer.
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Create Quote Dialog -->
    <Dialog
      v-model:visible="showQuoteDialog"
      header="Créer un Devis CPQ"
      modal
      class="w-full max-w-2xl"
    >
      <div class="space-y-4">
        <!-- Client -->
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1">Client</label>
          <Select
            v-model="quoteForm.clientId"
            :options="clientOptions"
            option-label="name"
            option-value="id"
            placeholder="Sélectionner un client"
            class="w-full"
          />
        </div>

        <!-- Products in quote -->
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">Produits</label>
          <div class="border border-surface-200 dark:border-surface-600 rounded-lg overflow-hidden">
            <div
              v-for="(line, idx) in quoteForm.lines"
              :key="idx"
              class="flex items-center gap-3 p-3 border-b border-surface-100 dark:border-surface-700 last:border-0"
            >
              <div class="flex-1">
                <p class="text-sm font-medium text-surface-800 dark:text-surface-100">{{ line.productName }}</p>
                <p class="text-xs text-surface-400">{{ formatCurrency(line.unitPrice) }} / unité</p>
              </div>
              <InputNumber v-model="line.qty" :min="1" :max="999" class="w-20" @input="recalcTotal" />
              <Button
                icon="pi pi-times"
                text
                severity="danger"
                size="small"
                @click="removeLine(idx)"
              />
            </div>
            <div v-if="quoteForm.lines.length === 0" class="p-4 text-center text-surface-400 text-sm">
              Ajoutez des produits depuis le catalogue ci-dessus
            </div>
          </div>
        </div>

        <!-- Discount -->
        <div class="flex items-center gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1">Remise (%)</label>
            <InputNumber v-model="quoteForm.discountPct" :min="0" :max="100" suffix="%" class="w-full" @input="recalcTotal" />
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1">Total calculé</label>
            <div class="p-3 bg-surface-50 dark:bg-surface-700 rounded-lg border border-surface-200 dark:border-surface-600">
              <span class="text-lg font-bold text-primary-600">{{ formatCurrency(quoteTotal) }}</span>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showQuoteDialog = false" />
        <Button label="Créer le devis" icon="pi pi-check" @click="saveQuote" :loading="saving" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import AppLayout from '@/Layouts/AppLayout.vue'
import AiAssistantPanel from '@/Components/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['crm-manager', 'sales-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


interface PricingTier {
  name: string
  price: number
}

interface Product {
  id: number
  name: string
  category: string
  description: string
  tiers: PricingTier[]
}

interface QuoteLine {
  productId: number
  productName: string
  unitPrice: number
  qty: number
}

interface Quote {
  id: number
  reference: string
  client: string
  amount: number
  status: string
  date: string
}

const { guidance } = useAiAssistant('CRM', 'create_opportunity')

const saving = ref(false)
const showQuoteDialog = ref(false)
const filterCategory = ref<string | null>(null)
const filterPriceRange = ref<string | null>(null)
const productSearch = ref('')

// RBAC: delete = admin only (mock: false for demo)
const canDelete = ref(false)

const categoryOptions = [
  { label: 'ERP', value: 'ERP' },
  { label: 'CRM', value: 'CRM' },
  { label: 'RH', value: 'RH' },
  { label: 'Comptabilité', value: 'Comptabilité' },
]

const priceRangeOptions = [
  { label: 'Moins de 100 000 XOF', value: 'low' },
  { label: '100 000 – 500 000 XOF', value: 'mid' },
  { label: 'Plus de 500 000 XOF', value: 'high' },
]

const clientOptions = [
  { id: 1, name: 'Tech Dakar SARL' },
  { id: 2, name: 'GhanaLink Ltd' },
  { id: 3, name: 'Abidjan Négoce' },
  { id: 4, name: 'Congo Trading SA' },
  { id: 5, name: 'Maroc Export SARL' },
]

const quoteForm = ref<{
  clientId: number | null
  lines: QuoteLine[]
  discountPct: number
}>({
  clientId: null,
  lines: [],
  discountPct: 0,
})

const products = ref<Product[]>([
  {
    id: 1,
    name: 'WideHalo CRM',
    category: 'CRM',
    description: 'Gestion relation client complète avec scoring IA et automatisation.',
    tiers: [
      { name: 'Basique', price: 45000 },
      { name: 'Pro', price: 95000 },
      { name: 'Enterprise', price: 220000 },
    ],
  },
  {
    id: 2,
    name: 'WideHalo ERP Core',
    category: 'ERP',
    description: 'Module ERP central: inventaire, achats, comptabilité OHADA.',
    tiers: [
      { name: 'Basique', price: 85000 },
      { name: 'Pro', price: 185000 },
      { name: 'Enterprise', price: 450000 },
    ],
  },
  {
    id: 3,
    name: 'WideHalo RH',
    category: 'RH',
    description: 'Gestion des ressources humaines, paie, congés, présences.',
    tiers: [
      { name: 'Basique', price: 55000 },
      { name: 'Pro', price: 115000 },
      { name: 'Enterprise', price: 280000 },
    ],
  },
  {
    id: 4,
    name: 'WideHalo Comptabilité',
    category: 'Comptabilité',
    description: 'Comptabilité SYSCOHADA/IFRS, bilan, liasses fiscales.',
    tiers: [
      { name: 'Basique', price: 65000 },
      { name: 'Pro', price: 135000 },
      { name: 'Enterprise', price: 320000 },
    ],
  },
  {
    id: 5,
    name: 'WideHalo BI & Analytics',
    category: 'ERP',
    description: 'Tableaux de bord BI, rapports automatisés, KPIs en temps réel.',
    tiers: [
      { name: 'Basique', price: 70000 },
      { name: 'Pro', price: 150000 },
      { name: 'Enterprise', price: 380000 },
    ],
  },
  {
    id: 6,
    name: 'WideHalo Mobile',
    category: 'ERP',
    description: 'Application mobile iOS/Android offline-first pour votre équipe terrain.',
    tiers: [
      { name: 'Basique', price: 35000 },
      { name: 'Pro', price: 75000 },
      { name: 'Enterprise', price: 180000 },
    ],
  },
])

const quotes = ref<Quote[]>([
  { id: 1, reference: 'DEV-2026-001', client: 'Tech Dakar SARL', amount: 650000, status: 'Brouillon', date: '23/05/2026' },
  { id: 2, reference: 'DEV-2026-002', client: 'GhanaLink Ltd', amount: 1280000, status: 'Envoyé', date: '21/05/2026' },
  { id: 3, reference: 'DEV-2026-003', client: 'Abidjan Négoce', amount: 920000, status: 'Accepté', date: '18/05/2026' },
  { id: 4, reference: 'DEV-2026-004', client: 'Congo Trading SA', amount: 540000, status: 'Refusé', date: '15/05/2026' },
  { id: 5, reference: 'DEV-2026-005', client: 'Maroc Export SARL', amount: 1750000, status: 'Envoyé', date: '12/05/2026' },
])

const filteredProducts = computed(() => {
  return products.value.filter(p => {
    if (filterCategory.value && p.category !== filterCategory.value) return false
    if (productSearch.value && !p.name.toLowerCase().includes(productSearch.value.toLowerCase())) return false
    if (filterPriceRange.value) {
      const minPrice = p.tiers[0]?.price ?? 0
      if (filterPriceRange.value === 'low' && minPrice >= 100000) return false
      if (filterPriceRange.value === 'mid' && (minPrice < 100000 || minPrice > 500000)) return false
      if (filterPriceRange.value === 'high' && minPrice <= 500000) return false
    }
    return true
  })
})

const quoteTotal = computed(() => {
  const subtotal = quoteForm.value.lines.reduce((sum, line) => sum + line.unitPrice * line.qty, 0)
  const discount = subtotal * (quoteForm.value.discountPct / 100)
  return subtotal - discount
})

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(value)
}

const quoteStatusSeverity = (status: string): string => {
  switch (status) {
    case 'Accepté': return 'success'
    case 'Envoyé': return 'info'
    case 'Brouillon': return 'secondary'
    case 'Refusé': return 'danger'
    default: return 'secondary'
  }
}

const addProductToQuote = (product: Product) => {
  const proTier = product.tiers.find(t => t.name === 'Pro') ?? product.tiers[0]
  const existing = quoteForm.value.lines.find(l => l.productId === product.id)
  if (existing) {
    existing.qty += 1
  } else {
    quoteForm.value.lines.push({
      productId: product.id,
      productName: product.name,
      unitPrice: proTier.price,
      qty: 1,
    })
  }
  showQuoteDialog.value = true
}

const removeLine = (idx: number) => {
  quoteForm.value.lines.splice(idx, 1)
}

const recalcTotal = () => {
  // computed handles this automatically
}

const saveQuote = async () => {
  saving.value = true
  await new Promise(resolve => setTimeout(resolve, 800))
  const client = clientOptions.find(c => c.id === quoteForm.value.clientId)
  quotes.value.unshift({
    id: Date.now(),
    reference: `DEV-2026-00${quotes.value.length + 1}`,
    client: client?.name ?? 'Client inconnu',
    amount: quoteTotal.value,
    status: 'Brouillon',
    date: new Date().toLocaleDateString('fr-FR'),
  })
  quoteForm.value = { clientId: null, lines: [], discountPct: 0 }
  saving.value = false
  showQuoteDialog.value = false
}

const viewQuote = (quote: Quote) => {
  console.log('Voir devis:', quote.reference)
}

const sendQuote = async (quote: Quote) => {
  const idx = quotes.value.findIndex(q => q.id === quote.id)
  if (idx !== -1) quotes.value[idx].status = 'Envoyé'
}

const deleteQuote = (quote: Quote) => {
  quotes.value = quotes.value.filter(q => q.id !== quote.id)
}
</script>
