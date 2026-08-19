<template>
  <AppLayout>
    <Head title="Templates de produits" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Templates de produits
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Catalogue de modèles réutilisables (matières premières, accessoires, semi-fini, produits finis) —
            utilisé pour pré-remplir la création de produit.
          </p>
        </div>
        <Button icon="pi pi-plus" label="Nouveau template" @click="openCreateModal" />
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="p-4 border-b border-surface-200 dark:border-surface-700 flex items-center gap-3">
          <Select
            v-model="familyFilter"
            :options="familyOptions"
            option-label="label"
            option-value="value"
            placeholder="Toutes les familles"
            show-clear
            class="w-64"
            @change="fetchTemplates"
          />
          <label class="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
            <Checkbox v-model="includeInactive" binary @change="fetchTemplates" />
            Inclure les templates désactivés
          </label>
        </div>

        <DataTable :value="templates" :loading="loading" striped-rows class="p-datatable-sm">
          <Column field="name" header="Nom">
            <template #body="{ data }">
              <div class="font-medium">{{ data.name }}</div>
              <code class="text-xs text-surface-400">{{ data.code }}</code>
            </template>
          </Column>
          <Column header="Famille">
            <template #body="{ data }">
              <Tag :value="families[data.family] ?? data.family" severity="info" />
            </template>
          </Column>
          <Column header="Catégorie">
            <template #body="{ data }">
              {{ data.category?.name ?? '—' }}
            </template>
          </Column>
          <Column header="Unité">
            <template #body="{ data }">
              {{ data.unit ? `${data.unit.name} (${data.unit.symbol})` : '—' }}
            </template>
          </Column>
          <Column header="Statut">
            <template #body="{ data }">
              <Tag :value="data.is_active ? 'Actif' : 'Désactivé'" :severity="data.is_active ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column header="Actions" style="width: 8rem">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-pencil" outlined size="small" @click="editTemplate(data)" />
                <Button icon="pi pi-trash" outlined severity="danger" size="small" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">Aucun template.</div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog v-model:visible="showModal" :header="editingTemplate ? 'Modifier le template' : 'Nouveau template'" modal class="w-full max-w-2xl">
      <form class="space-y-4" @submit.prevent="submitTemplate">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Code <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.code" :class="{ 'p-invalid': errors.code }" placeholder="ex. mp-tissu-lin" />
            <small v-if="errors.code" class="text-red-500">{{ errors.code }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Nom <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.name" :class="{ 'p-invalid': errors.name }" placeholder="ex. Tissu lin" />
            <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Famille <span class="text-red-500">*</span>
            </label>
            <Select v-model="form.family" :options="familyOptions" option-label="label" option-value="value" class="w-full" :class="{ 'p-invalid': errors.family }" />
            <small v-if="errors.family" class="text-red-500">{{ errors.family }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Catégorie <span class="text-red-500">*</span>
            </label>
            <Select v-model="form.category_id" :options="categories" option-label="name" option-value="id" filter class="w-full" :class="{ 'p-invalid': errors.category_id }" />
            <small v-if="errors.category_id" class="text-red-500">{{ errors.category_id }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Unité suggérée</label>
            <Select v-model="form.unit_id" :options="units" option-label="name" option-value="id" show-clear class="w-full" />
          </div>

          <div class="flex items-center gap-2 mt-6">
            <Checkbox v-model="form.is_active" binary input-id="is-active" />
            <label for="is-active" class="text-sm text-surface-700 dark:text-surface-200">Actif</label>
          </div>

          <div class="flex flex-col gap-1 md:col-span-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Description</label>
            <Textarea v-model="form.description" rows="2" />
          </div>

          <div class="flex flex-col gap-1 md:col-span-2">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Attributs par défaut (JSON, optionnel)
            </label>
            <Textarea v-model="attributesText" rows="3" placeholder='{"composition": null, "gsm_min": 250}' />
            <small v-if="errors.default_attributes" class="text-red-500">{{ errors.default_attributes }}</small>
          </div>
        </div>

        <div v-if="formError" class="rounded-md bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
          {{ formError }}
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-surface-200 dark:border-surface-700">
          <Button type="button" label="Annuler" outlined @click="showModal = false" />
          <Button type="submit" :label="editingTemplate ? 'Mettre à jour' : 'Créer'" :loading="submitting" />
        </div>
      </form>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'

interface ProductTemplate {
  id: number
  code: string
  name: string
  family: string
  category_id: number
  unit_id: number | null
  description: string | null
  default_attributes: Record<string, unknown> | null
  is_active: boolean
  category?: { id: number, name: string }
  unit?: { id: number, name: string, symbol: string }
}

const confirm = useConfirm()

const templates = ref<ProductTemplate[]>([])
const categories = ref<Array<{ id: number, name: string }>>([])
const units = ref<Array<{ id: number, name: string, symbol: string }>>([])
const families = ref<Record<string, string>>({})
const loading = ref(false)
const familyFilter = ref<string | null>(null)
const includeInactive = ref(false)

const showModal = ref(false)
const editingTemplate = ref<ProductTemplate | null>(null)
const submitting = ref(false)
const formError = ref<string | null>(null)
const errors = reactive<Record<string, string>>({})
const attributesText = ref('')

const familyOptions = computed(() =>
  Object.entries(families.value).map(([value, label]) => ({ value, label }))
)

const form = reactive({
  code: '',
  name: '',
  family: 'matiere_premiere',
  category_id: null as number | null,
  unit_id: null as number | null,
  description: '',
  is_active: true,
})

const fetchTemplates = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (familyFilter.value) params.set('family', familyFilter.value)
    if (includeInactive.value) params.set('include_inactive', '1')

    const { data } = await axios.get(`/api/v1/inventory/product-templates?${params}`)
    templates.value = data.data ?? []
    families.value = data.families ?? {}
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  const { data } = await axios.get('/api/v1/inventory/categories?per_page=200')
  categories.value = data.data ?? []
}

const fetchUnits = async () => {
  const { data } = await axios.get('/api/v1/inventory/units?per_page=200')
  units.value = data.data ?? []
}

const resetForm = () => {
  form.code = ''
  form.name = ''
  form.family = 'matiere_premiere'
  form.category_id = null
  form.unit_id = null
  form.description = ''
  form.is_active = true
  attributesText.value = ''
  Object.keys(errors).forEach(k => delete errors[k])
  formError.value = null
}

const openCreateModal = () => {
  editingTemplate.value = null
  resetForm()
  showModal.value = true
}

const editTemplate = (template: ProductTemplate) => {
  editingTemplate.value = template
  form.code = template.code
  form.name = template.name
  form.family = template.family
  form.category_id = template.category_id
  form.unit_id = template.unit_id
  form.description = template.description ?? ''
  form.is_active = template.is_active
  attributesText.value = template.default_attributes ? JSON.stringify(template.default_attributes, null, 2) : ''
  Object.keys(errors).forEach(k => delete errors[k])
  formError.value = null
  showModal.value = true
}

const confirmDelete = (template: ProductTemplate) => {
  confirm.require({
    message: `Supprimer le template "${template.name}" ? Cette action est définitive.`,
    header: 'Supprimer le template',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await axios.delete(`/api/v1/inventory/product-templates/${template.id}`)
      fetchTemplates()
    },
  })
}

const submitTemplate = async () => {
  submitting.value = true
  formError.value = null
  Object.keys(errors).forEach(k => delete errors[k])

  let defaultAttributes = null
  if (attributesText.value.trim()) {
    try {
      defaultAttributes = JSON.parse(attributesText.value)
    } catch {
      errors.default_attributes = 'JSON invalide.'
      submitting.value = false
      return
    }
  }

  const payload = { ...form, default_attributes: defaultAttributes }

  try {
    if (editingTemplate.value) {
      await axios.put(`/api/v1/inventory/product-templates/${editingTemplate.value.id}`, payload)
    } else {
      await axios.post('/api/v1/inventory/product-templates', payload)
    }

    showModal.value = false
    fetchTemplates()
  } catch (e) {
    if (e.response?.data?.errors) {
      Object.assign(errors, e.response.data.errors)
    } else {
      formError.value = e.response?.data?.message ?? "Impossible d'enregistrer ce template."
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchTemplates()
  fetchCategories()
  fetchUnits()
})
</script>
