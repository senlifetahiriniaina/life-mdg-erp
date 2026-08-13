<template>
  <AppLayout>
    <Head title="Themes" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Ecommerce · Themes</h1>
        <p class="wh-page-subtitle">Customize your storefront appearance</p>
      </div>
    </div>

    <!-- Store selector -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <label class="wh-label" style="margin:0">Store:</label>
      <select v-model="selectedStoreId" class="wh-input" style="max-width:200px" @change="loadThemes">
        <option value="">All stores</option>
        <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
    </div>

    <!-- Prebuilt themes -->
    <h2 style="font-size:15px;font-weight:600;margin-bottom:12px">Prebuilt Themes</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-bottom:32px">
      <div v-for="t in prebuiltThemes" :key="t.slug" class="theme-card">
        <!-- Preview placeholder -->
        <div
          class="theme-preview"
          :style="{ background: `linear-gradient(135deg, ${t.config.primary_color} 0%, ${t.config.secondary_color} 100%)` }"
        >
          <div style="font-family:sans-serif;color:#fff;font-size:11px;padding:12px;font-weight:600">{{ t.name }}</div>
        </div>
        <div style="padding:12px">
          <div style="font-weight:600;margin-bottom:4px">{{ t.name }}</div>
          <div style="font-size:12px;color:var(--fg-3);margin-bottom:12px">{{ t.description }}</div>
          <button class="wh-btn wh-btn-secondary wh-btn-sm" @click="applyPrebuilt(t)">Use theme</button>
        </div>
      </div>
    </div>

    <!-- Custom themes -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <h2 style="font-size:15px;font-weight:600;margin:0">Custom Themes</h2>
      <button class="wh-btn wh-btn-primary wh-btn-sm" @click="showCreate = true">New Theme</button>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
      <div
        v-for="t in themes"
        :key="t.id"
        class="theme-card"
        :class="{ 'theme-card--active': t.is_active }"
        @click="selectTheme(t)"
      >
        <!-- Preview -->
        <div
          class="theme-preview"
          :style="{ background: t.config?.primary_color ? `linear-gradient(135deg, ${t.config.primary_color} 0%, ${t.config.secondary_color ?? '#888'} 100%)` : 'var(--bg-2)' }"
        >
          <div v-if="t.is_active" class="theme-active-badge">{{ $t('common.active') }}</div>
        </div>
        <div style="padding:12px">
          <div style="font-weight:600;margin-bottom:8px">{{ t.name }}</div>
          <div style="display:flex;gap:8px">
            <button class="wh-btn wh-btn-ghost wh-btn-sm" @click.stop="openCustomize(t)">Customize</button>
            <button
              v-if="!t.is_active"
              class="wh-btn wh-btn-primary wh-btn-sm"
              @click.stop="activateTheme(t)"
            >Activate</button>
          </div>
        </div>
      </div>

      <div v-if="!themes.length" style="color:var(--fg-3);font-size:14px;padding:24px">
        No custom themes yet.
      </div>
    </div>

    <!-- Customize panel -->
    <div v-if="customizing" class="wh-modal-overlay" @click.self="customizing = null">
      <div class="wh-modal" style="max-width:560px">
        <div class="wh-modal-header">
          <h3>Customize — {{ customizing.name }}</h3>
          <button class="wh-btn-ghost" @click="customizing = null">✕</button>
        </div>
        <div class="wh-modal-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
              <label class="wh-label">Primary color</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input v-model="config.primary_color" type="color" style="width:40px;height:36px;border:none;cursor:pointer" />
                <input v-model="config.primary_color" type="text" class="wh-input" placeholder="#3b82f6" />
              </div>
            </div>
            <div>
              <label class="wh-label">Secondary color</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input v-model="config.secondary_color" type="color" style="width:40px;height:36px;border:none;cursor:pointer" />
                <input v-model="config.secondary_color" type="text" class="wh-input" placeholder="#64748b" />
              </div>
            </div>
            <div>
              <label class="wh-label">Font family</label>
              <select v-model="config.font_family" class="wh-input">
                <option value="Inter">Inter</option>
                <option value="Poppins">Poppins</option>
                <option value="Merriweather">Merriweather</option>
              </select>
            </div>
            <div>
              <label class="wh-label">Header layout</label>
              <select v-model="config.header_layout" class="wh-input">
                <option value="centered">Centered</option>
                <option value="left">Left</option>
                <option value="right">Right</option>
              </select>
            </div>
          </div>

          <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input v-model="config.show_search" type="checkbox" />
              <span>Show search bar</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input v-model="config.show_featured" type="checkbox" />
              <span>Show featured products</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input v-model="config.show_categories" type="checkbox" />
              <span>Show categories sidebar</span>
            </label>
          </div>

          <!-- Live preview placeholder -->
          <div style="margin-top:20px">
            <label class="wh-label">Live Preview</label>
            <div
              class="theme-preview-lg"
              :style="{ background: `linear-gradient(135deg, ${config.primary_color ?? '#3b82f6'} 0%, ${config.secondary_color ?? '#64748b'} 100%)` }"
            >
              <div style="padding:16px;color:#fff">
                <div style="font-weight:700;font-size:16px;margin-bottom:4px" :style="{ fontFamily: config.font_family }">
                  Your Store
                </div>
                <div v-if="config.show_search" style="background:rgba(255,255,255,.2);border-radius:4px;padding:6px 10px;font-size:12px;margin-bottom:8px">
                  Search products…
                </div>
                <div style="display:flex;gap:8px">
                  <div v-if="config.show_categories" style="width:60px;background:rgba(255,255,255,.15);border-radius:4px;padding:6px;font-size:10px">Categories</div>
                  <div style="flex:1;background:rgba(255,255,255,.15);border-radius:4px;padding:6px;font-size:10px">
                    {{ config.show_featured ? 'Featured Products' : 'Products' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="wh-modal-footer">
          <button class="wh-btn wh-btn-ghost" @click="customizing = null">{{ $t('common.cancel') }}</button>
          <button class="wh-btn wh-btn-primary" :disabled="saving" @click="saveConfig">
            {{ saving ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Create theme modal -->
    <div v-if="showCreate" class="wh-modal-overlay" @click.self="showCreate = false">
      <div class="wh-modal" style="max-width:400px">
        <div class="wh-modal-header">
          <h3>New Custom Theme</h3>
          <button class="wh-btn-ghost" @click="showCreate = false">✕</button>
        </div>
        <div class="wh-modal-body" style="display:flex;flex-direction:column;gap:12px">
          <div>
            <label class="wh-label">Name</label>
            <input v-model="newTheme.name" type="text" class="wh-input" placeholder="My Theme" />
          </div>
          <div>
            <label class="wh-label">Slug</label>
            <input v-model="newTheme.slug" type="text" class="wh-input" placeholder="my-theme" />
          </div>
          <div>
            <label class="wh-label">Store</label>
            <select v-model="newTheme.store_id" class="wh-input">
              <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
        </div>
        <div class="wh-modal-footer">
          <button class="wh-btn wh-btn-ghost" @click="showCreate = false">{{ $t('common.cancel') }}</button>
          <button class="wh-btn wh-btn-primary" :disabled="saving" @click="createTheme">
            {{ saving ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface ThemeConfig {
  primary_color?: string
  secondary_color?: string
  font_family?: string
  header_layout?: string
  show_search?: boolean
  show_featured?: boolean
  show_categories?: boolean
}

interface Theme {
  id: number
  name: string
  slug: string
  store_id: number
  is_active: boolean
  config: ThemeConfig | null
  preview_url: string | null
}

interface PrebuiltTheme {
  slug: string
  name: string
  description: string
  config: ThemeConfig
}

const props = defineProps<{
  themes: Theme[]
  stores: { id: number; name: string }[]
  filters: { store_id?: string }
}>()

const selectedStoreId = ref(props.filters.store_id ?? '')
const customizing = ref<Theme | null>(null)
const showCreate = ref(false)
const saving = ref(false)

const config = ref<ThemeConfig>({
  primary_color: '#3b82f6',
  secondary_color: '#64748b',
  font_family: 'Inter',
  header_layout: 'centered',
  show_search: true,
  show_featured: true,
  show_categories: true,
})

const newTheme = ref({ name: '', slug: '', store_id: props.stores[0]?.id ?? 0 })

const prebuiltThemes: PrebuiltTheme[] = [
  { slug: 'default', name: 'Default', description: 'Clean and minimal storefront theme.', config: { primary_color: '#3b82f6', secondary_color: '#64748b', font_family: 'Inter', header_layout: 'centered', show_search: true, show_featured: true, show_categories: true } },
  { slug: 'modern', name: 'Modern', description: 'Bold and contemporary design with vibrant colors.', config: { primary_color: '#8b5cf6', secondary_color: '#ec4899', font_family: 'Poppins', header_layout: 'left', show_search: true, show_featured: true, show_categories: false } },
  { slug: 'classic', name: 'Classic', description: 'Timeless and elegant with serif fonts.', config: { primary_color: '#1e3a5f', secondary_color: '#c9a84c', font_family: 'Merriweather', header_layout: 'right', show_search: false, show_featured: true, show_categories: true } },
]

function loadThemes(): void {
  router.get('/ecommerce/themes', { store_id: selectedStoreId.value }, { preserveState: true })
}

function selectTheme(t: Theme): void {
  // noop — click on card body just selects; buttons handle actions
}

function openCustomize(t: Theme): void {
  customizing.value = t
  config.value = { primary_color: '#3b82f6', secondary_color: '#64748b', font_family: 'Inter', header_layout: 'centered', show_search: true, show_featured: true, show_categories: true, ...(t.config ?? {}) }
}

async function saveConfig(): Promise<void> {
  if (!customizing.value) return
  saving.value = true
  try {
    await fetch(`/api/v1/ecommerce/themes/${customizing.value.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ config: config.value }),
    })
    customizing.value = null
    router.reload()
  } finally {
    saving.value = false
  }
}

async function activateTheme(t: Theme): Promise<void> {
  await fetch(`/api/v1/ecommerce/themes/${t.id}/activate`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  router.reload()
}

async function applyPrebuilt(t: PrebuiltTheme): Promise<void> {
  if (!selectedStoreId.value) {
    alert('Please select a store first.')
    return
  }
  const res = await fetch('/api/v1/ecommerce/themes', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ name: t.name, slug: `${t.slug}-${Date.now()}`, store_id: selectedStoreId.value, config: t.config }),
  })
  if (res.ok) {
    const { id } = await res.json()
    await fetch(`/api/v1/ecommerce/themes/${id}/activate`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    router.reload()
  }
}

async function createTheme(): Promise<void> {
  saving.value = true
  try {
    await fetch('/api/v1/ecommerce/themes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(newTheme.value),
    })
    showCreate.value = false
    router.reload()
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.theme-card {
  border: 2px solid var(--border-subtle);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
  background: var(--bg-1);
}

.theme-card:hover { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-subtle, #eff6ff); }
.theme-card--active { border-color: var(--success); }

.theme-preview {
  height: 120px;
  position: relative;
  display: flex;
  align-items: flex-end;
}

.theme-preview-lg {
  height: 160px;
  border-radius: 8px;
  overflow: hidden;
}

.theme-active-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: var(--success);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 12px;
}
</style>
