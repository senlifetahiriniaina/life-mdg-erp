<template>
  <AppLayout>
    <Head title="Ventes — WideHalo ERP" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Commandes de vente
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Gérez vos commandes, devis et factures clients
          </p>
        </div>
        <Button
          icon="pi pi-sparkles"
          outlined
          @click="showAiPanel = !showAiPanel"
          title="Assistant IA"
        />
        <Button
          v-if="hasAnyRole(['sales-rep','sales-manager','manager']) || isAdmin"
          icon="pi pi-plus"
          label="Nouvelle commande"
          @click="createOrder"
        />
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="kpi in kpis"
          :key="kpi.label"
          class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 font-medium">{{ kpi.label }}</p>
              <p class="text-2xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : kpi.value }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="kpi.iconBg">
              <i :class="['text-lg', kpi.icon, kpi.iconColor]" />
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <div class="flex-1 min-w-48">
            <InputText v-model="filters.search" placeholder="Rechercher une commande…" class="w-full" @input="onSearch" />
          </div>
          <Select
            v-model="filters.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            placeholder="Tous les statuts"
            show-clear
            class="w-48"
            @change="fetchOrders"
          />
          <Button icon="pi pi-filter-slash" outlined @click="clearFilters" />
        </div>
      </div>

      <!-- Orders table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="orders"
          :loading="loading"
          class="p-datatable-sm"
          striped-rows
        >
          <Column field="reference" header="Référence" style="width: 160px">
            <template #body="{ data }">
              <span class="font-mono text-sm font-medium text-primary-600">{{ data.reference }}</span>
            </template>
          </Column>
          <Column field="customer" header="Client">
            <template #body="{ data }">
              <span class="font-medium text-surface-900 dark:text-surface-50">{{ data.customer?.name ?? '—' }}</span>
            </template>
          </Column>
          <Column field="amount" header="Montant" style="width: 140px">
            <template #body="{ data }">
              <span class="font-semibold">{{ formatAmount(data.amount, data.currency) }}</span>
            </template>
          </Column>
          <Column field="status" header="Statut" style="width: 120px">
            <template #body="{ data }">
              <Tag
                :value="statusLabel(data.status)"
                :severity="statusSeverity(data.status)"
                class="text-xs"
              />
            </template>
          </Column>
          <Column field="order_date" header="Date" style="width: 160px">
            <template #body="{ data }">
              {{ formatDate(data.order_date) }}
            </template>
          </Column>
          <Column header="Actions" style="width: 100px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-pencil" outlined size="small" @click="editOrder(data)" />
                <Button icon="pi pi-eye" outlined size="small" severity="info" @click="viewOrder(data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-16 text-surface-400">
              <i class="pi pi-shopping-cart text-5xl mb-4 block" />
              <p class="font-medium">Aucune commande trouvée</p>
              <p class="text-sm mt-1">Cliquez sur <strong>Nouvelle commande</strong> pour créer votre première commande.</p>
            </div>
          </template>
        </DataTable>
      </div>
      <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useRoleAccess } from '@/composables/useRoleAccess'

const page = usePage()
const canManage = computed(() => isElevated.value || hasAnyRole(['sales-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const { guidance } = useAiAssistant('Sales', 'create_order')
const showAiPanel = ref(false)
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()

interface SalesOrder {
  id: number
  reference: string
  customer: { id: number; name: string } | null
  amount: number
  currency: string
  status: string
  order_date: string
}

const loading = ref(false)
const orders = ref<SalesOrder[]>([])
const filters = reactive({ search: '', status: null as string | null })

const kpis = reactive([
  { label: 'Commandes du mois', value: '0', icon: 'pi pi-shopping-cart', iconBg: 'bg-blue-50 dark:bg-blue-900/30', iconColor: 'text-blue-600' },
  { label: 'Chiffre d\'affaires', value: '0 XOF', icon: 'pi pi-dollar', iconBg: 'bg-green-50 dark:bg-green-900/30', iconColor: 'text-green-600' },
  { label: 'En attente', value: '0', icon: 'pi pi-clock', iconBg: 'bg-orange-50 dark:bg-orange-900/30', iconColor: 'text-orange-600' },
  { label: 'Livrées', value: '0', icon: 'pi pi-check-circle', iconBg: 'bg-violet-50 dark:bg-violet-900/30', iconColor: 'text-violet-600' },
])

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Confirmée', value: 'confirmed' },
  { label: 'En cours', value: 'processing' },
  { label: 'Livrée', value: 'delivered' },
  { label: 'Annulée', value: 'cancelled' },
]

let searchTimer: ReturnType<typeof setTimeout> | null = null
const onSearch = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchOrders, 400)
}

const fetchOrders = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    const res = await fetch(`/api/v1/sales/orders?${params}`, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    orders.value = data.data ?? []
    // Update KPIs from meta
    if (data.meta) {
      kpis[0].value = (data.meta.month_count ?? 0).toString()
      kpis[1].value = formatAmount(data.meta.month_revenue ?? 0, 'XOF')
      kpis[2].value = (data.meta.pending_count ?? 0).toString()
      kpis[3].value = (data.meta.delivered_count ?? 0).toString()
    }
  } catch (e) {
    console.error('Erreur chargement commandes', e)
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  filters.search = ''
  filters.status = null
  fetchOrders()
}

const formatDate = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
const formatAmount = (amount: number, currency = 'XOF') =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)

const statusLabel = (s: string) => ({ draft: 'Brouillon', confirmed: 'Confirmée', processing: 'En cours', delivered: 'Livrée', cancelled: 'Annulée' }[s] ?? s)
const statusSeverity = (s: string) => ({ draft: 'secondary', confirmed: 'info', processing: 'warning', delivered: 'success', cancelled: 'danger' }[s] ?? 'secondary')

const createOrder = () => router.visit('/sales/orders/create')
const editOrder = (order: SalesOrder) => router.visit(`/sales/orders/${order.id}/edit`)
const viewOrder = (order: SalesOrder) => router.visit(`/sales/orders/${order.id}`)

onMounted(fetchOrders)
</script>
