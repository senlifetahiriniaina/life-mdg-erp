<script setup lang="ts">
import { ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { useTheme, PALETTES } from '@/composables/useTheme'

const { palette, setPalette, isDark, toggleDark } = useTheme()
const open = ref(false)
const root = ref<HTMLElement | null>(null)
onClickOutside(root, () => { open.value = false })
</script>

<template>
  <div ref="root" class="theme-sw">
    <!-- Trigger button -->
    <button
      class="wh-icon-btn theme-sw-trigger"
      :title="'Thème : ' + palette"
      @click="open = !open"
    >
      <span
        class="palette-dot"
        :style="`background:${PALETTES.find(p => p.id === palette)?.color ?? 'var(--halo-500)'}`"
      />
    </button>

    <!-- Dropdown panel -->
    <div v-if="open" class="theme-sw-panel">
      <!-- Dark/Light toggle -->
      <div class="theme-sw-section">
        <div class="theme-sw-label">Apparence</div>
        <div class="theme-sw-row">
          <button
            :class="['theme-mode-btn', !isDark ? 'active' : '']"
            @click="isDark && toggleDark()"
          >
            <i class="pi pi-sun" style="font-size:14px" />
            Clair
          </button>
          <button
            :class="['theme-mode-btn', isDark ? 'active' : '']"
            @click="!isDark && toggleDark()"
          >
            <i class="pi pi-moon" style="font-size:14px" />
            Sombre
          </button>
        </div>
      </div>

      <!-- Palette swatches -->
      <div class="theme-sw-section">
        <div class="theme-sw-label">Couleur principale</div>
        <div class="theme-sw-palettes">
          <button
            v-for="p in PALETTES"
            :key="p.id"
            :class="['palette-swatch', palette === p.id ? 'active' : '']"
            :style="`--sw-color:${p.color}`"
            :title="p.description"
            @click="setPalette(p.id); open = false"
          >
            <span class="swatch-circle" />
            <span class="swatch-label">{{ p.label }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.theme-sw { position: relative; }

.palette-dot {
  display: block; width: 12px; height: 12px;
  border-radius: 50%; flex-shrink: 0;
}

.theme-sw-panel {
  position: absolute; right: 0; top: calc(100% + 8px);
  width: 220px; background: var(--bg-canvas);
  border: 1px solid var(--border-subtle); border-radius: var(--r-lg);
  box-shadow: var(--shadow-lg); z-index: var(--z-overlay);
  padding: 12px; display: flex; flex-direction: column; gap: 14px;
}

.theme-sw-section { display: flex; flex-direction: column; gap: 8px; }

.theme-sw-label {
  font-size: 11px; font-weight: 600; letter-spacing: 0.05em;
  text-transform: uppercase; color: var(--fg-3);
}

.theme-sw-row { display: flex; gap: 6px; }

.theme-mode-btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 7px 10px; border-radius: var(--r-md);
  border: 1px solid var(--border-subtle); background: transparent;
  font-family: var(--font-sans); font-size: 12px; font-weight: 500;
  color: var(--fg-2); cursor: pointer;
  transition: background var(--dur-base), color var(--dur-base), border-color var(--dur-base);
}
.theme-mode-btn:hover { background: var(--bg-sunken); color: var(--fg-1); }
.theme-mode-btn.active {
  background: var(--halo-50); border-color: var(--halo-300); color: var(--halo-700); font-weight: 600;
}

.theme-sw-palettes { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }

.palette-swatch {
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding: 8px 4px; border-radius: var(--r-md);
  border: 2px solid transparent; background: transparent;
  cursor: pointer; font-family: var(--font-sans);
  transition: background var(--dur-fast), border-color var(--dur-fast);
}
.palette-swatch:hover { background: var(--bg-sunken); }
.palette-swatch.active { border-color: var(--halo-500); background: var(--halo-50); }

.swatch-circle {
  display: block; width: 22px; height: 22px; border-radius: 50%;
  background: var(--sw-color);
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.swatch-label { font-size: 10px; font-weight: 500; color: var(--fg-2); white-space: nowrap; }
</style>
