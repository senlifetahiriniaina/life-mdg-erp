<template>
  <AppLayout>
    <Head :title="`TVA — ${periodLabel}`" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Déclaration TVA · {{ periodLabel }}</h1>
        <p class="wh-page-subtitle">
          <Tag :value="statusLabel" :severity="statusSeverity" />
          <span v-if="declaration.reference" style="margin-left: 8px; color: var(--fg-3); font-size: 13px">Réf: {{ declaration.reference }}</span>
        </p>
      </div>
      <div class="page-actions">
        <Button label="Exporter XML" icon="pi pi-file-export" severity="secondary" @click="exportXml" />
        <Button
          v-if="declaration.status === 'draft'"
          label="Soumettre"
          icon="pi pi-send"
          severity="primary"
          @click="showSubmitDialog = true"
        />
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom: 24px">
      <div class="wh-kpi">
        <div class="wh-kpi-label">Ventes HT</div>
        <div class="wh-kpi-num font-display">{{ fmt(declaration.total_sales) }} €</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Achats HT</div>
        <div class="wh-kpi-num font-display">{{ fmt(declaration.total_purchases) }} €</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">TVA collectée</div>
        <div class="wh-kpi-num font-display" style="color: var(--danger)">{{ fmt(declaration.vat_collected) }} €</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">TVA déductible</div>
        <div class="wh-kpi-num font-display" style="color: var(--success)">{{ fmt(declaration.vat_deductible) }} €</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">TVA due</div>
        <div class="wh-kpi-num font-display" :style="{ color: declaration.vat_due > 0 ? 'var(--danger)' : 'var(--success)' }">
          {{ fmt(declaration.vat_due) }} €
        </div>
      </div>
    </div>

    <!-- Summary panel -->
    <div class="wh-panel" style="padding: 20px; margin-bottom: 16px">
      <h3 style="margin: 0 0 16px; font-size: 15px">Détail du calcul</h3>
      <table style="width: 100%; border-collapse: collapse; font-size: 14px">
        <tbody>
          <tr style="border-bottom: 1px solid var(--border)">
            <td style="padding: 8px 0; color: var(--fg-2)">Total des ventes (HT)</td>
            <td style="padding: 8px 0; text-align: right; font-weight: 500">{{ fmt(declaration.total_sales) }} €</td>
          </tr>
          <tr style="border-bottom: 1px solid var(--border)">
            <td style="padding: 8px 0; color: var(--fg-2)">TVA collectée sur ventes</td>
            <td style="padding: 8px 0; text-align: right; font-weight: 500; color: var(--danger)">{{ fmt(declaration.vat_collected) }} €</td>
          </tr>
          <tr style="border-bottom: 1px solid var(--border)">
            <td style="padding: 8px 0; color: var(--fg-2)">Total des achats (HT)</td>
            <td style="padding: 8px 0; text-align: right; font-weight: 500">{{ fmt(declaration.total_purchases) }} €</td>
          </tr>
          <tr style="border-bottom: 1px solid var(--border)">
            <td style="padding: 8px 0; color: var(--fg-2)">TVA déductible sur achats</td>
            <td style="padding: 8px 0; text-align: right; font-weight: 500; color: var(--success)">{{ fmt(declaration.vat_deductible) }} €</td>
          </tr>
          <tr style="border-top: 2px solid var(--border)">
            <td style="padding: 12px 0; font-weight: 700; font-size: 16px">TVA nette due</td>
            <td style="padding: 12px 0; text-align: right; font-weight: 700; font-size: 16px"
                :style="{ color: declaration.vat_due > 0 ? 'var(--danger)' : 'var(--success)' }">
              {{ fmt(declaration.vat_due) }} €
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Submit dialog -->
    <Dialog v-model:visible="showSubmitDialog" header="Soumettre la déclaration" :style="{ width: '400px' }" modal>
      <div>
        <label style="font-size: 13px; font-weight: 500; display: block; margin-bottom: 4px">Référence de dépôt *</label>
        <InputText v-model="reference" class="w-full" placeholder="Ex: 2024-T1-12345" />
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showSubmitDialog = false" />
        <Button label="Confirmer" icon="pi pi-send" :loading="submitting" @click="submit" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dialog, InputText, Tag } from 'primevue'

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
  reference: string | null
  submitted_at: string | null
}

const props = defineProps<{ declaration: VatDeclaration }>()

const showSubmitDialog = ref(false)
const submitting = ref(false)
const reference = ref('')

const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']

const periodLabel = computed(() => {
  const d = props.declaration
  if (d.period_type === 'monthly') return `${months[d.period_number - 1]} ${d.period_year}`
  return `T${d.period_number} ${d.period_year}`
})

const statusLabel = computed(() => ({
  draft: 'Brouillon', submitted: 'Soumise', paid: 'Payée'
}[props.declaration.status] ?? props.declaration.status))

const statusSeverity = computed((): string => ({
  draft: 'secondary', submitted: 'warning', paid: 'success'
}[props.declaration.status] ?? 'secondary'))

function fmt(n: number) {
  return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

function exportXml() {
  window.open(`/api/v1/accounting/vat-declarations/${props.declaration.id}/xml`, '_blank')
}

async function submit() {
  if (!reference.value) return
  submitting.value = true
  try {
    await fetch(`/api/v1/accounting/vat-declarations/${props.declaration.id}/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ reference: reference.value }),
    })
    showSubmitDialog.value = false
    router.reload()
  } finally {
    submitting.value = false
  }
}
</script>
