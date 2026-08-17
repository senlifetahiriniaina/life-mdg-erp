<template>
  <AppLayout>
    <Head title="Helpdesk · Modèles de réponse IA" />
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Modèles de réponse IA</h1>
        <p class="wh-page-subtitle">{{ pagination.total ?? 0 }} modèle{{ pagination.total !== 1 ? 's' : '' }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="padding:12px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
      <input v-model="filters.category" placeholder="Catégorie…" class="wh-input" style="width:160px" @input="debounceLoad" />
      <select v-model="filters.language" class="wh-input" style="width:140px" @change="load">
        <option value="">Toutes langues</option>
        <option value="fr">Français</option>
        <option value="en">Anglais</option>
        <option value="pt">Portugais</option>
        <option value="es">Espagnol</option>
      </select>
      <select v-model="filters.tone" class="wh-input" style="width:150px" @change="load">
        <option value="">Tous les tons</option>
        <option value="formal">Formel</option>
        <option value="friendly">Amical</option>
        <option value="empathetic">Empathique</option>
        <option value="technical">Technique</option>
      </select>
      <select v-model="filters.status" class="wh-input" style="width:140px" @change="load">
        <option value="active">Actifs</option>
        <option value="archived">Archivés</option>
        <option value="">Tous statuts</option>
      </select>
    </div>

    <!-- Templates list -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Catégorie</th>
            <th>Langue</th>
            <th>Ton</th>
            <th>Utilisations</th>
            <th>Satisfaction moy.</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in templates" :key="t.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ t.title }}</td>
            <td>{{ t.category }}</td>
            <td>{{ t.language }}</td>
            <td>{{ t.tone }}</td>
            <td>{{ t.usage_count ?? 0 }}</td>
            <td>{{ t.avg_satisfaction_rating ? `${t.avg_satisfaction_rating}/5` : '—' }}</td>
            <td><span :class="['badge', t.status === 'active' ? 'badge-green' : 'badge-gray']">{{ t.status }}</span></td>
          </tr>
          <tr v-if="!loading && !templates.length">
            <td colspan="7" style="text-align:center;padding:32px 18px;color:var(--fg-3)">Aucun modèle trouvé.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Test on a ticket -->
    <div class="wh-panel" style="margin-top:24px;padding:18px">
      <h3 style="font-size:14px;font-weight:600;color:var(--fg-1);margin:0 0 12px">Tester les suggestions IA sur un ticket</h3>
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
        <input v-model.number="testTicketId" type="number" placeholder="ID du ticket" class="wh-input" style="width:160px" />
        <button class="btn btn-primary" :disabled="!testTicketId || testing" @click="testSuggestions">
          <i v-if="testing" class="pi pi-spin pi-spinner" style="font-size:12px" /> Obtenir des suggestions
        </button>
      </div>
      <p v-if="testError" style="color:var(--danger-fg,#dc2626);font-size:13px">{{ testError }}</p>
      <div v-if="suggestions.length" style="display:flex;flex-direction:column;gap:8px">
        <div v-for="s in suggestions" :key="s.id" class="suggestion-card">
          <p style="font-size:13px;color:var(--fg-1);margin:0 0 6px">{{ s.response }}</p>
          <p style="font-size:11px;color:var(--fg-3);margin:0">{{ s.reason }} · pertinence {{ Math.round((s.relevance ?? 0) * 100) }}% · confiance {{ Math.round((s.confidence ?? 0) * 100) }}%</p>
        </div>
      </div>
      <p v-else-if="testedOnce && !testing" style="color:var(--fg-3);font-size:13px">Aucune suggestion disponible pour ce ticket.</p>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const loading = ref(false)
const templates = ref([])
const pagination = ref({})
const filters = reactive({ category: '', language: '', tone: '', status: 'active' })

let debounceTimer = null
function debounceLoad() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(load, 350)
}

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.category) params.category = filters.category
    if (filters.language) params.language = filters.language
    if (filters.tone) params.tone = filters.tone
    if (filters.status) params.status = filters.status
    const { data } = await axios.get('/api/v1/helpdesk/cs-ai/response-templates', { params })
    templates.value = data.data ?? []
    pagination.value = data.meta ?? {}
  } finally {
    loading.value = false
  }
}

const testTicketId = ref(null)
const testing = ref(false)
const testedOnce = ref(false)
const testError = ref('')
const suggestions = ref([])

async function testSuggestions() {
  if (!testTicketId.value) return
  testing.value = true
  testError.value = ''
  testedOnce.value = true
  try {
    const { data } = await axios.get('/api/v1/helpdesk/cs-ai/response-suggestions', {
      params: { ticket_id: testTicketId.value },
    })
    suggestions.value = data.suggestions ?? []
  } catch (err) {
    testError.value = err.response?.data?.message || 'Échec de la récupération des suggestions.'
    suggestions.value = []
  } finally {
    testing.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-size:22px; font-weight:700; color:var(--fg-1); }
.wh-page-subtitle { margin:3px 0 0; font-size:13px; color:var(--fg-3); }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-input { padding:7px 10px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; outline:none; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:12px 18px; border-bottom:1px solid var(--border-subtle); vertical-align:middle; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }
.badge { padding:2px 7px; border-radius:4px; font-size:11px; }
.badge-green { background:var(--green-50,#f0fdf4); color:var(--green-600,#16a34a); }
.badge-gray { background:var(--slate-100,#f1f5f9); color:var(--slate-600,#475569); }
.btn { font-weight:500; font-size:13px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:disabled { opacity:.6; cursor:not-allowed; }
.suggestion-card { background:var(--bg-sunken); border-radius:var(--r-md); padding:10px 12px; }
</style>
