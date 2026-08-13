import { ref, watch } from 'vue'
import { useDark, useToggle } from '@vueuse/core'

export const PALETTES = [
  { id: 'halo',   label: 'Halo',   color: '#2E5BE8', description: 'Bleu saphir (défaut)' },
  { id: 'ocean',  label: 'Océan',  color: '#06b6d4', description: 'Bleu cyan' },
  { id: 'violet', label: 'Violet', color: '#8b5cf6', description: 'Violet améthyste' },
  { id: 'ember',  label: 'Ember',  color: '#f97316', description: 'Orange chaleureux' },
  { id: 'jade',   label: 'Jade',   color: '#22c55e', description: 'Vert émeraude' },
  { id: 'rose',   label: 'Rose',   color: '#f43f5e', description: 'Rose rubis' },
] as const

export type PaletteId = typeof PALETTES[number]['id']

const STORAGE_KEY = 'wh-palette'

function loadPalette(): PaletteId {
  try {
    const stored = localStorage.getItem(STORAGE_KEY) as PaletteId | null
    return PALETTES.find(p => p.id === stored) ? (stored as PaletteId) : 'halo'
  } catch {
    return 'halo'
  }
}

function applyPalette(id: PaletteId) {
  const root = document.documentElement
  if (id === 'halo') {
    root.removeAttribute('data-palette')
  } else {
    root.dataset.palette = id
  }
}

const palette = ref<PaletteId>(loadPalette())
applyPalette(palette.value)

watch(palette, (id) => {
  try { localStorage.setItem(STORAGE_KEY, id) } catch { /* noop */ }
  applyPalette(id)
}, { immediate: false })

export function useTheme() {
  const isDark = useDark()
  const toggleDark = useToggle(isDark)

  function setPalette(id: PaletteId) {
    palette.value = id
  }

  return {
    palette,
    palettes: PALETTES,
    setPalette,
    isDark,
    toggleDark,
  }
}
