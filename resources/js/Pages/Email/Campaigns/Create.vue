<template>
  <AppLayout>
    <Head title="Nouvelle campagne" />

    <div class="page-head">
      <div style="display:flex;align-items:center;gap:12px">
        <a href="/email/campaigns" class="back-btn" title="Retour aux campagnes">
          <i class="pi pi-arrow-left" style="font-size:14px" />
        </a>
        <div>
          <h1 class="wh-page-title">Nouvelle campagne</h1>
          <p class="wh-page-subtitle">Configurez et planifiez votre campagne email</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" :disabled="saving" @click="saveDraft">
          <i class="pi pi-save" style="font-size:13px" />
          Enregistrer brouillon
        </button>
        <button class="btn btn-primary" :disabled="saving" @click="scheduleOrSend">
          <i class="pi pi-send" style="font-size:13px" />
          {{ form.scheduled_at ? 'Planifier' : 'Envoyer maintenant' }}
        </button>
      </div>
    </div>

    <div v-if="errorMsg" class="alert-error">
      <i class="pi pi-exclamation-triangle" style="font-size:14px" />
      {{ errorMsg }}
    </div>
    <div v-if="successMsg" class="alert-success">
      <i class="pi pi-check-circle" style="font-size:14px" />
      {{ successMsg }}
    </div>

    <div class="form-grid">
      <!-- Left: main form -->
      <div class="form-main">
        <!-- Section: Identity -->
        <div class="wh-panel form-section">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-id-card" style="font-size:15px;color:var(--halo-500)" /> Identité de la campagne</h2>
          </div>
          <div class="section-body">
            <div class="field">
              <label class="field-label">Nom de la campagne <span class="required">*</span></label>
              <input v-model="form.name" class="wh-input" placeholder="ex. Newsletter Juin 2026" />
            </div>
            <div class="field">
              <label class="field-label">Objet de l'email <span class="required">*</span></label>
              <input v-model="form.subject" class="wh-input" placeholder="Objet affiché dans la boîte de réception…" />
              <p class="field-hint">{{ form.subject.length }}/150 caractères — idéalement moins de 50 pour mobile</p>
            </div>
            <div class="field-row">
              <div class="field">
                <label class="field-label">Nom expéditeur</label>
                <input v-model="form.from_name" class="wh-input" placeholder="WideHalo" />
              </div>
              <div class="field">
                <label class="field-label">Email expéditeur</label>
                <input v-model="form.from_email" class="wh-input" type="email" placeholder="hello@example.com" />
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label class="field-label">Type</label>
                <select v-model="form.type" class="wh-select">
                  <option value="regular">Régulière</option>
                  <option value="ab_test">A/B Test</option>
                  <option value="automated">Automatisée</option>
                </select>
              </div>
              <div class="field">
                <label class="field-label">Pré-header</label>
                <input v-model="form.preheader" class="wh-input" placeholder="Texte affiché après l'objet…" />
              </div>
            </div>
          </div>
        </div>

        <!-- Section: Audience -->
        <div class="wh-panel form-section">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-users" style="font-size:15px;color:var(--halo-500)" /> Audience</h2>
          </div>
          <div class="section-body">
            <div class="field">
              <label class="field-label">Liste de destinataires</label>
              <select v-model="form.list_id" class="wh-select">
                <option value="">— Sélectionner une liste —</option>
                <option v-for="list in emailLists" :key="list.id" :value="list.id">
                  {{ list.name }} ({{ list.subscribers_count ?? 0 }} abonnés)
                </option>
              </select>
            </div>
            <div v-if="form.list_id" class="audience-preview">
              <i class="pi pi-info-circle" style="color:var(--halo-500);font-size:14px" />
              <span>
                Vous atteindrez environ
                <strong>{{ selectedListCount }}</strong> abonné(s) actif(s).
              </span>
            </div>
          </div>
        </div>

        <!-- Section: Content -->
        <div class="wh-panel form-section">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-file-edit" style="font-size:15px;color:var(--halo-500)" /> Contenu</h2>
          </div>
          <div class="section-body">
            <div class="field">
              <label class="field-label">Source du contenu</label>
              <div class="content-tabs">
                <button
                  v-for="tab in contentTabs"
                  :key="tab.value"
                  :class="['content-tab', { active: contentSource === tab.value }]"
                  @click="contentSource = tab.value"
                >
                  <i :class="['pi', tab.icon]" style="font-size:13px" />
                  {{ tab.label }}
                </button>
              </div>
            </div>

            <!-- Template picker -->
            <div v-if="contentSource === 'template'" class="field">
              <label class="field-label">Choisir un template</label>
              <div class="template-grid">
                <div
                  v-for="t in templates"
                  :key="t.id"
                  :class="['template-card', { selected: form.template_id === t.id }]"
                  @click="form.template_id = t.id"
                >
                  <div class="template-thumb">
                    <i class="pi pi-file-edit" style="font-size:20px;color:var(--fg-3)" />
                  </div>
                  <p class="template-name">{{ t.name }}</p>
                  <p class="template-cat">{{ t.category ?? 'général' }}</p>
                </div>
                <a href="/email/builder" target="_blank" class="template-card new-template">
                  <div class="template-thumb" style="background:var(--halo-50)">
                    <i class="pi pi-plus" style="font-size:20px;color:var(--halo-500)" />
                  </div>
                  <p class="template-name" style="color:var(--halo-600)">Créer un template</p>
                  <p class="template-cat">Builder WYSIWYG</p>
                </a>
              </div>
            </div>

            <!-- HTML editor -->
            <div v-if="contentSource === 'html'" class="field">
              <label class="field-label">HTML de l'email</label>
              <textarea
                v-model="form.html_content"
                class="wh-textarea code-editor"
                rows="12"
                placeholder="<!DOCTYPE html><html>…"
                spellcheck="false"
              />
            </div>

            <!-- Plain text -->
            <div v-if="contentSource === 'text'" class="field">
              <label class="field-label">Version texte brut</label>
              <textarea
                v-model="form.text_content"
                class="wh-textarea"
                rows="10"
                placeholder="Contenu en texte brut pour les clients sans support HTML…"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Right: options sidebar -->
      <div class="form-sidebar">
        <!-- Scheduling -->
        <div class="wh-panel form-section">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-calendar" style="font-size:15px;color:var(--halo-500)" /> Planification</h2>
          </div>
          <div class="section-body">
            <div class="field">
              <label class="field-label">Envoyer le</label>
              <input
                v-model="scheduledDateStr"
                type="datetime-local"
                class="wh-input"
                :min="minDateStr"
              />
              <p class="field-hint">Laissez vide pour envoyer immédiatement</p>
            </div>
            <!-- Predictive send time -->
            <div class="field mt-3">
              <div class="flex items-center justify-between mb-1">
                <label class="field-label">✨ Heure optimale IA</label>
                <button class="btn-link text-xs" @click="loadPrediction" :disabled="predictLoading">
                  <i class="pi pi-sparkles" style="font-size:11px" />
                  {{ predictLoading ? 'Analyse...' : 'Calculer' }}
                </button>
              </div>
              <div v-if="predictSuggestion" class="predict-box">
                <div class="predict-main">
                  <i class="pi pi-clock text-blue-500" />
                  <span class="font-semibold text-sm">{{ predictSuggestion.day }} à {{ predictSuggestion.time }}</span>
                  <span class="text-xs text-gray-500 dark:text-surface-400 ml-1">— {{ predictSuggestion.confidence }}% de confiance</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-surface-400 mt-1">{{ predictSuggestion.reasoning }}</p>
                <button class="btn btn-secondary mt-2" style="font-size:12px;padding:4px 10px" @click="usePrediction">
                  Utiliser cette heure
                </button>
              </div>
            </div>

            <div v-if="scheduledDateStr" class="schedule-preview">
              <i class="pi pi-clock" style="color:var(--halo-500);font-size:14px" />
              <span>Envoi planifié le <strong>{{ formatScheduled }}</strong></span>
            </div>
          </div>
        </div>

        <!-- Test send -->
        <div class="wh-panel form-section">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-send" style="font-size:15px;color:var(--halo-500)" /> Email de test</h2>
          </div>
          <div class="section-body">
            <div class="field">
              <label class="field-label">Adresse de test</label>
              <div style="display:flex;gap:8px">
                <input v-model="testEmail" class="wh-input" type="email" placeholder="vous@exemple.com" style="flex:1" />
                <button class="btn btn-secondary" style="white-space:nowrap" @click="sendTest">
                  <i class="pi pi-send" style="font-size:12px" /> Test
                </button>
              </div>
              <p v-if="testSent" class="field-hint" style="color:var(--success-fg)">
                <i class="pi pi-check" /> Email de test envoyé à {{ testEmail }}
              </p>
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div class="wh-panel form-section">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-list" style="font-size:15px;color:var(--halo-500)" /> Récapitulatif</h2>
          </div>
          <div class="section-body">
            <div v-for="row in summaryRows" :key="row.label" class="summary-row">
              <span class="summary-label">{{ row.label }}</span>
              <span class="summary-value" :class="{ missing: !row.value }">{{ row.value || '—' }}</span>
            </div>
            <div class="readiness">
              <div class="readiness-bar">
                <div class="readiness-fill" :style="{ width: readiness + '%' }" />
              </div>
              <span class="readiness-label">{{ readiness }}% prêt</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  templates:  { type: Array, default: () => [] },
  emailLists: { type: Array, default: () => [] },
})

const saving      = ref(false)
const errorMsg    = ref('')
const successMsg  = ref('')
const testEmail   = ref('')
const testSent    = ref(false)
const contentSource = ref('template')

// Predictive send time
const predictLoading   = ref(false)
const predictSuggestion = ref(null)

async function loadPrediction() {
  predictLoading.value = true
  try {
    const axios = (await import('axios')).default
    const { data } = await axios.post('/api/v1/email/predict-send-time', {})
    predictSuggestion.value = data
  } catch (e) {
    // ignore
  } finally {
    predictLoading.value = false
  }
}

function usePrediction() {
  if (!predictSuggestion.value) return
  const dayMap = { 'Lundi': 1, 'Mardi': 2, 'Mercredi': 3, 'Jeudi': 4, 'Vendredi': 5, 'Samedi': 6, 'Dimanche': 0 }
  const now = new Date()
  const targetDay = dayMap[predictSuggestion.value.day] ?? 2
  const diff = (targetDay - now.getDay() + 7) % 7 || 7
  const target = new Date(now)
  target.setDate(now.getDate() + diff)
  const [h, m] = predictSuggestion.value.time.split(':')
  target.setHours(parseInt(h), parseInt(m), 0, 0)
  scheduledDateStr.value = target.toISOString().slice(0, 16)
}

const contentTabs = [
  { value: 'template', label: 'Template',    icon: 'pi-file-edit' },
  { value: 'html',     label: 'HTML direct', icon: 'pi-code' },
  { value: 'text',     label: 'Texte brut',  icon: 'pi-align-left' },
]

const form = ref({
  name: '', subject: '', from_name: '', from_email: '',
  type: 'regular', preheader: '', list_id: '',
  template_id: null, html_content: '', text_content: '',
  scheduled_at: null, status: 'draft',
})

const scheduledDateStr = ref('')
const minDateStr = computed(() => {
  const d = new Date()
  d.setMinutes(d.getMinutes() + 5)
  return d.toISOString().slice(0, 16)
})

const formatScheduled = computed(() => {
  if (!scheduledDateStr.value) return ''
  return new Date(scheduledDateStr.value).toLocaleString('fr-FR', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
})

const selectedListCount = computed(() => {
  const l = props.emailLists.find(l => l.id == form.value.list_id)
  return l ? (l.active_count ?? l.subscribers_count ?? 0) : 0
})

const summaryRows = computed(() => [
  { label: 'Nom',        value: form.value.name },
  { label: 'Objet',      value: form.value.subject },
  { label: 'Expéditeur', value: form.value.from_email },
  { label: 'Liste',      value: props.emailLists.find(l => l.id == form.value.list_id)?.name },
  { label: 'Contenu',    value: contentSource.value === 'template' && form.value.template_id
    ? props.templates.find(t => t.id === form.value.template_id)?.name
    : (contentSource.value === 'html' && form.value.html_content ? 'HTML personnalisé' : null) },
])

const readiness = computed(() => {
  const checks = [
    !!form.value.name,
    !!form.value.subject,
    !!form.value.from_email,
    !!form.value.list_id,
    contentSource.value === 'template' ? !!form.value.template_id
      : (contentSource.value === 'html' ? !!form.value.html_content : !!form.value.text_content),
  ]
  return Math.round((checks.filter(Boolean).length / checks.length) * 100)
})

const buildPayload = (status) => ({
  name: form.value.name,
  subject: form.value.subject,
  from_name: form.value.from_name,
  from_email: form.value.from_email,
  type: form.value.type,
  preheader: form.value.preheader,
  list_id: form.value.list_id || null,
  template_id: form.value.template_id,
  html_content: form.value.html_content,
  text_content: form.value.text_content,
  scheduled_at: scheduledDateStr.value ? new Date(scheduledDateStr.value).toISOString() : null,
  status,
})

const doSave = async (status) => {
  errorMsg.value = ''
  if (!form.value.name || !form.value.subject) {
    errorMsg.value = 'Le nom et l\'objet sont requis.'
    return
  }
  saving.value = true
  try {
    const res = await fetch('/api/v1/email/campaigns', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
      body: JSON.stringify(buildPayload(status)),
    })
    if (!res.ok) {
      const err = await res.json()
      errorMsg.value = err.message ?? 'Erreur lors de la création.'
      return
    }
    router.visit('/email/campaigns')
  } finally {
    saving.value = false
  }
}

const saveDraft = () => doSave('draft')
const scheduleOrSend = () => doSave(scheduledDateStr.value ? 'scheduled' : 'sending')

const sendTest = async () => {
  if (!testEmail.value) return
  testSent.value = false
  // Fire & forget test
  await fetch('/api/v1/email/campaigns/test', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
    },
    body: JSON.stringify({ email: testEmail.value, ...buildPayload('draft') }),
  })
  testSent.value = true
}
</script>

<style scoped>
.page-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-size:26px; font-weight:600; letter-spacing:-0.02em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.back-btn { display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); text-decoration:none; transition:all var(--dur-fast); }
.back-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }
.btn { font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background:var(--bg-sunken); }
.btn-secondary:disabled { opacity:0.6; cursor:not-allowed; }
.alert-error { display:flex; align-items:center; gap:10px; background:var(--danger-bg); color:var(--danger-fg); border:1px solid var(--danger-border,#fca5a5); border-radius:var(--r-md); padding:12px 16px; margin-bottom:16px; font-size:14px; }
.alert-success { display:flex; align-items:center; gap:10px; background:var(--success-bg); color:var(--success-fg); border:1px solid var(--success-border,#86efac); border-radius:var(--r-md); padding:12px 16px; margin-bottom:16px; font-size:14px; }
.form-grid { display:grid; grid-template-columns:1fr 320px; gap:16px; align-items:start; }
@media (max-width:960px) { .form-grid { grid-template-columns:1fr; } }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.form-section { margin-bottom:16px; }
.section-header { padding:14px 18px 0; border-bottom:1px solid var(--border-subtle); padding-bottom:12px; margin-bottom:4px; }
.section-title { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); display:flex; align-items:center; gap:8px; }
.section-body { padding:16px 18px; display:flex; flex-direction:column; gap:14px; }
.field { display:flex; flex-direction:column; gap:5px; }
.field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.field-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.required { color:var(--danger-fg); }
.field-hint { font-size:11px; color:var(--fg-3); margin:2px 0 0; }
.wh-input { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; transition:border-color var(--dur-fast); box-sizing:border-box; }
.wh-input:focus { outline:none; border-color:var(--halo-400); }
.wh-select { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; cursor:pointer; }
.wh-select:focus { outline:none; border-color:var(--halo-400); }
.wh-textarea { width:100%; padding:10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; resize:vertical; box-sizing:border-box; }
.wh-textarea:focus { outline:none; border-color:var(--halo-400); }
.code-editor { font-family:monospace; font-size:13px; }
.content-tabs { display:flex; gap:4px; padding:3px; background:var(--bg-sunken); border-radius:var(--r-md); width:fit-content; }
.content-tab { display:flex; align-items:center; gap:6px; padding:6px 12px; border-radius:var(--r-sm); border:none; background:transparent; color:var(--fg-2); font-size:13px; font-weight:500; cursor:pointer; transition:all var(--dur-fast); }
.content-tab.active { background:var(--bg-canvas); color:var(--fg-1); box-shadow:0 1px 3px rgba(0,0,0,.07); }
.template-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px; }
.template-card { border:2px solid var(--border-subtle); border-radius:var(--r-md); padding:10px; cursor:pointer; transition:all var(--dur-fast); text-decoration:none; display:block; }
.template-card:hover { border-color:var(--halo-400); }
.template-card.selected { border-color:var(--halo-500); background:var(--halo-50); }
.template-card.new-template { border-style:dashed; }
.template-thumb { height:70px; background:var(--bg-sunken); border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; margin-bottom:8px; }
.template-name { font-size:12px; font-weight:500; color:var(--fg-1); margin:0; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; }
.template-cat { font-size:11px; color:var(--fg-3); margin:2px 0 0; text-transform:capitalize; }
.audience-preview { display:flex; align-items:center; gap:8px; background:var(--halo-50); border:1px solid var(--halo-200,#bfdbfe); border-radius:var(--r-md); padding:10px 12px; font-size:13px; color:var(--fg-2); }
.schedule-preview { display:flex; align-items:center; gap:8px; background:var(--halo-50); border:1px solid var(--halo-200,#bfdbfe); border-radius:var(--r-md); padding:10px 12px; font-size:13px; color:var(--fg-2); }
.summary-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--border-subtle); font-size:13px; }
.summary-row:last-of-type { border-bottom:0; }
.summary-label { color:var(--fg-3); }
.summary-value { color:var(--fg-1); font-weight:500; text-align:right; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.summary-value.missing { color:var(--fg-4); font-weight:400; }
.readiness { margin-top:14px; }
.readiness-bar { height:6px; background:var(--bg-sunken); border-radius:var(--r-pill); overflow:hidden; margin-bottom:6px; }
.readiness-fill { height:100%; background:var(--halo-500); border-radius:var(--r-pill); transition:width 0.3s; }
.readiness-label { font-size:12px; color:var(--fg-3); }
</style>
