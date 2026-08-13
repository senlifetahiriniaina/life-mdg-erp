<template>
  <AppLayout title="Signatures électroniques">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Signatures électroniques</h1>
          <p class="text-surface-500 dark:text-surface-400 mt-1">Suivez les demandes de signature et leur statut</p>
        </div>
        <Button label="Nouvelle demande" icon="pi pi-plus" @click="createModal = true" />
      </div>

      <!-- KPI row -->
      <div class="grid grid-cols-4 gap-4">
        <div v-for="kpi in kpis" :key="kpi.label" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">{{ kpi.label }}</p>
          <p :class="['text-3xl font-bold mt-1', kpi.color]">{{ kpi.value }}</p>
        </div>
      </div>

      <!-- Signature requests table -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100">
        <DataTable :value="requests" stripedRows paginator :rows="15" class="text-sm">
          <Column field="document_name" header="Document" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <i class="pi pi-file-pdf text-red-500" />
                <span class="font-medium text-primary-700 dark:text-primary-300 cursor-pointer hover:underline">{{ data.document_name }}</span>
              </div>
            </template>
          </Column>
          <Column field="initiated_by" header="Demandé par" />
          <Column header="Signataires">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Avatar
                  v-for="s in data.signatories.slice(0, 3)"
                  :key="s.id"
                  :label="s.name[0]"
                  size="small"
                  :class="s.signed ? 'bg-green-100 text-green-700' : 'bg-gray-100 dark:bg-surface-700 text-surface-500 dark:text-surface-400'"
                />
                <span v-if="data.signatories.length > 3" class="text-xs text-surface-400 dark:text-surface-500 self-center">+{{ data.signatories.length - 3 }}</span>
              </div>
            </template>
          </Column>
          <Column field="progress" header="Avancement">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-20 bg-gray-200 rounded-full h-2">
                  <div class="bg-green-50 dark:bg-green-900/200 h-2 rounded-full" :style="{ width: data.progress + '%' }" />
                </div>
                <span class="text-xs text-surface-600 dark:text-surface-400">{{ data.progress }}%</span>
              </div>
            </template>
          </Column>
          <Column field="status" header="Statut">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="reqSeverity(data.status)" />
            </template>
          </Column>
          <Column field="expires_at" header="Expire le" sortable />
          <Column header="">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-eye" text size="small" @click="viewRequest(data)" />
                <Button icon="pi pi-send" text size="small" @click="remind(data)" v-if="data.status === 'pending'" />
                <Button icon="pi pi-times" text size="small" severity="danger" @click="cancel(data)" v-if="data.status === 'pending'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Create Modal -->
    <Dialog v-model:visible="createModal" header="Nouvelle demande de signature" :style="{ width: '36rem' }" modal>
      <div class="space-y-4">
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Document</label>
          <Dropdown v-model="newReq.document_id" :options="availableDocs" optionLabel="name" optionValue="id" class="w-full" placeholder="Choisir un document" />
        </div>
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Signataires (emails)</label>
          <Chips v-model="newReq.emails" placeholder="Ajouter email + Entrée" class="w-full" />
        </div>
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Message (optionnel)</label>
          <Textarea v-model="newReq.message" rows="2" class="w-full" placeholder="Veuillez signer ce document avant..." />
        </div>
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Date d'expiration</label>
          <DatePicker v-model="newReq.expires_at" class="w-full" dateFormat="dd/mm/yy" :minDate="new Date()" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="createModal = false" />
          <Button label="Envoyer la demande" severity="success" @click="sendRequest" />
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag, Dialog, Dropdown, Textarea, Avatar, DataTable, Column, Chips, DatePicker } from 'primevue'
import axios from 'axios'

const createModal = ref(false)
const newReq = ref({ document_id: null, emails: [], message: '', expires_at: null })

const kpis = ref([
  { label: 'En attente', value: 8, color: 'text-yellow-700 dark:text-yellow-300' },
  { label: 'Signées', value: 34, color: 'text-green-700 dark:text-green-300' },
  { label: 'Expirées', value: 2, color: 'text-red-700 dark:text-red-300' },
  { label: 'Ce mois-ci', value: 12, color: 'text-primary-700 dark:text-primary-300' },
])

const requests = ref([
  { id: 1, document_name: 'Contrat de prestation - Mars 2026.pdf', initiated_by: 'Admin', signatories: [{ id: 1, name: 'Jean D.', signed: true }, { id: 2, name: 'Marie M.', signed: false }], progress: 50, status: 'pending', expires_at: '15/05/2026' },
  { id: 2, document_name: 'NDA Partenaire XYZ.pdf', initiated_by: 'Admin', signatories: [{ id: 3, name: 'Pierre L.', signed: true }], progress: 100, status: 'completed', expires_at: '01/05/2026' },
  { id: 3, document_name: 'Accord de confidentialité.pdf', initiated_by: 'Admin', signatories: [{ id: 4, name: 'Sophie R.', signed: false }, { id: 5, name: 'Marc B.', signed: false }], progress: 0, status: 'pending', expires_at: '20/05/2026' },
])

const availableDocs = ref([
  { id: 1, name: 'Contrat de prestation.pdf' },
  { id: 2, name: 'NDA Standard.pdf' },
  { id: 3, name: 'Bon de commande.pdf' },
])

const reqSeverity = (s) => ({ pending: 'warn', completed: 'success', expired: 'danger', cancelled: 'secondary' }[s] || 'secondary')

function viewRequest(req) {}
function remind(req) {
  axios.post(`/api/v1/documents/signature-requests/${req.id}/remind`).catch(() => {})
}
function cancel(req) {
  req.status = 'cancelled'
  axios.patch(`/api/v1/documents/signature-requests/${req.id}`, { status: 'cancelled' }).catch(() => {})
}
function sendRequest() {
  axios.post('/api/v1/documents/signature-requests', newReq.value)
    .then(() => { createModal.value = false; newReq.value = { document_id: null, emails: [], message: '', expires_at: null } })
    .catch(() => { createModal.value = false })
}
</script>
