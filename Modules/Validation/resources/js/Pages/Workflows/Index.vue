<template>
  <AppLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Approval Workflows</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Manage and design approval workflows</p>
      </div>
      <Link href="/workflows/builder" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        + New Workflow
      </Link>
    </div>

    <!-- Module Tabs -->
    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow border-b border-gray-200 dark:border-surface-700">
      <div class="flex gap-0">
        <button
          v-for="mod in modules"
          :key="mod"
          @click="selectedModule = mod"
          :class="[
            'flex-1 px-4 py-3 text-sm font-medium border-b-2 transition',
            selectedModule === mod
              ? 'border-blue-600 text-primary-700 dark:text-primary-300'
              : 'border-transparent text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50'
          ]"
        >
          {{ mod }}
        </button>
      </div>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Workflow Name</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Module</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Steps</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Created</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && workflows.length === 0" class="border-b border-gray-200 dark:border-surface-700">
            <td colspan="6" class="px-6 py-8 text-center text-surface-500 dark:text-surface-400">
              No workflows found. <Link href="/workflows/builder" class="text-primary-700 dark:text-primary-300 hover:underline">Create one now</Link>
            </td>
          </tr>
          <tr v-for="workflow in workflows" :key="workflow.id" class="border-b border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
            <td class="px-6 py-4 text-sm font-medium text-surface-900 dark:text-surface-50">{{ workflow.name }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ workflow.module_name }}</td>
            <td class="px-6 py-4 text-sm">
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                {{ workflow.rules?.length ?? 0 }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  workflow.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ workflow.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ formatDate(workflow.created_at) }}</td>
            <td class="px-6 py-4 text-sm space-x-2">
              <Link :href="`/workflows/${workflow.id}/builder`" class="text-primary-700 dark:text-primary-300 hover:underline">
                Edit
              </Link>
              <button
                @click="toggleWorkflow(workflow)"
                :class="workflow.is_active ? 'text-yellow-700 dark:text-yellow-300' : 'text-green-700 dark:text-green-300'"
                class="hover:underline"
              >
                {{ workflow.is_active ? 'Deactivate' : 'Activate' }}
              </button>
              <button
                @click="deleteWorkflow(workflow.id)"
                class="text-red-700 dark:text-red-300 hover:underline"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Quick Templates -->
    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Quick Start Templates</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <button
          @click="createFromTemplate('po_standard')"
          class="p-4 border border-gray-200 dark:border-surface-700 rounded-lg hover:border-blue-600 hover:bg-primary-50 dark:bg-primary-900/20 transition text-left"
        >
          <p class="font-medium text-surface-900 dark:text-surface-50">Standard PO Approval</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">Manager → Approver flow</p>
        </button>
        <button
          @click="createFromTemplate('po_tiered')"
          class="p-4 border border-gray-200 dark:border-surface-700 rounded-lg hover:border-blue-600 hover:bg-primary-50 dark:bg-primary-900/20 transition text-left"
        >
          <p class="font-medium text-surface-900 dark:text-surface-50">Tiered by Amount</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">Route based on PO value</p>
        </button>
        <button
          @click="createFromTemplate('po_parallel')"
          class="p-4 border border-gray-200 dark:border-surface-700 rounded-lg hover:border-blue-600 hover:bg-primary-50 dark:bg-primary-900/20 transition text-left"
        >
          <p class="font-medium text-surface-900 dark:text-surface-50">Parallel Approval</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">All approvers simultaneously</p>
        </button>
      </div>
    </div>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const workflows = ref([])
const loading = ref(false)
const selectedModule = ref('All')
const modules = ref(['All', 'Accounting', 'Achats', 'HR', 'Inventory'])

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const loadWorkflows = async () => {
  loading.value = true
  try {
    const params = selectedModule.value !== 'All' ? { module_name: selectedModule.value } : {}
    const { data } = await axios.get('/api/v1/validation/approval-workflows', { params })
    workflows.value = data.data ?? data
  } catch (error) {
    console.error('Failed to load workflows:', error)
  } finally {
    loading.value = false
  }
}

const toggleWorkflow = async (workflow) => {
  try {
    await axios.put(`/api/v1/validation/approval-workflows/${workflow.id}`, {
      name: workflow.name,
      description: workflow.description,
      is_active: !workflow.is_active,
    })
    await loadWorkflows()
  } catch (error) {
    console.error('Failed to toggle workflow:', error)
  }
}

const deleteWorkflow = async (id) => {
  if (!confirm('Are you sure you want to delete this workflow?')) return

  try {
    await axios.delete(`/api/v1/validation/approval-workflows/${id}`)
    await loadWorkflows()
  } catch (error) {
    console.error('Failed to delete workflow:', error)
  }
}

const createFromTemplate = (template) => {
  window.location.href = `/workflows/builder?template=${template}`
}

onMounted(() => {
  loadWorkflows()
})

watch(selectedModule, loadWorkflows)
</script>
