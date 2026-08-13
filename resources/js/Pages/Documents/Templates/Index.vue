<template>
  <AppLayout>
    <Head title="Modèles de documents" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Modèles de documents</h1>
        <p class="wh-page-subtitle">Créez et gérez vos modèles réutilisables</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" style="font-size:13px" /> Nouveau modèle
        </button>
      </div>
    </div>

    <!-- Category filter -->
    <div class="filter-bar">
      <button
        v-for="cat in categories"
        :key="cat.value"
        class="cat-pill"
        :class="selectedCategory === cat.value ? 'cat-pill-active' : ''"
        @click="selectedCategory = cat.value"
      >{{ cat.label }}</button>
    </div>

    <!-- Templates grid -->
    <div class="templates-grid">
      <div
        v-for="tpl in filteredTemplates"
        :key="tpl.id"
        class="template-card"
      >
        <div class="card-top">
          <span :class="['cat-badge', categoryClass(tpl.category)]">{{ categoryLabel(tpl.category) }}</span>
          <span style="font-size:11px;color:var(--fg-3)">{{ tpl.uses_count }} utilisations</span>
        </div>
        <div class="card-name">{{ tpl.name }}</div>
        <div v-if="tpl.description" class="card-desc">{{ tpl.description }}</div>

        <div class="card-vars" v-if="tpl.variables?.length">
          <i class="pi pi-tag" style="font-size:10px;color:var(--fg-4)" />
          <span style="font-size:11px;color:var(--fg-3)">{{ tpl.variables.length }} variable{{ tpl.variables.length > 1 ? 's' : '' }}</span>
        </div>

        <div class="card-actions">
          <button class="btn btn-secondary btn-sm" @click="openGenerate(tpl)">
            <i class="pi pi-file-export" style="font-size:11px" /> Générer
          </button>
          <button class="btn btn-secondary btn-sm" @click="openEdit(tpl)">
            <i class="pi pi-pencil" style="font-size:11px" /> Modifier
          </button>
        </div>
      </div>

      <div v-if="filteredTemplates.length === 0" class="empty-state">
        <i class="pi pi-file" style="font-size:40px;color:var(--fg-4);margin-bottom:12px" />
        <p>Aucun modèle trouvé.</p>
        <button class="btn btn-primary" @click="openCreate">Créer un modèle</button>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showFormModal" class="modal-overlay" @click.self="closeForm">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h3>{{ editing ? 'Modifier le modèle' : 'Nouveau modèle' }}</h3>
          <button class="btn-icon" @click="closeForm"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-field">
              <label class="form-label">Nom *</label>
              <input v-model="form.name" type="text" class="form-input" placeholder="Nom du modèle" />
            </div>
            <div class="form-field">
              <label class="form-label">Catégorie *</label>
              <select v-model="form.category" class="form-input">
                <option v-for="c in categories.filter(c=>c.value!=='')" :key="c.value" :value="c.value">{{ c.label }}</option>
              </select>
            </div>
          </div>
          <div class="form-field">
            <label class="form-label">Description</label>
            <input v-model="form.description" type="text" class="form-input" placeholder="Description optionnelle" />
          </div>
          <div class="form-field">
            <label class="form-label">Contenu HTML</label>
            <textarea v-model="form.content_html" class="form-input code-area" rows="10" placeholder="<h1>{{title}}</h1><p>Cher {{name}},</p>" />
            <p style="font-size:11px;color:var(--fg-3);margin:4px 0 0">Utilisez <code v-text="'{{variable}}'"></code></p>
          </div>

          <!-- Variables -->
          <div class="vars-section">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
              <label class="form-label" style="margin:0">Variables</label>
              <button class="btn btn-secondary btn-sm" @click="addVariable">
                <i class="pi pi-plus" style="font-size:11px" /> Ajouter
              </button>
            </div>
            <div v-for="(v, i) in form.variables" :key="i" class="var-row">
              <input v-model="v.name" type="text" class="form-input" placeholder="nom_var" style="flex:1" />
              <input v-model="v.label" type="text" class="form-input" placeholder="Libellé" style="flex:1" />
              <select v-model="v.type" class="form-input" style="width:100px">
                <option value="text">Texte</option>
                <option value="number">Nombre</option>
                <option value="date">Date</option>
                <option value="select">Sélection</option>
              </select>
              <input v-model="v.default_value" type="text" class="form-input" placeholder="Défaut" style="flex:1" />
              <button class="btn-icon btn-danger" @click="form.variables.splice(i,1)">
                <i class="pi pi-trash" style="font-size:11px" />
              </button>
            </div>
          </div>

          <p v-if="formError" class="error-msg">{{ formError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeForm">Annuler</button>
          <button class="btn btn-primary" :disabled="formLoading" @click="saveTemplate">
            {{ formLoading ? 'Enregistrement...' : (editing ? 'Mettre à jour' : 'Créer') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Generate Modal -->
    <div v-if="showGenerateModal" class="modal-overlay" @click.self="showGenerateModal=false">
      <div class="modal">
        <div class="modal-header">
          <h3>Générer un document — {{ generateTemplate?.name }}</h3>
          <button class="btn-icon" @click="showGenerateModal=false"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <div class="form-field">
            <label class="form-label">Nom du fichier *</label>
            <input v-model="generateForm.filename" type="text" class="form-input" placeholder="mon-document" />
          </div>
          <template v-if="generateTemplate?.variables?.length">
            <div v-for="v in generateTemplate.variables" :key="v.name" class="form-field">
              <label class="form-label">{{ v.label }}</label>
              <input
                v-model="generateForm.variables[v.name]"
                :type="v.type === 'number' ? 'number' : v.type === 'date' ? 'date' : 'text'"
                class="form-input"
                :placeholder="v.default_value || ''"
              />
            </div>
          </template>
          <p v-if="generateError" class="error-msg">{{ generateError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showGenerateModal=false">Annuler</button>
          <button class="btn btn-primary" :disabled="generateLoading" @click="generateDocument">
            {{ generateLoading ? 'Génération...' : 'Générer' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  templates: { type: Array, default: () => [] },
})

const categories = [
  { value: '',         label: 'Tous' },
  { value: 'contract', label: 'Contrat' },
  { value: 'invoice',  label: 'Facture' },
  { value: 'letter',   label: 'Lettre' },
  { value: 'report',   label: 'Rapport' },
  { value: 'other',    label: 'Autre' },
]

const selectedCategory = ref('')
const templates        = ref(props.templates)

const filteredTemplates = computed(() => {
  if (!selectedCategory.value) return templates.value
  return templates.value.filter(t => t.category === selectedCategory.value)
})

function categoryLabel(cat) {
  return categories.find(c => c.value === cat)?.label || cat
}

function categoryClass(cat) {
  return { contract: 'cat-blue', invoice: 'cat-green', letter: 'cat-purple', report: 'cat-orange', other: 'cat-slate' }[cat] || 'cat-slate'
}

// Form modal
const showFormModal = ref(false)
const editing       = ref(null)
const formLoading   = ref(false)
const formError     = ref('')

const form = reactive({
  name: '', description: '', category: 'other', content_html: '', variables: [],
})

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', description: '', category: 'other', content_html: '', variables: [] })
  showFormModal.value = true
}

function openEdit(tpl) {
  editing.value = tpl
  Object.assign(form, {
    name: tpl.name, description: tpl.description || '', category: tpl.category,
    content_html: tpl.content_html, variables: JSON.parse(JSON.stringify(tpl.variables || [])),
  })
  showFormModal.value = true
}

function closeForm() {
  showFormModal.value = false
  formError.value = ''
}

function addVariable() {
  form.variables.push({ name: '', label: '', type: 'text', default_value: '' })
}

async function saveTemplate() {
  if (!form.name || !form.category || !form.content_html) {
    formError.value = 'Nom, catégorie et contenu HTML sont requis.'
    return
  }
  formLoading.value = true
  formError.value   = ''
  try {
    const payload = { name: form.name, description: form.description, category: form.category, content_html: form.content_html, variables: form.variables }
    if (editing.value) {
      const { data } = await axios.put(`/api/v1/doc-templates/${editing.value.id}`, payload)
      const idx = templates.value.findIndex(t => t.id === editing.value.id)
      if (idx !== -1) templates.value[idx] = data
    } else {
      const { data } = await axios.post('/api/v1/doc-templates', payload)
      templates.value.unshift(data)
    }
    closeForm()
  } catch (err) {
    formError.value = err?.response?.data?.message || 'Erreur lors de l\'enregistrement.'
  } finally {
    formLoading.value = false
  }
}

// Generate modal
const showGenerateModal = ref(false)
const generateTemplate  = ref(null)
const generateLoading   = ref(false)
const generateError     = ref('')
const generateForm      = reactive({ filename: '', variables: {} })

function openGenerate(tpl) {
  generateTemplate.value = tpl
  generateForm.filename  = ''
  generateForm.variables = {}
  if (tpl.variables) {
    tpl.variables.forEach(v => { generateForm.variables[v.name] = v.default_value || '' })
  }
  showGenerateModal.value = true
}

async function generateDocument() {
  if (!generateForm.filename) { generateError.value = 'Le nom du fichier est requis.'; return }
  generateLoading.value = true
  generateError.value   = ''
  try {
    await axios.post(`/api/v1/doc-templates/${generateTemplate.value.id}/generate`, {
      filename:  generateForm.filename,
      variables: generateForm.variables,
    })
    showGenerateModal.value = false
    generateTemplate.value.uses_count = (generateTemplate.value.uses_count || 0) + 1
  } catch (err) {
    generateError.value = err?.response?.data?.message || 'Erreur lors de la génération.'
  } finally {
    generateLoading.value = false
  }
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; }
.wh-page-title { margin:0; font-size:28px; font-weight:600; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

.filter-bar { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.cat-pill { padding:5px 14px; border-radius:var(--r-pill); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); font-size:13px; cursor:pointer; transition:all var(--dur-fast); }
.cat-pill:hover { background:var(--bg-sunken); }
.cat-pill-active { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }

.templates-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.template-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:18px; display:flex; flex-direction:column; gap:10px; }
.card-top { display:flex; align-items:center; justify-content:space-between; }
.card-name { font-weight:600; font-size:15px; color:var(--fg-1); }
.card-desc { font-size:13px; color:var(--fg-2); line-height:1.4; }
.card-vars { display:flex; align-items:center; gap:5px; }
.card-actions { display:flex; gap:8px; margin-top:4px; }

.cat-badge { display:inline-flex; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:600; }
.cat-blue   { background:var(--halo-50); color:var(--halo-700); }
.cat-green  { background:var(--success-bg); color:var(--success-fg); }
.cat-purple { background:var(--halo-50); color:var(--halo-700); }
.cat-orange { background:#fff7ed; color:#c2410c; }
.cat-slate  { background:var(--bg-sunken); color:var(--fg-2); }

.empty-state { grid-column:1/-1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:64px; color:var(--fg-3); gap:12px; }

.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:100%; max-width:520px; box-shadow:0 16px 60px rgba(0,0,0,.28); max-height:90vh; display:flex; flex-direction:column; }
.modal-lg { max-width:760px; }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-subtle); flex-shrink:0; }
.modal-header h3 { margin:0; font-size:15px; font-weight:600; color:var(--fg-1); }
.modal-body { padding:20px; display:flex; flex-direction:column; gap:14px; overflow-y:auto; flex:1; }
.modal-footer { display:flex; justify-content:flex-end; gap:8px; padding:14px 20px; border-top:1px solid var(--border-subtle); flex-shrink:0; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-field { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:12px; font-weight:500; color:var(--fg-2); }
.form-input { font-size:13px; padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); color:var(--fg-1); background:var(--bg-canvas); outline:none; font-family:var(--font-sans); }
.form-input:focus { border-color:var(--halo-500); }
.code-area { font-family:monospace; }

.vars-section { background:var(--bg-sunken); border-radius:var(--r-md); padding:12px; }
.var-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.var-row:last-child { margin-bottom:0; }

.btn { font-family:var(--font-sans); font-weight:500; font-size:13px; padding:7px 12px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn:disabled { opacity:.6; cursor:not-allowed; }
.btn-sm { padding:4px 8px; font-size:12px; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-icon { background:none; border:none; cursor:pointer; color:var(--fg-3); padding:4px; border-radius:var(--r-sm); display:flex; align-items:center; }
.btn-icon:hover { background:var(--bg-sunken); color:var(--fg-1); }
.btn-danger { color:var(--danger-fg); }
.btn-danger:hover { background:var(--danger-bg); }
.error-msg { color:var(--danger-fg); font-size:13px; margin:0; }
</style>
