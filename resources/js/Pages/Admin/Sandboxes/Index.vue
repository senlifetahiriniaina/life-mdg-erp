<template>
  <AppLayout>
    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">
            <i class="pi pi-box" />
            Environnements sandbox
          </h1>
          <p class="wh-page-subtitle">
            Clones jetables d'un tenant pour tester en isolation — expirent après {{ EXPIRY_DAYS }} jours
          </p>
        </div>
        <button class="wh-btn wh-btn-primary" @click="openCreateDialog">
          <i class="pi pi-plus" />
          Nouveau sandbox
        </button>
      </div>

      <div v-if="loadingList" class="wh-loading-state">
        <i class="pi pi-spin pi-spinner" />
        Chargement...
      </div>

      <div v-else-if="sandboxes.length === 0" class="wh-empty-state">
        <i class="pi pi-inbox" style="font-size: 32px; color: #D1D5DB" />
        <p>Aucun sandbox actif pour ce tenant</p>
      </div>

      <div v-else class="wh-sandbox-list">
        <div v-for="sb in sandboxes" :key="sb.id" class="wh-sandbox-card">
          <div class="wh-sb-header">
            <div>
              <span class="wh-sb-name">{{ sb.name }}</span>
              <span class="wh-sb-tenant">
                <i class="pi pi-copy" /> cloné depuis {{ sb.parent_tenant?.name ?? sb.parent_tenant_id }}
              </span>
            </div>
            <span :class="['wh-sb-status', `status-${sb.status}`]">{{ statusLabel(sb.status) }}</span>
          </div>
          <div class="wh-sb-meta">
            <span>Expire le {{ formatDate(sb.expires_at) }}</span>
          </div>
          <div class="wh-sb-actions">
            <button class="wh-btn wh-btn-secondary wh-btn-sm" :disabled="processing === sb.id" @click="resetSandbox(sb)">
              <i class="pi pi-refresh" /> Réinitialiser
            </button>
            <button class="wh-btn wh-btn-danger wh-btn-sm" :disabled="processing === sb.id" @click="deleteSandbox(sb)">
              <i class="pi pi-trash" /> Supprimer
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Sandbox Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau sandbox" :modal="true" :style="{ width: '460px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Nom du sandbox</label>
          <input v-model="createForm.name" class="wh-input" placeholder="ex: Test migration Q3" />
        </div>
        <div class="wh-form-field">
          <label>Tenant à cloner</label>
          <select v-model="createForm.tenant_id" class="wh-input">
            <option value="">— Sélectionner un tenant —</option>
            <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.slug }})</option>
          </select>
        </div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showCreateDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="creating || !createForm.name || !createForm.tenant_id" @click="createSandbox">
          <i v-if="creating" class="pi pi-spin pi-spinner" />
          Créer
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const toast = useToast()
const EXPIRY_DAYS = 30

const sandboxes = ref([])
const tenants = ref([])
const loadingList = ref(false)
const processing = ref(null)
const creating = ref(false)
const showCreateDialog = ref(false)

const createForm = ref({ name: '', tenant_id: '' })

const loadSandboxes = async () => {
  loadingList.value = true
  try {
    const { data } = await axios.get('/api/v1/core/sandboxes')
    sandboxes.value = data.data ?? data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les sandboxes', life: 3000 })
  } finally {
    loadingList.value = false
  }
}

const loadTenants = async () => {
  try {
    const { data } = await axios.get('/api/v1/tenants')
    tenants.value = data.data ?? data
  } catch {
    tenants.value = []
  }
}

onMounted(() => {
  loadSandboxes()
  loadTenants()
})

const openCreateDialog = () => {
  createForm.value = { name: '', tenant_id: '' }
  showCreateDialog.value = true
}

const createSandbox = async () => {
  creating.value = true
  try {
    await axios.post('/api/v1/core/sandboxes', createForm.value)
    toast.add({ severity: 'success', summary: 'Créé', detail: 'Sandbox créé avec succès', life: 3000 })
    showCreateDialog.value = false
    await loadSandboxes()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la création', life: 4000 })
  } finally {
    creating.value = false
  }
}

const resetSandbox = async (sb) => {
  if (!confirm(`Réinitialiser le sandbox "${sb.name}" ?`)) return
  processing.value = sb.id
  try {
    await axios.post(`/api/v1/core/sandboxes/${sb.id}/reset`)
    toast.add({ severity: 'success', summary: 'Réinitialisé', detail: 'Sandbox réinitialisé et expiration prolongée', life: 3000 })
    await loadSandboxes()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de réinitialiser le sandbox', life: 3000 })
  } finally {
    processing.value = null
  }
}

const deleteSandbox = async (sb) => {
  if (!confirm(`Supprimer le sandbox "${sb.name}" ?`)) return
  processing.value = sb.id
  try {
    await axios.delete(`/api/v1/core/sandboxes/${sb.id}`)
    toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Sandbox supprimé', life: 3000 })
    await loadSandboxes()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer le sandbox', life: 3000 })
  } finally {
    processing.value = null
  }
}

const statusLabel = (s) => ({ active: 'Actif', expired: 'Expiré', deleted: 'Supprimé' }[s] ?? s)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'
</script>

<style scoped>
.wh-page { max-width: 900px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }

.wh-sandbox-list { display: flex; flex-direction: column; gap: 10px; }
.wh-loading-state, .wh-empty-state {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 40px; color: #9CA3AF; font-size: 13px;
}

.wh-sandbox-card {
  border: 1px solid #E5E7EB; border-radius: 10px; padding: 14px 16px;
  background: #fff; transition: box-shadow 0.15s;
}
.wh-sandbox-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.dark .wh-sandbox-card { background: #1F2937; border-color: #374151; }

.wh-sb-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.wh-sb-name { font-size: 14px; font-weight: 600; margin-right: 10px; }
.wh-sb-tenant { font-size: 12px; color: #6B7280; }
.wh-sb-status {
  font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px;
}
.wh-sb-status.status-active { background: #D1FAE5; color: #065F46; }
.wh-sb-status.status-expired { background: #FEE2E2; color: #991B1B; }
.wh-sb-status.status-deleted { background: #F3F4F6; color: #6B7280; }

.wh-sb-meta { font-size: 12px; color: #9CA3AF; margin-bottom: 10px; }
.wh-sb-actions { display: flex; gap: 8px; }

.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; transition: opacity 0.15s; }
.wh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-btn-sm { padding: 4px 12px; font-size: 12px; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-primary:hover:not(:disabled) { background: #1D4ED8; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-btn-secondary:hover:not(:disabled) { background: #E5E7EB; }
.wh-btn-danger { background: #EF4444; color: #fff; }
.wh-btn-danger:hover:not(:disabled) { background: #DC2626; }

.wh-dialog-form { display: flex; flex-direction: column; gap: 14px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-form-field label { font-size: 13px; font-weight: 500; color: #374151; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; width: 100%; background: inherit; color: inherit; }
</style>
