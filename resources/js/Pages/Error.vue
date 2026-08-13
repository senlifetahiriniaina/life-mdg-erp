<template>
  <div class="error-shell">
    <div class="error-content">
      <div class="error-code font-display">{{ props.status }}</div>
      <h1 class="error-title">{{ title }}</h1>
      <p class="error-desc">{{ description }}</p>
      <div class="error-actions">
        <button class="btn btn-primary" @click="goToDashboard">
          <i class="pi pi-th-large" style="font-size:13px" /> Tableau de bord
        </button>
        <button class="btn btn-secondary" @click="goBack">
          <i class="pi pi-arrow-left" style="font-size:13px" /> Retour
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  status: number
}>()

const title = computed(() => {
  switch (props.status) {
    case 403: return 'Accès refusé'
    case 404: return 'Page introuvable'
    case 500: return 'Erreur serveur'
    case 503: return 'Maintenance en cours'
    default:  return 'Une erreur est survenue'
  }
})

const description = computed(() => {
  switch (props.status) {
    case 403: return "Vous n'avez pas les droits nécessaires pour accéder à cette page."
    case 404: return "La page que vous recherchez n'existe pas ou a été déplacée."
    case 500: return "Nous avons été notifiés et travaillons à résoudre le problème. Réessayez plus tard."
    case 503: return "L'application est temporairement en maintenance. Revenez bientôt."
    default:  return "Une erreur inattendue s'est produite. Veuillez réessayer."
  }
})

const goToDashboard = () => router.visit('/dashboard')
const goBack = () => window.history.back()
</script>

<style scoped>
.error-shell { min-height:100vh; background:var(--bg-app,#F7F8FB); display:flex; align-items:center; justify-content:center; padding:24px; }
.error-content { text-align:center; max-width:480px; }
.error-code { font-size:96px; font-weight:800; color:var(--halo-500,#2E5BE8); letter-spacing:-0.04em; line-height:1; margin-bottom:16px; }
.error-title { margin:0 0 8px; font-family:var(--font-display); font-size:24px; font-weight:700; color:var(--fg-1); }
.error-desc { margin:0 0 32px; font-size:15px; color:var(--fg-3); line-height:1.6; }
.error-actions { display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:9px 16px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
</style>
