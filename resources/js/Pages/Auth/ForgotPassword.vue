<template>
  <div class="auth-shell">
    <main class="auth-card">
      <header class="auth-logo">
        <div class="auth-logo-icon">W</div>
        <h1 class="auth-title">WideHalo ERP</h1>
        <p class="auth-subtitle">Réinitialisez votre mot de passe</p>
      </header>

      <div v-if="props.status" class="auth-alert auth-alert-success" role="status">{{ props.status }}</div>

      <p id="form-help" style="font-size:13px;color:var(--fg-3);margin-bottom:20px">
        Entrez votre adresse email et nous vous enverrons un lien de réinitialisation.
      </p>

      <form @submit.prevent="submit" class="auth-form" novalidate aria-describedby="form-help">
        <div class="form-field">
          <label for="email" class="form-label">Email</label>
          <InputText
            id="email"
            v-model="form.email"
            type="email"
            class="wh-input"
            placeholder="vous@entreprise.com"
            :invalid="!!form.errors.email"
            :aria-invalid="!!form.errors.email"
            :aria-describedby="form.errors.email ? 'email-error' : undefined"
            aria-label="Adresse email"
            autocomplete="email"
          />
          <small v-if="form.errors.email" id="email-error" class="form-error" role="alert">{{ form.errors.email }}</small>
        </div>

        <button type="submit" class="btn-auth" :disabled="form.processing" :aria-busy="form.processing" aria-label="Envoyer le lien de réinitialisation">
          <i v-if="form.processing" class="pi pi-spin pi-spinner" style="font-size:14px" aria-hidden="true" />
          <i v-else class="pi pi-send" style="font-size:14px" aria-hidden="true" />
          Envoyer le lien
        </button>
      </form>

      <div style="text-align:center;margin-top:20px">
        <Link :href="route('login')" style="font-size:13px;color:var(--halo-600,#2E5BE8)">← Retour à la connexion</Link>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'

const props = defineProps<{
  status?: string
}>()

const form = useForm({
  email: '',
})

const submit = () => {
  form.post(route('password.email'))
}
</script>

<style scoped>
.auth-shell { min-height:100vh; background:var(--bg-app,#F7F8FB); display:flex; align-items:center; justify-content:center; padding:24px; }
.auth-card { width:100%; max-width:400px; background:var(--bg-canvas,#fff); border:1px solid var(--border-subtle,#D7DCE7); border-radius:var(--r-xl,16px); padding:40px; box-shadow:var(--shadow-md); }
.auth-logo { text-align:center; margin-bottom:24px; }
.auth-logo-icon { width:56px; height:56px; background:linear-gradient(135deg,var(--halo-500,#2E5BE8),var(--halo-700,#1A3AA3)); border-radius:var(--r-lg,12px); display:inline-flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:22px; font-family:var(--font-display); margin-bottom:16px; }
.auth-title { margin:0; font-family:var(--font-display); font-size:24px; font-weight:700; letter-spacing:-0.02em; color:var(--fg-1); }
.auth-subtitle { margin:6px 0 0; font-size:14px; color:var(--fg-3); }
.auth-form { display:flex; flex-direction:column; gap:18px; }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.form-error { font-size:12px; color:var(--danger-fg,#991B1B); }
.wh-input { width:100%; padding:9px 12px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:14px; color:var(--fg-1); outline:none; transition:border-color var(--dur-base); }
.wh-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.auth-alert-success { background:var(--success-bg,#D1FAE5); color:var(--success-fg,#065F46); border-radius:var(--r-md); padding:10px 14px; font-size:13px; margin-bottom:16px; }
.btn-auth { width:100%; padding:10px 16px; background:var(--halo-500); color:#fff; border:none; border-radius:var(--r-md); font-family:var(--font-sans); font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:8px; transition:background var(--dur-base); }
.btn-auth:hover:not(:disabled) { background:var(--halo-700); }
.btn-auth:disabled { opacity:0.6; cursor:not-allowed; }
</style>
