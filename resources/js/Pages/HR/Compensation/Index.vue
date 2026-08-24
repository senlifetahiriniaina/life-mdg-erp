<template>
  <AppLayout>
    <Head title="Compensation Planning" />

    <!-- Chantier 32.17 (HR deep 14-layer audit): this real, routed page
         never called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Compensation Planning</h1>
        <p class="wh-page-subtitle">Salary bands, simulation and equity analysis</p>
      </div>
      <div class="page-actions">
        <Button label="AI Equity Analysis" icon="pi pi-sparkles" severity="secondary" @click="runEquityAnalysis" />
        <Button label="Add Band" icon="pi pi-plus" class="ml-2" @click="showCreateBand = true" />
      </div>
    </div>

    <!-- Salary Bands Table -->
    <div class="wh-panel" style="margin-bottom:24px">
      <DataTable :value="bands" :loading="loading" stripedRows>
        <Column field="level" header="Level" sortable />
        <Column field="title" header="Title" />
        <Column header="Min" field="min_salary">
          <template #body="{ data }">{{ formatCurrency(data.min_salary, data.currency) }}</template>
        </Column>
        <Column header="Mid" field="mid_salary">
          <template #body="{ data }">{{ formatCurrency(data.mid_salary, data.currency) }}</template>
        </Column>
        <Column header="Max" field="max_salary">
          <template #body="{ data }">{{ formatCurrency(data.max_salary, data.currency) }}</template>
        </Column>
        <Column header="Spread">
          <template #body>
            <!-- min/mid/max bar visualization -->
            <div style="position:relative;height:12px;background:var(--surface-d);border-radius:6px;min-width:140px">
              <div
                :style="`
                  position:absolute;
                  left:0;width:33%;
                  height:100%;background:#22c55e;border-radius:6px 0 0 6px;
                `"
              />
              <div
                :style="`
                  position:absolute;
                  left:33%;width:34%;
                  height:100%;background:#f59e0b;
                `"
              />
              <div
                :style="`
                  position:absolute;
                  left:67%;width:33%;
                  height:100%;background:#ef4444;border-radius:0 6px 6px 0;
                `"
              />
            </div>
          </template>
        </Column>
        <Column field="currency" header="Currency" />
        <Column header="Simulate">
          <template #body="{ data }">
            <Button label="Simulate" size="small" severity="secondary" @click="openSimulation(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Create Band Dialog -->
    <Dialog v-model:visible="showCreateBand" header="New Salary Band" modal style="width:480px">
      <div class="flex flex-col gap-3 mt-2">
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Title</label>
            <InputText v-model="bandForm.title" class="w-full" />
          </div>
          <div style="width:90px">
            <label class="block mb-1 text-sm font-medium">Level</label>
            <InputText v-model="bandForm.level" class="w-full" placeholder="L1" />
          </div>
        </div>
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Min</label>
            <InputNumber v-model="bandForm.min_salary" mode="decimal" :min-fraction-digits="2" class="w-full" />
          </div>
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Mid</label>
            <InputNumber v-model="bandForm.mid_salary" mode="decimal" :min-fraction-digits="2" class="w-full" />
          </div>
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Max</label>
            <InputNumber v-model="bandForm.max_salary" mode="decimal" :min-fraction-digits="2" class="w-full" />
          </div>
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Currency</label>
          <Dropdown v-model="bandForm.currency" :options="currencies" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showCreateBand = false" />
        <Button label="Save" @click="createBand" />
      </template>
    </Dialog>

    <!-- Simulation Dialog -->
    <Dialog v-model:visible="showSimulation" :header="`Simulate Raise — ${selectedBand?.title}`" modal style="width:500px">
      <div v-if="selectedBand" class="mt-2">
        <p class="text-sm text-gray-500 dark:text-surface-400 mb-4">Move the slider to simulate a salary increase percentage.</p>
        <div class="mb-4">
          <label class="block mb-2 text-sm font-medium">Raise: {{ raisePct }}%</label>
          <Slider v-model="raisePct" :min="0" :max="50" :step="0.5" class="w-full" @change="simulateRaise" />
        </div>
        <div v-if="simulatedBand" class="grid grid-cols-3 gap-3 mt-4">
          <div class="wh-panel" style="text-align:center;background:var(--surface-b)">
            <p class="text-xs text-gray-500 dark:text-surface-400">Min</p>
            <p class="font-bold">{{ formatCurrency(simulatedBand.min_salary, selectedBand.currency) }}</p>
            <p class="text-xs text-green-600">+{{ raisePct }}%</p>
          </div>
          <div class="wh-panel" style="text-align:center;background:var(--surface-b)">
            <p class="text-xs text-gray-500 dark:text-surface-400">Mid</p>
            <p class="font-bold">{{ formatCurrency(simulatedBand.mid_salary, selectedBand.currency) }}</p>
            <p class="text-xs text-green-600">+{{ raisePct }}%</p>
          </div>
          <div class="wh-panel" style="text-align:center;background:var(--surface-b)">
            <p class="text-xs text-gray-500 dark:text-surface-400">Max</p>
            <p class="font-bold">{{ formatCurrency(simulatedBand.max_salary, selectedBand.currency) }}</p>
            <p class="text-xs text-green-600">+{{ raisePct }}%</p>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Close" severity="secondary" @click="showSimulation = false" />
      </template>
    </Dialog>

    <!-- Equity Analysis Dialog -->
    <Dialog v-model:visible="showEquityDialog" header="AI Salary Equity Analysis" modal style="width:600px">
      <div v-if="loadingAi" class="text-center py-6">
        <i class="pi pi-spin pi-spinner" style="font-size:2rem" />
        <p class="mt-2 text-gray-500 dark:text-surface-400">Analysing salary equity...</p>
      </div>
      <div v-else-if="equityAnalysis" class="whitespace-pre-line text-sm mt-2">{{ equityAnalysis }}</div>
      <template #footer>
        <Button label="Close" severity="secondary" @click="showEquityDialog = false" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Select as Dropdown, InputNumber, Slider } from 'primevue'
import axios from 'axios'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

// Chantier 32.17 (HR deep 14-layer audit): see the AIAssistantPanel comment
// in the template — this page never called useAiAssistant() at all before.
const showAiPanel = ref(true)
const { guidance } = useAiAssistant('HR', 'view_compensation')

const bands           = ref<any[]>([])
const loading         = ref(false)
const showCreateBand  = ref(false)
const showSimulation  = ref(false)
const showEquityDialog = ref(false)
const loadingAi       = ref(false)
const equityAnalysis  = ref('')
const selectedBand    = ref<any>(null)
const simulatedBand   = ref<any>(null)
const raisePct        = ref(5)

const currencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD']

const bandForm = ref({
  title: '', level: '', min_salary: 0, mid_salary: 0, max_salary: 0, currency: 'EUR',
})

const formatCurrency = (amount: string | number, currency: string) => {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currency || 'EUR' }).format(Number(amount))
}

const fetchBands = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/hr/salary-bands')
    bands.value = res.data
  } finally {
    loading.value = false
  }
}

const createBand = async () => {
  await axios.post('/api/v1/hr/salary-bands', bandForm.value)
  showCreateBand.value = false
  bandForm.value = { title: '', level: '', min_salary: 0, mid_salary: 0, max_salary: 0, currency: 'EUR' }
  fetchBands()
}

const openSimulation = (band: any) => {
  selectedBand.value = band
  simulatedBand.value = null
  raisePct.value = 5
  showSimulation.value = true
  simulateRaise()
}

const simulateRaise = async () => {
  if (!selectedBand.value) return
  const res = await axios.post(`/api/v1/hr/salary-bands/${selectedBand.value.id}/simulate-raise`, {
    raise_pct: raisePct.value,
  })
  simulatedBand.value = res.data.simulated
}

const runEquityAnalysis = async () => {
  showEquityDialog.value = true
  loadingAi.value = true
  equityAnalysis.value = ''
  try {
    const res = await axios.get('/api/v1/hr/salary-bands/equity')
    equityAnalysis.value = res.data.analysis ?? ''
  } finally {
    loadingAi.value = false
  }
}

onMounted(fetchBands)
</script>
