<template>
  <AppLayout>
    <Head title="Import caisse / banque" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Import opérations de caisse / relevé bancaire</h1>
        <p class="wh-page-subtitle">
          Importez un fichier d'encaissements/décaissements ou un relevé bancaire — l'application propose un modèle
          d'écriture comptable par ligne, à valider ou modifier avant enregistrement.
        </p>
      </div>
    </div>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- STEP 1: choose treasury account + upload -->
    <div v-if="rows.length === 0" class="wh-panel" style="padding: 24px; max-width: 640px">
      <h3 style="margin-top: 0">1. Compte de trésorerie</h3>
      <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px">
        <label style="font-size: 13px; color: var(--fg-3)">Compte de trésorerie (caisse, banque, mobile money...)</label>
        <Dropdown
          v-model="treasuryAccountCode"
          :options="treasuryAccounts"
          optionLabel="displayLabel"
          optionValue="code"
          :loading="loadingTreasuryAccounts"
          placeholder="Sélectionner..."
          @change="onTreasuryAccountChange"
        />
      </div>

      <div v-if="isBankLike" style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px">
        <label style="font-size: 13px; color: var(--fg-3)">Compte bancaire (optionnel — pour rapprocher automatiquement)</label>
        <Dropdown
          v-model="bankAccountId"
          :options="bankAccounts"
          optionLabel="name"
          optionValue="id"
          placeholder="Aucun (écritures comptables uniquement)"
          showClear
        />
      </div>

      <h3>2. Fichier (CSV ou Excel)</h3>
      <p style="font-size: 13px; color: var(--fg-3)">
        Colonnes attendues : <code>date</code>, <code>libellé</code> (ou description), <code>montant</code>. Montant
        positif = encaissement, négatif = décaissement (le modèle proposé peut toujours corriger le sens).
      </p>
      <input type="file" accept=".csv,.txt,.xlsx,.xls" @change="onFileSelected" style="margin-bottom: 16px" />

      <div v-if="previewError" class="error-banner" style="margin-bottom: 12px">{{ previewError }}</div>

      <Button
        label="Analyser le fichier"
        icon="pi pi-search"
        :loading="analyzing"
        :disabled="!selectedFile || !treasuryAccountCode"
        @click="analyzeFile"
      />
    </div>

    <!-- STEP 2: review + validate -->
    <div v-else>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px">
        <p style="margin: 0">{{ rows.length }} ligne{{ rows.length !== 1 ? 's' : '' }} détectée{{ rows.length !== 1 ? 's' : '' }}</p>
        <Button label="Recommencer" icon="pi pi-refresh" text @click="reset" />
      </div>

      <DataTable :value="rows" class="mb-4">
        <Column field="date" header="Date" style="width: 110px" />
        <Column field="description" header="Libellé" />
        <Column header="Montant" style="width: 130px">
          <template #body="{ data }">
            <span :style="{ color: data.nature === 'encaissement' ? 'var(--success-fg)' : 'var(--danger-fg)' }">
              {{ data.nature === 'encaissement' ? '+' : '−' }}{{ fmt(Math.abs(data.amount)) }}
            </span>
          </template>
        </Column>
        <Column header="Modèle d'opération" style="min-width: 280px">
          <template #body="{ data }">
            <Dropdown
              v-model="data.template_code"
              :options="templatesForNature(data.nature)"
              optionLabel="label"
              optionValue="code"
              style="width: 100%"
            />
          </template>
        </Column>
        <Column header="Confiance" style="width: 110px">
          <template #body="{ data }">
            <Tag
              :value="Math.round(data.confidence * 100) + ' %'"
              :severity="data.confidence >= 0.75 ? 'success' : data.confidence >= 0.5 ? 'warning' : 'danger'"
            />
          </template>
        </Column>
        <Column header="" style="width: 60px">
          <template #body="{ data }">
            <Checkbox v-model="data.included" :binary="true" title="Inclure cette ligne" />
          </template>
        </Column>
      </DataTable>

      <div v-if="commitError" class="error-banner" style="margin-bottom: 12px">{{ commitError }}</div>
      <div v-if="commitResult" class="wh-panel" style="padding: 16px; margin-bottom: 16px; border-left: 4px solid var(--success-fg)">
        {{ commitResult.count }} écriture{{ commitResult.count !== 1 ? 's' : '' }} comptable{{ commitResult.count !== 1 ? 's' : '' }}
        enregistrée{{ commitResult.count !== 1 ? 's' : '' }} (total {{ fmt(commitResult.total_debit) }} MGA).
      </div>

      <Button
        label="Valider et enregistrer"
        icon="pi pi-check"
        :loading="committing"
        :disabled="includedCount === 0"
        @click="commit"
      />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dropdown, DataTable, Column, Tag, Checkbox } from 'primevue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Accounting', 'import_treasury')

interface OperationTemplate {
  code: string
  label: string
  nature: 'encaissement' | 'decaissement'
}

interface PreviewRow {
  date: string
  description: string
  amount: number
  nature: 'encaissement' | 'decaissement'
  suggested_template_code: string
  template_code: string
  confidence: number
  alternatives: string[]
  included: boolean
}

interface TreasuryAccount {
  code: string
  name: string
  journal: 'CAI' | 'BNQ'
  displayLabel?: string
}

const treasuryAccounts = ref<TreasuryAccount[]>([])
const loadingTreasuryAccounts = ref(false)
const treasuryAccountCode = ref<string | null>(null)
const bankAccountId = ref<number | null>(null)
const bankAccounts = ref<{ id: number; name: string }[]>([])
const selectedFile = ref<File | null>(null)
const analyzing = ref(false)
const previewError = ref('')
const rows = ref<PreviewRow[]>([])
const templates = ref<OperationTemplate[]>([])
const committing = ref(false)
const commitError = ref('')
const commitResult = ref<{ count: number; total_debit: number } | null>(null)

// A "banque-like" account is any real treasury account that doesn't post
// to the CAI (caisse) journal — Chantier 36: derived from the real
// account's journal instead of a hardcoded '530' comparison, so this
// correctly covers every named caisse/banque/mobile-money account the
// company's own chart of accounts defines, not just the 4 old codes.
const isBankLike = computed(() => {
  const account = treasuryAccounts.value.find((a) => a.code === treasuryAccountCode.value)
  return account !== undefined && account.journal !== 'CAI'
})
const includedCount = computed(() => rows.value.filter((r) => r.included).length)

async function loadTreasuryAccounts() {
  loadingTreasuryAccounts.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/treasury-accounts')
    treasuryAccounts.value = (data.data ?? []).map((a: TreasuryAccount) => ({
      ...a,
      displayLabel: `${a.code} — ${a.name}`,
    }))
  } catch {
    treasuryAccounts.value = []
  } finally {
    loadingTreasuryAccounts.value = false
  }
}

async function onTreasuryAccountChange() {
  bankAccountId.value = null
  if (!isBankLike.value) return
  try {
    const { data } = await axios.get('/api/v1/accounting/bank')
    bankAccounts.value = (data.data ?? data ?? []).map((a: any) => ({ id: a.id, name: `${a.name} — ${a.bank_name}` }))
  } catch {
    bankAccounts.value = []
  }
}

onMounted(loadTreasuryAccounts)

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
}

async function loadTemplates() {
  if (templates.value.length > 0) return
  const { data } = await axios.get('/api/v1/accounting/operation-templates')
  templates.value = data.data ?? []
}

function templatesForNature(nature: string) {
  return templates.value.filter((t) => t.nature === nature)
}

async function analyzeFile() {
  if (!selectedFile.value || !treasuryAccountCode.value) return
  analyzing.value = true
  previewError.value = ''

  try {
    await loadTemplates()

    const form = new FormData()
    form.append('file', selectedFile.value)
    form.append('treasury_account_code', treasuryAccountCode.value)

    const { data } = await axios.post('/api/v1/accounting/treasury-imports/preview', form)

    rows.value = (data.rows ?? []).map((r: any) => ({
      ...r,
      template_code: r.suggested_template_code,
      included: true,
    }))
  } catch (e: any) {
    previewError.value = e?.response?.data?.message ?? "Erreur lors de l'analyse du fichier."
  } finally {
    analyzing.value = false
  }
}

async function commit() {
  committing.value = true
  commitError.value = ''
  commitResult.value = null

  try {
    const payload = {
      treasury_account_code: treasuryAccountCode.value,
      bank_account_id: bankAccountId.value,
      rows: rows.value
        .filter((r) => r.included)
        .map((r) => ({ date: r.date, description: r.description, amount: r.amount, template_code: r.template_code })),
    }

    const { data } = await axios.post('/api/v1/accounting/treasury-imports/commit', payload)
    commitResult.value = data.data
    rows.value = []
    selectedFile.value = null
  } catch (e: any) {
    commitError.value = e?.response?.data?.message ?? "Erreur lors de l'enregistrement."
  } finally {
    committing.value = false
  }
}

function reset() {
  rows.value = []
  selectedFile.value = null
  previewError.value = ''
  commitResult.value = null
}

function fmt(n: number): string {
  return new Intl.NumberFormat('fr-FR').format(n)
}
</script>

<style scoped>
.error-banner {
  padding: 10px 14px;
  background: var(--danger-bg);
  color: var(--danger-fg);
  border-radius: 6px;
}
</style>
