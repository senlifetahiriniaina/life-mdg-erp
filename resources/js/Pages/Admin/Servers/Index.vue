<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">
            🖥️ Serveurs & Infrastructure
          </h1>
          <p class="text-surface-500 mt-1">Gérez vos serveurs cloud et on-premise</p>
        </div>
        <Button icon="pi pi-plus" label="Nouveau serveur" @click="showCreateDialog = true" />
      </div>

      <!-- DataTable -->
      <DataTable
        :value="servers.data"
        :loading="loading"
        striped-rows
        class="shadow-sm border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden"
      >
        <Column field="name" header="Nom" sortable />
        <Column header="Provider">
          <template #body="{ data }">
            <span class="flex items-center gap-2">
              <span>{{ providerIcon(data.provider) }}</span>
              <span class="text-sm font-medium">{{ providerLabel(data.provider) }}</span>
            </span>
          </template>
        </Column>
        <Column field="region" header="Région" />
        <Column field="ip_address" header="IP" />
        <Column header="Statut">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Dernier ping">
          <template #body="{ data }">
            <span class="text-sm text-surface-500">
              {{ data.last_ping_at ? formatDate(data.last_ping_at) : '—' }}
              <span v-if="pingResults[data.id]" class="ml-2 text-green-600 font-mono text-xs">
                {{ pingResults[data.id] }}ms
              </span>
            </span>
          </template>
        </Column>
        <Column header="Actions" style="width: 220px">
          <template #body="{ data }">
            <div class="flex gap-2 flex-wrap">
              <Button
                size="small"
                icon="pi pi-wifi"
                label="Ping"
                severity="secondary"
                :loading="pinging[data.id]"
                @click="pingServer(data)"
              />
              <Button
                size="small"
                icon="pi pi-chart-bar"
                label="Métriques"
                severity="info"
                @click="openMetrics(data)"
              />
              <Button
                v-if="consoleUrl(data.provider)"
                size="small"
                icon="pi pi-external-link"
                severity="secondary"
                as="a"
                :href="consoleUrl(data.provider)"
                target="_blank"
                title="Console cloud"
              />
              <Button
                size="small"
                icon="pi pi-trash"
                severity="danger"
                @click="deleteServer(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Create dialog -->
      <Dialog v-model:visible="showCreateDialog" header="Nouveau serveur" :style="{ width: '480px' }" modal>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <InputText v-model="form.name" class="w-full" placeholder="prod-api-01" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Provider</label>
            <Select v-model="form.provider" :options="providerOptions" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Région</label>
            <InputText v-model="form.region" class="w-full" placeholder="eu-west-1" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Type d'instance</label>
            <InputText v-model="form.instance_type" class="w-full" placeholder="t3.medium" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Adresse IP</label>
            <InputText v-model="form.ip_address" class="w-full" placeholder="10.0.0.1" />
          </div>
        </div>
        <template #footer>
          <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
          <Button label="Créer" :loading="saving" @click="createServer" />
        </template>
      </Dialog>

      <!-- Metrics dialog -->
      <Dialog v-model:visible="showMetricsDialog" :header="`Métriques — ${selectedServer?.name}`" :style="{ width: '440px' }" modal>
        <div v-if="metrics" class="space-y-4">
          <div v-for="(item, key) in metricsDisplay" :key="key">
            <div class="flex justify-between mb-1">
              <span class="text-sm font-medium">{{ item.label }}</span>
              <span class="text-sm text-surface-500">{{ item.value }}{{ item.unit }}</span>
            </div>
            <ProgressBar v-if="item.progress !== undefined" :value="item.progress" />
          </div>
          <div class="text-sm text-surface-500 mt-2">
            Uptime : {{ metrics.uptime_days }} jours
          </div>
        </div>
        <div v-else class="flex justify-center py-8">
          <i class="pi pi-spin pi-spinner text-2xl text-primary-500" />
        </div>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ProgressBar from 'primevue/progressbar'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  servers: { type: Object, default: () => ({ data: [] }) },
})

const toast = useToast()
const loading = ref(false)
const saving = ref(false)
const pinging = ref({})
const pingResults = ref({})
const showCreateDialog = ref(false)
const showMetricsDialog = ref(false)
const selectedServer = ref(null)
const metrics = ref(null)

const form = ref({
  name: '',
  provider: 'aws',
  region: '',
  instance_type: '',
  ip_address: '',
})

const providerOptions = [
  { label: '☁️ Google Cloud Platform', value: 'gcp' },
  { label: '🟠 Amazon Web Services', value: 'aws' },
  { label: '🔷 Microsoft Azure', value: 'azure' },
  { label: '💙 DigitalOcean', value: 'digitalocean' },
  { label: '⚙️ Hetzner Cloud', value: 'hetzner' },
  { label: '🔴 OVHcloud', value: 'ovh' },
  { label: '🏢 Custom / On-Premise', value: 'custom' },
]

const providerIconMap = {
  gcp: '☁️', aws: '🟠', azure: '🔷', digitalocean: '💙',
  hetzner: '⚙️', ovh: '🔴', custom: '🏢',
}

const providerLabelMap = {
  gcp: 'Google Cloud', aws: 'AWS', azure: 'Azure',
  digitalocean: 'DigitalOcean', hetzner: 'Hetzner', ovh: 'OVH', custom: 'Custom',
}

const consoleUrlMap = {
  gcp: 'https://console.cloud.google.com',
  aws: 'https://aws.amazon.com/console',
  azure: 'https://portal.azure.com',
  digitalocean: 'https://cloud.digitalocean.com',
  hetzner: 'https://console.hetzner.cloud',
  ovh: 'https://www.ovhcloud.com',
  custom: null,
}

const providerIcon = (p) => providerIconMap[p] ?? '🖥️'
const providerLabel = (p) => providerLabelMap[p] ?? p
const consoleUrl = (p) => consoleUrlMap[p] ?? null

const statusSeverity = (s) => {
  const map = { active: 'success', stopped: 'danger', maintenance: 'warn', unknown: 'secondary' }
  return map[s] ?? 'secondary'
}

const formatDate = (iso) => new Date(iso).toLocaleString('fr-FR')

const metricsDisplay = computed(() => {
  if (!metrics.value) return {}
  return {
    cpu: { label: 'CPU', value: metrics.value.cpu_usage, unit: '%', progress: metrics.value.cpu_usage },
    memory: { label: 'Mémoire', value: metrics.value.memory_usage, unit: '%', progress: metrics.value.memory_usage },
    disk: { label: 'Disque', value: metrics.value.disk_usage, unit: '%', progress: metrics.value.disk_usage },
  }
})

const pingServer = async (server) => {
  pinging.value[server.id] = true
  try {
    const { data } = await axios.post(`/api/v1/admin/servers/${server.id}/ping`)
    pingResults.value[server.id] = data.latency_ms
    toast.add({ severity: 'success', summary: 'Ping réussi', detail: `${data.latency_ms}ms`, life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Ping échoué', life: 3000 })
  } finally {
    pinging.value[server.id] = false
  }
}

const openMetrics = async (server) => {
  selectedServer.value = server
  metrics.value = null
  showMetricsDialog.value = true
  try {
    const { data } = await axios.get(`/api/v1/admin/servers/${server.id}/metrics`)
    metrics.value = data
  } catch {
    toast.add({ severity: 'error', summary: 'Impossible de charger les métriques', life: 3000 })
  }
}

const createServer = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/admin/servers', form.value)
    toast.add({ severity: 'success', summary: 'Serveur créé', life: 3000 })
    showCreateDialog.value = false
    window.location.reload()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message, life: 4000 })
  } finally {
    saving.value = false
  }
}

const deleteServer = async (server) => {
  if (!confirm(`Supprimer ${server.name} ?`)) return
  try {
    await axios.delete(`/api/v1/admin/servers/${server.id}`)
    toast.add({ severity: 'success', summary: 'Serveur supprimé', life: 3000 })
    window.location.reload()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur lors de la suppression', life: 3000 })
  }
}
</script>
