<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">📋 Logs d'audit</h1>
          <p class="text-surface-500 mt-1">Traçabilité de toutes les actions sensibles</p>
        </div>
        <Button icon="pi pi-download" label="Exporter CSV" severity="secondary" @click="exportCsv" />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl p-4 flex flex-wrap gap-3">
        <InputText v-model="filters.user_id" placeholder="ID utilisateur" style="width: 140px" @change="applyFilters" />
        <Select v-model="filters.action" :options="actionOptions" option-label="label" option-value="value" placeholder="Toutes les actions" show-clear style="width: 200px" @change="applyFilters" />
        <InputText v-model="filters.from" type="date" placeholder="Du" style="width: 150px" @change="applyFilters" />
        <InputText v-model="filters.to" type="date" placeholder="Au" style="width: 150px" @change="applyFilters" />
        <Button icon="pi pi-filter-slash" severity="secondary" title="Réinitialiser" @click="resetFilters" />
      </div>

      <!-- DataTable -->
      <DataTable :value="logList" striped-rows :loading="loading" class="shadow-sm border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
        <Column header="Date">
          <template #body="{ data }">
            <span class="text-sm text-surface-500 font-mono">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>
        <Column header="Utilisateur">
          <template #body="{ data }">
            <span class="text-sm">{{ data.user?.name ?? 'Système' }}</span>
            <div class="text-xs text-surface-400">{{ data.user?.email ?? '' }}</div>
          </template>
        </Column>
        <Column header="Action">
          <template #body="{ data }">
            <Tag :value="data.action" :severity="actionSeverity(data.action)" />
          </template>
        </Column>
        <Column header="Ressource">
          <template #body="{ data }">
            <span class="text-sm">{{ data.resource_type ?? '—' }}</span>
            <span v-if="data.resource_id" class="text-xs text-surface-400 ml-1">#{{ data.resource_id }}</span>
          </template>
        </Column>
        <Column field="ip_address" header="IP" />
        <Column header="Détails">
          <template #body="{ data }">
            <Button
              v-if="data.payload"
              size="small"
              icon="pi pi-eye"
              severity="secondary"
              @click="viewPayload(data)"
            />
          </template>
        </Column>
      </DataTable>

      <!-- Payload dialog -->
      <Dialog v-model:visible="showPayloadDialog" header="Détail de l'action" :style="{ width: '500px' }" modal>
        <pre class="bg-surface-100 dark:bg-surface-900 rounded-lg p-4 text-xs overflow-auto max-h-64">{{ JSON.stringify(selectedPayload, null, 2) }}</pre>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  logs: { type: Object, default: () => ({ data: [] }) },
})

const toast = useToast()
const loading = ref(false)
const showPayloadDialog = ref(false)
const selectedPayload = ref(null)
const logList = ref(props.logs.data)

const filters = ref({
  user_id: '',
  action: null,
  from: '',
  to: '',
})

const actionOptions = [
  { label: 'Créer', value: 'create' },
  { label: 'Mettre à jour', value: 'update' },
  { label: 'Supprimer', value: 'delete' },
  { label: 'Connexion', value: 'login' },
  { label: 'Déconnexion', value: 'logout' },
  { label: 'Ping', value: 'ping' },
  { label: 'Restauration', value: 'restore' },
  { label: 'Sécurité', value: 'security' },
  { label: 'Déploiement', value: 'deploy' },
  { label: 'Assigner rôle', value: 'assign_role' },
  { label: 'Révoquer rôle', value: 'revoke_role' },
]

const actionSeverity = (action) => {
  const map = {
    create: 'success',
    update: 'info',
    delete: 'danger',
    login: 'secondary',
    logout: 'secondary',
    security: 'warn',
    deploy: 'warn',
    restore: 'danger',
    assign_role: 'info',
    revoke_role: 'warn',
  }
  return map[action] ?? 'secondary'
}

const formatDate = (iso) => new Date(iso).toLocaleString('fr-FR')

const applyFilters = async () => {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v))
    const { data } = await axios.get('/api/v1/admin/audit-logs', { params })
    logList.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur lors du chargement', life: 3000 })
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.value = { user_id: '', action: null, from: '', to: '' }
  logList.value = props.logs.data
}

const viewPayload = (log) => {
  selectedPayload.value = log.payload
  showPayloadDialog.value = true
}

const exportCsv = () => {
  const headers = ['Date', 'Utilisateur', 'Email', 'Action', 'Ressource', 'ID', 'IP']
  const rows = logList.value.map(l => [
    formatDate(l.created_at),
    l.user?.name ?? '',
    l.user?.email ?? '',
    l.action,
    l.resource_type ?? '',
    l.resource_id ?? '',
    l.ip_address ?? '',
  ])
  const csv = [headers, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `audit_logs_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
</script>
