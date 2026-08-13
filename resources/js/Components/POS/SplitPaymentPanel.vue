<template>
  <div class="split-payment-panel">
    <!-- Order Summary -->
    <div class="sp-summary">
      <div class="sp-row">
        <span class="sp-label">Total commande</span>
        <span class="sp-amount sp-total">{{ formatCurrency(orderTotal) }}</span>
      </div>
      <div class="sp-row">
        <span class="sp-label">Payé</span>
        <span class="sp-amount sp-paid">{{ formatCurrency(totalPaid) }}</span>
      </div>
      <div class="sp-row sp-remaining-row">
        <span class="sp-label">Reste à payer</span>
        <span class="sp-amount" :class="remaining > 0 ? 'sp-red' : 'sp-green'">
          {{ formatCurrency(remaining) }}
        </span>
      </div>
    </div>

    <!-- Payment Method Buttons -->
    <div class="sp-methods">
      <button
        v-for="method in paymentMethods"
        :key="method.value"
        class="sp-method-btn"
        :class="activeMethod === method.value ? 'sp-method-on' : ''"
        @click="selectMethod(method.value)"
      >
        <i :class="method.icon" style="font-size:16px" />
        <span>{{ method.label }}</span>
      </button>
    </div>

    <!-- Amount Input -->
    <Transition name="slide">
      <div v-if="activeMethod" class="sp-amount-input">
        <label class="sp-input-label">Montant ({{ activeMethodLabel }})</label>
        <div style="display:flex;gap:8px">
          <input
            v-model="amountInput"
            type="number"
            step="0.01"
            :min="0"
            :max="remaining"
            class="sp-input"
            :placeholder="`Max: ${formatCurrency(remaining)}`"
            @keydown.enter="addPayment"
          />
          <button class="btn btn-primary" :disabled="!canAdd" @click="addPayment">
            <i class="pi pi-plus" style="font-size:12px" /> Ajouter
          </button>
        </div>
      </div>
    </Transition>

    <!-- Payment List -->
    <div v-if="payments.length" class="sp-payment-list">
      <div v-for="p in payments" :key="p.id" class="sp-payment-item">
        <div style="display:flex;align-items:center;gap:8px;flex:1">
          <i :class="methodIcon(p.payment_method)" style="font-size:14px;color:var(--fg-3)" />
          <div>
            <p style="margin:0;font-size:13px;font-weight:500;color:var(--fg-1)">{{ methodLabel(p.payment_method) }}</p>
            <p v-if="p.reference" style="margin:0;font-size:11px;color:var(--fg-4)">{{ p.reference }}</p>
          </div>
        </div>
        <span style="font-size:13px;font-weight:600;color:var(--fg-1)">{{ formatCurrency(p.amount) }}</span>
        <button class="sp-remove-btn" :disabled="finalized" @click="removePayment(p.id)">
          <i class="pi pi-times" style="font-size:11px" />
        </button>
      </div>
    </div>

    <!-- Finalize Button -->
    <button
      class="sp-finalize-btn"
      :disabled="remaining > 0.009 || finalized || finalizing"
      @click="finalizeOrder"
    >
      <i v-if="finalizing" class="pi pi-spin pi-spinner" style="font-size:14px" />
      <i v-else class="pi pi-check-circle" style="font-size:14px" />
      {{ finalized ? 'Commande finalisée' : 'Finaliser la commande' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
  orderId: number
  orderTotal: number
}>()

const emit = defineEmits<{
  (e: 'finalized'): void
}>()

interface Payment {
  id: number
  payment_method: string
  amount: number
  reference?: string
}

const payments    = ref<Payment[]>([])
const totalPaid   = ref(0)
const remaining   = ref(props.orderTotal)
const activeMethod = ref<string | null>(null)
const amountInput  = ref<number | null>(null)
const finalized    = ref(false)
const finalizing   = ref(false)

const paymentMethods = [
  { value: 'cash',           label: 'Espèces',       icon: 'pi pi-wallet' },
  { value: 'card',           label: 'Carte',          icon: 'pi pi-credit-card' },
  { value: 'mobile',         label: 'Mobile Money',   icon: 'pi pi-mobile' },
  { value: 'voucher',        label: 'Bon d\'achat',   icon: 'pi pi-ticket' },
  { value: 'loyalty_points', label: 'Points fidélité', icon: 'pi pi-star' },
]

const activeMethodLabel = computed(
  () => paymentMethods.find((m) => m.value === activeMethod.value)?.label ?? '',
)

const canAdd = computed(() => {
  const v = Number(amountInput.value)
  return activeMethod.value && v > 0
})

const csrf = () => (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? ''

const fetchPayments = async () => {
  const res = await fetch(`/api/v1/pos/orders/${props.orderId}/payments`, {
    headers: { Accept: 'application/json' },
  })
  if (res.ok) {
    const data = await res.json()
    payments.value   = data.payments
    totalPaid.value  = data.total_paid
    remaining.value  = data.remaining_amount
  }
}

const selectMethod = (method: string) => {
  activeMethod.value = activeMethod.value === method ? null : method
  amountInput.value  = null
}

const addPayment = async () => {
  if (!canAdd.value || !activeMethod.value) return
  const amount = Number(amountInput.value)

  const res = await fetch(`/api/v1/pos/orders/${props.orderId}/payments`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrf(),
    },
    body: JSON.stringify({ payment_method: activeMethod.value, amount }),
  })

  if (res.ok) {
    await fetchPayments()
    amountInput.value  = null
    activeMethod.value = null
  }
}

const removePayment = async (paymentId: number) => {
  const res = await fetch(`/api/v1/pos/orders/${props.orderId}/payments/${paymentId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
  })
  if (res.status === 204) {
    await fetchPayments()
  }
}

const finalizeOrder = async () => {
  finalizing.value = true
  try {
    const res = await fetch(`/api/v1/pos/orders/${props.orderId}/finalize`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
    })
    if (res.ok) {
      finalized.value = true
      emit('finalized')
    }
  } finally {
    finalizing.value = false
  }
}

const methodIcon  = (m: string) => paymentMethods.find((pm) => pm.value === m)?.icon ?? 'pi pi-credit-card'
const methodLabel = (m: string) => paymentMethods.find((pm) => pm.value === m)?.label ?? m
const formatCurrency = (v: number) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0))

// Initial load
fetchPayments()
</script>

<style scoped>
.split-payment-panel { display: flex; flex-direction: column; gap: 12px; }
.sp-summary { background: var(--bg-sunken); border: 1px solid var(--border-subtle); border-radius: var(--r-md); padding: 12px 16px; display: flex; flex-direction: column; gap: 6px; }
.sp-row { display: flex; justify-content: space-between; align-items: center; }
.sp-remaining-row { border-top: 1px dashed var(--border-subtle); padding-top: 6px; margin-top: 2px; }
.sp-label { font-size: 13px; color: var(--fg-3); }
.sp-amount { font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
.sp-total { color: var(--fg-1); font-size: 16px; font-weight: 700; }
.sp-paid { color: var(--success-fg); }
.sp-red { color: var(--danger-fg); }
.sp-green { color: var(--success-fg); }
.sp-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.sp-method-btn { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px 4px; border-radius: var(--r-md); border: 1px solid var(--border-subtle); background: var(--bg-canvas); color: var(--fg-2); cursor: pointer; font-size: 11px; font-family: var(--font-sans); font-weight: 500; transition: all var(--dur-fast); }
.sp-method-btn:hover { border-color: var(--halo-300); color: var(--halo-700); }
.sp-method-on { background: var(--halo-50); border-color: var(--halo-400); color: var(--halo-700); }
.sp-amount-input { display: flex; flex-direction: column; gap: 6px; padding: 10px; background: var(--bg-sunken); border-radius: var(--r-md); border: 1px solid var(--border-subtle); }
.sp-input-label { font-size: 12px; font-weight: 500; color: var(--fg-2); }
.sp-input { flex: 1; padding: 8px 12px; border-radius: var(--r-md); border: 1px solid var(--border-subtle); background: var(--bg-canvas); font-family: var(--font-sans); font-size: 14px; color: var(--fg-1); outline: none; }
.sp-input:focus { border-color: var(--halo-500); box-shadow: 0 0 0 3px rgba(46,91,232,0.12); }
.sp-payment-list { display: flex; flex-direction: column; gap: 4px; }
.sp-payment-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--r-md); }
.sp-remove-btn { width: 24px; height: 24px; border-radius: var(--r-sm); border: none; background: none; color: var(--danger-fg); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.sp-remove-btn:hover:not(:disabled) { background: var(--danger-bg); }
.sp-remove-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.sp-finalize-btn { width: 100%; padding: 11px; background: var(--success-fg, var(--green-500)); color: #fff; border: none; border-radius: var(--r-md); font-family: var(--font-sans); font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background var(--dur-base); }
.sp-finalize-btn:hover:not(:disabled) { filter: brightness(1.1); }
.sp-finalize-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn { font-family: var(--font-sans); font-weight: 500; font-size: 14px; padding: 8px 14px; border-radius: var(--r-md); border: 1px solid transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background var(--dur-base); line-height: 1.2; }
.btn-primary { background: var(--halo-500); color: #fff; }
.btn-primary:hover:not(:disabled) { background: var(--halo-700); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.slide-enter-active, .slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
