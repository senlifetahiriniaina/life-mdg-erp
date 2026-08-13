<template>
  <div v-if="visible" class="doc-viewer-overlay" @click.self="close">
    <div class="doc-viewer-modal">
      <div class="doc-viewer-header">
        <span class="doc-viewer-title">{{ document?.title || 'Document' }}</span>
        <div class="doc-viewer-actions">
          <button class="btn btn-secondary" @click="zoom -= 0.2" v-if="viewerType === 'image'" title="Zoom out">
            <i class="pi pi-minus" style="font-size:12px" />
          </button>
          <button class="btn btn-secondary" @click="zoom += 0.2" v-if="viewerType === 'image'" title="Zoom in">
            <i class="pi pi-plus" style="font-size:12px" />
          </button>
          <a :href="downloadUrl" class="btn btn-secondary" title="Download">
            <i class="pi pi-download" style="font-size:12px" /> Télécharger
          </a>
          <button class="btn btn-secondary" @click="close" title="Close">
            <i class="pi pi-times" style="font-size:13px" />
          </button>
        </div>
      </div>

      <div class="doc-viewer-body">
        <!-- PDF viewer -->
        <iframe
          v-if="viewerType === 'pdf'"
          :src="streamUrl"
          class="doc-viewer-iframe"
          frameborder="0"
        />

        <!-- Image viewer -->
        <div v-else-if="viewerType === 'image'" class="doc-viewer-image-wrap">
          <img
            :src="streamUrl"
            :style="{ transform: `scale(${zoom})`, transformOrigin: 'top center' }"
            class="doc-viewer-image"
            alt="Document preview"
          />
        </div>

        <!-- Video viewer -->
        <div v-else-if="viewerType === 'video'" class="doc-viewer-video-wrap">
          <video :src="streamUrl" controls class="doc-viewer-video" />
        </div>

        <!-- Office / unsupported -->
        <div v-else class="doc-viewer-unsupported">
          <i class="pi pi-file" style="font-size:48px;color:var(--fg-4);margin-bottom:16px" />
          <p style="color:var(--fg-2);margin-bottom:20px">Ce type de fichier ne peut pas être prévisualisé dans le navigateur.</p>
          <a :href="downloadUrl" class="btn btn-primary">
            <i class="pi pi-download" style="font-size:13px" /> Télécharger pour visualiser
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  document: { type: Object, default: null },
  visible:  { type: Boolean, default: false },
})

const emit = defineEmits(['update:visible'])

const zoom = ref(1)

const viewerType = computed(() => {
  if (!props.document) return 'unsupported'
  const mime = props.document.mime_type || ''
  if (mime === 'application/pdf') return 'pdf'
  if (mime.startsWith('image/')) return 'image'
  if (mime.startsWith('video/')) return 'video'
  if (mime.includes('word') || mime.includes('excel') || mime.includes('powerpoint') || mime.includes('spreadsheet') || mime.includes('presentation')) return 'office'
  return 'unsupported'
})

const streamUrl = computed(() => {
  if (!props.document) return ''
  return `/api/v1/documents/${props.document.id}/preview/stream`
})

const downloadUrl = computed(() => {
  if (!props.document) return ''
  return `/api/v1/documents/${props.document.id}/download`
})

function close() {
  zoom.value = 1
  emit('update:visible', false)
}
</script>

<style scoped>
.doc-viewer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.doc-viewer-modal {
  background: var(--bg-canvas);
  border-radius: var(--r-lg);
  width: 100%;
  max-width: 960px;
  height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
}

.doc-viewer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border-subtle);
  flex-shrink: 0;
}

.doc-viewer-title {
  font-weight: 600;
  font-size: 14px;
  color: var(--fg-1);
}

.doc-viewer-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.doc-viewer-body {
  flex: 1;
  overflow: auto;
  display: flex;
  flex-direction: column;
}

.doc-viewer-iframe {
  width: 100%;
  height: 100%;
  border: none;
  flex: 1;
}

.doc-viewer-image-wrap {
  overflow: auto;
  flex: 1;
  padding: 24px;
  display: flex;
  justify-content: center;
}

.doc-viewer-image {
  max-width: 100%;
  height: auto;
  transition: transform 0.2s ease;
}

.doc-viewer-video-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #000; /* explicit black for video player bg */
}

.doc-viewer-video {
  max-width: 100%;
  max-height: 100%;
}

.doc-viewer-unsupported {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px;
}

.btn {
  font-family: var(--font-sans);
  font-weight: 500;
  font-size: 13px;
  padding: 7px 12px;
  border-radius: var(--r-md);
  border: 1px solid transparent;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background var(--dur-base);
  line-height: 1.2;
  text-decoration: none;
}

.btn-primary  { background: var(--halo-500); color: #fff; }
.btn-primary:hover { background: var(--halo-700); }
.btn-secondary { background: var(--bg-canvas); color: var(--fg-1); border-color: var(--border-subtle); }
.btn-secondary:hover { background: var(--bg-sunken); }
</style>
