<template>
  <AppLayout>
    <Head title="Traçabilité de la commande" />

    <div class="max-w-4xl mx-auto space-y-6" v-if="trace">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Traçabilité — {{ trace.production_order.reference }}
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Devis chiffré → commande client → achats matières → sous-traitance → livraison, en un seul écran.
          </p>
        </div>
        <Link href="/inventory/production-orders" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
          ← Retour aux commandes de production
        </Link>
      </div>

      <!-- Fiche de chiffrage -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-3">1. Devis chiffré</h3>
        <div v-if="trace.costing_sheet" class="text-sm space-y-1">
          <p><span class="text-surface-500">Référence :</span> {{ trace.costing_sheet.reference }} — {{ trace.costing_sheet.name }}</p>
          <p><span class="text-surface-500">Coût de revient :</span> {{ formatMoney(trace.costing_sheet.total_cost_price) }} {{ trace.costing_sheet.base_currency }}</p>
          <p><span class="text-surface-500">Prix suggéré :</span> {{ formatMoney(trace.costing_sheet.suggested_selling_price) }} {{ trace.costing_sheet.base_currency }}</p>
        </div>
        <p v-else class="text-sm text-surface-400">Aucune fiche de chiffrage liée.</p>
      </div>

      <!-- Commande client -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-3">2. Commande client</h3>
        <div v-if="trace.sales_order" class="text-sm space-y-1">
          <p><span class="text-surface-500">Référence :</span> {{ trace.sales_order.reference }}</p>
          <p><span class="text-surface-500">Total :</span> {{ formatMoney(trace.sales_order.total) }} {{ trace.sales_order.currency }}</p>
          <p class="flex items-center gap-2">
            <span class="text-surface-500">Acompte/Solde :</span>
            <Tag :value="paymentStageLabel(trace.sales_order.payment_stage)" :severity="paymentStageSeverity(trace.sales_order.payment_stage)" />
          </p>
        </div>
        <p v-else class="text-sm text-surface-400">Aucune commande client liée.</p>
      </div>

      <!-- Achats matières -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-3">3. Achats matières</h3>
        <table v-if="trace.material_purchase_orders.length" class="w-full text-sm">
          <thead class="border-b border-surface-200 dark:border-surface-700">
            <tr>
              <th class="px-2 py-2 text-left">N° commande</th>
              <th class="px-2 py-2 text-left">Fournisseur</th>
              <th class="px-2 py-2 text-left">Statut</th>
              <th class="px-2 py-2 text-right">Total</th>
              <th class="px-2 py-2 text-left">Acompte/Solde</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="po in trace.material_purchase_orders" :key="po.id" class="border-b border-surface-100 dark:border-surface-700">
              <td class="px-2 py-2">{{ po.po_number }}</td>
              <td class="px-2 py-2">{{ po.supplier_name ?? '—' }}</td>
              <td class="px-2 py-2">{{ po.status }}</td>
              <td class="px-2 py-2 text-right">{{ formatMoney(po.total) }} {{ po.currency }}</td>
              <td class="px-2 py-2">
                <Tag :value="paymentStageLabel(po.payment_stage)" :severity="paymentStageSeverity(po.payment_stage)" />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-sm text-surface-400">Aucun achat de matières lié à cette commande de production.</p>
      </div>

      <!-- Sous-traitance / production -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-3">4. Sous-traitance / production</h3>
        <div class="text-sm space-y-1">
          <p v-if="trace.subcontractor"><span class="text-surface-500">Sous-traitant :</span> {{ trace.subcontractor.name }}</p>
          <p><span class="text-surface-500">Quantité :</span> {{ trace.production_order.quantity }}</p>
          <p class="flex items-center gap-2">
            <span class="text-surface-500">Statut :</span>
            <Tag :value="statusLabel(trace.production_order.status)" :severity="statusSeverity(trace.production_order.status)" />
          </p>
          <p v-if="trace.production_order.started_at"><span class="text-surface-500">Début sous-traitance :</span> {{ trace.production_order.started_at }}</p>
        </div>
      </div>

      <!-- Livraison -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-3">5. Livraison</h3>
        <p v-if="trace.production_order.delivered_at" class="text-sm">
          <span class="text-surface-500">Livré le :</span> {{ trace.production_order.delivered_at }}
        </p>
        <p v-else-if="trace.production_order.expected_delivery_at" class="text-sm">
          <span class="text-surface-500">Livraison prévue :</span> {{ trace.production_order.expected_delivery_at }}
        </p>
        <p v-else class="text-sm text-surface-400">Pas encore livré.</p>
      </div>
    </div>

    <div v-else class="text-center py-16 text-surface-400">Chargement…</div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ productionOrderId: { type: Number, required: true } })

const trace = ref(null)

function formatMoney(value) {
  return Number(value ?? 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 })
}

const STATUS_LABELS = {
  draft: 'Brouillon',
  materials_ready: 'Matières réunies',
  in_subcontracting: 'En sous-traitance',
  quality_check: 'Contrôle qualité',
  ready_for_delivery: 'Prêt à livrer',
  delivered: 'Livré',
  cancelled: 'Annulé',
}
function statusLabel(status) {
  return STATUS_LABELS[status] ?? status
}
function statusSeverity(status) {
  return {
    draft: 'secondary', materials_ready: 'info', in_subcontracting: 'warn',
    quality_check: 'warn', ready_for_delivery: 'info', delivered: 'success', cancelled: 'contrast',
  }[status] ?? 'secondary'
}

const PAYMENT_LABELS = {
  none: 'Aucun', deposit_invoiced: 'Acompte facturé', deposit_paid: 'Acompte payé',
  balance_invoiced: 'Solde facturé', paid_in_full: 'Payé intégralement',
}
function paymentStageLabel(stage) {
  return PAYMENT_LABELS[stage] ?? stage ?? 'Aucun'
}
function paymentStageSeverity(stage) {
  return { paid_in_full: 'success', deposit_paid: 'info', balance_invoiced: 'warn', deposit_invoiced: 'warn' }[stage] ?? 'secondary'
}

async function load() {
  const { data } = await axios.get(`/api/v1/inventory/production-orders/${props.productionOrderId}/trace`)
  trace.value = data.data
}

onMounted(load)
</script>
