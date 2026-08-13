<template>
  <AppLayout>
    <Head title="Documents" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Documents</h1>
        <p class="wh-page-subtitle">{{ documents.total }} document{{ documents.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showUploadDialog = true">
          <i class="pi pi-upload" style="font-size:13px" /> Importer
        </button>
      </div>
    </div>

    <!-- OCR Search bar -->
    <div class="search-bar-wrap">
      <div class="search-bar">
        <i class="pi pi-search" style="font-size:13px;color:var(--fg-3)" />
        <input
          v-model="ocrQuery"
          type="text"
          class="search-input"
          placeholder="Recherche dans le contenu des documents (OCR)..."
          @input="debounceSearch"
        />
        <button v-if="ocrQuery" class="btn-icon" @click="clearSearch">
          <i class="pi pi-times" style="font-size:11px" />
        </button>
      </div>
    </div>

    <!-- OCR search results -->
    <div v-if="ocrResults.length > 0" class="wh-panel" style="margin-bottom:16px">
      <div style="padding:10px 18px;border-bottom:1px solid var(--border-subtle);font-size:12px;font-weight:600;color:var(--fg-3);text-transform:uppercase;letter-spacing:.06em">
        Résultats de recherche ({{ ocrResults.length }})
      </div>
      <div v-for="result in ocrResults" :key="result.document?.id" class="search-result-row" @click="openPreview(result.document)">
        <i :class="[extensionIcon(result.document?.extension)]" style="font-size:16px;flex-shrink:0;color:var(--fg-3)" />
        <div style="flex:1;min-width:0">
          <div style="font-weight:500;color:var(--fg-1);font-size:13px">{{ result.document?.title }}</div>
          <div style="font-size:12px;color:var(--fg-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:600px">{{ result.excerpt }}</div>
        </div>
        <i class="pi pi-eye" style="font-size:13px;color:var(--fg-4)" />
      </div>
    </div>

    <div style="display:flex;gap:16px;align-items:flex-start">
      <!-- Folder sidebar -->
      <div class="wh-panel folder-sidebar">
        <div style="padding:12px 14px;border-bottom:1px solid var(--border-subtle)">
          <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--fg-3);margin:0">Dossiers</p>
        </div>
        <div style="padding:6px 0">
          <button
            class="folder-item"
            :class="selectedFolderId === null ? 'folder-item-active' : ''"
            @click="selectedFolderId = null"
          >
            <i class="pi pi-folder" style="font-size:13px;color:var(--fg-4)" />
            Tous les documents
          </button>
          <template v-for="folder in folders" :key="folder.id">
            <button
              class="folder-item"
              :class="selectedFolderId === folder.id ? 'folder-item-active' : ''"
              @click="selectedFolderId = folder.id"
            >
              <i class="pi pi-folder" style="font-size:13px" :style="folder.color ? `color:${folder.color}` : 'color:var(--fg-4)'" />
              {{ folder.name }}
            </button>
            <template v-if="folder.children?.length">
              <button
                v-for="child in folder.children"
                :key="child.id"
                class="folder-item folder-item-child"
                :class="selectedFolderId === child.id ? 'folder-item-active' : ''"
                @click="selectedFolderId = child.id"
              >
                <i class="pi pi-folder-open" style="font-size:12px;color:var(--fg-4)" />
                {{ child.name }}
              </button>
            </template>
          </template>
        </div>
      </div>

      <!-- Documents table -->
      <div class="wh-panel" style="flex:1;min-width:0">
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Document</th>
              <th>Type</th>
              <th>Taille</th>
              <th>Dossier</th>
              <th>Verrouillé</th>
              <th>Créé le</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="doc in documents.data"
              :key="doc.id"
              class="wh-dt-row"
              @click="openPreview(doc)"
            >
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <i :class="[extensionIcon(doc.extension), extensionColor(doc.extension)]" style="font-size:18px" />
                  <div>
                    <div style="font-weight:500;color:var(--fg-1)">{{ doc.title }}</div>
                    <div v-if="doc.description" style="font-size:12px;color:var(--fg-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px">{{ doc.description }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span :class="['wh-badge', extensionBadgeClass(doc.extension)]">{{ (doc.extension || '—').toUpperCase() }}</span>
              </td>
              <td style="color:var(--fg-2);font-variant-numeric:tabular-nums">{{ formatSize(doc.size) }}</td>
              <td style="color:var(--fg-2)">{{ doc.folder?.name ?? '—' }}</td>
              <td>
                <i :class="doc.is_locked ? 'pi pi-lock' : 'pi pi-lock-open'" :style="doc.is_locked ? 'color:var(--danger-fg)' : 'color:var(--fg-4)'" style="font-size:13px" />
              </td>
              <td style="color:var(--fg-3)">{{ formatDate(doc.created_at) }}</td>
              <td @click.stop>
                <div style="display:flex;gap:6px">
                  <button class="btn-icon" title="Prévisualiser" @click="openPreview(doc)">
                    <i class="pi pi-eye" style="font-size:13px;color:var(--fg-3)" />
                  </button>
                  <a :href="`/api/v1/documents/${doc.id}/download`" class="btn-icon" title="Télécharger" @click.stop>
                    <i class="pi pi-download" style="font-size:13px;color:var(--fg-3)" />
                  </a>
                </div>
              </td>
            </tr>
            <tr v-if="documents.data.length === 0">
              <td colspan="7" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucun document trouvé.</td>
            </tr>
          </tbody>
        </table>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
          <span style="font-size:13px;color:var(--fg-3)">{{ documents.total }} résultat{{ documents.total !== 1 ? 's' : '' }}</span>
          <Paginator :rows="documents.per_page" :total-records="documents.total" :first="(documents.current_page - 1) * documents.per_page" />
        </div>
      </div>
    </div>

    <!-- Upload Dialog -->
    <Dialog v-model:visible="showUploadDialog" header="Importer un document" :modal="true" :style="{ width: '480px' }">
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div class="form-field">
          <label class="form-label">Titre</label>
          <InputText v-model="uploadForm.title" class="w-full" placeholder="Titre du document" />
        </div>
        <div class="form-field">
          <label class="form-label">Description</label>
          <Textarea v-model="uploadForm.description" class="w-full" rows="3" placeholder="Description optionnelle" />
        </div>
        <div class="form-field">
          <label class="form-label">Fichier</label>
          <input type="file" style="font-size:13px;color:var(--fg-2)" />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showUploadDialog = false">Annuler</button>
        <button class="btn btn-primary" @click="showUploadDialog = false">
          <i class="pi pi-upload" style="font-size:13px" /> Importer
        </button>
      </template>
    </Dialog>

    <!-- Document Viewer -->
    <DocumentViewer
      v-model:visible="showViewer"
      :document="previewDocument"
    />

    <GuidedTour tour-id="documents-index" :steps="tourSteps" />
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import Paginator from 'primevue/paginator'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import DocumentViewer from '@/Components/Documents/DocumentViewer.vue'
import axios from 'axios'

const tourSteps = [
  { tag: 'Dossiers · Étape 1 / 5', icon: 'pi-folder', title: 'Organisation par dossiers', description: 'Naviguez dans l\'arborescence de dossiers à gauche pour retrouver rapidement vos fichiers.' },
  { tag: 'Fichiers · Étape 2 / 5', icon: 'pi-file', title: 'Bibliothèque de documents', description: 'Tous vos fichiers avec type, taille, date et propriétaire sont affichés dans le tableau central.' },
  { tag: 'Import · Étape 3 / 5', icon: 'pi-upload', title: 'Importer un document', description: 'Le bouton « Importer » ouvre un formulaire pour uploader un fichier et lui associer un titre et une description.' },
  { tag: 'Recherche · Étape 4 / 5', icon: 'pi-search', title: 'Recherche full-text', description: 'Utilisez la barre de recherche pour retrouver n\'importe quel document par titre, tag ou contenu indexé.' },
  { tag: 'Prévisualisation · Étape 5 / 5', icon: 'pi-eye', title: 'Ouvrir et partager', description: 'Cliquez sur l\'œil pour prévisualiser un fichier dans le navigateur ou générer un lien de partage sécurisé.' },
]

const props = defineProps({
  documents: { type: Object, required: true },
  folders:   { type: Array, required: true },
})

const showUploadDialog = ref(false)
const selectedFolderId = ref(null)
const uploadForm = ref({ title: '', description: '' })

// Preview
const showViewer     = ref(false)
const previewDocument = ref(null)

function openPreview(doc) {
  previewDocument.value = doc
  showViewer.value = true
}

// OCR Search
const ocrQuery   = ref('')
const ocrResults = ref([])
let searchTimer  = null

function debounceSearch() {
  clearTimeout(searchTimer)
  if (ocrQuery.value.length < 2) { ocrResults.value = []; return }
  searchTimer = setTimeout(runSearch, 400)
}

async function runSearch() {
  try {
    const { data } = await axios.get('/api/v1/documents/search', { params: { q: ocrQuery.value } })
    ocrResults.value = Array.isArray(data) ? data : []
  } catch {
    ocrResults.value = []
  }
}

function clearSearch() {
  ocrQuery.value   = ''
  ocrResults.value = []
}

const extensionIcon = (ext) => {
  const e = (ext || '').toLowerCase()
  if (['pdf'].includes(e))               return 'pi pi-file-pdf'
  if (['doc', 'docx'].includes(e))       return 'pi pi-file-word'
  if (['xls', 'xlsx', 'csv'].includes(e)) return 'pi pi-file-excel'
  if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'].includes(e)) return 'pi pi-image'
  return 'pi pi-file'
}

const extensionColor = (ext) => {
  const e = (ext || '').toLowerCase()
  if (['pdf'].includes(e))               return 'text-red'
  if (['doc', 'docx'].includes(e))       return 'text-blue'
  if (['xls', 'xlsx', 'csv'].includes(e)) return 'text-green'
  if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'].includes(e)) return 'text-purple'
  return 'text-muted'
}

const extensionBadgeClass = (ext) => {
  const e = (ext || '').toLowerCase()
  if (['pdf'].includes(e))               return 'wh-badge-red'
  if (['doc', 'docx'].includes(e))       return 'wh-badge-blue'
  if (['xls', 'xlsx', 'csv'].includes(e)) return 'wh-badge-green'
  if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'].includes(e)) return 'wh-badge-purple'
  return 'wh-badge-slate'
}

const formatSize = (bytes) => {
  if (!bytes) return '—'
  if (bytes < 1024)        return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  return `${(bytes / 1024 / 1024).toFixed(1)} Mo`
}

const formatDate = (date) => date ? new Date(date).toLocaleDateString('fr-FR') : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; text-decoration:none; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-icon { background:none; border:none; cursor:pointer; padding:5px; border-radius:var(--r-sm); display:flex; align-items:center; }
.btn-icon:hover { background:var(--bg-sunken); }
.folder-sidebar { width:220px; flex-shrink:0; }
.folder-item { width:100%; text-align:left; display:flex; align-items:center; gap:8px; padding:7px 14px; font-size:13px; color:var(--fg-2); background:none; border:none; cursor:pointer; border-radius:0; transition:background var(--dur-fast); }
.folder-item:hover { background:var(--bg-sunken); color:var(--fg-1); }
.folder-item-active { background:var(--halo-50); color:var(--halo-700); font-weight:500; }
.folder-item-child { padding-left:30px; }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-label { font-size:12px; font-weight:500; color:var(--fg-2); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 6px; border-radius:var(--r-pill); font-size:10px; font-weight:700; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-purple { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.text-red    { color:var(--red-600); }
.text-blue   { color:var(--halo-500); }
.text-green  { color:var(--green-600); }
.text-purple { color:var(--halo-500); }
.text-muted  { color:var(--fg-4); }

/* Search bar */
.search-bar-wrap { margin-bottom:16px; }
.search-bar { display:flex; align-items:center; gap:10px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-md); padding:8px 14px; }
.search-input { flex:1; border:none; outline:none; font-size:13px; color:var(--fg-1); background:transparent; font-family:var(--font-sans); }
.search-input::placeholder { color:var(--fg-4); }

/* OCR Results */
.search-result-row { display:flex; align-items:center; gap:12px; padding:10px 18px; border-bottom:1px solid var(--border-subtle); cursor:pointer; transition:background var(--dur-fast); }
.search-result-row:last-child { border-bottom:0; }
.search-result-row:hover { background:var(--bg-sunken); }

.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
</style>
