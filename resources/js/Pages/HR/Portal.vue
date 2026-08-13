<template>
  <AppLayout>
    <Head title="Mon espace RH" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">HR · Mon espace</h1>
        <p v-if="profile" class="wh-page-subtitle">{{ profile.first_name }} {{ profile.last_name }} · {{ profile.job_position?.title }}</p>
      </div>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <div v-else style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start">
      <!-- Profile card -->
      <div>
        <div class="wh-panel" style="padding:20px;text-align:center;margin-bottom:16px">
          <div class="portal-avatar">{{ initials }}</div>
          <div style="font-size:16px;font-weight:700;color:var(--fg-1);margin-bottom:4px">
            {{ profile?.first_name }} {{ profile?.last_name }}
          </div>
          <div style="font-size:13px;color:var(--fg-3)">{{ profile?.job_position?.title }}</div>
          <div style="font-size:12px;color:var(--fg-4);margin-top:4px">{{ profile?.department?.name }}</div>
          <div v-if="profile?.manager" style="margin-top:10px;font-size:12px;color:var(--fg-3)">
            <i class="pi pi-user" style="font-size:10px" />
            Manager : {{ profile.manager.first_name }} {{ profile.manager.last_name }}
          </div>
          <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);text-align:left">
            <div class="portal-info-row"><span>Email</span> <span>{{ profile?.email }}</span></div>
            <div class="portal-info-row"><span>Tél.</span> <span>{{ profile?.phone || '—' }}</span></div>
            <div class="portal-info-row"><span>Embauche</span> <span>{{ formatDate(profile?.hire_date) }}</span></div>
            <div class="portal-info-row"><span>Type</span> <span>{{ profile?.employment_type }}</span></div>
          </div>
        </div>

        <!-- Leave balance -->
        <div class="wh-panel" style="padding:16px">
          <div style="font-size:13px;font-weight:600;color:var(--fg-1);margin-bottom:12px">
            <i class="pi pi-calendar-times" style="font-size:12px;color:var(--halo-blue)" /> Soldes de congés {{ new Date().getFullYear() }}
          </div>
          <div v-for="bal in leaveBalance" :key="bal.id" style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;margin-bottom:3px">
              <span style="font-size:12px;color:var(--fg-2)">{{ bal.name }}</span>
              <span style="font-size:12px;font-weight:600;color:var(--fg-1)">{{ bal.days_remaining }}j restants</span>
            </div>
            <div style="height:4px;background:var(--bg-3);border-radius:2px;overflow:hidden">
              <div
                :style="{
                  width: bal.days_per_year > 0 ? (bal.days_taken / bal.days_per_year * 100) + '%' : '0%',
                  height: '100%',
                  background: bal.days_remaining > 2 ? 'var(--success)' : 'var(--danger)',
                  borderRadius: '2px'
                }"
              />
            </div>
            <div style="font-size:11px;color:var(--fg-4);margin-top:2px">
              {{ bal.days_taken }}j pris / {{ bal.days_per_year }}j
            </div>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div>
        <!-- Tabs -->
        <div style="display:flex;gap:4px;margin-bottom:12px">
          <button v-for="tab in tabs" :key="tab.key" :class="['btn', activeTab === tab.key ? 'btn-primary' : 'btn-secondary']" @click="activeTab = tab.key">
            <i :class="['pi', tab.icon]" style="font-size:12px" /> {{ tab.label }}
          </button>
        </div>

        <!-- Leave requests -->
        <div v-if="activeTab === 'leaves'">
          <div class="wh-panel" style="padding:12px 16px;margin-bottom:12px;display:flex;gap:10px;align-items:center">
            <span style="font-size:14px;font-weight:600;color:var(--fg-1)">Mes demandes de congé</span>
            <span style="flex:1" />
            <button class="btn btn-primary" @click="showLeaveForm = true">
              <i class="pi pi-plus" style="font-size:12px" /> Nouvelle demande
            </button>
          </div>

          <div v-if="showLeaveForm" class="wh-panel" style="padding:16px;margin-bottom:12px">
            <div style="font-size:14px;font-weight:600;margin-bottom:12px">Nouvelle demande de congé</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="wh-label">Type de congé</label>
                <select v-model="leaveForm.leave_type_id" class="wh-input" style="width:100%">
                  <option v-for="bal in leaveBalance" :key="bal.id" :value="bal.id">{{ bal.name }}</option>
                </select>
              </div>
              <div />
              <div>
                <label class="wh-label">Date de début</label>
                <input type="date" v-model="leaveForm.start_date" class="wh-input" style="width:100%" />
              </div>
              <div>
                <label class="wh-label">Date de fin</label>
                <input type="date" v-model="leaveForm.end_date" class="wh-input" style="width:100%" />
              </div>
              <div style="grid-column:span 2">
                <label class="wh-label">Motif (optionnel)</label>
                <textarea v-model="leaveForm.reason" class="wh-input" rows="2" style="width:100%;resize:vertical" />
              </div>
            </div>
            <div style="display:flex;gap:8px;margin-top:12px">
              <button class="btn btn-primary" @click="submitLeave" :disabled="submitLoading">
                <i class="pi pi-send" style="font-size:12px" /> Soumettre
              </button>
              <button class="btn btn-secondary" @click="showLeaveForm = false">Annuler</button>
            </div>
          </div>

          <div class="wh-panel" style="position:relative">
            <table class="wh-dt" style="font-size:13px">
              <thead>
                <tr><th>Période</th><th>Type</th><th>Jours</th><th>Statut</th><th>Motif</th></tr>
              </thead>
              <tbody>
                <tr v-for="req in leaveRequests.data" :key="req.id" class="wh-dt-row">
                  <td>{{ formatDate(req.start_date) }} → {{ formatDate(req.end_date) }}</td>
                  <td>{{ req.leave_type?.name }}</td>
                  <td>{{ req.days }}j</td>
                  <td><span :class="['badge', leaveStatusClass(req.status)]">{{ req.status }}</span></td>
                  <td style="color:var(--fg-3)">{{ req.reason || '—' }}</td>
                </tr>
                <tr v-if="!leaveRequests.data?.length">
                  <td colspan="5" style="text-align:center;padding:24px;color:var(--fg-3)">Aucune demande</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Payslips -->
        <div v-if="activeTab === 'payslips'">
          <div class="wh-panel">
            <table class="wh-dt" style="font-size:13px">
              <thead>
                <tr><th>Période</th><th class="num">Salaire brut</th><th class="num">Salaire net</th><th>Statut</th><th>Date paiement</th></tr>
              </thead>
              <tbody>
                <tr v-for="pay in payslips.data" :key="pay.id" class="wh-dt-row">
                  <td>{{ formatDate(pay.period_start) }} → {{ formatDate(pay.period_end) }}</td>
                  <td class="num">{{ fmt(pay.gross_salary) }}</td>
                  <td class="num" style="font-weight:600">{{ fmt(pay.net_salary) }}</td>
                  <td><span :class="['badge', pay.status === 'paid' ? 'badge-green' : 'badge-gray']">{{ pay.status }}</span></td>
                  <td>{{ formatDate(pay.payment_date) }}</td>
                </tr>
                <tr v-if="!payslips.data?.length">
                  <td colspan="5" style="text-align:center;padding:24px;color:var(--fg-3)">Aucun bulletin de paie</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const loading = ref(true)
const profile = ref(null)
const leaveBalance = ref([])
const leaveRequests = ref({ data: [] })
const payslips = ref({ data: [] })
const activeTab = ref('leaves')
const showLeaveForm = ref(false)
const submitLoading = ref(false)

const tabs = [
  { key: 'leaves', label: 'Congés', icon: 'pi-calendar-times' },
  { key: 'payslips', label: 'Bulletins de paie', icon: 'pi-file' },
]

const leaveForm = reactive({ leave_type_id: '', start_date: '', end_date: '', reason: '' })

const initials = computed(() => {
  if (!profile.value) return '?'
  return ((profile.value.first_name?.[0] || '') + (profile.value.last_name?.[0] || '')).toUpperCase()
})

async function submitLeave() {
  submitLoading.value = true
  try {
    await axios.post('/api/v1/hr/portal/leave-requests', leaveForm)
    showLeaveForm.value = false
    Object.assign(leaveForm, { leave_type_id: '', start_date: '', end_date: '', reason: '' })
    const [lb, lr] = await Promise.all([
      axios.get('/api/v1/hr/portal/leave-balance'),
      axios.get('/api/v1/hr/portal/leave-requests'),
    ])
    leaveBalance.value = lb.data
    leaveRequests.value = lr.data
  } catch (e) {
    console.error(e)
  } finally {
    submitLoading.value = false
  }
}

function formatDate(d) {
  if (!d) return '—'
  return new Intl.DateTimeFormat('fr-FR').format(new Date(d))
}

function fmt(v) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
}

function leaveStatusClass(s) {
  return { approved: 'badge-green', pending: 'badge-orange', rejected: 'badge-red' }[s] || 'badge-gray'
}

onMounted(async () => {
  try {
    const [p, lb, lr, ps] = await Promise.all([
      axios.get('/api/v1/hr/portal/profile'),
      axios.get('/api/v1/hr/portal/leave-balance'),
      axios.get('/api/v1/hr/portal/leave-requests'),
      axios.get('/api/v1/hr/portal/payslips'),
    ])
    profile.value = p.data
    leaveBalance.value = lb.data
    leaveRequests.value = lr.data
    payslips.value = ps.data
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.portal-avatar { width:64px;height:64px;border-radius:50%;background:var(--halo-blue);color:#fff;font-size:22px;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 12px; }
.portal-info-row { display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid var(--border); }
.portal-info-row span:first-child { color:var(--fg-3); }
.portal-info-row span:last-child { color:var(--fg-1);font-weight:500; }
.num { text-align:right; }
.badge-green  { background:var(--green-50); color:var(--green-600); padding:2px 7px; border-radius:4px; font-size:11px; }
.badge-orange { background:var(--yellow-50); color:var(--yellow-600); padding:2px 7px; border-radius:4px; font-size:11px; }
.badge-red    { background:var(--red-50); color:var(--red-600); padding:2px 7px; border-radius:4px; font-size:11px; }
.badge-gray   { background:var(--slate-100); color:var(--slate-600); padding:2px 7px; border-radius:4px; font-size:11px; }
</style>
