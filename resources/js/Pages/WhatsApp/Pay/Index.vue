<template>
  <AppLayout>
    <Head title="WhatsApp Pay" />

    <div class="page-head" style="margin-bottom: 16px">
      <h1 class="wh-page-title">WhatsApp Pay</h1>
      <Button label="Nouvelle demande" icon="pi pi-send" @click="showRequestDialog = true" />
    </div>

    <!-- KPIs -->
    <div class="wh-kpi-grid" style="margin-bottom: 20px">
      <div class="wh-kpi">
        <div class="wh-kpi-label">Total reçu ce mois (€)</div>
        <div class="wh-kpi-num font-display">{{ totalReceived }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">En attente</div>
        <div class="wh-kpi-num font-display">{{ pendingCount }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Taux conversion</div>
        <div class="wh-kpi-num font-display">{{ conversionRate }}%</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Montant moyen (€)</div>
        <div class="wh-kpi-num font-display">{{ avgAmount }}</div>
      </div>
    </div>

    <!-- Payments table -->
    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow p-5 mb-6">
      <h2 class="text-base font-semibold mb-3">Paiements</h2>
      <DataTable :value="payments" :loading="loading" striped-rows paginator :rows="10">
        <Column field="created_at" header="Date">
          <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
        </Column>
        <Column field="conversation_id" header="Conversation" />
        <Column field="amount" header="Montant">
          <template #body="{ data }">
            {{ parseFloat(data.amount).toFixed(2) }} {{ data.currency }}
          </template>
        </Column>
        <Column field="status" header="Statut">
          <template #body="{ data }">
            <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column field="payment_link" header="Lien">
          <template #body="{ data }">
            <a
              v-if="data.payment_link"
              :href="data.payment_link"
              target="_blank"
              class="text-blue-600 underline text-xs"
            >Ouvrir</a>
          </template>
        </Column>
        <Column field="description" header="Description" />
      </DataTable>
    </div>

    <!-- Configuration section -->
    <div class="bg-white dark:bg-surface-800 rounded-xl shadow p-5">
      <h2 class="text-base font-semibold mb-4">Configuration WhatsApp Pay</h2>
      <div v-if="configLoading" class="flex justify-center p-4">
        <i class="pi pi-spin pi-spinner" />
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Merchant ID</label>
          <InputText v-model="config.merchant_id" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Nom affiché</label>
          <InputText v-model="config.display_name" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Devise</label>
          <Dropdown
            v-model="config.currency"
            :options="currencyOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Webhook Secret</label>
          <InputText v-model="config.webhook_secret" class="w-full" type="password" />
        </div>
        <div class="md:col-span-2 flex items-center gap-3">
          <input id="active" v-model="config.active" type="checkbox" class="w-4 h-4" />
          <label for="active" class="text-sm font-medium">Activer WhatsApp Pay</label>
        </div>
        <div class="md:col-span-2">
          <Button label="Sauvegarder la configuration" :loading="savingConfig" @click="saveConfig" />
        </div>
      </div>
    </div>

    <!-- New payment request dialog -->
    <Dialog v-model:visible="showRequestDialog" header="Nouvelle demande de paiement" modal style="width: 480px">
      <div class="flex flex-col gap-4 pt-2">
        <div>
          <label class="block text-sm font-medium mb-1">Conversation ID *</label>
          <InputNumber v-model="requestForm.conversation_id" class="w-full" :use-grouping="false" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Montant (€) *</label>
          <InputNumber
            v-model="requestForm.amount"
            mode="currency"
            currency="EUR"
            locale="fr-FR"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Description *</label>
          <InputText v-model="requestForm.description" class="w-full" placeholder="Facture, produit..." />
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showRequestDialog = false" />
        <Button label="Envoyer" icon="pi pi-send" :loading="sending" @click="sendRequest" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Dropdown, InputNumber } from 'primevue'
import axios from 'axios'

interface Payment {
  id: number
  conversation_id: number | null
  amount: string
  currency: string
  description: string | null
  status: string
  payment_link: string | null
  created_at: string
}

interface Config {
  merchant_id: string
  display_name: string
  currency: string
  webhook_secret: string
  active: boolean
}

const payments = ref<Payment[]>([])
const loading = ref(false)
const showRequestDialog = ref(false)
const sending = ref(false)
const configLoading = ref(false)
const savingConfig = ref(false)

const config = ref<Config>({
  merchant_id: '',
  display_name: '',
  currency: 'EUR',
  webhook_secret: '',
  active: false,
})

const requestForm = ref({
  conversation_id: null as number | null,
  amount: null as number | null,
  description: '',
})

const currencyOptions = [
  { label: 'EUR — Euro', value: 'EUR' },
  { label: 'USD — Dollar', value: 'USD' },
  { label: 'GBP — Livre sterling', value: 'GBP' },
]

const totalReceived = computed(() => {
  const now = new Date()
  return payments.value
    .filter(p => p.status === 'completed' && new Date(p.created_at).getMonth() === now.getMonth())
    .reduce((s, p) => s + parseFloat(p.amount), 0)
    .toFixed(2)
})

const pendingCount = computed(() =>
  payments.value.filter(p => ['pending', 'sent'].includes(p.status)).length,
)

const conversionRate = computed(() => {
  if (!payments.value.length) return '0'
  const completed = payments.value.filter(p => p.status === 'completed').length
  return ((completed / payments.value.length) * 100).toFixed(0)
})

const avgAmount = computed(() => {
  if (!payments.value.length) return '0'
  const total = payments.value.reduce((s, p) => s + parseFloat(p.amount), 0)
  return (total / payments.value.length).toFixed(2)
})

async function loadPayments() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/whatsapp/pay/history')
    payments.value = data
  } finally {
    loading.value = false
  }
}

async function loadConfig() {
  configLoading.value = true
  try {
    const { data } = await axios.get('/api/v1/whatsapp/pay/config')
    if (data) {
      config.value = { ...config.value, ...data }
    }
  } finally {
    configLoading.value = false
  }
}

async function saveConfig() {
  savingConfig.value = true
  try {
    await axios.post('/api/v1/whatsapp/pay/config', config.value)
  } finally {
    savingConfig.value = false
  }
}

async function sendRequest() {
  sending.value = true
  try {
    const { data } = await axios.post('/api/v1/whatsapp/pay/request', {
      conversation_id: requestForm.value.conversation_id,
      amount: requestForm.value.amount,
      description: requestForm.value.description,
    })
    payments.value.unshift(data)
    showRequestDialog.value = false
    requestForm.value = { conversation_id: null, amount: null, description: '' }
  } finally {
    sending.value = false
  }
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    pending: 'En attente',
    sent: 'Envoyé',
    completed: 'Complété',
    failed: 'Échoué',
    refunded: 'Remboursé',
  }
  return map[status] ?? status
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = {
    pending: 'warn',
    sent: 'info',
    completed: 'success',
    failed: 'danger',
    refunded: 'secondary',
  }
  return map[status] ?? 'info'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

onMounted(() => {
  loadPayments()
  loadConfig()
})
</script>
