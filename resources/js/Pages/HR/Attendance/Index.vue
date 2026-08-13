<template>
  <AppLayout>
    <Head title="Attendance" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Attendance</h1>
        <p class="wh-page-subtitle">Track working hours and leaves</p>
      </div>
    </div>

    <!-- Clock In/Out Banner -->
    <div class="wh-panel" style="margin-bottom:16px;padding:20px;display:flex;align-items:center;gap:16px">
      <div style="flex:1">
        <div v-if="clockedIn" style="font-size:15px;font-weight:600;color:var(--success)">
          <i class="pi pi-circle-fill" style="font-size:10px" /> Clocked in since {{ clockedSince }}
        </div>
        <div v-else style="font-size:15px;font-weight:600;color:var(--fg-3)">
          <i class="pi pi-circle" style="font-size:10px" /> Not clocked in
        </div>
        <div style="font-size:12px;color:var(--fg-3);margin-top:4px">
          {{ today }} · Week: {{ weekHours }}h · Month: {{ monthHours }}h
        </div>
      </div>
      <button
        v-if="!clockedIn"
        class="btn btn-primary"
        @click="clockIn"
        :disabled="actionLoading"
      >
        <i class="pi pi-sign-in" style="font-size:13px" /> Clock In
      </button>
      <button
        v-else
        class="btn btn-secondary"
        @click="clockOut"
        :disabled="actionLoading"
      >
        <i class="pi pi-sign-out" style="font-size:13px" /> Clock Out
      </button>
      <button class="btn btn-secondary" @click="showLeaveForm = true">
        <i class="pi pi-calendar-plus" style="font-size:13px" /> Request Leave
      </button>
    </div>

    <!-- Calendar View Toggle -->
    <div style="display:flex;gap:4px;margin-bottom:12px;align-items:center">
      <button :class="['btn', viewMode === 'week' ? 'btn-primary' : 'btn-secondary']" @click="viewMode = 'week'">Weekly</button>
      <button :class="['btn', viewMode === 'month' ? 'btn-primary' : 'btn-secondary']" @click="viewMode = 'month'">Monthly</button>
      <span style="flex:1" />
      <button class="btn btn-secondary" @click="prevPeriod"><i class="pi pi-chevron-left" style="font-size:12px" /></button>
      <span style="font-size:14px;font-weight:600;color:var(--fg-1);padding:0 12px">{{ periodLabel }}</span>
      <button class="btn btn-secondary" @click="nextPeriod"><i class="pi pi-chevron-right" style="font-size:12px" /></button>
    </div>

    <!-- Records Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Date</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Break</th>
            <th class="num">Hours</th>
            <th>Type</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rec in filteredRecords" :key="rec.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ formatDate(rec.clock_in) }}</td>
            <td>{{ formatTime(rec.clock_in) }}</td>
            <td>{{ rec.clock_out ? formatTime(rec.clock_out) : '—' }}</td>
            <td>{{ rec.break_minutes }}min</td>
            <td class="num">{{ rec.worked_hours ?? 0 }}h</td>
            <td>
              <span :class="['wh-badge', typeBadge(rec.type)]">{{ rec.type }}</span>
            </td>
            <td style="color:var(--fg-3);font-size:12px">{{ rec.notes ?? '—' }}</td>
          </tr>
          <tr v-if="!filteredRecords.length">
            <td colspan="7" style="text-align:center;padding:32px;color:var(--fg-3)">No records for this period</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Leave Request Modal -->
    <div v-if="showLeaveForm" class="modal-overlay" @click.self="showLeaveForm = false">
      <div class="wh-panel modal-box">
        <div style="font-size:16px;font-weight:700;margin-bottom:16px">Request Leave</div>
        <div style="display:flex;flex-direction:column;gap:12px">
          <div>
            <label class="wh-label">Leave Type</label>
            <select v-model="leaveForm.leave_type_id" class="wh-input" style="width:100%">
              <option v-for="type in leaveTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Start Date</label>
              <input type="date" v-model="leaveForm.start_date" class="wh-input" style="width:100%" />
            </div>
            <div>
              <label class="wh-label">End Date</label>
              <input type="date" v-model="leaveForm.end_date" class="wh-input" style="width:100%" />
            </div>
          </div>
          <div>
            <label class="wh-label">Reason</label>
            <textarea v-model="leaveForm.reason" class="wh-input" rows="2" style="width:100%;resize:vertical" />
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px">
          <button class="btn btn-primary" @click="submitLeave" :disabled="actionLoading">
            <i class="pi pi-send" style="font-size:12px" /> Submit
          </button>
          <button class="btn btn-secondary" @click="showLeaveForm = false">{{ $t('common.cancel') }}</button>
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

const clockedIn = ref(false)
const clockedSince = ref('')
const currentRecord = ref<any>(null)
const records = ref<any[]>([])
const weekHours = ref(0)
const monthHours = ref(0)
const actionLoading = ref(false)
const showLeaveForm = ref(false)
const viewMode = ref<'week' | 'month'>('month')
const leaveTypes = ref<any[]>([])
const currentDate = ref(new Date())

const leaveForm = reactive({ leave_type_id: '', start_date: '', end_date: '', reason: '' })

const today = computed(() => new Intl.DateTimeFormat('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).format(new Date()))

const periodLabel = computed(() => {
  const d = currentDate.value
  if (viewMode.value === 'month') {
    return new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(d)
  }
  return `Week of ${formatDate(d.toISOString())}`
})

const filteredRecords = computed(() => {
  const d = currentDate.value
  return records.value.filter((r) => {
    const rd = new Date(r.clock_in)
    if (viewMode.value === 'month') {
      return rd.getMonth() === d.getMonth() && rd.getFullYear() === d.getFullYear()
    }
    const weekStart = new Date(d)
    weekStart.setDate(d.getDate() - d.getDay())
    const weekEnd = new Date(weekStart)
    weekEnd.setDate(weekStart.getDate() + 6)
    return rd >= weekStart && rd <= weekEnd
  })
})

function prevPeriod() {
  const d = new Date(currentDate.value)
  if (viewMode.value === 'month') d.setMonth(d.getMonth() - 1)
  else d.setDate(d.getDate() - 7)
  currentDate.value = d
  loadAttendance()
}

function nextPeriod() {
  const d = new Date(currentDate.value)
  if (viewMode.value === 'month') d.setMonth(d.getMonth() + 1)
  else d.setDate(d.getDate() + 7)
  currentDate.value = d
  loadAttendance()
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Intl.DateTimeFormat('en-GB').format(new Date(d))
}

function formatTime(d: string) {
  if (!d) return '—'
  return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' }).format(new Date(d))
}

function typeBadge(type: string) {
  return { regular: 'badge-blue', overtime: 'badge-orange', remote: 'badge-green' }[type] ?? 'badge-gray'
}

async function loadStatus() {
  const res = await axios.get('/api/v1/hr/attendance/status')
  clockedIn.value = res.data.clocked_in
  if (res.data.record) {
    clockedSince.value = formatTime(res.data.record.clock_in)
    currentRecord.value = res.data.record
  }
}

async function loadAttendance() {
  const d = currentDate.value
  const res = await axios.get('/api/v1/hr/me/attendance', {
    params: { year: d.getFullYear(), month: d.getMonth() + 1 },
  })
  records.value = res.data.records ?? []
  monthHours.value = res.data.monthly_hours ?? 0
}

async function clockIn() {
  actionLoading.value = true
  try {
    await axios.post('/api/v1/hr/attendance/clock-in')
    await loadStatus()
    await loadAttendance()
  } finally {
    actionLoading.value = false
  }
}

async function clockOut() {
  actionLoading.value = true
  try {
    await axios.post('/api/v1/hr/attendance/clock-out')
    await loadStatus()
    await loadAttendance()
  } finally {
    actionLoading.value = false
  }
}

async function submitLeave() {
  actionLoading.value = true
  try {
    await axios.post('/api/v1/hr/me/leave-requests', leaveForm)
    showLeaveForm.value = false
    alert('Leave request submitted!')
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadStatus(), loadAttendance()])
  const typesRes = await axios.get('/api/v1/hr/leave-types')
  leaveTypes.value = typesRes.data.data ?? typesRes.data
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-box { width: 480px; padding: 24px; }
.num { text-align: right; }
.badge-blue   { background: var(--blue-50); color: var(--blue-600); }
.badge-orange { background: var(--orange-50); color: var(--orange-600); }
.badge-green  { background: var(--green-50); color: var(--green-600); }
.badge-gray   { background: var(--slate-100); color: var(--slate-600); }
</style>
