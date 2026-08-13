<template>
  <AppLayout>
    <Head title="Templates Email" />
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Templates Email</h1>
        <p class="wh-page-subtitle">{{ templates.total }} template{{ templates.total !== 1 ? 's' : '' }} disponible{{ templates.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions"><a href="/email/builder" class="btn btn-primary"><i class="pi pi-plus" style="font-size:13px" /> Nouveau template</a></div>
    </div>
    <div class="wh-panel filter-bar">
      <div style="position:relative;flex:1;min-width:200px;max-width:320px">
        <i class="pi pi-search search-icon" />
        <input v-model="search" class="wh-filter-input" placeholder="Rechercher un template…" @input="onSearchInput" />
      </div>
      <select v-model="categoryFilter" class="wh-select-sm" @change="applyFilters">
        <option value="">Toutes les catégories</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
      <div class="view-toggle">
        <button :class="['vbtn', { active: viewMode === 'grid' }]" aria-label="Afficher en grille" @click="viewMode = 'grid'"><i class="pi pi-th-large" style="font-size:13px" /></button>
        <button :class="['vbtn', { active: viewMode === 'list' }]" aria-label="Afficher en liste" @click="viewMode = 'list'"><i class="pi pi-list" style="font-size:13px" /></button>
      </div>
    </div>
    <div v-if="viewMode === 'grid'" class="templates-grid">
      <div v-for="t in templates.data" :key="t.id" class="template-card">
        <div class="template-thumb">
          <div v-if="t.thumbnail_url"><img :src="t.thumbnail_url" :alt="t.name" style="width:100%;object-fit:cover" /></div>
          <div v-else class="thumb-placeholder"><i class="pi pi-file-edit" style="font-size:32px;color:var(--fg-4)" /></div>
          <div class="thumb-overlay">
            <a :href="`/email/builder?template=${t.id}`" class="overlay-btn"><i class="pi pi-pencil" style="font-size:13px" /> Éditer</a>
            <button class="overlay-btn" @click="previewTemplate(t)"><i class="pi pi-eye" style="font-size:13px" /> Aperçu</button>
          </div>
        </div>
        <div class="template-info">
          <p class="template-name">{{ t.name }}</p>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
            <span class="category-badge">{{ t.category ?? 'général' }}</span>
            <span style="font-size:11px;color:var(--fg-4)">{{ formatDate(t.updated_at) }}</span>
          </div>
          <div class="card-actions">
            <a :href="`/email/builder?template=${t.id}`" class="card-btn card-btn-primary"><i class="pi pi-pencil" style="font-size:11px" /> Éditer</a>
            <button class="card-btn" @click="duplicateTemplate(t)"><i class="pi pi-copy" style="font-size:11px" /> Dupliquer</button>
            <button class="card-btn card-btn-danger" @click="confirmDelete(t)"><i class="pi pi-trash" style="font-size:11px" /></button>
          </div>
        </div>
      </div>
      <div v-if="!templates.data.length" class="empty-state" style="grid-column:1/-1">
        <i class="pi pi-file-edit" style="font-size:40px;color:var(--fg-4);margin-bottom:12px" />
        <p style="color:var(--fg-2);font-size:15px;font-weight:500;margin:0 0 4px">Aucun template</p>
        <a href="/email/builder" class="btn btn-primary"><i class="pi pi-plus" style="font-size:13px" /> Créer un template</a>
      </div>
    </div>
    <div v-else class="wh-panel">
      <table class="wh-dt">
        <thead><tr><th>Template</th><th>Catégorie</th><th>Dernière modification</th><th style="width:160px"></th></tr></thead>
        <tbody>
          <tr v-for="t in templates.data" :key="t.id" class="wh-dt-row">
            <td><div style="display:flex;align-items:center;gap:12px"><div class="list-thumb"><img v-if="t.thumbnail_url" :src="t.thumbnail_url" :alt="`Template ${t.name} thumbnail`" style="width:100%;height:100%;object-fit:cover;border-radius:3px" /><i v-else class="pi pi-file-edit" style="font-size:16px;color:var(--fg-3)" /></div><p style="font-weight:500;margin:0">{{ t.name }}</p></div></td>
            <td><span class="category-badge">{{ t.category ?? 'général' }}</span></td>
            <td style="color:var(--fg-3)">{{ formatDate(t.updated_at) }}</td>
            <td><div style="display:flex;gap:6px;justify-content:flex-end"><a :href="`/email/builder?template=${t.id}`" class="card-btn card-btn-primary"><i class="pi pi-pencil" style="font-size:11px" /> Éditer</a><button class="card-btn" @click="duplicateTemplate(t)"><i class="pi pi-copy" style="font-size:11px" /> Dupliquer</button><button class="card-btn card-btn-danger" @click="confirmDelete(t)"><i class="pi pi-trash" style="font-size:11px" /></button></div></td>
          </tr>
          <tr v-if="!templates.data.length"><td colspan="4" style="text-align:center;color:var(--fg-3);padding:32px">Aucun template trouvé.</td></tr>
        </tbody>
      </table>
    </div>
    <div v-if="templates.last_page > 1" style="display:flex;justify-content:center;padding:12px 0">
      <Paginator :rows="templates.per_page ?? 15" :total-records="templates.total ?? 0" :first="((templates.current_page ?? 1) - 1) * (templates.per_page ?? 15)" @page="(e) => goPage(e.page + 1)" />
    </div>
    <div v-if="previewItem" class="modal-overlay" role="presentation" @click.self="previewItem = null">
      <div class="preview-modal" role="dialog" aria-modal="true" :aria-label="`Aperçu du template ${previewItem.name}`">
        <div class="preview-header"><h3 style="margin:0;font-size:15px;font-weight:600">{{ previewItem.name }}</h3><button class="icon-btn" @click="previewItem = null"><i class="pi pi-times" style="font-size:14px" /></button></div>
        <div class="preview-body">
          <iframe v-if="previewItem.html_content" :srcdoc="previewItem.html_content" class="preview-iframe" sandbox="allow-same-origin" />
          <div v-else class="empty-state" style="padding:60px"><i class="pi pi-file-edit" style="font-size:40px;color:var(--fg-4);margin-bottom:12px" /><p style="color:var(--fg-3)">Aucun aperçu HTML</p></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border-subtle)"><button class="btn btn-secondary" @click="previewItem = null">Fermer</button><a :href="`/email/builder?template=${previewItem.id}`" class="btn btn-primary">Éditer</a></div>
      </div>
    </div>
    <div v-if="deleteTarget" class="modal-overlay" role="presentation" @click.self="deleteTarget = null">
      <div class="confirm-modal" role="alertdialog" aria-modal="true" aria-label="Confirmer la suppression du template">
        <div style="padding:20px 20px 12px"><h3 style="margin:0 0 8px">Supprimer le template</h3><p style="margin:0;font-size:14px;color:var(--fg-2)">Êtes-vous sûr de vouloir supprimer <strong>{{ deleteTarget.name }}</strong> ?</p></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid var(--border-subtle)"><button class="btn btn-secondary" @click="deleteTarget = null">Annuler</button><button class="btn btn-danger" :disabled="deleting" @click="deleteTemplate">{{ deleting ? 'Suppression…' : 'Supprimer' }}</button></div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Paginator from 'primevue/paginator'

const props = defineProps({ templates: { type: Object, required: true } })
const search = ref('')
const categoryFilter = ref('')
const viewMode = ref('grid')
const previewItem = ref(null)
const deleteTarget = ref(null)
const deleting = ref(false)
const categories = computed(() => [...new Set(props.templates.data.map(t => t.category).filter(Boolean))])
let searchTimer = null
const onSearchInput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 400) }
const applyFilters = () => router.get('/email/templates', { search: search.value, category: categoryFilter.value }, { preserveState: true, preserveScroll: true })
const goPage = (p) => router.get('/email/templates', { page: p, search: search.value, category: categoryFilter.value }, { preserveState: true })
const previewTemplate = (t) => { previewItem.value = t }
const duplicateTemplate = async (t) => { const res = await fetch(`/api/v1/email/templates/${t.id}/duplicate`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } }); if (res.ok) router.reload() }
const confirmDelete = (t) => { deleteTarget.value = t; deleting.value = false }
const deleteTemplate = async () => { if (!deleteTarget.value) return; deleting.value = true; try { await fetch(`/api/v1/email/templates/${deleteTarget.value.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } }); deleteTarget.value = null; router.reload() } finally { deleting.value = false } }
const formatDate = (v) => v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; }
.wh-page-title { margin:0; font-size:28px; font-weight:600; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; line-height:1.2; text-decoration:none; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-danger { background:#dc2626; color:#fff; }
.btn-danger:disabled { opacity:.6; cursor:not-allowed; }
.filter-bar { display:flex; align-items:center; gap:10px; padding:10px 14px; margin-bottom:16px; flex-wrap:wrap; }
.wh-filter-input { width:100%; padding:8px 10px 8px 30px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; }
.search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--fg-4); font-size:13px; pointer-events:none; }
.wh-select-sm { padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:13px; }
.view-toggle { display:flex; gap:2px; background:var(--bg-sunken); border-radius:var(--r-sm); padding:2px; margin-left:auto; }
.vbtn { display:flex; align-items:center; justify-content:center; width:30px; height:28px; border:none; background:transparent; color:var(--fg-3); border-radius:3px; cursor:pointer; }
.vbtn.active { background:var(--bg-canvas); color:var(--fg-1); }
.templates-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
.template-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.template-thumb { height:160px; background:var(--bg-sunken); position:relative; overflow:hidden; }
.thumb-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; }
.thumb-overlay { position:absolute; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; gap:8px; opacity:0; transition:opacity var(--dur-fast); }
.template-thumb:hover .thumb-overlay { opacity:1; }
.overlay-btn { display:flex; align-items:center; gap:5px; padding:7px 12px; border-radius:var(--r-md); background:#fff; color:var(--fg-1); font-size:12px; font-weight:500; border:none; cursor:pointer; text-decoration:none; }
.template-info { padding:12px 14px; }
.template-name { font-size:14px; font-weight:600; color:var(--fg-1); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.category-badge { display:inline-block; padding:2px 8px; border-radius:var(--r-pill); background:var(--halo-50); color:var(--halo-700); font-size:11px; font-weight:500; }
.card-actions { display:flex; gap:4px; margin-top:10px; }
.card-btn { display:inline-flex; align-items:center; gap:4px; padding:5px 9px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); font-size:11px; cursor:pointer; text-decoration:none; }
.card-btn-primary { background:var(--halo-50); color:var(--halo-700); border-color:var(--halo-200,#c7d2fe); }
.card-btn-danger { color:#dc2626; }
.list-thumb { width:44px; height:44px; background:var(--bg-sunken); border:1px solid var(--border-subtle); border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 20px; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px; }
.preview-modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:680px; max-width:100%; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; }
.preview-header { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border-subtle); }
.preview-body { flex:1; overflow:auto; }
.preview-iframe { width:100%; height:500px; border:none; }
.confirm-modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:440px; max-width:100%; }
.icon-btn { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border:none; background:transparent; color:var(--fg-3); border-radius:var(--r-sm); cursor:pointer; }
</style>
