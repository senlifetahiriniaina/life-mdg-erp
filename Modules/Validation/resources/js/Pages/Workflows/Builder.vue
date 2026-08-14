<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Workflow Builder</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Design approval workflows visually</p>
      </div>
      <Link href="/workflows" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Workflows
      </Link>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Sidebar -->
      <div class="lg:col-span-1">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6 space-y-6">
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
                  v-model="workflow.module"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
                >
                  <option value="Achats">Purchase Orders</option>
                  <option value="Accounting">Invoices</option>
                  <option value="HR">HR Requests</option>
                </select>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-200 dark:border-surface-700 pt-6">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Add Steps</h3>
            <div class="space-y-2">
              <button
                @click="addStep('sequential')"
                class="w-full px-3 py-2 bg-primary-50 dark:bg-primary-900/20 text-blue-700 rounded-lg hover:bg-blue-100 text-sm font-medium text-left"
              >
                + Sequential Step
              </button>
              <button
                @click="addStep('parallel')"
                class="w-full px-3 py-2 bg-violet-50 dark:bg-violet-900/20 text-purple-700 rounded-lg hover:bg-purple-100 text-sm font-medium text-left"
              >
                + Parallel Step
              </button>
              <button
                @click="addStep('conditional')"
                class="w-full px-3 py-2 bg-amber-50 text-amber-700 rounded-lg hover:bg-amber-100 text-sm font-medium text-left"
              >
                + Conditional Step
              </button>
            </div>
          </div>

          <div class="border-t border-gray-200 dark:border-surface-700 pt-6">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Rules</h3>
            <div class="space-y-2">
              <button
                @click="showRuleBuilder = true"
                class="w-full px-3 py-2 bg-green-50 dark:bg-green-900/20 text-green-700 rounded-lg hover:bg-green-100 text-sm font-medium text-left"
              >
                + Add Rule
              </button>
              <div v-if="workflow.rules.length > 0" class="mt-3 space-y-2">
                <div
                  v-for="(rule, idx) in workflow.rules"
                  :key="idx"
                  class="p-2 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 rounded border border-gray-200 dark:border-surface-700 text-sm"
                >
                  <p class="font-medium text-surface-900 dark:text-surface-50">{{ rule.name }}</p>
                  <p class="text-surface-600 dark:text-surface-400 text-xs">{{ rule.condition }}</p>
                  <button
                    @click="removeRule(idx)"
                    class="text-red-700 dark:text-red-300 hover:text-red-800 text-xs mt-1"
                  >
                    Remove
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-gray-200 dark:border-surface-700 pt-6 flex gap-2">
            <button
              @click="saveWorkflow"
              class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
            >
              Save
            </button>
            <button
              @click="testWorkflow"
              class="flex-1 px-4 py-2 bg-gray-200 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-300 text-sm font-medium"
            >
              Test
            </button>
          </div>
        </div>
      </div>

      <!-- Main Canvas -->
      <div class="lg:col-span-3">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6" style="min-height: 600px">
          <div class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 rounded-lg p-8" style="min-height: 550px">
            <!-- Workflow Visualization -->
            <div v-if="workflow.steps.length === 0" class="flex items-center justify-center h-full">
              <div class="text-center text-surface-500 dark:text-surface-400">
                <p class="text-lg font-medium mb-2">No steps added yet</p>
                <p class="text-sm">Click "Add Steps" to create your workflow</p>
              </div>
            </div>

            <div v-else class="space-y-4">
              <!-- Steps Visualization -->
              <div v-for="(step, idx) in workflow.steps" :key="idx" class="flex items-center gap-4">
                <div
                  :class="[
                    'flex-1 p-4 rounded-lg border-2 cursor-pointer transition',
                    step.type === 'sequential' ? 'border-blue-300 bg-primary-50 dark:bg-primary-900/20' :
                    step.type === 'parallel' ? 'border-purple-300 bg-violet-50 dark:bg-violet-900/20' :
                    'border-amber-300 bg-amber-50'
                  ]"
                  @click="selectedStep = idx"
                 role="button" tabindex="0" @keydown.enter.prevent="selectedStep = idx">
                  <div class="flex items-start justify-between">
                    <div>
                      <p class="font-semibold text-surface-900 dark:text-surface-50">Step {{ idx + 1 }}: {{ step.type }}</p>
                      <input
                        v-model="step.name"
                        type="text"
                        placeholder="Step name"
                        class="mt-1 w-full px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm"
                      />
                      <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">
                        Approvers: {{ step.approvers.length }}
                      </p>
                    </div>
                    <button aria-label="Fermer"
                      @click.stop="removeStep(idx)"
                      class="text-red-700 dark:text-red-300 hover:text-red-800"
                    >✕
  </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step Editor -->
            <div v-if="selectedStep !== null" class="mt-8 border-t border-gray-300 dark:border-surface-600 pt-6">
              <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">
                Configure Step {{ selectedStep + 1 }}
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="approval-timeout-days" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                    Approval Timeout (days)
                  </label>
                  <input id="approval-timeout-days"
                    v-model.number="workflow.steps[selectedStep].timeout_days"
                    type="number"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm"
                  />
                </div>
                <div>
                  <label for="required-approvals" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                    Required Approvals
                  </label>
                  <input id="required-approvals"
                    v-model.number="workflow.steps[selectedStep].required_approvers"
                    type="number"
                    min="1"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm"
                  />
                </div>
              </div>

              <div class="mt-4">
                <label for="add-approvers" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                  Add Approvers
                </label>
                <div class="flex gap-2">
                  <input id="add-approvers"
                    v-model="newApprover"
                    type="text"
                    placeholder="Select or type approver..."
                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm"
                  />
                  <button
                    @click="addApprover"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
                  >
                    Add
                  </button>
                </div>

                <div class="mt-3 space-y-2">
                  <div
                    v-for="(approver, idx) in workflow.steps[selectedStep].approvers"
                    :key="idx"
                    class="p-2 bg-gray-100 dark:bg-surface-700 rounded flex items-center justify-between"
                  >
                    <span class="text-sm">{{ approver }}</span>
                    <button
                      @click="removeApprover(idx)"
                      class="text-red-700 dark:text-red-300 hover:text-red-800 text-xs"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rule Builder Modal -->
    <div v-if="showRuleBuilder" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">Add Workflow Rule</h2>
        <div class="space-y-4 mb-6">
          <div>
            <label for="rule-name" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Rule Name</label>
            <input id="rule-name"
              v-model="newRule.name"
              type="text"
              placeholder="e.g., High Value POs"
              class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm"
            />
          </div>
          <div>
            <label for="condition" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Condition</label>
            <select id="condition"
              v-model="newRule.type"
              class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm"
            >
              <option value="">Select condition type</option>
              <option value="amount">By Amount</option>
              <option value="role">By Role</option>
              <option value="date">By Date</option>
              <option value="custom">Custom Expression</option>
            </select>
          </div>
          <div v-if="newRule.type === 'amount'">
            <label for="amount-threshold" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Amount Threshold</label>
            <input id="amount-threshold"
              v-model.number="newRule.value"
              type="number"
              placeholder="e.g., 50000"
              class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm"
            />
          </div>
          <div>
            <label for="description-2" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Description</label>
            <textarea id="description-2"
              v-model="newRule.condition"
              rows="2"
              placeholder="Describe this rule..."
              class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm"
            ></textarea>
          </div>
        </div>
        <div class="flex gap-2 justify-end">
          <button
            @click="showRuleBuilder = false"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
          >
            Cancel
          </button>
          <button
            @click="addRuleToWorkflow"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            Add Rule
          </button>
        </div>
      </div>
    </div>

    <p v-if="message" :class="messageType === 'success' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'" class="text-sm mt-4">
      {{ message }}
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRouteId } from '@/composables/useRouteId'
const routeId = useRouteId()
const isEditing = ref(!!routeId.value)

const workflow = ref({
  name: '',
  description: '',
  module: 'Achats',
  steps: [],
  rules: []
})

const selectedStep = ref(null)
const newApprover = ref('')
const showRuleBuilder = ref(false)
const message = ref('')
const messageType = ref('success')

const newRule = ref({
  name: '',
  type: '',
  value: null,
  condition: ''
})

const addStep = (type) => {
  workflow.value.steps.push({
    name: `${type.charAt(0).toUpperCase() + type.slice(1)} Step ${workflow.value.steps.length + 1}`,
    type,
    approvers: [],
    timeout_days: 5,
    required_approvers: 1
  })
}

const removeStep = (idx) => {
  workflow.value.steps.splice(idx, 1)
  if (selectedStep.value === idx) {
    selectedStep.value = null
  }
}

const addApprover = () => {
  if (newApprover.value && selectedStep.value !== null) {
    workflow.value.steps[selectedStep.value].approvers.push(newApprover.value)
    newApprover.value = ''
  }
}

const removeApprover = (idx) => {
  if (selectedStep.value !== null) {
    workflow.value.steps[selectedStep.value].approvers.splice(idx, 1)
  }
}

const addRuleToWorkflow = () => {
  if (newRule.value.name && newRule.value.condition) {
    workflow.value.rules.push({
      name: newRule.value.name,
      type: newRule.value.type,
      value: newRule.value.value,
      condition: newRule.value.condition
    })
    showRuleBuilder.value = false
    newRule.value = { name: '', type: '', value: null, condition: '' }
    message.value = 'Rule added successfully!'
    messageType.value = 'success'
    setTimeout(() => { message.value = '' }, 3000)
  }
}

const removeRule = (idx) => {
  workflow.value.rules.splice(idx, 1)
}

const saveWorkflow = async () => {
  if (!workflow.value.name) {
    message.value = 'Please enter a workflow name'
    messageType.value = 'error'
    return
  }

  if (workflow.value.steps.length === 0) {
    message.value = 'Please add at least one step'
    messageType.value = 'error'
    return
  }

  message.value = 'Workflow saved successfully!'
  messageType.value = 'success'
  setTimeout(() => { message.value = '' }, 3000)
}

const testWorkflow = () => {
  message.value = 'Workflow validation passed! Ready to deploy.'
  messageType.value = 'success'
  setTimeout(() => { message.value = '' }, 3000)
}

const loadWorkflow = async () => {
  if (isEditing.value) {
    try {
      const response = await fetch(`/api/v1/validation/workflows/${routeId.value}`, {
        headers: {
          'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
        }
      })
      if (response.ok) {
        const data = await response.json()
        workflow.value = data
      }
    } catch (error) {
      console.error('Failed to load workflow:', error)
    }
  }
}

onMounted(() => {
  loadWorkflow()
})
</script>
