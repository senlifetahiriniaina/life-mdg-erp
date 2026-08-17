<template>
  <AppLayout>
    <Head title="Politiques d'amortissement" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Politiques d'amortissement</h1>
          <p class="wh-page-subtitle">Règles par défaut (méthode, durée de vie, valeur résiduelle) par catégorie d'actif</p>
        </div>
        <button v-if="can('accounting', 'depreciation_policy', 'create')" class="wh-btn wh-btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> Nouvelle politique
        </button>
      </div>

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <table v-else class="wh-table">
        <thead>
          <tr><th>Nom</th><th>Catégorie</th><th>Méthode</th><th>Durée de vie</th><th>Résiduel %</th><th>Statut</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="p in items" :key="p.id">
            <td>{{ p.policy_name }}</td>
            <td>{{ p.asset_category }}</td>
            <td>{{ methodLabel(p.depreciation_method) }}</td>
            <td>{{ p.default_useful_life_years }} ans</td>
            <td>{{ p.default_residual_percentage }}%</td>
            <td><span :class="['wh-badge', p.is_active ? 'wh-badge-green' : 'wh-badge-slate']">{{ p.is_active ? 'Active' : 'Inactive' }}</span></td>
            <td class="wh-row-actions">
              <button v-if="can('accounting', 'depreciation_policy', 'update')" class="wh-row-btn" title="Modifier" @click="openEdit(p)"><i class="pi pi-pencil" /></button>
              <button v-if="can('accounting', 'depreciation_policy', 'delete')" class="wh-row-btn" title="Supprimer" @click="destroy(p)"><i class="pi pi-trash" style="color:#DC2626" /></button>
            </td>
          </tr>
          <tr v-if="items.length === 0"><td colspan="7" class="wh-empty-state">Aucune politique d'amortissement.</td></tr>
        </tbody>
      </table>
    </div>

    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier la politique' : 'Nouvelle politique'" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field"><label>Nom</label><input v-model="form.policy_name" class="wh-input" /></div>
        <div class="wh-form-field" v-if="!editing"><label>Catégorie d'actif</label><input v-model="form.asset_category" class="wh-input" /></div>
        <div class="wh-form-field" v-if="!editing">
          <label>Méthode</label>
          <select v-model="form.depreciation_method" class="wh-input">
            <option value="straight_line">Linéaire</option>
            <option value="declining_balance">Dégressif</option>
            <option value="units_of_production">Unités de production</option>
            <option value="sum_of_years">Somme des chiffres</option>
            <option value="macrs">MACRS</option>
          </select>
        </div>
        <div class="wh-form-field"><label>Durée de vie (années)</label><input v-model.number="form.default_useful_life_years" type="number" min="1" class="wh-input" /></div>
        <div class="wh-form-field"><label>Valeur résiduelle (%)</label><input v-model.number="form.default_residual_percentage" type="number" min="0" max="100" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field" v-if="!editing"><label>Date d'effet</label><input v-model="form.effective_from" type="date" class="wh-input" /></div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitForm">{{ saving ? 'Enregistrement...' : 'Enregistrer' }}</button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { useRoleAccess } from '@/composables/useRoleAccess'

const { can } = useRoleAccess()
const toast = useToast()

const items = ref([])
const loading = ref(true)
const saving = ref(false)
const showDialog = ref(false)
const editing = ref(null)

const emptyForm = () => ({
  policy_name: '', asset_category: '', depreciation_method: 'straight_line',
  default_useful_life_years: 5, default_residual_percentage: 0,
  effective_from: new Date().toISOString().slice(0, 10),
})
const form = ref(emptyForm())

const load = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/depreciation-policies')
    items.value = data.data ?? data
  } catch (e) { console.error(e) } finally { loading.value = false }
}
onMounted(load)

const openCreate = () => { editing.value = null; form.value = emptyForm(); showDialog.value = true }
const openEdit = (p) => {
  editing.value = p
  form.value = {
    policy_name: p.policy_name, default_useful_life_years: p.default_useful_life_years,
    default_residual_percentage: p.default_residual_percentage,
  }
  showDialog.value = true
}

const submitForm = async () => {
  saving.value = true
  try {
    if (editing.value) {
      await axios.put(`/api/v1/accounting/depreciation-policies/${editing.value.id}`, form.value)
      toast.add({ severity: 'success', summary: 'Modifiée', life: 3000 })
    } else {
      await axios.post('/api/v1/accounting/depreciation-policies', form.value)
      toast.add({ severity: 'success', summary: 'Créée', life: 3000 })
    }
    showDialog.value = false
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  } finally { saving.value = false }
}

const destroy = async (p) => {
  if (!confirm(`Supprimer la politique "${p.policy_name}" ?`)) return
  try {
    await axios.delete(`/api/v1/accounting/depreciation-policies/${p.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', life: 3000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  }
}

const methodLabel = (m) => ({
  straight_line: 'Linéaire', declining_balance: 'Dégressif', units_of_production: 'Unités de production',
  sum_of_years: 'Somme des chiffres', macrs: 'MACRS',
}[m] ?? m)
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-row-actions { display: flex; gap: 4px; }
.wh-row-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid #E5E7EB; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.wh-row-btn:hover { background: #F9FAFB; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-loading-state, .wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-dialog-form { display: flex; flex-direction: column; gap: 12px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-form-field label { font-size: 13px; font-weight: 500; color: #374151; }
</style>
