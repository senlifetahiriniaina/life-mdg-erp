<template>
  <AppLayout>
    <Head title="Training & Skills" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Training &amp; Skills</h1>
        <p class="wh-page-subtitle">Manage skills catalog, training courses and employee competencies</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreateCourse = true">
          <i class="pi pi-plus" style="font-size:13px" /> New Course
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

    <!-- Skills Catalog -->
    <div v-if="activeTab === 'skills'">
      <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
        <button class="btn btn-secondary" @click="showCreateSkill = true">
          <i class="pi pi-plus" style="font-size:12px" /> Add Skill
        </button>
      </div>
      <div class="wh-panel">
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Name</th>
              <th>Category</th>
              <th class="num">Employees with skill</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="skill in skills" :key="skill.id" class="wh-dt-row">
              <td style="font-weight:600;color:var(--fg-1)">{{ skill.name }}</td>
              <td style="color:var(--fg-2)">{{ skill.category ?? '—' }}</td>
              <td class="num">{{ skill.employee_skills_count ?? 0 }}</td>
            </tr>
            <tr v-if="!skills.length">
              <td colspan="3" style="text-align:center;padding:32px;color:var(--fg-3)">No skills defined</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Training Courses -->
    <div v-if="activeTab === 'courses'">
      <div class="wh-panel">
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Provider</th>
              <th class="num">Hours</th>
              <th>Status</th>
              <th class="num">Enrolled</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="course in courses.data" :key="course.id" class="wh-dt-row">
              <td style="font-weight:600;color:var(--fg-1)">{{ course.title }}</td>
              <td>
                <span :class="['wh-badge', typeBadge(course.type)]">{{ course.type }}</span>
              </td>
              <td style="color:var(--fg-2)">{{ course.provider ?? '—' }}</td>
              <td class="num">{{ course.duration_hours }}h</td>
              <td>
                <span :class="['wh-badge', statusBadge(course.status)]">
                  <span class="wh-badge-dot" />{{ course.status }}
                </span>
              </td>
              <td class="num">{{ course.enrollments_count ?? 0 }}</td>
              <td>
                <button class="btn btn-sm btn-secondary" @click="enroll(course)">
                  <i class="pi pi-user-plus" style="font-size:11px" /> Enroll
                </button>
              </td>
            </tr>
            <tr v-if="!courses.data?.length">
              <td colspan="7" style="text-align:center;padding:32px;color:var(--fg-3)">No courses</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Skills Matrix -->
    <div v-if="activeTab === 'matrix'">
      <div class="wh-panel" style="overflow-x:auto">
        <div v-if="matrixLoading" style="text-align:center;padding:32px;color:var(--fg-3)">
          <i class="pi pi-spin pi-spinner" />
        </div>
        <table v-else class="wh-dt" style="min-width:600px">
          <thead>
            <tr>
              <th>Employee</th>
              <th v-for="skill in skills.slice(0, 10)" :key="skill.id" style="text-align:center;font-size:11px;white-space:nowrap">{{ skill.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="emp in matrixEmployees" :key="emp.id" class="wh-dt-row">
              <td style="font-weight:500;color:var(--fg-1);white-space:nowrap">{{ emp.first_name }} {{ emp.last_name }}</td>
              <td v-for="skill in skills.slice(0, 10)" :key="skill.id" style="text-align:center">
                <span
                  v-if="getLevel(emp, skill.id)"
                  :class="['level-badge', levelClass(getLevel(emp, skill.id))]"
                >L{{ getLevel(emp, skill.id) }}</span>
                <span v-else style="color:var(--fg-4)">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Skill Modal -->
    <div v-if="showCreateSkill" class="modal-overlay" @click.self="showCreateSkill = false">
      <div class="wh-panel modal-box">
        <div style="font-size:16px;font-weight:700;margin-bottom:16px">Add Skill</div>
        <div style="display:flex;flex-direction:column;gap:12px">
          <div>
            <label class="wh-label">Name *</label>
            <input v-model="skillForm.name" class="wh-input" style="width:100%" />
          </div>
          <div>
            <label class="wh-label">Category</label>
            <input v-model="skillForm.category" class="wh-input" style="width:100%" />
          </div>
          <div>
            <label class="wh-label">Description</label>
            <textarea v-model="skillForm.description" class="wh-input" rows="2" style="width:100%;resize:vertical" />
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px">
          <button class="btn btn-primary" @click="createSkill" :disabled="saving">
            <i class="pi pi-check" style="font-size:12px" /> Save
          </button>
          <button class="btn btn-secondary" @click="showCreateSkill = false">{{ $t('common.cancel') }}</button>
        </div>
      </div>
    </div>

    <!-- Create Course Modal -->
    <div v-if="showCreateCourse" class="modal-overlay" @click.self="showCreateCourse = false">
      <div class="wh-panel modal-box">
        <div style="font-size:16px;font-weight:700;margin-bottom:16px">New Training Course</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div style="grid-column:span 2">
            <label class="wh-label">Title *</label>
            <input v-model="courseForm.title" class="wh-input" style="width:100%" />
          </div>
          <div>
            <label class="wh-label">Type</label>
            <select v-model="courseForm.type" class="wh-input" style="width:100%">
              <option value="internal">Internal</option>
              <option value="external">External</option>
              <option value="online">Online</option>
            </select>
          </div>
          <div>
            <label class="wh-label">Provider</label>
            <input v-model="courseForm.provider" class="wh-input" style="width:100%" />
          </div>
          <div>
            <label class="wh-label">Duration (hours)</label>
            <input v-model.number="courseForm.duration_hours" type="number" class="wh-input" style="width:100%" />
          </div>
          <div>
            <label class="wh-label">Cost</label>
            <input v-model.number="courseForm.cost" type="number" step="0.01" class="wh-input" style="width:100%" />
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px">
          <button class="btn btn-primary" @click="createCourse" :disabled="saving">
            <i class="pi pi-check" style="font-size:12px" /> Create
          </button>
          <button class="btn btn-secondary" @click="showCreateCourse = false">{{ $t('common.cancel') }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps<{ courses: any; skills: any[] }>()

const activeTab = ref('courses')
const showCreateSkill = ref(false)
const showCreateCourse = ref(false)
const saving = ref(false)
const matrixLoading = ref(false)
const matrixEmployees = ref<any[]>([])

const tabs = [
  { key: 'skills', label: 'Skills Catalog', icon: 'pi-bolt' },
  { key: 'courses', label: 'Courses', icon: 'pi-book' },
  { key: 'matrix', label: 'Skills Matrix', icon: 'pi-table' },
]

const skillForm = reactive({ name: '', category: '', description: '' })
const courseForm = reactive({ title: '', type: 'internal', provider: '', duration_hours: 8, cost: 0 })

function typeBadge(type: string) {
  return { internal: 'badge-blue', external: 'badge-orange', online: 'badge-green' }[type] ?? 'badge-gray'
}

function statusBadge(status: string) {
  return { draft: 'badge-gray', active: 'badge-green', archived: 'badge-red' }[status] ?? 'badge-gray'
}

function getLevel(emp: any, skillId: number): number {
  const s = emp.skills?.find((es: any) => es.skill_id === skillId)
  return s?.level ?? 0
}

function levelClass(level: number): string {
  return ['', 'level-1', 'level-2', 'level-3', 'level-4', 'level-5'][level] ?? ''
}

async function loadMatrix() {
  matrixLoading.value = true
  try {
    const res = await axios.get('/api/v1/hr/employees?per_page=20')
    const employees = res.data.data ?? []
    const withSkills = await Promise.all(
      employees.map(async (emp: any) => {
        const sr = await axios.get(`/api/v1/hr/employees/${emp.id}/skills`)
        return { ...emp, skills: sr.data }
      })
    )
    matrixEmployees.value = withSkills
  } finally {
    matrixLoading.value = false
  }
}

watch(activeTab, (val) => {
  if (val === 'matrix' && matrixEmployees.value.length === 0) {
    loadMatrix()
  }
})

async function createSkill() {
  saving.value = true
  try {
    await axios.post('/api/v1/hr/skills', skillForm)
    showCreateSkill.value = false
    window.location.reload()
  } finally {
    saving.value = false
  }
}

async function createCourse() {
  saving.value = true
  try {
    await axios.post('/api/v1/hr/training-courses', courseForm)
    showCreateCourse.value = false
    window.location.reload()
  } finally {
    saving.value = false
  }
}

async function enroll(course: any) {
  const employeeId = prompt('Enter employee ID to enroll:')
  if (!employeeId) return
  await axios.post(`/api/v1/hr/training-courses/${course.id}/enroll`, {
    employee_id: parseInt(employeeId),
  })
  alert('Employee enrolled!')
}
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
.modal-box { width: 560px; max-height: 90vh; overflow-y: auto; padding: 24px; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.num { text-align: right; }
.badge-blue   { background: var(--blue-50); color: var(--blue-600); }
.badge-orange { background: var(--orange-50); color: var(--orange-600); }
.badge-green  { background: var(--green-50); color: var(--green-600); }
.badge-red    { background: var(--red-50); color: var(--red-600); }
.badge-gray   { background: var(--slate-100); color: var(--slate-600); }
.level-badge  { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.level-1 { background: var(--red-100); color: var(--red-700); }
.level-2 { background: var(--orange-100); color: var(--orange-700); }
.level-3 { background: var(--yellow-100); color: var(--yellow-700); }
.level-4 { background: var(--green-100); color: var(--green-700); }
.level-5 { background: var(--blue-100); color: var(--blue-700); }
</style>
