<template>
  <AppLayout>
    <Head :title="simulation?.name || 'Simulation financière'" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ simulation?.name || '…' }}</h1>
        <p class="wh-page-subtitle" v-if="simulation">
          {{ simulation.granularity === 'week' ? 'Hebdomadaire' : 'Mensuelle' }} · {{ simulation.horizon_periods }} périodes à partir du {{ simulation.start_date }}
        </p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showAddLine = true"><i class="pi pi-plus" style="font-size:13px" /> Ajouter une hypothèse</button>
      </div>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <template v-else>
      <!-- Lines -->
      <div class="wh-panel" style="margin-bottom:16px">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600">Hypothèses de vente et d'achat</div>
        <table class="wh-dt" style="font-size:13px">
          <thead>
            <tr>
              <th>Type</th>
              <th>Produit / Libellé</th>
              <th class="num">Qté</th>
              <th class="num">Prix unit.</th>
              <th>Récurrence</th>
              <th class="num">Croissance %</th>
              <th>Statut</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in lines" :key="line.id" class="wh-dt-row">
              <td><span :style="{ color: line.type === 'sale' ? 'var(--success)' : 'var(--danger)' }">{{ line.type === 'sale' ? 'Vente' : 'Achat' }}</span></td>
              <td>{{ line.label || productName(line.product_id) || '—' }}</td>
              <td class="num">{{ line.quantity }}</td>
              <td class="num">{{ fmt(line.unit_price) }}</td>
              <td>{{ recurrenceLabel(line.recurrence) }}</td>
              <td class="num">{{ line.growth_rate_percent || 0 }}%</td>
              <td>
                <span class="wh-badge" :class="line.status === 'realized' ? 'badge-success' : ''">
                  {{ line.status === 'realized' ? 'Réalisée' : 'Simulée' }}
                </span>
              </td>
              <td style="white-space:nowrap">
                <button v-if="line.status === 'simulated'" class="btn btn-sm btn-secondary" @click="realize(line)" :disabled="realizing === line.id">
                  {{ realizing === line.id ? '…' : 'Réaliser' }}
                </button>
                <button v-if="line.status === 'simulated'" class="btn btn-sm btn-secondary" @click="removeLine(line)" style="margin-left:6px">
                  <i class="pi pi-trash" style="font-size:12px" />
                </button>
              </td>
            </tr>
            <tr v-if="!lines.length">
              <td colspan="8" style="text-align:center;padding:20px;color:var(--fg-3)">Aucune hypothèse. Ajoutez une vente ou un achat simulé.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Projection -->
      <div v-if="projection" class="wh-panel">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;gap:16px">
          <button v-for="tab in tabs" :key="tab.key" class="tab-btn" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
            {{ tab.label }}
          </button>
        </div>

        <div style="padding:16px">
          <svg class="chart" viewBox="0 0 600 200" preserveAspectRatio="none">
            <polyline :points="chartPoints(seriesA)" fill="none" stroke="var(--success)" stroke-width="2" />
            <polyline :points="chartPoints(seriesB)" fill="none" stroke="var(--danger)" stroke-width="2" />
          </svg>
          <div style="display:flex;gap:16px;font-size:12px;margin-top:4px">
            <span><span class="dot" style="background:var(--success)" /> {{ seriesALabel }}</span>
            <span><span class="dot" style="background:var(--danger)" /> {{ seriesBLabel }}</span>
          </div>

          <table class="wh-dt" style="font-size:12px;margin-top:16px">
            <thead>
              <tr>
                <th>Période</th>
                <th class="num">{{ seriesALabel }}</th>
                <th class="num">{{ seriesBLabel }}</th>
                <th class="num" v-if="activeTab !== 'tresorerie'">Résultat net</th>
                <th class="num" v-if="activeTab === 'bilan'">Équilibré</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in projection.periods" :key="p.label" class="wh-dt-row">
                <td>{{ p.label }}</td>
                <td class="num">{{ fmt(valueA(p)) }}</td>
                <td class="num">{{ fmt(valueB(p)) }}</td>
                <td class="num" v-if="activeTab !== 'tresorerie'">{{ fmt(p.compte_de_resultat.resultat_net) }}</td>
                <td class="num" v-if="activeTab === 'bilan'">{{ p.bilan_simplifie.equilibre ? '✓' : '✗' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Add line modal -->
    <div v-if="showAddLine" class="wh-modal-backdrop" @click.self="showAddLine = false">
      <div class="wh-modal" style="max-width:520px">
        <h3 style="margin-bottom:16px">Nouvelle hypothèse</h3>
        <div style="display:flex;flex-direction:column;gap:12px">
          <div style="display:flex;gap:12px">
            <div style="flex:1">
              <label class="wh-label">Type</label>
              <select v-model="lineForm.type" class="wh-input" style="width:100%">
                <option value="sale">Vente</option>
                <option value="purchase">Achat</option>
              </select>
            </div>
            <div style="flex:1">
              <label class="wh-label">Récurrence</label>
              <select v-model="lineForm.recurrence" class="wh-input" style="width:100%">
                <option value="once">Une fois</option>
                <option value="weekly">Hebdomadaire</option>
                <option value="monthly">Mensuelle</option>
              </select>
            </div>
          </div>
          <div>
            <label class="wh-label">Produit (optionnel — sinon prix/compte manuels)</label>
            <select v-model="lineForm.product_id" class="wh-input" style="width:100%">
              <option :value="null">— Aucun —</option>
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="wh-label">Libellé</label>
            <input v-model="lineForm.label" class="wh-input" style="width:100%" />
          </div>
          <div style="display:flex;gap:12px">
            <div style="flex:1">
              <label class="wh-label">Quantité</label>
              <input v-model.number="lineForm.quantity" type="number" min="0.0001" step="0.01" class="wh-input" style="width:100%" />
            </div>
            <div style="flex:1">
              <label class="wh-label">Prix unitaire (Ar, optionnel si produit choisi)</label>
              <input v-model.number="lineForm.unit_price" type="number" min="0" class="wh-input" style="width:100%" />
            </div>
          </div>
          <div style="display:flex;gap:12px">
            <div style="flex:1">
              <label class="wh-label">Date de début</label>
              <input v-model="lineForm.start_date" type="date" class="wh-input" style="width:100%" />
            </div>
            <div style="flex:1">
              <label class="wh-label">Croissance par cycle (%)</label>
              <input v-model.number="lineForm.growth_rate_percent" type="number" step="0.1" class="wh-input" style="width:100%" />
            </div>
          </div>
          <p v-if="lineError" style="color:var(--danger);font-size:13px">{{ lineError }}</p>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px">
          <button class="btn btn-secondary" @click="showAddLine = false">Annuler</button>
          <button class="btn btn-primary" @click="addLine" :disabled="addingLine">Ajouter</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ id: { type: Number, required: true } })

const loading = ref(false)
const simulation = ref(null)
const lines = ref([])
const projection = ref(null)
const products = ref([])
const showAddLine = ref(false)
const addingLine = ref(false)
const lineError = ref('')
const realizing = ref(null)
const activeTab = ref('compte_de_resultat')

const tabs = [
  { key: 'compte_de_resultat', label: 'Compte de résultat' },
  { key: 'tresorerie', label: 'Trésorerie' },
  { key: 'bilan', label: 'Bilan simplifié' },
]

const lineForm = reactive({
  type: 'sale',
  product_id: null,
  label: '',
  quantity: 1,
  unit_price: null,
  recurrence: 'once',
  start_date: new Date().toISOString().split('T')[0],
  growth_rate_percent: 0,
})

const seriesALabel = computed(() => ({
  compte_de_resultat: "Chiffre d'affaires",
  tresorerie: 'Trésorerie (ouverture)',
  bilan: 'Actif',
}[activeTab.value]))

const seriesBLabel = computed(() => ({
  compte_de_resultat: 'Charges',
  tresorerie: 'Trésorerie (fermeture)',
  bilan: 'Passif',
}[activeTab.value]))

function valueA(p) {
  if (activeTab.value === 'compte_de_resultat') return p.compte_de_resultat.chiffre_affaires
  if (activeTab.value === 'tresorerie') return p.tresorerie.ouverture
  return p.bilan_simplifie.actif.total
}
function valueB(p) {
  if (activeTab.value === 'compte_de_resultat') return p.compte_de_resultat.charges
  if (activeTab.value === 'tresorerie') return p.tresorerie.fermeture
  return p.bilan_simplifie.passif.total
}

const seriesA = computed(() => projection.value?.periods.map(valueA) ?? [])
const seriesB = computed(() => projection.value?.periods.map(valueB) ?? [])

function chartPoints(series) {
  if (!series.length) return ''
  const max = Math.max(...seriesA.value, ...seriesB.value, 1)
  const min = Math.min(...seriesA.value, ...seriesB.value, 0)
  const range = max - min || 1
  const stepX = 600 / Math.max(series.length - 1, 1)
  return series.map((v, i) => `${i * stepX},${200 - ((v - min) / range) * 180 - 10}`).join(' ')
}

function productName(id) {
  return products.value.find((p) => p.id === id)?.name
}

function recurrenceLabel(r) {
  return { once: 'Une fois', weekly: 'Hebdomadaire', monthly: 'Mensuelle' }[r] ?? r
}

function fmt(v) {
  if (v === null || v === undefined) return '—'
  return new Intl.NumberFormat('fr-MG', { maximumFractionDigits: 0 }).format(v) + ' Ar'
}

async function load() {
  loading.value = true
  try {
    const [{ data: simRes }, { data: projRes }] = await Promise.all([
      axios.get(`/api/v1/accounting/financial-simulations/${props.id}`),
      axios.get(`/api/v1/accounting/financial-simulations/${props.id}/project`),
    ])
    simulation.value = simRes.data
    lines.value = simRes.data.lines ?? []
    projection.value = projRes.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadProducts() {
  try {
    const { data } = await axios.get('/api/v1/inventory/products', { params: { per_page: 100 } })
    products.value = data.data ?? []
  } catch (e) {
    console.error(e)
  }
}

async function addLine() {
  lineError.value = ''
  addingLine.value = true
  try {
    const payload = { ...lineForm }
    if (!payload.unit_price) delete payload.unit_price
    if (!payload.product_id) delete payload.product_id
    await axios.post(`/api/v1/accounting/financial-simulations/${props.id}/lines`, payload)
    showAddLine.value = false
    await load()
  } catch (e) {
    lineError.value = e.response?.data?.message || 'Erreur lors de l\'ajout.'
  } finally {
    addingLine.value = false
  }
}

async function removeLine(line) {
  if (!confirm('Supprimer cette hypothèse ?')) return
  await axios.delete(`/api/v1/accounting/financial-simulation-lines/${line.id}`)
  await load()
}

async function realize(line) {
  if (!confirm('Réaliser cette ligne va créer une vraie commande et une écriture comptable réelle. Continuer ?')) return
  realizing.value = line.id
  try {
    await axios.post(`/api/v1/accounting/financial-simulation-lines/${line.id}/realize`)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Erreur lors de la réalisation.')
  } finally {
    realizing.value = null
  }
}

onMounted(() => {
  load()
  loadProducts()
})
</script>

<style scoped>
.num { text-align: right; font-variant-numeric: tabular-nums; }
.wh-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.wh-modal { background: var(--bg-1); border-radius: 8px; padding: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.3); }
.badge-success { background: var(--success); color: #fff; }
.tab-btn { background: none; border: none; padding: 8px 4px; font-weight: 600; color: var(--fg-3); cursor: pointer; border-bottom: 2px solid transparent; }
.tab-btn.active { color: var(--halo-blue); border-bottom-color: var(--halo-blue); }
.chart { width: 100%; height: 160px; }
.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
</style>
