import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const usePosStore = defineStore('pos', () => {
  // ─── State ────────────────────────────────────────────────────────────────
  const cart           = ref([])
  const activeSession  = ref(null)
  const activeTable    = ref(null)
  const paymentMethod  = ref('cash')
  const discountAmount = ref(0)
  const discountType   = ref('fixed') // 'fixed' | 'percent'
  const couponCode     = ref('')
  const taxRate        = ref(0)

  // ─── Computed ─────────────────────────────────────────────────────────────
  const cartCount = computed(() => cart.value.reduce((s, i) => s + i.qty, 0))

  const subtotal = computed(() =>
    cart.value.reduce((s, i) => s + i.price * i.qty, 0)
  )

  const discountValue = computed(() => {
    if (!discountAmount.value) return 0
    if (discountType.value === 'percent') {
      return subtotal.value * (discountAmount.value / 100)
    }
    return Math.min(discountAmount.value, subtotal.value)
  })

  const taxableAmount = computed(() => subtotal.value - discountValue.value)

  const taxAmount = computed(() =>
    taxableAmount.value * (taxRate.value / 100)
  )

  const total = computed(() => taxableAmount.value + taxAmount.value)

  const hasSession = computed(() => !!activeSession.value)

  // ─── Cart Actions ─────────────────────────────────────────────────────────
  function addItem(product) {
    const existing = cart.value.find((i) => i.id === product.id)
    if (existing) {
      existing.qty++
    } else {
      cart.value.push({
        id:    product.id,
        name:  product.name,
        price: Number(product.sales_price ?? product.price ?? 0),
        qty:   1,
        note:  '',
      })
    }
  }

  function setQty(idx, qty) {
    if (qty <= 0) {
      cart.value.splice(idx, 1)
    } else {
      cart.value[idx].qty = qty
    }
  }

  function changeQty(idx, delta) {
    const newQty = (cart.value[idx]?.qty ?? 0) + delta
    setQty(idx, newQty)
  }

  function removeItem(idx) {
    cart.value.splice(idx, 1)
  }

  function clearCart() {
    cart.value       = []
    discountAmount.value = 0
    couponCode.value = ''
    paymentMethod.value  = 'cash'
  }

  function applyDiscount(amount, type = 'fixed') {
    discountAmount.value = Number(amount)
    discountType.value   = type
  }

  function setNote(idx, note) {
    if (cart.value[idx]) cart.value[idx].note = note
  }

  // ─── Session Actions ───────────────────────────────────────────────────────
  async function openSession(payload = {}) {
    const { data } = await axios.post('/api/v1/pos/sessions', {
      opening_balance: payload.openingBalance ?? 0,
      config_id:       payload.configId ?? null,
    })
    activeSession.value = data
    taxRate.value = data.config?.tax_rate ?? 0
    return data
  }

  async function closeSession(closingBalance = null) {
    if (!activeSession.value) return
    const { data } = await axios.post(
      `/api/v1/pos/sessions/${activeSession.value.id}/close`,
      { closing_balance: closingBalance ?? total.value }
    )
    activeSession.value = null
    clearCart()
    return data
  }

  function setSession(session) {
    activeSession.value = session
    taxRate.value = session?.config?.tax_rate ?? 0
  }

  // ─── Table ────────────────────────────────────────────────────────────────
  function selectTable(table) {
    activeTable.value = table
  }

  function clearTable() {
    activeTable.value = null
  }

  // ─── Order ────────────────────────────────────────────────────────────────
  async function placeOrder() {
    if (!cart.value.length || !activeSession.value) {
      throw new Error('Panier vide ou session inactive')
    }
    const { data } = await axios.post('/api/v1/pos/orders', {
      session_id:     activeSession.value.id,
      payment_method: paymentMethod.value,
      table_id:       activeTable.value?.id ?? null,
      subtotal:       subtotal.value,
      discount:       discountValue.value,
      tax_amount:     taxAmount.value,
      total:          total.value,
      lines: cart.value.map((i) => ({
        product_id:   i.id,
        product_name: i.name,
        qty:          i.qty,
        unit_price:   i.price,
        subtotal:     i.qty * i.price,
        note:         i.note,
      })),
    })
    clearCart()
    return data
  }

  return {
    // state
    cart, activeSession, activeTable, paymentMethod,
    discountAmount, discountType, couponCode, taxRate,
    // computed
    cartCount, subtotal, discountValue, taxableAmount, taxAmount, total, hasSession,
    // actions
    addItem, setQty, changeQty, removeItem, clearCart, applyDiscount, setNote,
    openSession, closeSession, setSession,
    selectTable, clearTable,
    placeOrder,
  }
})
