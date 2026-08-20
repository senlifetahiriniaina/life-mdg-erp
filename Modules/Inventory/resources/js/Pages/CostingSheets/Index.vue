<template>
  <AppLayout>
    <Head title="Fiches de chiffrage" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Fiches de chiffrage
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Nomenclature de coût (matière + accessoires + main-d'œuvre + frais fixes) pour chiffrer un article avant devis client.
          </p>
        </div>
        <Button icon="pi pi-plus" label="Nouvelle fiche" @click="router.visit('/inventory/costing-sheets/create')" />
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex gap-3 items-center">
        <Select
          v-model="statusFilter"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          placeholder="Tous statuts"
          show-clear
          class="w-56"
          @change="load"
        />
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable :value="sheets" :loading="loading" striped-rows class="p-datatable-sm">
          <Column field="reference" header="Référence" />
          <Column field="name" header="Désignation" />
          <Column field="quantity" header="Qté" />
          <Column header="Coût de revient">
            <template #body="{ data }">{{ formatMoney(data.total_cost_price) }} {{ data.base_currency }}</template>
          </Column>
          <Column header="Prix suggéré">
            <template #body="{ data }">{{ formatMoney(data.suggested_selling_price) }} {{ data.base_currency }}</template>
          </Column>
          <Column field="version" header="Version" />
          <Column header="Statut">
            <template #body="{ data }">
              <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button icon="pi pi-pencil" text size="small" @click="router.visit(`/inventory/costing-sheets/${data.id}/edit`)" />
                <Button icon="pi pi-copy" text size="small" title="Dupliquer (nouvelle révision)" @click="duplicate(data)" />
                <Button icon="pi pi-trash" text size="small" severity="danger" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-8 text-surface-400">Aucune fiche de chiffrage pour le moment.</div>
          </template>
        </DataTable>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'

const sheets = ref([])
const loading = ref(false)
const statusFilter = ref(null)
const confirmDialog = useConfirm()
const toast = useToast()

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Chiffré', value: 'quoted' },
  { label: 'Approuvé', value: 'approved' },
  { label: 'Archivé', value: 'archived' },
]

function statusLabel(status) {
  return statusOptions.find((o) => o.value === status)?.label ?? status
}
function statusSeverity(status) {
  return { draft: 'secondary', quoted: 'info', approved: 'success', archived: 'contrast' }[status] ?? 'secondary'
}
function formatMoney(value) {
  return Number(value ?? 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 })
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/inventory/costing-sheets', {
      params: { status: statusFilter.value || undefined, per_page: 50 },
    })
    sheets.value = data.data || []
  } finally {
    loading.value = false
  }
}

async function duplicate(sheet) {
  try {
    const { data } = await axios.post(`/api/v1/inventory/costing-sheets/${sheet.id}/duplicate`)
    toast.add({ severity: 'success', summary: 'Nouvelle révision créée', life: 3000 })
    router.visit(`/inventory/costing-sheets/${data.data.id}/edit`)
  } catch {
    toast.add({ severity: 'error', summary: 'Échec de la duplication', life: 3000 })
  }
}

function confirmDelete(sheet) {
  confirmDialog.require({
    message: `Supprimer la fiche "${sheet.name}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      await axios.delete(`/api/v1/inventory/costing-sheets/${sheet.id}`)
      await load()
    },
  })
}

onMounted(load)
</script>
