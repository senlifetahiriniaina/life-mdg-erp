<template>
  <AppLayout>
    <Head title="Benchmark de prix fournisseurs" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
          Benchmark de prix fournisseurs
        </h1>
        <p class="text-surface-500 text-sm mt-1">
          Comparez le prix catalogue interne aux prix observés chez des fournisseurs de sourcing en Chine/Europe
          (XM Textiles, Klopman, Carrington Textiles, Marina Textil, TenCate Protective Fabrics) — la structure de
          coûts se précise à mesure que vous ajoutez des observations.
        </p>
      </div>

      <!-- Product picker -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200 block mb-1">Produit</label>
        <Select
          v-model="selectedProductId"
          :options="products"
          option-label="name"
          option-value="id"
          placeholder="Sélectionner un produit du catalogue"
          filter
          class="w-full md:w-96"
          @change="onProductChange"
        />
      </div>

      <template v-if="selectedProductId">
        <!-- Comparison summary -->
        <div v-if="comparison" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-sm text-surface-500">Coût catalogue interne</p>
              <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">
                {{ formatMoney(comparison.internal_cost) }} {{ comparison.internal_currency }}
              </p>
            </div>
            <div class="text-sm text-surface-500">
              {{ comparison.observation_count }} observation(s) enregistrée(s)
            </div>
          </div>

          <div v-if="comparison.by_source.length === 0" class="text-center py-8 text-surface-400">
            Aucune observation de prix externe pour ce produit — ajoutez-en une ci-dessous.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="row in comparison.by_source"
              :key="row.source"
              class="border border-surface-200 dark:border-surface-700 rounded-lg p-4"
            >
              <div class="flex items-center justify-between mb-2">
                <span class="font-semibold">{{ row.source_label }}</span>
                <Tag
                  v-if="row.variance_pct !== null"
                  :value="`${row.variance_pct > 0 ? '+' : ''}${row.variance_pct}%`"
                  :severity="varianceSeverity(row.variance_pct)"
                />
              </div>
              <p class="text-sm text-surface-500">{{ row.observation_count }} observation(s)</p>
              <p class="text-lg font-medium mt-1">
                {{ formatMoney(row.avg_price) }} {{ comparison.internal_currency }}
                <span class="text-xs text-surface-400 font-normal">moyenne</span>
              </p>
              <p class="text-xs text-surface-400">
                min {{ formatMoney(row.min_price) }} — max {{ formatMoney(row.max_price) }}
              </p>
            </div>
          </div>

          <!-- Monthly trend -->
          <div v-if="comparison.monthly_trend.length > 1" class="mt-6">
            <p class="text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">Évolution mensuelle (moyenne tous fournisseurs confondus)</p>
            <svg :viewBox="`0 0 ${trendWidth} 80`" class="w-full h-20">
              <polyline :points="trendPoints" fill="none" stroke="var(--primary-color, #6366f1)" stroke-width="2" />
              <circle v-for="(pt, i) in trendCoords" :key="i" :cx="pt.x" :cy="pt.y" r="3" fill="var(--primary-color, #6366f1)" />
            </svg>
            <div class="flex justify-between text-xs text-surface-400">
              <span>{{ comparison.monthly_trend[0].month }}</span>
              <span>{{ comparison.monthly_trend[comparison.monthly_trend.length - 1].month }}</span>
            </div>
          </div>
        </div>

        <!-- Add observation -->
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <h2 class="font-semibold mb-4">Ajouter une observation de prix</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Fournisseur <span class="text-red-500">*</span></label>
              <Select
                v-model="form.source"
                :options="sourceOptions"
                option-label="label"
                option-value="value"
                class="w-full"
              />
            </div>
            <div v-if="form.source === 'autre'" class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Nom du fournisseur</label>
              <InputText v-model="form.source_name_other" class="w-full" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Prix unitaire <span class="text-red-500">*</span></label>
              <InputNumber v-model="form.unit_price" :min="0" :min-fraction-digits="2" :max-fraction-digits="4" class="w-full" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Devise <span class="text-red-500">*</span></label>
              <Select
                v-model="form.currency"
                :options="currencies"
                option-label="code"
                option-value="code"
                class="w-full"
              />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Unité</label>
              <InputText v-model="form.unit" placeholder="m, kg, pièce…" class="w-full" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Date d'observation <span class="text-red-500">*</span></label>
              <DatePicker v-model="form.observed_at" date-format="yy-mm-dd" class="w-full" />
            </div>
            <div class="flex flex-col gap-1 md:col-span-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">URL de la source</label>
              <InputText v-model="form.source_url" placeholder="https://…" class="w-full" />
            </div>
            <div class="flex flex-col gap-1 md:col-span-3">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Notes</label>
              <Textarea v-model="form.notes" rows="2" class="w-full" />
            </div>
          </div>

          <div v-if="formError" class="mt-3 rounded-md bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            {{ formError }}
          </div>

          <div class="flex justify-end mt-4">
            <Button label="Enregistrer l'observation" icon="pi pi-plus" :loading="submitting" @click="submitObservation" />
          </div>
        </div>

        <!-- History -->
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
          <DataTable :value="history" class="p-datatable-sm">
            <Column field="observed_at" header="Date" />
            <Column header="Fournisseur">
              <template #body="{ data }">
                {{ data.source === 'autre' ? data.source_name_other : sourceLabel(data.source) }}
              </template>
            </Column>
            <Column header="Prix">
              <template #body="{ data }">
                {{ formatMoney(data.unit_price) }} {{ data.currency }} <span v-if="data.unit" class="text-surface-400">/ {{ data.unit }}</span>
              </template>
            </Column>
            <Column field="notes" header="Notes" />
            <Column style="width: 4rem">
              <template #body="{ data }">
                <Button icon="pi pi-trash" text severity="danger" @click="deleteObservation(data.id)" />
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-8 text-surface-400">Aucune observation pour ce produit.</div>
            </template>
          </DataTable>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AppLayout from '@/Layouts/AppLayout.vue'

interface ProductOption {
  id: number
  name: string
  sku: string
}

interface CompareRow {
  source: string
  source_label: string
  observation_count: number
  avg_price: number
  min_price: number
  max_price: number
  variance_pct: number | null
}

interface Comparison {
  internal_cost: number
  internal_currency: string
  by_source: CompareRow[]
  monthly_trend: Array<{ month: string, avg_price: number }>
  observation_count: number
}

const products = ref<ProductOption[]>([])
const selectedProductId = ref<number | null>(null)
const comparison = ref<Comparison | null>(null)
const history = ref<any[]>([])
const sources = ref<Record<string, string>>({})
const currencies = ref<Array<{ code: string }>>([])

const submitting = ref(false)
const formError = ref<string | null>(null)

const form = ref({
  source: 'xm_textiles',
  source_name_other: '',
  source_url: '',
  unit_price: null as number | null,
  currency: 'USD',
  unit: '',
  quantity_reference: null as number | null,
  observed_at: new Date(),
  notes: '',
})

const sourceOptions = computed(() =>
  Object.entries(sources.value).map(([value, label]) => ({ value, label }))
)

const sourceLabel = (source: string) => sources.value[source] ?? source

const formatMoney = (value: number) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(value)

const varianceSeverity = (pct: number) => {
  const abs = Math.abs(pct)
  if (abs <= 5) return 'success'
  if (abs <= 15) return 'warn'
  return 'danger'
}

const trendWidth = 400
const trendCoords = computed(() => {
  const trend = comparison.value?.monthly_trend ?? []
  if (trend.length === 0) return []
  const prices = trend.map(t => t.avg_price)
  const min = Math.min(...prices)
  const max = Math.max(...prices)
  const range = max - min || 1
  const step = trendWidth / Math.max(trend.length - 1, 1)

  return trend.map((t, i) => ({
    x: i * step,
    y: 70 - ((t.avg_price - min) / range) * 60,
  }))
})
const trendPoints = computed(() => trendCoords.value.map(p => `${p.x},${p.y}`).join(' '))

const fetchProducts = async () => {
  const { data } = await axios.get('/api/v1/inventory/products?per_page=200')
  products.value = data.data ?? []
}

const fetchCurrencies = async () => {
  const { data } = await axios.get('/api/v1/shared/currencies')
  currencies.value = data.data ?? []
}

const fetchComparisonAndHistory = async () => {
  if (!selectedProductId.value) return

  const [compareRes, historyRes] = await Promise.all([
    axios.get(`/api/v1/inventory/sourcing-benchmarks/compare/${selectedProductId.value}`),
    axios.get(`/api/v1/inventory/sourcing-benchmarks?product_id=${selectedProductId.value}`),
  ])

  comparison.value = compareRes.data.data
  history.value = historyRes.data.data
  sources.value = historyRes.data.sources ?? {}
}

const onProductChange = () => {
  fetchComparisonAndHistory()
}

const submitObservation = async () => {
  formError.value = null
  submitting.value = true

  try {
    await axios.post('/api/v1/inventory/sourcing-benchmarks', {
      product_id: selectedProductId.value,
      source: form.value.source,
      source_name_other: form.value.source_name_other || null,
      source_url: form.value.source_url || null,
      unit_price: form.value.unit_price,
      currency: form.value.currency,
      unit: form.value.unit || null,
      quantity_reference: form.value.quantity_reference,
      observed_at: form.value.observed_at instanceof Date
        ? form.value.observed_at.toISOString().split('T')[0]
        : form.value.observed_at,
      notes: form.value.notes || null,
    })

    form.value.unit_price = null
    form.value.notes = ''
    await fetchComparisonAndHistory()
  } catch (e) {
    formError.value = e.response?.data?.message ?? "Impossible d'enregistrer cette observation."
  } finally {
    submitting.value = false
  }
}

const deleteObservation = async (id: number) => {
  await axios.delete(`/api/v1/inventory/sourcing-benchmarks/${id}`)
  await fetchComparisonAndHistory()
}

onMounted(async () => {
  await fetchProducts()
  await fetchCurrencies()

  const params = new URLSearchParams(window.location.search)
  const preselected = params.get('product_id')
  if (preselected) {
    selectedProductId.value = Number(preselected)
    await fetchComparisonAndHistory()
  }
})
</script>
