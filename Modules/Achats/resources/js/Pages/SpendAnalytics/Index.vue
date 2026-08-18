<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Analyse des Dépenses</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Dépenses réelles par fournisseur, tendances mensuelles et factures fournisseur en retard</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showAiPanel = !showAiPanel"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300"
          title="Assistant IA"
        >
          <i class="pi pi-sparkles" />
        </button>
        <select
          v-model="selectedPeriod"
          @change="loadData"
          class="px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500 text-sm"
        >
          <option value="ytd">Année en cours (YTD)</option>
          <option value="q1">T1 (Jan–Mar)</option>
          <option :value="String(currentYear - 1)">Année {{ currentYear - 1 }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-surface-500 dark:text-surface-400">
      Chargement…
    </div>

    <template v-else>
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
          <div class="text-sm text-surface-500 dark:text-surface-400">Dépenses totales</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ formatXOF(spending.total_spend) }}</div>
        </div>
        <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
          <div class="text-sm text-surface-500 dark:text-surface-400">Fournisseurs actifs</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ spending.active_suppliers }}</div>
          <div class="text-xs text-surface-500 mt-1">{{ spending.new_suppliers }} nouveaux sur la période</div>
        </div>
        <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
          <div class="text-sm text-surface-500 dark:text-surface-400">Réceptions en attente</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ pendingReceipts.length }}</div>
          <div class="text-xs text-surface-500 mt-1">commandes approuvées non réceptionnées</div>
        </div>
        <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
          <div class="text-sm text-surface-500 dark:text-surface-400">Factures fournisseur en retard</div>
          <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ overdueInvoices.count }}</div>
          <div class="text-xs text-red-600 mt-1">{{ formatXOF(overdueInvoices.total_overdue) }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Spend by Supplier -->
        <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-6">
          <h2 class="text-lg font-bold text-surface-900 dark:text-surface-50 mb-4">Dépenses par fournisseur</h2>
          <div v-if="spending.by_supplier.length === 0" class="text-sm text-surface-500 dark:text-surface-400">
            Aucune commande sur la période sélectionnée.
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="row in spending.by_supplier"
              :key="row.supplier_id"
              class="space-y-1"
            >
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-surface-700 dark:text-surface-300">{{ row.supplier_name }}</span>
                <div class="flex items-center gap-3">
                  <span class="text-surface-500 dark:text-surface-400">{{ row.percent }}%</span>
                  <span class="font-medium text-surface-900 dark:text-surface-50">{{ formatXOF(row.amount) }}</span>
                </div>
              </div>
              <div class="w-full bg-surface-200 dark:bg-surface-700 rounded-full h-2.5">
                <div class="h-2.5 rounded-full bg-blue-500 transition-all duration-500" :style="{ width: row.percent + '%' }" />
              </div>
            </div>
          </div>
        </div>

        <!-- Monthly Trends -->
        <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-6">
          <h2 class="text-lg font-bold text-surface-900 dark:text-surface-50 mb-4">Tendance mensuelle des dépenses</h2>
          <div v-if="spending.monthly_trend.length === 0" class="text-sm text-surface-500 dark:text-surface-400">
            Aucune donnée sur la période sélectionnée.
          </div>
          <table v-else class="w-full">
            <thead>
              <tr class="border-b border-subtle">
                <th scope="col" class="pb-2 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Mois</th>
                <th scope="col" class="pb-2 text-right text-sm font-semibold text-surface-700 dark:text-surface-300">Dépenses (XOF)</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in spending.monthly_trend"
                :key="row.month"
                class="border-b border-subtle hover:bg-surface-50 dark:hover:bg-surface-700"
              >
                <td class="py-2 text-sm font-medium text-surface-900 dark:text-surface-50">{{ row.month }}</td>
                <td class="py-2 text-sm text-right text-surface-600 dark:text-surface-400">{{ formatXOF(row.amount) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-surface-300 dark:border-surface-600">
                <td class="pt-3 text-sm font-bold text-surface-900 dark:text-surface-50">Total</td>
                <td class="pt-3 text-sm text-right font-bold text-surface-900 dark:text-surface-50">{{ formatXOF(spending.total_spend) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Overdue Invoices -->
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-surface-900 dark:text-surface-50 mb-4">Factures fournisseur en retard</h2>
        <div v-if="overdueInvoices.invoices.length === 0" class="text-sm text-surface-500 dark:text-surface-400">
          Aucune facture fournisseur en retard.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="invoice in overdueInvoices.invoices"
            :key="invoice.id"
            class="flex items-start gap-4 p-4 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20"
          >
            <div class="mt-0.5">
              <i class="pi pi-exclamation-circle text-lg text-red-600 dark:text-red-400" />
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-surface-900 dark:text-surface-50 text-sm">{{ invoice.partner_name || invoice.number }}</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300">
                  {{ invoice.number }}
                </span>
              </div>
              <div class="mt-1 flex items-center gap-4 text-xs text-surface-500 dark:text-surface-400">
                <span>Solde dû: <strong class="text-surface-900 dark:text-surface-50">{{ formatXOF(invoice.amount_due) }}</strong></span>
                <span>Échéance: {{ invoice.due_date }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Achats', 'view_dashboard')
const showAiPanel = ref(false)

const currentYear = new Date().getFullYear()
const selectedPeriod = ref('ytd')
const loading = ref(true)

const spending = ref({
  total_spend: 0,
  active_suppliers: 0,
  new_suppliers: 0,
  by_supplier: [],
  monthly_trend: [],
})
const pendingReceipts = ref([])
const overdueInvoices = ref({ count: 0, total_overdue: 0, invoices: [] })

const getAuthHeaders = () => ({
  Accept: 'application/json',
  Authorization: `Bearer ${document.querySelector('meta[name="api-token"]')?.content ?? ''}`,
})

const loadData = async () => {
  loading.value = true
  try {
    const [spendingRes, pendingRes, overdueRes] = await Promise.all([
      fetch(`/api/v1/achats/reports/spending?period=${selectedPeriod.value}`, { headers: getAuthHeaders() }),
      fetch('/api/v1/achats/reports/pending-receipts', { headers: getAuthHeaders() }),
      fetch('/api/v1/achats/reports/overdue-invoices', { headers: getAuthHeaders() }),
    ])

    if (spendingRes.ok) spending.value = await spendingRes.json()
    if (pendingRes.ok) pendingReceipts.value = await pendingRes.json()
    if (overdueRes.ok) overdueInvoices.value = await overdueRes.json()
  } finally {
    loading.value = false
  }
}

const formatXOF = (amount) => {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(amount || 0)
}

onMounted(loadData)
</script>
