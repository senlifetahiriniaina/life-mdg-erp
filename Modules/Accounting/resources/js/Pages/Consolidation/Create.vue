<template>
  <AppLayout>
    <Head title="Nouvelle société de consolidation" />

    <div class="wh-page">
      <h1 class="wh-page-title">Nouvelle société de consolidation</h1>
      <p class="wh-page-subtitle">Ajoute une société (mère ou filiale) à l'arborescence de consolidation.</p>

      <form class="wh-form" @submit.prevent="submitForm">
        <div class="wh-form-row">
          <div class="wh-form-field">
            <label for="cf-name">Nom</label>
            <input id="cf-name" v-model="form.name" class="wh-input" required />
          </div>
          <div class="wh-form-field">
            <label for="cf-code">Code</label>
            <input id="cf-code" v-model="form.code" class="wh-input" required maxlength="20" />
            <span v-if="errors.code" class="wh-field-error">{{ errors.code }}</span>
          </div>
        </div>

        <div class="wh-form-row">
          <div class="wh-form-field">
            <label for="cf-parent">Société mère (optionnel)</label>
            <select id="cf-parent" v-model="form.parent_company_id" class="wh-input">
              <option :value="null">— Aucune (société de tête) —</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }} ({{ company.code }})
              </option>
            </select>
          </div>
          <div class="wh-form-field">
            <label for="cf-type">Type</label>
            <select id="cf-type" v-model="form.company_type" class="wh-input">
              <option value="parent">Société mère</option>
              <option value="subsidiary">Filiale</option>
              <option value="associate">Société associée</option>
            </select>
          </div>
        </div>

        <div class="wh-form-row">
          <div class="wh-form-field">
            <label for="cf-ownership">% Détention</label>
            <input id="cf-ownership" v-model.number="form.ownership_percentage" type="number" min="0" max="100" step="0.01" class="wh-input" />
          </div>
          <div class="wh-form-field">
            <label for="cf-currency">Devise</label>
            <input id="cf-currency" v-model="form.currency" class="wh-input" maxlength="3" placeholder="XOF" />
          </div>
          <div class="wh-form-field">
            <label for="cf-fy">Mois de début d'exercice</label>
            <input id="cf-fy" v-model.number="form.fiscal_year_start_month" type="number" min="1" max="12" class="wh-input" />
          </div>
        </div>

        <label class="wh-checkbox">
          <input type="checkbox" v-model="form.is_active" />
          Société active
        </label>

        <div class="wh-form-actions">
          <button type="button" class="wh-btn wh-btn-secondary" @click="router.get('/accounting/consolidations')">Annuler</button>
          <button type="submit" class="wh-btn wh-btn-primary" :disabled="submitting">
            {{ submitting ? 'Création...' : 'Créer la société' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const toast = useToast()
const submitting = ref(false)
const companies = ref([])
const errors = reactive({})

const form = ref({
  name: '',
  code: '',
  parent_company_id: null,
  company_type: 'subsidiary',
  ownership_percentage: 100,
  currency: 'XOF',
  fiscal_year_start_month: 1,
  is_active: true,
})

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/consolidations', { params: { active_only: 1 } })
    companies.value = data.data ?? data
  } catch {
    companies.value = []
  }
})

const submitForm = async () => {
  Object.keys(errors).forEach(k => delete errors[k])
  submitting.value = true
  try {
    const { data } = await axios.post('/api/v1/accounting/consolidations', form.value)
    toast.add({ severity: 'success', summary: 'Créée', detail: 'Société de consolidation créée', life: 3000 })
    router.get(`/accounting/consolidations/${data.id}`)
  } catch (e) {
    if (e.response?.status === 422) {
      Object.assign(errors, e.response.data.errors ?? {})
    }
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la création', life: 4000 })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.wh-page { max-width: 700px; margin: 0 auto; padding: 24px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin: 0 0 24px; }
.wh-form { display: flex; flex-direction: column; gap: 16px; border: 1px solid #E5E7EB; border-radius: 10px; padding: 24px; background: #fff; }
.wh-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-form-field label { font-size: 13px; font-weight: 500; color: #374151; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; width: 100%; }
.wh-checkbox { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; }
.wh-field-error { font-size: 11px; color: #EF4444; }
.wh-form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
</style>
