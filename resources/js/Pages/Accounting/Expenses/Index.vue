<template>
  <AppLayout title="Notes de frais">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Mes notes de frais</h1>
        <Button label="Nouvelle note de frais" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>

      <!-- Expense Reports List -->
      <DataTable :value="reports" :loading="loading" paginator :rows="20" class="mb-6">
        <Column field="id" header="#" style="width: 60px" />
        <Column field="title" header="Titre" />
        <Column header="Statut">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Montant total">
          <template #body="{ data }">
            <span class="font-semibold">{{ Number(data.total).toFixed(2) }} €</span>
          </template>
        </Column>
        <Column header="Soumis le">
          <template #body="{ data }">{{ data.submitted_at ? new Date(data.submitted_at).toLocaleDateString('fr-FR') : '—' }}</template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button label="Voir" size="small" severity="secondary" @click="openDetail(data)" />
              <Button
                v-if="data.status === 'draft'"
                label="Soumettre"
                size="small"
                severity="success"
                @click="submitReport(data.id)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Create Dialog -->
      <Dialog
        v-model:visible="showCreateDialog"
        header="Nouvelle note de frais"
        :style="{ width: '600px' }"
        modal
      >
        <div class="flex flex-col gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Titre</label>
            <InputText v-model="createForm.title" class="w-full" placeholder="ex: Déplacement Paris mai 2026" />
          </div>
          <div class="flex justify-end gap-2">
            <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
            <Button label="Créer" @click="createReport" />
          </div>
        </div>
      </Dialog>

      <!-- Detail Dialog -->
      <Dialog
        v-model:visible="showDetailDialog"
        :header="selectedReport?.title ?? 'Détail'"
        :style="{ width: '750px' }"
        modal
      >
        <div v-if="selectedReport" class="flex flex-col gap-5">
          <!-- AI Auto-categorize -->
          <div class="flex justify-end">
            <Button
              label="Catégoriser automatiquement (IA)"
              icon="pi pi-sparkles"
              severity="secondary"
              size="small"
              @click="aiCategorize"
            />
          </div>

          <!-- Lines Table -->
          <DataTable :value="selectedReport.lines ?? []" class="mb-2">
            <Column field="date" header="Date" />
            <Column field="category" header="Catégorie" />
            <Column field="description" header="Description" />
            <Column header="Montant">
              <template #body="{ data }">{{ Number(data.amount).toFixed(2) }} {{ data.currency }}</template>
            </Column>
            <Column header="Km">
              <template #body="{ data }">{{ data.km ?? '—' }}</template>
            </Column>
            <Column header="Reçu">
              <template #body="{ data }">
                <span v-if="data.receipt_url">
                  <a :href="data.receipt_url" target="_blank" class="text-blue-500 underline">Voir</a>
                </span>
                <span v-else class="text-surface-400">—</span>
              </template>
            </Column>
          </DataTable>

          <!-- Add Line Section -->
          <div class="border-t pt-4">
            <h3 class="font-semibold mb-3">Ajouter une ligne</h3>
            <div class="grid grid-cols-2 gap-3 mb-2">
              <div>
                <label class="block text-sm mb-1">Date</label>
                <InputText v-model="lineForm.date" type="date" class="w-full" />
              </div>
              <div>
                <label class="block text-sm mb-1">Catégorie</label>
                <Dropdown
                  v-model="lineForm.category"
                  :options="categories"
                  class="w-full"
                />
              </div>
              <div>
                <label class="block text-sm mb-1">Description</label>
                <InputText v-model="lineForm.description" class="w-full" />
              </div>
              <div>
                <label class="block text-sm mb-1">Montant (€)</label>
                <InputNumber v-model="lineForm.amount" :min="0.01" :step="0.01" class="w-full" />
              </div>
              <div>
                <label class="block text-sm mb-1">Reçu (URL)</label>
                <InputText v-model="lineForm.receipt_url" class="w-full" placeholder="https://..." />
              </div>
            </div>
            <Button label="Ajouter ligne" size="small" @click="addLine" />
          </div>

          <!-- Mileage Section -->
          <div class="border-t pt-4">
            <h3 class="font-semibold mb-3">Section Kilométrage</h3>
            <div class="flex gap-3 items-end">
              <div>
                <label class="block text-sm mb-1">Km</label>
                <InputNumber v-model="mileageForm.km" :min="0.1" :step="0.1" style="width: 120px" />
              </div>
              <div>
                <label class="block text-sm mb-1">Taux (€/km)</label>
                <InputNumber v-model="mileageForm.rate" :min="0.01" :step="0.01" :maxFractionDigits="2" style="width: 120px" />
              </div>
              <div class="pb-1">
                <span class="text-sm text-surface-600">
                  Total : {{ ((mileageForm.km ?? 0) * (mileageForm.rate ?? 0)).toFixed(2) }} €
                </span>
              </div>
              <Button label="Ajouter kilométrage" size="small" @click="addMileage" />
            </div>
          </div>

          <div class="flex justify-between items-center border-t pt-4">
            <span class="font-bold text-lg">Total : {{ Number(selectedReport.total).toFixed(2) }} €</span>
            <Button
              v-if="selectedReport.status === 'draft'"
              label="Soumettre"
              severity="success"
              @click="submitReport(selectedReport.id)"
            />
          </div>
        </div>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown, InputNumber } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface ExpenseLine {
  id: number
  date: string
  category: string
  description: string | null
  amount: string
  currency: string
  receipt_url: string | null
  km: string | null
}

interface ExpenseReport {
  id: number
  title: string
  status: string
  total: string
  submitted_at: string | null
  lines?: ExpenseLine[]
}

const reports = ref<ExpenseReport[]>([])
const loading = ref(false)
const showCreateDialog = ref(false)
const showDetailDialog = ref(false)
const selectedReport = ref<ExpenseReport | null>(null)

const createForm = ref({ title: '' })
const lineForm = ref({ date: new Date().toISOString().slice(0, 10), category: 'Repas', description: '', amount: null as number | null, receipt_url: '' })
const mileageForm = ref({ km: null as number | null, rate: 0.28 })

const categories = ['Transport', 'Repas', 'Hébergement', 'Fournitures', 'Énergie', 'Assurance', 'Autres charges']

function statusSeverity(status: string): string {
  const map: Record<string, string> = { draft: 'secondary', submitted: 'info', approved: 'success', rejected: 'danger', paid: 'success' }
  return map[status] ?? 'secondary'
}

async function loadReports() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/accounting/expenses')
    reports.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

async function createReport() {
  await axios.post('/api/v1/accounting/expenses', createForm.value)
  showCreateDialog.value = false
  createForm.value = { title: '' }
  await loadReports()
}

async function openDetail(report: ExpenseReport) {
  const res = await axios.get(`/api/v1/accounting/expenses/${report.id}`)
  selectedReport.value = res.data
  showDetailDialog.value = true
}

async function addLine() {
  if (!selectedReport.value) return
  await axios.post(`/api/v1/accounting/expenses/${selectedReport.value.id}/lines`, lineForm.value)
  await refreshDetail()
  lineForm.value = { date: new Date().toISOString().slice(0, 10), category: 'Repas', description: '', amount: null, receipt_url: '' }
}

async function addMileage() {
  if (!selectedReport.value) return
  await axios.post(`/api/v1/accounting/expenses/${selectedReport.value.id}/mileage`, mileageForm.value)
  await refreshDetail()
}

async function submitReport(id: number) {
  await axios.post(`/api/v1/accounting/expenses/${id}/submit`)
  showDetailDialog.value = false
  await loadReports()
}

async function refreshDetail() {
  if (!selectedReport.value) return
  const res = await axios.get(`/api/v1/accounting/expenses/${selectedReport.value.id}`)
  selectedReport.value = res.data
}

async function aiCategorize() {
  if (!selectedReport.value?.lines?.length) return
  for (const line of selectedReport.value.lines) {
    if (line.description) {
      const res = await axios.post('/api/v1/accounting/smart-categorize', {
        description: line.description,
        amount: line.amount,
      })
      line.category = res.data.category
    }
  }
}

onMounted(loadReports)
</script>
