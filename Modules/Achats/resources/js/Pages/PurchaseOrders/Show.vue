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
      <!-- Document lifecycle -->
      <div v-if="!isCancelled && !isRejected" class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <WorkflowStepper :steps="lifecycleSteps" :current-step="purchaseOrder.status" :show-actions="false" :show-details="false" />
      </div>

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

      <!-- Approval Panel -->
      <div v-if="instance" class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Progression de l'approbation</h3>
        <ApprovalPanel :instance="instance" :steps="steps" :decisions="decisions" :can-approve="purchaseOrder.can_approve" @decide="onDecide" />
      </div>

      <!-- Chantier 22 (volet B) — cycle acompte/solde -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Acompte / Solde</h3>

        <div v-if="!purchaseOrder.deposit_invoice_id" class="flex items-center gap-3">
          <input v-model.number="depositPercent" type="number" min="1" max="100" step="1"
                 class="w-24 rounded border border-gray-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
          <span class="text-sm text-surface-600 dark:text-surface-400">% du total</span>
          <button @click="requestDeposit" :disabled="actionPending"
                  class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm disabled:opacity-50">
            Demander un acompte
          </button>
        </div>

        <div v-else class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Acompte ({{ purchaseOrder.deposit_percent }}%)</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">
                {{ purchaseOrder.deposit_invoice?.total }} {{ purchaseOrder.currency }}
                <span :class="['ml-2 px-2 py-0.5 rounded text-xs', purchaseOrder.deposit_invoice?.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800']">
                  {{ purchaseOrder.deposit_invoice?.status === 'paid' ? 'Payé' : 'En attente' }}
                </span>
              </p>
            </div>
            <div v-if="purchaseOrder.deposit_invoice?.status !== 'paid'" class="flex items-center gap-2">
              <input v-model.number="depositPayAmount" type="number" min="0.01" step="0.01"
                     class="w-32 rounded border border-gray-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
              <button @click="payDeposit" :disabled="actionPending"
                      class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm disabled:opacity-50">
                Enregistrer le paiement
              </button>
            </div>
          </div>

          <div v-if="purchaseOrder.deposit_invoice?.status === 'paid' && !purchaseOrder.balance_invoice_id">
            <button @click="requestBalance" :disabled="actionPending"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm disabled:opacity-50">
              Demander le solde
            </button>
          </div>

          <div v-if="purchaseOrder.balance_invoice_id" class="flex items-center justify-between border-t border-gray-200 dark:border-surface-700 pt-4">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Solde</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">
                {{ purchaseOrder.balance_invoice?.total }} {{ purchaseOrder.currency }}
                <span :class="['ml-2 px-2 py-0.5 rounded text-xs', purchaseOrder.balance_invoice?.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800']">
                  {{ purchaseOrder.balance_invoice?.status === 'paid' ? 'Payé' : 'En attente' }}
                </span>
              </p>
            </div>
            <div v-if="purchaseOrder.balance_invoice?.status !== 'paid'" class="flex items-center gap-2">
              <input v-model.number="balancePayAmount" type="number" min="0.01" step="0.01"
                     class="w-32 rounded border border-gray-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
              <button @click="payBalance" :disabled="actionPending"
                      class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm disabled:opacity-50">
                Enregistrer le paiement
              </button>
            </div>
          </div>
        </div>
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
import WorkflowStepper from '@/Components/UI/WorkflowStepper.vue'

const props = defineProps({
  purchaseOrder: { type: Object, required: true },
})

const error = ref('')
const actionPending = ref(false)

// Chantier 22 (volet B) — cycle acompte/solde
const depositPercent = ref(30)
const depositPayAmount = ref(props.purchaseOrder.deposit_required_amount || 0)
const balancePayAmount = ref((props.purchaseOrder.total || 0) - (props.purchaseOrder.deposit_required_amount || 0))

const requestDeposit = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/deposit/request`, { percent: depositPercent.value })
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || "Échec de la demande d'acompte."
  } finally {
    actionPending.value = false
  }
}

const payDeposit = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/deposit/pay`, { amount: depositPayAmount.value })
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || "Échec de l'enregistrement du paiement."
  } finally {
    actionPending.value = false
  }
}

const requestBalance = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/balance/request`)
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || 'Échec de la demande de solde.'
  } finally {
    actionPending.value = false
  }
}

const payBalance = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/balance/pay`, { amount: balancePayAmount.value })
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || "Échec de l'enregistrement du paiement."
  } finally {
    actionPending.value = false
  }
}

const statusClasses = {
  draft: 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-100',
  submitted: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-blue-100 text-blue-800',
  received: 'bg-purple-100 text-purple-800',
  invoiced: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
  rejected: 'bg-red-100 text-red-800',
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
const isCancelled = computed(() => props.purchaseOrder.status === 'cancelled')
const isRejected = computed(() => props.purchaseOrder.status === 'rejected')

const lifecycleSteps = [
  { key: 'draft', label: 'Brouillon', icon: 'pi pi-file' },
  { key: 'submitted', label: 'Soumis', icon: 'pi pi-send' },
  { key: 'approved', label: 'Approuvé', icon: 'pi pi-check' },
  { key: 'received', label: 'Reçu', icon: 'pi pi-box' },
  { key: 'invoiced', label: 'Facturé', icon: 'pi pi-file-invoice' },
]

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

const onDecide = async ({ decision, comment }) => {
  error.value = ''
  try {
    if (decision === 'approved') {
      await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/approve`)
    } else {
      await axios.post(`/api/v1/achats/purchase-orders/${props.purchaseOrder.id}/reject`, { reason: comment || 'Rejeté' })
    }
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || 'Une erreur est survenue.'
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
