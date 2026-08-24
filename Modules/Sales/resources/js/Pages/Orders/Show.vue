<template>
  <AppLayout>
    <Head title="Commande de vente" />

    <div class="max-w-6xl mx-auto space-y-6" v-if="order">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Commande {{ order.reference }}</h1>
          <p class="text-surface-500 text-sm mt-1">{{ order.status }}</p>
        </div>
        <Link href="/sales" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
          ← Retour aux commandes
        </Link>
      </div>

      <!-- Lines -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Lignes</h3>
        <table class="w-full text-sm">
          <thead class="border-b border-surface-200 dark:border-surface-700">
            <tr>
              <th class="px-2 py-2 text-left">Description</th>
              <th class="px-2 py-2 text-right">Qté</th>
              <th class="px-2 py-2 text-right">Prix unitaire</th>
              <th class="px-2 py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in order.lines" :key="line.id" class="border-b border-surface-100 dark:border-surface-700">
              <td class="px-2 py-2">{{ line.description }}</td>
              <td class="px-2 py-2 text-right">{{ line.quantity }}</td>
              <td class="px-2 py-2 text-right">{{ line.unit_price }}</td>
              <td class="px-2 py-2 text-right font-medium">{{ line.line_total }}</td>
            </tr>
          </tbody>
        </table>
        <div class="flex justify-end mt-4 text-lg font-semibold">
          Total : {{ order.total }} {{ order.currency }}
        </div>
      </div>

      <!-- Chantier 22 (volet B) — cycle acompte/solde -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Acompte / Solde</h3>

        <div v-if="!order.deposit_invoice_id" class="flex items-center gap-3">
          <input v-model.number="depositPercent" type="number" min="1" max="100" step="1"
                 class="w-24 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
          <span class="text-sm text-surface-500">% du total</span>
          <Button label="Demander un acompte" :disabled="actionPending" @click="requestDeposit" />
        </div>

        <div v-else class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-surface-500">Acompte ({{ order.deposit_percent }}%)</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">
                {{ order.deposit_invoice?.total }} {{ order.currency }}
                <Tag :value="order.deposit_invoice?.status === 'paid' ? 'Payé' : 'En attente'"
                     :severity="order.deposit_invoice?.status === 'paid' ? 'success' : 'warn'" class="ml-2" />
              </p>
            </div>
            <div v-if="order.deposit_invoice?.status !== 'paid'" class="flex items-center gap-2">
              <input v-model.number="depositPayAmount" type="number" min="0.01" step="0.01"
                     class="w-32 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
              <Button label="Enregistrer le paiement" severity="success" :disabled="actionPending" @click="payDeposit" />
            </div>
          </div>

          <div v-if="order.deposit_invoice?.status === 'paid' && !order.balance_invoice_id">
            <Button label="Demander le solde" :disabled="actionPending" @click="requestBalance" />
          </div>

          <div v-if="order.balance_invoice_id" class="flex items-center justify-between border-t border-surface-200 dark:border-surface-700 pt-4">
            <div>
              <p class="text-sm text-surface-500">Solde</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">
                {{ order.balance_invoice?.total }} {{ order.currency }}
                <Tag :value="order.balance_invoice?.status === 'paid' ? 'Payé' : 'En attente'"
                     :severity="order.balance_invoice?.status === 'paid' ? 'success' : 'warn'" class="ml-2" />
              </p>
            </div>
            <div v-if="order.balance_invoice?.status !== 'paid'" class="flex items-center gap-2">
              <input v-model.number="balancePayAmount" type="number" min="0.01" step="0.01"
                     class="w-32 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
              <Button label="Enregistrer le paiement" severity="success" :disabled="actionPending" @click="payBalance" />
            </div>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-600 mt-4">{{ error }}</p>
      </div>

      <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
    </div>

    <div v-else class="text-center py-16 text-surface-400">Chargement…</div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const props = defineProps<{ orderId: number }>()

// Chantier 32.16 (Sales deep 14-layer audit): this page never called
// useAiAssistant() at all — confirmed via grep that only SalesIndex.vue did
// among the module's 4 real Vue pages, the same "N of M pages never wired"
// pattern already found and fixed for Strategy (Chantier 30) and
// Validation (Chantier 32.7). showAiPanel starts true here (unlike
// SalesIndex.vue's toggle-only button) since this deposit/balance workflow
// is exactly the kind of multi-step process AI Assisted First is meant to
// support.
const { guidance } = useAiAssistant('Sales', 'manage_deposit_balance')
const showAiPanel = ref(true)

const order = ref<any>(null)
const error = ref('')
const actionPending = ref(false)
const depositPercent = ref(30)
const depositPayAmount = ref(0)
const balancePayAmount = ref(0)

const loadOrder = async () => {
  const { data } = await axios.get(`/api/v1/sales/orders/${props.orderId}`)
  order.value = data
  depositPayAmount.value = Number(data.deposit_required_amount || 0)
  balancePayAmount.value = Number(data.total || 0) - Number(data.deposit_required_amount || 0)
}

const requestDeposit = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/sales/orders/${props.orderId}/deposit/request`, { percent: depositPercent.value })
    await loadOrder()
  } catch (err: any) {
    error.value = err.response?.data?.message || "Échec de la demande d'acompte."
  } finally {
    actionPending.value = false
  }
}

const payDeposit = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/sales/orders/${props.orderId}/deposit/pay`, { amount: depositPayAmount.value })
    await loadOrder()
  } catch (err: any) {
    error.value = err.response?.data?.message || "Échec de l'enregistrement du paiement."
  } finally {
    actionPending.value = false
  }
}

const requestBalance = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/sales/orders/${props.orderId}/balance/request`)
    await loadOrder()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Échec de la demande de solde.'
  } finally {
    actionPending.value = false
  }
}

const payBalance = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/sales/orders/${props.orderId}/balance/pay`, { amount: balancePayAmount.value })
    await loadOrder()
  } catch (err: any) {
    error.value = err.response?.data?.message || "Échec de l'enregistrement du paiement."
  } finally {
    actionPending.value = false
  }
}

onMounted(loadOrder)
</script>
