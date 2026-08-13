<template>
  <AppLayout>
    <Toast />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Profil</h1>
        <p class="wh-page-subtitle">Gérez vos informations personnelles et votre mot de passe.</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <!-- Profile Information -->
      <div class="wh-panel" style="padding:24px">
        <h3 style="margin:0 0 20px;font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--fg-1)">Informations du profil</h3>
        <form @submit.prevent="submitProfile" style="display:flex;flex-direction:column;gap:16px">
          <div class="form-field">
            <label class="form-label">Nom</label>
            <InputText v-model="profileForm.name" class="wh-input w-full" placeholder="Votre nom" :invalid="!!profileForm.errors.name" autocomplete="name" />
            <small v-if="profileForm.errors.name" class="form-error">{{ profileForm.errors.name }}</small>
          </div>
          <div class="form-field">
            <label class="form-label">Email</label>
            <InputText v-model="profileForm.email" type="email" class="wh-input w-full" placeholder="vous@entreprise.com" :invalid="!!profileForm.errors.email" autocomplete="email" />
            <small v-if="profileForm.errors.email" class="form-error">{{ profileForm.errors.email }}</small>
          </div>
          <div style="display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary" :disabled="profileForm.processing">
              <i v-if="profileForm.processing" class="pi pi-spin pi-spinner" style="font-size:13px" />
              <i v-else class="pi pi-check" style="font-size:13px" />
              Enregistrer
            </button>
          </div>
        </form>
      </div>

      <!-- Change Password -->
      <div class="wh-panel" style="padding:24px">
        <h3 style="margin:0 0 20px;font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--fg-1)">Changer le mot de passe</h3>
        <form @submit.prevent="submitPassword" style="display:flex;flex-direction:column;gap:16px">
          <div class="form-field">
            <label class="form-label">Mot de passe actuel</label>
            <Password v-model="passwordForm.current_password" class="wh-input-wrap" inputClass="wh-input" :feedback="false" :invalid="!!passwordForm.errors.current_password" autocomplete="current-password" toggleMask />
            <small v-if="passwordForm.errors.current_password" class="form-error">{{ passwordForm.errors.current_password }}</small>
          </div>
          <div class="form-field">
            <label class="form-label">Nouveau mot de passe</label>
            <Password v-model="passwordForm.password" class="wh-input-wrap" inputClass="wh-input" :invalid="!!passwordForm.errors.password" autocomplete="new-password" toggleMask />
            <small v-if="passwordForm.errors.password" class="form-error">{{ passwordForm.errors.password }}</small>
          </div>
          <div class="form-field">
            <label class="form-label">Confirmer le mot de passe</label>
            <Password v-model="passwordForm.password_confirmation" class="wh-input-wrap" inputClass="wh-input" :feedback="false" autocomplete="new-password" toggleMask />
          </div>
          <div style="display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary" :disabled="passwordForm.processing">
              <i v-if="passwordForm.processing" class="pi pi-spin pi-spinner" style="font-size:13px" />
              <i v-else class="pi pi-lock" style="font-size:13px" />
              Modifier
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Toast from 'primevue/toast'

const props = defineProps<{
  user: { id: number; name: string; email: string }
}>()

const page = usePage()
const toast = useToast()

const profileForm = useForm({ name: props.user.name, email: props.user.email })
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' })

const submitProfile = () => {
  profileForm.patch(route('profile.update'), {
    onSuccess: () => toast.add({ severity: 'success', summary: 'Succès', detail: 'Profil mis à jour.', life: 3000 }),
  })
}

const submitPassword = () => {
  passwordForm.patch(route('profile.password'), {
    onSuccess: () => {
      passwordForm.reset()
      toast.add({ severity: 'success', summary: 'Succès', detail: 'Mot de passe modifié.', life: 3000 })
    },
  })
}

onMounted(() => {
  const flash = page.props.flash as Record<string, string> | undefined
  if (flash?.success) {
    toast.add({ severity: 'success', summary: 'Succès', detail: flash.success, life: 3000 })
  }
})
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.form-error { font-size:12px; color:var(--danger-fg,#991B1B); }
.wh-input { width:100%; padding:9px 12px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:14px; color:var(--fg-1); outline:none; }
.wh-input-wrap { width:100%; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
</style>
