<template>
  <AppLayout>
    <Head title="Rapprochement bancaire" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Rapprochement bancaire</h1>
        <p class="wh-page-subtitle">{{ accounts.length }} compte{{ accounts.length !== 1 ? 's' : '' }} bancaire{{ accounts.length !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <Button label="Connecter une banque" icon="pi pi-link" @click="showConnectDialog = true" />
        <Button label="Nouveau compte" icon="pi pi-plus" severity="primary" @click="showCreateDialog = true" />
      </div>
    </div>

    <!-- Bank accounts grid -->
    <div class="wh-kpi-grid" style="margin-bottom: 24px">
      <div v-for="account in accounts" :key="account.id" class="wh-panel" style="padding: 16px">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px">
          <div>
            <div style="font-weight: 600; font-size: 16px">{{ account.name }}</div>
            <div style="color: var(--fg-3); font-size: 13px">{{ account.bank_name }}</div>
          </div>
          <div style="display: flex; gap: 8px; align-items: center">
            <!-- Connection status badge -->
            <template v-for="conn in account.open_banking_connections" :key="conn.id">
              <Tag
                :value="conn.status === 'active' ? 'Connecté' : conn.status === 'expired' ? 'Expiré' : conn.status"
                :severity="conn.status === 'active' ? 'success' : conn.status === 'expired' ? 'danger' : 'warning'"
                style="font-size: 11px"
              />
              <Button
                v-if="conn.status === 'active'"
                icon="pi pi-refresh"
                size="small"
                text
                :loading="syncingId === conn.id"
                @click="syncConnection(conn)"
                title="Synchroniser"
              />
            </template>
          </div>
        </div>

        <div style="display: flex; gap: 24px; margin-bottom: 16px">
          <div>
            <div style="font-size: 11px; color: var(--fg-3); text-transform: uppercase; letter-spacing: 0.05em">Solde</div>
            <div class="font-display" style="font-size: 20px">{{ fmt(account.current_balance) }} {{ account.currency }}</div>
          </div>
          <div>
            <div style="font-size: 11px; color: var(--fg-3); text-transform: uppercase; letter-spacing: 0.05em">Non rapprochés</div>
            <div style="font-size: 20px; font-weight: 600; color: var(--warning)">{{ account.unreconciled_count ?? 0 }}</div>
          </div>
          <div v-if="account.last_synced_at">
            <div style="font-size: 11px; color: var(--fg-3); text-transform: uppercase; letter-spacing: 0.05em">Dernière synchro</div>
            <div style="font-size: 13px">{{ fmtDate(account.last_synced_at) }}</div>
          </div>
        </div>

        <div style="display: flex; gap: 8px">
          <Button
            label="Rapprocher"
            icon="pi pi-check-square"
            size="small"
            severity="secondary"
            :href="route('accounting.bank-reconciliation.reconcile', account.id)"
            as="a"
          />
        </div>
      </div>
    </div>

    <!-- Connect bank dialog -->
    <Dialog v-model:visible="showConnectDialog" header="Connecter une banque" :style="{ width: '480px' }" modal>
      <div style="display: flex; flex-direction: column; gap: 16px">
        <div>
          <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">Compte bancaire</label>
          <Select
            v-model="connectForm.bank_account_id"
            :options="accounts"
            option-label="name"
            option-value="id"
            placeholder="Sélectionner un compte"
            class="w-full"
          />
        </div>
        <div>
          <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 8px">Fournisseur</label>
          <div style="display: flex; gap: 12px">
            <div
              v-for="provider in providers"
              :key="provider.id"
              :class="['provider-card', connectForm.provider === provider.id ? 'provider-card-selected' : '']"
              @click="connectForm.provider = provider.id"
            >
              <div style="font-weight: 600">{{ provider.name }}</div>
              <div style="font-size: 11px; color: var(--fg-3)">{{ provider.description }}</div>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showConnectDialog = false" />
        <Button label="Connecter" icon="pi pi-external-link" :loading="connecting" @click="initiateConnect" />
      </template>
    </Dialog>

    <!-- Create account dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau compte bancaire" :style="{ width: '480px' }" modal>
      <div style="display: flex; flex-direction: column; gap: 16px">
        <div>
          <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">Nom du compte *</label>
          <InputText v-model="createForm.name" class="w-full" placeholder="Ex: Compte courant BNP" />
        </div>
        <div>
          <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">Banque *</label>
          <InputText v-model="createForm.bank_name" class="w-full" placeholder="Ex: BNP Paribas" />
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px">
          <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">IBAN</label>
            <InputText v-model="createForm.iban" class="w-full" placeholder="FR76..." />
          </div>
          <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">BIC</label>
            <InputText v-model="createForm.bic" class="w-full" placeholder="BNPAFRPP" />
          </div>
        </div>
        <div>
          <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">Devise</label>
          <Select v-model="createForm.currency" :options="currencies" option-label="label" option-value="value" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
        <Button label="Créer" icon="pi pi-check" :loading="creating" @click="createAccount" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dialog, InputText, Select, Tag } from 'primevue'

interface OpenBankingConnection {
  id: number
  status: string
}

interface BankAccount {
  id: number
  name: string
  bank_name: string
  currency: string
  current_balance: number
  last_synced_at: string | null
  unreconciled_count: number
  open_banking_connections: OpenBankingConnection[]
}

const props = defineProps<{
  accounts: BankAccount[]
}>()

const showConnectDialog = ref(false)
const showCreateDialog = ref(false)
const connecting = ref(false)
const creating = ref(false)
const syncingId = ref<number | null>(null)

const providers = [
  { id: 'nordigen', name: 'Nordigen', description: 'GoCardless Open Banking' },
  { id: 'plaid', name: 'Plaid', description: '12 000+ institutions' },
  { id: 'bridge', name: 'Bridge', description: 'Banques françaises' },
]

const currencies = [
  { label: 'EUR — Euro', value: 'EUR' },
  { label: 'USD — Dollar US', value: 'USD' },
  { label: 'GBP — Livre sterling', value: 'GBP' },
]

const connectForm = reactive({ bank_account_id: null as number | null, provider: 'nordigen' })
const createForm = reactive({ name: '', bank_name: '', iban: '', bic: '', currency: 'EUR' })

function fmt(n: number) {
  return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}

async function initiateConnect() {
  if (!connectForm.bank_account_id) return
  connecting.value = true
  try {
    const res = await fetch('/api/v1/accounting/open-banking/connect', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(connectForm),
    })
    const data = await res.json()
    if (data.redirect_url) {
      window.location.href = data.redirect_url
    }
  } finally {
    connecting.value = false
    showConnectDialog.value = false
  }
}

async function syncConnection(conn: OpenBankingConnection) {
  syncingId.value = conn.id
  try {
    await fetch(`/api/v1/accounting/open-banking/sync/${conn.id}`, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    router.reload()
  } finally {
    syncingId.value = null
  }
}

async function createAccount() {
  creating.value = true
  try {
    await fetch('/api/v1/accounting/bank-accounts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(createForm),
    })
    showCreateDialog.value = false
    router.reload()
  } finally {
    creating.value = false
  }
}
</script>

<style scoped>
.provider-card {
  flex: 1;
  border: 2px solid var(--border);
  border-radius: 8px;
  padding: 12px;
  cursor: pointer;
  transition: border-color 0.15s;
}
.provider-card:hover { border-color: var(--primary); }
.provider-card-selected { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 8%, transparent); }
</style>
