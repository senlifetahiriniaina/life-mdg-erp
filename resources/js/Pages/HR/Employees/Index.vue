<template>
  <AppLayout>
    <Head title="Collaborateurs" />

    <!-- Chantier 32.17 (HR deep 14-layer audit): this real, routed page
         never called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">RH · Collaborateurs</h1>
        <p class="wh-page-subtitle">{{ employees.total }} collaborateur{{ employees.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <a class="btn btn-secondary" :href="'/api/v1/hr/employees/export'"><i class="pi pi-download" style="font-size:13px" /> Exporter</a>
        <button class="btn btn-primary" @click="router.visit(route('hr.employees.create'))"><i class="pi pi-plus" style="font-size:13px" /> Ajouter</button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in stats" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Collaborateur</th>
            <th>Poste</th>
            <th>Département</th>
            <th>Statut</th>
            <th>Date d'entrée</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="emp in employees.data"
            :key="emp.id"
            class="wh-dt-row"
            @click="router.visit(route('hr.employees.show', emp.id))"
          >
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="emp-avatar">{{ initials(emp.full_name) }}</div>
                <div>
                  <div style="font-weight:500;color:var(--fg-1)">{{ emp.full_name }}</div>
                  <div style="font-family:var(--font-mono);font-size:11px;color:var(--fg-3)">{{ emp.employee_number }}</div>
                </div>
              </div>
            </td>
            <td style="color:var(--fg-2)">{{ emp.job_title ?? '—' }}</td>
            <td style="color:var(--fg-2)">{{ emp.department?.name ?? '—' }}</td>
            <td>
              <span :class="['wh-badge', emp.status === 'active' ? 'wh-badge-green' : 'wh-badge-slate']">
                <span class="wh-badge-dot" />{{ emp.status === 'active' ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td style="color:var(--fg-2);font-variant-numeric:tabular-nums">{{ formatDate(emp.hire_date) }}</td>
          </tr>
          <tr v-if="employees.data.length === 0">
            <td colspan="5" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucun collaborateur trouvé.</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ employees.total }} résultat{{ employees.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="employees.per_page"
          :total-records="employees.total"
          :first="(employees.current_page - 1) * employees.per_page"
          @page="onPage"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
// Chantier 32.17 (HR deep 14-layer audit): this entire page had ZERO click
// handlers anywhere — confirmed by grep before this fix — despite real
// routes/controller methods existing for every one of these actions:
// "Exporter" and "Ajouter" were dead buttons, each table row (styled with
// cursor:pointer, implying it should be clickable) had no navigation to the
// employee's own real detail page, and the Paginator had no @page handler
// at all so clicking a page number did nothing. All four wired to the real,
// already-existing routes/endpoints (employees.create/employees.show web
// routes, the newly-routed employees/export API endpoint — see routes/api.php).
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Paginator from 'primevue/paginator'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const props = defineProps({ employees: { type: Object, required: true } })

// Chantier 32.17 (HR deep 14-layer audit): see the AIAssistantPanel comment
// in the template — this page never called useAiAssistant() at all before.
const showAiPanel = ref(true)
const { guidance } = useAiAssistant('HR', 'view_employees_list')

const stats = computed(() => [
  { label: 'Total',            value: props.employees.total },
  { label: 'Actifs',           value: props.employees.data.filter(e => e.status === 'active').length },
  { label: 'En congé',         value: 0 },
  { label: 'Entrées ce mois',  value: 0 },
])

const initials   = (name) => name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() ?? '?'
const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'

const onPage = (event) => {
  router.get(
    route('hr.employees.index'),
    { page: event.page + 1 },
    { preserveState: true, replace: true },
  )
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
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.emp-avatar { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,var(--halo-500),var(--halo-700)); color:#fff; font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
</style>
