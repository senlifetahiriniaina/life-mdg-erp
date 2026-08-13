<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">💾 Sauvegardes</h1>
          <p class="text-surface-500 mt-1">Gérez vos sauvegardes et planifications</p>
        </div>
        <Button icon="pi pi-plus" label="Nouvelle sauvegarde" @click="showCreateDialog = true" />
      </div>

      <!-- Tabs -->
      <TabView>
        <!-- Backups tab -->
        <TabPanel header="Sauvegardes">
          <DataTable :value="backups.data" striped-rows class="mt-4">
            <Column header="Type">
              <template #body="{ data }">
                <Tag :value="data.type" :severity="typeSeverity(data.type)" />
              </template>
            </Column>
            <Column header="Statut">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="statusSeverity(data.status)" />
              </template>
            </Column>
            <Column header="Taille">
              <template #body="{ data }">
                {{ data.size_bytes ? formatBytes(data.size_bytes) : '—' }}
              </template>
            </Column>
            <Column header="Déclenché par">
              <template #body="{ data }">
                {{ data.triggered_by?.name ?? 'Système' }}
              </template>
            </Column>
            <Column header="Démarré le">
              <template #body="{ data }">
                {{ data.started_at ? formatDate(data.started_at) : '—' }}
              </template>
            </Column>
            <Column header="Actions" style="width: 180px">
              <template #body="{ data }">
                <div class="flex gap-2">
                  <Button
                    v-if="data.status === 'completed'"
                    size="small"
                    icon="pi pi-download"
                    severity="secondary"
                    @click="downloadBackup(data)"
                    title="Télécharger"
                  />
                  <Button
                    v-if="data.status === 'completed'"
                    size="small"
                    icon="pi pi-replay"
                    severity="warn"
                    @click="openRestoreDialog(data)"
                    title="Restaurer"
                  />
                  <Button
                    size="small"
                    icon="pi pi-trash"
                    severity="danger"
                    @click="deleteBackup(data)"
                    title="Supprimer"
                  />
                </div>
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- Schedules tab -->
        <TabPanel header="Planifications">
          <div class="flex justify-end mt-2 mb-4">
            <Button size="small" icon="pi pi-plus" label="Nouvelle planification" @click="showScheduleDialog = true" />
          </div>
          <DataTable :value="scheduleList" striped-rows>
            <Column header="Type">
              <template #body="{ data }">
                <Tag :value="data.type" :severity="typeSeverity(data.type)" />
              </template>
            </Column>
            <Column field="frequency" header="Fréquence" />
            <Column field="time_of_day" header="Heure" />
            <Column header="Rétention">
              <template #body="{ data }">{{ data.retention_days }} jours</template>
            </Column>
            <Column field="storage_driver" header="Stockage" />
            <Column header="Actif">
              <template #body="{ data }">
                <ToggleSwitch
                  :model-value="data.enabled"
                  @update:model-value="toggleSchedule(data, $event)"
                />
              </template>
            </Column>
            <Column header="Actions">
              <template #body="{ data }">
                <Button size="small" icon="pi pi-trash" severity="danger" @click="deleteSchedule(data)" />
              </template>
            </Column>
          </DataTable>
        </TabPanel>
      </TabView>

      <!-- Create backup dialog -->
      <Dialog v-model:visible="showCreateDialog" header="Nouvelle sauvegarde" :style="{ width: '420px' }" modal>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Type</label>
            <Select v-model="backupForm.type" :options="backupTypes" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Stockage</label>
            <Select v-model="backupForm.storage_driver" :options="storageDrivers" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Notes (optionnel)</label>
            <Textarea v-model="backupForm.notes" class="w-full" rows="2" />
          </div>
        </div>
        <template #footer>
          <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
          <Button label="Démarrer" :loading="saving" @click="triggerBackup" />
        </template>
      </Dialog>

      <!-- Restore warning dialog -->
      <Dialog v-model:visible="showRestoreDialog" header="Restauration" :style="{ width: '440px' }" modal>
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg p-4 mb-4">
          <div class="flex items-start gap-3">
            <i class="pi pi-exclamation-triangle text-red-600 text-xl mt-0.5" />
            <div>
              <div class="font-semibold text-red-700 dark:text-red-400">Action irréversible</div>
              <div class="text-red-600 dark:text-red-300 text-sm mt-1">
                Cette action remplacera intégralement la base de données par la sauvegarde sélectionnée.
                Toutes les données actuelles seront perdues.
              </div>
            </div>
          </div>
        </div>
        <p class="text-surface-700 dark:text-surface-300 text-sm">
          Confirmez-vous la restauration de la sauvegarde
          <strong>{{ selectedBackup?.file_path }}</strong> ?
        </p>
        <template #footer>
          <Button label="Annuler" severity="secondary" @click="showRestoreDialog = false" />
          <Button label="Confirmer la restauration" severity="danger" :loading="restoring" @click="confirmRestore" />
        </template>
      </Dialog>

      <!-- Schedule creation dialog -->
      <Dialog v-model:visible="showScheduleDialog" header="Nouvelle planification" :style="{ width: '420px' }" modal>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Type</label>
            <Select v-model="scheduleForm.type" :options="backupTypes" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Fréquence</label>
            <Select v-model="scheduleForm.frequency" :options="frequencies" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Heure (ex: 02:00)</label>
            <InputText v-model="scheduleForm.time_of_day" class="w-full" placeholder="02:00" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Rétention (jours)</label>
            <InputNumber v-model="scheduleForm.retention_days" class="w-full" :min="1" :max="365" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Stockage</label>
            <Select v-model="scheduleForm.storage_driver" :options="storageDrivers" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>
        <template #footer>
          <Button label="Annuler" severity="secondary" @click="showScheduleDialog = false" />
          <Button label="Créer" :loading="savingSchedule" @click="createSchedule" />
        </template>
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
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  backups: { type: Object, default: () => ({ data: [] }) },
  schedules: { type: Array, default: () => [] },
})

const toast = useToast()
const saving = ref(false)
const restoring = ref(false)
const savingSchedule = ref(false)
const showCreateDialog = ref(false)
const showRestoreDialog = ref(false)
const showScheduleDialog = ref(false)
const selectedBackup = ref(null)
const scheduleList = ref(props.schedules)

const backupForm = ref({ type: 'database', storage_driver: 'local', notes: '' })
const scheduleForm = ref({ type: 'database', frequency: 'daily', time_of_day: '02:00', retention_days: 30, storage_driver: 'local' })

const backupTypes = [
  { label: 'Base de données', value: 'database' },
  { label: 'Fichiers', value: 'files' },
  { label: 'Complet', value: 'full' },
]

const storageDrivers = [
  { label: 'Local', value: 'local' },
  { label: 'Amazon S3', value: 's3' },
  { label: 'Google Cloud Storage', value: 'gcs' },
  { label: 'Azure Blob', value: 'azure_blob' },
]

const frequencies = [
  { label: 'Toutes les heures', value: 'hourly' },
  { label: 'Quotidienne', value: 'daily' },
  { label: 'Hebdomadaire', value: 'weekly' },
  { label: 'Mensuelle', value: 'monthly' },
]

const typeSeverity = (t) => ({ database: 'info', files: 'secondary', full: 'warn' })[t] ?? 'secondary'
const statusSeverity = (s) => ({ completed: 'success', running: 'info', failed: 'danger', pending: 'secondary' })[s] ?? 'secondary'

const formatDate = (iso) => new Date(iso).toLocaleString('fr-FR')
const formatBytes = (bytes) => {
  if (!bytes) return '0 B'
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`
}

const triggerBackup = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/admin/backups', backupForm.value)
    toast.add({ severity: 'success', summary: 'Sauvegarde déclenchée', life: 3000 })
    showCreateDialog.value = false
    window.location.reload()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message, life: 4000 })
  } finally {
    saving.value = false
  }
}

const downloadBackup = async (backup) => {
  try {
    const { data } = await axios.post(`/api/v1/admin/backups/${backup.id}/download`)
    window.open(data.download_url, '_blank')
  } catch {
    toast.add({ severity: 'error', summary: 'Impossible de générer le lien', life: 3000 })
  }
}

const openRestoreDialog = (backup) => {
  selectedBackup.value = backup
  showRestoreDialog.value = true
}

const confirmRestore = async () => {
  restoring.value = true
  try {
    await axios.post(`/api/v1/admin/backups/${selectedBackup.value.id}/restore`, { confirm: true })
    toast.add({ severity: 'warn', summary: 'Restauration planifiée', detail: 'Le job de restauration a été mis en file d\'attente.', life: 5000 })
    showRestoreDialog.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message, life: 4000 })
  } finally {
    restoring.value = false
  }
}

const deleteBackup = async (backup) => {
  if (!confirm('Supprimer cet enregistrement de sauvegarde ?')) return
  try {
    await axios.delete(`/api/v1/admin/backups/${backup.id}`)
    toast.add({ severity: 'success', summary: 'Supprimé', life: 3000 })
    window.location.reload()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', life: 3000 })
  }
}

const createSchedule = async () => {
  savingSchedule.value = true
  try {
    const { data } = await axios.post('/api/v1/admin/backup-schedules', scheduleForm.value)
    scheduleList.value.push(data)
    toast.add({ severity: 'success', summary: 'Planification créée', life: 3000 })
    showScheduleDialog.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message, life: 4000 })
  } finally {
    savingSchedule.value = false
  }
}

const toggleSchedule = async (schedule, enabled) => {
  try {
    await axios.put(`/api/v1/admin/backup-schedules/${schedule.id}`, { enabled })
    schedule.enabled = enabled
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur lors de la mise à jour', life: 3000 })
  }
}

const deleteSchedule = async (schedule) => {
  if (!confirm('Supprimer cette planification ?')) return
  try {
    await axios.delete(`/api/v1/admin/backup-schedules/${schedule.id}`)
    scheduleList.value = scheduleList.value.filter(s => s.id !== schedule.id)
    toast.add({ severity: 'success', summary: 'Planification supprimée', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', life: 3000 })
  }
}
</script>
