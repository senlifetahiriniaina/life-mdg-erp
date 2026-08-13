<template>
  <div class="barcode-scanner">
    <div style="display:flex;gap:8px;align-items:center">
      <div style="position:relative;flex:1">
        <i class="pi pi-barcode" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:14px;pointer-events:none" />
        <input
          ref="inputRef"
          v-model="barcodeInput"
          type="text"
          :placeholder="placeholder"
          class="wh-filter-input"
          style="padding-left:32px"
          @keydown.enter.prevent="onScan"
          @paste="onPaste"
        />
      </div>
      <button class="btn btn-secondary" style="flex-shrink:0" @click="onScan">
        <i class="pi pi-search" style="font-size:13px" /> Lookup
      </button>
    </div>
    <p v-if="hint" style="font-size:11px;color:var(--fg-4);margin-top:4px">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const props = withDefaults(defineProps<{
  placeholder?: string
  hint?: string
  autoFocus?: boolean
}>(), {
  placeholder: 'Scan barcode or enter code...',
  hint: 'Use a barcode scanner or type manually and press Enter',
  autoFocus: false,
})

const emit = defineEmits<{
  (e: 'scanned', barcode: string): void
}>()

const barcodeInput = ref('')
const inputRef = ref<HTMLInputElement | null>(null)

onMounted(() => {
  if (props.autoFocus && inputRef.value) {
    inputRef.value.focus()
  }
})

function onScan() {
  const code = barcodeInput.value.trim()
  if (!code) return
  emit('scanned', code)
  barcodeInput.value = ''
}

function onPaste(event: ClipboardEvent) {
  // Some barcode scanners paste quickly and may not trigger keydown.enter
  // Schedule a scan check after paste
  setTimeout(() => {
    if (barcodeInput.value.trim()) {
      onScan()
    }
  }, 50)
}

function focus() {
  inputRef.value?.focus()
}

defineExpose({ focus })
</script>
