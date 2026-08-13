<template>
  <AppLayout>
    <Head title="Campagnes Email" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Email · Campagnes</h1>
        <p class="wh-page-subtitle">{{ campaigns.total }} campagne{{ campaigns.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvelle campagne
        </button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in kpiList" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Campaigns table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Campagne</th>
            <th>Type</th>
            <th>Statut</th>
            <th class="num">Destinataires</th>
            <th class="num">Taux d'ouverture</th>
            <th>Planifiée</th>
            <th>Envoyée</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="campaign in campaigns.data" :key="campaign.id" class="wh-dt-row">
            <td>
              <p style="font-weight:500;color:var(--fg-1);margin:0">{{ campaign.name }}</p>
              <p style="font-size:12px;color:var(--fg-3);margin:2px 0 0">{{ campaign.subject }}</p>
            </td>
            <td style="color:var(--fg-2);text-transform:capitalize">{{ campaign.type ?? 'regular' }}</td>
            <td>
              <span :class="statusBadge(campaign.status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ statusLabel(campaign.status) }}
              </span>
            </td>
            <td class="num" style="color:var(--fg-2)">{{ campaign.total_recipients ?? 0 }}</td>
            <td class="num" style="color:var(--fg-2)">{{ openRate(campaign) }}%</td>
            <td style="color:var(--fg-3)">{{ formatDate(campaign.scheduled_at) }}</td>
            <td style="color:var(--fg-3)">{{ formatDate(campaign.sent_at) }}</td>
          </tr>
          <tr v-if="!campaigns.data.length">
            <td colspan="7" style="text-align:center;color:var(--fg-3);padding:32px 18px">Aucune campagne trouvée.</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ campaigns.total }} résultat{{ campaigns.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="campaigns.per_page"
          :total-records="campaigns.total"
          :first="(campaigns.current_page - 1) * campaigns.per_page"
        />
      </div>
    </div>

    <!-- Create Campaign Dialog -->
    <Dialog v-model:visible="showCreate" header="Nouvelle campagne" :modal="true" :style="{ width: '520px' }">
      <div class="space-y-4 py-2">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-1">Nom de la campagne <span class="text-red-500">*</span></label>
          <InputText v-model="form.name" class="w-full" placeholder="ex. Newsletter Mars" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-1">Objet <span class="text-red-500">*</span></label>
          <InputText v-model="form.subject" class="w-full" placeholder="Objet de l'email…" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-1">Nom expéditeur</label>
            <InputText v-model="form.from_name" class="w-full" placeholder="WideHalo" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-1">Email expéditeur</label>
            <InputText v-model="form.from_email" class="w-full" placeholder="hello@example.com" type="email" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-1">Type</label>
            <Select
              v-model="form.type"
              :options="typeOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-1">Planification (optionnel)</label>
            <DatePicker v-model="form.scheduled_at" class="w-full" show-time hour-format="24" />
          </div>
        </div>
        <Message v-if="createError" severity="error" :closable="false">{{ createError }}</Message>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showCreate = false">Annuler</button>
        <button class="btn btn-primary" :disabled="saving" @click="submitCreate">
          <i class="pi pi-save" style="font-size:13px" />
          {{ saving ? 'Enregistrement…' : 'Enregistrer comme brouillon' }}
        </button>
      </template>
    </Dialog>
    <GuidedTour tour-id="email-campaigns" :steps="tourSteps" />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Paginator, Dialog, InputText, Select, DatePicker, Message } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'

const tourSteps = [
  { tag: 'KPIs · Étape 1 / 5', icon: 'pi-chart-bar', title: 'Métriques d\'emailing', description: 'Les tuiles KPI affichent le nombre total de campagnes, les emails envoyés, le taux d\'ouverture et le taux de clic moyens.' },
  { tag: 'Campagnes · Étape 2 / 5', icon: 'pi-envelope', title: 'Liste des campagnes', description: 'Chaque ligne montre le nom, le type (régulière, automatisation), le statut, les destinataires et les statistiques en temps réel.' },
  { tag: 'Créer · Étape 3 / 5', icon: 'pi-plus', title: 'Nouvelle campagne', description: 'Le bouton « Nouvelle campagne » ouvre un formulaire pour configurer l\'objet, le contenu HTML, l\'audience et la date d\'envoi.' },
  { tag: 'Segmentation · Étape 4 / 5', icon: 'pi-users', title: 'Cibler votre audience', description: 'Sélectionnez un segment de contacts ou une liste personnalisée pour envoyer le bon message aux bonnes personnes.' },
  { tag: 'Rapport · Étape 5 / 5', icon: 'pi-chart-line', title: 'Statistiques détaillées', description: 'Cliquez sur une campagne pour voir le rapport complet : taux d\'ouverture, clics, désabonnements, bounces.' },
]

const props = defineProps({
  campaigns: { type: Object, required: true },
  stats:     { type: Object, required: true },
})

const kpiList = computed(() => [
  { label: 'Total',       value: props.stats.total },
  { label: 'Brouillons',  value: props.stats.draft },
  { label: 'Envoyées',    value: props.stats.sent },
  { label: 'Planifiées',  value: props.stats.scheduled },
])

const showCreate  = ref(false)
const saving      = ref(false)
const createError = ref(null)

const typeOptions = [
  { label: 'Régulière',  value: 'regular' },
  { label: 'A/B Test',   value: 'ab_test' },
  { label: 'Automatisée', value: 'automated' },
]

const defaultForm = () => ({
  name: '', subject: '', from_name: '', from_email: '',
  type: 'regular', scheduled_at: null, status: 'draft',
})
const form = ref(defaultForm())

const openCreate = () => {
  form.value = defaultForm()
  createError.value = null
  showCreate.value = true
}

const submitCreate = async () => {
  if (!form.value.name || !form.value.subject) {
    createError.value = 'Le nom et l\'objet sont requis.'
    return
  }
  saving.value = true
  createError.value = null
  try {
    const res = await fetch('/api/v1/email/campaigns', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
      body: JSON.stringify({
        ...form.value,
        scheduled_at: form.value.scheduled_at ? new Date(form.value.scheduled_at).toISOString() : null,
      }),
    })
    if (!res.ok) {
      const err = await res.json()
      createError.value = err.message ?? 'Échec de la création de la campagne.'
      return
    }
    showCreate.value = false
    router.reload()
  } finally {
    saving.value = false
  }
}

const statusBadge = (s) => ({
  draft: 'wh-badge-slate', sent: 'wh-badge-green', scheduled: 'wh-badge-blue',
  sending: 'wh-badge-amber', paused: 'wh-badge-amber', cancelled: 'wh-badge-red',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s) => ({
  draft: 'Brouillon', sent: 'Envoyée', scheduled: 'Planifiée',
  sending: 'En envoi', paused: 'En pause', cancelled: 'Annulée',
}[s] ?? s)

const openRate = (c) => (!c.total_sent ? '0.00' : ((c.total_opened / c.total_sent) * 100).toFixed(2))
const formatDate = (v) => v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
@media (max-width:640px) { .wh-kpi-grid { grid-template-columns:repeat(2,1fr); } }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin-bottom:6px; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
</style>
