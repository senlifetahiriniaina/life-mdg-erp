<template>
  <AppLayout>
    <Head title="Categories" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Categories
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Organize your products into categories
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            icon="pi pi-th-large"
            label="Templates de produits"
            outlined
            @click="router.visit('/inventory/product-templates')"
          />
          <Button
            icon="pi pi-plus"
            label="New Category"
            @click="openCreateModal"
          />
        </div>
      </div>

      <!-- DataTable -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="categories"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
        >
          <Column field="name" header="Name" sortable>
            <template #body="{ data }">
              <div class="font-medium text-surface-900 dark:text-surface-50">{{ data.name }}</div>
            </template>
          </Column>
          <Column field="slug" header="Slug">
            <template #body="{ data }">
              <code class="text-xs bg-surface-100 dark:bg-surface-700 px-2 py-0.5 rounded">{{ data.slug }}</code>
            </template>
          </Column>
          <Column field="description" header="Description">
            <template #body="{ data }">
              <span class="text-sm text-surface-600 dark:text-surface-300">{{ data.description ?? '—' }}</span>
            </template>
          </Column>
          <Column field="products_count" header="Products">
            <template #body="{ data }">
              <Tag :value="String(data.products_count ?? 0)" severity="info" />
            </template>
          </Column>
          <Column header="Actions" style="width: 8rem">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  icon="pi pi-pencil"
                  outlined
                  size="small"
                  @click="editCategory(data)"
                />
                <Button
                  icon="pi pi-trash"
                  outlined
                  severity="danger"
                  size="small"
                  @click="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              No categories found.
            </div>
          </template>
        </DataTable>

        <!-- Pagination -->
        <div
          v-if="pagination.total > pagination.per_page"
          class="flex items-center justify-between p-4 border-t border-surface-200 dark:border-surface-700"
        >
          <span class="text-sm text-surface-500">
            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }}
          </span>
          <Paginator
            :rows="pagination.per_page"
            :total-records="pagination.total"
            :first="(pagination.current_page - 1) * pagination.per_page"
            @page="onPageChange"
          />
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingCategory ? 'Edit Category' : 'New Category'"
      modal
      class="w-full max-w-lg"
    >
      <form class="space-y-4" @submit.prevent="submitCategory">
        <div class="grid grid-cols-1 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
              Name <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="categoryForm.name"
              :class="{ 'p-invalid': categoryErrors.name }"
              placeholder="Category name"
            />
            <small v-if="categoryErrors.name" class="text-red-500">{{ categoryErrors.name }}</small>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Description</label>
            <Textarea
              v-model="categoryForm.description"
              rows="3"
              placeholder="Optional description"
            />
            <small v-if="categoryErrors.description" class="text-red-500">{{ categoryErrors.description }}</small>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-surface-200 dark:border-surface-700">
          <Button
            type="button"
            label="Cancel"
            outlined
            @click="showModal = false"
          />
          <Button
            type="submit"
            :label="editingCategory ? 'Update Category' : 'Create Category'"
            :loading="submitting"
          />
        </div>
      </form>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Category {
  id: number
  name: string
  slug: string
  description: string | null
  products_count: number
}

interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

const confirm = useConfirm()

const categories = ref<Category[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingCategory = ref<Category | null>(null)
const submitting = ref(false)

const pagination = reactive<Pagination>({
  current_page: 1,
  per_page: 25,
  total: 0,
  last_page: 1,
})

const categoryForm = reactive({
  name: '',
  description: '',
})

const categoryErrors = reactive<Record<string, string>>({})

// Chantier 19 Lot 4: this page's POST/PUT/DELETE fetch() calls sent no CSRF
// token at all — unlike axios (used elsewhere in this app), which reads the
// XSRF-TOKEN cookie and attaches X-XSRF-TOKEN automatically, a raw fetch()
// does nothing on its own. Confirmed empirically (curl against a real
// php artisan serve instance, real login session, real Referer header
// matching config('sanctum.stateful')) that every create/update/delete on
// this page returned 419 "CSRF token mismatch" — Pest tests can never catch
// this, since VerifyCsrfToken::runningUnitTests() unconditionally bypasses
// the check whenever app()->runningUnitTests() is true. Same fix pattern
// already used by Shipments/Index.vue's own getCsrf() helper.
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

const fetchCategories = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(pagination.per_page))

    const response = await fetch(`/api/v1/inventory/categories?${params}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    categories.value = data.data
    pagination.current_page = data.meta?.current_page ?? 1
    pagination.total = data.meta?.total ?? data.data.length
    pagination.last_page = data.meta?.last_page ?? 1
  } finally {
    loading.value = false
  }
}

const onPageChange = (event: { page: number }) => fetchCategories(event.page + 1)

const openCreateModal = () => {
  editingCategory.value = null
  categoryForm.name = ''
  categoryForm.description = ''
  Object.keys(categoryErrors).forEach(k => delete categoryErrors[k])
  showModal.value = true
}

const editCategory = (category: Category) => {
  editingCategory.value = category
  categoryForm.name = category.name
  categoryForm.description = category.description ?? ''
  Object.keys(categoryErrors).forEach(k => delete categoryErrors[k])
  showModal.value = true
}

const confirmDelete = (category: Category) => {
  confirm.require({
    message: `Are you sure you want to delete "${category.name}"?`,
    header: 'Delete Category',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/inventory/categories/${category.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      })
      fetchCategories(pagination.current_page)
    },
  })
}

const submitCategory = async () => {
  submitting.value = true
  Object.keys(categoryErrors).forEach(k => delete categoryErrors[k])

  const url = editingCategory.value
    ? `/api/v1/inventory/categories/${editingCategory.value.id}`
    : '/api/v1/inventory/categories'

  const method = editingCategory.value ? 'PUT' : 'POST'

  try {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrf(),
      },
      body: JSON.stringify({ ...categoryForm }),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        Object.assign(categoryErrors, data.errors)
      }
      return
    }

    showModal.value = false
    fetchCategories(pagination.current_page)
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchCategories()
})
</script>
