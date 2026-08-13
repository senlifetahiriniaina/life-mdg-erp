<template>
  <AppLayout>
    <Head title="Opportunity Scoring" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Opportunity Scoring</h1>
        <p class="wh-page-subtitle">ML-powered predictive scores for your pipeline</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" :disabled="rescoring" @click="rescoreAll">
          <i class="pi pi-refresh" :class="{ 'pi-spin': rescoring }" style="font-size:13px" />
          {{ rescoring ? 'Rescoring…' : 'Rescore All' }}
        </button>
      </div>
    </div>

    <!-- KPI cards -->
    <div class="wh-kpi-row" style="margin-bottom:20px">
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Average Score</div>
        <div class="wh-kpi-value">{{ kpis.avgScore }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Grade A</div>
        <div class="wh-kpi-value" style="color:#22c55e">{{ kpis.gradeAPercent }}%</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Grade B</div>
        <div class="wh-kpi-value" style="color:#3b82f6">{{ kpis.gradeBPercent }}%</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Unscored</div>
        <div class="wh-kpi-value" style="color:#ef4444">{{ kpis.unscored }}</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

      <!-- Left: Opportunity scores table -->
      <div class="wh-panel">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border-1);display:flex;gap:8px;align-items:center">
          <span style="font-weight:600;font-size:15px">Scored Opportunities</span>
          <div style="margin-left:auto;display:flex;gap:8px">
            <Select
              v-model="gradeFilter"
              :options="gradeOptions"
              option-label="label"
              option-value="value"
              placeholder="All grades"
              show-clear
              style="width:140px"
              @change="loadScores"
            />
          </div>
        </div>

        <DataTable
          :value="scores"
          :loading="loadingScores"
          striped-rows
          style="font-size:13px"
        >
          <Column field="opportunity.name" header="Opportunity" />
          <Column field="opportunity.stage" header="Stage">
            <template #body="{ data }">
              <Tag :value="data.opportunity?.stage ?? '—'" severity="secondary" />
            </template>
          </Column>
          <Column header="Score" style="width:200px">
            <template #body="{ data }">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="flex:1;background:var(--surface-2);border-radius:4px;height:8px;overflow:hidden">
                  <div
                    :style="{
                      width: data.total_score + '%',
                      height: '100%',
                      background: scoreColor(data.grade),
                      borderRadius: '4px',
                      transition: 'width .4s'
                    }"
                  />
                </div>
                <span style="min-width:28px;font-weight:600;font-size:12px">{{ data.total_score }}</span>
              </div>
            </template>
          </Column>
          <Column header="Grade" style="width:70px;text-align:center">
            <template #body="{ data }">
              <span
                :style="{
                  display:'inline-block',
                  minWidth:'28px',
                  textAlign:'center',
                  padding:'2px 8px',
                  borderRadius:'6px',
                  fontWeight:'700',
                  fontSize:'13px',
                  background: gradeBg(data.grade),
                  color: gradeColor(data.grade),
                }"
              >{{ data.grade }}</span>
            </template>
          </Column>
          <Column header="Win Prob." style="width:90px">
            <template #body="{ data }">
              {{ ((data.win_probability ?? 0) * 100).toFixed(0) }}%
            </template>
          </Column>
          <Column header="Scored At" style="width:120px">
            <template #body="{ data }">
              {{ data.scored_at ? new Date(data.scored_at).toLocaleDateString() : '—' }}
            </template>
          </Column>
        </DataTable>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" style="padding:12px 16px;display:flex;justify-content:flex-end;gap:4px">
          <button
            v-for="p in pagination.last_page"
            :key="p"
            class="btn"
            :class="p === pagination.current_page ? 'btn-primary' : 'btn-secondary'"
            style="min-width:32px;padding:4px 8px"
            @click="loadScores(p)"
          >{{ p }}</button>
        </div>
      </div>

      <!-- Right: Scoring rules panel -->
      <div class="wh-panel">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border-1);display:flex;align-items:center">
          <span style="font-weight:600;font-size:15px">Scoring Rules</span>
          <button class="btn btn-primary" style="margin-left:auto;padding:4px 12px;font-size:12px" @click="openCreateRule">
            <i class="pi pi-plus" style="font-size:11px" /> Add Rule
          </button>
        </div>

        <div v-if="loadingRules" style="padding:20px;text-align:center;color:var(--fg-4)">Loading…</div>
        <div v-else>
          <div
            v-for="rule in rules"
            :key="rule.id"
            style="padding:10px 16px;border-bottom:1px solid var(--border-1);display:flex;align-items:center;gap:8px"
          >
            <div style="flex:1">
              <div style="font-weight:500;font-size:13px">{{ rule.name }}</div>
              <div style="font-size:11px;color:var(--fg-4)">
                {{ rule.condition_field }} {{ rule.condition_operator }} {{ rule.condition_value }}
                &nbsp;·&nbsp; <strong>{{ rule.points }}pts</strong>
                &nbsp;·&nbsp; w={{ rule.weight }}
              </div>
            </div>
            <Tag :value="rule.category" severity="secondary" style="font-size:10px" />
            <button class="btn btn-secondary" style="padding:2px 8px;font-size:11px" @click="openEditRule(rule)">
              <i class="pi pi-pencil" />
            </button>
            <button class="btn btn-danger" style="padding:2px 8px;font-size:11px" @click="deleteRule(rule.id)">
              <i class="pi pi-trash" />
            </button>
          </div>
          <div v-if="rules.length === 0" style="padding:20px;text-align:center;color:var(--fg-4);font-size:13px">
            No scoring rules defined yet.
          </div>
        </div>
      </div>
    </div>

    <!-- Rule dialog -->
    <Dialog
      v-model:visible="ruleDialog"
      :header="editingRule ? 'Edit Rule' : 'New Scoring Rule'"
      modal
      :style="{ width: '480px' }"
    >
      <div style="display:flex;flex-direction:column;gap:14px;padding:4px 0">
        <div>
          <label class="wh-label">Rule Name</label>
          <InputText v-model="ruleForm.name" class="wh-input" placeholder="e.g. Large Deal Bonus" />
        </div>
        <div>
          <label class="wh-label">Category</label>
          <Select
            v-model="ruleForm.category"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            placeholder="Select category"
            class="wh-input"
          />
        </div>
        <div>
          <label class="wh-label">Criterion / Condition Field</label>
          <Select
            v-model="ruleForm.condition_field"
            :options="criterionOptions"
            option-label="label"
            option-value="value"
            placeholder="Select criterion"
            class="wh-input"
          />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label class="wh-label">Operator</label>
            <Select
              v-model="ruleForm.condition_operator"
              :options="operatorOptions"
              option-label="label"
              option-value="value"
              placeholder="Operator"
              class="wh-input"
            />
          </div>
          <div>
            <label class="wh-label">Value</label>
            <InputText v-model="ruleForm.condition_value" class="wh-input" placeholder="e.g. 50000" />
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label class="wh-label">Points</label>
            <InputText v-model.number="ruleForm.points" type="number" class="wh-input" placeholder="e.g. 20" />
          </div>
          <div>
            <label class="wh-label">Weight ({{ ruleForm.weight }})</label>
            <input
              v-model.number="ruleForm.weight"
              type="range"
              min="1"
              max="10"
              style="width:100%;margin-top:8px"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <button class="btn btn-secondary" @click="ruleDialog = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="savingRule" @click="saveRule">
          {{ savingRule ? 'Saving…' : 'Save Rule' }}
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { DataTable, Column, Tag, Dialog, InputText, Select } from 'primevue'
import axios from 'axios'

// ── State ───────────────────────────────────────────────────────────────────

const scores        = ref([])
const rules         = ref([])
const loadingScores = ref(false)
const loadingRules  = ref(false)
const rescoring     = ref(false)
const gradeFilter   = ref(null)
const pagination    = ref({ current_page: 1, last_page: 1, total: 0 })

const ruleDialog  = ref(false)
const editingRule = ref(null)
const savingRule  = ref(false)

const ruleFormDefault = () => ({
  name: '',
  category: '',
  condition_field: '',
  condition_operator: 'gt',
  condition_value: '',
  points: 10,
  weight: 1,
  is_active: true,
})
const ruleForm = ref(ruleFormDefault())

// ── Options ──────────────────────────────────────────────────────────────────

const gradeOptions = [
  { label: 'Grade A', value: 'A' },
  { label: 'Grade B', value: 'B' },
  { label: 'Grade C', value: 'C' },
  { label: 'Grade D', value: 'D' },
  { label: 'Grade F', value: 'F' },
]

const categoryOptions = [
  { label: 'Engagement', value: 'engagement' },
  { label: 'Fit',        value: 'fit' },
  { label: 'Velocity',   value: 'velocity' },
  { label: 'History',    value: 'history' },
]

const criterionOptions = [
  { label: 'Deal Size',      value: 'deal_size' },
  { label: 'Days Open',      value: 'days_in_stage' },
  { label: 'Stage',          value: 'stage' },
  { label: 'Activity Count', value: 'activities_count' },
  { label: 'Probability',    value: 'probability' },
  { label: 'Demo Requested', value: 'demo_requested' },
  { label: 'Email Opens',    value: 'email_opens' },
  { label: 'Meetings',       value: 'meetings_count' },
]

const operatorOptions = [
  { label: '=',        value: 'eq' },
  { label: '>',        value: 'gt' },
  { label: '<',        value: 'lt' },
  { label: '>=',       value: 'gte' },
  { label: '<=',       value: 'lte' },
  { label: 'contains', value: 'contains' },
  { label: 'exists',   value: 'exists' },
]

// ── KPIs ─────────────────────────────────────────────────────────────────────

const kpis = computed(() => {
  const total = scores.value.length
  if (!total) return { avgScore: 0, gradeAPercent: 0, gradeBPercent: 0, unscored: 0 }
  const avg   = Math.round(scores.value.reduce((s, r) => s + (r.total_score ?? 0), 0) / total)
  const aCount = scores.value.filter(r => r.grade === 'A').length
  const bCount = scores.value.filter(r => r.grade === 'B').length
  return {
    avgScore:       avg,
    gradeAPercent:  Math.round((aCount / total) * 100),
    gradeBPercent:  Math.round((bCount / total) * 100),
    unscored:       0, // from API in real use
  }
})

// ── Helpers ──────────────────────────────────────────────────────────────────

function scoreColor(grade) {
  return { A: '#22c55e', B: '#3b82f6', C: '#f97316', D: '#ef4444', F: '#9ca3af' }[grade] ?? '#9ca3af'
}
function gradeColor(grade) {
  return { A: '#15803d', B: '#1d4ed8', C: '#c2410c', D: '#b91c1c', F: '#4b5563' }[grade] ?? '#4b5563'
}
function gradeBg(grade) {
  return { A: '#dcfce7', B: '#dbeafe', C: '#ffedd5', D: '#fee2e2', F: '#f3f4f6' }[grade] ?? '#f3f4f6'
}

// ── API calls ─────────────────────────────────────────────────────────────────

async function loadScores(page = 1) {
  loadingScores.value = true
  try {
    const params = { page }
    if (gradeFilter.value) params.grade = gradeFilter.value
    const { data } = await axios.get('/api/v1/crm/opportunity-scores', { params })
    scores.value = data.data ?? data
    if (data.meta) {
      pagination.value = {
        current_page: data.meta.current_page,
        last_page:    data.meta.last_page,
        total:        data.meta.total,
      }
    }
  } finally {
    loadingScores.value = false
  }
}

async function loadRules() {
  loadingRules.value = true
  try {
    const { data } = await axios.get('/api/v1/crm/scoring-rules')
    rules.value = data.data ?? data
  } finally {
    loadingRules.value = false
  }
}

async function rescoreAll() {
  rescoring.value = true
  try {
    await axios.post('/api/v1/crm/opportunity-scores/bulk')
    await loadScores()
  } finally {
    rescoring.value = false
  }
}

function openCreateRule() {
  editingRule.value = null
  ruleForm.value = ruleFormDefault()
  ruleDialog.value = true
}

function openEditRule(rule) {
  editingRule.value = rule
  ruleForm.value = { ...rule }
  ruleDialog.value = true
}

async function saveRule() {
  savingRule.value = true
  try {
    if (editingRule.value) {
      await axios.put(`/api/v1/crm/scoring-rules/${editingRule.value.id}`, ruleForm.value)
    } else {
      await axios.post('/api/v1/crm/scoring-rules', ruleForm.value)
    }
    ruleDialog.value = false
    await loadRules()
  } finally {
    savingRule.value = false
  }
}

async function deleteRule(id) {
  if (!confirm('Delete this scoring rule?')) return
  await axios.delete(`/api/v1/crm/scoring-rules/${id}`)
  await loadRules()
}

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(() => {
  loadScores()
  loadRules()
})
</script>
