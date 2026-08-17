<template>
  <AppLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Prévision de Demande (IA)</h1>
        <p class="text-surface-500 text-sm mt-1">Générez et consultez les prévisions de demande par produit</p>
      </div>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Générer une prévision</div></template>
      <template #content>
        <form class="flex flex-wrap gap-3 items-end" @submit.prevent="generateForecast">
          <div class="flex flex-col gap-1">
            <label class="text-sm text-surface-600">Produit</label>
            <Select
              v-model="form.product_id"
              :options="products"
              option-label="name"
              option-value="id"
              filter
              placeholder="Sélectionner un produit"
              class="w-64"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm text-surface-600">Méthode</label>
            <Select
              v-model="form.method"
              :options="methodOptions"
              option-label="label"
              option-value="value"
              class="w-56"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm text-surface-600">Horizon (mois)</label>
            <InputNumber v-model="form.months" :min="1" :max="24" class="w-28" />
          </div>
          <Button type="submit" label="Générer" icon="pi pi-refresh" :loading="generating" :disabled="!form.product_id" />
        </form>
        <p v-if="generateError" class="text-red-600 text-sm mt-2">{{ generateError }}</p>
        <p v-if="generateResult" class="text-green-700 text-sm mt-2">{{ generateResult }}</p>
      </template>
    </Card>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Prévisions existantes</div></template>
      <template #content>
        <DataTable :value="forecasts" :loading="loading" stripedRows responsiveLayout="scroll">
          <Column header="Produit">
            <template #body="{ data }">{{ data.product?.name ?? '—' }}</template>
          </Column>
          <Column field="period_start" header="Début période" />
          <Column field="period_end" header="Fin période" />
          <Column field="forecasted_qty" header="Demande prévue" />
          <Column field="actual_qty" header="Demande réelle">
            <template #body="{ data }">{{ data.actual_qty ?? '—' }}</template>
          </Column>
          <Column field="method" header="Méthode" />
          <Column field="confidence" header="Fiabilité %">
            <template #body="{ data }">
              <ProgressBar v-if="data.confidence != null" :value="data.confidence" :style="{ height: '8px' }" />
              <span v-else>—</span>
            </template>
          </Column>
          <Column field="status" header="Statut">
            <template #body="{ data }"><Tag :value="data.status" /></template>
          </Column>
          <template #empty>
            <div class="text-center py-8 text-surface-400">Aucune prévision générée pour le moment.</div>
          </template>
        </DataTable>
      </template>
    </Card>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import ProgressBar from 'primevue/progressbar'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const { guidance } = useAiAssistant('Inventory', 'low_stock_alert')

const products = ref([])
const forecasts = ref([])
const loading = ref(false)
const generating = ref(false)
const generateError = ref(null)
const generateResult = ref(null)

const methodOptions = [
  { label: 'Moyenne mobile', value: 'moving_average' },
  { label: 'Lissage exponentiel', value: 'exponential_smoothing' },
  { label: 'Saisonnier', value: 'seasonal' },
]

const form = reactive({
  product_id: null,
  method: 'moving_average',
  months: 3,
})

const loadProducts = async () => {
  const { data } = await axios.get('/api/v1/inventory/products', { params: { per_page: 100 } })
  products.value = data.data ?? []
}

const loadForecasts = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/inventory/demand-forecasts')
    forecasts.value = data.data ?? []
  } finally {
    loading.value = false
  }
}

const generateForecast = async () => {
  generating.value = true
  generateError.value = null
  generateResult.value = null
  try {
    const { data } = await axios.post('/api/v1/inventory/demand-forecasts/generate', {
      product_id: form.product_id,
      method: form.method,
      months: form.months,
    })
    generateResult.value = `${data.count} prévision(s) générée(s).`
    await loadForecasts()
  } catch (e) {
    generateError.value = e.response?.data?.message ?? 'Impossible de générer la prévision.'
  } finally {
    generating.value = false
  }
}

onMounted(() => {
  loadProducts()
  loadForecasts()
})
</script>
