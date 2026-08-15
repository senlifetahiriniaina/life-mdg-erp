<template>
  <AppLayout>
    <Head title="Base de connaissances" />
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Helpdesk · Base de connaissances</h1>
        <p class="wh-page-subtitle">{{ pagination.total ?? 0 }} article{{ pagination.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="showCategoryModal = true"><i class="pi pi-folder-plus" style="font-size:13px" /> Catégorie</button>
        <button class="btn btn-primary" @click="openCreate"><i class="pi pi-plus" style="font-size:13px" /> Nouvel article</button>
      </div>
    </div>
    <div class="wh-panel" style="padding:12px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
      <div style="position:relative;flex:1;min-width:200px;max-width:380px">
        <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
        <input v-model="filters.search" placeholder="Rechercher dans la base de connaissances…" class="wh-filter-input" @input="debounceLoad" />
      </div>
      <select v-model="filters.category_id" class="wh-input" style="width:180px" @change="load">
        <option value="">Toutes les catégories</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
      </select>
      <select v-model="filters.status" class="wh-input" style="width:140px" @change="load">
        <option value="">Tous les statuts</option>
        <option value="published">Publié</option>
        <option value="draft">Brouillon</option>
        <option value="archived">Archivé</option>
      </select>
    </div>
    <div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start">
      <div class="wh-panel" style="padding:8px 0">
        <div style="padding:8px 14px;font-size:12px;font-weight:600;color:var(--fg-3);text-transform:uppercase;letter-spacing:.05em">Catégories</div>
        <div v-for="cat in categories" :key="cat.id" class="kb-cat-item" :class="{ 'kb-cat-active': filters.category_id === cat.id }" @click="toggleCategory(cat.id)">
          <i :class="['pi', cat.icon || 'pi-folder', 'kb-cat-icon']" />
          {{ cat.name }}
          <span class="kb-cat-count">{{ cat.articles?.length ?? 0 }}</span>
        </div>
        <div v-if="categories.length === 0" style="padding:12px 14px;color:var(--fg-4);font-size:12px">Aucune catégorie</div>
      </div>
      <div>
        <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)"><i class="pi pi-spin pi-spinner" style="font-size:20px" /></div>
        <div v-else-if="articles.length === 0" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)"><i class="pi pi-book" style="font-size:32px;display:block;margin-bottom:12px" />Aucun article trouvé.</div>
        <div v-else style="display:flex;flex-direction:column;gap:8px">
          <div v-for="article in articles" :key="article.id" class="wh-panel kb-article-card" @click="openArticle(article)">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="flex:1">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                  <span :class="['badge', statusClass(article.status)]">{{ article.status }}</span>
                  <span v-if="article.category" style="font-size:12px;color:var(--fg-3)"><i class="pi pi-folder" style="font-size:10px" /> {{ article.category.name }}</span>
                </div>
                <div class="kb-article-title">{{ article.title }}</div>
                <div v-if="article.excerpt" class="kb-article-excerpt">{{ article.excerpt }}</div>
                <div v-if="article.tags?.length" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px">
                  <span v-for="tag in article.tags" :key="tag" class="kb-tag">{{ tag }}</span>
                </div>
              </div>
              <div style="text-align:right;flex-shrink:0;font-size:12px;color:var(--fg-3)">
                <div><i class="pi pi-eye" style="font-size:10px" /> {{ article.view_count }} vues</div>
                <div style="margin-top:2px"><i class="pi pi-thumbs-up" style="font-size:10px" /> {{ article.helpful_count }}</div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="(pagination.last_page ?? 0) > 1" style="display:flex;justify-content:center;padding:12px 0">
          <Paginator :rows="Math.ceil((pagination.total ?? 1) / Math.max(pagination.last_page ?? 1, 1))" :total-records="pagination.total ?? 0" :first="((pagination.current_page ?? 1) - 1) * Math.ceil((pagination.total ?? 1) / Math.max(pagination.last_page ?? 1, 1))" @page="(e) => goPage(e.page + 1)" />
        </div>
      </div>
    </div>
    <div v-if="articleModal" class="wh-modal-backdrop" @click.self="articleModal = null">
      <div class="wh-modal" style="max-width:720px;max-height:85vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <h2 style="font-size:18px;font-weight:700;color:var(--fg-1)">{{ articleModal.title }}</h2>
          <button class="btn btn-secondary" @click="articleModal = null"><i class="pi pi-times" style="font-size:12px" /></button>
        </div>
        <div class="kb-content" v-html="sanitized(articleModal.content)" />
        <div style="display:flex;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid var(--border-subtle)">
          <button class="btn btn-secondary" @click="sendFeedback(articleModal, true)"><i class="pi pi-thumbs-up" style="font-size:12px" /> Utile ({{ articleModal.helpful_count }})</button>
          <button class="btn btn-secondary" @click="sendFeedback(articleModal, false)"><i class="pi pi-thumbs-down" style="font-size:12px" /> Pas utile ({{ articleModal.not_helpful_count }})</button>
        </div>
      </div>
    </div>
    <div v-if="showCreateModal" class="wh-modal-backdrop" @click.self="showCreateModal = false">
      <div class="wh-modal" style="max-width:640px;max-height:85vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <h2 style="font-size:16px;font-weight:700">{{ editingArticle ? 'Modifier l\'article' : 'Nouvel article' }}</h2>
          <button class="btn btn-secondary" @click="showCreateModal = false"><i class="pi pi-times" style="font-size:12px" /></button>
        </div>
        <form @submit.prevent="saveArticle" style="display:flex;flex-direction:column;gap:12px">
          <div><label class="form-label">Titre *</label><input v-model="articleForm.title" class="wh-input" style="width:100%" required /></div>
          <div><label class="form-label">Catégorie *</label><select v-model="articleForm.category_id" class="wh-input" style="width:100%" required><option value="">Choisir une catégorie</option><option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option></select></div>
          <div><label class="form-label">Extrait</label><input v-model="articleForm.excerpt" class="wh-input" style="width:100%" /></div>
          <div><label class="form-label">Contenu *</label><textarea v-model="articleForm.content" class="wh-input" style="width:100%;min-height:200px;resize:vertical" required /></div>
          <div><label class="form-label">Statut</label><select v-model="articleForm.status" class="wh-input" style="width:180px"><option value="draft">Brouillon</option><option value="published">Publié</option><option value="archived">Archivé</option></select></div>
          <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:8px;border-top:1px solid var(--border-subtle)">
            <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="savingArticle"><i v-if="savingArticle" class="pi pi-spin pi-spinner" style="font-size:12px" /> Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Paginator from 'primevue/paginator'
import axios from 'axios'
import DOMPurify from 'dompurify'

interface Category {
  id: number
  name: string
  icon?: string | null
  articles?: unknown[]
}

interface Article {
  id: number
  title: string
  status: string
  category?: Category | null
  excerpt?: string | null
  content: string
  tags?: string[]
  view_count: number
  helpful_count: number
  not_helpful_count: number
}

interface Pagination {
  total: number
  current_page: number
  last_page: number
}

interface ArticleForm {
  title: string
  category_id: number | string
  excerpt: string
  content: string
  status: string
}

const sanitized = (html: string) => DOMPurify.sanitize(html ?? '', {
  ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'em', 'strong', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'code', 'pre', 'a'],
  ALLOWED_ATTR: ['href', 'target', 'rel'],
})

const loading = ref(false)
const articles = ref<Article[]>([])
const categories = ref<Category[]>([])
const pagination = ref<Partial<Pagination>>({})
const articleModal = ref<Article | null>(null)
const filters = reactive({ search: '', category_id: '' as number | string, status: 'published', page: 1 })

let debounceTimer: ReturnType<typeof setTimeout> | null = null
function debounceLoad() { if (debounceTimer) clearTimeout(debounceTimer); debounceTimer = setTimeout(load, 350) }
function toggleCategory(id: number) { filters.category_id = filters.category_id === id ? '' : id; load() }

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: filters.page }
    if (filters.search) params.search = filters.search
    if (filters.category_id) params.category_id = filters.category_id
    if (filters.status) params.status = filters.status
    const { data } = await axios.get('/api/v1/helpdesk/kb/articles', { params })
    articles.value = data.data
    pagination.value = { total: data.total, current_page: data.current_page, last_page: data.last_page }
  } finally { loading.value = false }
}

async function loadCategories() { const { data } = await axios.get('/api/v1/helpdesk/kb/categories'); categories.value = data }
async function openArticle(article: Article) { const { data } = await axios.get(`/api/v1/helpdesk/kb/articles/${article.id}`); articleModal.value = data }
async function sendFeedback(article: Article, helpful: boolean) { const { data } = await axios.post(`/api/v1/helpdesk/kb/articles/${article.id}/feedback`, { helpful }); article.helpful_count = data.helpful_count; article.not_helpful_count = data.not_helpful_count }
function goPage(p: number) { filters.page = p; load() }

// showCategoryModal was referenced in the template (the "Catégorie" button)
// but never declared anywhere in this script — a dead button, not just a
// type gap; clicking it would warn "property was accessed during render
// but is not defined" and do nothing.
const showCategoryModal = ref(false)
const showCreateModal = ref(false)
const editingArticle = ref<Article | null>(null)
const savingArticle = ref(false)
const articleForm = ref<ArticleForm>({ title: '', category_id: '', excerpt: '', content: '', status: 'draft' })

function openCreate() { editingArticle.value = null; articleForm.value = { title: '', category_id: '', excerpt: '', content: '', status: 'draft' }; showCreateModal.value = true }

async function saveArticle() {
  savingArticle.value = true
  try {
    const payload = { ...articleForm.value }
    if (editingArticle.value) { await axios.put(`/api/v1/helpdesk/kb/portal/articles/${editingArticle.value.id}`, payload) } else { await axios.post('/api/v1/helpdesk/kb/portal/articles', payload) }
    showCreateModal.value = false; await load()
  } finally { savingArticle.value = false }
}

function statusClass(s: string) { return ({ published: 'badge-green', draft: 'badge-gray', archived: 'badge-orange' } as Record<string, string>)[s] || 'badge-gray' }

onMounted(async () => { await loadCategories(); await load() })
</script>

<style scoped>
.kb-cat-item { display:flex;align-items:center;gap:8px;padding:7px 14px;cursor:pointer;font-size:13px;color:var(--fg-2);transition:background .1s; }
.kb-cat-item:hover { background:var(--bg-sunken); }
.kb-cat-active { background:var(--halo-50);color:var(--halo-700);font-weight:500; }
.kb-cat-icon { font-size:13px;color:var(--fg-3); }
.kb-cat-count { margin-left:auto;font-size:11px;color:var(--fg-4);background:var(--bg-sunken);padding:1px 6px;border-radius:10px; }
.kb-article-card { padding:14px 16px;cursor:pointer;transition:box-shadow .15s; }
.kb-article-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
.kb-article-title { font-weight:600;font-size:14px;color:var(--fg-1);margin-bottom:4px; }
.kb-article-excerpt { font-size:13px;color:var(--fg-3);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
.kb-tag { font-size:10px;background:var(--bg-sunken);color:var(--fg-2);padding:1px 6px;border-radius:4px; }
.kb-content { font-size:14px;line-height:1.7;color:var(--fg-1); }
.badge-green { background:var(--green-50); color:var(--green-600); padding:2px 7px; border-radius:4px; font-size:11px; }
.badge-gray { background:var(--slate-100); color:var(--slate-600); padding:2px 7px; border-radius:4px; font-size:11px; }
.badge-orange { background:var(--yellow-50); color:var(--yellow-600); padding:2px 7px; border-radius:4px; font-size:11px; }
.form-label { display:block; font-size:12px; font-weight:500; color:var(--fg-2); margin-bottom:4px; }
.wh-modal-backdrop { position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:24px; }
.wh-modal { background:var(--bg-canvas);border-radius:var(--r-lg);padding:24px;width:100%;box-shadow:0 8px 40px rgba(0,0,0,0.2); }
.wh-filter-input { width:100%; padding:7px 12px 7px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-size:13px; color:var(--fg-1); outline:none; }
.wh-input { padding:7px 10px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; outline:none; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.btn { font-weight:500; font-size:13px; padding:7px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-primary:disabled { opacity:.6; cursor:not-allowed; }
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-size:22px; font-weight:700; color:var(--fg-1); }
.wh-page-subtitle { margin:3px 0 0; font-size:13px; color:var(--fg-3); }
.page-actions { display:flex; gap:8px; }
</style>
