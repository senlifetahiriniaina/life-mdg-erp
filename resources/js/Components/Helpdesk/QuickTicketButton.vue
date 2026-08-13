<template>
  <div class="qt-container">
    <button
      class="wh-pill wh-pill-ticket"
      @click="isOpen = true"
      :aria-label="$t('helpdesk.quick_ticket.open')"
      :aria-pressed="isOpen"
      :title="$t('helpdesk.quick_ticket.open')"
    >
      <i class="pi pi-flag" style="font-size: 12px" aria-hidden="true" />
      <span>{{ $t('helpdesk.quick_ticket.label') }}</span>
    </button>

    <Transition name="qt-fade">
      <div v-if="isOpen" class="qt-overlay" @click.self="close">
        <div class="qt-modal" role="dialog" aria-modal="true" :aria-label="$t('helpdesk.quick_ticket.title')">
          <div class="qt-header">
            <h3 class="qt-title">{{ $t('helpdesk.quick_ticket.title') }}</h3>
            <button class="qt-close" @click="close" :aria-label="$t('common.close')">
              <i class="pi pi-times" style="font-size: 12px" />
            </button>
          </div>

          <form @submit.prevent="submit" class="qt-form">
            <div class="qt-field">
              <label class="qt-label">{{ $t('helpdesk.quick_ticket.subject') }}</label>
              <input v-model="form.subject" type="text" class="qt-input" maxlength="255" required />
            </div>
            <div class="qt-field">
              <label class="qt-label">{{ $t('helpdesk.quick_ticket.description') }}</label>
              <textarea v-model="form.description" class="qt-textarea" rows="4" />
            </div>
            <div class="qt-field">
              <label class="qt-label">{{ $t('helpdesk.quick_ticket.priority') }}</label>
              <select v-model="form.priority" class="qt-input">
                <option value="low">{{ $t('helpdesk.priority_levels.low') }}</option>
                <option value="medium">{{ $t('helpdesk.priority_levels.medium') }}</option>
                <option value="high">{{ $t('helpdesk.priority_levels.high') }}</option>
                <option value="urgent">{{ $t('helpdesk.priority_levels.urgent') }}</option>
              </select>
            </div>

            <p v-if="sourceLabel" class="qt-source-hint">
              <i class="pi pi-link" style="font-size: 11px" /> {{ sourceLabel }}
            </p>

            <p v-if="successMessage" class="qt-success">{{ successMessage }}</p>
            <p v-if="errorMessage" class="qt-error">{{ errorMessage }}</p>

            <div class="qt-actions">
              <button type="button" class="qt-btn-secondary" @click="close">{{ $t('common.cancel') }}</button>
              <button type="submit" class="qt-btn-primary" :disabled="submitting">
                {{ submitting ? $t('common.sending') : $t('helpdesk.quick_ticket.submit') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

/**
 * Global "raise an incident" entry point, embedded once in AppLayout so any
 * authenticated user can open a Helpdesk ticket from anywhere in the app —
 * this is what makes Helpdesk actually "coupled with every module" rather
 * than a standalone support desk. A page can optionally pass sourceModule/
 * sourceId (aliases registered in HelpdeskServiceProvider's morph map) to
 * link the ticket to the record currently being viewed.
 */
const props = defineProps({
  sourceModule: { type: String, default: null },
  sourceId: { type: [Number, String], default: null },
  sourceLabel: { type: String, default: '' },
})

const isOpen = ref(false)
const submitting = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const form = reactive({
  subject: '',
  description: '',
  priority: 'medium',
})

function close() {
  isOpen.value = false
  successMessage.value = ''
  errorMessage.value = ''
}

async function submit() {
  submitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload = { ...form }
    if (props.sourceModule && props.sourceId) {
      payload.source_module = props.sourceModule
      payload.source_id = props.sourceId
    }

    await axios.post('/api/v1/helpdesk/tickets', payload)

    successMessage.value = 'Ticket créé — notre équipe support va le traiter.'
    form.subject = ''
    form.description = ''
    form.priority = 'medium'
    setTimeout(close, 1500)
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Impossible de créer le ticket.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.qt-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.qt-modal {
  background: var(--bg-1, #fff);
  color: var(--fg-1, #111);
  border-radius: 12px;
  width: min(480px, 92vw);
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}
.qt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-1, #e5e7eb);
}
.qt-title { font-size: 16px; font-weight: 600; margin: 0; }
.qt-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--fg-3, #6b7280);
}
.qt-form { padding: 16px 20px 20px; display: flex; flex-direction: column; gap: 12px; }
.qt-field { display: flex; flex-direction: column; gap: 4px; }
.qt-label { font-size: 12px; font-weight: 600; color: var(--fg-2, #374151); }
.qt-input,
.qt-textarea {
  border: 1px solid var(--border-1, #d1d5db);
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 14px;
  background: var(--bg-2, #fff);
  color: inherit;
}
.qt-source-hint { font-size: 12px; color: var(--fg-3, #6b7280); display: flex; align-items: center; gap: 6px; }
.qt-success { font-size: 13px; color: #16a34a; }
.qt-error { font-size: 13px; color: #dc2626; }
.qt-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
.qt-btn-secondary,
.qt-btn-primary {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
}
.qt-btn-secondary {
  background: transparent;
  border-color: var(--border-1, #d1d5db);
  color: var(--fg-2, #374151);
}
.qt-btn-primary {
  background: #dc2626;
  color: #fff;
}
.qt-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.qt-fade-enter-active,
.qt-fade-leave-active { transition: opacity 0.15s ease; }
.qt-fade-enter-from,
.qt-fade-leave-to { opacity: 0; }
</style>
