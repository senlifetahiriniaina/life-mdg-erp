<template>
  <AppLayout>
    <Head title="Builder Email" />

    <!-- Toolbar -->
    <div class="builder-toolbar">
      <div class="toolbar-left">
        <a href="/email/templates" class="back-btn" title="Retour aux templates">
          <i class="pi pi-arrow-left" style="font-size:13px" />
        </a>
        <div class="toolbar-name">
          <input
            v-model="templateName"
            class="name-input"
            placeholder="Nom du template…"
          />
        </div>
      </div>

      <div class="toolbar-center">
        <div class="preview-toggle">
          <button :class="['toggle-btn', { active: previewMode === 'desktop' }]" @click="previewMode = 'desktop'" title="Aperçu bureau">
            <i class="pi pi-desktop" style="font-size:14px" />
          </button>
          <button :class="['toggle-btn', { active: previewMode === 'mobile' }]" @click="previewMode = 'mobile'" title="Aperçu mobile">
            <i class="pi pi-mobile" style="font-size:14px" />
          </button>
        </div>
      </div>

      <div class="toolbar-right">
        <button class="tbtn tbtn-ghost" :disabled="historyIndex <= 0" title="Annuler (Ctrl+Z)" @click="undo">
          <i class="pi pi-undo" style="font-size:13px" />
        </button>
        <button class="tbtn tbtn-ghost" :disabled="historyIndex >= history.length - 1" title="Rétablir (Ctrl+Y)" @click="redo">
          <i class="pi pi-refresh" style="font-size:13px" />
        </button>
        <button class="tbtn tbtn-ghost" title="Exporter HTML" @click="exportHtml">
          <i class="pi pi-download" style="font-size:13px" /> Exporter HTML
        </button>
        <button class="tbtn tbtn-primary" :disabled="saving" @click="saveTemplate">
          <i class="pi pi-save" style="font-size:13px" />
          {{ saving ? 'Sauvegarde…' : 'Sauvegarder' }}
        </button>
      </div>
    </div>

    <div class="builder-shell">
      <!-- Left sidebar: block palette -->
      <div class="palette-panel">
        <p class="palette-title">Blocs</p>
        <div class="palette-grid">
          <div
            v-for="block in blockPalette"
            :key="block.type"
            class="palette-item"
            draggable="true"
            @dragstart="onPaletteDragStart($event, block.type)"
          >
            <div class="palette-icon">
              <i :class="['pi', block.icon]" style="font-size:16px;color:var(--halo-500)" />
            </div>
            <span class="palette-label">{{ block.label }}</span>
          </div>
        </div>

        <p class="palette-title" style="margin-top:20px">Structure</p>
        <div
          class="palette-item palette-item-wide"
          draggable="true"
          @dragstart="onPaletteDragStart($event, 'columns2')"
        >
          <div style="display:flex;gap:4px;flex:1">
            <div style="flex:1;height:28px;background:var(--bg-sunken);border-radius:3px;border:1px dashed var(--border-subtle)" />
            <div style="flex:1;height:28px;background:var(--bg-sunken);border-radius:3px;border:1px dashed var(--border-subtle)" />
          </div>
          <span class="palette-label" style="margin-left:10px">2 colonnes</span>
        </div>
      </div>

      <!-- Center: canvas -->
      <div class="canvas-panel">
        <div
          class="canvas-outer"
          :class="{ 'mobile-view': previewMode === 'mobile' }"
          @dragover.prevent="onCanvasDragOver"
          @dragleave="dragOverIndex = null"
          @drop="onCanvasDrop"
        >
          <!-- Drop hint when empty -->
          <div v-if="blocks.length === 0" class="canvas-empty">
            <i class="pi pi-inbox" style="font-size:36px;color:var(--fg-4);margin-bottom:12px" />
            <p style="color:var(--fg-3);font-size:14px;margin:0">Glissez des blocs ici pour commencer</p>
          </div>

          <!-- Drop indicator top -->
          <div v-if="dragOverIndex === 0" class="drop-indicator" />

          <template v-for="(block, idx) in blocks" :key="block.id">
            <div
              :class="['canvas-block', { selected: selectedId === block.id }]"
              @click.stop="selectBlock(block.id)"
            >
              <!-- Block controls -->
              <div v-if="selectedId === block.id" class="block-controls">
                <button class="block-ctrl" :disabled="idx === 0" title="Monter" @click.stop="moveBlock(idx, -1)">
                  <i class="pi pi-chevron-up" style="font-size:11px" />
                </button>
                <button class="block-ctrl" :disabled="idx === blocks.length - 1" title="Descendre" @click.stop="moveBlock(idx, 1)">
                  <i class="pi pi-chevron-down" style="font-size:11px" />
                </button>
                <button class="block-ctrl" title="Dupliquer" @click.stop="duplicateBlock(idx)">
                  <i class="pi pi-copy" style="font-size:11px" />
                </button>
                <button class="block-ctrl block-ctrl-danger" title="Supprimer" @click.stop="removeBlock(idx)">
                  <i class="pi pi-trash" style="font-size:11px" />
                </button>
              </div>

              <!-- Block renderer -->
              <BlockRenderer :block="block" />
            </div>

            <!-- Drop indicator between blocks -->
            <div v-if="dragOverIndex === idx + 1" class="drop-indicator" />
          </template>
        </div>
      </div>

      <!-- Right sidebar: properties panel -->
      <div class="props-panel">
        <template v-if="selectedBlock">
          <div class="props-header">
            <i :class="['pi', blockIcon(selectedBlock.type)]" style="font-size:14px;color:var(--halo-500)" />
            <p class="props-title">{{ blockLabel(selectedBlock.type) }}</p>
          </div>
          <div class="props-body">
            <!-- Header block -->
            <template v-if="selectedBlock.type === 'header'">
              <PropField label="Texte">
                <input v-model="selectedBlock.props.text" class="prop-input" @input="pushHistory" />
              </PropField>
              <PropField label="Taille de police">
                <input v-model.number="selectedBlock.props.fontSize" type="number" min="12" max="72" class="prop-input" @change="pushHistory" />
              </PropField>
              <PropField label="Couleur du texte">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.color" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.color" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Couleur de fond">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.bgColor" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.bgColor" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Alignement">
                <AlignButtons v-model="selectedBlock.props.align" @update:modelValue="pushHistory" />
              </PropField>
              <PropField label="Padding (px)">
                <PaddingInputs v-model="selectedBlock.props.padding" @update:modelValue="pushHistory" />
              </PropField>
            </template>

            <!-- Text block -->
            <template v-if="selectedBlock.type === 'text'">
              <PropField label="Contenu">
                <textarea v-model="selectedBlock.props.text" class="prop-textarea" rows="6" @input="pushHistory" />
              </PropField>
              <PropField label="Taille de police">
                <input v-model.number="selectedBlock.props.fontSize" type="number" min="10" max="48" class="prop-input" @change="pushHistory" />
              </PropField>
              <PropField label="Couleur du texte">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.color" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.color" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Couleur de fond">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.bgColor" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.bgColor" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Alignement">
                <AlignButtons v-model="selectedBlock.props.align" @update:modelValue="pushHistory" />
              </PropField>
              <PropField label="Padding (px)">
                <PaddingInputs v-model="selectedBlock.props.padding" @update:modelValue="pushHistory" />
              </PropField>
            </template>

            <!-- Image block -->
            <template v-if="selectedBlock.type === 'image'">
              <PropField label="URL de l'image">
                <input v-model="selectedBlock.props.src" class="prop-input" placeholder="https://…" @input="pushHistory" />
              </PropField>
              <PropField label="Texte alternatif">
                <input v-model="selectedBlock.props.alt" class="prop-input" placeholder="Description de l'image" @input="pushHistory" />
              </PropField>
              <PropField label="Lien (optionnel)">
                <input v-model="selectedBlock.props.link" class="prop-input" placeholder="https://…" @input="pushHistory" />
              </PropField>
              <PropField label="Largeur (%)">
                <input v-model.number="selectedBlock.props.width" type="number" min="10" max="100" class="prop-input" @change="pushHistory" />
              </PropField>
              <PropField label="Alignement">
                <AlignButtons v-model="selectedBlock.props.align" @update:modelValue="pushHistory" />
              </PropField>
              <PropField label="Padding (px)">
                <PaddingInputs v-model="selectedBlock.props.padding" @update:modelValue="pushHistory" />
              </PropField>
            </template>

            <!-- Button block -->
            <template v-if="selectedBlock.type === 'button'">
              <PropField label="Texte du bouton">
                <input v-model="selectedBlock.props.text" class="prop-input" @input="pushHistory" />
              </PropField>
              <PropField label="URL du lien">
                <input v-model="selectedBlock.props.href" class="prop-input" placeholder="https://…" @input="pushHistory" />
              </PropField>
              <PropField label="Couleur du bouton">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.bgColor" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.bgColor" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Couleur du texte">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.color" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.color" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Border-radius (px)">
                <input v-model.number="selectedBlock.props.radius" type="number" min="0" max="50" class="prop-input" @change="pushHistory" />
              </PropField>
              <PropField label="Taille de police">
                <input v-model.number="selectedBlock.props.fontSize" type="number" min="10" max="36" class="prop-input" @change="pushHistory" />
              </PropField>
              <PropField label="Alignement">
                <AlignButtons v-model="selectedBlock.props.align" @update:modelValue="pushHistory" />
              </PropField>
              <PropField label="Padding (px)">
                <PaddingInputs v-model="selectedBlock.props.padding" @update:modelValue="pushHistory" />
              </PropField>
            </template>

            <!-- Divider block -->
            <template v-if="selectedBlock.type === 'divider'">
              <PropField label="Couleur">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.color" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.color" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Épaisseur (px)">
                <input v-model.number="selectedBlock.props.height" type="number" min="1" max="10" class="prop-input" @change="pushHistory" />
              </PropField>
              <PropField label="Padding vertical (px)">
                <input v-model.number="selectedBlock.props.paddingY" type="number" min="0" max="60" class="prop-input" @change="pushHistory" />
              </PropField>
            </template>

            <!-- Columns2 block -->
            <template v-if="selectedBlock.type === 'columns2'">
              <PropField label="Contenu colonne gauche">
                <textarea v-model="selectedBlock.props.leftText" class="prop-textarea" rows="4" @input="pushHistory" />
              </PropField>
              <PropField label="Contenu colonne droite">
                <textarea v-model="selectedBlock.props.rightText" class="prop-textarea" rows="4" @input="pushHistory" />
              </PropField>
              <PropField label="Couleur de fond">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.bgColor" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.bgColor" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Padding (px)">
                <PaddingInputs v-model="selectedBlock.props.padding" @update:modelValue="pushHistory" />
              </PropField>
            </template>

            <!-- Footer block -->
            <template v-if="selectedBlock.type === 'footer'">
              <PropField label="Texte du footer">
                <textarea v-model="selectedBlock.props.text" class="prop-textarea" rows="4" @input="pushHistory" />
              </PropField>
              <PropField label="Lien désabonnement">
                <input v-model="selectedBlock.props.unsubLink" class="prop-input" placeholder="{{unsubscribe_url}}" @input="pushHistory" />
              </PropField>
              <PropField label="Couleur du texte">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.color" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.color" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Couleur de fond">
                <div style="display:flex;gap:8px;align-items:center">
                  <input v-model="selectedBlock.props.bgColor" type="color" class="prop-color" @change="pushHistory" />
                  <input v-model="selectedBlock.props.bgColor" class="prop-input" style="flex:1" @input="pushHistory" />
                </div>
              </PropField>
              <PropField label="Alignement">
                <AlignButtons v-model="selectedBlock.props.align" @update:modelValue="pushHistory" />
              </PropField>
            </template>
          </div>
        </template>

        <!-- No selection state -->
        <div v-else class="props-empty">
          <i class="pi pi-mouse" style="font-size:28px;color:var(--fg-4);margin-bottom:10px" />
          <p style="color:var(--fg-3);font-size:13px;text-align:center;margin:0">Sélectionnez un bloc pour modifier ses propriétés</p>
        </div>
      </div>
    </div>

    <!-- Export modal -->
    <div v-if="showExport" class="modal-overlay" @click.self="showExport = false">
      <div class="modal-box">
        <div class="modal-header">
          <h3 style="margin:0;font-size:16px;font-weight:600;color:var(--fg-1)">HTML exporté</h3>
          <button class="icon-btn" @click="showExport = false"><i class="pi pi-times" style="font-size:14px" /></button>
        </div>
        <textarea class="export-textarea" readonly :value="exportedHtml" rows="20" />
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border-subtle)">
          <button class="tbtn tbtn-ghost" @click="copyHtml">
            <i class="pi pi-copy" style="font-size:13px" /> {{ copied ? 'Copié !' : 'Copier' }}
          </button>
          <button class="tbtn tbtn-primary" @click="downloadHtml">
            <i class="pi pi-download" style="font-size:13px" /> Télécharger
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// ─── Props ───────────────────────────────────────────────────────────────────
const props = defineProps({
  templateData: { type: Object, default: null },
})

// ─── State ───────────────────────────────────────────────────────────────────
const templateName = ref(props.templateData?.name ?? 'Nouveau template')
const previewMode  = ref('desktop')
const selectedId   = ref(null)
const saving       = ref(false)
const showExport   = ref(false)
const exportedHtml = ref('')
const copied       = ref(false)
const dragOverIndex = ref(null)
let dragSrcType = null   // type being dragged from palette

// ─── Blocks ──────────────────────────────────────────────────────────────────
const defaultProps = {
  header:   () => ({ text: 'Votre titre ici', fontSize: 28, color: '#1a1a2e', bgColor: '#ffffff', align: 'center', padding: { top: 24, right: 24, bottom: 16, left: 24 } }),
  text:     () => ({ text: 'Votre texte ici. Rédigez votre message et personnalisez le style dans le panneau de droite.', fontSize: 15, color: '#444444', bgColor: '#ffffff', align: 'left', padding: { top: 12, right: 24, bottom: 12, left: 24 } }),
  image:    () => ({ src: 'https://via.placeholder.com/600x200?text=Image', alt: 'Image', link: '', width: 100, align: 'center', padding: { top: 12, right: 0, bottom: 12, left: 0 } }),
  button:   () => ({ text: 'Cliquez ici', href: '#', bgColor: '#4f46e5', color: '#ffffff', radius: 6, fontSize: 15, align: 'center', padding: { top: 16, right: 24, bottom: 16, left: 24 } }),
  divider:  () => ({ color: '#e5e7eb', height: 1, paddingY: 16 }),
  columns2: () => ({ leftText: 'Colonne gauche', rightText: 'Colonne droite', bgColor: '#ffffff', padding: { top: 16, right: 24, bottom: 16, left: 24 } }),
  footer:   () => ({ text: '© 2026 WideHalo. Tous droits réservés.\n123 Rue Exemple, Paris, France', unsubLink: '{{unsubscribe_url}}', color: '#888888', bgColor: '#f9fafb', align: 'center' }),
}

let uid = 1
const makeBlock = (type) => ({ id: uid++, type, props: defaultProps[type]() })

const blocks = ref(
  props.templateData?.blocks
    ? JSON.parse(JSON.stringify(props.templateData.blocks)).map(b => ({ ...b, id: uid++ }))
    : []
)

// ─── History (undo/redo) ─────────────────────────────────────────────────────
const history      = ref([JSON.stringify(blocks.value)])
const historyIndex = ref(0)

const pushHistory = () => {
  const snap = JSON.stringify(blocks.value)
  if (snap === history.value[historyIndex.value]) return
  history.value = history.value.slice(0, historyIndex.value + 1)
  history.value.push(snap)
  historyIndex.value = history.value.length - 1
}

const undo = () => {
  if (historyIndex.value > 0) {
    historyIndex.value--
    blocks.value = JSON.parse(history.value[historyIndex.value]).map(b => ({ ...b, id: uid++ }))
    selectedId.value = null
  }
}

const redo = () => {
  if (historyIndex.value < history.value.length - 1) {
    historyIndex.value++
    blocks.value = JSON.parse(history.value[historyIndex.value]).map(b => ({ ...b, id: uid++ }))
    selectedId.value = null
  }
}

// ─── Keyboard shortcuts ───────────────────────────────────────────────────────
const onKeyDown = (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo() }
  if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo() }
  if ((e.key === 'Delete' || e.key === 'Backspace') && selectedId.value && !e.target.closest('input,textarea')) {
    const idx = blocks.value.findIndex(b => b.id === selectedId.value)
    if (idx > -1) removeBlock(idx)
  }
}

onMounted(() => window.addEventListener('keydown', onKeyDown))
onUnmounted(() => window.removeEventListener('keydown', onKeyDown))

// ─── Selection ───────────────────────────────────────────────────────────────
const selectedBlock = computed(() => blocks.value.find(b => b.id === selectedId.value) ?? null)
const selectBlock   = (id) => { selectedId.value = selectedId.value === id ? null : id }

// ─── Block mutations ─────────────────────────────────────────────────────────
const removeBlock    = (idx) => { blocks.value.splice(idx, 1); selectedId.value = null; pushHistory() }
const duplicateBlock = (idx) => { const b = JSON.parse(JSON.stringify(blocks.value[idx])); b.id = uid++; blocks.value.splice(idx + 1, 0, b); selectedId.value = b.id; pushHistory() }
const moveBlock      = (idx, dir) => {
  const ni = idx + dir
  if (ni < 0 || ni >= blocks.value.length) return
  const tmp = blocks.value[idx]; blocks.value[idx] = blocks.value[ni]; blocks.value[ni] = tmp
  pushHistory()
}

// ─── Drag & Drop ─────────────────────────────────────────────────────────────
const blockPalette = [
  { type: 'header',  label: 'Titre / Header', icon: 'pi-heading' },
  { type: 'text',    label: 'Texte',           icon: 'pi-align-left' },
  { type: 'image',   label: 'Image',           icon: 'pi-image' },
  { type: 'button',  label: 'Bouton CTA',      icon: 'pi-external-link' },
  { type: 'divider', label: 'Séparateur',      icon: 'pi-minus' },
  { type: 'footer',  label: 'Footer',          icon: 'pi-building' },
]

const onPaletteDragStart = (e, type) => {
  dragSrcType = type
  e.dataTransfer.effectAllowed = 'copy'
  e.dataTransfer.setData('text/plain', type)
}

const onCanvasDragOver = (e) => {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'copy'
  // Compute insert index
  const target = e.currentTarget
  const blockEls = [...target.querySelectorAll('.canvas-block')]
  if (!blockEls.length) { dragOverIndex.value = 0; return }
  let idx = blockEls.length
  for (let i = 0; i < blockEls.length; i++) {
    const rect = blockEls[i].getBoundingClientRect()
    if (e.clientY < rect.top + rect.height / 2) { idx = i; break }
  }
  dragOverIndex.value = idx
}

const onCanvasDrop = (e) => {
  e.preventDefault()
  const type = e.dataTransfer.getData('text/plain') || dragSrcType
  if (!type || !defaultProps[type]) { dragOverIndex.value = null; return }
  const insertAt = dragOverIndex.value ?? blocks.value.length
  const newBlock = makeBlock(type)
  blocks.value.splice(insertAt, 0, newBlock)
  selectedId.value = newBlock.id
  dragOverIndex.value = null
  pushHistory()
}

// ─── Palette metadata helpers ─────────────────────────────────────────────────
const blockLabel = (t) => ({
  header: 'Titre / Header', text: 'Texte', image: 'Image',
  button: 'Bouton CTA', divider: 'Séparateur', columns2: '2 Colonnes', footer: 'Footer',
}[t] ?? t)
const blockIcon = (t) => ({
  header: 'pi-heading', text: 'pi-align-left', image: 'pi-image',
  button: 'pi-external-link', divider: 'pi-minus', columns2: 'pi-table', footer: 'pi-building',
}[t] ?? 'pi-box')

// ─── HTML export ──────────────────────────────────────────────────────────────
const pad = (p) => `${p.top}px ${p.right}px ${p.bottom}px ${p.left}px`

const renderBlockHtml = (block) => {
  const p = block.props
  switch (block.type) {
    case 'header':
      return `<table width="100%" cellpadding="0" cellspacing="0" style="background:${p.bgColor}">
  <tr><td style="padding:${pad(p.padding)};text-align:${p.align}">
    <h1 style="margin:0;font-family:Arial,sans-serif;font-size:${p.fontSize}px;color:${p.color};font-weight:700">${p.text}</h1>
  </td></tr>
</table>`

    case 'text':
      return `<table width="100%" cellpadding="0" cellspacing="0" style="background:${p.bgColor}">
  <tr><td style="padding:${pad(p.padding)};text-align:${p.align}">
    <p style="margin:0;font-family:Arial,sans-serif;font-size:${p.fontSize}px;color:${p.color};line-height:1.6">${p.text.replace(/\n/g, '<br>')}</p>
  </td></tr>
</table>`

    case 'image':
      return `<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:${pad(p.padding)};text-align:${p.align}">
    ${p.link ? `<a href="${p.link}" target="_blank">` : ''}
    <img src="${p.src}" alt="${p.alt}" width="${p.width}%" style="display:block;max-width:100%;${p.align === 'center' ? 'margin:0 auto' : ''}" />
    ${p.link ? '</a>' : ''}
  </td></tr>
</table>`

    case 'button':
      return `<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:${pad(p.padding)};text-align:${p.align}">
    <a href="${p.href}" target="_blank" style="display:inline-block;background:${p.bgColor};color:${p.color};font-family:Arial,sans-serif;font-size:${p.fontSize}px;font-weight:600;text-decoration:none;padding:12px 28px;border-radius:${p.radius}px">${p.text}</a>
  </td></tr>
</table>`

    case 'divider':
      return `<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:${p.paddingY}px 24px">
    <hr style="border:0;border-top:${p.height}px solid ${p.color};margin:0" />
  </td></tr>
</table>`

    case 'columns2':
      return `<table width="100%" cellpadding="0" cellspacing="0" style="background:${p.bgColor}">
  <tr>
    <td width="50%" valign="top" style="padding:${pad(p.padding)};font-family:Arial,sans-serif;font-size:14px;color:#444">${p.leftText}</td>
    <td width="50%" valign="top" style="padding:${pad(p.padding)};font-family:Arial,sans-serif;font-size:14px;color:#444">${p.rightText}</td>
  </tr>
</table>`

    case 'footer':
      return `<table width="100%" cellpadding="0" cellspacing="0" style="background:${p.bgColor}">
  <tr><td style="padding:24px;text-align:${p.align}">
    <p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:12px;color:${p.color}">${p.text.replace(/\n/g, '<br>')}</p>
    <a href="${p.unsubLink}" style="font-family:Arial,sans-serif;font-size:12px;color:${p.color}">Se désabonner</a>
  </td></tr>
</table>`

    default: return ''
  }
}

const generateHtml = () => {
  const body = blocks.value.map(renderBlockHtml).join('\n')
  return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>${templateName.value}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff">
      <tr><td>
${body}
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>`
}

const exportHtml = () => { exportedHtml.value = generateHtml(); showExport.value = true; copied.value = false }
const copyHtml   = () => { navigator.clipboard.writeText(exportedHtml.value); copied.value = true }
const downloadHtml = () => {
  const blob = new Blob([exportedHtml.value], { type: 'text/html' })
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob)
  a.download = `${templateName.value.replace(/\s+/g, '-').toLowerCase()}.html`; a.click()
}

// ─── Save ─────────────────────────────────────────────────────────────────────
const saveTemplate = async () => {
  saving.value = true
  try {
    const payload = {
      name: templateName.value,
      html_content: generateHtml(),
      blocks: blocks.value.map(({ type, props }) => ({ type, props })),
    }
    const id = props.templateData?.id
    const url = id ? `/api/v1/email/templates/${id}` : '/api/v1/email/templates'
    const method = id ? 'PUT' : 'POST'
    await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
      body: JSON.stringify(payload),
    })
  } finally {
    saving.value = false
  }
}

// ─── Sub-components ───────────────────────────────────────────────────────────
const PropField = defineComponent({
  props: { label: String },
  setup(p, { slots }) {
    return () => h('div', { class: 'prop-field' }, [
      h('label', { class: 'prop-label' }, p.label),
      slots.default?.(),
    ])
  },
})

const AlignButtons = defineComponent({
  props: { modelValue: String },
  emits: ['update:modelValue'],
  setup(p, { emit }) {
    const opts = [
      { value: 'left',   icon: 'pi-align-left' },
      { value: 'center', icon: 'pi-align-center' },
      { value: 'right',  icon: 'pi-align-right' },
    ]
    return () => h('div', { class: 'align-btns' }, opts.map(o =>
      h('button', {
        class: ['align-btn', { active: p.modelValue === o.value }],
        onClick: () => emit('update:modelValue', o.value),
      }, [h('i', { class: ['pi', o.icon], style: 'font-size:13px' })])
    ))
  },
})

const PaddingInputs = defineComponent({
  props: { modelValue: Object },
  emits: ['update:modelValue'],
  setup(p, { emit }) {
    const sides = [
      { key: 'top',    label: 'H' },
      { key: 'right',  label: 'D' },
      { key: 'bottom', label: 'B' },
      { key: 'left',   label: 'G' },
    ]
    return () => h('div', { class: 'padding-inputs' },
      sides.map(s => h('div', { class: 'padding-item' }, [
        h('label', { class: 'padding-label' }, s.label),
        h('input', {
          type: 'number', min: 0, max: 120,
          value: p.modelValue?.[s.key] ?? 0,
          class: 'prop-input padding-num',
          onInput: (e) => emit('update:modelValue', { ...p.modelValue, [s.key]: +e.target.value }),
        }),
      ]))
    )
  },
})

// ─── Block renderer component ─────────────────────────────────────────────────
const BlockRenderer = defineComponent({
  props: { block: Object },
  setup(p) {
    return () => {
      const b = p.block
      const pp = b.props
      switch (b.type) {
        case 'header': {
          const style = { backgroundColor: pp.bgColor, padding: `${pp.padding.top}px ${pp.padding.right}px ${pp.padding.bottom}px ${pp.padding.left}px`, textAlign: pp.align }
          return h('div', { style }, [h('h1', { style: { margin: 0, fontSize: `${pp.fontSize}px`, color: pp.color, fontWeight: '700', lineHeight: '1.2' } }, pp.text)])
        }
        case 'text': {
          const style = { backgroundColor: pp.bgColor, padding: `${pp.padding.top}px ${pp.padding.right}px ${pp.padding.bottom}px ${pp.padding.left}px`, textAlign: pp.align }
          return h('div', { style }, [h('p', { style: { margin: 0, fontSize: `${pp.fontSize}px`, color: pp.color, lineHeight: '1.6', whiteSpace: 'pre-wrap' } }, pp.text)])
        }
        case 'image': {
          const imgStyle = { display: 'block', width: `${pp.width}%`, maxWidth: '100%', margin: pp.align === 'center' ? '0 auto' : pp.align === 'right' ? '0 0 0 auto' : '0' }
          const wrapStyle = { padding: `${pp.padding.top}px ${pp.padding.right}px ${pp.padding.bottom}px ${pp.padding.left}px`, textAlign: pp.align }
          const img = h('img', { src: pp.src, alt: pp.alt, style: imgStyle })
          return h('div', { style: wrapStyle }, [pp.link ? h('a', { href: pp.link }, [img]) : img])
        }
        case 'button': {
          const style = { padding: `${pp.padding.top}px ${pp.padding.right}px ${pp.padding.bottom}px ${pp.padding.left}px`, textAlign: pp.align }
          const btnStyle = { display: 'inline-block', background: pp.bgColor, color: pp.color, fontSize: `${pp.fontSize}px`, fontWeight: '600', textDecoration: 'none', padding: '12px 28px', borderRadius: `${pp.radius}px`, cursor: 'pointer' }
          return h('div', { style }, [h('a', { href: pp.href, style: btnStyle }, pp.text)])
        }
        case 'divider':
          return h('div', { style: { padding: `${pp.paddingY}px 24px` } }, [
            h('hr', { style: { border: 0, borderTop: `${pp.height}px solid ${pp.color}`, margin: 0 } }),
          ])
        case 'columns2': {
          const colStyle = { flex: 1, padding: `${pp.padding.top}px ${pp.padding.right}px ${pp.padding.bottom}px ${pp.padding.left}px`, fontSize: '14px', color: '#444', whiteSpace: 'pre-wrap' }
          return h('div', { style: { backgroundColor: pp.bgColor, display: 'flex', gap: '8px' } }, [
            h('div', { style: colStyle }, pp.leftText),
            h('div', { style: colStyle }, pp.rightText),
          ])
        }
        case 'footer': {
          const style = { backgroundColor: pp.bgColor, padding: '24px', textAlign: pp.align }
          return h('div', { style }, [
            h('p', { style: { margin: '0 0 8px', fontSize: '12px', color: pp.color, whiteSpace: 'pre-wrap' } }, pp.text),
            h('a', { href: pp.unsubLink, style: { fontSize: '12px', color: pp.color } }, 'Se désabonner'),
          ])
        }
        default:
          return h('div', { style: { padding: '16px', color: '#999', fontSize: '13px' } }, `Bloc inconnu : ${b.type}`)
      }
    }
  },
})
</script>

<style scoped>
/* ─── Toolbar ─────────────────────────────────────────── */
.builder-toolbar {
  position:sticky; top:0; z-index:50;
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  background:var(--bg-canvas); border-bottom:1px solid var(--border-subtle);
  padding:8px 16px; margin:-20px -20px 0; /* bleed out of AppLayout padding */
}
.toolbar-left  { display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
.toolbar-center { display:flex; justify-content:center; }
.toolbar-right { display:flex; align-items:center; gap:6px; }
.back-btn { display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); text-decoration:none; transition:all var(--dur-fast); flex-shrink:0; }
.back-btn:hover { background:var(--bg-sunken); }
.name-input { border:1px solid transparent; background:transparent; color:var(--fg-1); font-size:14px; font-weight:600; padding:4px 8px; border-radius:var(--r-sm); width:220px; }
.name-input:focus { outline:none; border-color:var(--halo-400); background:var(--bg-sunken); }
.preview-toggle { display:flex; gap:2px; background:var(--bg-sunken); border-radius:var(--r-md); padding:3px; }
.toggle-btn { display:flex; align-items:center; justify-content:center; width:32px; height:28px; border:none; background:transparent; color:var(--fg-3); border-radius:var(--r-sm); cursor:pointer; transition:all var(--dur-fast); }
.toggle-btn.active { background:var(--bg-canvas); color:var(--fg-1); box-shadow:0 1px 3px rgba(0,0,0,.08); }
.tbtn { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:var(--r-md); border:1px solid transparent; font-size:13px; font-weight:500; cursor:pointer; transition:all var(--dur-fast); white-space:nowrap; }
.tbtn-ghost { background:var(--bg-canvas); color:var(--fg-2); border-color:var(--border-subtle); }
.tbtn-ghost:hover:not(:disabled) { background:var(--bg-sunken); color:var(--fg-1); }
.tbtn-ghost:disabled { opacity:0.4; cursor:not-allowed; }
.tbtn-primary { background:var(--halo-500); color:#fff; }
.tbtn-primary:hover:not(:disabled) { background:var(--halo-700); }
.tbtn-primary:disabled { opacity:0.6; cursor:not-allowed; }

/* ─── Builder shell ───────────────────────────────────── */
.builder-shell {
  display:flex; gap:0; height:calc(100vh - 120px); margin:0 -20px;
  border-top:1px solid var(--border-subtle); overflow:hidden;
}

/* ─── Palette panel ───────────────────────────────────── */
.palette-panel {
  width:176px; flex-shrink:0; background:var(--bg-sunken);
  border-right:1px solid var(--border-subtle); overflow-y:auto;
  padding:12px 8px;
}
.palette-title { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-3); margin:0 4px 8px; }
.palette-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
.palette-item {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:6px; padding:10px 6px; border-radius:var(--r-md);
  border:1px solid var(--border-subtle); background:var(--bg-canvas);
  cursor:grab; user-select:none; transition:all var(--dur-fast);
}
.palette-item:hover { border-color:var(--halo-400); box-shadow:0 1px 4px rgba(0,0,0,.06); }
.palette-item:active { cursor:grabbing; opacity:0.8; }
.palette-item-wide { grid-column:span 2; flex-direction:row; padding:8px 10px; }
.palette-icon { width:32px; height:32px; border-radius:var(--r-sm); background:var(--halo-50); display:flex; align-items:center; justify-content:center; }
.palette-label { font-size:11px; font-weight:500; color:var(--fg-2); text-align:center; line-height:1.2; }

/* ─── Canvas panel ────────────────────────────────────── */
.canvas-panel {
  flex:1; overflow-y:auto; background:#e2e8f0;
  display:flex; align-items:flex-start; justify-content:center;
  padding:24px 16px;
}
.canvas-outer {
  width:600px; background:#ffffff; min-height:400px;
  box-shadow:0 4px 24px rgba(0,0,0,.12); border-radius:2px;
  position:relative; transition:width 0.3s;
}
.canvas-outer.mobile-view { width:375px; }
.canvas-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px 20px; }
.canvas-block {
  position:relative; cursor:pointer;
  outline:2px solid transparent; outline-offset:-2px;
  transition:outline-color var(--dur-fast);
}
.canvas-block:hover { outline-color:rgba(99,102,241,.3); }
.canvas-block.selected { outline-color:var(--halo-500); }
.block-controls {
  position:absolute; top:-1px; right:0; z-index:10;
  display:flex; gap:2px; background:var(--halo-500); border-radius:0 0 0 var(--r-sm);
  padding:3px 4px;
}
.block-ctrl { display:flex; align-items:center; justify-content:center; width:22px; height:22px; border:none; background:transparent; color:#fff; border-radius:3px; cursor:pointer; transition:background var(--dur-fast); }
.block-ctrl:hover:not(:disabled) { background:rgba(255,255,255,.2); }
.block-ctrl:disabled { opacity:0.4; cursor:not-allowed; }
.block-ctrl-danger:hover { background:rgba(239,68,68,.4) !important; }
.drop-indicator { height:3px; background:var(--halo-500); border-radius:var(--r-pill); margin:0 12px; }

/* ─── Props panel ─────────────────────────────────────── */
.props-panel {
  width:256px; flex-shrink:0; background:var(--bg-canvas);
  border-left:1px solid var(--border-subtle); overflow-y:auto;
}
.props-header { display:flex; align-items:center; gap:8px; padding:14px 14px 12px; border-bottom:1px solid var(--border-subtle); }
.props-title { margin:0; font-size:13px; font-weight:600; color:var(--fg-1); }
.props-body { padding:12px 14px; display:flex; flex-direction:column; gap:10px; }
.props-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 16px; }

/* Prop field (used in sub-components) */
:deep(.prop-field) { display:flex; flex-direction:column; gap:4px; }
:deep(.prop-label) { font-size:11px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.05em; }
:deep(.prop-input) { width:100%; padding:6px 8px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); background:var(--bg-canvas); color:var(--fg-1); font-size:13px; box-sizing:border-box; }
:deep(.prop-input:focus) { outline:none; border-color:var(--halo-400); }
:deep(.prop-textarea) { width:100%; padding:6px 8px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); background:var(--bg-canvas); color:var(--fg-1); font-size:13px; resize:vertical; box-sizing:border-box; }
:deep(.prop-textarea:focus) { outline:none; border-color:var(--halo-400); }
:deep(.prop-color) { width:32px; height:32px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); padding:2px; cursor:pointer; background:none; }
:deep(.align-btns) { display:flex; gap:4px; }
:deep(.align-btn) { display:flex; align-items:center; justify-content:center; width:32px; height:28px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); background:var(--bg-canvas); color:var(--fg-3); cursor:pointer; transition:all var(--dur-fast); }
:deep(.align-btn.active) { background:var(--halo-50); border-color:var(--halo-400); color:var(--halo-600); }
:deep(.padding-inputs) { display:grid; grid-template-columns:repeat(4,1fr); gap:4px; }
:deep(.padding-item) { display:flex; flex-direction:column; align-items:center; gap:2px; }
:deep(.padding-label) { font-size:10px; color:var(--fg-4); font-weight:600; }
:deep(.padding-num) { width:100%; text-align:center; }

/* ─── Export modal ────────────────────────────────────── */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px; }
.modal-box { background:var(--bg-canvas); border-radius:var(--r-lg); width:720px; max-width:100%; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border-subtle); }
.icon-btn { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border:none; background:transparent; color:var(--fg-3); border-radius:var(--r-sm); cursor:pointer; }
.icon-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }
.export-textarea { flex:1; width:100%; padding:12px; border:none; font-family:monospace; font-size:12px; color:var(--fg-2); background:var(--bg-sunken); resize:none; overflow-y:auto; box-sizing:border-box; }
.export-textarea:focus { outline:none; }
</style>
