<template>
  <div class="sign-page">
    <!-- Loading -->
    <div v-if="loading" class="sign-center">
      <div class="spinner" />
      <p style="margin-top:16px;color:var(--fg-2)">Chargement...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="sign-center">
      <i class="pi pi-times-circle" style="font-size:48px;color:var(--danger-fg)" />
      <h2 style="margin:16px 0 8px">Lien invalide</h2>
      <p style="color:var(--fg-2)">{{ error }}</p>
    </div>

    <!-- Already signed -->
    <div v-else-if="alreadySigned" class="sign-center">
      <i class="pi pi-check-circle" style="font-size:64px;color:var(--success-fg)" />
      <h2 style="margin:16px 0 8px;color:var(--fg-1)">Document déjà signé</h2>
      <p style="color:var(--fg-2)">Vous avez déjà signé ce document. Merci !</p>
    </div>

    <!-- Thank you screen -->
    <div v-else-if="signed" class="sign-center">
      <i class="pi pi-check-circle" style="font-size:64px;color:var(--success-fg)" />
      <h2 style="margin:16px 0 8px;color:var(--fg-1)">Signature enregistrée</h2>
      <p style="color:var(--fg-2)">Merci {{ signerName }}, votre signature a été enregistrée avec succès.</p>
    </div>

    <!-- Sign screen -->
    <div v-else-if="data" class="sign-layout">
      <!-- Left: Document preview -->
      <div class="doc-preview-pane">
        <div class="preview-header">
          <i class="pi pi-file-pdf" style="font-size:16px;color:var(--danger-fg)" />
          <span>{{ data.request?.title }}</span>
        </div>
        <iframe
          v-if="data.document_url"
          :src="data.document_url"
          class="doc-iframe"
          frameborder="0"
        />
        <div v-else class="no-preview">
          <i class="pi pi-file" style="font-size:32px;color:var(--fg-4)" />
          <p>Prévisualisation non disponible</p>
        </div>
      </div>

      <!-- Right: Signature pad -->
      <div class="signature-pane">
        <div class="signature-header">
          <h2>Signer le document</h2>
          <p style="color:var(--fg-2);font-size:13px;margin:4px 0 0">{{ data.request?.title }}</p>
        </div>

        <div class="signer-info">
          <i class="pi pi-user" style="color:var(--fg-3)" />
          <div>
            <div style="font-weight:500;font-size:13px">{{ data.signer?.name }}</div>
            <div style="font-size:12px;color:var(--fg-3)">{{ data.signer?.email }}</div>
          </div>
        </div>

        <div class="canvas-wrap">
          <canvas
            ref="canvas"
            width="480"
            height="200"
            class="signature-canvas"
            @mousedown="startDraw"
            @mousemove="draw"
            @mouseup="stopDraw"
            @mouseleave="stopDraw"
            @touchstart.prevent="startDrawTouch"
            @touchmove.prevent="drawTouch"
            @touchend="stopDraw"
          />
          <div v-if="!hasDrawn" class="canvas-placeholder">
            Dessinez votre signature ici
          </div>
        </div>

        <div class="canvas-actions">
          <button class="btn btn-secondary" @click="clearSignature">
            <i class="pi pi-eraser" style="font-size:12px" /> Effacer
          </button>
        </div>

        <p v-if="signError" class="error-msg">{{ signError }}</p>

        <button class="btn btn-primary btn-block" :disabled="!hasDrawn || submitting" @click="submitSignature">
          <i class="pi pi-check" style="font-size:13px" />
          {{ submitting ? 'Signature en cours...' : 'Signer le document' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  token: { type: String, required: true },
})

const loading      = ref(true)
const error        = ref('')
const data         = ref(null)
const signed       = ref(false)
const alreadySigned = ref(false)
const signerName   = ref('')
const submitting   = ref(false)
const signError    = ref('')
const canvas       = ref(null)
const hasDrawn     = ref(false)
const isDrawing    = ref(false)

let ctx = null
let lastX = 0
let lastY = 0

onMounted(async () => {
  try {
    const res = await axios.get(`/api/v1/documents/sign/${props.token}`)
    if (res.data.status === 'signed') {
      alreadySigned.value = true
    } else {
      data.value = res.data
      signerName.value = res.data.signer?.name || ''
    }
  } catch (err) {
    error.value = err?.response?.data?.message || 'Ce lien de signature est invalide ou a expiré.'
  } finally {
    loading.value = false
  }

  if (canvas.value) {
    ctx = canvas.value.getContext('2d')
    ctx.strokeStyle = '#1a1a2e'
    ctx.lineWidth = 2
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
  }
})

function getPos(e, element) {
  const rect = element.getBoundingClientRect()
  const scaleX = element.width / rect.width
  const scaleY = element.height / rect.height
  return {
    x: (e.clientX - rect.left) * scaleX,
    y: (e.clientY - rect.top) * scaleY,
  }
}

function startDraw(e) {
  isDrawing.value = true
  const pos = getPos(e, canvas.value)
  lastX = pos.x
  lastY = pos.y
}

function draw(e) {
  if (!isDrawing.value || !ctx) return
  const pos = getPos(e, canvas.value)
  ctx.beginPath()
  ctx.moveTo(lastX, lastY)
  ctx.lineTo(pos.x, pos.y)
  ctx.stroke()
  lastX = pos.x
  lastY = pos.y
  hasDrawn.value = true
}

function stopDraw() {
  isDrawing.value = false
}

function startDrawTouch(e) {
  const touch = e.touches[0]
  isDrawing.value = true
  const pos = getPos(touch, canvas.value)
  lastX = pos.x
  lastY = pos.y
}

function drawTouch(e) {
  if (!isDrawing.value || !ctx) return
  const touch = e.touches[0]
  const pos = getPos(touch, canvas.value)
  ctx.beginPath()
  ctx.moveTo(lastX, lastY)
  ctx.lineTo(pos.x, pos.y)
  ctx.stroke()
  lastX = pos.x
  lastY = pos.y
  hasDrawn.value = true
}

function clearSignature() {
  if (ctx && canvas.value) {
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
    hasDrawn.value = false
  }
}

async function submitSignature() {
  if (!hasDrawn.value) return

  submitting.value = true
  signError.value  = ''

  try {
    const signatureData = canvas.value.toDataURL('image/png')

    await axios.post(`/api/v1/documents/sign/${props.token}`, {
      signature_data: signatureData,
      position: { page: 1, x: 0, y: 0, width: 0, height: 0 },
    })

    signed.value = true
  } catch (err) {
    signError.value = err?.response?.data?.message || 'Erreur lors de la soumission de la signature.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
* { box-sizing: border-box; }

.sign-page {
  min-height: 100vh;
  background: var(--bg-sunken, #f5f6f8);
  font-family: var(--font-sans, system-ui, sans-serif);
}

.sign-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 32px;
  text-align: center;
}

.sign-layout {
  display: flex;
  height: 100vh;
}

.doc-preview-pane {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-right: 1px solid #e5e7eb;
}

.preview-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  border-bottom: 1px solid #e5e7eb;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.doc-iframe {
  flex: 1;
  width: 100%;
  border: none;
}

.no-preview {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #9ca3af;
}

.signature-pane {
  width: 420px;
  flex-shrink: 0;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  overflow-y: auto;
}

.signature-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}

.signer-info {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #f9fafb;
  border-radius: 8px;
}

.canvas-wrap {
  position: relative;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  overflow: hidden;
  background: #fafafa;
}

.signature-canvas {
  width: 100%;
  cursor: crosshair;
  display: block;
}

.canvas-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 14px;
  pointer-events: none;
}

.canvas-actions {
  display: flex;
  justify-content: flex-end;
}

.btn {
  font-family: inherit;
  font-weight: 500;
  font-size: 14px;
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid transparent;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: background 0.15s;
  line-height: 1.2;
}
.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn-primary  { background: #4f46e5; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #4338ca; }
.btn-secondary { background: #fff; color: #374151; border-color: #d1d5db; }
.btn-secondary:hover { background: #f9fafb; }
.btn-block { width: 100%; justify-content: center; }

.error-msg { color: #dc2626; font-size: 13px; margin: 0; }

.spinner {
  width: 36px; height: 36px;
  border: 3px solid #e5e7eb;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
