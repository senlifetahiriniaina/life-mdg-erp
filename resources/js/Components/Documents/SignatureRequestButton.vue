<template>
  <div>
    <button class="btn btn-secondary" @click="open">
      <i class="pi pi-pen-to-square" style="font-size:13px" /> Demander une signature
    </button>

    <div v-if="showModal" class="modal-overlay" @click.self="close">
      <div class="modal">
        <div class="modal-header">
          <h3>Demande de signature électronique</h3>
          <button class="btn-icon" @click="close"><i class="pi pi-times" /></button>
        </div>

        <div class="modal-body">
          <div class="form-field">
            <label class="form-label">Titre *</label>
            <input v-model="form.title" type="text" class="form-input" placeholder="Titre de la demande" />
          </div>

          <div class="form-field">
            <label class="form-label">Message (optionnel)</label>
            <textarea v-model="form.message" class="form-input" rows="3" placeholder="Message pour les signataires" />
          </div>

          <div class="form-field">
            <label class="form-label">Date d'expiration (optionnelle)</label>
            <input v-model="form.expires_at" type="date" class="form-input" />
          </div>

          <div class="signers-section">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
              <label class="form-label" style="margin:0">Signataires *</label>
              <button class="btn btn-secondary btn-sm" @click="addSigner">
                <i class="pi pi-plus" style="font-size:11px" /> Ajouter
              </button>
            </div>

            <div v-for="(signer, idx) in form.signers" :key="idx" class="signer-row">
              <span class="signer-order">{{ idx + 1 }}</span>
              <input v-model="signer.name" type="text" class="form-input" placeholder="Nom" style="flex:1" />
              <input v-model="signer.email" type="email" class="form-input" placeholder="Email" style="flex:1" />
              <button class="btn-icon btn-danger" @click="removeSigner(idx)" v-if="form.signers.length > 1">
                <i class="pi pi-trash" style="font-size:11px" />
              </button>
            </div>
          </div>

          <p v-if="error" class="error-msg">{{ error }}</p>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="close">Annuler</button>
          <button class="btn btn-primary" :disabled="loading" @click="submit">
            <i class="pi pi-send" style="font-size:12px" />
            {{ loading ? 'Envoi...' : 'Envoyer la demande' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

const props = defineProps({
  documentId: { type: Number, required: true },
})

const emit = defineEmits(['sent'])

const showModal = ref(false)
const loading   = ref(false)
const error     = ref('')

const form = reactive({
  title:      '',
  message:    '',
  expires_at: '',
  signers:    [{ name: '', email: '', order: 1 }],
})

function open() {
  showModal.value = true
}

function close() {
  showModal.value = false
  error.value = ''
}

function addSigner() {
  form.signers.push({ name: '', email: '', order: form.signers.length + 1 })
}

function removeSigner(idx) {
  form.signers.splice(idx, 1)
  form.signers.forEach((s, i) => { s.order = i + 1 })
}

async function submit() {
  if (!form.title.trim()) { error.value = 'Le titre est requis.'; return }
  if (!form.signers.every(s => s.name && s.email)) { error.value = 'Nom et email requis pour chaque signataire.'; return }

  loading.value = true
  error.value   = ''

  try {
    await axios.post(`/api/v1/documents/${props.documentId}/signature-requests`, {
      title:      form.title,
      message:    form.message || undefined,
      expires_at: form.expires_at || undefined,
      signers:    form.signers,
    })
    emit('sent')
    close()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Une erreur est survenue.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1000;
  display: flex; align-items: center; justify-content: center; padding: 24px;
}
.modal {
  background: var(--bg-canvas); border-radius: var(--r-lg); width: 100%;
  max-width: 560px; box-shadow: 0 16px 60px rgba(0,0,0,.28);
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border-subtle);
}
.modal-header h3 { margin: 0; font-size: 15px; font-weight: 600; color: var(--fg-1); }
.modal-body { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border-subtle);
}
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12px; font-weight: 500; color: var(--fg-2); }
.form-input {
  font-size: 13px; padding: 7px 10px; border: 1px solid var(--border-subtle);
  border-radius: var(--r-md); color: var(--fg-1); background: var(--bg-canvas); outline: none;
  font-family: var(--font-sans);
}
.form-input:focus { border-color: var(--halo-500); }
.signers-section { background: var(--bg-sunken); border-radius: var(--r-md); padding: 12px; }
.signer-row {
  display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
}
.signer-row:last-child { margin-bottom: 0; }
.signer-order {
  width: 22px; height: 22px; border-radius: 50%; background: var(--halo-100);
  color: var(--halo-700); font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.btn {
  font-family: var(--font-sans); font-weight: 500; font-size: 13px; padding: 7px 12px;
  border-radius: var(--r-md); border: 1px solid transparent; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px; transition: background var(--dur-base); line-height: 1.2;
}
.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
.btn-primary { background: var(--halo-500); color: #fff; }
.btn-primary:hover:not(:disabled) { background: var(--halo-700); }
.btn-secondary { background: var(--bg-canvas); color: var(--fg-1); border-color: var(--border-subtle); }
.btn-secondary:hover { background: var(--bg-sunken); }
.btn-icon {
  background: none; border: none; cursor: pointer; color: var(--fg-3);
  padding: 4px; border-radius: var(--r-sm); display: flex; align-items: center;
}
.btn-icon:hover { background: var(--bg-sunken); color: var(--fg-1); }
.btn-danger { color: var(--danger-fg); }
.btn-danger:hover { background: var(--danger-bg); }
.error-msg { color: var(--danger-fg); font-size: 13px; margin: 0; }
</style>
