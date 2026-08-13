<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface KpiData {
  total_shipments: number
  today: number
  delivered: number
  in_transit: number
  exceptions: number
  on_time_rate_pct: number
  exception_rate_pct: number
  avg_cost: number
  total_co2_kg: number
}

interface ShipmentStats {
  by_status: Record<string, number>
  by_mode: Record<string, number>
  by_type: Record<string, number>
}

const kpis         = ref<KpiData | null>(null)
const stats        = ref<ShipmentStats | null>(null)
const loading      = ref(false)

async function loadAll() {
  loading.value = true
  try {
    const [kpiRes, statsRes] = await Promise.all([
      fetch('/api/v1/logistics/analytics/kpis', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      }),
      fetch('/api/v1/logistics/analytics/shipment-stats', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      }),
    ])
    kpis.value  = await kpiRes.json()
    stats.value = await statsRes.json()
  } finally { loading.value = false }
}

function formatNumber(n?: number): string {
  if (n === undefined || n === null) return '—'
  return new Intl.NumberFormat('fr-FR').format(n)
}

function formatPct(n?: number): string {
  if (n === undefined || n === null) return '—'
  return n.toFixed(1) + '%'
}

function formatCo2(kg?: number): string {
  if (!kg) return '—'
  return kg >= 1000 ? (kg / 1000).toFixed(2) + ' t' : kg.toFixed(1) + ' kg'
}

onMounted(loadAll)
</script>

<template>
  <AppLayout>
    <Head title="Analytiques Logistique" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Analytiques Logistique</h1>
        <p class="wh-page-subtitle">Vue d'ensemble des performances logistiques</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="loadAll">
          <i class="pi pi-refresh" style="font-size:13px" /> Actualiser
        </button>
      </div>
    </div>

    <div v-if="loading" style="padding:60px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:32px" />
    </div>

    <template v-else>
      <!-- KPI Cards -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:20px">
        <div class="wh-panel" style="padding:18px">
          <div style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Total expéditions</div>
          <div style="font-size:28px;font-weight:700;color:var(--fg-1)">{{ formatNumber(kpis?.total_shipments) }}</div>
          <div style="font-size:11px;color:var(--fg-3);margin-top:4px">{{ formatNumber(kpis?.today) }} aujourd'hui</div>
        </div>
        <div class="wh-panel" style="padding:18px">
          <div style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">En transit</div>
          <div style="font-size:28px;font-weight:700;color:#3b82f6">{{ formatNumber(kpis?.in_transit) }}</div>
        </div>
        <div class="wh-panel" style="padding:18px">
          <div style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Livrées</div>
          <div style="font-size:28px;font-weight:700;color:#22c55e">{{ formatNumber(kpis?.delivered) }}</div>
        </div>
        <div class="wh-panel" style="padding:18px">
          <div style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Taux ponctualité</div>
          <div style="font-size:28px;font-weight:700;color:var(--halo-600)">{{ formatPct(kpis?.on_time_rate_pct) }}</div>
        </div>
        <div class="wh-panel" style="padding:18px">
          <div style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Exceptions</div>
          <div style="font-size:28px;font-weight:700;color:#ef4444">{{ formatNumber(kpis?.exceptions) }}</div>
          <div style="font-size:11px;color:var(--fg-3);margin-top:4px">{{ formatPct(kpis?.exception_rate_pct) }} du total</div>
        </div>
        <div class="wh-panel" style="padding:18px">
          <div style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">CO2 total</div>
          <div style="font-size:28px;font-weight:700;color:#10b981">{{ formatCo2(kpis?.total_co2_kg) }}</div>
        </div>
      </div>

      <!-- Stats breakdowns -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <!-- By mode -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border-subtle);font-weight:600;font-size:14px">Par mode de transport</div>
          <table class="wh-dt" v-if="stats?.by_mode">
            <thead><tr><th>Mode</th><th style="text-align:right">Expéditions</th></tr></thead>
            <tbody>
              <tr v-for="(count, mode) in stats.by_mode" :key="mode" class="wh-dt-row">
                <td>
                  <span class="wh-badge badge-blue">{{ mode }}</span>
                </td>
                <td style="text-align:right;font-weight:600">{{ formatNumber(count) }}</td>
              </tr>
              <tr v-if="!Object.keys(stats.by_mode).length">
                <td colspan="2" style="text-align:center;padding:20px;color:var(--fg-3)">Aucune donnée</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- By status -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border-subtle);font-weight:600;font-size:14px">Par statut</div>
          <table class="wh-dt" v-if="stats?.by_status">
            <thead><tr><th>Statut</th><th style="text-align:right">Expéditions</th></tr></thead>
            <tbody>
              <tr v-for="(count, status) in stats.by_status" :key="status" class="wh-dt-row">
                <td>
                  <span class="wh-badge badge-gray">{{ status }}</span>
                </td>
                <td style="text-align:right;font-weight:600">{{ formatNumber(count) }}</td>
              </tr>
              <tr v-if="!Object.keys(stats?.by_status ?? {}).length">
                <td colspan="2" style="text-align:center;padding:20px;color:var(--fg-3)">Aucune donnée</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- By type -->
      <div class="wh-panel" style="margin-bottom:20px">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border-subtle);font-weight:600;font-size:14px">Par type d'expédition</div>
        <table class="wh-dt" v-if="stats?.by_type">
          <thead><tr><th>Type</th><th style="text-align:right">Expéditions</th></tr></thead>
          <tbody>
            <tr v-for="(count, type) in stats.by_type" :key="type" class="wh-dt-row">
              <td><span class="wh-badge badge-blue">{{ type }}</span></td>
              <td style="text-align:right;font-weight:600">{{ formatNumber(count) }}</td>
            </tr>
            <tr v-if="!Object.keys(stats?.by_type ?? {}).length">
              <td colspan="2" style="text-align:center;padding:20px;color:var(--fg-3)">Aucune donnée</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </AppLayout>
</template>
