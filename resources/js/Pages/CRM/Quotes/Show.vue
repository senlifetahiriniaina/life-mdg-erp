<template>
  <AppLayout>
    <Head :title="`Devis ${quote?.reference ?? ''}`" />

    <div v-if="quote" style="max-width:900px;margin:0 auto">
      <!-- Header -->
      <div class="page-head" style="margin-bottom:20px">
        <div>
          <h1 class="wh-page-title" style="font-family:monospace">{{ quote.reference }}</h1>
          <div style="display:flex;gap:10px;align-items:center;margin-top:4px">
            <Tag :value="quote.status" :severity="statusSeverity(quote.status)" />
            <span v-if="quote.contact" style="font-size:13px;color:var(--fg-4)">
              <i class="pi pi-user" style="font-size:11px" /> {{ quote.contact.full_name }}
            </span>
            <span v-if="quote.opportunity" style="font-size:13px;color:var(--fg-4)">
              <i class="pi pi-briefcase" style="font-size:11px" /> {{ quote.opportunity.name }}
            </span>
            <span v-if="quote.valid_until" style="font-size:13px;color:var(--fg-4)">
              <i class="pi pi-calendar" style="font-size:11px" /> Valable jusqu'au {{ quote.valid_until }}
            </span>
          </div>
        </div>
        <div class="page-actions">
          <button class="btn btn-secondary" @click="suggestDiscount">
            ✨ Suggérer une remise
          </button>
          <button class="btn btn-secondary" @click="generatePdf">
            <i class="pi pi-file-pdf" style="font-size:13px" />
            Générer PDF
          </button>
          <Dropdown v-model="quote.status" :options="statusOptions" option-label="label" option-value="value" style="width:140px" @change="updateStatus" />
        </div>
      </div>

      <!-- Lines table -->
      <div class="wh-panel" style="margin-bottom:20px">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border-1);display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:600;font-size:15px">Lignes du devis</span>
          <button class="btn btn-primary" style="padding:4px 12px;font-size:13px" @click="openAddLine">
            <i class="pi pi-plus" style="font-size:11px" /> Ajouter
          </button>
        </div>

        <DataTable :value="quote.lines" style="font-size:13px">
          <Column header="Description" style="min-width:200px">
            <template #body="{ data, index }">
              <InputText v-if="editingLine === index" v-model="data.description" style="width:100%" />
              <span v-else>{{ data.description }}</span>
            </template>
          </Column>
          <Column header="Qté" style="width:90px;text-align:right">
            <template #body="{ data }">{{ data.quantity }}</template>
          </Column>
          <Column header="P.U." style="width:110px;text-align:right">
            <template #body="{ data }">{{ fmtCurrency(data.unit_price) }}</template>
          </Column>
          <Column header="Remise%" style="width:80px;text-align:right">
            <template #body="{ data }">{{ data.discount_pct }}%</template>
          </Column>
          <Column header="Total HT" style="width:120px;text-align:right">
            <template #body="{ data }">
              <strong>{{ fmtCurrency(data.line_total) }}</strong>
            </template>
          </Column>
          <Column header="" style="width:50px">
            <template #body="{ data }">
              <button style="background:none;border:none;cursor:pointer;color:#ef4444" @click="removeLine(data)">
                <i class="pi pi-trash" style="font-size:12px" />
              </button>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Summary panel -->
      <div class="wh-panel" style="max-width:360px;margin-left:auto">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
          <tr style="border-bottom:1px solid var(--border-1)">
            <td style="padding:10px 16px">Sous-total HT</td>
            <td style="padding:10px 16px;text-align:right">{{ fmtCurrency(quote.subtotal) }}</td>
          </tr>
          <tr style="border-bottom:1px solid var(--border-1)">
            <td style="padding:10px 16px">Remise</td>
            <td style="padding:10px 16px;text-align:right;color:#ef4444">- {{ fmtCurrency(quote.discount_amount) }}</td>
          </tr>
          <tr style="border-bottom:1px solid var(--border-1)">
            <td style="padding:10px 16px">TVA (20%)</td>
            <td style="padding:10px 16px;text-align:right">{{ fmtCurrency(quote.tax_amount) }}</td>
          </tr>
          <tr style="background:var(--surface-2)">
            <td style="padding:12px 16px;font-size:16px"><strong>TOTAL TTC</strong></td>
            <td style="padding:12px 16px;text-align:right;font-size:18px;color:#4f46e5"><strong>{{ fmtCurrency(quote.total) }}</strong></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Add line dialog -->
    <Dialog v-model:visible="showAddLine" header="Ajouter une ligne" :style="{ width: '440px' }" modal>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="wh-label">Description *</label>
          <InputText v-model="lineForm.description" class="w-full" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="wh-label">Quantité *</label>
            <InputNumber v-model="lineForm.quantity" :min="0.01" :min-fraction-digits="2" class="w-full" />
          </div>
          <div>
            <label class="wh-label">Prix unitaire *</label>
            <InputNumber v-model="lineForm.unit_price" :min="0" :min-fraction-digits="2" class="w-full" />
          </div>
        </div>
        <div>
          <label class="wh-label">Remise (%)</label>
          <InputNumber v-model="lineForm.discount_pct" :min="0" :max="100" :min-fraction-digits="2" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showAddLine = false" />
        <Button label="Ajouter" :loading="addingLine" @click="submitLine" />
      </template>
    </Dialog>

    <!-- AI suggestion dialog -->
    <Dialog v-model:visible="showAiSuggestion" header="✨ Suggestion de remise IA" :style="{ width: '480px' }" modal>
      <div v-if="aiSuggestion" style="white-space:pre-wrap;font-size:14px;line-height:1.6">{{ aiSuggestion }}</div>
      <div v-else style="color:var(--fg-4)">Analyse en cours…</div>
      <template #footer>
        <Button label="Fermer" @click="showAiSuggestion = false" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Select as Dropdown, InputNumber } from 'primevue'
import axios from 'axios'

const props = defineProps({ id: [String, Number] })

const quote = ref(null)
const loading = ref(false)
const showAddLine = ref(false)
const addingLine = ref(false)
const editingLine = ref(null)
const showAiSuggestion = ref(false)
const aiSuggestion = ref(null)

const lineForm = ref({ description: '', quantity: 1, unit_price: 0, discount_pct: 0 })

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Envoyé', value: 'sent' },
  { label: 'Accepté', value: 'accepted' },
  { label: 'Refusé', value: 'rejected' },
  { label: 'Expiré', value: 'expired' },
]

function statusSeverity(status) {
  return { draft: 'secondary', sent: 'info', accepted: 'success', rejected: 'danger', expired: 'warn' }[status] ?? 'secondary'
}

function fmtCurrency(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val || 0)
}

// Resolve ID from URL if not passed as prop
const quoteId = ref(props.id ?? window.location.pathname.split('/').pop())

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/v1/crm/quotes/${quoteId.value}`)
    quote.value = data
  } finally {
    loading.value = false
  }
}

async function updateStatus() {
  await axios.put(`/api/v1/crm/quotes/${quoteId.value}`, { status: quote.value.status })
}

function openAddLine() {
  lineForm.value = { description: '', quantity: 1, unit_price: 0, discount_pct: 0 }
  showAddLine.value = true
}

async function submitLine() {
  addingLine.value = true
  try {
    await axios.post(`/api/v1/crm/quotes/${quoteId.value}/lines`, lineForm.value)
    showAddLine.value = false
    await load()
  } finally {
    addingLine.value = false
  }
}

async function removeLine(line) {
  if (!confirm('Supprimer cette ligne ?')) return
  await axios.delete(`/api/v1/crm/quote-lines/${line.id}`)
  await load()
}

function generatePdf() {
  window.open(`/api/v1/crm/quotes/${quoteId.value}/pdf`, '_blank')
}

async function suggestDiscount() {
  showAiSuggestion.value = true
  aiSuggestion.value = null
  try {
    const context = `Devis ${quote.value?.reference}, total: ${quote.value?.total}€, statut: ${quote.value?.status}, ${quote.value?.lines?.length ?? 0} ligne(s).`
    const { data } = await axios.post('/api/v1/ai/ask', {
      question: `Analyse ce devis et suggère une remise commerciale optimale pour maximiser les chances d'acceptation. ${context}`,
      module: 'CRM',
    })
    aiSuggestion.value = data.answer ?? data.reply ?? JSON.stringify(data)
  } catch {
    aiSuggestion.value = 'Erreur lors de la consultation IA. Veuillez réessayer.'
  }
}

onMounted(load)
</script>
