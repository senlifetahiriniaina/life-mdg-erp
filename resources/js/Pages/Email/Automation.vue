<template>
  <AppLayout>
    <Head title="Automatisation Email" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Automatisation</h1>
        <p class="wh-page-subtitle">{{ flows.total }} flow{{ flows.total !== 1 ? 's' : '' }} d'automatisation</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" style="font-size:13px" /> Nouveau flow
        </button>
      </div>
    </div>

    <div class="wh-kpi-grid">
      <div class="wh-kpi" v-for="k in kpiList" :key="k.label">
        <div class="wh-kpi-label">{{ k.label }}</div>
        <div class="wh-kpi-num font-display">{{ k.value }}</div>
      </div>
    </div>

    <div class="wh-panel filter-bar">
      <div style="position:relative;flex:1;min-width:200px;max-width:300px">
        <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
        <input v-model="search" class="wh-filter-input" placeholder="Rechercher un flow…" @input="onSearchInput" />
      </div>
      <select v-model="statusFilter" class="wh-select-sm" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="draft">Brouillon</option>
        <option value="paused">En pause</option>
      </select>
      <select v-model="triggerFilter" class="wh-select-sm" @change="applyFilters">
        <option value="">Tous les triggers</option>
        <option v-for="t in triggerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
      </select>
    </div>

    <div v-if="flows.data.length" class="flows-list">
      <div v-for="flow in flows.data" :key="flow.id" class="flow-card">
        <div class="flow-card-header">
          <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
            <div :class="['flow-status-dot', flow.is_active ? 'dot-active' : 'dot-inactive']" :title="flow.is_active ? 'Actif' : 'Inactif'" />
            <div style="min-width:0">
              <p class="flow-name">{{ flow.name }}</p>
              <p v-if="flow.description" class="flow-desc">{{ flow.description }}</p>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <span :class="flowStatusBadge(flow.status)" class="wh-badge">
              <span class="wh-badge-dot" />{{ flowStatusLabel(flow.status) }}
            </span>
            <button :class="['toggle-flow-btn', flow.is_active ? 'pause' : 'activate']" :disabled="toggling === flow.id" @click="toggleFlow(flow)">
              <i :class="['pi', flow.is_active ? 'pi-pause' : 'pi-play']" style="font-size:11px" />
              {{ flow.is_active ? 'Pause' : 'Activer' }}
            </button>
            <button class="row-btn" title="Modifier" @click="editFlow(flow)"><i class="pi pi-pencil" style="font-size:11px" /></button>
            <button class="row-btn row-btn-danger" title="Supprimer" @click="confirmDelete(flow)"><i class="pi pi-trash" style="font-size:11px" /></button>
          </div>
        </div>
        <div class="flow-body">
          <div class="step-pill step-trigger">
            <i class="pi pi-bolt" style="font-size:12px;color:var(--halo-500)" />
            <span class="step-pill-label">Trigger</span>
            <span class="step-pill-value">{{ triggerLabel(flow.trigger_type) }}</span>
          </div>
          <template v-for="(step, si) in (flow.steps ?? []).slice(0, 5)" :key="si">
            <div class="step-arrow"><i class="pi pi-chevron-right" style="font-size:10px;color:var(--fg-4)" /></div>
            <div :class="['step-pill', stepTypeClass(step.type)]">
              <i :class="['pi', stepIcon(step.type)]" style="font-size:12px" />
              <span class="step-pill-label">{{ stepTypeLabel(step.type) }}</span>
              <span v-if="step.delay_hours" class="step-pill-value">+{{ formatDelay(step.delay_hours) }}</span>
            </div>
          </template>
          <template v-if="(flow.steps ?? []).length > 5">
            <div class="step-arrow"><i class="pi pi-chevron-right" style="font-size:10px;color:var(--fg-4)" /></div>
            <div class="step-pill step-more">
              <i class="pi pi-ellipsis-h" style="font-size:12px" />
              <span class="step-pill-value">+{{ (flow.steps ?? []).length - 5 }} étapes</span>
            </div>
          </template>
        </div>
        <div class="flow-footer">
          <div class="flow-stat"><span class="flow-stat-num">{{ flow.total_enrolled ?? 0 }}</span><span class="flow-stat-label">Inscrits</span></div>
          <div class="flow-stat-sep" />
          <div class="flow-stat"><span class="flow-stat-num">{{ flow.total_completed ?? 0 }}</span><span class="flow-stat-label">Terminés</span></div>
          <div class="flow-stat-sep" />
          <div class="flow-stat"><span class="flow-stat-num">{{ completionRate(flow) }}%</span><span class="flow-stat-label">Taux de complétion</span></div>
          <div class="flow-stat-sep" />
          <div class="flow-stat"><span class="flow-stat-num">{{ (flow.steps ?? []).length }}</span><span class="flow-stat-label">Étapes</span></div>
          <div style="margin-left:auto;font-size:11px;color:var(--fg-4)">Créé {{ formatDate(flow.created_at) }}</div>
        </div>
      </div>
    </div>

    <div v-else class="empty-state">
      <i class="pi pi-sync" style="font-size:40px;color:var(--fg-4);margin-bottom:12px" />
      <p style="color:var(--fg-2);font-size:15px;font-weight:500;margin:0 0 4px">Aucun flow d'automatisation</p>
      <p style="color:var(--fg-3);font-size:13px;margin:0 0 16px">Automatisez vos emails avec des séquences déclenchées par des événements.</p>
      <button class="btn btn-primary" @click="openCreate"><i class="pi pi-plus" style="font-size:13px" /> Créer un flow</button>
    </div>

    <div v-if="flows.last_page > 1" style="display:flex;justify-content:center;padding:12px 0">
      <Paginator :rows="flows.per_page ?? 15" :total-records="flows.total ?? 0" :first="((flows.current_page ?? 1) - 1) * (flows.per_page ?? 15)" @page="(e) => goPage(e.page + 1)" />
    </div>

    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="form-modal">
        <div class="modal-header">
          <h3 style="margin:0;font-size:16px;font-weight:600;color:var(--fg-1)">{{ editTarget ? 'Modifier le flow' : 'Nouveau flow d\'automatisation' }}</h3>
          <button class="icon-btn" @click="showForm = false"><i class="pi pi-times" style="font-size:14px" /></button>
        </div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px">
          <div class="field"><label class="field-label">Nom du flow <span style="color:var(--danger-fg)">*</span></label><input v-model="flowForm.name" class="wh-input" placeholder="ex. Bienvenue nouveaux inscrits" /></div>
          <div class="field"><label class="field-label">Description</label><textarea v-model="flowForm.description" class="wh-textarea" rows="2" placeholder="Décrivez l'objectif de ce flow…" /></div>
          <div class="field">
            <label class="field-label">Trigger (déclencheur)</label>
            <select v-model="flowForm.trigger_type" class="wh-select">
              <option v-for="t in triggerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div class="field">
            <label class="field-label">Étapes</label>
            <div class="steps-builder">
              <div v-for="(step, si) in flowForm.steps" :key="si" class="step-row">
                <div class="step-num">{{ si + 1 }}</div>
                <select v-model="step.type" class="wh-select step-select">
                  <option value="email">Email</option>
                  <option value="wait">Délai</option>
                  <option value="condition">Condition</option>
                  <option value="tag">Ajouter tag</option>
                  <option value="notify">Notification interne</option>
                </select>
                <div style="display:flex;align-items:center;gap:4px">
                  <input v-if="step.type === 'wait'" v-model.number="step.delay_hours" type="number" min="1" class="wh-input step-delay" placeholder="Heures" />
                  <span v-if="step.type === 'wait'" style="font-size:12px;color:var(--fg-3)">h</span>
                </div>
                <button class="icon-btn" style="color:var(--danger-fg)" @click="removeStep(si)"><i class="pi pi-times" style="font-size:12px" /></button>
              </div>
              <button class="add-step-btn" @click="addStep"><i class="pi pi-plus" style="font-size:11px" /> Ajouter une étape</button>
            </div>
          </div>
          <div v-if="formError" class="alert-error">{{ formError }}</div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border-subtle)">
          <button class="btn btn-secondary" @click="showForm = false">Annuler</button>
          <button class="btn btn-primary" :disabled="savingFlow" @click="submitFlow">{{ savingFlow ? 'Enregistrement…' : (editTarget ? 'Mettre à jour' : 'Créer le flow') }}</button>
        </div>
      </div>
    </div>

    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="confirm-modal">
        <div style="padding:20px 20px 12px">
          <h3 style="margin:0 0 8px;font-size:16px;font-weight:600;color:var(--fg-1)">Supprimer le flow</h3>
          <p style="margin:0;font-size:14px;color:var(--fg-2)">Êtes-vous sûr de vouloir supprimer <strong>{{ deleteTarget.name }}</strong> ?</p>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid var(--border-subtle)">
          <button class="btn btn-secondary" @click="deleteTarget = null">Annuler</button>
          <button class="btn btn-danger" :disabled="deleting" @click="deleteFlow">{{ deleting ? 'Suppression…' : 'Supprimer' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Paginator from 'primevue/paginator'

const props = defineProps({ flows: { type: Object, required: true }, stats: { type: Object, default: () => ({}) } })

const search = ref('')
const statusFilter = ref('')
const triggerFilter = ref('')
const showForm = ref(false)
const editTarget = ref(null)
const deleteTarget = ref(null)
const deleting = ref(false)
const savingFlow = ref(false)
const formError = ref('')
const toggling = ref(null)

const triggerTypes = [
  { value: 'subscriber_added', label: 'Nouvel abonné' },
  { value: 'campaign_opened', label: 'Campagne ouverte' },
  { value: 'campaign_clicked', label: 'Lien cliqué' },
  { value: 'tag_added', label: 'Tag ajouté' },
  { value: 'birthday', label: 'Anniversaire' },
  { value: 'purchase', label: 'Achat effectué' },
  { value: 'form_submitted', label: 'Formulaire soumis' },
  { value: 'manual', label: 'Déclenchement manuel' },
]

const kpiList = computed(() => [
  { label: 'Total flows', value: props.stats.total ?? props.flows.total ?? 0 },
  { label: 'Actifs', value: props.stats.active ?? 0 },
  { label: 'Inscrits (total)', value: props.stats.enrolled ?? 0 },
  { label: 'Terminés', value: props.stats.completed ?? 0 },
])

const defaultFlowForm = () => ({ name: '', description: '', trigger_type: 'subscriber_added', steps: [{ type: 'email', delay_hours: 0 }] })
const flowForm = ref(defaultFlowForm())

const openCreate = () => { editTarget.value = null; flowForm.value = defaultFlowForm(); formError.value = ''; showForm.value = true }
const editFlow = (f) => { editTarget.value = f; flowForm.value = { name: f.name, description: f.description ?? '', trigger_type: f.trigger_type, steps: (f.steps ?? [{ type: 'email', delay_hours: 0 }]).map(s => ({ type: s.type ?? 'email', delay_hours: s.delay_hours ?? 0 })) }; formError.value = ''; showForm.value = true }
const addStep = () => flowForm.value.steps.push({ type: 'email', delay_hours: 0 })
const removeStep = (i) => flowForm.value.steps.splice(i, 1)

const submitFlow = async () => {
  if (!flowForm.value.name) { formError.value = 'Le nom est requis.'; return }
  savingFlow.value = true; formError.value = ''
  try {
    const url = editTarget.value ? `/api/v1/email/automation-flows/${editTarget.value.id}` : '/api/v1/email/automation-flows'
    const method = editTarget.value ? 'PUT' : 'POST'
    const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }, body: JSON.stringify(flowForm.value) })
    if (!res.ok) { const e = await res.json(); formError.value = e.message ?? 'Erreur'; return }
    showForm.value = false; router.reload()
  } finally { savingFlow.value = false }
}

const toggleFlow = async (flow) => {
  toggling.value = flow.id
  const action = flow.is_active ? 'pause' : 'activate'
  await fetch(`/api/v1/email/automation-flows/${flow.id}/${action}`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } })
  toggling.value = null; router.reload()
}

const confirmDelete = (f) => { deleteTarget.value = f; deleting.value = false }
const deleteFlow = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try { await fetch(`/api/v1/email/automation-flows/${deleteTarget.value.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } }); deleteTarget.value = null; router.reload() } finally { deleting.value = false }
}

let st = null
const onSearchInput = () => { clearTimeout(st); st = setTimeout(applyFilters, 400) }
const applyFilters = () => router.get('/email/automation', { search: search.value, status: statusFilter.value, trigger: triggerFilter.value }, { preserveState: true, preserveScroll: true })
const goPage = (p) => router.get('/email/automation', { page: p, search: search.value, status: statusFilter.value, trigger: triggerFilter.value }, { preserveState: true })

const triggerLabel = (t) => triggerTypes.find(x => x.value === t)?.label ?? t
const completionRate = (f) => f.total_enrolled > 0 ? Math.round((f.total_completed / f.total_enrolled) * 100) : 0
const formatDate = (v) => v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const formatDelay = (h) => h >= 24 ? `${Math.round(h / 24)}j` : `${h}h`
const flowStatusBadge = (s) => ({ active: 'wh-badge-green', draft: 'wh-badge-slate', paused: 'wh-badge-amber' }[s] ?? 'wh-badge-slate')
const flowStatusLabel = (s) => ({ active: 'Actif', draft: 'Brouillon', paused: 'En pause' }[s] ?? s)
const stepTypeLabel = (t) => ({ email: 'Email', wait: 'Délai', condition: 'Condition', tag: 'Tag', notify: 'Notif.' }[t] ?? t)
const stepIcon = (t) => ({ email: 'pi-envelope', wait: 'pi-clock', condition: 'pi-code', tag: 'pi-tag', notify: 'pi-bell' }[t] ?? 'pi-circle')
const stepTypeClass = (t) => ({ email: 'step-email', wait: 'step-wait', condition: 'step-condition', tag: 'step-tag', notify: 'step-notify' }[t] ?? '')
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-danger { background:var(--danger-fg,#dc2626); color:#fff; }
.btn-danger:hover:not(:disabled) { opacity:.9; }
.btn-danger:disabled { opacity:.6; cursor:not-allowed; }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin-bottom:6px; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }
.filter-bar { display:flex; align-items:center; gap:10px; padding:10px 14px; margin-bottom:16px; flex-wrap:wrap; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-filter-input { width:100%; padding:8px 10px 8px 30px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; }
.wh-select-sm { padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:13px; cursor:pointer; }
.flows-list { display:flex; flex-direction:column; gap:12px; }
.flow-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.flow-card-header { display:flex; align-items:center; justify-content:space-between; padding:14px 16px 10px; gap:12px; }
.flow-status-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.dot-active { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.dot-inactive { background:var(--fg-4); }
.flow-name { margin:0; font-size:15px; font-weight:600; color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.flow-desc { margin:2px 0 0; font-size:12px; color:var(--fg-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:400px; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-amber { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
.toggle-flow-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:var(--r-md); border:1px solid; font-size:12px; font-weight:500; cursor:pointer; }
.toggle-flow-btn.pause { background:var(--warn-bg); color:var(--warn-fg); border-color:#fde68a; }
.toggle-flow-btn.activate { background:var(--success-bg); color:var(--success-fg); border-color:#86efac; }
.row-btn { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; }
.row-btn-danger:hover { background:var(--danger-bg); color:var(--danger-fg); }
.flow-body { display:flex; align-items:center; flex-wrap:wrap; gap:4px; padding:10px 16px; background:var(--bg-sunken); border-top:1px solid var(--border-subtle); border-bottom:1px solid var(--border-subtle); overflow-x:auto; }
.step-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:var(--r-pill); font-size:11px; font-weight:500; border:1px solid; white-space:nowrap; }
.step-trigger { background:#fdf4ff; color:#9333ea; border-color:#e9d5ff; }
.step-email { background:var(--halo-50); color:var(--halo-700); border-color:var(--halo-200,#c7d2fe); }
.step-wait { background:#fff7ed; color:#ea580c; border-color:#fed7aa; }
.step-condition { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
.step-tag { background:#fef9c3; color:#854d0e; border-color:#fde047; }
.step-notify { background:#f0f9ff; color:#0369a1; border-color:#bae6fd; }
.step-more { background:var(--bg-canvas); color:var(--fg-3); border-color:var(--border-subtle); }
.step-pill-label { color:var(--fg-3); font-weight:400; font-size:10px; }
.step-pill-value { font-weight:600; }
.step-arrow { color:var(--fg-4); display:flex; align-items:center; }
.flow-footer { display:flex; align-items:center; padding:10px 16px; flex-wrap:wrap; }
.flow-stat { display:flex; flex-direction:column; align-items:center; padding:0 14px; }
.flow-stat-num { font-size:15px; font-weight:700; color:var(--fg-1); }
.flow-stat-label { font-size:11px; color:var(--fg-3); margin-top:2px; }
.flow-stat-sep { width:1px; height:28px; background:var(--border-subtle); }
.empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:64px 20px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px; }
.form-modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:560px; max-width:100%; max-height:90vh; overflow-y:auto; }
.confirm-modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:440px; max-width:100%; }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border-subtle); }
.icon-btn { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border:none; background:transparent; color:var(--fg-3); border-radius:var(--r-sm); cursor:pointer; }
.field { display:flex; flex-direction:column; gap:4px; }
.field-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.wh-input { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; box-sizing:border-box; }
.wh-select { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; cursor:pointer; }
.wh-textarea { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; resize:vertical; box-sizing:border-box; }
.steps-builder { display:flex; flex-direction:column; gap:6px; }
.step-row { display:flex; align-items:center; gap:6px; }
.step-num { width:22px; height:22px; border-radius:50%; background:var(--halo-50); color:var(--halo-700); font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.step-select { flex:1; font-size:13px; padding:6px 8px; }
.step-delay { width:80px; padding:6px 8px; font-size:13px; }
.add-step-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border:1px dashed var(--border-subtle); border-radius:var(--r-md); background:transparent; color:var(--fg-3); font-size:12px; cursor:pointer; }
.alert-error { background:var(--danger-bg); color:var(--danger-fg); border:1px solid #fca5a5; border-radius:var(--r-sm); padding:10px 12px; font-size:13px; }
</style>
