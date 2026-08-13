<template>
  <AppLayout>
    <Head title="Learning Management" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Learning Management</h1>
        <p class="wh-page-subtitle">Course catalogue, enrolments and learning paths</p>
      </div>
      <div class="page-actions">
        <Button label="Add Course" icon="pi pi-plus" @click="showCreateCourse = true" />
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap">
      <Dropdown
        v-model="filterCategory"
        :options="categories"
        placeholder="All Categories"
        showClear
        style="min-width:180px"
        @change="fetchCourses"
      />
      <Dropdown
        v-model="filterLevel"
        :options="levelOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="All Levels"
        showClear
        style="min-width:150px"
        @change="fetchCourses"
      />
    </div>

    <!-- Course Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:28px">
      <div
        v-for="course in courses"
        :key="course.id"
        class="wh-panel"
        style="cursor:pointer"
        @click="openCourse(course)"
      >
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
          <Tag :value="course.level" :severity="levelSeverity(course.level)" />
          <span style="font-size:20px">{{ formatIcon(course.format) }}</span>
        </div>
        <h3 style="font-weight:600;font-size:15px;margin-bottom:4px">{{ course.title }}</h3>
        <p style="color:var(--fg-2);font-size:13px;margin-bottom:10px">{{ course.category }} · {{ course.duration_hours }}h</p>
        <div v-if="enrollmentMap[course.id]" style="margin-top:8px">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
            <span>Progress</span>
            <span>{{ enrollmentMap[course.id].progress_pct }}%</span>
          </div>
          <div style="background:var(--surface-d);border-radius:4px;height:6px;overflow:hidden">
            <div :style="`width:${enrollmentMap[course.id].progress_pct}%;background:var(--primary-color);height:100%`" />
          </div>
          <Tag
            :value="enrollmentMap[course.id].status"
            :severity="enrollStatusSeverity(enrollmentMap[course.id].status)"
            class="mt-1"
            style="font-size:11px"
          />
        </div>
        <Button
          v-else
          label="Enrol"
          size="small"
          severity="secondary"
          class="mt-2"
          @click.stop="enrollInCourse(course)"
        />
        <Tag v-if="course.mandatory" value="Mandatory" severity="danger" style="font-size:10px;margin-top:6px" />
      </div>
    </div>

    <!-- Recommended Paths -->
    <div class="wh-panel" style="margin-bottom:24px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h2 style="font-size:16px;font-weight:600">Recommended Learning Paths</h2>
        <Button
          label="What should I learn?"
          icon="pi pi-sparkles"
          severity="secondary"
          size="small"
          @click="suggestPath"
        />
      </div>
      <div v-if="learningPaths.length" style="display:flex;flex-wrap:wrap;gap:12px">
        <div
          v-for="path in learningPaths"
          :key="path.id"
          class="wh-panel"
          style="min-width:220px;flex:1;background:var(--surface-b)"
        >
          <p style="font-weight:600;font-size:14px">{{ path.name }}</p>
          <p v-if="path.role_target" style="font-size:12px;color:var(--fg-2)">For: {{ path.role_target }}</p>
          <p v-if="path.description" style="font-size:12px;margin-top:4px">{{ path.description }}</p>
        </div>
      </div>
      <p v-else style="color:var(--fg-2);font-size:13px">No learning paths configured yet.</p>
    </div>

    <!-- Create Course Dialog -->
    <Dialog v-model:visible="showCreateCourse" header="Add Course" modal style="width:520px">
      <div class="flex flex-col gap-3 mt-2">
        <div>
          <label class="block mb-1 text-sm font-medium">Title</label>
          <InputText v-model="courseForm.title" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Description</label>
          <Textarea v-model="courseForm.description" rows="2" class="w-full" />
        </div>
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Category</label>
            <InputText v-model="courseForm.category" class="w-full" />
          </div>
          <div style="width:100px">
            <label class="block mb-1 text-sm font-medium">Hours</label>
            <InputNumber v-model="courseForm.duration_hours" :min="0.5" :step="0.5" class="w-full" />
          </div>
        </div>
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Level</label>
            <Dropdown v-model="courseForm.level" :options="levelOptions" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Format</label>
            <Dropdown v-model="courseForm.format" :options="formatOptions" optionLabel="label" optionValue="value" class="w-full" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showCreateCourse = false" />
        <Button label="Save" @click="createCourse" />
      </template>
    </Dialog>

    <!-- AI Path Suggestion Dialog -->
    <Dialog v-model:visible="showAiPath" header="AI Learning Suggestion" modal style="width:560px">
      <div v-if="loadingAi" class="text-center py-6">
        <i class="pi pi-spin pi-spinner" style="font-size:2rem" />
        <p class="mt-2 text-gray-500 dark:text-surface-400">Analysing your profile...</p>
      </div>
      <div v-else-if="aiPathSuggestion" class="whitespace-pre-line text-sm mt-2">
        {{ aiPathSuggestion }}
      </div>
      <template #footer>
        <Button label="Close" severity="secondary" @click="showAiPath = false" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown, InputNumber } from 'primevue'
import axios from 'axios'

const page = usePage()

const courses        = ref<any[]>([])
const enrollments    = ref<any[]>([])
const learningPaths  = ref<any[]>([])
const filterCategory = ref<string | null>(null)
const filterLevel    = ref<string | null>(null)
const showCreateCourse  = ref(false)
const showAiPath        = ref(false)
const loadingAi         = ref(false)
const aiPathSuggestion  = ref('')

const courseForm = ref({
  title: '', description: '', category: '', duration_hours: 1.0, level: 'beginner', format: 'video',
})

const categories = ['Leadership', 'Technical', 'Compliance', 'Soft Skills', 'Safety']
const levelOptions  = [
  { label: 'Beginner',      value: 'beginner' },
  { label: 'Intermediate',  value: 'intermediate' },
  { label: 'Advanced',      value: 'advanced' },
]
const formatOptions = [
  { label: 'Video',    value: 'video' },
  { label: 'Article',  value: 'article' },
  { label: 'Quiz',     value: 'quiz' },
  { label: 'Workshop', value: 'workshop' },
]

const enrollmentMap = computed(() => {
  const m: Record<number, any> = {}
  for (const e of enrollments.value) m[e.course_id] = e
  return m
})

const levelSeverity = (l: string) => ({ beginner: 'success', intermediate: 'warning', advanced: 'danger' }[l] ?? 'info')
const enrollStatusSeverity = (s: string) => ({ enrolled: 'secondary', in_progress: 'warning', completed: 'success' }[s] ?? 'secondary')

const formatIcon = (f: string) => ({ video: '🎬', article: '📄', quiz: '📝', workshop: '🛠️' }[f] ?? '📚')

const fetchCourses = async () => {
  const params: Record<string, string> = {}
  if (filterCategory.value) params.category = filterCategory.value
  if (filterLevel.value) params.level = filterLevel.value
  const res = await axios.get('/api/v1/hr/courses', { params })
  courses.value = res.data.data ?? res.data
}

const fetchEnrollments = async () => {
  const res = await axios.get('/api/v1/hr/enrollments')
  enrollments.value = res.data
}

const fetchPaths = async () => {
  const res = await axios.get('/api/v1/hr/learning-paths')
  learningPaths.value = res.data
}

const openCourse = (course: any) => {
  // future: navigate to course detail
}

const enrollInCourse = async (course: any) => {
  const user = (page.props as any).auth?.user
  if (!user?.employee_id) return
  await axios.post(`/api/v1/hr/courses/${course.id}/enroll`, { employee_id: user.employee_id })
  fetchEnrollments()
}

const createCourse = async () => {
  await axios.post('/api/v1/hr/courses', courseForm.value)
  showCreateCourse.value = false
  courseForm.value = { title: '', description: '', category: '', duration_hours: 1.0, level: 'beginner', format: 'video' }
  fetchCourses()
}

const suggestPath = async () => {
  const user = (page.props as any).auth?.user
  showAiPath.value = true
  loadingAi.value = true
  aiPathSuggestion.value = ''
  try {
    const res = await axios.post('/api/v1/hr/learning-paths/suggest', { employee_id: user?.employee_id ?? 1 })
    aiPathSuggestion.value = res.data.ai_suggestion ?? ''
    if (res.data.suggested_paths?.length) {
      learningPaths.value = res.data.suggested_paths
    }
  } finally {
    loadingAi.value = false
  }
}

onMounted(() => {
  fetchCourses()
  fetchEnrollments()
  fetchPaths()
})
</script>
