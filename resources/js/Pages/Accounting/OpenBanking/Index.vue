<template>
  <AppLayout>
    <Head title="Open Banking" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Open Banking</h1>
        <p class="wh-page-subtitle">{{ connections.length }} connexion{{ connections.length !== 1 ? 's' : '' }} bancaire{{ connections.length !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <Button label="Connecter une banque" icon="pi pi-plus" severity="primary" @click="showConnectDialog = true" />
      </div>
    </div>

    <!-- KPIs -->
    <div class="wh-kpi-grid" style="margin-bottom: 24px">
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Transactions nouvelles</div>
        <div class="wh-kpi-value">{{ newTransactionsCount }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Solde total connecté</div>
        <div class="wh-kpi-value">{{ fmt(totalBalance) }} €</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Comptes connectés</div>
        <div class="wh-kpi-value">{{ connections.filter(c => c.status === 'active').length }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Transactions matchées</div>
        <div class="wh-kpi-value">{{ matchedCount }}</div>
      </div>
    </div>

    <div class="wh-two-col" style="gap: 16px">
      <!-- Sidebar: connexions bancaires -->
      <div class="wh-panel" style="min-width: 260px; max-width: 300px">
        <div style="font-weight: 600; font-size: 14px; margin-bottom: 12px">Connexions</div>
        <div
          v-for="conn in connections"
          :key="conn.id"
          class="connection-item"
          :class="{ active: selectedConnection?.id === conn.id }"
          @click="selectConnection(conn)"
        >
          <div style="display: flex; align-items: center; justify-content: space-between">
            <div>
              <div style="font-weight: 600; font-size: 14px">{{ conn.bank_name }}</div>
              <div style="font-size: 11px; color: var(--fg-3)">{{ conn.bank_code.toUpperCase() }}</div>
            </div>
            <Tag
              :value="conn.status === 'active' ? 'Actif' : conn.status === 'error' ? 'Erreur' : 'Inactif'"
              :severity="conn.status === 'active' ? 'success' : conn.status === 'error' ? 'danger' : 'secondary'"
              style="font-size: 10px"
            />
          </div>
          <div v-if="conn.last_synced_at" style="font-size: 11px; color: var(--fg-3); margin-top: 4px">
            Sync: {{ fmtDate(conn.last_synced_at) }}
          </div>
          <div style="display: flex; gap: 4px; margin-top: 8px">
            <Button
              v-if="conn.status === 'active'"
              icon="pi pi-refresh"
              size="small"
              text
              :loading="syncingId === conn.id"
              @click.stop="syncConnection(conn)"
              v-tooltip="'Synchroniser'"
            />
            <Button
              icon="pi pi-trash"
              size="small"
              text
              severity="danger"
              @click.stop="deleteConnection(conn)"
              v-tooltip="'Supprimer'"
            />
          </div>
        </div>

        <div v-if="connections.length === 0" style="color: var(--fg-3); font-size: 13px; text-align: center; padding: 24px 0">
          Aucune connexion bancaire
        </div>
      </div>

      <!-- Main content -->
      <div v-if="selectedConnection" class="wh-panel" style="flex: 1">
        <TabView>
          <TabPanel value="0" header="Comptes">
            <div v-if="feeds.length === 0" style="color: var(--fg-3); font-size: 13px; padding: 16px 0">
              Aucun compte récupéré. Synchronisez la connexion.
            </div>
            <div v-for="feed in feeds" :key="feed.id" class="feed-card">
              <div style="font-weight: 600">{{ feed.account_name }}</div>
              <div style="font-size: 12px; color: var(--fg-3)">{{ feed.iban }}</div>
              <div style="margin-top: 4px">
                <span style="font-size: 16px; font-weight: 600">{{ fmt(feed.balance) }} {{ feed.currency }}</span>
              </div>
            </div>
          </TabPanel>

          <TabPanel value="1" header="Transactions">
            <div style="display: flex; gap: 8px; margin-bottom: 16px; align-items: center">
              <Dropdown
                v-model="txFilter"
                :options="txFilterOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Tous les statuts"
                style="min-width: 160px"
              />
              <InputText v-model="txSearch" placeholder="Rechercher..." style="flex: 1" />
            </div>

            <DataTable
              :value="filteredTransactions"
              :loading="txLoading"
              stripedRows
              size="small"
              :rows="20"
              paginator
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
            >
              <Column field="date" header="Date" style="width: 110px">
                <template #body="{ data }">{{ fmtDate(data.date) }}</template>
              </Column>
              <Column field="description" header="Description" style="min-width: 200px" />
              <Column field="amount" header="Montant" style="width: 120px; text-align: right">
                <template #body="{ data }">
                  <span :class="parseFloat(data.amount) >= 0 ? 'amount-positive' : 'amount-negative'">
                    {{ fmt(data.amount) }} €
                  </span>
                </template>
              </Column>
              <Column field="category" header="Catégorie" style="width: 140px">
                <template #body="{ data }">
                  <Badge v-if="data.category" :value="data.category" severity="secondary" style="font-size: 10px" />
                  <span v-else style="color: var(--fg-3)">—</span>
                </template>
              </Column>
              <Column field="status" header="Statut" style="width: 110px">
                <template #body="{ data }">
                  <Tag
                    :value="statusLabel(data.status)"
                    :severity="statusSeverity(data.status)"
                    style="font-size: 10px"
                  />
                </template>
              </Column>
              <Column header="Actions" style="width: 130px">
                <template #body="{ data }">
                  <div style="display: flex; gap: 4px">
                    <Button
                      v-if="data.status === 'new'"
                      label="Matcher"
                      size="small"
                      text
                      @click="openMatchDialog(data)"
                    />
                    <Button
                      v-if="data.status === 'new'"
                      label="Ignorer"
                      size="small"
                      text
                      severity="secondary"
                      @click="ignoreTransaction(data)"
                    />
                  </div>
                </template>
              </Column>
            </DataTable>
          </TabPanel>
        </TabView>
      </div>

      <div v-else class="wh-panel" style="flex: 1; display: flex; align-items: center; justify-content: center; color: var(--fg-3)">
        Sélectionnez une connexion bancaire pour voir les détails
      </div>
    </div>

    <!-- Dialog: connecter une banque -->
    <Dialog v-model:visible="showConnectDialog" header="Connecter une banque" :style="{ width: '480px' }" modal>
      <div style="display: flex; flex-direction: column; gap: 16px">
        <div>
          <label class="form-label">Banque</label>
          <Dropdown
            v-model="connectForm.bank_code"
            :options="bankOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Sélectionner une banque"
            style="width: 100%"
          />
        </div>
        <div>
          <label class="form-label">Code d'autorisation OAuth</label>
          <InputText v-model="connectForm.auth_code" placeholder="Collez le code reçu de la banque..." style="width: 100%" />
          <small style="color: var(--fg-3)">
            Simulation OAuth : saisissez n'importe quel code pour simuler la connexion
          </small>
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="showConnectDialog = false" />
        <Button
          label="Connecter"
          icon="pi pi-link"
          :loading="connecting"
          :disabled="!connectForm.bank_code || !connectForm.auth_code"
          @click="connectBank"
        />
      </template>
    </Dialog>

    <!-- Dialog: matcher une transaction -->
    <Dialog v-model:visible="showMatchDialog" header="Matcher la transaction" :style="{ width: '560px' }" modal>
      <div v-if="matchingTx" style="margin-bottom: 16px">
        <div style="font-weight: 600">{{ matchingTx.description }}</div>
        <div :class="parseFloat(matchingTx.amount) >= 0 ? 'amount-positive' : 'amount-negative'">
          {{ fmt(matchingTx.amount) }} € · {{ fmtDate(matchingTx.date) }}
        </div>
      </div>

      <div>
        <label class="form-label">Écriture comptable (ID)</label>
        <InputText v-model="matchJournalId" placeholder="ID de l'écriture comptable..." type="number" style="width: 100%" />
        <small style="color: var(--fg-3)">Saisissez l'identifiant de l'écriture comptable à associer</small>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="showMatchDialog = false" />
        <Button
          label="Matcher"
          icon="pi pi-check"
          :loading="matching"
          :disabled="!matchJournalId"
          @click="confirmMatch"
        />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Dropdown, Badge } from 'primevue'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'

interface Connection {
  id: number
  bank_name: string
  bank_code: string
  status: 'active' | 'inactive' | 'error'
  last_synced_at: string | null
  token_expires_at: string | null
}

interface Feed {
  id: number
  account_name: string
  iban: string | null
  currency: string
  balance: string | null
}

interface Transaction {
  id: number
  feed_id: number
  external_id: string
  date: string
  amount: string
  description: string
  category: string | null
  merchant: string | null
  status: 'new' | 'matched' | 'ignored'
  journal_entry_id: number | null
  ai_category_suggestion: string | null
}

const connections = ref<Connection[]>([])
const selectedConnection = ref<Connection | null>(null)
const feeds = ref<Feed[]>([])
const transactions = ref<Transaction[]>([])
const txLoading = ref(false)
const syncingId = ref<number | null>(null)
const showConnectDialog = ref(false)
const showMatchDialog = ref(false)
const connecting = ref(false)
const matching = ref(false)
const matchingTx = ref<Transaction | null>(null)
const matchJournalId = ref('')
const txFilter = ref('')
const txSearch = ref('')

const connectForm = ref({ bank_code: '', auth_code: '' })

const bankOptions = [
  { label: 'BNP Paribas', value: 'bnp' },
  { label: 'Société Générale', value: 'sg' },
  { label: 'Crédit Agricole', value: 'ca' },
  { label: 'La Banque Postale', value: 'lbp' },
  { label: 'CIC', value: 'cic' },
  { label: 'LCL', value: 'lcl' },
  { label: 'HSBC France', value: 'hsbc' },
]

const txFilterOptions = [
  { label: 'Tous', value: '' },
  { label: 'Nouveaux', value: 'new' },
  { label: 'Matchés', value: 'matched' },
  { label: 'Ignorés', value: 'ignored' },
]

const newTransactionsCount = computed(() => transactions.value.filter(t => t.status === 'new').length)
const matchedCount = computed(() => transactions.value.filter(t => t.status === 'matched').length)
const totalBalance = computed(() => {
  return feeds.value.reduce((sum, f) => sum + parseFloat(f.balance ?? '0'), 0)
})

const filteredTransactions = computed(() => {
  let list = transactions.value
  if (txFilter.value) {
    list = list.filter(t => t.status === txFilter.value)
  }
  if (txSearch.value) {
    const q = txSearch.value.toLowerCase()
    list = list.filter(t => t.description.toLowerCase().includes(q) || (t.category ?? '').toLowerCase().includes(q))
  }
  return list
})

function fmt(value: string | number | null): string {
  if (value === null || value === undefined) return '0,00'
  return parseFloat(String(value)).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtDate(date: string): string {
  return new Date(date).toLocaleDateString('fr-FR')
}

function statusLabel(status: string): string {
  return { new: 'Nouveau', matched: 'Matché', ignored: 'Ignoré' }[status] ?? status
}

function statusSeverity(status: string): string {
  return { new: 'warn', matched: 'success', ignored: 'secondary' }[status] ?? 'secondary'
}

async function loadConnections() {
  const res = await fetch('/api/v1/accounting/open-banking/connections', {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
  if (res.ok) {
    const json = await res.json()
    connections.value = json.data ?? json
  }
}

async function selectConnection(conn: Connection) {
  selectedConnection.value = conn
  feeds.value = []
  transactions.value = []
  txLoading.value = true

  const [feedsRes, txRes] = await Promise.all([
    fetch(`/api/v1/accounting/open-banking/feeds/${conn.id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
    fetch(`/api/v1/accounting/open-banking/transactions?feed_id=`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
  ])

  if (feedsRes.ok) {
    feeds.value = await feedsRes.json()
  }

  // Load transactions for all feeds of this connection
  if (feeds.value.length > 0) {
    const firstFeedId = feeds.value[0].id
    const txRes2 = await fetch(`/api/v1/accounting/open-banking/transactions?feed_id=${firstFeedId}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    if (txRes2.ok) {
      const json = await txRes2.json()
      transactions.value = json.data ?? json
    }
  }

  txLoading.value = false
}

async function syncConnection(conn: Connection) {
  syncingId.value = conn.id
  try {
    const res = await fetch(`/api/v1/accounting/open-banking/connections/${conn.id}/sync`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
    })
    if (res.ok) {
      await loadConnections()
      if (selectedConnection.value?.id === conn.id) {
        await selectConnection(conn)
      }
    }
  } finally {
    syncingId.value = null
  }
}

async function deleteConnection(conn: Connection) {
  if (!confirm(`Supprimer la connexion ${conn.bank_name} ?`)) return
  await fetch(`/api/v1/accounting/open-banking/connections/${conn.id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { 'X-CSRF-TOKEN': getCsrf() },
  })
  if (selectedConnection.value?.id === conn.id) {
    selectedConnection.value = null
    feeds.value = []
    transactions.value = []
  }
  await loadConnections()
}

async function connectBank() {
  connecting.value = true
  try {
    const res = await fetch('/api/v1/accounting/open-banking/connections', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(connectForm.value),
    })
    if (res.ok) {
      showConnectDialog.value = false
      connectForm.value = { bank_code: '', auth_code: '' }
      await loadConnections()
    }
  } finally {
    connecting.value = false
  }
}

function openMatchDialog(tx: Transaction) {
  matchingTx.value = tx
  matchJournalId.value = ''
  showMatchDialog.value = true
}

async function confirmMatch() {
  if (!matchingTx.value || !matchJournalId.value) return
  matching.value = true
  try {
    const res = await fetch(`/api/v1/accounting/open-banking/transactions/${matchingTx.value.id}`, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify({ journal_entry_id: parseInt(matchJournalId.value) }),
    })
    if (res.ok) {
      showMatchDialog.value = false
      matchingTx.value = null
      if (selectedConnection.value) await selectConnection(selectedConnection.value)
    }
  } finally {
    matching.value = false
  }
}

async function ignoreTransaction(tx: Transaction) {
  await fetch(`/api/v1/accounting/open-banking/transactions/${tx.id}`, {
    method: 'PATCH',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
    body: JSON.stringify({ status: 'ignored' }),
  })
  if (selectedConnection.value) await selectConnection(selectedConnection.value)
}

function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

onMounted(loadConnections)
</script>

<style scoped>
.wh-two-col {
  display: flex;
  align-items: flex-start;
}
.connection-item {
  padding: 10px 12px;
  border-radius: 8px;
  cursor: pointer;
  margin-bottom: 4px;
  border: 1px solid transparent;
  transition: background 0.15s;
}
.connection-item:hover {
  background: var(--surface-hover);
}
.connection-item.active {
  background: var(--primary-50);
  border-color: var(--primary-200);
}
.amount-positive {
  color: var(--green-600);
  font-weight: 600;
}
.amount-negative {
  color: var(--red-600);
  font-weight: 600;
}
.feed-card {
  padding: 12px 16px;
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  margin-bottom: 8px;
}
.form-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 6px;
}
</style>
