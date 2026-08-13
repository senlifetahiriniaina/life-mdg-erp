<template>
  <AppLayout>
    <Head title="Checkout" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Ecommerce · Checkout</h1>
        <p class="wh-page-subtitle">Integrated checkout flow</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">
      <!-- Main checkout steps -->
      <div>
        <!-- Step tabs -->
        <div class="wh-panel" style="margin-bottom:16px;padding:0">
          <div style="display:flex;border-bottom:1px solid var(--border-subtle)">
            <button
              v-for="(step, idx) in steps"
              :key="step.key"
              class="checkout-tab"
              :class="{ active: currentStep === idx, done: idx < currentStep }"
              :disabled="idx > currentStep"
              @click="currentStep = idx"
            >
              <span class="checkout-tab-num">{{ idx + 1 }}</span>
              {{ step.label }}
            </button>
          </div>
        </div>

        <!-- Step: Cart review -->
        <div v-if="currentStep === 0" class="wh-panel">
          <h2 style="font-size:16px;font-weight:600;margin-bottom:16px">Review your cart</h2>

          <div v-if="cart.items.length === 0" style="text-align:center;color:var(--fg-3);padding:32px">
            Your cart is empty.
          </div>

          <div v-else>
            <!-- Multi-vendor breakdown (shown when 2+ vendors) -->
            <div v-if="vendorSplit && vendorSplit.vendor_count > 1" style="background:var(--bg-2);border-radius:8px;padding:12px 16px;margin-bottom:16px;border:1px solid var(--border-subtle)">
              <div style="font-weight:600;font-size:13px;margin-bottom:8px;color:var(--fg-1)">
                Vendeurs dans votre panier ({{ vendorSplit.vendor_count }})
              </div>
              <div
                v-for="group in vendorSplit.groups"
                :key="group.vendor_id ?? 'direct'"
                style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border-subtle)"
              >
                <div>
                  <span style="font-weight:500;font-size:13px">{{ group.vendor_name }}</span>
                  <span style="font-size:12px;color:var(--fg-3);margin-left:8px">{{ group.items.length }} article(s)</span>
                </div>
                <div style="font-weight:600;font-size:13px">{{ formatCurrency(group.subtotal) }}</div>
              </div>
            </div>

            <div
              v-for="item in cart.items"
              :key="item.id"
              style="display:flex;align-items:center;gap:16px;padding:12px 0;border-bottom:1px solid var(--border-subtle)"
            >
              <div style="width:56px;height:56px;background:var(--bg-2);border-radius:8px;flex-shrink:0" />
              <div style="flex:1">
                <div style="font-weight:500;color:var(--fg-1)">{{ item.product_name }}</div>
                <div style="font-size:13px;color:var(--fg-3)">SKU: {{ item.sku ?? '—' }}</div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <button class="wh-btn-ghost wh-btn-sm" @click="updateQty(item, item.quantity - 1)">−</button>
                <span style="min-width:24px;text-align:center">{{ item.quantity }}</span>
                <button class="wh-btn-ghost wh-btn-sm" @click="updateQty(item, item.quantity + 1)">+</button>
              </div>
              <div style="min-width:80px;text-align:right;font-weight:500">
                {{ formatCurrency(item.unit_price * item.quantity) }}
              </div>
              <button class="wh-btn-ghost wh-btn-sm" style="color:var(--danger)" @click="removeItem(item)">✕</button>
            </div>

            <!-- Coupon -->
            <div style="margin-top:16px;display:flex;gap:8px">
              <input
                v-model="couponCode"
                type="text"
                class="wh-input"
                placeholder="Coupon code"
                style="flex:1"
                @keydown.enter="applyCoupon"
              />
              <button class="wh-btn wh-btn-secondary" :disabled="applying" @click="applyCoupon">
                Apply
              </button>
            </div>
            <div v-if="couponMessage" :style="{ color: couponValid ? 'var(--success)' : 'var(--danger)', fontSize: '13px', marginTop: '6px' }">
              {{ couponMessage }}
            </div>
          </div>

          <div style="margin-top:24px;text-align:right">
            <button class="wh-btn wh-btn-primary" :disabled="cart.items.length === 0" @click="currentStep = 1">
              Continue to Shipping
            </button>
          </div>
        </div>

        <!-- Step: Shipping address -->
        <div v-if="currentStep === 1" class="wh-panel">
          <h2 style="font-size:16px;font-weight:600;margin-bottom:16px">Shipping address</h2>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">First name</label>
              <input v-model="shipping.first_name" type="text" class="wh-input" />
            </div>
            <div>
              <label class="wh-label">Last name</label>
              <input v-model="shipping.last_name" type="text" class="wh-input" />
            </div>
            <div style="grid-column:1/-1">
              <label class="wh-label">Address line 1</label>
              <input v-model="shipping.line1" type="text" class="wh-input" />
            </div>
            <div style="grid-column:1/-1">
              <label class="wh-label">Address line 2</label>
              <input v-model="shipping.line2" type="text" class="wh-input" />
            </div>
            <div>
              <label class="wh-label">City</label>
              <input v-model="shipping.city" type="text" class="wh-input" />
            </div>
            <div>
              <label class="wh-label">Postal code</label>
              <input v-model="shipping.postal_code" type="text" class="wh-input" />
            </div>
            <div>
              <label class="wh-label">Country (2-letter code)</label>
              <input v-model="shipping.country" type="text" class="wh-input" maxlength="2" />
            </div>
            <div>
              <label class="wh-label">Phone</label>
              <input v-model="shipping.phone" type="text" class="wh-input" />
            </div>
          </div>

          <!-- Shipping methods -->
          <h3 style="font-size:14px;font-weight:600;margin:20px 0 12px">Select shipping method</h3>
          <div style="display:flex;flex-direction:column;gap:8px">
            <label
              v-for="method in shippingMethods"
              :key="method.id"
              class="wh-radio-card"
              :class="{ selected: selectedMethod?.id === method.id }"
            >
              <input v-model="selectedMethod" :value="method" type="radio" style="display:none" />
              <div style="flex:1">
                <div style="font-weight:500">{{ method.name }}</div>
                <div style="font-size:13px;color:var(--fg-3)">{{ method.carrier }} · {{ method.estimated_days_min }}–{{ method.estimated_days_max }} days</div>
              </div>
              <div style="font-weight:600">{{ formatCurrency(method.cost ?? method.base_cost) }}</div>
            </label>
            <div v-if="shippingMethods.length === 0" style="color:var(--fg-3);font-size:13px">
              Enter a country code above to see shipping options.
            </div>
          </div>

          <div style="margin-top:24px;display:flex;gap:12px;justify-content:space-between">
            <button class="wh-btn wh-btn-ghost" @click="currentStep = 0">Back</button>
            <button class="wh-btn wh-btn-primary" @click="currentStep = 2">Continue to Payment</button>
          </div>
        </div>

        <!-- Step: Payment -->
        <div v-if="currentStep === 2" class="wh-panel">
          <h2 style="font-size:16px;font-weight:600;margin-bottom:16px">Payment</h2>

          <div style="display:flex;gap:8px;margin-bottom:20px">
            <button
              v-for="pm in paymentMethods"
              :key="pm.key"
              class="wh-btn"
              :class="selectedPayment === pm.key ? 'wh-btn-primary' : 'wh-btn-ghost'"
              @click="selectedPayment = pm.key"
            >
              {{ pm.label }}
            </button>
          </div>

          <!-- Card -->
          <div v-if="selectedPayment === 'stripe'" style="display:flex;flex-direction:column;gap:12px">
            <div>
              <label class="wh-label">Card number</label>
              <input type="text" class="wh-input" placeholder="1234 5678 9012 3456" maxlength="19" />
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="wh-label">Expiry</label>
                <input type="text" class="wh-input" placeholder="MM/YY" />
              </div>
              <div>
                <label class="wh-label">CVC</label>
                <input type="text" class="wh-input" placeholder="123" maxlength="4" />
              </div>
            </div>
          </div>

          <!-- PayPal -->
          <div v-else-if="selectedPayment === 'paypal'" style="text-align:center;padding:24px">
            <div style="background:#ffc439;color:#000;font-weight:700;padding:12px 24px;border-radius:4px;display:inline-block;cursor:pointer">
              Pay with PayPal
            </div>
          </div>

          <!-- Bank transfer -->
          <div v-else-if="selectedPayment === 'bank_transfer'" style="background:var(--bg-2);border-radius:8px;padding:16px">
            <p style="margin:0;color:var(--fg-2)">
              Transfer to: <strong>IBAN FR76 3000 1007 1700 0123 4567 890</strong><br />
              Reference: your order number will be provided after confirmation.
            </p>
          </div>

          <!-- COD -->
          <div v-else-if="selectedPayment === 'cod'" style="background:var(--bg-2);border-radius:8px;padding:16px">
            <p style="margin:0;color:var(--fg-2)">Pay in cash upon delivery. No payment needed now.</p>
          </div>

          <div style="margin-top:24px;display:flex;gap:12px;justify-content:space-between">
            <button class="wh-btn wh-btn-ghost" @click="currentStep = 1">Back</button>
            <button class="wh-btn wh-btn-primary" :disabled="placing" @click="placeOrder">
              {{ placing ? 'Placing order…' : 'Place Order' }}
            </button>
          </div>
        </div>

        <!-- Step: Confirmation -->
        <div v-if="currentStep === 3" class="wh-panel" style="text-align:center;padding:48px 24px">
          <div style="font-size:48px;margin-bottom:16px">✓</div>
          <h2 style="font-size:20px;font-weight:700;color:var(--fg-1);margin-bottom:8px">Order placed!</h2>
          <p style="color:var(--fg-3);margin-bottom:24px">
            Your order <strong>{{ confirmedOrder?.order_number }}</strong> has been placed successfully.
          </p>
          <a href="/ecommerce/orders" class="wh-btn wh-btn-primary">View Orders</a>
        </div>
      </div>

      <!-- Cart summary sidebar -->
      <div style="position:sticky;top:24px">
        <div class="wh-panel">
          <h3 style="font-size:14px;font-weight:600;margin-bottom:12px">Order Summary</h3>

          <div v-for="item in cart.items" :key="item.id" style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
            <span style="color:var(--fg-2)">{{ item.product_name }} × {{ item.quantity }}</span>
            <span>{{ formatCurrency(item.unit_price * item.quantity) }}</span>
          </div>

          <div style="border-top:1px solid var(--border-subtle);margin:12px 0;padding-top:12px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
              <span style="color:var(--fg-3)">Subtotal</span>
              <span>{{ formatCurrency(subtotal) }}</span>
            </div>
            <div v-if="cart.discount_amount > 0" style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;color:var(--success)">
              <span>Discount</span>
              <span>−{{ formatCurrency(cart.discount_amount) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
              <span style="color:var(--fg-3)">Shipping</span>
              <span>{{ selectedMethod ? formatCurrency(selectedMethod.cost ?? selectedMethod.base_cost) : '—' }}</span>
            </div>
          </div>

          <div style="display:flex;justify-content:space-between;font-weight:700;font-size:15px">
            <span>Total</span>
            <span>{{ formatCurrency(total) }}</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface CartItem {
  id: number
  product_name: string
  sku: string | null
  quantity: number
  unit_price: number
}

interface Cart {
  items: CartItem[]
  discount_amount: number
  coupon_code: string | null
}

interface VendorGroup {
  vendor_id: number | null
  vendor_name: string
  items: CartItem[]
  subtotal: number
}

interface VendorSplit {
  vendor_count: number
  groups: VendorGroup[]
  total: number
}

interface ShippingMethod {
  id: number
  name: string
  carrier: string
  base_cost: number
  cost?: number
  estimated_days_min: number
  estimated_days_max: number
}

const cart        = ref<Cart>({ items: [], discount_amount: 0, coupon_code: null })
const vendorSplit = ref<VendorSplit | null>(null)

async function loadVendorSplit(): Promise<void> {
  if (cart.value.items.length === 0) {
    vendorSplit.value = null
    return
  }
  try {
    const { data } = await axios.post('/api/v1/ecommerce/checkout/multi-vendor', {
      items: cart.value.items.map(i => ({
        product_id: i.id,
        quantity:   i.quantity,
        unit_price: i.unit_price,
      })),
    })
    vendorSplit.value = data
  } catch {
    vendorSplit.value = null
  }
}

watch(() => cart.value.items.length, loadVendorSplit)
const currentStep = ref(0)
const couponCode = ref('')
const couponMessage = ref('')
const couponValid = ref(false)
const applying = ref(false)
const placing = ref(false)
const confirmedOrder = ref<{ order_number: string } | null>(null)
const selectedMethod = ref<ShippingMethod | null>(null)
const selectedPayment = ref<string>('stripe')
const shippingMethods = ref<ShippingMethod[]>([])

const shipping = ref({
  first_name: '', last_name: '', line1: '', line2: '',
  city: '', postal_code: '', country: '', phone: '',
})

const steps = [
  { key: 'cart', label: 'Cart' },
  { key: 'shipping', label: 'Shipping' },
  { key: 'payment', label: 'Payment' },
  { key: 'confirmation', label: 'Confirmation' },
]

const paymentMethods = [
  { key: 'stripe', label: 'Credit Card' },
  { key: 'paypal', label: 'PayPal' },
  { key: 'bank_transfer', label: 'Bank Transfer' },
  { key: 'cod', label: 'Cash on Delivery' },
]

const subtotal = computed(() =>
  cart.value.items.reduce((sum, item) => sum + item.unit_price * item.quantity, 0)
)

const total = computed(() => {
  const shippingCost = selectedMethod.value?.cost ?? selectedMethod.value?.base_cost ?? 0
  return Math.max(0, subtotal.value - cart.value.discount_amount + shippingCost)
})

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value)
}

function updateQty(item: CartItem, qty: number): void {
  if (qty <= 0) {
    removeItem(item)
    return
  }
  item.quantity = qty
}

function removeItem(item: CartItem): void {
  cart.value.items = cart.value.items.filter(i => i.id !== item.id)
}

async function applyCoupon(): Promise<void> {
  applying.value = true
  couponMessage.value = ''
  // Mock validation — in production, call POST /api/v1/ecommerce/cart/{cart}/coupon
  setTimeout(() => {
    if (couponCode.value.toUpperCase() === 'SAVE10') {
      cart.value.discount_amount = subtotal.value * 0.1
      couponValid.value = true
      couponMessage.value = 'Coupon applied! 10% off.'
    } else {
      couponValid.value = false
      couponMessage.value = 'Invalid coupon code.'
    }
    applying.value = false
  }, 400)
}

async function placeOrder(): Promise<void> {
  placing.value = true
  // Mock order creation — in production, call checkout API
  setTimeout(() => {
    confirmedOrder.value = { order_number: 'ORD-' + Math.random().toString(36).slice(2, 10).toUpperCase() }
    currentStep.value = 3
    placing.value = false
  }, 1000)
}
</script>

<style scoped>
.checkout-tab {
  flex: 1;
  padding: 14px 16px;
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--fg-3);
  transition: color .15s;
}

.checkout-tab:disabled { cursor: not-allowed; }
.checkout-tab.active { color: var(--primary); font-weight: 600; }
.checkout-tab.done { color: var(--success); }

.checkout-tab-num {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--bg-2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
}

.checkout-tab.active .checkout-tab-num { background: var(--primary); color: #fff; }
.checkout-tab.done .checkout-tab-num { background: var(--success); color: #fff; }

.wh-radio-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 1px solid var(--border-subtle);
  border-radius: 8px;
  cursor: pointer;
  transition: border-color .15s;
}

.wh-radio-card.selected { border-color: var(--primary); background: var(--primary-subtle, #eff6ff); }
</style>
