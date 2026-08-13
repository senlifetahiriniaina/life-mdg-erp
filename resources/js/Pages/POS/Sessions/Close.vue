<template>
  <AppLayout>
    <Head :title="$t('pos.session.close_title')" />

    <div class="session-close-layout">
      <div class="session-close-header">
        <h1>{{ $t('pos.session.close_title') }}</h1>
        <p class="session-info">{{ $t('pos.session.register') }}: <strong>{{ session.register_name }}</strong> · {{ $t('pos.session.opened_at') }}: {{ formatDate(session.opened_at) }}</p>
      </div>

      <!-- Summary cards -->
      <div class="session-summary">
        <div class="summary-card">
          <span class="summary-value">{{ session.transaction_count ?? 0 }}</span>
          <span class="summary-label">{{ $t('pos.session.transactions') }}</span>
        </div>
        <div class="summary-card">
          <span class="summary-value">{{ formatAmount(session.total_sales ?? 0) }}</span>
          <span class="summary-label">{{ $t('pos.session.total_sales') }}</span>
        </div>
        <div class="summary-card">
          <span class="summary-value">{{ formatAmount(session.total_cash ?? 0) }}</span>
          <span class="summary-label">{{ $t('pos.session.cash_collected') }}</span>
        </div>
        <div class="summary-card">
          <span class="summary-value">{{ formatAmount(session.opening_float ?? 0) }}</span>
          <span class="summary-label">{{ $t('pos.session.opening_float') }}</span>
        </div>
      </div>

      <!-- Closing float input -->
      <div class="close-form">
        <h2>{{ $t('pos.session.closing_count') }}</h2>
        <div class="denomination-grid">
          <div v-for="denom in denominations" :key="denom.value" class="denomination-row">
            <label :for="`denom-${denom.value}`" class="denom-label">{{ denom.label }}</label>
            <input
              :id="`denom-${denom.value}`"
              v-model.number="counts[denom.value]"
              type="number"
              min="0"
              class="denom-input"
              :aria-label="`${denom.label} count`"
            />
            <span class="denom-subtotal">= {{ formatAmount((counts[denom.value] ?? 0) * denom.value) }}</span>
          </div>
        </div>

        <div class="closing-total">
          <span>{{ $t('pos.session.counted_cash') }}</span>
          <span class="closing-total-value">{{ formatAmount(countedCash) }}</span>
        </div>
        <div class="closing-difference" :class="difference >= 0 ? 'positive' : 'negative'">
          <span>{{ $t('pos.session.difference') }}</span>
          <span>{{ formatAmount(difference) }}</span>
        </div>

        <div class="close-actions">
          <button class="wh-btn wh-btn-ghost" @click="router.visit('/pos/sessions')" :aria-label="$t('common.cancel')">
            {{ $t('common.cancel') }}
          </button>
          <button class="wh-btn wh-btn-danger" @click="confirmClose" :disabled="closing" :aria-busy="closing">
            <i class="pi pi-lock" aria-hidden="true" />
            {{ closing ? $t('common.loading') : $t('pos.session.close_session') }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface Session {
  id: number
  register_name: string
  opened_at: string
  transaction_count?: number
  total_sales?: number
  total_cash?: number
  opening_float?: number
}

const props = defineProps<{ session: Session }>()

const closing = ref(false)
const counts  = ref<Record<number, number>>({})

const denominations = [
  { value: 500,  label: '500€' },
  { value: 200,  label: '200€' },
  { value: 100,  label: '100€' },
  { value: 50,   label: '50€'  },
  { value: 20,   label: '20€'  },
  { value: 10,   label: '10€'  },
  { value: 5,    label: '5€'   },
  { value: 2,    label: '2€'   },
  { value: 1,    label: '1€'   },
  { value: 0.5,  label: '50c'  },
  { value: 0.2,  label: '20c'  },
  { value: 0.1,  label: '10c'  },
  { value: 0.05, label: '5c'   },
  { value: 0.02, label: '2c'   },
  { value: 0.01, label: '1c'   },
]

const countedCash = computed(() =>
  denominations.reduce((sum, d) => sum + (counts.value[d.value] ?? 0) * d.value, 0)
)

const difference = computed(() =>
  countedCash.value - (props.session.total_cash ?? 0) - (props.session.opening_float ?? 0)
)

function formatAmount(n: number) {
  return '€' + (n ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
}

function formatDate(d: string) {
  return new Date(d).toLocaleString()
}

async function confirmClose() {
  if (!confirm('Confirm session closure?')) return
  closing.value = true
  try {
    await axios.post(`/api/v1/pos/sessions/${props.session.id}/close`, {
      closing_float: countedCash.value,
    })
    router.visit('/pos/sessions')
  } finally {
    closing.value = false
  }
}
</script>

<style scoped>
.session-close-layout { max-width:720px; margin:0 auto; }
.session-close-header { margin-bottom:1.5rem; }
.session-close-header h1 { font-size:1.5rem; font-weight:700; margin:0 0 .25rem; color:var(--fg-1); }
.session-info { color:var(--fg-3); font-size:.875rem; margin:0; }
.session-summary { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; margin-bottom:2rem; }
@media(min-width:640px) { .session-summary { grid-template-columns:repeat(4,1fr); } }
.summary-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:1rem; text-align:center; }
.summary-value { display:block; font-size:1.25rem; font-weight:700; color:var(--fg-1); }
.summary-label { font-size:.7rem; color:var(--fg-3); }
.close-form { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:1.5rem; }
.close-form h2 { font-size:1rem; font-weight:600; margin:0 0 1rem; color:var(--fg-1); }
.denomination-grid { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1.5rem; }
.denomination-row { display:flex; align-items:center; gap:.75rem; }
.denom-label { width:60px; font-size:.875rem; font-weight:600; text-align:right; color:var(--fg-2); }
.denom-input { width:80px; padding:.375rem .5rem; border:1px solid var(--border-subtle); border-radius:var(--r-md); text-align:center; font-size:.875rem; background:var(--bg-sunken); color:var(--fg-1); }
.denom-input:focus { outline:none; border-color:var(--halo-500); background:var(--bg-canvas); }
.denom-subtotal { font-size:.75rem; color:var(--fg-3); font-family:var(--font-mono); min-width:80px; }
.closing-total, .closing-difference { display:flex; justify-content:space-between; padding:.75rem 0; border-top:1px solid var(--border-subtle); font-weight:600; font-size:.9375rem; }
.closing-total-value { font-size:1.125rem; color:var(--fg-1); }
.closing-difference.positive { color:var(--success-fg); }
.closing-difference.negative { color:var(--danger-fg); }
.close-actions { display:flex; justify-content:flex-end; gap:.75rem; margin-top:1.5rem; }
.wh-btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.wh-btn-ghost { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.wh-btn-ghost:hover { background:var(--bg-sunken); }
.wh-btn-danger { background:var(--danger-fg); color:#fff; }
.wh-btn-danger:hover:not(:disabled) { opacity:.9; }
.wh-btn-danger:disabled { opacity:0.6; cursor:not-allowed; }
</style>
