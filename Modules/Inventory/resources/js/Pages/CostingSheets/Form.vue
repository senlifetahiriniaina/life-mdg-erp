<template>
  <AppLayout>
    <Head :title="isEdit ? 'Modifier la fiche de chiffrage' : 'Nouvelle fiche de chiffrage'" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            {{ isEdit ? sheet.reference : 'Nouvelle fiche de chiffrage' }}
          </h1>
          <p class="text-surface-500 text-sm mt-1">{{ form.name || 'Sans titre' }}</p>
        </div>
        <div class="flex gap-2">
          <Button label="Retour" text @click="router.visit('/inventory/costing-sheets')" />
          <Button label="Enregistrer" icon="pi pi-save" :loading="saving" @click="save" />
        </div>
      </div>

      <!-- En-tête -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
          <label class="text-sm font-medium block mb-1">Désignation</label>
          <InputText v-model="form.name" class="w-full" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Genre</label>
          <InputText v-model="form.gender" class="w-full" placeholder="HOMME / FEMME / UNISEXE" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Taille</label>
          <InputText v-model="form.size_range" class="w-full" placeholder="S-XXXL" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Saison</label>
          <InputText v-model="form.season" class="w-full" placeholder="COURANT" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Quantité</label>
          <InputNumber v-model="form.quantity" class="w-full" :min="1" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Devise de base</label>
          <InputText v-model="form.base_currency" class="w-full" maxlength="3" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Statut</label>
          <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Temps de gamme (mn)</label>
          <InputNumber v-model="form.production_minutes" class="w-full" :min-fraction-digits="2" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Coût minute</label>
          <InputNumber v-model="form.minute_cost" class="w-full" :min-fraction-digits="2" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Coefficient de couverture</label>
          <InputNumber v-model="form.fixed_cost_coefficient" class="w-full" :min-fraction-digits="2" />
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Marge visée (%)</label>
          <InputNumber v-model="form.target_margin_percent" class="w-full" suffix=" %" :min-fraction-digits="2" />
        </div>
      </div>

      <!-- Lignes par section -->
      <div v-for="(sectionLabel, sectionKey) in sections" :key="sectionKey" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold text-surface-800 dark:text-surface-100">{{ sectionLabel }}</h3>
          <Button icon="pi pi-plus" label="Ajouter une ligne" text size="small" @click="addLine(sectionKey)" />
        </div>

        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-surface-500">
              <th class="pb-2">Désignation</th>
              <th class="pb-2 w-24">Conso.</th>
              <th class="pb-2 w-20">Unité</th>
              <th class="pb-2 w-28">PU</th>
              <th class="pb-2 w-20">Devise</th>
              <th class="pb-2 w-24">% Frais</th>
              <th v-if="sectionKey === 'valeur_ajoutee'" class="pb-2 w-24">Marge %</th>
              <th class="pb-2 w-28 text-right">Total ligne</th>
              <th class="pb-2 w-10"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, idx) in linesBySection(sectionKey)" :key="line._key" class="border-t border-surface-100 dark:border-surface-700">
              <td class="py-1 pr-2"><InputText v-model="line.designation" class="w-full" size="small" /></td>
              <td class="py-1 pr-2"><InputNumber v-model="line.consumption_qty" class="w-full" size="small" :min-fraction-digits="0" :max-fraction-digits="4" /></td>
              <td class="py-1 pr-2"><InputText v-model="line.unit" class="w-full" size="small" /></td>
              <td class="py-1 pr-2"><InputNumber v-model="line.unit_price" class="w-full" size="small" :min-fraction-digits="0" :max-fraction-digits="4" /></td>
              <td class="py-1 pr-2"><InputText v-model="line.currency" class="w-full" size="small" maxlength="3" /></td>
              <td class="py-1 pr-2"><InputNumber v-model="line.customs_freight_percent" class="w-full" size="small" suffix=" %" /></td>
              <td v-if="sectionKey === 'valeur_ajoutee'" class="py-1 pr-2"><InputNumber v-model="line.margin_percent" class="w-full" size="small" suffix=" %" /></td>
              <td class="py-1 pr-2 text-right font-medium">{{ formatMoney(line.line_total) }}</td>
              <td class="py-1 text-right">
                <Button icon="pi pi-times" text size="small" severity="danger" @click="removeLine(idx, sectionKey)" />
              </td>
            </tr>
            <tr v-if="linesBySection(sectionKey).length === 0">
              <td colspan="9" class="py-3 text-center text-surface-400">Aucune ligne</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Totaux -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <p v-if="!isEdit" class="text-sm text-surface-400 mb-3">
          Les totaux sont calculés par le serveur lors de l'enregistrement — enregistrez une première fois pour les voir apparaître.
        </p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <p class="text-xs text-surface-500">Matière</p>
            <p class="font-semibold">{{ formatMoney(sheet.total_material_cost) }} {{ form.base_currency }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">Accessoires montage</p>
            <p class="font-semibold">{{ formatMoney(sheet.total_assembly_cost) }} {{ form.base_currency }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">Accessoires finition</p>
            <p class="font-semibold">{{ formatMoney(sheet.total_finishing_cost) }} {{ form.base_currency }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">Valeur ajoutée</p>
            <p class="font-semibold">{{ formatMoney(sheet.total_value_added_cost) }} {{ form.base_currency }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">Main-d'œuvre</p>
            <p class="font-semibold">{{ formatMoney(sheet.labor_cost) }} {{ form.base_currency }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">Coût de revient</p>
            <p class="font-bold text-lg">{{ formatMoney(sheet.total_cost_price) }} {{ form.base_currency }}</p>
          </div>
          <div class="md:col-span-2">
            <p class="text-xs text-surface-500">Prix de vente suggéré</p>
            <p class="font-bold text-lg text-primary-600">{{ formatMoney(sheet.suggested_selling_price) }} {{ form.base_currency }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  costingSheetId: { type: [Number, String], default: null },
})

const isEdit = computed(() => !!props.costingSheetId)
const toast = useToast()
const saving = ref(false)
const sheet = ref({})

const sections = {
  matiere: 'Matière',
  accessoire_montage: 'Accessoire de montage',
  accessoire_finition: 'Accessoire de finition',
  valeur_ajoutee: 'Valeur ajoutée',
  lavage: 'Type lavage',
}

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Chiffré', value: 'quoted' },
  { label: 'Approuvé', value: 'approved' },
  { label: 'Archivé', value: 'archived' },
]

const form = reactive({
  name: '',
  gender: '',
  size_range: '',
  season: '',
  quantity: 1,
  base_currency: 'MGA',
  status: 'draft',
  production_minutes: 0,
  minute_cost: 0,
  fixed_cost_coefficient: 0,
  target_margin_percent: null,
})

let lineKeySeq = 0
const lines = ref([])

function linesBySection(section) {
  return lines.value.filter((l) => l.section === section)
}

function addLine(section) {
  lines.value.push({
    _key: ++lineKeySeq,
    section,
    designation: '',
    consumption_qty: 0,
    unit: '',
    unit_price: 0,
    currency: form.base_currency,
    customs_freight_percent: 0,
    margin_percent: null,
    line_total: 0,
  })
}

function removeLine(idxInSection, section) {
  const target = linesBySection(section)[idxInSection]
  lines.value = lines.value.filter((l) => l !== target)
}

function formatMoney(value) {
  return Number(value ?? 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 })
}

function applySheet(data) {
  sheet.value = data
  Object.assign(form, {
    name: data.name,
    gender: data.gender,
    size_range: data.size_range,
    season: data.season,
    quantity: data.quantity,
    base_currency: data.base_currency,
    status: data.status,
    production_minutes: Number(data.production_minutes),
    minute_cost: Number(data.minute_cost),
    fixed_cost_coefficient: Number(data.fixed_cost_coefficient),
    target_margin_percent: data.target_margin_percent !== null ? Number(data.target_margin_percent) : null,
  })
  lines.value = (data.lines || []).map((l) => ({
    _key: ++lineKeySeq,
    section: l.section,
    designation: l.designation,
    consumption_qty: Number(l.consumption_qty),
    unit: l.unit,
    unit_price: Number(l.unit_price),
    currency: l.currency,
    customs_freight_percent: Number(l.customs_freight_percent),
    margin_percent: l.margin_percent !== null ? Number(l.margin_percent) : null,
    line_total: Number(l.line_total),
  }))
}

async function load() {
  if (!isEdit.value) return
  const { data } = await axios.get(`/api/v1/inventory/costing-sheets/${props.costingSheetId}`)
  applySheet(data.data)
}

async function save() {
  saving.value = true
  try {
    const payload = {
      ...form,
      lines: lines.value.map(({ _key, line_total, ...rest }) => rest),
    }
    const { data } = isEdit.value
      ? await axios.put(`/api/v1/inventory/costing-sheets/${props.costingSheetId}`, payload)
      : await axios.post('/api/v1/inventory/costing-sheets', payload)

    toast.add({ severity: 'success', summary: 'Fiche enregistrée', life: 2000 })

    if (!isEdit.value) {
      router.visit(`/inventory/costing-sheets/${data.data.id}/edit`)
      return
    }
    applySheet(data.data)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Échec de l\'enregistrement', detail: e?.response?.data?.message, life: 4000 })
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
