<template>
  <AppLayout>
    <Head title="Rapports — WideHalo ERP" />

    <div class="space-y-6">
      <!-- AI Assistant -->
      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Rapports
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Générez et consultez les rapports de votre activité
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="Nouveau rapport"
          @click="createReport"
        />
      </div>

      <!-- Quick reports -->
      <div>
        <h2 class="font-semibold text-surface-700 dark:text-surface-300 mb-3">Rapports rapides</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div
            v-for="quick in quickReports"
            :key="quick.id"
            class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 cursor-pointer hover:border-primary-300 dark:hover:border-primary-600 transition-all group"
            @click="runQuickReport(quick)"
           role="button" tabindex="0" @keydown.enter.prevent="runQuickReport(quick)">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="quick.iconBg">
                <i :class="['text-lg', quick.icon, quick.iconColor]" />
              </div>
              <div>
                <h3 class="font-semibold text-sm text-surface-900 dark:text-surface-50 group-hover:text-primary-600">{{ quick.label }}</h3>
                <p class="text-xs text-surface-400 mt-0.5">{{ quick.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Report history -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
        <div class="flex items-center justify-between px-5 py-4 border-b border-surface-200 dark:border-surface-700">
          <h2 class="font-semibold text-surface-900 dark:text-surface-50">Historique des rapports</h2>
          <div class="flex gap-2">
            <Select
              v-model="filters.module"
              :options="moduleOptions"
              option-label="label"
              option-value="value"
              placeholder="Tous les modules"
              show-clear
              class="text-sm"
              @change="fetchReports"
            />
            <Button icon="pi pi-refresh" outlined size="small" :loading="loading" @click="fetchReports" />
          </div>
        </div>

        <DataTable :value="reports" :loading="loading" class="p-datatable-sm" striped-rows>
          <Column field="name" header="Nom du rapport">
            <template #body="{ data }">
              <span class="font-medium text-surface-900 dark:text-surface-50">{{ data.name }}</span>
            </template>
          </Column>
          <Column field="module" header="Module" style="width: 140px">
            <template #body="{ data }">
              <Tag :value="data.module" severity="secondary" class="text-xs" />
            </template>
          </Column>
          <Column field="status" header="Statut" style="width: 120px">
            <template #body="{ data }">
              <Tag
                :value="reportStatusLabel(data.status)"
                :severity="reportStatusSeverity(data.status)"
                class="text-xs"
              />
            </template>
          </Column>
          <Column field="generated_at" header="Généré le" style="width: 180px">
            <template #body="{ data }">
              {{ data.generated_at ? formatDate(data.generated_at) : '—' }}
            </template>
          </Column>
          <Column header="Actions" style="width: 100px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-download"
                  outlined
                  size="small"
                  :disabled="data.status !== 'ready'"
                  @click="downloadReport(data)"
                />
                <Button icon="pi pi-eye" outlined size="small" severity="info" @click="viewReport(data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-16 text-surface-400">
              <i class="pi pi-chart-bar text-5xl mb-4 block" />
              <p class="font-medium">Aucun rapport généré</p>
              <p class="text-sm mt-1">Cliquez sur <strong>Nouveau rapport</strong> ou utilisez un rapport rapide.</p>
            </div>
          </template>
        </DataTable>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed} from 'vue'
import { Head, router, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Reporting', 'view_dashboard')

const page = usePage()
const { isAdmin, isElevated } = useRoleAccess()
const canManage = computed(() => isElevated.value)
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


interface Report {
  id: number
  name: string
  module: string
  status: 'pending' | 'running' | 'ready' | 'failed'
  generated_at: string | null
  file_url: string | null
}

const loading = ref(false)
const reports = ref<Report[]>([])
const filters = reactive({ module: null as string | null })

const quickReports = [
  { id: 'sales_monthly', label: 'Ventes du mois', description: 'CA et commandes du mois en cours', icon: 'pi pi-chart-line', iconBg: 'bg-green-50 dark:bg-green-900/30', iconColor: 'text-green-600' },
  { id: 'stock_valuation', label: 'Valorisation du stock', description: 'Valeur actuelle de l\'inventaire', icon: 'pi pi-box', iconBg: 'bg-blue-50 dark:bg-blue-900/30', iconColor: 'text-blue-600' },
  { id: 'aged_receivables', label: 'Balance âgée clients', description: 'Créances en attente par ancienneté', icon: 'pi pi-wallet', iconBg: 'bg-orange-50 dark:bg-orange-900/30', iconColor: 'text-orange-600' },
  { id: 'ohada_balance', label: 'Bilan OHADA', description: 'Bilan comptable conforme OHADA/SYSCOHADA', icon: 'pi pi-file', iconBg: 'bg-violet-50 dark:bg-violet-900/30', iconColor: 'text-violet-600' },
]

const moduleOptions = [
  { label: 'Ventes', value: 'Sales' },
  { label: 'Comptabilité', value: 'Accounting' },
  { label: 'Inventaire', value: 'Inventory' },
  { label: 'RH', value: 'HR' },
  { label: 'CRM', value: 'CRM' },
]

const fetchReports = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filters.module) params.set('module', filters.module)
    const res = await fetch(`/api/v1/reporting/reports?${params}`, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    reports.value = data.data ?? []
  } catch (e) {
    console.error('Erreur chargement rapports', e)
  } finally {
    loading.value = false
  }
}

const runQuickReport = async (quick: typeof quickReports[0]) => {
  await fetch('/api/v1/reporting/generate', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ report_type: quick.id }),
  })
  fetchReports()
}

const createReport = () => router.visit('/reporting/create')
const viewReport = (r: Report) => router.visit(`/reporting/reports/${r.id}`)
const downloadReport = (r: Report) => { if (r.file_url) window.open(r.file_url, '_blank') }

const formatDate = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
const reportStatusLabel = (s: string) => ({ pending: 'En attente', running: 'En cours', ready: 'Prêt', failed: 'Échoué' }[s] ?? s)
const reportStatusSeverity = (s: string) => ({ pending: 'secondary', running: 'info', ready: 'success', failed: 'danger' }[s] ?? 'secondary')

onMounted(fetchReports)
</script>
