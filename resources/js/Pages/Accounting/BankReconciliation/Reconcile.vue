<template>
  <AppLayout>
    <Head :title="`Rapprochement — ${account.name}`" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ account.name }}</h1>
        <p class="wh-page-subtitle">Rapprochement bancaire</p>
      </div>
      <div class="page-actions">
        <Button
          label="Auto-rapprocher"
          icon="pi pi-bolt"
          severity="secondary"
          :loading="autoMatching"
          @click="autoMatch"
        />
        <Button
          label="Terminer"
          icon="pi pi-check"
          severity="success"
          :disabled="!activeSession"
          @click="completeSession"
        />
      </div>
    </div>

    <!-- Progress bar -->
    <div class="wh-panel" style="padding: 12px 16px; margin-bottom: 16px">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px">
        <span style="font-size: 13px; font-weight: 500">Progression du rapprochement</span>
        <span style="font-size: 13px; color: var(--fg-3)">{{ reconciledCount }} / {{ transactions.total }} transactions</span>
      </div>
      <ProgressBar :value="progressPercent" style="height: 8px" />
    </div>

    <!-- Two-column layout -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start">
      <!-- Left: Bank transactions -->
      <div class="wh-panel" style="padding: 0">
        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600">
          Transactions bancaires
          <span style="font-weight: 400; color: var(--fg-3); font-size: 13px">({{ unreconciledTxs.length }} non rapprochées)</span>
        </div>
        <div style="max-height: 600px; overflow-y: auto">
          <div
            v-for="tx in unreconciledTxs"
            :key="tx.id"
            :class="['tx-row', selectedTx?.id === tx.id ? 'tx-row-selected' : '']"
            @click="selectTx(tx)"
          >
            <div style="display: flex; justify-content: space-between; align-items: flex-start">
              <div>
                <div style="font-weight: 500; font-size: 13px">{{ tx.description }}</div>
                <div style="font-size: 11px; color: var(--fg-3)">{{ fmtDate(tx.date) }} · Réf: {{ tx.reference || '—' }}</div>
              </div>
              <div :style="{ fontWeight: 600, color: tx.type === 'credit' ? 'var(--success)' : 'var(--danger)' }">
                {{ tx.type === 'credit' ? '+' : '-' }}{{ fmt(tx.amount) }}
              </div>
            </div>
          </div>
          <div v-if="unreconciledTxs.length === 0" style="padding: 32px; text-align: center; color: var(--fg-3)">
            <i class="pi pi-check-circle" style="font-size: 32px; color: var(--success); display: block; margin-bottom: 8px" />
            Toutes les transactions sont rapprochées !
          </div>
        </div>
      </div>

      <!-- Right: GL entries -->
      <div class="wh-panel" style="padding: 0">
        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600">
          Écritures comptables
          <span v-if="selectedTx" style="font-weight: 400; color: var(--primary); font-size: 13px">
            — sélectionner une écriture à associer
          </span>
        </div>
        <div style="max-height: 600px; overflow-y: auto">
          <div
            v-for="entry in glEntries"
            :key="entry.id"
            :class="['tx-row', 'gl-row', selectedEntry?.id === entry.id ? 'tx-row-selected' : '']"
            @click="selectedEntry = entry"
          >
            <div style="display: flex; justify-content: space-between; align-items: flex-start">
              <div>
                <div style="font-weight: 500; font-size: 13px">{{ entry.description }}</div>
                <div style="font-size: 11px; color: var(--fg-3)">{{ fmtDate(entry.date) }} · {{ entry.reference }}</div>
              </div>
              <div style="text-align: right">
                <div style="font-size: 12px">D: {{ fmt(entry.total_debit) }}</div>
                <div style="font-size: 12px">C: {{ fmt(entry.total_credit) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Match button -->
        <div v-if="selectedTx && selectedEntry" style="padding: 12px 16px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end">
          <Button
            label="Associer"
            icon="pi pi-link"
            :loading="matching"
            @click="matchSelected"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, ProgressBar } from 'primevue'

interface Transaction {
  id: number
  date: string
  description: string
  amount: number
  type: 'debit' | 'credit'
  reference: string | null
  reconciled: boolean
}

interface GlEntry {
  id: number
  date: string
  description: string
  reference: string
  total_debit: number
  total_credit: number
}

interface PaginatedTransactions {
  data: Transaction[]
  total: number
}

interface BankAccount {
  id: number
  name: string
  currency: string
  current_balance: number
}

const props = defineProps<{
  account: BankAccount
  transactions: PaginatedTransactions
  glEntries: GlEntry[]
  activeSession?: { id: number } | null
}>()

const selectedTx = ref<Transaction | null>(null)
const selectedEntry = ref<GlEntry | null>(null)
const matching = ref(false)
const autoMatching = ref(false)

const unreconciledTxs = computed(() => props.transactions.data.filter(t => !t.reconciled))
const reconciledCount = computed(() => props.transactions.data.filter(t => t.reconciled).length)
const progressPercent = computed(() => {
  if (props.transactions.total === 0) return 0
  return Math.round((reconciledCount.value / props.transactions.total) * 100)
})

function fmt(n: number) {
  return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}

function selectTx(tx: Transaction) {
  selectedTx.value = selectedTx.value?.id === tx.id ? null : tx
  selectedEntry.value = null
}

async function matchSelected() {
  if (!selectedTx.value || !selectedEntry.value) return
  matching.value = true
  try {
    await fetch(`/api/v1/accounting/bank/transactions/${selectedTx.value.id}/match`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        entry_id: selectedEntry.value.id,
      }),
    })
    selectedTx.value = null
    selectedEntry.value = null
    router.reload()
  } finally {
    matching.value = false
  }
}

async function autoMatch() {
  autoMatching.value = true
  try {
    await fetch(`/api/v1/accounting/bank-accounts/${props.account.id}/transactions/auto-match`, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    router.reload()
  } finally {
    autoMatching.value = false
  }
}

async function completeSession() {
  if (!props.activeSession) return
  await fetch(
    `/api/v1/accounting/bank-accounts/${props.account.id}/sessions/${props.activeSession.id}/complete`,
    {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }
  )
  router.reload()
}
</script>

<style scoped>
.tx-row {
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background 0.1s;
}
.tx-row:hover { background: var(--surface-hover); }
.tx-row-selected { background: color-mix(in srgb, var(--primary) 8%, transparent); }
.gl-row { cursor: pointer; }
</style>
