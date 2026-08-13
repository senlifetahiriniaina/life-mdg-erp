<template>
  <div class="receipt-printer">
    <!-- Print Preview Dialog -->
    <Dialog v-model:visible="showPreview" header="Aperçu ticket" :modal="true" :style="{ width: '420px' }">
      <div class="receipt-preview-wrap">
        <iframe
          ref="receiptFrame"
          :srcdoc="receiptHtml"
          class="receipt-iframe"
          title="Ticket de caisse"
        />
      </div>
      <template #footer>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button class="btn btn-ghost" @click="showPreview = false">Fermer</button>
          <button class="btn btn-secondary" :disabled="!printerUrl" @click="sendToPrinter">
            <i class="pi pi-wifi" style="font-size:13px" />
            Envoyer au terminal
          </button>
          <button class="btn btn-primary" @click="printWindow">
            <i class="pi pi-print" style="font-size:13px" />
            Imprimer
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Print Button -->
    <button class="btn btn-secondary btn-sm" :disabled="loading" @click="openPreview">
      <i v-if="loading" class="pi pi-spin pi-spinner" style="font-size:12px" />
      <i v-else class="pi pi-print" style="font-size:12px" />
      {{ $t('pos.receipt.print_button') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Dialog from 'primevue/dialog'


const props = defineProps<{
  orderId: number
  printerUrl?: string
}>()

const showPreview  = ref(false)
const receiptHtml  = ref('')
const loading      = ref(false)
const receiptFrame = ref<HTMLIFrameElement | null>(null)

const csrf = () => (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? ''

const openPreview = async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/v1/pos/orders/${props.orderId}/receipt/html`, {
      headers: { Accept: 'text/html' },
    })
    if (res.ok) {
      receiptHtml.value = await res.text()
      showPreview.value = true
    }
  } catch {
    // silently degrade
  } finally {
    loading.value = false
  }
}

const printWindow = () => {
  const frame = receiptFrame.value
  if (!frame?.contentWindow) return
  frame.contentWindow.focus()
  frame.contentWindow.print()
}

const sendToPrinter = async () => {
  if (!props.printerUrl) return
  try {
    await fetch(`/api/v1/pos/orders/${props.orderId}/receipt/print`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
      },
      body: JSON.stringify({ printer_url: props.printerUrl }),
    })
  } catch {
    // silently degrade
  }
}
</script>

<style scoped>
.receipt-preview-wrap {
  width: 100%;
  display: flex;
  justify-content: center;
  background: var(--bg-subtle);
  padding: 12px;
  border-radius: 6px;
}

.receipt-iframe {
  width: 80mm;
  height: 400px;
  border: 1px solid var(--border-subtle);
  background: #fff;
  border-radius: 4px;
}

.btn {
  font-family: var(--font-sans);
  font-weight: 500;
  font-size: 14px;
  padding: 8px 14px;
  border-radius: var(--r-md);
  border: 1px solid transparent;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: background var(--dur-base);
  line-height: 1.2;
}
.btn-sm { font-size: 12px; padding: 5px 10px; }
.btn-primary { background: var(--halo-500); color: #fff; }
.btn-primary:hover:not(:disabled) { background: var(--halo-700); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: var(--bg-canvas); color: var(--fg-1); border-color: var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background: var(--bg-sunken); }
.btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ghost { background: transparent; color: var(--fg-2); border-color: var(--border-subtle); }
.btn-ghost:hover { background: var(--bg-sunken); }
</style>
