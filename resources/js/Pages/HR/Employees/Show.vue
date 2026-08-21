<template>
  <AppLayout>
    <Head :title="employee.full_name" />

    <!-- Chantier 32.17 (HR deep 14-layer audit): this real, routed page
         never called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="page-head">
      <div style="display:flex;align-items:center;gap:14px">
        <button class="btn btn-icon" @click="$inertia.visit(route('hr.employees.index'))">
          <i class="pi pi-arrow-left" style="font-size:14px" />
        </button>
        <div class="emp-avatar">{{ initials }}</div>
        <div>
          <h1 class="wh-page-title">{{ employee.full_name }}</h1>
          <p class="wh-page-subtitle">{{ employee.job_title ?? '—' }} · {{ employee.department?.name ?? '—' }}</p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span :class="['wh-badge', employee.status === 'active' ? 'wh-badge-green' : 'wh-badge-slate']">
          <span class="wh-badge-dot" />{{ employee.status === 'active' ? 'Actif' : 'Inactif' }}
        </span>
        <!-- Chantier 32.17 (HR deep 14-layer audit): this page had no way at
             all to reach the real, already-routed edit form
             (hr.employees.edit) — the code comment on
             EmployeeWebController::edit() claimed this link already existed
             here, but it never actually did in this file. -->
        <button class="btn btn-secondary" @click="router.visit(route('hr.employees.edit', employee.id))">
          <i class="pi pi-pencil" style="font-size:13px" /> Modifier
        </button>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:16px">
      <!-- Info panel -->
      <div class="wh-panel" style="padding:20px">
        <p class="panel-section-title">Informations</p>
        <div style="display:flex;flex-direction:column;gap:14px;margin-top:12px">
          <div v-for="f in fields" :key="f.label" style="display:flex;gap:10px;align-items:flex-start">
            <i :class="f.icon" style="font-size:13px;color:var(--fg-4);margin-top:2px;width:14px;flex-shrink:0" />
            <div>
              <p style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:0.06em;margin:0">{{ f.label }}</p>
              <p style="font-size:13px;font-weight:500;color:var(--fg-1);margin:2px 0 0">{{ f.value || '—' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Leave balances -->
      <div class="wh-panel">
        <div class="wh-panel-head">
          <h3>Soldes de congés</h3>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--border-subtle)">
          <div v-for="lb in leaveBalances" :key="lb.type" class="leave-cell">
            <div class="leave-remaining font-display">{{ lb.remaining }}</div>
            <div style="font-size:12px;color:var(--fg-2);margin-top:2px">{{ lb.type }}</div>
            <div style="font-size:11px;color:var(--fg-4);margin-top:2px">sur {{ lb.total }} jours</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
// Chantier 32.17 (HR deep 14-layer audit): `leaveBalances` used to be a
// literal, hardcoded array — the exact same "100% mock data" bug already
// fixed once for Leave/Analytics.vue at Chantier 8.3 — now a real prop
// computed server-side by EmployeeWebController::show(). The "Salaire"
// field used to read `employee.salary`, a key EmployeeResource has never
// exposed (by design — no PII/compensation leak) — now reads the real
// current-compensation figures the controller resolves via
// EmployeeCompensation, and formats in the employee's real currency
// (defaulting to MGA, not the previous hardcoded €) rather than assuming
// EUR. "Modifier" navigates to the real, already-routed edit form.
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

interface LeaveBalanceRow {
  type: string
  total: number
  remaining: number
}

const props = defineProps({
  employee: { type: Object, required: true },
  leaveBalances: { type: Array as () => LeaveBalanceRow[], default: () => [] },
})

// Chantier 32.17 (HR deep 14-layer audit): see the AIAssistantPanel comment
// in the template — this page never called useAiAssistant() at all before.
const showAiPanel = ref(true)
const { guidance } = useAiAssistant('HR', 'view_employee_detail')

const initials = computed(() =>
  props.employee.full_name?.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase() ?? '?'
)

const formattedSalary = computed(() => {
  if (!props.employee.current_salary) return null
  const currency = props.employee.salary_currency || 'MGA'
  return `${Number(props.employee.current_salary).toLocaleString('fr-FR')} ${currency}`
})

const fields = computed(() => [
  { label: 'Email',           icon: 'pi pi-envelope', value: props.employee.email },
  { label: 'Téléphone',       icon: 'pi pi-phone',    value: props.employee.phone },
  { label: 'N° Employé',      icon: 'pi pi-id-card',  value: props.employee.employee_number },
  { label: "Date d'entrée",   icon: 'pi pi-calendar', value: props.employee.hire_date },
  { label: 'Salaire',         icon: 'pi pi-wallet',   value: formattedSalary.value },
])
</script>

<style scoped>
.page-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:24px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:13px; color:var(--fg-3); }
.btn-icon { background:var(--bg-canvas); color:var(--fg-2); border:1px solid var(--border-subtle); border-radius:var(--r-md); width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
.emp-avatar { width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg,var(--halo-500),var(--halo-700)); color:#fff; font-size:15px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.panel-section-title { margin:0; font-size:11px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; }
.wh-panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-panel-head h3 { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); }
.leave-cell { background:var(--bg-canvas); padding:24px 20px; text-align:center; }
.leave-remaining { font-size:32px; font-weight:700; color:var(--halo-500); line-height:1; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
</style>
