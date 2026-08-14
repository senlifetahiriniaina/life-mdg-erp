<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Bon de commande</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">{{ purchaseOrder.po_number }}</p>
      </div>
      <Link href="/purchase-orders" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Retour aux bons de commande
      </Link>
    </div>

    <div class="space-y-6">
      <!-- Status Bar -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Statut actuel</h2>
            <span :class="['px-4 py-2 rounded-lg text-sm font-medium', statusClasses[purchaseOrder.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100']">
              {{ formatStatus(purchaseOrder.status) }}
            </span>
          </div>
          <div class="flex gap-2">
            <button
              v-if="purchaseOrder.status === 'draft'"
              @click="submitForApproval"
              :disabled="actionPending"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm disabled:opacity-50"
            >
              Soumettre pour approbation
            </button>
            <button
              v-if="purchaseOrder.status === 'submitted' && purchaseOrder.can_approve"
              @click="approvePO"
              :disabled="actionPending"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm disabled:opacity-50"
            >
              Approuver
            </button>
            <button
              v-if="purchaseOrder.status === 'draft'"
              @click="deletePO"
              :disabled="actionPending"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm disabled:opacity-50"
            >
              Supprimer
            </button>
          </div>
        </div>
      </div>

      <!-- Approval Panel — visualization only; the real decision goes through
           the button above (Achats has no reject action yet, only approve/
           cancel, so ApprovalPanel's built-in per-step form isn't a fit). -->
      <div v-if="instance" class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Progression de l'approbation</h3>
        <ApprovalPanel :instance="instance" :steps="steps" :decisions="decisions" :can-approve="false" />
      </div>

      <!-- Order Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Informations de commande</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Numéro</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ purchaseOrder.po_number }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Date de commande</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ formatDate(purchaseOrder.order_date) }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Date de livraison</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ purchaseOrder.delivery_date ? formatDate(purchaseOrder.delivery_date) : 'Non spécifiée' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Devise</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ purchaseOrder.currency }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Fournisseur</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Nom</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ purchaseOrder.supplier?.name || 'Inconnu' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Email</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ purchaseOrder.supplier?.email || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Téléphone</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ purchaseOrder.supplier?.phone || 'N/A' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Lignes</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
              <tr>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300">Description</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Quantité</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Unité</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-24">Prix unitaire</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-16">TVA %</th>
                <th scope="col" class="px-4 py-3 text-right font-medium text-surface-700 dark:text-surface-300 w-24">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in purchaseOrder.lines" :key="line.id" class="border-b border-gray-200 dark:border-surface-700">
                <td class="px-4 py-3">{{ line.description }}</td>
                <td class="px-4 py-3">{{ line.quantity }}</td>
                <td class="px-4 py-3">{{ line.unit }}</td>
                <td class="px-4 py-3">{{ line.unit_price }}</td>
                <td class="px-4 py-3">{{ line.tax_rate }}%</td>
                <td class="px-4 py-3 text-right font-medium">{{ line.line_total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Totals -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex justify-end max-w-md ml-auto space-y-2">
          <div class="flex justify-between w-full text-sm">
            <span class="text-surface-600 dark:text-surface-400">Sous-total :</span>
            <span class="font-medium">{{ subtotal }}</span>
          </div>
          <div class="flex justify-between w-full text-sm">
            <span class="text-surface-600 dark:text-surface-400">TVA :</span>
            <span class="font-medium">{{ taxAmount }}</span>
          </div>
          <div class="flex justify-between w-full text-sm">
            <span class="text-surface-600 dark:text-surface-400">Livraison :</span>
            <span class="font-medium">{{ purchaseOrder.shipping_cost || 0 }}</span>
          </div>
          <div class="border-t border-gray-200 dark:border-surface-700 pt-2 flex justify-between w-full font-semibold text-lg">
            <span>Total :</span>
            <span>{{ purchaseOrder.total }}</span>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="purchaseOrder.notes" class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Notes</h3>
        <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ purchaseOrder.notes }}</p>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-700 dark:text-red-300 mt-4">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import ApprovalPanel from '@/Components/UI/ApprovalPanel.vue'

const props = defineProps({
  purchaseOrder: { type: Object, required: true },
})

const error = ref('')
const actionPending = ref(false)

const statusClasses = {
  draft: 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-100',
  submitted: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-blue-100 text-blue-800',
  received: 'bg-purple-100 text-purple-800',
  invoiced: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
}

const formatStatus = (status) => status ? status.charAt(0).toUpperCase() + status.slice(1) : '—'

const formatDate = (date) => date
  ? new Date(date).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const subtotal = computed(() => (props.purchaseOrder.lines || []).reduce((sum, line) => sum + (Number(line.line_total) || 0), 0))
const taxAmount = computed(() => (props.purchaseOrder.lines || []).reduce((sum, line) => {
  return sum + (Number(line.line_total) || 0) * ((Number(line.tax_rate) || 0) / 100)
}, 0))

// approval is a MorphOne — the single ApprovalRequest this PO's submission
// created. hierarchy.levels (routed via ApprovalRoutingResolver) gives the
// real per-level titles when present.
const approval = computed(() => props.purchaseOrder.approval)

const steps = computed(() => {
  const levels = approval.value?.hierarchy?.levels
  if (levels?.length) {
    return levels.map(l => ({ order: l.level_order, label: l.title, approver_type: 'role', approver_value: l.title }))
  }
  const total = approval.value?.total_levels || 1
  return Array.from({ length: total }, (_, i) => ({ order: i + 1, label: `Niveau ${i + 1}`, approver_type: 'role', approver_value: '—' }))
})

const decisions = computed(() => (approval.value?.actions || []).map((a, idx) => ({
  step_order: idx + 1,
  approver_name: a.approver?.name,
  decision: a.action,
  comment: a.comment,
  decided_at: a.acted_at,
})))

const instance = computed(() => {
  if (!approval.value) return null
  return {
    id: approval.value.id,
    current_step: props.purchaseOrder.status === 'approved' ? steps.value.length + 1 : (approval.value.current_level || 1),
    status: approval.value.status,
    initiated_by: approval.value.requested_by,
    decisions: decisions.value,
  }
})

const submitForApproval = async () => {
  if (!confirm('Soumettre ce bon de commande pour approbation ?')) return
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/submit`)
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || 'Échec de la soumission.'
  } finally {
    actionPending.value = false
  }
}

const approvePO = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/approve`)
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || "Échec de l'approbation."
  } finally {
    actionPending.value = false
  }
}

const deletePO = async () => {
  if (!confirm('Voulez-vous vraiment supprimer ce bon de commande ?')) return
  actionPending.value = true
  error.value = ''
  try {
    await axios.delete(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}`)
    router.visit('/purchase-orders')
  } catch (err) {
    error.value = err.response?.data?.message || 'Échec de la suppression.'
    actionPending.value = false
  }
}
</script>
