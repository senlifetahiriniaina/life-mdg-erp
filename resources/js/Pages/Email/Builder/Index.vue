<template>
  <AppLayout>
    <Head :title="`Builder — ${template.name}`" />

    <!-- Top bar -->
    <div class="builder-topbar">
      <div class="builder-topbar-left">
        <a href="/email/campaigns" class="builder-back">
          <i class="pi pi-arrow-left" /> Campaigns
        </a>
        <span class="builder-sep">/</span>
        <span class="builder-title">{{ template.name }}</span>
      </div>
      <div class="builder-topbar-actions">
        <button class="btn btn-ghost" @click="openPreview">
          <i class="pi pi-eye" /> Preview
        </button>
        <button class="btn btn-ghost" @click="sendTestPrompt">
          <i class="pi pi-send" /> Test email
        </button>
        <button class="btn btn-primary" :disabled="saving" @click="saveBlocks">
          <i class="pi pi-save" /> {{ saving ? $t('common.saving') : $t('common.save') }}
        </button>
      </div>
    </div>

    <div class="builder-layout">
      <!-- Left panel: block library -->
      <aside class="builder-library">
        <p class="builder-section-title">Block Library</p>

        <div v-for="category in blockCategories" :key="category" class="builder-category">
          <p class="builder-category-title">{{ category }}</p>
          <div
            v-for="block in prebuiltBlocks.filter(b => b.category === category)"
            :key="block.id"
            class="builder-block-item"
            draggable="true"
            @dragstart="onLibraryDragStart(block)"
          >
            <i :class="blockIcon(block.block_type)" />
            <span>{{ block.label }}</span>
          </div>
        </div>
      </aside>

      <!-- Center canvas -->
      <main
        class="builder-canvas"
        @dragover.prevent
        @drop="onCanvasDrop"
      >
        <p v-if="blocks.length === 0" class="builder-canvas-empty">
          Drag blocks from the library to start building your email
        </p>

        <div
          v-for="(block, index) in blocks"
          :key="block._id"
          class="builder-canvas-block"
          :class="{ 'is-selected': selectedIndex === index }"
          @click="selectBlock(index)"
        >
          <!-- Block preview -->
          <div class="builder-block-preview" v-html="blockPreview(block)" />

          <!-- Block actions -->
          <div class="builder-block-actions">
            <button title="Move up" :disabled="index === 0" @click.stop="moveBlock(index, -1)">
              <i class="pi pi-angle-up" />
            </button>
            <button title="Move down" :disabled="index === blocks.length - 1" @click.stop="moveBlock(index, 1)">
              <i class="pi pi-angle-down" />
            </button>
            <button class="danger" title="Delete" @click.stop="removeBlock(index)">
              <i class="pi pi-trash" />
            </button>
          </div>
        </div>
      </main>

      <!-- Right panel: properties editor -->
      <aside class="builder-props" v-if="selectedBlock">
        <p class="builder-section-title">
          {{ selectedBlock.block_type.charAt(0).toUpperCase() + selectedBlock.block_type.slice(1) }} Properties
        </p>

        <!-- Header -->
        <template v-if="selectedBlock.block_type === 'header'">
          <label>Title</label>
          <input v-model="selectedBlock.config.title" class="wh-input" />
          <label>Subtitle</label>
          <input v-model="selectedBlock.config.subtitle" class="wh-input" />
          <label>Background color</label>
          <input type="color" v-model="selectedBlock.config.background_color" />
          <label>Text color</label>
          <input type="color" v-model="selectedBlock.config.text_color" />
        </template>

        <!-- Text -->
        <template v-else-if="selectedBlock.block_type === 'text'">
          <label>Content (HTML)</label>
          <textarea v-model="selectedBlock.config.content" class="wh-input" rows="8" />
        </template>

        <!-- Image -->
        <template v-else-if="selectedBlock.block_type === 'image'">
          <label>Image URL</label>
          <input v-model="selectedBlock.config.url" class="wh-input" placeholder="https://..." />
          <label>Alt text</label>
          <input v-model="selectedBlock.config.alt" class="wh-input" />
          <label>Width</label>
          <input v-model="selectedBlock.config.width" class="wh-input" placeholder="100%" />
        </template>

        <!-- Button -->
        <template v-else-if="selectedBlock.block_type === 'button'">
          <label>Label</label>
          <input v-model="selectedBlock.config.label" class="wh-input" />
          <label>URL</label>
          <input v-model="selectedBlock.config.url" class="wh-input" />
          <label>Color</label>
          <input type="color" v-model="selectedBlock.config.color" />
          <label>Size</label>
          <select v-model="selectedBlock.config.size" class="wh-input">
            <option value="small">Small</option>
            <option value="medium">Medium</option>
            <option value="large">Large</option>
          </select>
        </template>

        <!-- Divider -->
        <template v-else-if="selectedBlock.block_type === 'divider'">
          <label>Color</label>
          <input type="color" v-model="selectedBlock.config.color" />
        </template>

        <!-- Footer -->
        <template v-else-if="selectedBlock.block_type === 'footer'">
          <label>Content</label>
          <textarea v-model="selectedBlock.config.content" class="wh-input" rows="4" />
          <label>Unsubscribe URL</label>
          <input v-model="selectedBlock.config.unsubscribe_url" class="wh-input" />
          <label>Background color</label>
          <input type="color" v-model="selectedBlock.config.background_color" />
          <label>Text color</label>
          <input type="color" v-model="selectedBlock.config.text_color" />
        </template>

        <!-- Columns -->
        <template v-else-if="selectedBlock.block_type === 'columns'">
          <label>Left column (HTML)</label>
          <textarea v-model="selectedBlock.config.columns[0].content" class="wh-input" rows="4" />
          <label>Right column (HTML)</label>
          <textarea v-model="selectedBlock.config.columns[1].content" class="wh-input" rows="4" />
        </template>
      </aside>
      <aside class="builder-props builder-props-empty" v-else>
        <p style="color:var(--fg-3);font-size:14px;">Click a block to edit its properties</p>
      </aside>
    </div>

    <!-- Preview modal -->
    <div v-if="previewHtml" class="builder-modal-overlay" @click.self="previewHtml = null">
      <div class="builder-modal">
        <div class="builder-modal-header">
          <span>Preview</span>
          <button @click="previewHtml = null"><i class="pi pi-times" /></button>
        </div>
        <div class="builder-modal-body">
          <iframe :srcdoc="previewHtml" style="width:600px;height:600px;border:none;" />
        </div>
      </div>
    </div>

    <!-- Test email modal -->
    <div v-if="showTestModal" class="builder-modal-overlay" @click.self="showTestModal = false">
      <div class="builder-modal builder-modal-sm">
        <div class="builder-modal-header">
          <span>Send test email</span>
          <button @click="showTestModal = false"><i class="pi pi-times" /></button>
        </div>
        <div class="builder-modal-body" style="padding:20px">
          <label>Recipient email</label>
          <input v-model="testEmail" class="wh-input" type="email" placeholder="test@example.com" />
          <button class="btn btn-primary" style="margin-top:12px;width:100%" :disabled="sendingTest" @click="sendTestEmail">
            {{ sendingTest ? 'Sending…' : 'Send test' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface BlockConfig {
  [key: string]: unknown
  title?: string
  subtitle?: string
  background_color?: string
  text_color?: string
  content?: string
  url?: string
  alt?: string
  width?: string
  label?: string
  color?: string
  size?: string
  columns?: Array<{ content?: string }>
  unsubscribe_url?: string
}

interface Block {
  _id: string
  block_type: string
  config: BlockConfig
}

interface PrebuiltBlock {
  id: string
  label: string
  block_type: string
  category: string
  config: BlockConfig
}

interface Template {
  id: number
  name: string
  subject?: string
}

const props = defineProps<{ template: Template }>()

const blocks = ref<Block[]>([])
const prebuiltBlocks = ref<PrebuiltBlock[]>([])
const selectedIndex = ref<number | null>(null)
const saving = ref(false)
const previewHtml = ref<string | null>(null)
const showTestModal = ref(false)
const testEmail = ref('')
const sendingTest = ref(false)

let draggedLibraryBlock: PrebuiltBlock | null = null
let idCounter = 0

const blockCategories = computed(() => {
  const cats = [...new Set(prebuiltBlocks.value.map(b => b.category))]
  return cats
})

const selectedBlock = computed<Block | null>(() =>
  selectedIndex.value !== null ? blocks.value[selectedIndex.value] : null,
)

function newId(): string {
  return `block-${Date.now()}-${idCounter++}`
}

function blockIcon(type: string): string {
  const icons: Record<string, string> = {
    header: 'pi pi-image',
    text: 'pi pi-align-left',
    image: 'pi pi-photo',
    button: 'pi pi-stop',
    divider: 'pi pi-minus',
    columns: 'pi pi-table',
    footer: 'pi pi-bars',
  }
  return icons[type] ?? 'pi pi-box'
}

function onLibraryDragStart(block: PrebuiltBlock): void {
  draggedLibraryBlock = block
}

function onCanvasDrop(): void {
  if (!draggedLibraryBlock) return
  blocks.value.push({
    _id: newId(),
    block_type: draggedLibraryBlock.block_type,
    config: JSON.parse(JSON.stringify(draggedLibraryBlock.config)),
  })
  selectedIndex.value = blocks.value.length - 1
  draggedLibraryBlock = null
}

function selectBlock(index: number): void {
  selectedIndex.value = index
}

function moveBlock(index: number, direction: number): void {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= blocks.value.length) return
  const arr = [...blocks.value]
  const [item] = arr.splice(index, 1)
  arr.splice(newIndex, 0, item)
  blocks.value = arr
  selectedIndex.value = newIndex
}

function removeBlock(index: number): void {
  blocks.value.splice(index, 1)
  if (selectedIndex.value === index) {
    selectedIndex.value = null
  } else if (selectedIndex.value !== null && selectedIndex.value > index) {
    selectedIndex.value--
  }
}

function escHtml(s: unknown): string {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function blockPreview(block: Block): string {
  const type = block.block_type
  const cfg = block.config
  const label = type.charAt(0).toUpperCase() + type.slice(1)

  const previewMap: Record<string, string> = {
    header: `<div style="background:${escHtml(cfg.background_color ?? '#004080')};color:${escHtml(cfg.text_color ?? '#fff')};padding:16px;font-weight:bold;">${escHtml(cfg.title ?? 'Header')}</div>`,
    text: `<div style="padding:8px;font-size:13px;color:#333;max-height:60px;overflow:hidden;">${escHtml(cfg.content ?? 'Text block')}</div>`,
    image: `<div style="text-align:center;padding:8px"><img src="${escHtml(cfg.url ?? '')}" alt="${escHtml(cfg.alt ?? '')}" style="max-width:100%;max-height:60px;" /></div>`,
    button: `<div style="text-align:center;padding:8px"><span style="background:${escHtml(cfg.color ?? '#004080')};color:#fff;padding:6px 16px;border-radius:4px;font-size:13px;">${escHtml(cfg.label ?? 'Button')}</span></div>`,
    divider: `<div style="padding:8px"><hr style="border-top:1px solid ${escHtml(cfg.color ?? '#ddd')};margin:0" /></div>`,
    columns: `<div style="display:flex;gap:8px;padding:8px"><div style="flex:1;border:1px dashed #ccc;padding:4px;font-size:11px">Col 1</div><div style="flex:1;border:1px dashed #ccc;padding:4px;font-size:11px">Col 2</div></div>`,
    footer: `<div style="background:${escHtml(cfg.background_color ?? '#f4f4f4')};color:${escHtml(cfg.text_color ?? '#666')};padding:8px;font-size:12px;text-align:center;">${escHtml(cfg.content ?? 'Footer')}</div>`,
  }

  return previewMap[type] ?? `<div style="padding:8px;color:#999">${label}</div>`
}

async function saveBlocks(): Promise<void> {
  saving.value = true
  try {
    const payload = blocks.value.map((b, i) => ({
      block_type: b.block_type,
      config: b.config,
      block_order: i,
    }))
    await axios.put(`/api/v1/email/templates/${props.template.id}/blocks`, { blocks: payload })
  } finally {
    saving.value = false
  }
}

async function openPreview(): Promise<void> {
  const res = await axios.post(`/api/v1/email/templates/${props.template.id}/render`)
  previewHtml.value = res.data.html
}

function sendTestPrompt(): void {
  showTestModal.value = true
}

async function sendTestEmail(): Promise<void> {
  sendingTest.value = true
  try {
    await axios.post(`/api/v1/email/templates/${props.template.id}/send-test`, { email: testEmail.value })
    showTestModal.value = false
    testEmail.value = ''
  } finally {
    sendingTest.value = false
  }
}

async function loadBlocks(): Promise<void> {
  const [blocksRes, libraryRes] = await Promise.all([
    axios.get(`/api/v1/email/templates/${props.template.id}/blocks`),
    axios.get('/api/v1/email/builder/prebuilt-blocks'),
  ])
  blocks.value = blocksRes.data.map((b: { block_type: string; config: BlockConfig }) => ({
    ...b,
    _id: newId(),
  }))
  prebuiltBlocks.value = libraryRes.data
}

onMounted(loadBlocks)
</script>

<style scoped>
.builder-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  border-bottom: 1px solid var(--border-1, #e5e7eb);
  background: var(--surface-0, #fff);
}
.builder-topbar-left { display: flex; align-items: center; gap: 8px; }
.builder-back { color: var(--fg-3, #6b7280); text-decoration: none; font-size: 14px; }
.builder-sep { color: var(--fg-3, #6b7280); }
.builder-title { font-weight: 600; font-size: 15px; }
.builder-topbar-actions { display: flex; gap: 8px; }

.builder-layout {
  display: grid;
  grid-template-columns: 220px 1fr 260px;
  height: calc(100vh - 110px);
  overflow: hidden;
}

.builder-library {
  border-right: 1px solid var(--border-1, #e5e7eb);
  overflow-y: auto;
  padding: 12px;
  background: var(--surface-1, #f9fafb);
}
.builder-section-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--fg-3, #6b7280);
  letter-spacing: .06em;
  margin: 0 0 8px;
}
.builder-category { margin-bottom: 16px; }
.builder-category-title {
  font-size: 11px;
  font-weight: 600;
  color: var(--fg-3, #9ca3af);
  margin: 0 0 6px;
  padding-left: 2px;
}
.builder-block-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 13px;
  cursor: grab;
  background: var(--surface-0, #fff);
  border: 1px solid var(--border-1, #e5e7eb);
  margin-bottom: 4px;
  user-select: none;
}
.builder-block-item:hover { background: var(--primary-50, #eff6ff); border-color: var(--primary-300, #93c5fd); }

.builder-canvas {
  overflow-y: auto;
  padding: 24px;
  background: var(--surface-2, #f3f4f6);
}
.builder-canvas-empty {
  text-align: center;
  color: var(--fg-3, #9ca3af);
  margin-top: 80px;
  font-size: 15px;
}
.builder-canvas-block {
  position: relative;
  background: #fff;
  border: 2px solid transparent;
  border-radius: 6px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: border-color .15s;
}
.builder-canvas-block:hover { border-color: var(--primary-200, #bfdbfe); }
.builder-canvas-block.is-selected { border-color: var(--primary-500, #3b82f6); }
.builder-block-preview { overflow: hidden; max-height: 80px; }

.builder-block-actions {
  position: absolute;
  top: 4px;
  right: 4px;
  display: none;
  gap: 2px;
}
.builder-canvas-block:hover .builder-block-actions,
.builder-canvas-block.is-selected .builder-block-actions { display: flex; }
.builder-block-actions button {
  width: 26px;
  height: 26px;
  border: 1px solid var(--border-1, #e5e7eb);
  background: #fff;
  border-radius: 4px;
  cursor: pointer;
  font-size: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.builder-block-actions button:hover { background: var(--surface-1, #f9fafb); }
.builder-block-actions button.danger:hover { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }
.builder-block-actions button:disabled { opacity: .4; cursor: default; }

.builder-props {
  border-left: 1px solid var(--border-1, #e5e7eb);
  overflow-y: auto;
  padding: 16px;
  background: var(--surface-0, #fff);
}
.builder-props label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--fg-2, #374151);
  margin: 10px 0 4px;
}
.builder-props-empty { display: flex; align-items: center; justify-content: center; }

.wh-input {
  width: 100%;
  border: 1px solid var(--border-1, #d1d5db);
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 13px;
  box-sizing: border-box;
}

.builder-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.builder-modal {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  min-width: 640px;
}
.builder-modal-sm { min-width: 360px; }
.builder-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  border-bottom: 1px solid #e5e7eb;
  font-weight: 600;
}
.builder-modal-header button { background: none; border: none; cursor: pointer; font-size: 16px; }
.builder-modal-body { padding: 20px; }

.btn { padding: 7px 14px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: var(--primary-600, #2563eb); color: #fff; }
.btn-primary:hover { background: var(--primary-700, #1d4ed8); }
.btn-primary:disabled { opacity: .6; cursor: default; }
.btn-ghost { background: transparent; color: var(--fg-2, #374151); border: 1px solid var(--border-1, #d1d5db); }
.btn-ghost:hover { background: var(--surface-1, #f9fafb); }
</style>
