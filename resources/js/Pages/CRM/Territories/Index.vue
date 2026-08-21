<template>
  <AppLayout>
    <Head title="Territory Management" />

    <!-- Chantier 32.15 (CRM deep 14-layer audit): this real, routed page never
         called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Territory Management</h1>
        <p class="wh-page-subtitle">Gérez vos territoires de vente et les quotas</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" :disabled="assigning" @click="autoAssignAll">
          <i class="pi pi-map-marker" style="font-size:13px" />
          {{ assigning ? 'Assignation…' : 'Auto-assigner' }}
        </button>
        <button class="btn btn-secondary" :disabled="rebalancing" @click="doRebalance">
          <i class="pi pi-sync" :class="{ 'pi-spin': rebalancing }" style="font-size:13px" />
          ✨ Rééquilibrage IA
        </button>
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" style="font-size:13px" />
          Nouveau territoire
        </button>
      </div>
    </div>

    <!-- KPI cards -->
    <div class="wh-kpi-row" style="margin-bottom:20px">
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Quota Total</div>
        <div class="wh-kpi-value">{{ fmtCurrency(totalQuota) }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Atteinte Moyenne</div>
        <div class="wh-kpi-value" :style="{ color: avgAttainment >= 80 ? '#22c55e' : avgAttainment >= 50 ? '#f59e0b' : '#ef4444' }">
          {{ avgAttainment.toFixed(1) }}%
        </div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Nb Territoires</div>
        <div class="wh-kpi-value">{{ territories.length }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Contacts assignés</div>
        <div class="wh-kpi-value">{{ totalContacts }}</div>
      </div>
    </div>

    <!-- Territory tree list -->
    <div class="wh-panel">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border-1);display:flex;gap:8px;align-items:center">
        <span style="font-weight:600;font-size:15px">Hiérarchie des territoires</span>
        <span style="margin-left:auto;font-size:12px;color:var(--fg-4)">{{ territories.length }} territoire(s)</span>
      </div>

      <DataTable
        :value="flatTree"
        :loading="loading"
        striped-rows
        style="font-size:13px"
      >
        <Column header="Territoire" style="min-width:200px">
          <template #body="{ data }">
            <span :style="{ paddingLeft: data.depth * 20 + 'px' }">
              <i v-if="data.depth > 0" class="pi pi-arrow-right" style="font-size:10px;margin-right:4px;color:var(--fg-4)" />
              <strong>{{ data.name }}</strong>
              <span style="color:var(--fg-4);margin-left:6px;font-size:11px">{{ data.code }}</span>
            </span>
          </template>
        </Column>
        <Column field="region" header="Région" style="width:120px" />
        <Column header="Propriétaire" style="width:150px">
          <template #body="{ data }">{{ data.assigned_to_name }}</template>
        </Column>
        <Column header="Quota" style="width:130px;text-align:right">
          <template #body="{ data }">{{ fmtCurrency(data.sales_target) }}</template>
        </Column>
        <Column header="YTD Rev." style="width:130px;text-align:right">
          <template #body="{ data }">{{ fmtCurrency(data.ytd_revenue) }}</template>
        </Column>
        <Column header="Atteinte %" style="width:120px">
          <template #body="{ data }">
            <div style="display:flex;align-items:center;gap:6px">
              <div style="flex:1;background:var(--surface-2);border-radius:4px;height:8px;overflow:hidden">
                <div
                  :style="{
                    width: Math.min(data.quota_attainment, 100) + '%',
                    height: '100%',
                    background: data.quota_attainment >= 80 ? '#22c55e' : data.quota_attainment >= 50 ? '#f59e0b' : '#ef4444',
                    borderRadius: '4px',
                  }"
                />
              </div>
              <span style="font-size:11px;min-width:36px;text-align:right">{{ data.quota_attainment?.toFixed(0) }}%</span>
            </div>
          </template>
        </Column>
        <Column header="Contacts" style="width:90px;text-align:right">
          <template #body="{ data }">
            <Tag :value="String(data.open_opportunities ?? 0)" severity="secondary" />
          </template>
        </Column>
        <Column header="" style="width:80px">
          <template #body="{ data }">
            <button class="btn btn-secondary" style="padding:4px 8px;font-size:12px" @click="openEdit(data)">
              Modifier
            </button>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Edit/Create Dialog -->
    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier territoire' : 'Nouveau territoire'" :style="{ width: '480px' }" modal>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="wh-label">Nom *</label>
          <InputText v-model="form.name" class="w-full" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="wh-label">Code *</label>
            <InputText v-model="form.code" class="w-full" :disabled="editing" />
          </div>
          <div>
            <label class="wh-label">Type</label>
            <Dropdown v-model="form.type" :options="typeOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>
        <div>
          <label class="wh-label">Quota (€)</label>
          <InputNumber v-model="form.sales_target" mode="decimal" :min-fraction-digits="2" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Propriétaire</label>
          <InputText v-model="form.assigned_to_name" class="w-full" placeholder="ID propriétaire" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showDialog = false" />
        <Button :label="editing ? 'Enregistrer' : 'Créer'" :loading="saving" @click="saveTerritory" />
      </template>
    </Dialog>

    <!-- AI rebalance result -->
    <Dialog v-model:visible="showAiResult" header="✨ Résultat du rééquilibrage IA" :style="{ width: '480px' }" modal>
      <div v-if="aiResult">
        <p style="margin-bottom:12px">
          <strong>{{ aiResult.rebalanced }}</strong> assignation(s) rééquilibrée(s) sur {{ territories.length }} territoire(s) actifs.
        </p>
        <p style="color:var(--fg-4);font-size:13px">Les contacts ont été redistribués équitablement. Rechargez pour voir les nouvelles métriques.</p>
      </div>
      <template #footer>
        <Button label="Fermer" @click="showAiResult = false; load()" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { Button, DataTable, Column, Dialog, Tag, InputText, Dropdown, InputNumber } from 'primevue'
import axios from 'axios'

// Chantier 32.15 (CRM deep 14-layer audit): this real, routed page never
// called useAiAssistant() at all before this fix.
const { guidance } = useAiAssistant('CRM', 'manage_territories')
const showAiPanel = ref(true)

const territories = ref([])
const loading = ref(false)
const saving = ref(false)
const assigning = ref(false)
const rebalancing = ref(false)
const showDialog = ref(false)
const showAiResult = ref(false)
const editing = ref(null)
const aiResult = ref(null)

const form = ref({ name: '', code: '', sales_target: 0, assigned_to_name: '', type: 'region' })

const typeOptions = [
  { label: 'Pays', value: 'country' },
  { label: 'Région', value: 'region' },
  { label: 'Zone', value: 'area' },
  { label: 'Secteur', value: 'zone' },
]

// Build flat tree (territories with depth level)
const flatTree = computed(() => {
  const result = []
  const roots = territories.value.filter(t => !t.parent_territory_id)
  const addLevel = (items, depth) => {
    items.forEach(t => {
      result.push({ ...t, depth })
      const children = territories.value.filter(c => c.parent_territory_id === t.id)
      if (children.length) addLevel(children, depth + 1)
    })
  }
  addLevel(roots, 0)
  // Add orphaned (parent not in list)
  territories.value.forEach(t => {
    if (!result.find(r => r.id === t.id)) result.push({ ...t, depth: 0 })
  })
  return result
})

const totalQuota = computed(() => territories.value.reduce((s, t) => s + (t.sales_target || 0), 0))
const avgAttainment = computed(() => {
  if (!territories.value.length) return 0
  return territories.value.reduce((s, t) => s + (t.quota_attainment || 0), 0) / territories.value.length
})
const totalContacts = computed(() => territories.value.reduce((s, t) => s + (t.open_opportunities || 0), 0))

function fmtCurrency(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(val || 0)
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/crm/territories', { params: { per_page: 200 } })
    territories.value = data.data || []
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', code: '', sales_target: 0, assigned_to_name: '', type: 'region' }
  showDialog.value = true
}

function openEdit(territory) {
  editing.value = territory
  form.value = {
    name: territory.name,
    code: territory.code,
    sales_target: territory.sales_target,
    assigned_to_name: territory.assigned_to_name,
    type: territory.type || 'region',
  }
  showDialog.value = true
}

async function saveTerritory() {
  saving.value = true
  try {
    if (editing.value) {
      await axios.put(`/api/v1/crm/territories/${editing.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/crm/territories', form.value)
    }
    showDialog.value = false
    await load()
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}

async function autoAssignAll() {
  assigning.value = true
  try {
    await axios.post('/api/v1/ai/ask', {
      question: 'Auto-assigner les contacts non assignés aux territoires selon les règles',
      module: 'CRM',
    })
  } catch (e) {
    console.error(e)
  } finally {
    assigning.value = false
  }
}

async function doRebalance() {
  rebalancing.value = true
  try {
    const { data } = await axios.post('/api/v1/crm/territories/rebalance')
    aiResult.value = data
    showAiResult.value = true
  } catch (e) {
    console.error(e)
  } finally {
    rebalancing.value = false
  }
}

onMounted(load)
</script>
