<template>
  <AppLayout title="Forum communautaire">
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Forum communautaire</h1>
        <p class="wh-page-subtitle">Posez vos questions, partagez vos solutions</p>
      </div>
      <Button label="Nouvelle question" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <DataTable :value="posts" :loading="loading" class="wh-table" stripedRows>
      <Column field="title" header="Question">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <a :href="`/helpdesk/forum/${data.id}`" class="font-medium text-blue-600 hover:underline">{{ data.title }}</a>
            <Tag v-if="data.status === 'answered'" value="Résolu" severity="success" style="font-size:10px" />
          </div>
        </template>
      </Column>
      <Column field="category" header="Catégorie">
        <template #body="{ data }">
          <Tag :value="data.category" severity="secondary" />
        </template>
      </Column>
      <Column field="votes" header="Votes">
        <template #body="{ data }">
          <span class="font-semibold">▲ {{ data.votes }}</span>
        </template>
      </Column>
      <Column field="replies_count" header="Réponses">
        <template #body="{ data }">
          <Badge :value="data.replies_count ?? 0" severity="secondary" />
        </template>
      </Column>
      <Column field="author.name" header="Auteur" />
      <Column header="">
        <template #body="{ data }">
          <Button icon="pi pi-eye" size="small" text @click="openPost(data)" />
        </template>
      </Column>
    </DataTable>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouvelle question" :style="{ width: '560px' }" modal>
      <div class="space-y-4">
        <div class="field">
          <label class="field-label">Titre <span class="required">*</span></label>
          <InputText v-model="form.title" class="w-full" placeholder="Décrivez votre problème en une phrase..." />
        </div>
        <div class="field">
          <label class="field-label">Catégorie</label>
          <Dropdown v-model="form.category" :options="categories" class="w-full" />
        </div>
        <div class="field">
          <label class="field-label">Détails <span class="required">*</span></label>
          <Textarea v-model="form.content" rows="5" class="w-full" placeholder="Expliquez votre question en détail..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Publier" :loading="saving" @click="createPost" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown, Badge } from 'primevue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const posts = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const showCreateDialog = ref(false)
const form = ref({ title: '', content: '', category: 'general' })
const categories = ['general', 'billing', 'technical', 'feature-request']

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/helpdesk/forum/posts')
    posts.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function createPost() {
  saving.value = true
  try {
    const { data } = await axios.post('/api/v1/helpdesk/forum/posts', form.value)
    posts.value.unshift(data)
    showCreateDialog.value = false
    form.value = { title: '', content: '', category: 'general' }
  } finally {
    saving.value = false
  }
}

function openPost(post: any) {
  router.visit(`/helpdesk/forum/${post.id}`)
}

onMounted(load)
</script>
