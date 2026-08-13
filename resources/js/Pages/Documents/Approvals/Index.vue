<template>
  <AppLayout>
    <Head title="Approbations" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Workflow d'approbation</h1>
        <p class="wh-page-subtitle">Gérez les approbations de documents</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
      <button class="tab" :class="activeTab === 'pending' ? 'tab-active' : ''" @click="activeTab = 'pending'">
        Mes approbations en attente
        <span v-if="pending.length" class="tab-badge">{{ pending.length }}</span>
      </button>
      <button class="tab" :class="activeTab === 'all' ? 'tab-active' : ''" @click="activeTab = 'all'">
        Toutes les instances
      </button>
    </div>

    <!-- Pending tab -->
    <div v-if="activeTab === 'pending'">
      <div v-if="pending.length === 0" class="empty-state">
        <i class="pi pi-check-circle" style="font-size:40px;color:var(--success-fg)" />
        <p>Aucune approbation en attente. Tout est à jour !</p>
      </div>
      <div v-else class="wh-panel">
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Document</th>
              <th>Workflow</th>
              <th>Étape</th>
              <th>Demandé par</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="instance in pending" :key="instance.id" class="wh-dt-row">
              <td style="font-weight:500;color:var(--fg-1)">{{ instance.document?.title }}</td>
              <td style="color:var(--fg-2)">{{ instance.workflow?.name }}</td>
              <td>
                <span class="step-badge">Étape {{ instance.current_step }}</span>
              </td>
              <td style="color:var(--fg-2)">{{ instance.initiated_by?.name }}</td>
              <td style="color:var(--fg-3)">{{ formatDate(instance.created_at) }}</td>
              <td>
                <div style="display:flex;gap:8px">
                  <button class="btn btn-success btn-sm" @click="decide(instance, 'approved')">
                    <i class="pi pi-check" style="font-size:11px" /> Approuver
                  </button>
                  <button class="btn btn-danger-soft btn-sm" @click="openReject(instance)">
                    <i class="pi pi-times" style="font-size:11px" /> Rejeter
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- All instances tab -->
    <div v-if="activeTab === 'all'">
      <div v-if="allInstances.length === 0" class="empty-state">
        <i class="pi pi-inbox" style="font-size:40px;color:var(--fg-4)" />
        <p>Aucune instance d'approbation trouvée.</p>
      </div>
      <div v-else class="wh-panel">
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Document</th>
              <th>Workflow</th>
              <th>Statut</th>
              <th>Progression</th>
              <th>Demandé par</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="instance in allInstances"
              :key="instance.id"
              class="wh-dt-row"
              @click="selectInstance(instance)"
              style="cursor:pointer"
            >
              <td style="font-weight:500;color:var(--fg-1)">{{ instance.document?.title }}</td>
              <td style="color:var(--fg-2)">{{ instance.workflow?.name }}</td>
              <td>
                <span :class="['status-badge', statusClass(instance.status)]">{{ statusLabel(instance.status) }}</span>
              </td>
              <td>
                <span style="font-size:12px;color:var(--fg-2)">
                  Étape {{ instance.current_step }} / {{ instance.workflow?.steps?.length || '?' }}
                </span>
              </td>
              <td style="color:var(--fg-2)">{{ instance.initiated_by?.name }}</td>
              <td style="color:var(--fg-3)">{{ formatDate(instance.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Instance detail modal -->
    <div v-if="selectedInstance" class="modal-overlay" @click.self="selectedInstance=null">
      <div class="modal">
        <div class="modal-header">
          <h3>Détail — {{ selectedInstance.document?.title }}</h3>
          <button class="btn-icon" @click="selectedInstance=null"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <div class="timeline">
            <div
              v-for="(step, idx) in (selectedInstance.workflow?.steps || [])"
              :key="idx"
              class="timeline-step"
              :class="getStepClass(selectedInstance, idx + 1)"
            >
              <div class="step-dot">
                <i v-if="getDecisionForStep(selectedInstance, idx + 1)?.decision === 'approved'" class="pi pi-check" style="font-size:10px;color:#fff" />
                <i v-else-if="getDecisionForStep(selectedInstance, idx + 1)?.decision === 'rejected'" class="pi pi-times" style="font-size:10px;color:#fff" />
                <span v-else style="font-size:10px;font-weight:700;color:#fff">{{ idx + 1 }}</span>
              </div>
              <div class="step-content">
                <div class="step-name">{{ step.name }}</div>
                <div v-if="getDecisionForStep(selectedInstance, idx + 1)" class="step-decision">
                  <span :class="getDecisionForStep(selectedInstance, idx + 1)!.decision === 'approved' ? 'text-success' : 'text-danger'">
                    {{ getDecisionForStep(selectedInstance, idx + 1)!.decision === 'approved' ? 'Approuvé' : 'Rejeté' }}
                  </span>
                  <span style="color:var(--fg-3);font-size:12px"> · {{ getDecisionForStep(selectedInstance, idx + 1)!.approver?.name }} · {{ formatDate(getDecisionForStep(selectedInstance, idx + 1)!.decided_at) }}</span>
                  <div v-if="getDecisionForStep(selectedInstance, idx + 1)!.comment" style="font-size:12px;color:var(--fg-2);margin-top:2px;font-style:italic">{{ getDecisionForStep(selectedInstance, idx + 1)!.comment }}</div>
                </div>
                <div v-else style="font-size:12px;color:var(--fg-3)">
                  {{ idx + 1 === selectedInstance.current_step ? 'En attente d\'approbation' : 'Non commencé' }}
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="selectedInstance=null">Fermer</button>
        </div>
      </div>
    </div>

    <!-- Reject modal -->
    <div v-if="rejectInstance" class="modal-overlay" @click.self="rejectInstance=null">
      <div class="modal" style="max-width:400px">
        <div class="modal-header">
          <h3>Rejeter l'approbation</h3>
          <button class="btn-icon" @click="rejectInstance=null"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <div class="form-field">
            <label class="form-label">Commentaire (optionnel)</label>
            <textarea v-model="rejectComment" class="form-input" rows="3" placeholder="Raison du rejet..." />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="rejectInstance=null">Annuler</button>
          <button class="btn btn-danger" @click="decide(rejectInstance, 'rejected')">Confirmer le rejet</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  pending:      { type: Array, default: () => [] },
  allInstances: { type: Array, default: () => [] },
})

const activeTab       = ref('pending')
const pending         = ref(props.pending)
const allInstances    = ref(props.allInstances)
const selectedInstance = ref(null)
const rejectInstance  = ref(null)
const rejectComment   = ref('')

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('fr-FR') : '—'
}

function statusLabel(s) {
  return { pending: 'En attente', approved: 'Approuvé', rejected: 'Rejeté', cancelled: 'Annulé' }[s] || s
}

function statusClass(s) {
  return { pending: 'status-pending', approved: 'status-approved', rejected: 'status-rejected', cancelled: 'status-cancelled' }[s] || ''
}

function getStepClass(instance, stepNum) {
  const decision = getDecisionForStep(instance, stepNum)
  if (decision?.decision === 'approved') return 'step-done'
  if (decision?.decision === 'rejected') return 'step-rejected'
  if (stepNum === instance.current_step) return 'step-current'
  return 'step-future'
}

function getDecisionForStep(instance, stepNum) {
  return instance.decisions?.find(d => d.step_number === stepNum) || null
}

function selectInstance(instance) {
  selectedInstance.value = instance
}

function openReject(instance) {
  rejectInstance.value = instance
  rejectComment.value  = ''
}

async function decide(instance, decision) {
  try {
    await axios.post(`/api/v1/documents/approvals/${instance.id}/decide`, {
      decision,
      comment: decision === 'rejected' ? rejectComment.value : undefined,
    })
    pending.value = pending.value.filter(i => i.id !== instance.id)
    const idx = allInstances.value.findIndex(i => i.id === instance.id)
    if (idx !== -1) {
      allInstances.value[idx].status = decision === 'rejected' ? 'rejected' : 'pending'
    }
    rejectInstance.value = null
  } catch (err) {
    alert(err?.response?.data?.message || 'Erreur lors de la soumission.')
  }
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; }
.wh-page-title { margin:0; font-size:28px; font-weight:600; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }

.tab-bar { display:flex; gap:0; border-bottom:1px solid var(--border-subtle); margin-bottom:20px; }
.tab { padding:10px 16px; font-size:13px; font-weight:500; color:var(--fg-2); background:none; border:none; border-bottom:2px solid transparent; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all var(--dur-fast); margin-bottom:-1px; }
.tab:hover { color:var(--fg-1); }
.tab-active { color:var(--halo-600); border-bottom-color:var(--halo-500); }
.tab-badge { background:var(--danger-bg); color:var(--danger-fg); font-size:10px; font-weight:700; padding:1px 6px; border-radius:var(--r-pill); }

.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }

.step-badge { background:var(--halo-50); color:var(--halo-700); font-size:11px; font-weight:600; padding:2px 8px; border-radius:var(--r-pill); }

.status-badge { display:inline-flex; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:600; }
.status-pending  { background:var(--warning-bg,#fef3c7); color:var(--warning-fg,#b45309); }
.status-approved { background:var(--success-bg); color:var(--success-fg); }
.status-rejected { background:var(--danger-bg); color:var(--danger-fg); }
.status-cancelled { background:var(--bg-sunken); color:var(--fg-2); }

.empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:64px; color:var(--fg-3); gap:12px; }

/* Timeline */
.timeline { display:flex; flex-direction:column; gap:0; }
.timeline-step { display:flex; gap:14px; padding:12px 0; border-bottom:1px solid var(--border-subtle); }
.timeline-step:last-child { border-bottom:0; }
.step-dot { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; }
.step-current .step-dot  { background:var(--halo-500); }
.step-done .step-dot     { background:var(--success-fg); }
.step-rejected .step-dot { background:var(--danger-fg); }
.step-future .step-dot   { background:var(--bg-sunken); border:2px solid var(--border-subtle); }
.step-future .step-dot span { color:var(--fg-4) !important; }
.step-name { font-weight:500; font-size:14px; color:var(--fg-1); }
.step-decision { margin-top:4px; }
.text-success { color:var(--success-fg); font-weight:600; font-size:13px; }
.text-danger  { color:var(--danger-fg); font-weight:600; font-size:13px; }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; display:flex; align-items:center; justify-content:center; padding:24px; }
.modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:100%; max-width:560px; box-shadow:0 16px 60px rgba(0,0,0,.28); max-height:90vh; display:flex; flex-direction:column; }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-subtle); flex-shrink:0; }
.modal-header h3 { margin:0; font-size:15px; font-weight:600; color:var(--fg-1); }
.modal-body { padding:20px; overflow-y:auto; flex:1; }
.modal-footer { display:flex; justify-content:flex-end; gap:8px; padding:14px 20px; border-top:1px solid var(--border-subtle); }

.form-field { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:12px; font-weight:500; color:var(--fg-2); }
.form-input { font-size:13px; padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); color:var(--fg-1); background:var(--bg-canvas); outline:none; font-family:var(--font-sans); }

.btn { font-family:var(--font-sans); font-weight:500; font-size:13px; padding:7px 12px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-sm { padding:5px 10px; font-size:12px; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-success { background:var(--success-fg); color:#fff; }
.btn-success:hover { opacity:.9; }
.btn-danger { background:var(--danger-fg); color:#fff; }
.btn-danger:hover { opacity:.9; }
.btn-danger-soft { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--danger-bg); }
.btn-danger-soft:hover { opacity:.9; }
.btn-icon { background:none; border:none; cursor:pointer; color:var(--fg-3); padding:4px; border-radius:var(--r-sm); display:flex; align-items:center; }
</style>
