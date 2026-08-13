<template>
  <AppLayout title="Espaces de travail">
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Espaces de travail</h1>
        <p class="wh-page-subtitle">Organisez vos documents en espaces partagés</p>
      </div>
      <Button label="Nouvel espace" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <!-- Grid of workspace cards -->
    <div v-if="loading" class="workspace-grid">
      <div v-for="i in 6" :key="i" class="workspace-skeleton" />
    </div>
    <div v-else-if="workspaces.length === 0" class="wh-empty-state">
      <i class="pi pi-folder text-5xl text-gray-300 mb-4" />
      <p class="text-gray-500 dark:text-surface-400">Aucun espace de travail. Créez-en un pour commencer.</p>
    </div>
    <div v-else class="workspace-grid">
      <div
        v-for="ws in workspaces"
        :key="ws.id"
        class="workspace-card"
        :style="{ borderTop: `4px solid ${ws.color}` }"
        @click="openWorkspace(ws)"
      >
        <div class="workspace-icon" :style="{ background: ws.color + '20' }">
          {{ ws.icon }}
        </div>
        <div class="workspace-info">
          <p class="workspace-name">{{ ws.name }}</p>
          <p class="workspace-desc">{{ ws.description ?? 'Aucune description' }}</p>
        </div>
        <div class="workspace-meta">
          <span class="meta-pill">
            <i class="pi pi-users" style="font-size:11px" />
            {{ ws.members_count ?? 0 }} membre(s)
          </span>
          <Tag :value="ws.visibility" severity="secondary" style="font-size:10px" />
        </div>
      </div>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouvel espace de travail" :style="{ width: '480px' }" modal>
      <div class="space-y-4">
        <div class="flex gap-3">
          <div class="field w-16">
            <label class="field-label">Icône</label>
            <InputText v-model="form.icon" class="w-full text-center text-xl" maxlength="2" />
          </div>
          <div class="field flex-1">
            <label class="field-label">Nom <span class="required">*</span></label>
            <InputText v-model="form.name" class="w-full" placeholder="ex. Marketing Team" />
          </div>
        </div>
        <div class="field">
          <label class="field-label">Couleur</label>
          <div class="flex gap-2">
            <button
              v-for="c in colorPalette"
              :key="c"
              :style="{ background: c, width:'28px', height:'28px', borderRadius:'50%', border: form.color === c ? '2px solid #1e293b' : '2px solid transparent' }"
              @click="form.color = c"
            />
          </div>
        </div>
        <div class="field">
          <label class="field-label">Visibilité</label>
          <Dropdown v-model="form.visibility" :options="visibilityOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div class="field">
          <label class="field-label">Description</label>
          <Textarea v-model="form.description" rows="2" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Créer l'espace" :loading="saving" @click="createWorkspace" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dialog, Tag, InputText, Textarea, Dropdown } from 'primevue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const workspaces = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const form = ref({ name: '', description: '', icon: '📁', color: '#6366f1', visibility: 'team' })

const colorPalette = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#14b8a6']
const visibilityOptions = [
  { label: 'Équipe', value: 'team' },
  { label: 'Privé', value: 'private' },
  { label: 'Public', value: 'public' },
]

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/documents/workspaces')
    workspaces.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function createWorkspace() {
  saving.value = true
  try {
    const { data } = await axios.post('/api/v1/documents/workspaces', form.value)
    workspaces.value.unshift(data)
    showCreateDialog.value = false
    form.value = { name: '', description: '', icon: '📁', color: '#6366f1', visibility: 'team' }
  } finally {
    saving.value = false
  }
}

function openWorkspace(ws: any) {
  router.visit(`/documents/workspaces/${ws.id}`)
}

onMounted(load)
</script>

<style scoped>
.workspace-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}
.workspace-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  cursor: pointer;
  transition: box-shadow 0.15s, transform 0.1s;
  overflow: hidden;
}
.workspace-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); transform: translateY(-2px); }
.workspace-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px; }
.workspace-name { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 4px; }
.workspace-desc { font-size: 12px; color: #6b7280; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.workspace-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.meta-pill { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 99px; }
.workspace-skeleton { background: #f3f4f6; border-radius: 12px; height: 160px; animation: pulse 1.5s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>
