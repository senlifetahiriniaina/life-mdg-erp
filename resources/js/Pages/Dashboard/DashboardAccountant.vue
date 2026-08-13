<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Unpaid Invoices Total -->
      <Card class="bg-red-50 dark:bg-red-900/20">
        <template #title>Factures Impayées</template>
        <div class="text-3xl font-bold text-red-700 dark:text-red-300">{{ formatCurrency(metrics.unpaid_invoices_total) }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">{{ metrics.unpaid_invoices_count }} factures</p>
      </Card>

      <!-- Overdue Invoices -->
      <Card class="bg-amber-50 dark:bg-amber-900/20">
        <template #title>Factures en Retard</template>
        <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ metrics.overdue_invoices }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">À relancer immédiatement</p>
      </Card>

      <!-- Expense Reports Pending -->
      <Card class="bg-primary-50 dark:bg-primary-900/20">
        <template #title>Notes de Frais en Attente</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.expense_reports_pending }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">À valider</p>
      </Card>

      <!-- VAT Due -->
      <Card class="bg-violet-50 dark:bg-violet-900/20">
        <template #title>TVA à Déclarer</template>
        <div class="text-3xl font-bold text-violet-700 dark:text-violet-300">{{ formatCurrency(metrics.vat_due_amount) }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Montant dû</p>
      </Card>
    </div>

    <!-- AI-Generated Balance Sheet Summary -->
    <Card class="bg-white dark:bg-surface-800 border-l-4 border-green-500">
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-sparkles text-amber-500"></i>
          Résumé du Bilan (par IA)
        </div>
      </template>
      <div class="prose prose-sm max-w-none">
        <p class="text-surface-700 dark:text-surface-300">Le bilan du mois dernier montre une amélioration de <strong class="text-green-700 dark:text-green-300">+12% de trésorerie</strong>. L'actif courant a augmenté de 8%, principalement dû aux encaissements de mars. Les dettes à court terme ont diminué de 3%. Ratio de liquidité: <strong>1.8x</strong> (sain). À noter: une nouvelle facture fournisseur de 15 000€ en attente de règlement.</p>
      </div>
      <Button label="Voir le Bilan Complet" icon="pi pi-arrow-right" class="mt-4" />
    </Card>

    <!-- Bank Reconciliation Status -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>Réconciliation Bancaire</template>
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-surface-900 dark:text-surface-50">Transactions en attente</span>
          <Tag :value="`${metrics.bank_reconciliation_pending} transactions`" severity="warning" />
        </div>
        <div class="flex items-center justify-between">
          <span class="text-surface-900 dark:text-surface-50">Discrepancies détectées</span>
          <Tag value="0" severity="success" />
        </div>
        <Button label="Réconcilier Maintenant" icon="pi pi-check" class="w-full mt-4" />
      </div>
    </Card>

    <!-- To-Do Today -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>À Faire Aujourd'hui</template>
      <Divider />
      <div class="space-y-2">
        <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:bg-surface-800 rounded">
          <Checkbox v-model="todos[0]" :binary="true" />
          <span>Valider {{ metrics.expense_reports_pending }} notes de frais</span>
        </div>
        <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:bg-surface-800 rounded">
          <Checkbox v-model="todos[1]" :binary="true" />
          <span>Relancer {{ metrics.overdue_invoices }} factures en retard</span>
        </div>
        <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:bg-surface-800 rounded">
          <Checkbox v-model="todos[2]" :binary="true" />
          <span>Réconcilier les {{ metrics.bank_reconciliation_pending }} transactions</span>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Checkbox from 'primevue/checkbox'
import Divider from 'primevue/divider'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
})

const todos = ref([false, false, false])

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(value || 0)
}
</script>
