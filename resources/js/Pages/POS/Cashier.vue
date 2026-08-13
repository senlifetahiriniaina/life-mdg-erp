<template>
  <AppLayout title="Caisse">
    <Head title="Caisse POS" />

    <div class="pos-shell">
      <!-- ══════════ LEFT : Catalogue ══════════ -->
      <div class="pos-catalog">
        <!-- Search + Categories -->
        <div class="pos-search-row">
          <div class="search-wrap">
            <i class="pi pi-search search-icon" />
            <input
              v-model="search"
              class="pos-search"
              placeholder="Rechercher un produit ou scanner un code…"
              @input="applyFilters"
            />
            <button v-if="search" class="search-clear" @click="search=''; applyFilters()">
              <i class="pi pi-times" style="font-size:11px" />
            </button>
          </div>
        </div>

        <div class="cat-row">
          <button
            class="cat-pill"
            :class="{ 'cat-on': activeCategory === null }"
            @click="setCategory(null)"
          >Tous</button>
          <button
            v-for="cat in categories"
            :key="cat"
            class="cat-pill"
            :class="{ 'cat-on': activeCategory === cat }"
            @click="setCategory(cat)"
          >{{ cat }}</button>
        </div>

        <!-- Product grid -->
        <div class="pos-products">
          <div v-if="loadingProducts" class="products-loading">
            <i class="pi pi-spin pi-spinner" style="font-size:28px;color:var(--halo-500)" />
            <p style="font-size:13px;color:var(--fg-3);margin:8px 0 0">Chargement du catalogue…</p>
          </div>
          <div v-else-if="filteredProducts.length === 0" class="products-empty">
            <i class="pi pi-box" style="font-size:40px;color:var(--fg-4)" />
            <p style="font-size:14px;color:var(--fg-3);margin:8px 0 0">Aucun produit trouvé</p>
          </div>
          <div v-else class="pos-grid">
            <button
              v-for="product in filteredProducts"
              :key="product.id"
              class="pos-product-card"
              :class="{ 'in-cart': isInCart(product.id) }"
              @click="store.addItem(product)"
            >
              <div class="product-img-wrap">
                <img
                  v-if="product.image_url"
                  :src="product.image_url"
                  :alt="product.name"
                  class="product-img"
                />
                <div v-else class="product-img-placeholder">
                  <i class="pi pi-box" style="font-size:18px;color:var(--halo-400)" />
                </div>
                <span v-if="isInCart(product.id)" class="in-cart-badge">
                  {{ cartQty(product.id) }}
                </span>
              </div>
              <p class="product-name">{{ product.name }}</p>
              <p class="product-price">{{ formatCurrency(product.sales_price ?? product.price ?? 0) }}</p>
            </button>
          </div>
        </div>
      </div>

      <!-- ══════════ CENTRE : Panier ══════════ -->
      <div class="pos-cart-col">
        <!-- Session banner -->
        <div class="pos-session-bar" :class="store.hasSession ? 'bar-open' : 'bar-closed'">
          <i class="pi pi-desktop" style="font-size:13px" />
          <span class="session-label">
            {{ store.hasSession ? `Session #${store.activeSession.id}` : 'Aucune session active' }}
          </span>
          <span v-if="store.activeTable" class="table-chip">
            <i class="pi pi-table" style="font-size:11px" /> Table {{ store.activeTable.number }}
          </span>
          <button
            v-if="!store.hasSession"
            class="btn btn-sm btn-white"
            style="margin-left:auto"
            @click="showOpenSessionDialog = true"
            :disabled="openingSession"
          >
            <i v-if="openingSession" class="pi pi-spin pi-spinner" style="font-size:11px" />
            Ouvrir
          </button>
          <button
            v-else
            class="btn btn-sm btn-ghost-white"
            style="margin-left:auto"
            @click="confirmCloseSession"
          >Fermer</button>
        </div>

        <!-- Cart items list -->
        <div class="cart-items-wrap">
          <div v-if="store.cart.length === 0" class="cart-empty">
            <i class="pi pi-shopping-cart" style="font-size:44px;color:var(--fg-4)" />
            <p style="font-size:14px;color:var(--fg-3);margin:10px 0 0">Sélectionnez des produits</p>
          </div>
          <div v-else class="cart-list">
            <div v-for="(item, i) in store.cart" :key="i" class="cart-item">
              <div class="cart-item-name">
                <p class="item-name">{{ item.name }}</p>
                <p class="item-unit">{{ formatCurrency(item.price) }} / u.</p>
              </div>
              <div class="qty-controls">
                <button class="qty-btn" @click="store.changeQty(i, -1)">
                  <i class="pi pi-minus" style="font-size:10px" />
                </button>
                <input
                  class="qty-input"
                  type="number"
                  min="1"
                  :value="item.qty"
                  @change="(e) => store.setQty(i, parseInt(e.target.value) || 1)"
                />
                <button class="qty-btn" @click="store.changeQty(i, 1)">
                  <i class="pi pi-plus" style="font-size:10px" />
                </button>
              </div>
              <p class="item-line-total">{{ formatCurrency(item.price * item.qty) }}</p>
              <button class="del-btn" @click="store.removeItem(i)">
                <i class="pi pi-trash" style="font-size:11px" />
              </button>
            </div>
          </div>
        </div>

        <!-- Discount / Coupon -->
        <div class="discount-row">
          <div class="discount-field">
            <input
              v-model="couponInput"
              class="discount-input"
              placeholder="Code coupon…"
              @keyup.enter="applyCoupon"
            />
            <button class="btn btn-ghost btn-sm" @click="applyCoupon">Appliquer</button>
          </div>
          <div class="discount-field">
            <select v-model="store.discountType" class="discount-select">
              <option value="fixed">€ fixe</option>
              <option value="percent">% remise</option>
            </select>
            <input
              v-model.number="store.discountAmount"
              class="discount-input"
              type="number"
              min="0"
              :placeholder="store.discountType === 'percent' ? 'Remise %' : 'Remise €'"
            />
          </div>
        </div>

        <!-- Totals -->
        <div class="totals-block">
          <div class="total-row">
            <span>Sous-total</span>
            <span>{{ formatCurrency(store.subtotal) }}</span>
          </div>
          <div v-if="store.discountValue > 0" class="total-row discount-line">
            <span>Remise {{ store.discountType === 'percent' ? `(${store.discountAmount}%)` : '' }}</span>
            <span>- {{ formatCurrency(store.discountValue) }}</span>
          </div>
          <div class="total-row">
            <span>TVA ({{ store.taxRate }}%)</span>
            <span>{{ formatCurrency(store.taxAmount) }}</span>
          </div>
          <div class="total-row total-final">
            <span>Total TTC</span>
            <span>{{ formatCurrency(store.total) }}</span>
          </div>
        </div>

        <!-- Payment methods -->
        <div class="payment-methods">
          <button
            v-for="m in paymentMethods"
            :key="m.value"
            class="pay-btn"
            :class="{ 'pay-on': store.paymentMethod === m.value }"
            @click="store.paymentMethod = m.value"
          >
            <i :class="m.icon" style="font-size:14px" />
            {{ m.label }}
          </button>
        </div>

        <!-- Charge + Cancel -->
        <div class="action-row">
          <button
            class="btn btn-ghost cancel-btn"
            :disabled="store.cart.length === 0"
            @click="store.clearCart()"
          >
            <i class="pi pi-times" style="font-size:13px" />
            Annuler
          </button>
          <button
            class="charge-btn"
            :disabled="store.cart.length === 0 || !store.hasSession || charging"
            @click="handleCharge"
          >
            <i v-if="charging" class="pi pi-spin pi-spinner" style="font-size:14px" />
            <i v-else class="pi pi-check-circle" style="font-size:14px" />
            Encaisser {{ formatCurrency(store.total) }}
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════ DIALOG : Monnaie espèces ══════════ -->
    <Dialog
      v-model:visible="showCashDialog"
      header="Paiement en espèces"
      :modal="true"
      :style="{ width: '380px' }"
    >
      <div style="display:flex;flex-direction:column;gap:16px;padding:10px 0">
        <div class="total-display">
          <p class="td-label">Montant à payer</p>
          <p class="td-amount">{{ formatCurrency(store.total) }}</p>
        </div>
        <div class="form-group">
          <label class="form-label">Montant reçu (€)</label>
          <InputText
            v-model.number="cashReceived"
            type="number"
            min="0"
            step="0.01"
            class="w-full"
            autofocus
            @keyup.enter="confirmCashPayment"
          />
        </div>
        <div v-if="cashReceived >= store.total" class="change-display">
          <p class="td-label">Monnaie à rendre</p>
          <p class="td-change">{{ formatCurrency(cashReceived - store.total) }}</p>
        </div>
        <div v-else-if="cashReceived > 0" class="change-display insufficient">
          <p class="td-label">Manque</p>
          <p class="td-change danger">{{ formatCurrency(store.total - cashReceived) }}</p>
        </div>
        <!-- Quick amounts -->
        <div class="quick-amounts">
          <button
            v-for="amt in quickAmounts"
            :key="amt"
            class="quick-amt-btn"
            :class="{ 'qa-on': cashReceived === amt }"
            @click="cashReceived = amt"
          >{{ formatCurrency(amt) }}</button>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showCashDialog = false">Annuler</button>
        <button
          class="btn btn-primary"
          :disabled="cashReceived < store.total || charging"
          @click="confirmCashPayment"
        >
          <i v-if="charging" class="pi pi-spin pi-spinner" style="font-size:12px" />
          Valider le paiement
        </button>
      </template>
    </Dialog>

    <!-- ══════════ DIALOG : Reçu ══════════ -->
    <Dialog
      v-model:visible="showReceiptDialog"
      header="Commande complétée"
      :modal="true"
      :style="{ width: '360px' }"
    >
      <div class="receipt-body">
        <div class="receipt-check">
          <i class="pi pi-check-circle" style="font-size:32px;color:var(--success-fg)" />
        </div>
        <p class="receipt-title">Paiement reçu</p>
        <p class="receipt-amount">{{ formatCurrency(lastOrder.total) }}</p>
        <p class="receipt-order"># {{ lastOrder.orderNumber }}</p>
        <div v-if="lastOrder.change > 0" class="receipt-change">
          <span>Monnaie rendue :</span>
          <strong>{{ formatCurrency(lastOrder.change) }}</strong>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showReceiptDialog = false; printReceipt()">
          <i class="pi pi-print" style="font-size:12px" />
          Imprimer
        </button>
        <button class="btn btn-primary" @click="newOrder">
          <i class="pi pi-refresh" style="font-size:12px" />
          Nouvelle commande
        </button>
      </template>
    </Dialog>

    <!-- ══════════ DIALOG : Ouvrir session ══════════ -->
    <Dialog
      v-model:visible="showOpenSessionDialog"
      header="Ouvrir une session de caisse"
      :modal="true"
      :style="{ width: '400px' }"
    >
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div class="form-group">
          <label class="form-label">Fond de caisse initial (€)</label>
          <InputText
            v-model.number="openingBalance"
            type="number"
            min="0"
            step="0.01"
            placeholder="0.00"
            class="w-full"
          />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showOpenSessionDialog = false">Annuler</button>
        <button class="btn btn-primary" @click="openSession" :disabled="openingSession">
          <i v-if="openingSession" class="pi pi-spin pi-spinner" style="font-size:12px" />
          Ouvrir la session
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Dialog, InputText } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePosStore } from '@/stores/posStore'
import axios from 'axios'

const props = defineProps({
  session: { type: Object, default: null },
  config:  { type: Object, default: null },
})

const store = usePosStore()

// ─── Product catalogue ─────────────────────────────────────────────────────
const search           = ref('')
const activeCategory   = ref(null)
const loadingProducts  = ref(false)
const allProducts      = ref([])
const filteredProducts = ref([])

const categories = computed(() =>
  [...new Set(allProducts.value.map((p) => p.category?.name).filter(Boolean))].slice(0, 8)
)

function isInCart(productId) {
  return store.cart.some((i) => i.id === productId)
}

function cartQty(productId) {
  return store.cart.find((i) => i.id === productId)?.qty ?? 0
}

function setCategory(cat) {
  activeCategory.value = cat
  applyFilters()
}

function applyFilters() {
  let list = allProducts.value
  if (activeCategory.value) list = list.filter((p) => p.category?.name === activeCategory.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(
      (p) => p.name.toLowerCase().includes(q) || (p.barcode ?? '').includes(q)
    )
  }
  filteredProducts.value = list
}

// ─── Cash dialog ───────────────────────────────────────────────────────────
const showCashDialog = ref(false)
const cashReceived   = ref(0)

const quickAmounts = computed(() => {
  const t = store.total
  const base = Math.ceil(t / 10) * 10
  return [base, base + 10, base + 20, base + 50].filter((v) => v > 0)
})

function handleCharge() {
  if (store.paymentMethod === 'cash') {
    cashReceived.value = Math.ceil(store.total / 10) * 10
    showCashDialog.value = true
  } else {
    processPayment(0)
  }
}

async function confirmCashPayment() {
  if (cashReceived.value < store.total) return
  const change = cashReceived.value - store.total
  await processPayment(change)
  showCashDialog.value = false
}

// ─── Order placement ───────────────────────────────────────────────────────
const charging    = ref(false)
const showReceiptDialog = ref(false)
const lastOrder   = ref({ total: 0, orderNumber: '', change: 0 })

async function processPayment(change = 0) {
  charging.value = true
  try {
    const order = await store.placeOrder()
    lastOrder.value = {
      total:       order.total ?? store.total,
      orderNumber: order.order_number ?? order.id,
      change,
    }
    showReceiptDialog.value = true
  } catch (err) {
    console.error('Payment error', err)
  } finally {
    charging.value = false
  }
}

function newOrder() {
  showReceiptDialog.value = false
  cashReceived.value = 0
}

function printReceipt() {
  window.print()
}

// ─── Coupon ────────────────────────────────────────────────────────────────
const couponInput = ref('')

function applyCoupon() {
  if (!couponInput.value) return
  store.couponCode = couponInput.value
  // basic: 10% off for any code — real implementation calls API
  store.applyDiscount(10, 'percent')
}

// ─── Session ───────────────────────────────────────────────────────────────
const showOpenSessionDialog = ref(false)
const openingSession        = ref(false)
const openingBalance        = ref(0)

async function openSession() {
  openingSession.value = true
  try {
    await store.openSession({ openingBalance: openingBalance.value, configId: props.config?.id })
    showOpenSessionDialog.value = false
  } finally {
    openingSession.value = false
  }
}

async function confirmCloseSession() {
  if (!confirm('Fermer la session de caisse ? Vous devrez compter la caisse.')) return
  await store.closeSession()
}

// ─── Payment methods ───────────────────────────────────────────────────────
const paymentMethods = [
  { label: 'Espèces',      value: 'cash',   icon: 'pi pi-money-bill' },
  { label: 'Carte',        value: 'card',   icon: 'pi pi-credit-card' },
  { label: 'Mobile Money', value: 'mobile', icon: 'pi pi-mobile' },
]

// ─── Utils ─────────────────────────────────────────────────────────────────
const formatCurrency = (v) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0))

// ─── Init ──────────────────────────────────────────────────────────────────
onMounted(async () => {
  // Restore session from prop
  if (props.session && !store.hasSession) {
    store.setSession(props.session)
  }
  if (props.config?.tax_rate) {
    store.taxRate = props.config.tax_rate
  }

  // Load products
  loadingProducts.value = true
  try {
    const { data } = await axios.get('/api/v1/inventory/products?per_page=200&is_active=1')
    allProducts.value      = data.data ?? []
    filteredProducts.value = allProducts.value
  } catch { /* silently degrade */ } finally {
    loadingProducts.value = false
  }
})
</script>

<style scoped>
/* ── Layout ── */
.pos-shell {
  display: flex;
  height: calc(100vh - 80px);
  gap: 0;
  overflow: hidden;
  margin: -20px;  /* bleed to AppLayout padding */
}

/* ── Catalog ── */
.pos-catalog {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  gap: 10px;
  padding: 14px;
  border-right: 1px solid var(--border-subtle);
  background: var(--bg-sunken);
  overflow: hidden;
}

.pos-search-row { display:flex; align-items:center; gap:8px; }
.search-wrap { position:relative; flex:1; }
.pos-search {
  width: 100%;
  padding: 10px 36px 10px 36px;
  border-radius: var(--r-md);
  border: 1px solid var(--border-subtle);
  background: var(--bg-canvas);
  font-family: var(--font-sans);
  font-size: 14px;
  color: var(--fg-1);
  outline: none;
  box-sizing: border-box;
}
.pos-search:focus { border-color:var(--halo-500); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--fg-4); font-size:13px; pointer-events:none; }
.search-clear { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--fg-3); display:flex; padding:2px; }

.cat-row { display:flex; gap:6px; flex-wrap:wrap; }
.cat-pill {
  height: 32px;
  padding: 0 14px;
  border-radius: var(--r-pill);
  border: 1px solid var(--border-subtle);
  background: var(--bg-canvas);
  font-size: 13px;
  font-weight: 500;
  color: var(--fg-2);
  cursor: pointer;
  transition: all var(--dur-fast);
  font-family: var(--font-sans);
}
.cat-pill:hover { border-color:var(--halo-300); color:var(--halo-600); }
.cat-on { background:var(--halo-50); border-color:var(--halo-300); color:var(--halo-700) !important; }

.pos-products { flex:1; overflow-y:auto; }
.products-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; }
.products-empty  { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; }

.pos-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:8px; }

.pos-product-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px 10px;
  border-radius: var(--r-md);
  border: 1px solid var(--border-subtle);
  background: var(--bg-canvas);
  cursor: pointer;
  text-align: center;
  transition: all var(--dur-fast);
  font-family: var(--font-sans);
  position: relative;
}
.pos-product-card:hover { border-color:var(--halo-400); box-shadow:var(--shadow-md); transform:translateY(-1px); }
.pos-product-card:active { transform:scale(0.97); }
.in-cart { border-color:var(--halo-400); background:var(--halo-50); }

.product-img-wrap { position:relative; width:56px; height:56px; }
.product-img { width:56px; height:56px; object-fit:cover; border-radius:var(--r-sm); }
.product-img-placeholder { width:56px; height:56px; border-radius:var(--r-sm); background:var(--halo-50); display:flex; align-items:center; justify-content:center; }
.in-cart-badge { position:absolute; top:-6px; right:-6px; background:var(--halo-500); color:#fff; width:18px; height:18px; border-radius:50%; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; }

.product-name { font-size:12px; font-weight:500; color:var(--fg-1); margin:0; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.35; }
.product-price { font-size:13px; font-weight:700; color:var(--halo-600); margin:0; font-variant-numeric:tabular-nums; }

/* ── Cart column ── */
.pos-cart-col {
  width: 340px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: var(--bg-canvas);
  overflow: hidden;
}

/* Session bar */
.pos-session-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 500;
  border-bottom: 1px solid var(--border-subtle);
}
.bar-open   { background:#D1FAE5; color:#065F46; }
.bar-closed { background:var(--bg-sunken); color:var(--fg-2); }
.session-label { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.table-chip { font-size:11px; background:var(--halo-50); color:var(--halo-700); padding:2px 7px; border-radius:var(--r-pill); display:inline-flex; align-items:center; gap:4px; }

/* Cart items */
.cart-items-wrap { flex:1; overflow-y:auto; }
.cart-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--fg-4); }
.cart-list { display:flex; flex-direction:column; }

.cart-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border-subtle);
  transition: background var(--dur-fast);
}
.cart-item:hover { background:var(--bg-sunken); }
.cart-item-name { flex:1; min-width:0; }
.item-name { font-size:13px; font-weight:500; color:var(--fg-1); margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.item-unit { font-size:11px; color:var(--fg-3); margin:2px 0 0; }
.item-line-total { font-size:13px; font-weight:600; color:var(--fg-1); min-width:60px; text-align:right; font-variant-numeric:tabular-nums; margin:0; }

.qty-controls { display:flex; align-items:center; gap:3px; }
.qty-btn { width:26px; height:26px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background var(--dur-fast); }
.qty-btn:hover { background:var(--halo-50); color:var(--halo-600); border-color:var(--halo-300); }
.qty-input { width:36px; height:26px; text-align:center; border:1px solid var(--border-subtle); border-radius:var(--r-sm); font-size:13px; font-weight:600; color:var(--fg-1); background:var(--bg-canvas); font-family:var(--font-sans); padding:0; outline:none; }
.qty-input:focus { border-color:var(--halo-500); }
.del-btn { width:26px; height:26px; border-radius:var(--r-sm); border:none; background:none; color:var(--danger-fg); cursor:pointer; display:flex; align-items:center; justify-content:center; opacity:0.6; transition:opacity var(--dur-fast),background var(--dur-fast); }
.del-btn:hover { opacity:1; background:var(--danger-bg); }

/* Discount */
.discount-row { padding:10px 14px; border-top:1px solid var(--border-subtle); display:flex; flex-direction:column; gap:7px; }
.discount-field { display:flex; gap:6px; align-items:center; }
.discount-input { flex:1; padding:6px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); font-size:13px; color:var(--fg-1); background:var(--bg-canvas); font-family:var(--font-sans); outline:none; min-width:0; }
.discount-input:focus { border-color:var(--halo-500); }
.discount-select { padding:6px 8px; border:1px solid var(--border-subtle); border-radius:var(--r-md); font-size:13px; color:var(--fg-1); background:var(--bg-canvas); outline:none; font-family:var(--font-sans); }

/* Totals */
.totals-block { padding:12px 16px; border-top:1px solid var(--border-subtle); display:flex; flex-direction:column; gap:5px; }
.total-row { display:flex; justify-content:space-between; font-size:14px; color:var(--fg-2); }
.discount-line { color:var(--success-fg); }
.total-final { font-size:17px; font-weight:700; color:var(--fg-1); margin-top:6px; padding-top:8px; border-top:2px solid var(--border-subtle); }

/* Payment methods */
.payment-methods { padding:0 14px 10px; display:flex; gap:6px; }
.pay-btn { flex:1; padding:8px 4px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:12px; font-weight:500; color:var(--fg-2); cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:4px; transition:all var(--dur-fast); font-family:var(--font-sans); }
.pay-btn:hover { border-color:var(--halo-300); color:var(--halo-600); }
.pay-on { background:var(--halo-50); border-color:var(--halo-400); color:var(--halo-700) !important; }

/* Actions */
.action-row { display:flex; gap:8px; padding:0 14px 14px; }
.cancel-btn { flex:0 0 auto; }
.charge-btn {
  flex: 1;
  padding: 13px;
  background: var(--halo-500);
  color: #fff;
  border: none;
  border-radius: var(--r-md);
  font-family: var(--font-sans);
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background var(--dur-base);
}
.charge-btn:hover:not(:disabled) { background:var(--halo-700); }
.charge-btn:disabled { opacity:0.5; cursor:not-allowed; }

/* Cash dialog */
.total-display { text-align:center; padding:12px; background:var(--bg-sunken); border-radius:var(--r-md); }
.td-label { font-size:12px; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; margin:0 0 4px; }
.td-amount { font-size:28px; font-weight:700; color:var(--fg-1); margin:0; font-family:var(--font-display); }
.change-display { text-align:center; padding:12px; background:var(--success-bg); border-radius:var(--r-md); }
.change-display.insufficient { background:var(--danger-bg); }
.td-change { font-size:24px; font-weight:700; color:var(--success-fg); margin:0; }
.td-change.danger { color:var(--danger-fg); }
.quick-amounts { display:flex; gap:8px; flex-wrap:wrap; }
.quick-amt-btn { flex:1; min-width:70px; padding:8px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:13px; font-weight:500; color:var(--fg-2); cursor:pointer; transition:all var(--dur-fast); font-family:var(--font-sans); }
.quick-amt-btn:hover { border-color:var(--halo-300); color:var(--halo-600); }
.qa-on { background:var(--halo-50); border-color:var(--halo-400); color:var(--halo-700); }

/* Receipt dialog */
.receipt-body { display:flex; flex-direction:column; align-items:center; gap:10px; padding:16px 0; }
.receipt-check { width:64px; height:64px; background:var(--success-bg); border-radius:50%; display:flex; align-items:center; justify-content:center; }
.receipt-title  { font-size:17px; font-weight:600; color:var(--fg-1); margin:0; }
.receipt-amount { font-size:30px; font-weight:800; color:var(--success-fg); margin:0; font-family:var(--font-display); }
.receipt-order  { font-size:13px; color:var(--fg-3); margin:0; font-family:var(--font-mono); }
.receipt-change { display:flex; gap:8px; align-items:center; font-size:14px; color:var(--fg-2); }

/* Form helpers */
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.w-full { width:100%; }

/* Buttons */
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-sm { font-size:12px; padding:5px 10px; }
.btn-primary { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-ghost { background:transparent; color:var(--fg-2); border-color:var(--border-subtle); }
.btn-ghost:hover { background:var(--bg-sunken); color:var(--fg-1); }
.btn-white { background:#fff; color:var(--fg-1); border-color:rgba(0,0,0,0.15); }
.btn-white:hover { background:#F8FAFB; }
.btn-ghost-white { background:rgba(255,255,255,0.18); color:inherit; border-color:transparent; }
.btn-ghost-white:hover { background:rgba(255,255,255,0.32); }
</style>
