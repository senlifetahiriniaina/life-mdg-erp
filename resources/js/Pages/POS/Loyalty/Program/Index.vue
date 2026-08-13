<template>
  <AppLayout>
    <Head title="Loyalty Program" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">POS · Loyalty Program</h1>
        <p class="wh-page-subtitle">Configure tiers, rewards and track member statistics</p>
      </div>
    </div>

    <!-- Statistics -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
      <!-- Donut chart - tier distribution -->
      <div class="wh-panel">
        <h3 style="font-size:14px;font-weight:600;margin-bottom:16px">Members by Tier</h3>
        <div v-if="stats">
          <div id="tier-chart" />
        </div>
        <p style="color:var(--fg-3);font-size:13px;margin:0">
          Total members: <strong>{{ stats?.total_members ?? 0 }}</strong>
        </p>
      </div>

      <!-- Points stats -->
      <div class="wh-panel">
        <h3 style="font-size:14px;font-weight:600;margin-bottom:16px">Points Distributed</h3>
        <div style="font-size:36px;font-weight:700;color:var(--primary)">
          {{ (stats?.points_distributed ?? 0).toLocaleString() }}
        </div>
        <p style="color:var(--fg-3);font-size:13px;margin-top:6px">Total points earned by all members</p>
      </div>
    </div>

    <!-- Tier configuration -->
    <div class="wh-panel" style="margin-bottom:24px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h3 style="font-size:14px;font-weight:600;margin:0">Tiers Configuration</h3>
        <Button label="Add Tier" icon="pi pi-plus" size="small" @click="openAddTier" />
      </div>

      <DataTable :value="tiers" :loading="loadingTiers" dataKey="id">
        <Column header="Color" style="width:60px">
          <template #body="{ data }">
            <div :style="{ width:'20px', height:'20px', borderRadius:'50%', background: data.color }" />
          </template>
        </Column>
        <Column field="name" header="Tier Name" />
        <Column field="min_points" header="Min Points" style="width:120px" />
        <Column field="discount_pct" header="Discount %" style="width:110px">
          <template #body="{ data }">{{ data.discount_pct }}%</template>
        </Column>
        <Column header="Perks" style="width:200px">
          <template #body="{ data }">
            <span style="font-size:12px;color:var(--fg-3)">
              {{ data.perks ? data.perks.join(', ') : '—' }}
            </span>
          </template>
        </Column>
        <Column header="" style="width:80px">
          <template #body="{ data }">
            <Button icon="pi pi-trash" severity="danger" text size="small" @click="deleteTier(data.id)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Rewards catalogue -->
    <div class="wh-panel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h3 style="font-size:14px;font-weight:600;margin:0">Rewards Catalogue</h3>
        <Button label="Add Reward" icon="pi pi-plus" size="small" @click="openAddReward" />
      </div>

      <DataTable :value="rewards" :loading="loadingRewards" dataKey="id">
        <Column field="name" header="Reward Name" />
        <Column field="type" header="Type" style="width:130px">
          <template #body="{ data }">
            <Tag :value="data.type" severity="info" />
          </template>
        </Column>
        <Column field="points_cost" header="Points Required" style="width:140px" />
        <Column field="value" header="Value" style="width:100px">
          <template #body="{ data }">{{ formatCurrency(data.value) }}</template>
        </Column>
        <Column field="active" header="Active" style="width:80px">
          <template #body="{ data }">
            <Tag :value="data.active ? $t('common.active') : $t('common.inactive')" :severity="data.active ? 'success' : 'secondary'" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Add Tier Dialog -->
    <Dialog v-model:visible="showAddTier" header="Add Loyalty Tier" :modal="true" style="width:420px">
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label class="wh-label">Name *</label>
          <InputText v-model="tierForm.name" placeholder="e.g. Gold" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Min Points *</label>
          <InputNumber v-model="tierForm.min_points" :min="0" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Discount % *</label>
          <InputNumber v-model="tierForm.discount_pct" :min="0" :max="100" :maxFractionDigits="2" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Color (hex)</label>
          <InputText v-model="tierForm.color" placeholder="#3b82f6" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showAddTier = false" />
        <Button label="Save" :loading="saving" @click="saveTier" />
      </template>
    </Dialog>

    <!-- Add Reward Dialog -->
    <Dialog v-model:visible="showAddReward" header="Add Reward" :modal="true" style="width:420px">
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label class="wh-label">Name *</label>
          <InputText v-model="rewardForm.name" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Type *</label>
          <Dropdown
            v-model="rewardForm.type"
            :options="rewardTypes"
            optionLabel="label"
            optionValue="value"
            class="w-full"
          />
        </div>
        <div>
          <label class="wh-label">Points Required *</label>
          <InputNumber v-model="rewardForm.points_cost" :min="1" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Value (amount / qty) *</label>
          <InputNumber v-model="rewardForm.value" :min="0" :maxFractionDigits="2" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showAddReward = false" />
        <Button label="Save" :loading="saving" @click="saveReward" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, InputNumber, Dropdown } from 'primevue'
import axios from 'axios'

interface LoyaltyTier {
  id: number
  name: string
  min_points: number
  discount_pct: string
  color: string
  perks: string[] | null
}

interface LoyaltyReward {
  id: number
  name: string
  type: string
  points_cost: number
  value: string
  active: boolean
}

interface Stats {
  total_members: number
  points_distributed: number
  tier_distribution: { tier: string; color: string; members: number }[]
}

const tiers          = ref<LoyaltyTier[]>([])
const rewards        = ref<LoyaltyReward[]>([])
const stats          = ref<Stats | null>(null)
const loadingTiers   = ref(false)
const loadingRewards = ref(false)
const saving         = ref(false)
const showAddTier    = ref(false)
const showAddReward  = ref(false)

const tierForm = ref({ name: '', min_points: 0, discount_pct: 0, color: '#6366f1' })
const rewardForm = ref({ name: '', type: 'discount', points_cost: 100, value: 0 })

const rewardTypes = [
  { label: 'Discount', value: 'discount' },
  { label: 'Free Product', value: 'free_product' },
  { label: 'Gift', value: 'gift' },
]

async function loadAll(): Promise<void> {
  loadingTiers.value   = true
  loadingRewards.value = true

  const [tiersRes, rewardsRes, statsRes] = await Promise.all([
    axios.get('/api/v1/pos/loyalty-program/tiers'),
    axios.get('/api/v1/pos/loyalty-program/rewards'),
    axios.get('/api/v1/pos/loyalty-program/stats'),
  ])

  tiers.value   = tiersRes.data
  rewards.value = rewardsRes.data
  stats.value   = statsRes.data

  loadingTiers.value   = false
  loadingRewards.value = false
}

function openAddTier(): void {
  tierForm.value = { name: '', min_points: 0, discount_pct: 0, color: '#6366f1' }
  showAddTier.value = true
}

async function saveTier(): Promise<void> {
  saving.value = true
  try {
    await axios.post('/api/v1/pos/loyalty-program/tiers', tierForm.value)
    await loadAll()
    showAddTier.value = false
  } finally {
    saving.value = false
  }
}

async function deleteTier(id: number): Promise<void> {
  await axios.delete(`/api/v1/pos/loyalty-program/tiers/${id}`)
  await loadAll()
}

function openAddReward(): void {
  rewardForm.value = { name: '', type: 'discount', points_cost: 100, value: 0 }
  showAddReward.value = true
}

async function saveReward(): Promise<void> {
  saving.value = true
  try {
    await axios.post('/api/v1/pos/loyalty-program/rewards', rewardForm.value)
    await loadAll()
    showAddReward.value = false
  } finally {
    saving.value = false
  }
}

function formatCurrency(value: string | number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value))
}

onMounted(loadAll)
</script>
