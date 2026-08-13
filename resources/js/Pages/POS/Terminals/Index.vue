<template>
  <AppLayout>
    <Head title="Terminaux NFC" />

    <!-- KPIs -->
    <div class="wh-kpi-grid" style="margin-bottom: 20px">
      <div class="wh-kpi">
        <div class="wh-kpi-label">Terminaux en ligne</div>
        <div class="wh-kpi-num font-display">{{ onlineCount }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Transactions aujourd'hui</div>
        <div class="wh-kpi-num font-display">{{ todayTransactions }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Montant traité (€)</div>
        <div class="wh-kpi-num font-display">{{ totalAmountFormatted }}</div>
      </div>
    </div>

    <!-- Header -->
    <div class="page-head" style="margin-bottom: 16px">
      <h1 class="wh-page-title">Terminaux de paiement</h1>
      <Button label="Nouveau terminal" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <!-- Terminals grid -->
    <div v-if="loading" class="flex justify-center p-8">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem" />
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="terminal in terminals"
        :key="terminal.id"
        class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow p-5 cursor-pointer border-2 transition-all"
        :class="selectedTerminal?.id === terminal.id ? 'border-primary-500' : 'border-transparent'"
        @click="selectTerminal(terminal)"
      >
        <div class="flex items-start justify-between mb-3">
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-surface-50 dark:text-surface-50">{{ terminal.name }}</h3>
            <p class="text-sm text-gray-500 dark:text-surface-400">{{ terminal.serial_number }}</p>
          </div>
          <Tag
            :value="statusLabel(terminal.status)"
            :severity="statusSeverity(terminal.status)"
          />
        </div>

        <div class="text-sm text-gray-600 dark:text-surface-300 dark:text-surface-300 space-y-1 mb-4">
          <div><span class="font-medium">Type:</span> {{ terminal.type.toUpperCase() }}</div>
          <div v-if="terminal.location"><span class="font-medium">Localisation:</span> {{ terminal.location }}</div>
          <div v-if="terminal.last_ping_at">
            <span class="font-medium">Dernier ping:</span> {{ formatDate(terminal.last_ping_at) }}
          </div>
        </div>

        <Button
          label="Tester connexion"
          icon="pi pi-wifi"
          size="small"
          severity="secondary"
          :loading="pinging[terminal.id]"
          @click.stop="pingTerminal(terminal)"
        />
      </div>
    </div>

    <!-- Transactions panel (right side) -->
    <div v-if="selectedTerminal" class="mt-6 bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Transactions — {{ selectedTerminal.name }}</h2>
        <Button
          icon="pi pi-times"
          text
          rounded
          @click="selectedTerminal = null"
        />
      </div>

      <DataTable :value="selectedTransactions" :loading="txLoading" striped-rows>
        <Column field="id" header="#" style="width: 60px" />
        <Column field="amount" header="Montant">
          <template #body="{ data }">
            {{ parseFloat(data.amount).toFixed(2) }} {{ data.currency }}
          </template>
        </Column>
        <Column field="status" header="Statut">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="txSeverity(data.status)" />
          </template>
        </Column>
        <Column field="card_last4" header="Carte">
          <template #body="{ data }">
            <span v-if="data.card_last4">**** {{ data.card_last4 }}</span>
            <span v-else class="text-gray-400">—</span>
          </template>
        </Column>
        <Column field="created_at" header="Date">
          <template #body="{ data }">
            {{ formatDate(data.created_at) }}
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Create terminal dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau terminal" modal style="width: 480px">
      <div class="flex flex-col gap-4 pt-2">
        <div>
          <label class="block text-sm font-medium mb-1">Nom *</label>
          <InputText v-model="form.name" class="w-full" placeholder="Caisse principale" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Numéro de série *</label>
          <InputText v-model="form.serial_number" class="w-full" placeholder="TPE-0001-ABCD" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Type *</label>
          <Dropdown
            v-model="form.type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Localisation</label>
          <InputText v-model="form.location" class="w-full" placeholder="Caisse 1, entrée..." />
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
        <Button label="Créer" :loading="creating" @click="createTerminal" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Dropdown } from 'primevue'
import axios from 'axios'

interface Terminal {
  id: number
  name: string
  serial_number: string
  type: string
  location: string | null
  status: string
  last_ping_at: string | null
  transactions_count?: number
}

interface Transaction {
  id: number
  amount: string
  currency: string
  status: string
  card_last4: string | null
  card_brand: string | null
  created_at: string
}

const terminals = ref<Terminal[]>([])
const loading = ref(false)
const showCreateDialog = ref(false)
const creating = ref(false)
const pinging = ref<Record<number, boolean>>({})
const selectedTerminal = ref<Terminal | null>(null)
const selectedTransactions = ref<Transaction[]>([])
const txLoading = ref(false)

const form = ref({
  name: '',
  serial_number: '',
  type: 'nfc_card',
  location: '',
})

const typeOptions = [
  { label: 'NFC + Carte', value: 'nfc_card' },
  { label: 'NFC uniquement', value: 'nfc' },
  { label: 'Carte uniquement', value: 'card' },
]

const onlineCount = computed(() => terminals.value.filter(t => t.status === 'online').length)
const todayTransactions = computed(() =>
  terminals.value.reduce((s, t) => s + (t.transactions_count ?? 0), 0),
)
const totalAmountFormatted = computed(() => '—')

async function loadTerminals() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/pos/terminals')
    terminals.value = data
  } finally {
    loading.value = false
  }
}

async function selectTerminal(terminal: Terminal) {
  selectedTerminal.value = terminal
  txLoading.value = true
  try {
    const { data } = await axios.get(`/api/v1/pos/terminals/${terminal.id}/transactions`)
    selectedTransactions.value = data.data ?? data
  } finally {
    txLoading.value = false
  }
}

async function pingTerminal(terminal: Terminal) {
  pinging.value[terminal.id] = true
  try {
    const { data } = await axios.post(`/api/v1/pos/terminals/${terminal.id}/ping`)
    const idx = terminals.value.findIndex(t => t.id === terminal.id)
    if (idx !== -1) {
      terminals.value[idx] = { ...terminals.value[idx], status: data.status }
    }
  } finally {
    pinging.value[terminal.id] = false
  }
}

async function createTerminal() {
  creating.value = true
  try {
    const { data } = await axios.post('/api/v1/pos/terminals', {
      name: form.value.name,
      serial_number: form.value.serial_number,
      type: form.value.type,
      location: form.value.location || null,
    })
    terminals.value.unshift(data)
    showCreateDialog.value = false
    form.value = { name: '', serial_number: '', type: 'nfc_card', location: '' }
  } finally {
    creating.value = false
  }
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    online: 'En ligne',
    offline: 'Hors ligne',
    error: 'Erreur',
    pairing: 'Appairage',
  }
  return map[status] ?? status
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = {
    online: 'success',
    offline: 'secondary',
    error: 'danger',
    pairing: 'warn',
  }
  return map[status] ?? 'info'
}

function txSeverity(status: string): string {
  const map: Record<string, string> = {
    approved: 'success',
    pending: 'warn',
    declined: 'danger',
    cancelled: 'secondary',
    timeout: 'danger',
  }
  return map[status] ?? 'info'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

onMounted(loadTerminals)
</script>
