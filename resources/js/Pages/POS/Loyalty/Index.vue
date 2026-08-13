<template>
  <AppLayout>
    <Head title="Programme de fidélité" />

    <div class="wh-page">
      <!-- Header -->
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Programme de fidélité</h1>
          <p class="wh-page-subtitle">Gérez les points et récompenses clients</p>
        </div>
      </div>

      <div class="loyalty-grid">
        <!-- Programs Card -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">
              <i class="pi pi-star" style="color:var(--halo-500)" /> Programmes actifs
            </h2>
            <button class="btn btn-primary btn-sm" @click="showCreateProgram = true">
              <i class="pi pi-plus" style="font-size:12px" /> Nouveau
            </button>
          </div>
          <div v-if="programs.length === 0" class="empty-state">
            <i class="pi pi-star" style="font-size:32px;color:var(--fg-4)" />
            <p>Aucun programme configuré</p>
          </div>
          <div v-else class="program-list">
            <div v-for="prog in programs" :key="prog.id" class="program-card" :class="prog.status === 'active' ? 'program-active' : ''">
              <div style="flex:1">
                <p style="font-size:14px;font-weight:600;color:var(--fg-1);margin:0">{{ prog.name }}</p>
                <p style="font-size:12px;color:var(--fg-3);margin:2px 0 0">
                  {{ prog.points_per_currency }} pts / 1 {{ currency }} &nbsp;·&nbsp;
                  1 pt = {{ prog.currency_per_point }} {{ currency }} &nbsp;·&nbsp;
                  Min. {{ prog.min_points_redeem }} pts pour échanger
                </p>
              </div>
              <span class="badge" :class="prog.status === 'active' ? 'badge-success' : 'badge-neutral'">
                {{ prog.status === 'active' ? 'Actif' : 'Inactif' }}
              </span>
            </div>
          </div>

          <!-- Tiers Info -->
          <div class="tiers-section">
            <p style="font-size:12px;font-weight:600;color:var(--fg-2);margin:0 0 8px">Niveaux de fidélité</p>
            <div class="tiers-grid">
              <div v-for="tier in tiers" :key="tier.name" class="tier-card">
                <i class="pi pi-circle-fill" :style="`color:${tier.color};font-size:10px`" />
                <span style="font-size:12px;font-weight:500;color:var(--fg-1)">{{ tier.label }}</span>
                <span style="font-size:11px;color:var(--fg-3)">{{ tier.threshold }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Customer Lookup Card -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">
              <i class="pi pi-search" style="color:var(--halo-500)" /> Rechercher un client
            </h2>
          </div>
          <div style="display:flex;gap:8px;margin-bottom:16px">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Téléphone ou email…"
              class="wh-input"
              @keydown.enter="searchCustomer"
            />
            <button class="btn btn-primary" :disabled="!searchQuery || searching" @click="searchCustomer">
              <i v-if="searching" class="pi pi-spin pi-spinner" style="font-size:12px" />
              <i v-else class="pi pi-search" style="font-size:12px" />
              Rechercher
            </button>
          </div>

          <!-- Account Result -->
          <div v-if="account" class="account-card">
            <div class="account-header">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="account-avatar">
                  <i class="pi pi-user" style="font-size:20px;color:var(--fg-3)" />
                </div>
                <div>
                  <p style="font-size:14px;font-weight:600;color:var(--fg-1);margin:0">{{ account.customer_id }}</p>
                  <span class="tier-badge" :style="`background:${tierColor(account.tier)}`">
                    {{ tierLabel(account.tier) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="account-stats">
              <div class="stat-box">
                <p class="stat-value">{{ account.points_balance.toLocaleString() }}</p>
                <p class="stat-label">Points disponibles</p>
              </div>
              <div class="stat-box">
                <p class="stat-value">{{ account.total_earned.toLocaleString() }}</p>
                <p class="stat-label">Total gagnés</p>
              </div>
              <div class="stat-box">
                <p class="stat-value">{{ account.total_redeemed.toLocaleString() }}</p>
                <p class="stat-label">Total échangés</p>
              </div>
            </div>

            <!-- Manual Adjustment -->
            <div class="adjustment-form">
              <p style="font-size:12px;font-weight:600;color:var(--fg-2);margin:0 0 8px">Ajustement manuel</p>
              <div style="display:flex;gap:8px;align-items:flex-end">
                <div style="flex:1">
                  <label class="form-label">Points</label>
                  <input v-model="adjustPoints" type="number" class="wh-input" placeholder="Ex: 100" />
                </div>
                <div style="flex:2">
                  <label class="form-label">Note</label>
                  <input v-model="adjustNote" type="text" class="wh-input" placeholder="Raison…" />
                </div>
              </div>
              <div style="display:flex;gap:6px;margin-top:8px">
                <button class="btn btn-secondary btn-sm" :disabled="!adjustPoints" @click="adjust('add')">
                  <i class="pi pi-plus" style="font-size:11px" /> Ajouter
                </button>
                <button class="btn btn-danger btn-sm" :disabled="!adjustPoints" @click="adjust('remove')">
                  <i class="pi pi-minus" style="font-size:11px" /> Retirer
                </button>
              </div>
            </div>

            <!-- Recent Transactions -->
            <div v-if="account.transactions?.length" class="transactions-list">
              <p style="font-size:12px;font-weight:600;color:var(--fg-2);margin:0 0 8px">Dernières transactions</p>
              <div v-for="tx in account.transactions.slice(0, 5)" :key="tx.id" class="tx-item">
                <i :class="tx.type === 'earned' ? 'pi pi-arrow-up' : 'pi pi-arrow-down'"
                   :style="`color:${tx.type === 'earned' ? 'var(--success-fg)' : 'var(--danger-fg)'};font-size:12px`" />
                <span style="flex:1;font-size:12px;color:var(--fg-2)">{{ tx.note ?? tx.type }}</span>
                <span style="font-size:12px;font-weight:600" :style="`color:${tx.points > 0 ? 'var(--success-fg)' : 'var(--danger-fg)'}`">
                  {{ tx.points > 0 ? '+' : '' }}{{ tx.points }} pts
                </span>
              </div>
            </div>
          </div>

          <div v-else-if="searched && !account" class="empty-state">
            <i class="pi pi-user" style="font-size:32px;color:var(--fg-4)" />
            <p>Aucun compte trouvé pour « {{ lastSearched }} »</p>
          </div>
        </div>
      </div>

      <!-- Create Program Dialog -->
      <Dialog v-model:visible="showCreateProgram" header="Nouveau programme" :modal="true" :style="{ width: '420px' }">
        <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
          <div>
            <label class="form-label">Nom</label>
            <input v-model="newProgram.name" type="text" class="wh-input" placeholder="Programme standard" />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
              <label class="form-label">Points / unité monétaire</label>
              <input v-model="newProgram.points_per_currency" type="number" step="0.0001" class="wh-input" />
            </div>
            <div>
              <label class="form-label">Unité / point</label>
              <input v-model="newProgram.currency_per_point" type="number" step="0.0001" class="wh-input" />
            </div>
          </div>
          <div>
            <label class="form-label">Points minimum pour échanger</label>
            <input v-model="newProgram.min_points_redeem" type="number" min="1" class="wh-input" />
          </div>
        </div>
        <template #footer>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-ghost" @click="showCreateProgram = false">Annuler</button>
            <button class="btn btn-primary" :disabled="!newProgram.name || creatingProgram" @click="createProgram">
              <i v-if="creatingProgram" class="pi pi-spin pi-spinner" style="font-size:12px" />
              Créer
            </button>
          </div>
        </template>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps<{ programs: any[] }>()

const currency         = 'EUR'
const searchQuery      = ref('')
const searching        = ref(false)
const searched         = ref(false)
const lastSearched     = ref('')
const account          = ref<any>(null)
const showCreateProgram = ref(false)
const creatingProgram  = ref(false)
const adjustPoints     = ref<number | null>(null)
const adjustNote       = ref('')

const newProgram = ref({
  name: '',
  points_per_currency: 1,
  currency_per_point: 0.01,
  min_points_redeem: 100,
  status: 'active',
})

const tiers = [
  { name: 'bronze',   label: 'Bronze',   color: '#CD7F32', threshold: '0 – 999 pts' },
  { name: 'silver',   label: 'Argent',   color: '#9E9E9E', threshold: '1 000 – 4 999 pts' },
  { name: 'gold',     label: 'Or',       color: '#FFD700', threshold: '5 000 – 9 999 pts' },
  { name: 'platinum', label: 'Platine',  color: '#E5E4E2', threshold: '10 000+ pts' },
]

const tierColor = (tier: string) => tiers.find((t) => t.name === tier)?.color ?? '#CD7F32'
const tierLabel = (tier: string) => tiers.find((t) => t.name === tier)?.label ?? tier

const csrf = () => (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? ''

const searchCustomer = async () => {
  if (!searchQuery.value.trim()) return
  searching.value  = true
  lastSearched.value = searchQuery.value.trim()
  account.value    = null
  searched.value   = false

  try {
    const res = await fetch(`/api/v1/pos/loyalty/accounts/${encodeURIComponent(searchQuery.value.trim())}`, {
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      account.value = await res.json()
    }
  } finally {
    searching.value = false
    searched.value  = true
  }
}

const adjust = async (mode: 'add' | 'remove') => {
  if (!account.value || !adjustPoints.value) return
  const points = mode === 'add' ? Math.abs(adjustPoints.value) : -Math.abs(adjustPoints.value)

  // Use earn endpoint for add, note the manual adjustment
  const endpoint = mode === 'add'
    ? `/api/v1/pos/loyalty/accounts/${account.value.id}/earn`
    : `/api/v1/pos/loyalty/accounts/${account.value.id}/redeem`

  await fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
    body: JSON.stringify({ points: Math.abs(points), note: adjustNote.value }),
  })

  await searchCustomer()
  adjustPoints.value = null
  adjustNote.value   = ''
}

const createProgram = async () => {
  creatingProgram.value = true
  try {
    const res = await fetch('/api/v1/pos/loyalty/programs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(newProgram.value),
    })
    if (res.ok) {
      showCreateProgram.value = false
      window.location.reload()
    }
  } finally {
    creatingProgram.value = false
  }
}
</script>

<style scoped>
.wh-page { padding: 24px; max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; justify-content: space-between; align-items: flex-start; }
.wh-page-title { font-size: 22px; font-weight: 700; color: var(--fg-1); margin: 0; }
.wh-page-subtitle { font-size: 14px; color: var(--fg-3); margin: 2px 0 0; }
.loyalty-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 900px) { .loyalty-grid { grid-template-columns: 1fr; } }
.card { background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--r-lg); padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 600; color: var(--fg-1); margin: 0; display: flex; align-items: center; gap: 8px; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--fg-4); padding: 24px 0; font-size: 13px; }
.program-list { display: flex; flex-direction: column; gap: 8px; }
.program-card { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border: 1px solid var(--border-subtle); border-radius: var(--r-md); background: var(--bg-sunken); }
.program-active { border-color: var(--success-border, #a7f3d0); background: var(--success-bg, #d1fae5); }
.badge { padding: 2px 8px; border-radius: var(--r-pill); font-size: 11px; font-weight: 600; }
.badge-success { background: var(--success-bg); color: var(--success-fg); }
.badge-neutral { background: var(--bg-sunken); color: var(--fg-3); }
.tiers-section { border-top: 1px solid var(--border-subtle); padding-top: 14px; }
.tiers-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.tier-card { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid var(--border-subtle); border-radius: var(--r-md); background: var(--bg-sunken); }
.wh-input { width: 100%; padding: 8px 12px; border-radius: var(--r-md); border: 1px solid var(--border-subtle); background: var(--bg-canvas); font-family: var(--font-sans); font-size: 14px; color: var(--fg-1); outline: none; }
.wh-input:focus { border-color: var(--halo-500); box-shadow: 0 0 0 3px rgba(46,91,232,0.12); }
.account-card { border: 1px solid var(--border-subtle); border-radius: var(--r-md); padding: 14px; display: flex; flex-direction: column; gap: 14px; }
.account-header { display: flex; justify-content: space-between; align-items: center; }
.account-avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--bg-sunken); border: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: center; }
.tier-badge { padding: 2px 8px; border-radius: var(--r-pill); font-size: 11px; font-weight: 600; color: #fff; display: inline-block; margin-top: 4px; }
.account-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.stat-box { text-align: center; padding: 10px; background: var(--bg-sunken); border-radius: var(--r-md); }
.stat-value { font-size: 18px; font-weight: 700; color: var(--fg-1); margin: 0; font-variant-numeric: tabular-nums; }
.stat-label { font-size: 11px; color: var(--fg-3); margin: 2px 0 0; }
.adjustment-form { border-top: 1px solid var(--border-subtle); padding-top: 12px; }
.form-label { display: block; font-size: 12px; font-weight: 500; color: var(--fg-2); margin-bottom: 4px; }
.transactions-list { border-top: 1px solid var(--border-subtle); padding-top: 12px; display: flex; flex-direction: column; gap: 6px; }
.tx-item { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.btn { font-family: var(--font-sans); font-weight: 500; font-size: 14px; padding: 8px 14px; border-radius: var(--r-md); border: 1px solid transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background var(--dur-base); line-height: 1.2; }
.btn-sm { font-size: 12px; padding: 5px 10px; }
.btn-primary { background: var(--halo-500); color: #fff; }
.btn-primary:hover:not(:disabled) { background: var(--halo-700); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: var(--bg-canvas); color: var(--fg-1); border-color: var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background: var(--bg-sunken); }
.btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-danger { background: var(--danger-bg); color: var(--danger-fg); border-color: var(--danger-border, var(--border-subtle)); }
.btn-danger:hover:not(:disabled) { filter: brightness(0.95); }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ghost { background: transparent; color: var(--fg-2); border-color: var(--border-subtle); }
.btn-ghost:hover { background: var(--bg-sunken); }
</style>
