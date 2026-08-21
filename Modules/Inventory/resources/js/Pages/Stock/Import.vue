<template>
  <AppLayout>
    <Head title="Import de mouvements de stock" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Import de mouvements de stock
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Importez des entrées/sorties de stock depuis un fichier CSV/Excel — les produits inconnus sont ajoutés automatiquement au catalogue.
          </p>
        </div>
        <Button
          icon="pi pi-arrow-left"
          label="Retour aux mouvements"
          outlined
          @click="router.visit('/stock/movements')"
        />
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <!-- Step 1: warehouse + file -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Entrepôt <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="warehouseId"
              :options="warehouses"
              option-label="name"
              option-value="id"
              placeholder="Sélectionner un entrepôt"
              class="w-full"
              :disabled="rows.length > 0"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Fichier (CSV, XLSX) <span class="text-red-500">*</span>
            </label>
            <input
              ref="fileInput"
              type="file"
              accept=".csv,.txt,.xlsx,.xls"
              class="text-sm text-surface-700 dark:text-surface-200 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-primary-50 file:text-primary-700"
              @change="onFileSelected"
            >
          </div>
        </div>

        <div v-if="previewError" class="rounded-md bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
          {{ previewError }}
        </div>

        <div class="flex justify-end">
          <Button
            label="Analyser le fichier"
            icon="pi pi-search"
            :loading="loadingPreview"
            :disabled="!warehouseId || !selectedFile || rows.length > 0"
            @click="loadPreview"
          />
        </div>
      </div>

      <!-- Step 2: preview table -->
      <div v-if="rows.length > 0" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="p-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
          <div class="text-sm text-surface-600 dark:text-surface-300">
            {{ rows.length }} ligne(s) détectée(s) —
            <span class="text-green-600 font-medium">{{ existingCount }} produit(s) existant(s)</span>,
            <span class="text-amber-600 font-medium">{{ newCount }} nouveau(x) produit(s)</span> à créer
          </div>
        </div>

        <DataTable :value="rows" class="p-datatable-sm">
          <Column field="name" header="Produit">
            <template #body="{ data }">
              <div class="font-medium">{{ data.name }}</div>
              <div class="text-xs text-surface-400">{{ data.sku || '—' }}</div>
            </template>
          </Column>
          <Column field="type" header="Type" style="width: 8rem">
            <template #body="{ data, index }">
              <Select
                v-model="data.type"
                :options="typeOptions"
                option-label="label"
                option-value="value"
                class="w-full"
                @change="rows[index].type = data.type"
              />
            </template>
          </Column>
          <Column field="quantity" header="Quantité" style="width: 8rem">
            <template #body="{ data }">
              <InputNumber v-model="data.quantity" :min="0.01" :min-fraction-digits="0" :max-fraction-digits="2" class="w-full" />
            </template>
          </Column>
          <Column field="unit_cost" header="Coût unitaire" style="width: 9rem">
            <template #body="{ data }">
              <InputNumber v-model="data.unit_cost" :min="0" :min-fraction-digits="0" :max-fraction-digits="2" class="w-full" placeholder="—" />
            </template>
          </Column>
          <Column field="product_exists" header="Catalogue" style="width: 10rem">
            <template #body="{ data }">
              <Tag
                :value="data.product_exists ? 'Produit existant' : 'Nouveau produit'"
                :severity="data.product_exists ? 'success' : 'warn'"
              />
            </template>
          </Column>
        </DataTable>

        <div v-if="commitError" class="mx-4 mb-4 rounded-md bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
          {{ commitError }}
        </div>

        <div class="flex justify-end gap-2 p-4 border-t border-surface-200 dark:border-surface-700">
          <Button label="Annuler" outlined @click="resetImport" />
          <Button
            label="Valider l'import"
            icon="pi pi-check"
            :loading="committing"
            @click="commitImport"
          />
        </div>
      </div>

      <!-- Step 3: result summary -->
      <div v-if="result" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-3">
        <div class="flex items-center gap-2 text-green-700 dark:text-green-400">
          <i class="pi pi-check-circle text-xl" />
          <span class="font-semibold">{{ result.movements }} mouvement(s) de stock enregistré(s).</span>
        </div>
        <div v-if="result.products_created.length > 0">
          <p class="text-sm text-surface-600 dark:text-surface-300 mb-2">
            {{ result.products_created.length }} produit(s) créé(s) automatiquement dans le catalogue :
          </p>
          <ul class="text-sm list-disc list-inside space-y-1">
            <li v-for="p in result.products_created" :key="p.id">
              {{ p.name }} <span class="text-surface-400">({{ p.sku }})</span>
            </li>
          </ul>
        </div>
        <div class="flex gap-2 pt-2">
          <Button label="Nouvel import" outlined @click="resetImport" />
          <Button label="Voir les mouvements" @click="router.visit('/stock/movements')" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import InputNumber from 'primevue/inputnumber'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Inventory', 'import_stock')

interface Warehouse {
  id: number
  name: string
  code: string
}

interface PreviewRow {
  sku: string | null
  name: string
  quantity: number
  type: 'in' | 'out'
  unit_cost: number | null
  product_exists: boolean
  matched_product_id: number | null
}

interface CommitResult {
  movements: number
  products_created: Array<{ id: number, sku: string, name: string }>
}

const warehouses = ref<Warehouse[]>([])
const warehouseId = ref<number | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)

const rows = ref<PreviewRow[]>([])
const loadingPreview = ref(false)
const previewError = ref<string | null>(null)

const committing = ref(false)
const commitError = ref<string | null>(null)
const result = ref<CommitResult | null>(null)

const typeOptions = [
  { label: 'Entrée', value: 'in' },
  { label: 'Sortie', value: 'out' },
]

const existingCount = computed(() => rows.value.filter(r => r.product_exists).length)
const newCount = computed(() => rows.value.filter(r => !r.product_exists).length)

const onFileSelected = (event: Event) => {
  const target = event.target as HTMLInputElement
  selectedFile.value = target.files?.[0] ?? null
}

const loadPreview = async () => {
  if (!selectedFile.value) return

  loadingPreview.value = true
  previewError.value = null

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    const { data } = await axios.post('/api/v1/inventory/stock-imports/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    rows.value = data.rows
  } catch (e) {
    previewError.value = e.response?.data?.message ?? "Impossible d'analyser ce fichier."
  } finally {
    loadingPreview.value = false
  }
}

const commitImport = async () => {
  if (!warehouseId.value) return

  committing.value = true
  commitError.value = null

  try {
    const { data } = await axios.post('/api/v1/inventory/stock-imports/commit', {
      warehouse_id: warehouseId.value,
      rows: rows.value.map(r => ({
        sku: r.sku,
        name: r.name,
        quantity: r.quantity,
        type: r.type,
        unit_cost: r.unit_cost,
      })),
    })

    result.value = data.data
    rows.value = []
  } catch (e) {
    commitError.value = e.response?.data?.message ?? "Impossible de valider l'import."
  } finally {
    committing.value = false
  }
}

const resetImport = () => {
  rows.value = []
  result.value = null
  previewError.value = null
  commitError.value = null
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const fetchWarehouses = async () => {
  const { data } = await axios.get('/api/v1/inventory/warehouses?per_page=100')
  warehouses.value = data.data ?? []
}

onMounted(() => {
  fetchWarehouses()
})
</script>
