<template>
  <AppLayout>
    <Head title="Déclarations TVA" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Déclarations TVA</h1>
        <p class="wh-page-subtitle">{{ declarations.total }} déclaration{{ declarations.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <Button label="Calculer une déclaration" icon="pi pi-calculator" severity="primary" @click="showCalcDialog = true" />
      </div>
    </div>

    <!-- Declarations table -->
    <div class="wh-panel" style="padding: 0">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Période</th>
            <th>Type</th>
            <th class="num">Ventes HT</th>
            <th class="num">TVA collectée</th>
            <th class="num">TVA déductible</th>
            <th class="num">TVA due</th>
            <th>Statut</th>
            <th style="width: 80px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in declarations.data" :key="d.id">
            <td>{{ periodLabel(d) }}</td>
            <td>{{ d.period_type === 'monthly' ? 'Mensuelle' : 'Trimestrielle' }}</td>
            <td class="num">{{ fmt(d.total_sales) }} €</td>
            <td class="num">{{ fmt(d.vat_collected) }} €</td>
            <td class="num">{{ fmt(d.vat_deductible) }} €</td>
            <td class="num" :style="{ fontWeight: 600, color: d.vat_due > 0 ? 'var(--danger)' : 'var(--success)' }">
              {{ fmt(d.vat_due) }} €
            </td>
            <td>
              <Tag :value="statusLabel(d.status)" :severity="statusSeverity(d.status)" />
            </td>
            <td>
              <Button icon="pi pi-eye" text size="small" :href="route('accounting.vat-declarations.web.show', d.id)" as="a" />
            </td>
          </tr>
          <tr v-if="declarations.data.length === 0">
            <td colspan="8" style="text-align: center; padding: 32px; color: var(--fg-3)">Aucune déclaration</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Calculate dialog -->
    <Dialog v-model:visible="showCalcDialog" header="Calculer une déclaration TVA" :style="{ width: '440px' }" modal>
      <div style="display: flex; flex-direction: column; gap: 16px">
        <div>
          <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">Type</label>
          <SelectButton v-model="calcForm.type" :options="periodTypes" option-label="label" option-value="value" />
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px">
          <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">Année</label>
            <InputNumber v-model="calcForm.year" :min="2000" :max="2100" class="w-full" :useGrouping="false" />
          </div>
          <div>
            <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">
              {{ calcForm.type === 'monthly' ? 'Mois (1-12)' : 'Trimestre (1-4)' }}
            </label>
            <InputNumber v-model="calcForm.period" :min="1" :max="calcForm.type === 'monthly' ? 12 : 4" class="w-full" :useGrouping="false" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showCalcDialog = false" />
        <Button label="Calculer" icon="pi pi-calculator" :loading="calculating" @click="calculate" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dialog, InputNumber, SelectButton, Tag } from 'primevue'

interface VatDeclaration {
  id: number
  period_type: string
  period_year: number
  period_number: number
  status: string
  total_sales: number
  total_purchases: number
  vat_collected: number
  vat_deductible: number
  vat_due: number
}

interface PaginatedDeclarations {
  data: VatDeclaration[]
  total: number
}

defineProps<{ declarations: PaginatedDeclarations }>()

const showCalcDialog = ref(false)
const calculating = ref(false)

const periodTypes = [
  { label: 'Mensuelle', value: 'monthly' },
  { label: 'Trimestrielle', value: 'quarterly' },
]

const calcForm = reactive({ type: 'monthly', year: new Date().getFullYear(), period: new Date().getMonth() + 1 })

const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']

function periodLabel(d: VatDeclaration) {
  if (d.period_type === 'monthly') return `${months[d.period_number - 1]} ${d.period_year}`
  return `T${d.period_number} ${d.period_year}`
}

function fmt(n: number) {
  return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

function statusLabel(s: string) {
  return { draft: 'Brouillon', submitted: 'Soumise', paid: 'Payée' }[s] ?? s
}

function statusSeverity(s: string): string {
  return { draft: 'secondary', submitted: 'warning', paid: 'success' }[s] ?? 'secondary'
}

async function calculate() {
  calculating.value = true
  try {
    await fetch('/api/v1/accounting/vat-declarations/calculate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(calcForm),
    })
    showCalcDialog.value = false
    router.reload()
  } finally {
    calculating.value = false
  }
}
</script>
