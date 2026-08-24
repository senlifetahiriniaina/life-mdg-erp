<template>
  <AppLayout>
    <Head title="Dettes fournisseurs" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Dettes fournisseurs (balance âgée)</h1>
          <p class="wh-page-subtitle">Calculée en direct sur les factures fournisseur non soldées — aucun montant préchargé</p>
        </div>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div v-if="loading" class="wh-empty-state">Chargement…</div>

      <template v-else>
        <div class="wh-summary">
          <div class="wh-summary-card">
            <div class="wh-summary-value">{{ fmt(data.total_due) }}</div>
            <div class="wh-summary-label">Total dû, tous fournisseurs</div>
          </div>
        </div>

        <table class="wh-table">
          <thead><tr><th>Fournisseur</th><th>0-30 j</th><th>31-60 j</th><th>61-90 j</th><th>90+ j</th><th>Total dû</th></tr></thead>
          <tbody>
            <tr v-for="s in data.suppliers" :key="s.partner_id ?? s.partner_name">
              <td>{{ s.partner_name || '—' }}</td>
              <td>{{ fmt(s.buckets.current) }}</td>
              <td>{{ fmt(s.buckets.d31_60) }}</td>
              <td>{{ fmt(s.buckets.d61_90) }}</td>
              <td :style="{ color: s.buckets.d90_plus > 0 ? '#DC2626' : undefined }">{{ fmt(s.buckets.d90_plus) }}</td>
              <td><strong>{{ fmt(s.total_due) }}</strong></td>
            </tr>
            <tr v-if="data.suppliers.length === 0">
              <td colspan="6" class="wh-empty-state">Aucune facture fournisseur impayée pour le moment.</td>
            </tr>
          </tbody>
        </table>

        <template v-for="s in data.suppliers" :key="'detail-' + (s.partner_id ?? s.partner_name)">
          <h3 class="wh-section-title" style="margin-top: 20px">{{ s.partner_name || '—' }} — détail des factures</h3>
          <table class="wh-table">
            <thead><tr><th>N°</th><th>Échéance</th><th>Total</th><th>Payé</th><th>Dû</th><th>Retard (j)</th><th>Étape</th></tr></thead>
            <tbody>
              <tr v-for="inv in s.invoices" :key="inv.id">
                <td>{{ inv.number || '—' }}</td>
                <td>{{ inv.due_date?.slice(0, 10) || '—' }}</td>
                <td>{{ fmt(inv.total) }}</td>
                <td>{{ fmt(inv.amount_paid) }}</td>
                <td>{{ fmt(inv.amount_due) }}</td>
                <td :style="{ color: inv.days_overdue > 0 ? '#DC2626' : '#059669' }">{{ inv.days_overdue }}</td>
                <td>{{ stageLabel(inv.payment_stage) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Accounting', 'view_aged_payables')

const loading = ref(true)
const data = ref({ suppliers: [], total_due: 0 })

const load = async () => {
  loading.value = true
  try {
    const { data: res } = await axios.get('/api/v1/accounting/supplier-debt/aged-payables')
    data.value = res
  } catch (e) {
    console.error(e)
  } finally { loading.value = false }
}

const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
const stageLabel = (stage) => ({
  deposit_invoiced: 'Acompte facturé',
  deposit_paid: 'Acompte payé',
  balance_invoiced: 'Solde facturé',
  balance_paid: 'Soldé',
  paid_in_full: 'Payé intégralement',
}[stage] || (stage || '—'))

onMounted(load)
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-summary { display: flex; gap: 12px; margin-bottom: 20px; }
.wh-summary-card { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px 20px; }
.wh-summary-value { font-size: 22px; font-weight: 700; }
.wh-summary-label { font-size: 12px; color: #6B7280; margin-top: 2px; }
.wh-section-title { font-size: 15px; font-weight: 600; margin: 24px 0 12px; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
</style>
