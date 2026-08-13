<template>
  <AppLayout>
    <Head title="Recruitment" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Recruitment</h1>
        <p class="wh-page-subtitle">Manage job postings and applications</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreatePosting = true">
          <i class="pi pi-plus" style="font-size:13px" /> New Job Posting
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:16px">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="['btn', activeTab === tab.key ? 'btn-primary' : 'btn-secondary']"
        @click="activeTab = tab.key"
      >
        <i :class="['pi', tab.icon]" style="font-size:12px" /> {{ tab.label }}
      </button>
    </div>

    <!-- Job Postings Tab -->
    <div v-if="activeTab === 'postings'">
      <div class="wh-panel">
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Title</th>
              <th>Department</th>
              <th>Type</th>
              <th>Status</th>
              <th class="num">Applicants</th>
              <th>Published</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="posting in postings.data" :key="posting.id" class="wh-dt-row">
              <td style="font-weight:600;color:var(--fg-1)">{{ posting.title }}</td>
              <td style="color:var(--fg-2)">{{ posting.department?.name ?? '—' }}</td>
              <td>
                <span :class="['wh-badge', typeBadge(posting.type)]">{{ posting.type.replace('_', ' ') }}</span>
              </td>
              <td>
                <span :class="['wh-badge', statusBadge(posting.status)]">
                  <span class="wh-badge-dot" />{{ posting.status }}
                </span>
              </td>
              <td class="num">{{ posting.applications_count ?? 0 }}</td>
              <td style="color:var(--fg-3)">{{ formatDate(posting.published_at) }}</td>
              <td>
                <div style="display:flex;gap:6px">
                  <button
                    v-if="posting.status === 'draft'"
                    class="btn btn-sm btn-secondary"
                    @click="publish(posting)"
                  >Publish</button>
                  <button class="btn btn-sm btn-secondary" @click="viewApplications(posting)">
                    Applications
                  </button>
                  <button class="btn btn-sm btn-danger" @click="deletePosting(posting)">
                    <i class="pi pi-trash" style="font-size:11px" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!postings.data?.length">
              <td colspan="7" style="text-align:center;padding:32px;color:var(--fg-3)">No job postings found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Applications Tab -->
    <div v-if="activeTab === 'applications'">
      <div class="filter-bar" style="margin-bottom:12px">
        <select v-model="appStatusFilter" class="wh-input" @change="loadApplications">
          <option value="">All Statuses</option>
          <option v-for="s in appStatuses" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <div class="wh-panel">
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Applicant</th>
              <th>Job</th>
              <th>Status</th>
              <th>Rating</th>
              <th>Applied</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="app in applications" :key="app.id" class="wh-dt-row">
              <td>
                <div style="font-weight:600;color:var(--fg-1)">{{ app.applicant_name }}</div>
                <div style="font-size:12px;color:var(--fg-3)">{{ app.applicant_email }}</div>
              </td>
              <td style="color:var(--fg-2)">{{ app.posting?.title ?? '—' }}</td>
              <td>
                <select
                  :value="app.status"
                  class="wh-input"
                  style="padding:2px 4px;font-size:12px"
                  @change="updateAppStatus(app, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="s in appStatuses" :key="s" :value="s">{{ s }}</option>
                </select>
              </td>
              <td>
                <div style="display:flex;gap:2px">
                  <i
                    v-for="n in 5"
                    :key="n"
                    :class="['pi', n <= (app.score ?? 0) / 20 ? 'pi-star-fill' : 'pi-star']"
                    style="font-size:12px;color:var(--yellow-400)"
                  />
                </div>
              </td>
              <td style="color:var(--fg-3);font-size:12px">{{ formatDate(app.created_at) }}</td>
              <td>
                <button class="btn btn-sm btn-secondary" @click="scheduleInterview(app)">
                  <i class="pi pi-calendar" style="font-size:11px" /> Interview
                </button>
              </td>
            </tr>
            <tr v-if="!applications.length">
              <td colspan="6" style="text-align:center;padding:32px;color:var(--fg-3)">No applications</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Job Posting Modal -->
    <div v-if="showCreatePosting" class="modal-overlay" @click.self="showCreatePosting = false">
      <div class="wh-panel modal-box">
        <div style="font-size:16px;font-weight:700;margin-bottom:16px">New Job Posting</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div style="grid-column:span 2">
            <label class="wh-label">Title *</label>
            <input v-model="form.title" class="wh-input" style="width:100%" />
          </div>
          <div style="grid-column:span 2">
            <label class="wh-label">Description *</label>
            <textarea v-model="form.description" class="wh-input" rows="4" style="width:100%;resize:vertical" />
          </div>
          <div>
            <label class="wh-label">Type</label>
            <select v-model="form.type" class="wh-input" style="width:100%">
              <option value="full_time">Full Time</option>
              <option value="part_time">Part Time</option>
              <option value="contract">Contract</option>
              <option value="internship">Internship</option>
            </select>
          </div>
          <div>
            <label class="wh-label">Work Mode</label>
            <select v-model="form.work_mode" class="wh-input" style="width:100%">
              <option value="onsite">Onsite</option>
              <option value="remote">Remote</option>
              <option value="hybrid">Hybrid</option>
            </select>
          </div>
          <div>
            <label class="wh-label">Location</label>
            <input v-model="form.location" class="wh-input" style="width:100%" />
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px">
          <button class="btn btn-primary" @click="createPosting" :disabled="saving">
            <i class="pi pi-check" style="font-size:12px" /> Create
          </button>
          <button class="btn btn-secondary" @click="showCreatePosting = false">{{ $t('common.cancel') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineProps<{ postings: any }>()

const activeTab = ref('postings')
const showCreatePosting = ref(false)
const saving = ref(false)
const applications = ref<any[]>([])
const appStatusFilter = ref('')

const tabs = [
  { key: 'postings', label: 'Job Postings', icon: 'pi-briefcase' },
  { key: 'applications', label: 'Applications', icon: 'pi-users' },
]

const appStatuses = ['received', 'screening', 'interview', 'offer', 'hired', 'rejected']

const form = reactive({
  title: '',
  description: '',
  type: 'full_time',
  work_mode: 'onsite',
  location: '',
})

function typeBadge(type: string) {
  const map: Record<string, string> = {
    full_time: 'badge-blue',
    part_time: 'badge-cyan',
    contract: 'badge-orange',
    internship: 'badge-purple',
  }
  return map[type] ?? 'badge-gray'
}

function statusBadge(status: string) {
  const map: Record<string, string> = {
    draft: 'badge-gray',
    published: 'badge-green',
    closed: 'badge-red',
    cancelled: 'badge-red',
  }
  return map[status] ?? 'badge-gray'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Intl.DateTimeFormat('en-GB').format(new Date(d))
}

async function publish(posting: any) {
  await axios.patch(`/api/v1/hr/jobs/${posting.id}`, { status: 'published' })
  window.location.reload()
}

async function deletePosting(posting: any) {
  if (!confirm('Delete this job posting?')) return
  await axios.delete(`/api/v1/hr/jobs/${posting.id}`)
  window.location.reload()
}

function viewApplications(posting: any) {
  activeTab.value = 'applications'
  loadApplicationsByPosting(posting.id)
}

async function loadApplicationsByPosting(postingId: number) {
  const res = await axios.get(`/api/v1/hr/jobs/${postingId}/applications`)
  applications.value = res.data.data ?? res.data
}

async function loadApplications() {
  const params: Record<string, string> = {}
  if (appStatusFilter.value) params.status = appStatusFilter.value
  const res = await axios.get('/api/v1/hr/applications', { params })
  applications.value = res.data.data ?? res.data
}

async function updateAppStatus(app: any, status: string) {
  await axios.patch(`/api/v1/hr/applications/${app.id}/status`, { status })
  app.status = status
}

async function scheduleInterview(app: any) {
  const interviewerId = prompt('Enter interviewer user ID:')
  const scheduledAt = prompt('Scheduled at (ISO datetime):')
  if (!interviewerId || !scheduledAt) return
  await axios.post(`/api/v1/hr/applications/${app.id}/interviews`, {
    interviewer_id: parseInt(interviewerId),
    scheduled_at: scheduledAt,
    type: 'video',
  })
  alert('Interview scheduled!')
}

async function createPosting() {
  saving.value = true
  try {
    await axios.post('/api/v1/hr/jobs', form)
    showCreatePosting.value = false
    window.location.reload()
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadApplications()
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
.modal-box {
  width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
}
.btn-sm { padding: 4px 10px; font-size: 12px; }
.btn-danger { background: var(--red-500); color: #fff; border: none; }
.num { text-align: right; }
.badge-blue   { background: var(--blue-50); color: var(--blue-600); }
.badge-cyan   { background: var(--cyan-50); color: var(--cyan-600); }
.badge-orange { background: var(--orange-50); color: var(--orange-600); }
.badge-purple { background: var(--purple-50); color: var(--purple-600); }
.badge-green  { background: var(--green-50); color: var(--green-600); }
.badge-red    { background: var(--red-50); color: var(--red-600); }
.badge-gray   { background: var(--slate-100); color: var(--slate-600); }
</style>
