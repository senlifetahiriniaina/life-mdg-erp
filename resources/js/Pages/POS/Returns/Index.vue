<template>
  <AppLayout>
    <Head title="Retours / Remboursements" />

    <div class="wh-page">
      <!-- Header -->
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Retours &amp; Remboursements</h1>
          <p class="wh-page-subtitle">Gérez les retours produits et remboursements clients</p>
        </div>
        <button class="btn btn-primary" @click="showInitiateReturn = true">
          <i class="pi pi-plus" style="font-size:13px" /> Nouveau retour
        </button>
      </div>

      <!-- Returns Table -->
      <div class="card">
        <table class="wh-table">
          <thead>
            <tr>
              <th>Référence</th>
              <th>Commande orig.</th>
              <th>Raison</th>
              <th>Méthode</th>
              <th>Montant</th>
              <th>Statut</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="returns.data.length === 0">
              <td colspan="8" style="text-align:center;color:var(--fg-4);padding:32px">
                Aucun retour enregistré
              </td>
            </tr>
            <tr v-for="r in returns.data" :key="r.id">
              <td><code style="font-size:12px">{{ r.reference }}</code></td>
              <td><code style="font-size:12px">{{ r.original_order?.reference ?? '#' + r.original_order_id }}</code></td>
              <td>{{ reasonLabel(r.reason) }}</td>
              <td>{{ methodLabel(r.refund_method) }}</td>
              <td style="font-weight:600;font-variant-numeric:tabular-nums">{{ formatCurrency(r.refund_amount) }}</td>
              <td>
                <span class="badge" :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
              </td>
              <td style="color:var(--fg-3);font-size:12px">{{ formatDate(r.created_at) }}</td>
              <td>
                <div style="display:flex;gap:4px">
                  <button
                    v-if="r.status === 'pending'"
                    class="btn btn-sm btn-success"
                    @click="processReturn(r.id)"
                  >
                    Traiter
                  </button>
                  <button
                    v-if="r.status === 'pending'"
                    class="btn btn-sm btn-danger"
                    @click="cancelReturn(r.id)"
                  >
                    Annuler
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="returns.last_page > 1" class="pagination">
          <button
            v-for="page in returns.last_page"
            :key="page"
            class="page-btn"
            :class="returns.current_page === page ? 'page-btn-on' : ''"
            @click="loadPage(page)"
          >{{ page }}</button>
        </div>
      </div>

      <!-- Initiate Return Dialog -->
      <Dialog v-model:visible="showInitiateReturn" header="Initier un retour" :modal="true" :style="{ width: '520px' }">
        <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
          <!-- Order Search -->
          <div>
            <label class="form-label">N° de commande</label>
            <div style="display:flex;gap:8px">
              <input v-model="orderRef" type="text" class="wh-input" placeholder="ORD-XXXXX" @keydown.enter="lookupOrder" />
              <button class="btn btn-secondary" :disabled="!orderRef || lookingUp" @click="lookupOrder">
                <i v-if="lookingUp" class="pi pi-spin pi-spinner" style="font-size:12px" />
                <i v-else class="pi pi-search" style="font-size:12px" />
                Chercher
              </button>
            </div>
          </div>

          <!-- Order Items -->
          <div v-if="foundOrder">
            <label class="form-label">Sélectionner les articles à retourner</label>
            <div style="display:flex;flex-direction:column;gap:6px">
              <div v-for="item in foundOrder.items" :key="item.id" class="return-item-row">
                <input
                  type="checkbox"
                  :id="`item-${item.id}`"
                  v-model="selectedItems[item.id]"
                  @change="initReturnLine(item)"
                />
                <label :for="`item-${item.id}`" style="flex:1;font-size:13px;cursor:pointer">
                  {{ item.product_name }}
                  <span style="color:var(--fg-3);font-size:12px">
                    × {{ item.quantity }} @ {{ formatCurrency(item.unit_price) }}
                  </span>
                </label>
                <input
                  v-if="selectedItems[item.id]"
                  v-model="returnQty[item.id]"
                  type="number"
                  :max="item.quantity"
                  min="0.0001"
                  step="0.0001"
                  class="wh-input"
                  style="width:80px"
                />
              </div>
            </div>
          </div>

          <!-- Reason & Method -->
          <div v-if="foundOrder" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
              <label class="form-label">Raison</label>
              <select v-model="returnReason" class="wh-input">
                <option value="refund">Remboursement</option>
                <option value="exchange">Échange</option>
                <option value="store_credit">Avoir</option>
              </select>
            </div>
            <div>
              <label class="form-label">Méthode de remboursement</label>
              <select v-model="returnMethod" class="wh-input">
                <option value="cash">Espèces</option>
                <option value="card">Carte</option>
                <option value="store_credit">Avoir</option>
              </select>
            </div>
          </div>
        </div>

        <template #footer>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-ghost" @click="showInitiateReturn = false; resetReturnForm()">Annuler</button>
            <button
              class="btn btn-primary"
              :disabled="!canSubmitReturn || submitting"
              @click="submitReturn"
            >
              <i v-if="submitting" class="pi pi-spin pi-spinner" style="font-size:12px" />
              Initier le retour
            </button>
          </div>
        </template>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps<{ returns: any }>()

const showInitiateReturn = ref(false)
const orderRef           = ref('')
const lookingUp          = ref(false)
const foundOrder         = ref<any>(null)
const selectedItems      = reactive<Record<number, boolean>>({})
const returnQty          = reactive<Record<number, number>>({})
const returnReason       = ref('refund')
const returnMethod       = ref('cash')
const submitting         = ref(false)

const csrf = () => (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? ''

const canSubmitReturn = computed(() => {
  if (!foundOrder.value) return false
  return Object.entries(selectedItems).some(([id, checked]) => checked && returnQty[Number(id)] > 0)
})

const lookupOrder = async () => {
  if (!orderRef.value.trim()) return
  lookingUp.value = true
  foundOrder.value = null
  try {
    const res = await fetch(`/api/v1/pos/orders?reference=${encodeURIComponent(orderRef.value.trim())}`, {
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const data = await res.json()
      const order = data.data?.[0] ?? null
      if (order) {
        const detailRes = await fetch(`/api/v1/pos/orders/${order.id}`, { headers: { Accept: 'application/json' } })
        foundOrder.value = detailRes.ok ? await detailRes.json() : null
      }
    }
  } finally {
    lookingUp.value = false
  }
}

const initReturnLine = (item: any) => {
  if (selectedItems[item.id] && !returnQty[item.id]) {
    returnQty[item.id] = Number(item.quantity)
  }
}

const submitReturn = async () => {
  if (!foundOrder.value || !canSubmitReturn.value) return
  submitting.value = true

  const lines = foundOrder.value.items
    .filter((item: any) => selectedItems[item.id] && returnQty[item.id] > 0)
    .map((item: any) => ({
      order_line_id: item.id,
      quantity:      returnQty[item.id],
      unit_price:    Number(item.unit_price),
      product_id:    item.product_id ?? null,
      restock:       true,
    }))

  try {
    const res = await fetch(`/api/v1/pos/orders/${foundOrder.value.id}/returns`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ lines, reason: returnReason.value, refund_method: returnMethod.value }),
    })

    if (res.ok) {
      showInitiateReturn.value = false
      resetReturnForm()
      router.reload()
    }
  } finally {
    submitting.value = false
  }
}

const resetReturnForm = () => {
  orderRef.value    = ''
  foundOrder.value  = null
  returnReason.value = 'refund'
  returnMethod.value = 'cash'
  Object.keys(selectedItems).forEach((k) => delete selectedItems[Number(k)])
  Object.keys(returnQty).forEach((k) => delete returnQty[Number(k)])
}

const processReturn = async (id: number) => {
  await fetch(`/api/v1/pos/returns/${id}/process`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
  })
  router.reload()
}

const cancelReturn = async (id: number) => {
  await fetch(`/api/v1/pos/returns/${id}/cancel`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
  })
  router.reload()
}

const loadPage = (page: number) => {
  router.get('/pos/returns', { page }, { preserveState: true, preserveScroll: true })
}

const reasonLabel = (r: string) =>
  ({ refund: 'Remboursement', exchange: 'Échange', store_credit: 'Avoir' })[r] ?? r

const methodLabel = (m: string) =>
  ({ cash: 'Espèces', card: 'Carte', store_credit: 'Avoir' })[m] ?? m

const statusLabel = (s: string) =>
  ({ pending: 'En attente', completed: 'Traité', cancelled: 'Annulé' })[s] ?? s

const statusClass = (s: string) => ({
  pending: 'badge-warning',
  completed: 'badge-success',
  cancelled: 'badge-danger',
})[s] ?? 'badge-neutral'

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0))

const formatDate = (d: string) =>
  d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
</script>

<style scoped>
.wh-page { padding: 24px; max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; justify-content: space-between; align-items: flex-start; }
.wh-page-title { font-size: 22px; font-weight: 700; color: var(--fg-1); margin: 0; }
.wh-page-subtitle { font-size: 14px; color: var(--fg-3); margin: 2px 0 0; }
.card { background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--r-lg); padding: 20px; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; padding: 8px 12px; font-size: 12px; font-weight: 600; color: var(--fg-3); border-bottom: 1px solid var(--border-subtle); }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-subtle); color: var(--fg-1); }
.wh-table tr:last-child td { border-bottom: none; }
.badge { padding: 2px 8px; border-radius: var(--r-pill); font-size: 11px; font-weight: 600; }
.badge-success { background: var(--success-bg); color: var(--success-fg); }
.badge-warning { background: #FEF9C3; color: #713F12; }
.badge-danger { background: var(--danger-bg); color: var(--danger-fg); }
.badge-neutral { background: var(--bg-sunken); color: var(--fg-3); }
.pagination { display: flex; gap: 4px; margin-top: 16px; justify-content: center; }
.page-btn { padding: 4px 10px; border-radius: var(--r-sm); border: 1px solid var(--border-subtle); background: var(--bg-canvas); cursor: pointer; font-size: 13px; color: var(--fg-2); }
.page-btn-on { background: var(--halo-500); color: #fff; border-color: var(--halo-500); }
.wh-input { width: 100%; padding: 8px 12px; border-radius: var(--r-md); border: 1px solid var(--border-subtle); background: var(--bg-canvas); font-family: var(--font-sans); font-size: 14px; color: var(--fg-1); outline: none; box-sizing: border-box; }
.wh-input:focus { border-color: var(--halo-500); box-shadow: 0 0 0 3px rgba(46,91,232,0.12); }
.form-label { display: block; font-size: 12px; font-weight: 500; color: var(--fg-2); margin-bottom: 4px; }
.return-item-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--border-subtle); border-radius: var(--r-md); background: var(--bg-sunken); }
.btn { font-family: var(--font-sans); font-weight: 500; font-size: 14px; padding: 8px 14px; border-radius: var(--r-md); border: 1px solid transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background var(--dur-base); line-height: 1.2; }
.btn-sm { font-size: 12px; padding: 5px 10px; }
.btn-primary { background: var(--halo-500); color: #fff; }
.btn-primary:hover:not(:disabled) { background: var(--halo-700); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: var(--bg-canvas); color: var(--fg-1); border-color: var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background: var(--bg-sunken); }
.btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-success { background: var(--success-bg); color: var(--success-fg); border-color: var(--success-border, var(--border-subtle)); }
.btn-danger { background: var(--danger-bg); color: var(--danger-fg); border-color: var(--danger-border, var(--border-subtle)); }
.btn-ghost { background: transparent; color: var(--fg-2); border-color: var(--border-subtle); }
.btn-ghost:hover { background: var(--bg-sunken); }
</style>
