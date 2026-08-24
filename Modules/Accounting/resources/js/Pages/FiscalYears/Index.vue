<template>
  <AppLayout>
    <Head title="Années d'exercice" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Années d'exercice comptable</h1>
          <p class="wh-page-subtitle">Les écritures comptables se rattachent automatiquement à l'exercice couvrant leur date</p>
        </div>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <h2 class="wh-section-title">Nouvel exercice</h2>
      <div class="wh-filters">
        <input v-model="form.name" type="text" class="wh-input" placeholder="Nom (ex. Exercice 2027)" />
        <input v-model="form.start_date" type="date" class="wh-input" />
        <input v-model="form.end_date" type="date" class="wh-input" />
        <button class="wh-input wh-btn-primary" :disabled="saving" @click="create">
          {{ saving ? 'Création…' : 'Créer' }}
        </button>
      </div>
      <p v-if="feedback" class="wh-generate-feedback" :class="{ 'wh-generate-error': feedbackIsError }">{{ feedback }}</p>

      <h2 class="wh-section-title">Exercices existants</h2>
      <table class="wh-table">
        <thead><tr><th>Nom</th><th>Début</th><th>Fin</th><th>Statut</th><th></th></tr></thead>
        <tbody>
          <tr v-for="y in years" :key="y.id">
            <td>{{ y.name }}</td>
            <td>{{ y.start_date?.slice(0, 10) }}</td>
            <td>{{ y.end_date?.slice(0, 10) }}</td>
            <td>
              <span :style="{ color: y.is_closed ? '#DC2626' : '#059669' }">{{ y.is_closed ? 'Clôturé' : 'Ouvert' }}</span>
            </td>
            <td>
              <button v-if="!y.is_closed" class="wh-link-btn" @click="close(y)">Clôturer</button>
            </td>
          </tr>
          <tr v-if="years.length === 0"><td colspan="5" class="wh-empty-state">Aucun exercice enregistré.</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Accounting', 'manage_fiscal_years')

const years = ref([])
const saving = ref(false)
const feedback = ref('')
const feedbackIsError = ref(false)
const form = ref({ name: '', start_date: '', end_date: '' })

const load = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/fiscal-years')
    years.value = data.data ?? data
  } catch (e) { console.error(e) }
}

const create = async () => {
  saving.value = true
  feedback.value = ''
  try {
    await axios.post('/api/v1/accounting/fiscal-years', form.value)
    feedback.value = 'Exercice créé.'
    feedbackIsError.value = false
    form.value = { name: '', start_date: '', end_date: '' }
    await load()
  } catch (err) {
    feedback.value = err.response?.data?.message || 'Échec de la création.'
    feedbackIsError.value = true
  } finally { saving.value = false }
}

const close = async (year) => {
  try {
    await axios.post(`/api/v1/accounting/fiscal-years/${year.id}/close`)
    await load()
  } catch (e) { console.error(e) }
}

onMounted(load)
</script>

<style scoped>
.wh-page { max-width: 1000px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-btn-primary { cursor: pointer; background: #F0F9FF; border-color: #2E5BE8; color: #2E5BE8; }
.wh-link-btn { background: none; border: none; color: #2E5BE8; cursor: pointer; font-size: 13px; padding: 0; }
.wh-section-title { font-size: 15px; font-weight: 600; margin: 24px 0 12px; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
.wh-generate-feedback { font-size: 13px; color: #059669; margin-top: 8px; }
.wh-generate-error { color: #DC2626; }
</style>
