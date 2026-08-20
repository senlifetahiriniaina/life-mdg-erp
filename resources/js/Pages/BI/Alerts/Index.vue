<template>
  <AppLayout>
    <Head title="BI · Alertes" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">BI · Alertes</h1>
        <p class="wh-page-subtitle">{{ alerts.total }} alerte{{ alerts.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvelle alerte
        </button>
      </div>
    </div>

    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Condition</th>
            <th>Seuil</th>
            <th>Statut</th>
            <th>Dernière vérification</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="alert in alerts.data" :key="alert.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ alert.name }}</td>
            <!-- Chantier 19 Lot 5: was `alert.condition`, a field BiAlert
                 has never had (the real column is `condition_type`) — this
                 always rendered '—' regardless of the alert's real
                 condition. -->
            <td style="color:var(--fg-2)">{{ alert.condition_type ?? '—' }}</td>
            <td style="color:var(--fg-2);font-variant-numeric:tabular-nums">{{ alert.threshold ?? '—' }}</td>
            <td>
              <span :class="['wh-badge', statusBadge(alert.status)]">
                <span class="wh-badge-dot" />
                {{ statusLabel(alert.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ formatDate(alert.last_checked_at ?? alert.updated_at) }}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="wh-row-btn wh-row-btn-danger" title="Supprimer" @click="deleteAlert(alert.id)">
                  <i class="pi pi-trash" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!alerts.data.length">
            <td colspan="6" style="text-align:center;color:var(--fg-3);padding:48px 18px;font-size:14px">
              <div style="display:flex;flex-direction:column;align-items:center;gap:10px">
                <i class="pi pi-bell" style="font-size:32px;color:var(--fg-4)" />
                <span>Aucune alerte configurée.</span>
                <button class="btn btn-primary" @click="showCreate = true">
                  <i class="pi pi-plus" style="font-size:13px" /> Créer une alerte
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div
        v-if="alerts.total > alerts.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">{{ alerts.total }} résultat{{ alerts.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="alerts.per_page"
          :total-records="alerts.total"
          :first="(alerts.current_page - 1) * alerts.per_page"
        />
      </div>
    </div>

    <!-- Create dialog -->
    <Dialog v-model:visible="showCreate" header="Nouvelle alerte" modal style="width:480px">
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div>
          <label class="wh-label">Nom</label>
          <InputText v-model="form.name" class="w-full" placeholder="ex. Revenu sous 10 000 €" />
        </div>
        <div>
          <label class="wh-label">Métrique suivie</label>
          <InputText v-model="form.metric_name" class="w-full" placeholder="ex. monthly_revenue" />
        </div>
        <div>
          <label class="wh-label">Condition</label>
          <Select
            v-model="form.condition"
            :options="conditionOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            placeholder="Sélectionner une condition"
          />
        </div>
        <div>
          <label class="wh-label">Seuil</label>
          <InputNumber v-model="form.threshold" class="w-full" placeholder="0" />
        </div>
      </div>
      <template #footer>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn btn-secondary" @click="showCreate = false">Annuler</button>
          <button class="btn btn-primary" @click="createAlert" :disabled="!form.name || !form.metric_name">
            <i class="pi pi-check" style="font-size:13px" /> Créer
          </button>
        </div>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Paginator, Dialog, InputText, InputNumber, Select } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  alerts: { type: Object, required: true },
})

const page = usePage()

const showCreate = ref(false)
const form = reactive({ name: '', metric_name: '', condition: null as string | null, threshold: null as number | null })

// Chantier 19 Lot 5: AlertController::store() validates `condition_type`
// against `in:above,below,equals,change_pct` — this list's 'lt'/'gt'/'eq'
// values never matched (the wrong field name AND the wrong vocabulary, see
// createAlert() below).
const conditionOptions = [
  { label: 'Inférieur à', value: 'below' },
  { label: 'Supérieur à', value: 'above' },
  { label: 'Égal à', value: 'equals' },
]

const statusBadge = (s: string) => ({
  active:    'wh-badge-green',
  triggered: 'wh-badge-red',
  inactive:  'wh-badge-slate',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s: string) => ({
  active:    'Active',
  triggered: 'Déclenchée',
  inactive:  'Inactive',
}[s] ?? s)

const formatDate = (v: string) =>
  v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'

const createAlert = async () => {
  // Chantier 19 Lot 5: this used to POST the raw `{ name, condition,
  // threshold }` form — but AlertController::store() requires
  // `condition_type` (not `condition`, and with a different value
  // vocabulary — fixed above), `metric_name` (never sent at all), and
  // `channels`/`recipients` (both required arrays, never sent at all) —
  // every real "Créer" click 422'd. Defaults `channels` to in-app and
  // `recipients` to the creating user, matching this app's smart-defaults
  // convention rather than building a full recipient-picker UI for what
  // was, until now, a fully broken create flow.
  const res = await fetch('/api/v1/bi/alerts', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      name: form.name,
      metric_name: form.metric_name,
      condition_type: form.condition,
      threshold: form.threshold,
      channels: ['in_app'],
      recipients: page.props.auth?.user?.id ? [page.props.auth.user.id] : [],
    }),
  })
  if (!res.ok) {
    // eslint-disable-next-line no-alert
    alert('Échec de la création de l\'alerte.')
    return
  }
  showCreate.value = false
  router.reload({ only: ['alerts'] })
}

const deleteAlert = async (alertId: number) => {
  // eslint-disable-next-line no-alert
  if (!confirm('Supprimer cette alerte ?')) return
  await fetch(`/api/v1/bi/alerts/${alertId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  })
  router.reload({ only: ['alerts'] })
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-label { display:block; font-size:12px; font-weight:600; color:var(--fg-2); margin-bottom:5px; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); color:var(--fg-1); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-row-btn-danger:hover { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--red-500); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-red   { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
.w-full { width:100%; }
</style>
