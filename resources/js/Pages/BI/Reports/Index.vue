<template>
  <AppLayout>
    <Head title="BI · Rapports" />

    <!-- Header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">BI · Rapports</h1>
        <p class="wh-page-subtitle">{{ reports.total }} rapport{{ reports.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="showScheduleDialog = true">
          <i class="pi pi-clock" style="font-size:13px" />
          Planifier
        </button>
        <button class="btn btn-primary" @click="showCreateDialog = true">
          <i class="pi pi-plus" style="font-size:13px" />
          Nouveau rapport
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <input v-model="search" class="filter-input" placeholder="Rechercher un rapport…" />
      <select v-model="filterStatus" class="filter-select">
        <option value="">Tous les statuts</option>
        <option value="draft">Brouillon</option>
        <option value="published">Publié</option>
        <option value="scheduled">Planifié</option>
      </select>
      <select v-model="filterType" class="filter-select">
        <option value="">Tous les types</option>
        <option value="pdf">PDF</option>
        <option value="excel">Excel</option>
        <option value="csv">CSV</option>
        <option value="dashboard">Dashboard</option>
      </select>
    </div>

    <!-- Reports table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Type</th>
            <th>Statut</th>
            <th>Planification</th>
            <th>Créé le</th>
            <th style="width:130px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="report in filteredReports" :key="report.id" class="wh-dt-row">
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="report-type-icon" :style="typeIconStyle(report.type)">
                  <i :class="typeIcon(report.type)" />
                </div>
                <div>
                  <p style="font-weight:600;color:var(--fg-1);margin:0;font-size:14px">{{ report.name }}</p>
                  <p v-if="report.description" style="color:var(--fg-3);margin:1px 0 0;font-size:12px">{{ report.description }}</p>
                </div>
              </div>
            </td>
            <td>
              <span class="wh-badge wh-badge-slate" style="text-transform:uppercase;font-size:10px">
                {{ report.type }}
              </span>
            </td>
            <td>
              <span :class="['wh-badge', statusBadge(report.status)]">
                <span class="wh-badge-dot" />
                {{ statusLabel(report.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3);font-size:13px">
              <span v-if="report.schedule">
                <i class="pi pi-clock" style="font-size:11px;margin-right:4px" />
                {{ report.schedule }}
              </span>
              <span v-else style="color:var(--fg-4,var(--fg-3))">—</span>
            </td>
            <td style="color:var(--fg-3);font-size:13px">{{ formatDate(report.created_at) }}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button
                  class="wh-row-btn"
                  title="Générer maintenant"
                  :class="{ 'wh-row-btn--loading': generatingId === report.id }"
                  @click="generateReport(report)"
                >
                  <i :class="generatingId === report.id ? 'pi pi-spin pi-spinner' : 'pi pi-play'" style="font-size:12px" />
                </button>
                <button class="wh-row-btn" title="Exporter PDF" @click="exportReport(report, 'pdf')">
                  <i class="pi pi-file-pdf" style="font-size:12px" />
                </button>
                <button class="wh-row-btn" title="Exporter Excel" @click="exportReport(report, 'excel')">
                  <i class="pi pi-file-excel" style="font-size:12px" />
                </button>
                <button class="wh-row-btn wh-row-btn--danger" title="Supprimer" @click="deleteReport(report)">
                  <i class="pi pi-trash" style="font-size:12px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!filteredReports.length">
            <td colspan="6" style="text-align:center;padding:48px 18px">
              <div style="display:flex;flex-direction:column;align-items:center;gap:10px;color:var(--fg-3)">
                <i class="pi pi-file-o" style="font-size:36px" />
                <p style="font-size:14px;font-weight:500;margin:0">Aucun rapport trouvé</p>
                <p style="font-size:12px;margin:0">{{ search || filterStatus || filterType ? 'Modifiez vos filtres.' : 'Créez votre premier rapport.' }}</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="reports.total > reports.per_page" style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ reports.total }} résultat{{ reports.total !== 1 ? 's' : '' }}</span>
        <div style="display:flex;gap:4px">
          <button class="page-btn" v-for="p in pageNumbers" :key="p" :class="{ 'page-btn--active': p === currentPage }" @click="currentPage = p">{{ p }}</button>
        </div>
      </div>
    </div>

    <!-- Scheduled reports panel -->
    <div v-if="scheduledReports.length" style="margin-top:24px">
      <div class="section-label" style="margin-bottom:12px">Rapports planifiés</div>
      <div class="scheduled-grid">
        <div v-for="report in scheduledReports" :key="report.id" class="scheduled-card">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <span style="font-size:13px;font-weight:600;color:var(--fg-1)">{{ report.name }}</span>
            <span class="wh-badge wh-badge-blue">
              <i class="pi pi-clock" style="font-size:10px" />
              {{ report.schedule }}
            </span>
          </div>
          <p style="font-size:12px;color:var(--fg-3);margin:0 0 8px">{{ report.description ?? 'Rapport automatique' }}</p>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:11px;color:var(--fg-3)">Prochain envoi : {{ report.next_run ? formatDate(report.next_run) : '—' }}</span>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px" @click="generateReport(report)">
              <i class="pi pi-play" style="font-size:11px" /> Exécuter
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create report dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau rapport" modal style="width:500px">
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div>
          <label class="wh-label">Nom du rapport</label>
          <InputText v-model="createForm.name" class="w-full" placeholder="ex. Rapport mensuel des ventes" />
        </div>
        <div>
          <label class="wh-label">Type d'export</label>
          <Select
            v-model="createForm.type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            placeholder="Sélectionner un format"
          />
        </div>
        <div>
          <label class="wh-label">Description (optionnel)</label>
          <Textarea v-model="createForm.description" class="w-full" rows="2" placeholder="Description du rapport…" />
        </div>
        <div>
          <label class="wh-label">Période de données</label>
          <Select
            v-model="createForm.period"
            :options="periodOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="scheduleCheck" v-model="createForm.scheduled" />
          <label for="scheduleCheck" class="wh-label" style="margin:0;cursor:pointer">Planifier automatiquement</label>
        </div>
        <div v-if="createForm.scheduled">
          <label class="wh-label">Fréquence</label>
          <Select
            v-model="createForm.schedule"
            :options="scheduleOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
      </div>
      <template #footer>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn btn-secondary" @click="showCreateDialog = false">Annuler</button>
          <button class="btn btn-primary" @click="createReport" :disabled="!createForm.name || !createForm.type">
            <i class="pi pi-check" style="font-size:13px" /> Créer le rapport
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Schedule dialog -->
    <Dialog v-model:visible="showScheduleDialog" header="Planifier un rapport" modal style="width:440px">
      <div style="padding:8px 0 16px;color:var(--fg-2);font-size:14px">
        Sélectionnez un rapport existant et définissez sa fréquence d'envoi automatique.
      </div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label class="wh-label">Rapport</label>
          <Select
            v-model="scheduleForm.reportId"
            :options="reports.data.map(r => ({ label: r.name, value: r.id }))"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div>
          <label class="wh-label">Fréquence</label>
          <Select
            v-model="scheduleForm.schedule"
            :options="scheduleOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>
        <div>
          <label class="wh-label">Destinataires (emails, séparés par virgule)</label>
          <InputText v-model="scheduleForm.recipients" class="w-full" placeholder="email@example.com, …" />
        </div>
      </div>
      <template #footer>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn btn-secondary" @click="showScheduleDialog = false">Annuler</button>
          <button class="btn btn-primary" @click="saveSchedule" :disabled="!scheduleForm.reportId">
            <i class="pi pi-clock" style="font-size:13px" /> Planifier
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Export loading toast -->
    <Transition name="toast">
      <div v-if="toastMsg" class="save-toast">
        <i :class="toastIcon" style="font-size:16px" />
        {{ toastMsg }}
      </div>
    </Transition>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Dialog, InputText, Select, Textarea } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  reports: { type: Object, required: true },
})

// ── State ──────────────────────────────────────────────────────────────────────
const search           = ref('')
const filterStatus     = ref('')
const filterType       = ref('')
const currentPage      = ref(1)
const generatingId     = ref(null)
const showCreateDialog = ref(false)
const showScheduleDialog = ref(false)
const toastMsg         = ref('')
const toastIcon        = ref('pi pi-check-circle')

const createForm = reactive({
  name:        '',
  type:        '',
  description: '',
  period:      'month',
  scheduled:   false,
  schedule:    'weekly',
})

const scheduleForm = reactive({
  reportId:   null,
  schedule:   'weekly',
  recipients: '',
})

// ── Options ───────────────────────────────────────────────────────────────────
const typeOptions = [
  { label: 'PDF',       value: 'pdf' },
  { label: 'Excel',     value: 'excel' },
  { label: 'CSV',       value: 'csv' },
  { label: 'Dashboard', value: 'dashboard' },
]
const periodOptions = [
  { label: 'Aujourd\'hui', value: 'today' },
  { label: 'Cette semaine', value: 'week' },
  { label: 'Ce mois',      value: 'month' },
  { label: 'Ce trimestre', value: 'quarter' },
  { label: 'Cette année',  value: 'year' },
]
const scheduleOptions = [
  { label: 'Quotidien',    value: 'daily' },
  { label: 'Hebdomadaire', value: 'weekly' },
  { label: 'Mensuel',      value: 'monthly' },
]

// ── Computed ──────────────────────────────────────────────────────────────────
const filteredReports = computed(() => {
  return (props.reports.data ?? []).filter(r => {
    const matchSearch = !search.value || r.name.toLowerCase().includes(search.value.toLowerCase())
    const matchStatus = !filterStatus.value || r.status === filterStatus.value
    const matchType   = !filterType.value   || r.type   === filterType.value
    return matchSearch && matchStatus && matchType
  })
})

const scheduledReports = computed(() =>
  (props.reports.data ?? []).filter(r => r.schedule)
)

const pageNumbers = computed(() => {
  const total = Math.ceil((props.reports.total ?? 0) / (props.reports.per_page ?? 15))
  return Array.from({ length: total }, (_, i) => i + 1)
})

// ── Methods ───────────────────────────────────────────────────────────────────
function statusBadge(status) {
  return { draft: 'wh-badge-slate', published: 'wh-badge-green', scheduled: 'wh-badge-blue' }[status] ?? 'wh-badge-slate'
}

function statusLabel(status) {
  return { draft: 'Brouillon', published: 'Publié', scheduled: 'Planifié' }[status] ?? status
}

function typeIcon(type) {
  return { pdf: 'pi pi-file-pdf', excel: 'pi pi-file-excel', csv: 'pi pi-file', dashboard: 'pi pi-th-large' }[type] ?? 'pi pi-file'
}

function typeIconStyle(type) {
  const map = {
    pdf:       { background: '#fff1f0', color: '#dc2626' },
    excel:     { background: '#f0fdf4', color: '#16a34a' },
    csv:       { background: '#f0f9ff', color: '#0369a1' },
    dashboard: { background: '#eff4ff', color: '#1d4ed8' },
  }
  return map[type] ?? { background: 'var(--bg-sunken)', color: 'var(--fg-3)' }
}

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

async function generateReport(report) {
  generatingId.value = report.id
  try {
    await axios.post(`/api/v1/bi/reports/${report.id}/generate`)
    showToast('Rapport généré avec succès', 'pi pi-check-circle')
  } catch (e) {
    showToast(e.response?.data?.message ?? 'Erreur lors de la génération', 'pi pi-exclamation-circle')
  } finally {
    generatingId.value = null
  }
}

async function exportReport(report, format) {
  try {
    const res = await axios.get(`/api/v1/bi/reports/${report.id}/export`, {
      params:       { format },
      responseType: 'blob',
    })
    const url  = URL.createObjectURL(res.data)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `${report.name}.${format === 'excel' ? 'xlsx' : format}`
    a.click()
    URL.revokeObjectURL(url)
    showToast(`Export ${format.toUpperCase()} téléchargé`, 'pi pi-download')
  } catch (e) {
    showToast('Erreur lors de l\'export', 'pi pi-exclamation-circle')
  }
}

async function deleteReport(report) {
  if (!confirm(`Supprimer le rapport "${report.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/bi/reports/${report.id}`)
    showToast('Rapport supprimé', 'pi pi-check-circle')
  } catch (e) {
    showToast('Erreur lors de la suppression', 'pi pi-exclamation-circle')
  }
}

async function createReport() {
  try {
    await axios.post('/api/v1/bi/reports', createForm)
    showCreateDialog.value = false
    Object.assign(createForm, { name: '', type: '', description: '', period: 'month', scheduled: false, schedule: 'weekly' })
    showToast('Rapport créé avec succès', 'pi pi-check-circle')
  } catch (e) {
    showToast(e.response?.data?.message ?? 'Erreur', 'pi pi-exclamation-circle')
  }
}

async function saveSchedule() {
  // Chantier 19 Lot 5: was POSTing to `bi/reports/{id}/schedule`, a route
  // that never existed (404 on every click) — this app already has a real,
  // fully-working recurring-delivery mechanism (`ScheduledReport` via
  // `bi/scheduled-reports`, confirmed tested in BIReportingTest.php), not a
  // second one hanging off `Report` itself. Repointed to the real endpoint,
  // matching its real field names (`report_id`, `recipients` as an array).
  const reportName = props.reports.data.find(r => r.id === scheduleForm.reportId)?.name ?? 'Rapport planifié'
  const recipients = scheduleForm.recipients
    .split(',')
    .map(r => r.trim())
    .filter(Boolean)
  try {
    await axios.post('/api/v1/bi/scheduled-reports', {
      name: reportName,
      report_id: scheduleForm.reportId,
      schedule: scheduleForm.schedule,
      recipients,
    })
    showScheduleDialog.value = false
    showToast('Planification enregistrée', 'pi pi-check-circle')
  } catch (e) {
    showToast('Erreur lors de la planification', 'pi pi-exclamation-circle')
  }
}

function showToast(msg, icon = 'pi pi-check-circle') {
  toastMsg.value  = msg
  toastIcon.value = icon
  setTimeout(() => { toastMsg.value = '' }, 3500)
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:16px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.section-label { font-size:12px; font-weight:600; color:var(--fg-2); letter-spacing:0.05em; text-transform:uppercase; }

.filter-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.filter-input, .filter-select {
  font-family:var(--font-sans); font-size:13px; padding:7px 10px;
  border:1px solid var(--border-subtle); border-radius:var(--r-md);
  background:var(--bg-canvas); color:var(--fg-1); outline:none;
}
.filter-input { flex:1; min-width:200px; }
.filter-input:focus, .filter-select:focus { border-color:var(--halo-400); }

.report-type-icon {
  width:32px; height:32px; border-radius:var(--r-sm);
  display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0;
}

/* Table */
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:12px 18px; border-bottom:1px solid var(--border-subtle); vertical-align:middle; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }

.wh-row-btn { width:30px; height:30px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }
.wh-row-btn--danger:hover { background:var(--danger-bg); color:var(--danger-fg); border-color:transparent; }
.wh-row-btn--loading { opacity:0.7; pointer-events:none; }

/* Scheduled reports */
.scheduled-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; }
.scheduled-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 18px; }

/* Badges */
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue  { background:var(--halo-50,#eff4ff); color:var(--halo-700,#1d4ed8); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }

/* Pagination */
.page-btn { min-width:30px; height:30px; padding:0 6px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; font-size:12px; display:inline-flex; align-items:center; justify-content:center; }
.page-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }
.page-btn--active { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }

/* Buttons */
.btn { font-family:var(--font-sans); font-weight:500; font-size:13px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }

.wh-label { display:block; font-size:12px; font-weight:600; color:var(--fg-2); margin-bottom:5px; }
.w-full { width:100%; }

/* Toast */
.save-toast { position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; align-items:center; gap:10px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:12px 18px; font-size:13px; color:var(--fg-1); box-shadow:0 4px 20px rgba(0,0,0,0.12); }
.toast-enter-active, .toast-leave-active { transition:all 0.3s ease; }
.toast-enter-from { transform:translateY(16px); opacity:0; }
.toast-leave-to   { transform:translateY(8px); opacity:0; }
</style>
