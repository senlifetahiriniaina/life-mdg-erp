<template>
  <AppLayout>
    <Head title="Simulations financières" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Accounting · Simulations financières</h1>
        <p class="wh-page-subtitle">Simulez des ventes/achats futurs et projetez les états financiers, semaine après semaine ou mois après mois.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvelle simulation
        </button>
      </div>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <div v-else class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Granularité</th>
            <th>Début</th>
            <th class="num">Périodes</th>
            <th class="num">Lignes</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sim in simulations" :key="sim.id" class="wh-dt-row" style="cursor:pointer" @click="open(sim)">
            <td>{{ sim.name }}</td>
            <td>{{ sim.granularity === 'week' ? 'Semaine' : 'Mois' }}</td>
            <td>{{ sim.start_date }}</td>
            <td class="num">{{ sim.horizon_periods }}</td>
            <td class="num">{{ sim.lines_count ?? 0 }}</td>
            <td><span class="wh-badge">{{ statusLabel(sim.status) }}</span></td>
          </tr>
          <tr v-if="!simulations.length">
            <td colspan="6" style="text-align:center;padding:24px;color:var(--fg-3)">Aucune simulation. Créez-en une pour commencer.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create modal -->
    <div v-if="showCreate" class="wh-modal-backdrop" @click.self="showCreate = false">
      <div class="wh-modal" style="max-width:480px">
        <h3 style="margin-bottom:16px">Nouvelle simulation</h3>
        <div style="display:flex;flex-direction:column;gap:12px">
          <div>
            <label class="wh-label">Nom</label>
            <input v-model="form.name" class="wh-input" style="width:100%" placeholder="Ex. Croissance T4 2026" />
          </div>
          <div style="display:flex;gap:12px">
            <div style="flex:1">
              <label class="wh-label">Granularité</label>
              <select v-model="form.granularity" class="wh-input" style="width:100%">
                <option value="week">Semaine</option>
                <option value="month">Mois</option>
              </select>
            </div>
            <div style="flex:1">
              <label class="wh-label">Horizon (nb périodes)</label>
              <input v-model.number="form.horizon_periods" type="number" min="1" max="104" class="wh-input" style="width:100%" />
            </div>
          </div>
          <div>
            <label class="wh-label">Date de début</label>
            <input v-model="form.start_date" type="date" class="wh-input" style="width:100%" />
          </div>
          <div>
            <label class="wh-label">Trésorerie d'ouverture (Ar, optionnel — sinon dérivée des comptes bancaires réels)</label>
            <input v-model.number="form.opening_cash_balance" type="number" min="0" class="wh-input" style="width:100%" />
          </div>
          <p v-if="error" style="color:var(--danger);font-size:13px">{{ error }}</p>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px">
          <button class="btn btn-secondary" @click="showCreate = false">Annuler</button>
          <button class="btn btn-primary" @click="create" :disabled="creating">Créer</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const loading = ref(false)
const simulations = ref([])
const showCreate = ref(false)
const creating = ref(false)
const error = ref('')

const form = reactive({
  name: '',
  granularity: 'month',
  start_date: new Date().toISOString().split('T')[0],
  horizon_periods: 12,
  opening_cash_balance: null,
})

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/financial-simulations')
    simulations.value = data.data ?? []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function create() {
  error.value = ''
  creating.value = true
  try {
    const payload = { ...form }
    if (!payload.opening_cash_balance) delete payload.opening_cash_balance
    const { data } = await axios.post('/api/v1/accounting/financial-simulations', payload)
    showCreate.value = false
    router.visit(`/accounting/financial-simulations/${data.data.id}`)
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur lors de la création.'
  } finally {
    creating.value = false
  }
}

function open(sim) {
  router.visit(`/accounting/financial-simulations/${sim.id}`)
}

function statusLabel(s) {
  return { draft: 'Brouillon', active: 'Active', archived: 'Archivée' }[s] ?? s
}

onMounted(load)
</script>

<style scoped>
.num { text-align: right; font-variant-numeric: tabular-nums; }
.wh-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.wh-modal { background: var(--bg-1); border-radius: 8px; padding: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.3); }
</style>
