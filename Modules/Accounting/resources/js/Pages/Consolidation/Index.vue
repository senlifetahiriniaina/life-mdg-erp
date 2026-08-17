<template>
  <AppLayout>
    <Head title="Consolidation multi-sociétés" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Consolidation multi-sociétés</h1>
          <p class="wh-page-subtitle">Arborescence des sociétés du groupe et sous-filiales</p>
        </div>
        <button v-if="canCreate" class="wh-btn wh-btn-primary" @click="router.get('/accounting/consolidations/create')">
          <i class="pi pi-plus" /> Nouvelle société
        </button>
      </div>

      <div class="wh-filters">
        <input v-model="filters.company_type" class="wh-input" placeholder="Type (parent, subsidiary, associate)" @keyup.enter="fetchCompanies" />
        <label class="wh-checkbox">
          <input type="checkbox" v-model="filters.active_only" @change="fetchCompanies" />
          Actives uniquement
        </label>
        <button class="wh-btn wh-btn-secondary" @click="fetchCompanies">Rechercher</button>
      </div>

      <div v-if="loading" class="wh-loading-state">
        <i class="pi pi-spin pi-spinner" /> Chargement...
      </div>

      <table v-else class="wh-table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Code</th>
            <th>Société mère</th>
            <th>Type</th>
            <th>% Détention</th>
            <th>Devise</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="company in companies" :key="company.id" class="wh-row" @click="router.get(`/accounting/consolidations/${company.id}`)">
            <td>{{ company.name }}</td>
            <td><span class="wh-mono">{{ company.code }}</span></td>
            <td>{{ company.parent?.name ?? '—' }}</td>
            <td>{{ company.company_type }}</td>
            <td>{{ company.ownership_percentage }}%</td>
            <td>{{ company.currency }}</td>
            <td>
              <span :class="['wh-badge', company.is_active ? 'wh-badge-green' : 'wh-badge-slate']">
                {{ company.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td><i class="pi pi-chevron-right" style="color:#9CA3AF" /></td>
          </tr>
          <tr v-if="companies.length === 0">
            <td colspan="8" class="wh-empty-state">Aucune société trouvée. Créez-en une pour commencer.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="pagination.last_page > 1" class="wh-pagination">
        <button class="wh-btn wh-btn-secondary wh-btn-sm" :disabled="pagination.current_page <= 1" @click="fetchCompanies(pagination.current_page - 1)">Précédent</button>
        <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="wh-btn wh-btn-secondary wh-btn-sm" :disabled="pagination.current_page >= pagination.last_page" @click="fetchCompanies(pagination.current_page + 1)">Suivant</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { useRoleAccess } from '@/composables/useRoleAccess'

const { isSuperAdmin } = useRoleAccess()
const canCreate = isSuperAdmin

const companies = ref([])
const loading = ref(true)
const pagination = ref({ current_page: 1, last_page: 1 })
const filters = ref({ company_type: '', active_only: false })

const fetchCompanies = async (page = 1) => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/consolidations', {
      params: {
        page,
        company_type: filters.value.company_type || undefined,
        active_only: filters.value.active_only ? 1 : undefined,
      },
    })
    companies.value = data.data ?? data
    pagination.value = { current_page: data.current_page ?? 1, last_page: data.last_page ?? 1 }
  } catch (error) {
    console.error('Error fetching companies:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchCompanies())
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-filters { display: flex; gap: 10px; align-items: center; margin-bottom: 16px; }
.wh-checkbox { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; letter-spacing: 0.04em; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-row td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-row { cursor: pointer; }
.wh-row:hover { background: #F9FAFB; }
.wh-mono { font-family: monospace; font-size: 12px; color: #6B7280; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-loading-state, .wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
.wh-pagination { display: flex; align-items: center; gap: 12px; justify-content: center; margin-top: 16px; font-size: 13px; color: #6B7280; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.wh-btn-sm { padding: 4px 12px; font-size: 12px; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
</style>
