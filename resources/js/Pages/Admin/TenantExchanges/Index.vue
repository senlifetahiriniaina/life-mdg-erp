<template>
  <AppLayout>
    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">
            <i class="pi pi-arrow-right-arrow-left" />
            Échanges inter-tenant
          </h1>
          <p class="wh-page-subtitle">
            Partage de données entre tenants avec validation bilatérale
          </p>
        </div>
        <button class="wh-btn wh-btn-primary" @click="showSendDialog = true">
          <i class="pi pi-send" />
          Nouvel échange
        </button>
      </div>

      <!-- Tabs -->
      <div class="wh-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          :class="['wh-tab', activeTab === tab.value ? 'active' : '']"
          @click="activeTab = tab.value"
        >
          {{ tab.label }}
          <span v-if="tab.count > 0" class="wh-tab-badge">{{ tab.count }}</span>
        </button>
      </div>

      <!-- Exchange list -->
      <div class="wh-exchange-list">
        <div v-if="loadingList" class="wh-loading-state">
          <i class="pi pi-spin pi-spinner" />
          Chargement...
        </div>

        <div v-else-if="exchanges.length === 0" class="wh-empty-state">
          <i class="pi pi-inbox" style="font-size: 32px; color: #D1D5DB" />
          <p>Aucun échange {{ activeTab === 'incoming' ? 'reçu' : 'envoyé' }}</p>
        </div>

        <div v-for="ex in exchanges" :key="ex.id" class="wh-exchange-card">
          <div class="wh-ex-header">
            <div class="wh-ex-meta">
              <span :class="['wh-ex-type-badge', `type-${ex.exchange_type}`]">
                {{ typeLabel(ex.exchange_type) }}
              </span>
              <span class="wh-ex-tenant">
                <i class="pi pi-building" />
                {{ activeTab === 'incoming' ? `De : ${ex.source_tenant_id}` : `Vers : ${ex.target_tenant_id}` }}
              </span>
            </div>
            <span :class="['wh-ex-status', `status-${ex.status}`]">
              <i :class="statusIcon(ex.status)" />
              {{ statusLabel(ex.status) }}
            </span>
          </div>

          <p v-if="ex.message" class="wh-ex-message">{{ ex.message }}</p>

          <!-- Payload preview -->
          <div v-if="ex.payload" class="wh-ex-payload">
            <span class="wh-ex-payload-label">Contenu :</span>
            <code class="wh-ex-payload-content">{{ formatPayload(ex.payload) }}</code>
          </div>

          <div v-if="ex.rejection_reason" class="wh-ex-rejection">
            <i class="pi pi-times-circle" />
            {{ ex.rejection_reason }}
          </div>

          <!-- Actions -->
          <div class="wh-ex-actions">
            <template v-if="activeTab === 'incoming' && ex.status === 'pending'">
              <button
                class="wh-btn wh-btn-success wh-btn-sm"
                :disabled="processing === ex.id"
                @click="acceptExchange(ex)"
              >
                <i class="pi pi-check" /> Accepter
              </button>
              <button
                class="wh-btn wh-btn-danger wh-btn-sm"
                :disabled="processing === ex.id"
                @click="openRejectDialog(ex)"
              >
                <i class="pi pi-times" /> Rejeter
              </button>
            </template>
            <button
              v-if="activeTab === 'outgoing' && ex.status === 'pending'"
              class="wh-btn wh-btn-secondary wh-btn-sm"
              :disabled="processing === ex.id"
              @click="cancelExchange(ex)"
            >
              <i class="pi pi-ban" /> Annuler
            </button>
            <span class="wh-ex-date">
              {{ formatDate(ex.created_at) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Send Exchange Dialog -->
    <Dialog v-model:visible="showSendDialog" header="Envoyer un échange" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Tenant cible (slug ou ID)</label>
          <input v-model="sendForm.target" class="wh-input" placeholder="acme-corp-a3b7kx ou ID..." />
        </div>
        <div class="wh-form-field">
          <label>Type d'échange</label>
          <select v-model="sendForm.type" class="wh-input">
            <option value="product_share">Partage produit</option>
            <option value="catalog_share">Partage catalogue</option>
            <option value="contact_share">Partage contact</option>
            <option value="quote_share">Partage devis</option>
            <option value="order_reference">Référence commande</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Message (optionnel)</label>
          <textarea v-model="sendForm.message" class="wh-input" rows="3" placeholder="Message pour le destinataire..." />
        </div>
        <div class="wh-form-field">
          <label>Données (JSON)</label>
          <textarea v-model="sendForm.payloadText" class="wh-input wh-monospace" rows="4" placeholder='{"product_id": 42, "name": "Widget Pro"}' />
          <span v-if="payloadError" class="wh-field-error">{{ payloadError }}</span>
        </div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showSendDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="sending" @click="sendExchange">
          <i v-if="sending" class="pi pi-spin pi-spinner" />
          Envoyer
        </button>
      </template>
    </Dialog>

    <!-- Reject Dialog -->
    <Dialog v-model:visible="showRejectDialog" header="Motif de rejet" :modal="true" :style="{ width: '380px' }">
      <div class="wh-form-field">
        <label>Raison du rejet</label>
        <textarea v-model="rejectReason" class="wh-input" rows="3" placeholder="Expliquez pourquoi vous rejetez cet échange..." />
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showRejectDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-danger" :disabled="processing !== null" @click="confirmReject">
          Confirmer le rejet
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const toast = useToast()

interface Exchange {
  id: number
  source_tenant_id: string
  target_tenant_id: string
  exchange_type: string
  payload: Record<string, unknown>
  status: 'pending' | 'accepted' | 'rejected' | 'cancelled' | 'expired'
  message?: string | null
  rejection_reason?: string | null
  created_at: string
}

const activeTab = ref<'incoming' | 'outgoing'>('incoming')
const exchanges = ref<Exchange[]>([])
const loadingList = ref(false)
const processing = ref<number | null>(null)
const sending = ref(false)

const showSendDialog = ref(false)
const showRejectDialog = ref(false)
const rejectTarget = ref<Exchange | null>(null)
const rejectReason = ref('')
const payloadError = ref('')

const sendForm = ref({
  target: '',
  type: 'product_share',
  message: '',
  payloadText: '{}',
})

const pendingIncoming = computed(() =>
  activeTab.value === 'incoming'
    ? exchanges.value.filter(e => e.status === 'pending').length
    : 0
)

const tabs = computed<Array<{ value: 'incoming' | 'outgoing'; label: string; count: number }>>(() => [
  { value: 'incoming', label: 'Reçus', count: pendingIncoming.value },
  { value: 'outgoing', label: 'Envoyés', count: 0 },
])

const loadExchanges = async () => {
  loadingList.value = true
  try {
    const url = activeTab.value === 'incoming'
      ? '/api/v1/core/exchanges/incoming'
      : '/api/v1/core/exchanges/outgoing'
    const { data } = await axios.get(url)
    exchanges.value = data.data ?? data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les échanges', life: 3000 })
  } finally {
    loadingList.value = false
  }
}

watch(activeTab, loadExchanges)
onMounted(loadExchanges)

const sendExchange = async () => {
  payloadError.value = ''
  let payload: Record<string, unknown>
  try {
    payload = JSON.parse(sendForm.value.payloadText)
  } catch {
    payloadError.value = 'JSON invalide'
    return
  }
  sending.value = true
  try {
    await axios.post('/api/v1/core/exchanges', {
      target_tenant_id: sendForm.value.target,
      exchange_type: sendForm.value.type,
      message: sendForm.value.message || null,
      payload,
    })
    toast.add({ severity: 'success', summary: 'Envoyé', detail: 'Demande d\'échange envoyée', life: 3000 })
    showSendDialog.value = false
    sendForm.value = { target: '', type: 'product_share', message: '', payloadText: '{}' }
    if (activeTab.value === 'outgoing') loadExchanges()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erreur lors de l\'envoi'
    toast.add({ severity: 'error', summary: 'Erreur', detail: msg, life: 4000 })
  } finally {
    sending.value = false
  }
}

const acceptExchange = async (ex: Exchange) => {
  processing.value = ex.id
  try {
    await axios.post(`/api/v1/core/exchanges/${ex.id}/accept`)
    toast.add({ severity: 'success', summary: 'Accepté', detail: 'Échange accepté avec succès', life: 3000 })
    loadExchanges()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'accepter l\'échange', life: 3000 })
  } finally {
    processing.value = null
  }
}

const openRejectDialog = (ex: Exchange) => {
  rejectTarget.value = ex
  rejectReason.value = ''
  showRejectDialog.value = true
}

const confirmReject = async () => {
  if (!rejectTarget.value) return
  processing.value = rejectTarget.value.id
  try {
    await axios.post(`/api/v1/core/exchanges/${rejectTarget.value.id}/reject`, { reason: rejectReason.value })
    toast.add({ severity: 'info', summary: 'Rejeté', detail: 'Échange rejeté', life: 3000 })
    showRejectDialog.value = false
    loadExchanges()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de rejeter l\'échange', life: 3000 })
  } finally {
    processing.value = null
  }
}

const cancelExchange = async (ex: Exchange) => {
  processing.value = ex.id
  try {
    await axios.delete(`/api/v1/core/exchanges/${ex.id}`)
    toast.add({ severity: 'info', summary: 'Annulé', detail: 'Échange annulé', life: 3000 })
    loadExchanges()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'annuler', life: 3000 })
  } finally {
    processing.value = null
  }
}

const typeLabel = (t: string) => ({
  product_share: 'Produit',
  catalog_share: 'Catalogue',
  contact_share: 'Contact',
  quote_share: 'Devis',
  order_reference: 'Commande',
})[t] ?? t

const statusLabel = (s: string) => ({
  pending: 'En attente',
  accepted: 'Accepté',
  rejected: 'Rejeté',
  cancelled: 'Annulé',
  expired: 'Expiré',
})[s] ?? s

const statusIcon = (s: string) => ({
  pending: 'pi pi-clock',
  accepted: 'pi pi-check-circle',
  rejected: 'pi pi-times-circle',
  cancelled: 'pi pi-ban',
  expired: 'pi pi-calendar-times',
})[s] ?? 'pi pi-circle'

const formatPayload = (p: Record<string, unknown>) => {
  const str = JSON.stringify(p)
  return str.length > 80 ? str.slice(0, 80) + '...' : str
}

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
</script>

<style scoped>
.wh-page { max-width: 900px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }

.wh-tabs { display: flex; gap: 4px; border-bottom: 1px solid #E5E7EB; margin-bottom: 16px; }
.wh-tab {
  padding: 8px 16px; font-size: 13px; font-weight: 500; cursor: pointer;
  border: none; background: none; color: #6B7280; border-bottom: 2px solid transparent; margin-bottom: -1px;
  display: flex; align-items: center; gap: 6px;
}
.wh-tab.active { color: #2563EB; border-bottom-color: #2563EB; }
.wh-tab-badge {
  background: #EF4444; color: #fff; font-size: 10px; font-weight: 700;
  border-radius: 10px; padding: 1px 6px;
}

.wh-exchange-list { display: flex; flex-direction: column; gap: 10px; }
.wh-loading-state, .wh-empty-state {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 40px; color: #9CA3AF; font-size: 13px;
}

.wh-exchange-card {
  border: 1px solid #E5E7EB; border-radius: 10px; padding: 14px 16px;
  background: #fff; transition: box-shadow 0.15s;
}
.wh-exchange-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.dark .wh-exchange-card { background: #1F2937; border-color: #374151; }

.wh-ex-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.wh-ex-meta { display: flex; align-items: center; gap: 10px; }
.wh-ex-type-badge {
  font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: #EFF6FF; color: #1D4ED8;
}
.wh-ex-tenant { font-size: 12px; color: #6B7280; display: flex; align-items: center; gap: 4px; }
.wh-ex-status {
  font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px;
  display: flex; align-items: center; gap: 4px;
}
.wh-ex-status.status-pending { background: #FEF3C7; color: #92400E; }
.wh-ex-status.status-accepted { background: #D1FAE5; color: #065F46; }
.wh-ex-status.status-rejected { background: #FEE2E2; color: #991B1B; }
.wh-ex-status.status-cancelled, .wh-ex-status.status-expired { background: #F3F4F6; color: #6B7280; }

.wh-ex-message { font-size: 13px; color: #374151; margin-bottom: 8px; }
.wh-ex-payload { font-size: 11px; margin-bottom: 8px; }
.wh-ex-payload-label { color: #9CA3AF; margin-right: 6px; }
.wh-ex-payload-content { background: #F9FAFB; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
.wh-ex-rejection { font-size: 12px; color: #991B1B; background: #FEE2E2; padding: 4px 10px; border-radius: 4px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }

.wh-ex-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.wh-ex-date { margin-left: auto; font-size: 11px; color: #9CA3AF; }

.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; transition: opacity 0.15s; }
.wh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-btn-sm { padding: 4px 12px; font-size: 12px; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-primary:hover:not(:disabled) { background: #1D4ED8; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-btn-secondary:hover:not(:disabled) { background: #E5E7EB; }
.wh-btn-success { background: #10B981; color: #fff; }
.wh-btn-success:hover:not(:disabled) { background: #059669; }
.wh-btn-danger { background: #EF4444; color: #fff; }
.wh-btn-danger:hover:not(:disabled) { background: #DC2626; }

.wh-dialog-form { display: flex; flex-direction: column; gap: 14px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-form-field label { font-size: 13px; font-weight: 500; color: #374151; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; width: 100%; background: inherit; color: inherit; }
.wh-monospace { font-family: monospace; font-size: 12px; }
.wh-field-error { font-size: 11px; color: #EF4444; }
</style>
