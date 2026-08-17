<template>
  <AppLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Workflow Builder</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Design approval workflows: an ordered list of rules, each with a trigger condition and an approval mode</p>
      </div>
      <Link href="/workflows" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Workflows
      </Link>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Sidebar -->
      <div class="lg:col-span-1">
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6 space-y-6">
          <div>
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Workflow Details</h3>
            <div class="space-y-4">
              <div>
                <label for="name" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Name</label>
                <input id="name"
                  v-model="workflow.name"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
                  placeholder="e.g., PO Approval Flow"
                />
              </div>
              <div>
                <label for="description" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Description</label>
                <textarea id="description"
                  v-model="workflow.description"
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
                  placeholder="Describe the workflow..."
                ></textarea>
              </div>
              <div>
                <label for="module" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Module</label>
                <select id="module"
                  v-model="workflow.module_name"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
                >
                  <option value="Achats">Achats</option>
                  <option value="Accounting">Accounting</option>
                  <option value="HR">HR</option>
                  <option value="Inventory">Inventory</option>
                </select>
              </div>
              <label v-if="isEditing" class="flex items-center gap-2 text-sm text-surface-700 dark:text-surface-300">
                <input v-model="workflow.is_active" type="checkbox" />
                Active
              </label>
            </div>
          </div>

          <div class="border-t border-gray-200 dark:border-surface-700 pt-6">
            <button
              @click="openRuleBuilder()"
              class="w-full px-3 py-2 bg-primary-50 dark:bg-primary-900/20 text-blue-700 rounded-lg hover:bg-blue-100 text-sm font-medium text-left"
            >
              + Add Rule
            </button>
          </div>

          <div class="border-t border-gray-200 dark:border-surface-700 pt-6">
            <button
              @click="saveWorkflow"
              :disabled="saving"
              class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Main Canvas -->
      <div class="lg:col-span-3">
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow p-6" style="min-height: 400px">
          <div v-if="workflow.rules.length === 0" class="flex items-center justify-center" style="min-height: 350px">
            <div class="text-center text-surface-500 dark:text-surface-400">
              <p class="text-lg font-medium mb-2">No rules added yet</p>
              <p class="text-sm">Click "Add Rule" to define when and how this workflow approves</p>
            </div>
          </div>

          <div v-else class="space-y-4">
            <div v-for="(rule, idx) in workflow.rules" :key="rule.id ?? `new-${idx}`" class="p-4 rounded-lg border-2 border-blue-200">
              <div class="flex items-start justify-between">
                <div>
                  <p class="font-semibold text-surface-900 dark:text-surface-50">
                    Rule {{ idx + 1 }}: {{ rule.condition_type }} {{ rule.condition_operator }} {{ rule.condition_value }}
                  </p>
                  <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">
                    {{ rule.approval_mode }} · {{ rule.required_approvers_count }} approver(s) required
                    <span v-if="rule.hierarchy_id"> · via {{ hierarchyName(rule.hierarchy_id) }}</span>
                  </p>
                </div>
                <div class="flex gap-2">
                  <button @click="openRuleBuilder(idx)" class="text-primary-700 dark:text-primary-300 hover:underline text-sm">Edit</button>
                  <button @click="removeRule(idx)" class="text-red-700 dark:text-red-300 hover:underline text-sm">Remove</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rule Builder Modal -->
    <div v-if="showRuleBuilder" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">
          {{ editingRuleIndex === null ? 'Add Rule' : 'Edit Rule' }}
        </h2>
        <div class="space-y-4 mb-6">
          <div>
            <label for="condition-type" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Condition type</label>
            <select id="condition-type" v-model="ruleForm.condition_type" class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm">
              <option value="amount">Amount</option>
              <option value="category">Category</option>
              <option value="department">Department</option>
              <option value="custom_field">Custom field</option>
            </select>
          </div>
          <div v-if="ruleForm.condition_type === 'custom_field'">
            <label for="condition-field" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Field name</label>
            <input id="condition-field" v-model="ruleForm.condition_field" type="text" class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="condition-operator" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Operator</label>
              <select id="condition-operator" v-model="ruleForm.condition_operator" class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm">
                <option v-for="op in ['>', '<', '>=', '<=', '==', '!=']" :key="op" :value="op">{{ op }}</option>
              </select>
            </div>
            <div>
              <label for="condition-value" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Value</label>
              <input id="condition-value" v-model="ruleForm.condition_value" type="text" placeholder="e.g., 50000" class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="approval-mode" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Approval mode</label>
              <select id="approval-mode" v-model="ruleForm.approval_mode" class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm">
                <option value="sequential">Sequential</option>
                <option value="parallel">Parallel</option>
              </select>
            </div>
            <div>
              <label for="required-approvals" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Required approvals</label>
              <input id="required-approvals" v-model.number="ruleForm.required_approvers_count" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm" />
            </div>
          </div>
          <div>
            <label for="hierarchy" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Approver hierarchy (optional)</label>
            <select id="hierarchy" v-model="ruleForm.hierarchy_id" class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm">
              <option :value="null">— None —</option>
              <option v-for="h in hierarchies" :key="h.id" :value="h.id">{{ h.name }}</option>
            </select>
          </div>
        </div>
        <div class="flex gap-2 justify-end">
          <button @click="showRuleBuilder = false" class="px-4 py-2 border border-gray-300 dark:border-surface-600 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-800">
            Cancel
          </button>
          <button @click="confirmRule" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            {{ editingRuleIndex === null ? 'Add Rule' : 'Update Rule' }}
          </button>
        </div>
      </div>
    </div>

    <p v-if="message" :class="messageType === 'success' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'" class="text-sm mt-4">
      {{ message }}
    </p>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useRouteId } from '@/composables/useRouteId'

const routeId = useRouteId()
const isEditing = ref(!!routeId.value)
const saving = ref(false)

const workflow = ref({
  name: '',
  description: '',
  module_name: 'Achats',
  is_active: true,
  rules: [],
})

const hierarchies = ref([])
const showRuleBuilder = ref(false)
const editingRuleIndex = ref(null)
const message = ref('')
const messageType = ref('success')

const emptyRuleForm = () => ({
  condition_type: 'amount',
  condition_field: null,
  condition_operator: '>',
  condition_value: '',
  approval_mode: 'sequential',
  required_approvers_count: 1,
  hierarchy_id: null,
})

const ruleForm = ref(emptyRuleForm())

const hierarchyName = (id) => hierarchies.value.find(h => h.id === id)?.name ?? `#${id}`

const openRuleBuilder = (idx = null) => {
  editingRuleIndex.value = idx
  ruleForm.value = idx === null ? emptyRuleForm() : { ...workflow.value.rules[idx] }
  showRuleBuilder.value = true
}

const confirmRule = () => {
  if (editingRuleIndex.value === null) {
    workflow.value.rules.push({ ...ruleForm.value })
  } else {
    workflow.value.rules[editingRuleIndex.value] = { ...workflow.value.rules[editingRuleIndex.value], ...ruleForm.value }
  }
  showRuleBuilder.value = false
}

const removeRule = async (idx) => {
  const rule = workflow.value.rules[idx]
  if (rule.id && isEditing.value) {
    try {
      await axios.delete(`/api/v1/validation/approval-workflows/${routeId.value}/rules/${rule.id}`)
    } catch (error) {
      console.error('Failed to delete rule:', error)
      return
    }
  }
  workflow.value.rules.splice(idx, 1)
}

const loadHierarchies = async () => {
  try {
    const { data } = await axios.get('/api/v1/validation/approval-hierarchies')
    hierarchies.value = data.data ?? data
  } catch {
    hierarchies.value = []
  }
}

const loadWorkflow = async () => {
  if (!isEditing.value) return
  try {
    const { data } = await axios.get(`/api/v1/validation/approval-workflows/${routeId.value}`)
    workflow.value = { ...data, rules: data.rules ?? [] }
  } catch (error) {
    console.error('Failed to load workflow:', error)
  }
}

const saveWorkflow = async () => {
  if (!workflow.value.name) {
    message.value = 'Please enter a workflow name'
    messageType.value = 'error'
    return
  }

  saving.value = true
  try {
    let workflowId = routeId.value
    if (isEditing.value) {
      await axios.put(`/api/v1/validation/approval-workflows/${workflowId}`, {
        name: workflow.value.name,
        description: workflow.value.description,
        is_active: workflow.value.is_active,
      })
    } else {
      const { data } = await axios.post('/api/v1/validation/approval-workflows', {
        name: workflow.value.name,
        description: workflow.value.description,
        module_name: workflow.value.module_name,
      })
      workflowId = data.id
    }

    for (const rule of workflow.value.rules) {
      const payload = {
        condition_type: rule.condition_type,
        condition_operator: rule.condition_operator,
        condition_value: rule.condition_value,
        condition_field: rule.condition_field,
        required_approvers_count: rule.required_approvers_count,
        approval_mode: rule.approval_mode,
        hierarchy_id: rule.hierarchy_id,
      }
      if (rule.id) {
        await axios.put(`/api/v1/validation/approval-workflows/${workflowId}/rules/${rule.id}`, payload)
      } else {
        await axios.post(`/api/v1/validation/approval-workflows/${workflowId}/rules`, payload)
      }
    }

    message.value = 'Workflow saved successfully!'
    messageType.value = 'success'
    if (!isEditing.value) {
      router.visit(`/workflows/${workflowId}/builder`)
    } else {
      await loadWorkflow()
    }
  } catch (error) {
    message.value = error.response?.data?.message ?? 'Failed to save workflow'
    messageType.value = 'error'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadHierarchies()
  loadWorkflow()
})
</script>
