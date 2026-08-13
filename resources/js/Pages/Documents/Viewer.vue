<template>
  <AppLayout :title="`Document — ${document?.name ?? ''}`">
    <div class="flex gap-6 h-[calc(100vh-5rem)]">
      <!-- Document viewer -->
      <div class="flex-1 flex flex-col bg-gray-100 dark:bg-surface-700 rounded-xl overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center justify-between px-4 py-2 bg-white dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-center gap-3">
            <Button icon="pi pi-arrow-left" text @click="$inertia.visit('/documents')" />
            <span class="font-medium text-gray-800 dark:text-surface-100">{{ document?.name }}</span>
            <Tag :value="document?.file_type?.toUpperCase()" severity="secondary" />
            <!-- File type icon -->
            <i v-if="fileType === 'docx'" class="pi pi-file-word text-blue-500 text-xl" />
            <i v-else-if="fileType === 'xlsx'" class="pi pi-file-excel text-green-500 text-xl" />
            <i v-else-if="fileType === 'pptx'" class="pi pi-file text-orange-500 text-xl" />
          </div>
          <!-- Co-editing indicators -->
          <div class="flex items-center gap-1">
            <div
              v-for="viewer in activeViewers"
              :key="viewer"
              class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-400 to-purple-400 flex items-center justify-center text-white text-xs font-bold border-2 border-white -ml-1"
              :title="viewer"
            >{{ viewer.slice(0, 1).toUpperCase() }}</div>
            <span v-if="activeViewers.length > 0" class="text-xs text-surface-400 dark:text-surface-500 ml-1">{{ activeViewers.length }} personne(s)</span>
          </div>

          <div class="flex items-center gap-2">
            <Button icon="pi pi-search-minus" text rounded @click="zoom = Math.max(50, zoom - 10)" />
            <span class="text-sm text-surface-600 dark:text-surface-400 w-12 text-center">{{ zoom }}%</span>
            <Button icon="pi pi-search-plus" text rounded @click="zoom = Math.min(200, zoom + 10)" />
            <Divider layout="vertical" class="h-6" />
            <Button icon="pi pi-download" label="Télécharger" size="small" outlined @click="download" />
            <Button icon="pi pi-share-alt" label="Partager" size="small" outlined @click="shareModal = true" />
            <Button v-if="!signed" icon="pi pi-pencil" label="Signer" size="small" severity="success" @click="signModal = true" />
          </div>
        </div>

        <!-- Viewer area -->
        <div class="flex-1 overflow-auto flex items-start justify-center p-6">
          <div
            :style="{ transform: `scale(${zoom / 100})`, transformOrigin: 'top center' }"
            class="bg-white dark:bg-surface-800 dark:bg-surface-800 shadow-2xl rounded w-full max-w-4xl"
          >
            <!-- PDF — iframe viewer -->
            <iframe
              v-if="fileType === 'pdf'"
              :src="downloadUrl"
              class="w-full"
              style="min-height: 842px; border: none;"
            />

            <!-- DOCX — server HTML or fallback -->
            <div v-else-if="fileType === 'docx'" class="p-6 overflow-y-auto" style="min-height: 600px;">
              <div v-if="officeHtml" v-html="sanitizedOfficeHtml" class="prose max-w-none" />
              <div v-else class="flex flex-col items-center justify-center py-20 text-surface-400 dark:text-surface-500">
                <i class="pi pi-file-word text-6xl text-blue-500 mb-4" />
                <p class="text-lg font-medium text-surface-700 dark:text-surface-300">Document Word</p>
                <p class="text-sm mb-4 text-center max-w-xs">La prévisualisation avancée nécessite un traitement serveur (LibreOffice).</p>
                <Button label="Télécharger pour ouvrir" icon="pi pi-download" @click="download" />
              </div>
            </div>

            <!-- XLSX — spreadsheet tabs + table -->
            <div v-else-if="fileType === 'xlsx'" style="min-height: 600px;" class="overflow-auto">
              <div v-if="spreadsheetData.length">
                <!-- Sheet tabs -->
                <div class="flex gap-1 border-b px-4 pt-2 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
                  <button
                    v-for="(sheet, i) in spreadsheetData"
                    :key="i"
                    :class="['px-3 py-1 text-sm rounded-t transition-colors', activeSheet === i ? 'bg-white dark:bg-surface-800 border border-b-white -mb-px font-medium text-surface-900 dark:text-surface-50' : 'text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:text-surface-300']"
                    @click="activeSheet = i"
                  >{{ sheet.name }}</button>
                </div>
                <!-- Table -->
                <div class="overflow-x-auto">
                  <table class="text-xs border-collapse w-full">
                    <tr v-for="(row, ri) in spreadsheetData[activeSheet]?.data" :key="ri">
                      <td
                        v-for="(cell, ci) in row"
                        :key="ci"
                        class="border border-gray-200 dark:border-surface-700 px-2 py-0.5 whitespace-nowrap"
                      >{{ cell }}</td>
                    </tr>
                  </table>
                </div>
              </div>
              <div v-else class="flex flex-col items-center justify-center py-20 text-surface-400 dark:text-surface-500">
                <i class="pi pi-file-excel text-6xl text-green-500 mb-4" />
                <p class="text-lg font-medium text-surface-700 dark:text-surface-300 mb-2">Fichier Excel</p>
                <Button label="Télécharger" text size="small" icon="pi pi-download" @click="download" />
              </div>
            </div>

            <!-- PPTX -->
            <div v-else-if="fileType === 'pptx'" class="flex flex-col items-center justify-center py-20 text-surface-400 dark:text-surface-500" style="min-height: 600px;">
              <i class="pi pi-file text-6xl text-orange-500 mb-4" />
              <p class="text-lg font-medium text-surface-700 dark:text-surface-300">Présentation PowerPoint</p>
              <p class="text-sm mb-4 text-center max-w-xs">La prévisualisation des présentations est disponible après conversion serveur.</p>
              <Button label="Télécharger" icon="pi pi-download" @click="download" />
            </div>

            <!-- Image -->
            <img
              v-else-if="fileType === 'image'"
              :src="downloadUrl"
              class="max-h-full max-w-full object-contain mx-auto rounded"
              alt="document"
            />

            <!-- Generic fallback -->
            <div v-else class="w-full p-16 text-center text-surface-400 dark:text-surface-500" style="min-height: 600px;">
              <i class="pi pi-file text-6xl mb-4 block" />
              <p>Prévisualisation non disponible pour ce type de fichier.</p>
              <Button label="Télécharger" icon="pi pi-download" class="mt-4" @click="download" />
            </div>
          </div>
        </div>
      </div>

      <!-- Right sidebar: details & history -->
      <div class="w-72 flex flex-col gap-4 overflow-y-auto">
        <!-- Metadata -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-3">Informations</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-surface-500 dark:text-surface-400">Type</dt><dd class="font-medium">{{ document?.file_type?.toUpperCase() }}</dd></div>
            <div class="flex justify-between"><dt class="text-surface-500 dark:text-surface-400">Taille</dt><dd>{{ document?.file_size ? (document.file_size / 1024).toFixed(1) + ' KB' : '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-surface-500 dark:text-surface-400">Version</dt><dd>v{{ document?.version ?? 1 }}</dd></div>
            <div class="flex justify-between"><dt class="text-surface-500 dark:text-surface-400">Créé le</dt><dd>{{ document?.created_at ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-surface-500 dark:text-surface-400">Statut sig.</dt>
              <dd><Tag :value="signed ? 'Signé' : 'Non signé'" :severity="signed ? 'success' : 'warn'" class="text-xs" /></dd>
            </div>
          </dl>
        </div>

        <!-- Signatories -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-3">Signataires</h3>
          <div class="space-y-2">
            <div v-for="sig in signatories" :key="sig.id" class="flex items-center gap-2">
              <Avatar :label="sig.name[0]" size="small" class="bg-blue-100 text-blue-700" />
              <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-surface-900 dark:text-surface-50 truncate">{{ sig.name }}</p>
                <p class="text-xs text-surface-400 dark:text-surface-500">{{ sig.email }}</p>
              </div>
              <i :class="['pi text-sm', sig.signed ? 'pi-check-circle text-green-500' : 'pi-clock text-yellow-400']" />
            </div>
          </div>
        </div>

        <!-- Version history -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-3">Versions</h3>
          <div class="space-y-2">
            <div v-for="v in versions" :key="v.version" class="flex items-center gap-2 text-sm">
              <div :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold', v.current ? 'bg-primary-50 dark:bg-primary-900/200 text-white' : 'bg-gray-100 dark:bg-surface-700 text-surface-600 dark:text-surface-400']">
                {{ v.version }}
              </div>
              <div class="flex-1">
                <p class="text-xs text-surface-700 dark:text-surface-300">{{ v.label }}</p>
                <p class="text-xs text-surface-400 dark:text-surface-500">{{ v.date }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Share Modal -->
    <Dialog v-model:visible="shareModal" header="Partager le document" :style="{ width: '28rem' }" modal>
      <div class="space-y-3">
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Lien de partage</label>
          <div class="flex gap-2">
            <InputText :value="shareLink" readonly class="flex-1 text-xs" />
            <Button icon="pi pi-copy" outlined @click="copyLink" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Expiration</label>
            <Dropdown v-model="shareExpiry" :options="expiryOptions" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div>
            <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Accès</label>
            <Dropdown v-model="shareAccess" :options="['Lecture', 'Téléchargement']" class="w-full" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Fermer" outlined @click="shareModal = false" />
          <Button label="Générer lien" severity="primary" @click="generateLink" />
        </div>
      </div>
    </Dialog>

    <!-- Sign Modal -->
    <Dialog v-model:visible="signModal" header="Signature électronique" :style="{ width: '32rem' }" modal>
      <div class="space-y-4">
        <p class="text-sm text-surface-600 dark:text-surface-400">Dessinez votre signature ou tapez votre nom pour créer une signature électronique.</p>
        <div class="border-2 border-dashed border-gray-300 dark:border-surface-600 rounded-lg h-32 flex items-center justify-center bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 cursor-pointer" @click="signatureType = 'draw'">
          <span class="text-surface-400 dark:text-surface-500 text-sm">Zone de signature — cliquez pour dessiner</span>
        </div>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" outlined @click="signModal = false" />
          <Button label="Apposer la signature" severity="success" @click="applySignature" />
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag, Dialog, InputText, Dropdown, Avatar, Divider } from 'primevue'
import axios from 'axios'
import DOMPurify from 'dompurify'

const props = defineProps({ document: Object })

const zoom          = ref(100)
const activeViewers = ref<string[]>(['Alice M.', 'Bob D.']) // Mock — en réalité via WebSocket
const shareModal    = ref(false)
const signModal     = ref(false)
const shareLink     = ref('https://docs.widehalo.com/share/abc123')
const shareExpiry   = ref('7d')
const shareAccess   = ref('Lecture')
const signatureType = ref('draw')
const signed        = ref(false)

// Office preview state
const officeHtml         = ref<string | null>(null)
const sanitizedOfficeHtml = computed(() =>
  officeHtml.value ? DOMPurify.sanitize(officeHtml.value, { USE_PROFILES: { html: true } }) : ''
)
const spreadsheetData = ref([])
const activeSheet     = ref(0)

const expiryOptions = [
  { label: '24 heures', value: '1d' },
  { label: '7 jours', value: '7d' },
  { label: '30 jours', value: '30d' },
]

const signatories = ref([
  { id: 1, name: 'Jean Dupont', email: 'jean@example.com', signed: true },
  { id: 2, name: 'Marie Martin', email: 'marie@example.com', signed: false },
])

const versions = ref([
  { version: 3, label: 'Version courante', date: "Aujourd'hui", current: true },
  { version: 2, label: 'Modifications mineures', date: 'Il y a 3 jours', current: false },
  { version: 1, label: 'Version initiale', date: 'Il y a 1 semaine', current: false },
])

/**
 * Detect the file type from mime_type or extension.
 */
const fileType = computed(() => {
  const mime = props.document?.mime_type ?? ''
  const ext  = props.document?.file_type ?? props.document?.extension ?? ''

  if (mime.includes('pdf') || ext === 'pdf') return 'pdf'
  if (mime.includes('word') || mime.includes('wordprocessing') || ext === 'docx' || ext === 'doc') return 'docx'
  if (mime.includes('spreadsheet') || mime.includes('ms-excel') || ext === 'xlsx' || ext === 'xls') return 'xlsx'
  if (mime.includes('presentation') || ext === 'pptx' || ext === 'ppt') return 'pptx'
  if (mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image'
  return 'unknown'
})

const downloadUrl = computed(() => {
  if (!props.document?.id) return ''
  return `/api/v1/documents/files/${props.document.id}/download`
})

function download() {
  window.open(downloadUrl.value)
}

function copyLink() {
  navigator.clipboard?.writeText(shareLink.value)
}

function generateLink() {
  shareModal.value = false
}

function applySignature() {
  signed.value = true
  signModal.value = false
}

/**
 * On mount: fetch Office preview metadata if the file is an Office format.
 */
onMounted(async () => {
  const officeTypes = ['docx', 'xlsx', 'pptx']

  if (!props.document?.id || !officeTypes.includes(fileType.value)) return

  try {
    const res = await axios.get(`/api/v1/documents/${props.document.id}/office-preview`)
    const data = res.data

    if (data.preview_method === 'server_html' && data.html_content) {
      officeHtml.value = data.html_content
    }
  } catch {
    // Silently fall back to the client-side / download prompt
  }
})
</script>
