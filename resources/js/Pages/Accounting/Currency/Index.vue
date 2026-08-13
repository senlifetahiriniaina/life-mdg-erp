<template>
  <AppLayout title="Taux de change & Gains/Pertes">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Taux de change</h1>
        <div class="flex gap-2">
          <Button label="Actualiser les taux" icon="pi pi-refresh" severity="secondary" @click="fetchRates" :loading="fetching" />
          <Button label="Ajouter taux" icon="pi pi-plus" @click="showCreateDialog = true" />
        </div>
      </div>

      <!-- Exchange Rates Table -->
      <DataTable :value="rates" :loading="loading" paginator :rows="20" class="mb-8">
        <Column header="Paire">
          <template #body="{ data }">
            <span class="font-mono font-bold">{{ data.base_currency }} / {{ data.target_currency }}</span>
          </template>
        </Column>
        <Column field="rate" header="Taux" />
        <Column header="Source">
          <template #body="{ data }">
            <Tag :value="data.source" :severity="data.source === 'api' ? 'success' : 'secondary'" />
          </template>
        </Column>
        <Column field="date" header="Date" />
        <Column header="Actions">
          <template #body="{ data }">
            <Button icon="pi pi-trash" size="small" severity="danger" text @click="deleteRate(data.id)" />
          </template>
        </Column>
      </DataTable>

      <!-- Gains / Pertes de change -->
      <div class="flex items-center gap-4 mb-4">
        <h2 class="text-lg font-semibold">Gains / Pertes de change</h2>
        <div class="flex gap-4 text-sm">
          <span class="text-green-600 font-medium">Réalisés : {{ gainLossSummary.total_realized }} €</span>
          <span class="text-orange-600 font-medium">Non réalisés : {{ gainLossSummary.total_unrealized }} €</span>
        </div>
      </div>
      <DataTable :value="gainLossRecords" :loading="loadingGainLoss" paginator :rows="10">
        <Column field="id" header="#" style="width: 60px" />
        <Column header="Facture">
          <template #body="{ data }">{{ data.invoice_id ?? '—' }}</template>
        </Column>
        <Column header="Montant original">
          <template #body="{ data }">{{ data.original_amount }} {{ data.original_currency }}</template>
        </Column>
        <Column header="Montant converti">
          <template #body="{ data }">{{ data.converted_amount }} {{ data.base_currency }}</template>
        </Column>
        <Column field="gain_loss" header="Gain / Perte (€)" />
        <Column header="Réalisé">
          <template #body="{ data }">
            <Tag :value="data.realized ? 'Oui' : 'Non'" :severity="data.realized ? 'success' : 'warning'" />
          </template>
        </Column>
      </DataTable>

      <!-- Create Rate Dialog -->
      <Dialog
        v-model:visible="showCreateDialog"
        header="Ajouter un taux de change"
        :style="{ width: '450px' }"
        modal
      >
        <div class="flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium mb-1">Devise de base</label>
              <InputText v-model="form.base_currency" maxlength="3" class="w-full" placeholder="EUR" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Devise cible</label>
              <InputText v-model="form.target_currency" maxlength="3" class="w-full" placeholder="USD" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Taux</label>
            <InputNumber v-model="form.rate" :min="0.000001" :step="0.0001" :maxFractionDigits="6" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Date</label>
            <InputText v-model="form.date" type="date" class="w-full" />
          </div>
          <div class="flex justify-end gap-2">
            <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
            <Button label="Enregistrer" @click="createRate" />
          </div>
        </div>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, InputNumber } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface ExchangeRate {
  id: number
  base_currency: string
  target_currency: string
  rate: string
  source: string
  date: string
}

interface GainLossRecord {
  id: number
  invoice_id: number | null
  original_amount: string
  original_currency: string
  converted_amount: string
  base_currency: string
  gain_loss: string
  realized: boolean
}

const rates = ref<ExchangeRate[]>([])
const gainLossRecords = ref<GainLossRecord[]>([])
const gainLossSummary = ref({ total_realized: '0.00', total_unrealized: '0.00' })
const loading = ref(false)
const loadingGainLoss = ref(false)
const fetching = ref(false)
const showCreateDialog = ref(false)
const form = ref({ base_currency: 'EUR', target_currency: '', rate: null as number | null, date: new Date().toISOString().slice(0, 10) })

async function loadRates() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/accounting/exchange-rates')
    rates.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

async function loadGainLoss() {
  loadingGainLoss.value = true
  try {
    const res = await axios.get('/api/v1/accounting/exchange-rates/gain-losses')
    gainLossRecords.value = res.data.records?.data ?? []
    gainLossSummary.value = { total_realized: res.data.total_realized, total_unrealized: res.data.total_unrealized }
  } finally {
    loadingGainLoss.value = false
  }
}

async function fetchRates() {
  fetching.value = true
  try {
    await axios.post('/api/v1/accounting/exchange-rates/fetch')
    await loadRates()
  } finally {
    fetching.value = false
  }
}

async function deleteRate(id: number) {
  await axios.delete(`/api/v1/accounting/exchange-rates/${id}`)
  await loadRates()
}

async function createRate() {
  await axios.post('/api/v1/accounting/exchange-rates', form.value)
  showCreateDialog.value = false
  form.value = { base_currency: 'EUR', target_currency: '', rate: null, date: new Date().toISOString().slice(0, 10) }
  await loadRates()
}

onMounted(async () => {
  await Promise.all([loadRates(), loadGainLoss()])
})
</script>
