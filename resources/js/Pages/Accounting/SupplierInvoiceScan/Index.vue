<template>
  <AppLayout>
    <Head title="Scanner une facture fournisseur" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Scanner une facture fournisseur</h1>
        <p class="wh-page-subtitle">
          Photographiez ou importez une facture fournisseur (image ou PDF) — l'IA propose une extraction des champs,
          entièrement modifiable avant tout enregistrement. Rien n'est enregistré tant que vous n'avez pas validé.
        </p>
      </div>
    </div>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- STEP 1: upload -->
    <div v-if="!extracted" class="wh-panel" style="padding: 24px; max-width: 640px">
      <h3 style="margin-top: 0">1. Fichier (image ou PDF)</h3>
      <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="onFileSelected" style="margin-bottom: 16px" />

      <div v-if="previewError" class="error-banner" style="margin-bottom: 12px">{{ previewError }}</div>

      <Button
        label="Analyser le fichier"
        icon="pi pi-search"
        :loading="analyzing"
        :disabled="!selectedFile"
        @click="analyzeFile"
      />

      <p v-if="!analyzing" style="font-size: 12px; color: var(--fg-3); margin-top: 12px">
        Si l'IA n'est pas configurée ou ne parvient pas à lire le document, un formulaire vierge s'ouvrira — vous pourrez
        toujours saisir la facture manuellement.
      </p>
    </div>

    <!-- STEP 2: review + edit + commit -->
    <div v-else class="wh-panel" style="padding: 24px; max-width: 720px">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px">
        <h3 style="margin: 0">2. Vérifier et compléter les champs</h3>
        <Tag v-if="aiEnabled" value="Extrait par l'IA — à vérifier" severity="info" />
        <Tag v-else value="Saisie manuelle (IA indisponible)" severity="warning" />
      </div>

      <div class="form-grid">
        <div class="field">
          <label>Numéro de facture (optionnel)</label>
          <InputText v-model="form.number" />
        </div>
        <div class="field">
          <label>Fournisseur *</label>
          <InputText v-model="form.partner_name" />
        </div>
        <div class="field">
          <label>Date de facture *</label>
          <InputText v-model="form.invoice_date" type="date" />
        </div>
        <div class="field">
          <label>Date d'échéance</label>
          <InputText v-model="form.due_date" type="date" />
        </div>
        <div class="field">
          <label>Devise *</label>
          <Dropdown v-model="form.currency" :options="currencyOptions" />
        </div>
      </div>

      <h4>Lignes</h4>
      <DataTable :value="form.lines" class="mb-3">
        <Column header="Description">
          <template #body="{ data }">
            <InputText v-model="data.description" style="width: 100%" />
          </template>
        </Column>
        <Column header="Quantité" style="width: 110px">
          <template #body="{ data }">
            <InputText v-model.number="data.quantity" type="number" step="0.01" style="width: 100%" />
          </template>
        </Column>
        <Column header="Prix unitaire" style="width: 130px">
          <template #body="{ data }">
            <InputText v-model.number="data.unit_price" type="number" step="0.01" style="width: 100%" />
          </template>
        </Column>
        <Column header="TVA %" style="width: 90px">
          <template #body="{ data }">
            <InputText v-model.number="data.tax_rate" type="number" step="0.01" style="width: 100%" />
          </template>
        </Column>
        <Column header="" style="width: 50px">
          <template #body="{ index }">
            <Button icon="pi pi-trash" text severity="danger" @click="form.lines.splice(index, 1)" />
          </template>
        </Column>
      </DataTable>
      <Button label="Ajouter une ligne" icon="pi pi-plus" text @click="addLine" />

      <div v-if="commitError" class="error-banner" style="margin: 16px 0">{{ commitError }}</div>

      <div style="display: flex; gap: 8px; margin-top: 20px">
        <Button label="Recommencer" icon="pi pi-refresh" text @click="reset" />
        <Button
          label="Enregistrer la facture"
          icon="pi pi-check"
          :loading="committing"
          :disabled="!canCommit"
          @click="commit"
        />
      </div>
    </div>

    <div v-if="commitResult" class="wh-panel" style="padding: 16px; margin-top: 16px; border-left: 4px solid var(--success-fg); max-width: 720px">
      Facture fournisseur {{ commitResult.number }} enregistrée avec succès (total {{ fmt(commitResult.total) }}
      {{ commitResult.currency }}).
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dropdown, DataTable, Column, Tag, InputText } from 'primevue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Accounting', 'scan_supplier_invoice')

interface ScanLine {
  description: string | null
  quantity: number | null
  unit_price: number | null
  tax_rate: number
}

const currencyOptions = ['MGA', 'EUR', 'USD', 'CNY', 'XOF', 'XAF', 'MAD', 'NGN', 'GHS', 'KES', 'TZS', 'INR', 'EGP']

const selectedFile = ref<File | null>(null)
const analyzing = ref(false)
const previewError = ref('')
const extracted = ref(false)
const aiEnabled = ref(false)
const committing = ref(false)
const commitError = ref('')
const commitResult = ref<{ number: string; total: number; currency: string } | null>(null)

const form = ref<{
  number: string | null
  partner_name: string
  invoice_date: string
  due_date: string | null
  currency: string
  lines: ScanLine[]
}>({
  number: null,
  partner_name: '',
  invoice_date: '',
  due_date: null,
  currency: 'MGA',
  lines: [],
})

const canCommit = computed(() => form.value.partner_name.trim() !== '' && form.value.invoice_date !== '' && form.value.lines.length > 0)

function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
}

function addLine() {
  form.value.lines.push({ description: null, quantity: 1, unit_price: 0, tax_rate: 0 })
}

async function analyzeFile() {
  if (!selectedFile.value) return
  analyzing.value = true
  previewError.value = ''

  try {
    const body = new FormData()
    body.append('file', selectedFile.value)

    const { data } = await axios.post('/api/v1/accounting/supplier-invoice-scan/preview', body)

    aiEnabled.value = !!data.enabled
    const fields = data.fields ?? {}

    form.value = {
      number: fields.number ?? null,
      partner_name: fields.partner_name ?? '',
      invoice_date: fields.invoice_date ?? '',
      due_date: fields.due_date ?? null,
      currency: fields.currency ?? 'MGA',
      lines: (fields.lines ?? []).map((l: any) => ({
        description: l.description ?? null,
        quantity: l.quantity ?? 1,
        unit_price: l.unit_price ?? 0,
        tax_rate: 0,
      })),
    }

    if (form.value.lines.length === 0) {
      addLine()
    }

    extracted.value = true
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
    const body = new FormData()
    if (selectedFile.value) body.append('file', selectedFile.value)
    if (form.value.number) body.append('number', form.value.number)
    body.append('partner_name', form.value.partner_name)
    body.append('invoice_date', form.value.invoice_date)
    if (form.value.due_date) body.append('due_date', form.value.due_date)
    body.append('currency', form.value.currency)
    form.value.lines.forEach((line, i) => {
      body.append(`lines[${i}][description]`, line.description ?? '')
      body.append(`lines[${i}][quantity]`, String(line.quantity ?? 1))
      body.append(`lines[${i}][unit_price]`, String(line.unit_price ?? 0))
      body.append(`lines[${i}][tax_rate]`, String(line.tax_rate ?? 0))
    })

    const { data } = await axios.post('/api/v1/accounting/supplier-invoice-scan/commit', body)

    commitResult.value = { number: data.data.number, total: parseFloat(data.data.total), currency: data.data.currency }
    reset()
  } catch (e: any) {
    commitError.value = e?.response?.data?.message ?? "Erreur lors de l'enregistrement."
  } finally {
    committing.value = false
  }
}

function reset() {
  selectedFile.value = null
  extracted.value = false
  previewError.value = ''
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
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field label {
  font-size: 13px;
  color: var(--fg-3);
}
</style>
