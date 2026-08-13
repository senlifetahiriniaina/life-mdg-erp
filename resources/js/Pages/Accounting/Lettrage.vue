<template>
  <AppLayout>
    <Head title="Lettrage" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Accounting · Lettrage des comptes</h1>
        <p class="wh-page-subtitle">Rapprochement des lignes clients / fournisseurs</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="padding:12px 16px;margin-bottom:16px;display:flex;gap:12px;align-items:center">
      <div style="position:relative;flex:1;min-width:160px;max-width:260px">
        <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
        <input v-model="filters.account_code" placeholder="Code compte (ex: 411)" class="wh-filter-input" @input="loadLines" />
      </div>
      <button class="btn btn-secondary" @click="loadLines" :disabled="loading">
        <i class="pi pi-refresh" style="font-size:13px" /> Actualiser
      </button>
      <button
        class="btn btn-primary"
        :disabled="selected.length < 2 || matchLoading"
        @click="doMatch"
      >
        <i class="pi pi-link" style="font-size:13px" />
        Lettrer la sélection ({{ selected.length }})
      </button>
    </div>

    <!-- Table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th style="width:40px">
              <input type="checkbox" @change="toggleAll" :checked="allSelected" />
            </th>
            <th>Facture</th>
            <th>Partenaire</th>
            <th>Compte</th>
            <th>Description</th>
            <th class="num">Montant</th>
            <th>Lettrage</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="line in lines" :key="line.id" class="wh-dt-row" :class="{ selected: selected.includes(line.id) }">
            <td>
              <input type="checkbox" :value="line.id" v-model="selected" />
            </td>
            <td>
              <span :class="['badge', line.invoice_type === 'invoice' ? 'badge-blue' : 'badge-orange']" style="font-size:10px;margin-right:6px">
                {{ line.invoice_type === 'invoice' ? 'Client' : 'Fourn.' }}
              </span>
              {{ line.invoice_number }}
            </td>
            <td>{{ line.partner_name }}</td>
            <td style="font-family:monospace;font-size:12px">{{ line.account_code }} — {{ line.account_name }}</td>
            <td style="color:var(--fg-3);font-size:12px">{{ line.description || '—' }}</td>
            <td class="num">{{ fmt(line.total) }}</td>
            <td>
              <span v-if="line.match_ref" style="font-family:monospace;font-size:11px;background:var(--success-bg);color:var(--success);padding:2px 6px;border-radius:4px">
                {{ line.match_ref.substring(0,8) }}…
              </span>
              <span v-else style="color:var(--fg-4);font-size:12px">Non lettré</span>
            </td>
          </tr>
          <tr v-if="!loading && lines.length === 0">
            <td colspan="7" style="text-align:center;padding:32px;color:var(--fg-3)">
              Aucune ligne non lettrée trouvée.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const loading = ref(false)
const matchLoading = ref(false)
const lines = ref([])
const selected = ref([])

const filters = reactive({ account_code: '' })

const allSelected = computed(() =>
  lines.value.length > 0 && lines.value.every(l => selected.value.includes(l.id))
)

function toggleAll(e) {
  selected.value = e.target.checked ? lines.value.map(l => l.id) : []
}

async function loadLines() {
  loading.value = true
  selected.value = []
  try {
    const params = {}
    if (filters.account_code) params.account_code = filters.account_code
    const { data } = await axios.get('/api/v1/accounting/matching', { params })
    lines.value = data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function doMatch() {
  if (selected.value.length < 2) return
  matchLoading.value = true
  try {
    await axios.post('/api/v1/accounting/matching/match', { line_ids: selected.value })
    await loadLines()
  } catch (e) {
    console.error(e)
  } finally {
    matchLoading.value = false
  }
}

function fmt(v) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}

onMounted(loadLines)
</script>

<style scoped>
.num { text-align: right; font-variant-numeric: tabular-nums; }
.selected td { background: var(--halo-blue-10) !important; }
.badge-blue   { background: var(--halo-50); color: var(--halo-700); padding:2px 6px; border-radius:4px; }
.badge-orange { background: var(--yellow-50); color: var(--yellow-600); padding:2px 6px; border-radius:4px; }
</style>
