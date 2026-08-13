<template>
  <div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Approval Request</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">{{ approval.request_number }}</p>
      </div>
      <Link href="/approvals" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Approvals
      </Link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <p class="text-surface-600 dark:text-surface-400">Loading...</p>
    </div>

    <div v-else-if="approval.id" class="space-y-6">
      <!-- Status Bar -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Current Status</h2>
            <span
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium',
                statusClasses[approval.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
              ]"
            >
              {{ formatStatus(approval.status) }}
            </span>
          </div>
          <div class="flex gap-2">
            <button
              v-if="approval.status === 'pending' && approval.awaiting_my_action"
              @click="openApprovalModal"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
            >
              Approve
            </button>
            <button
              v-if="approval.status === 'pending' && approval.awaiting_my_action"
              @click="openRejectionModal"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm"
            >
              Reject
            </button>
          </div>
        </div>
      </div>

      <!-- Request Details -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Request Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Request Number</p>
            <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ approval.request_number }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Module</p>
            <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ approval.module }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Requester</p>
            <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ approval.requester_name }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Amount</p>
            <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ approval.amount || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Submitted Date</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ formatDate(approval.created_at) }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Due Date</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ approval.due_date ? formatDate(approval.due_date) : 'No deadline' }}</p>
          </div>
        </div>
      </div>

      <!-- Approval Workflow -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Approval Workflow</h3>
        <div class="space-y-4">
          <div v-for="(step, idx) in approval.approval_steps" :key="idx" class="relative pb-6">
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0">
                <div
                  :class="[
                    'flex items-center justify-center h-10 w-10 rounded-full text-white text-sm font-semibold',
                    step.status === 'approved' ? 'bg-green-50 dark:bg-green-900/200' :
                    step.status === 'rejected' ? 'bg-red-50 dark:bg-red-900/200' :
                    step.status === 'pending' ? 'bg-yellow-50 dark:bg-yellow-900/200' : 'bg-gray-300'
                  ]"
                >
                  {{ idx + 1 }}
                </div>
              </div>
              <div class="flex-1">
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ step.approver_name || 'Pending Approver' }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400">{{ step.approver_role || 'Role not specified' }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">
                  {{ step.status === 'approved' ? '✓ Approved' :
                     step.status === 'rejected' ? '✗ Rejected' :
                     '⏳ Pending' }}
                  {{ step.decision_date ? `on ${formatDate(step.decision_date)}` : '' }}
                </p>
                <p v-if="step.comments" class="text-sm text-surface-700 dark:text-surface-300 mt-2 italic">{{ step.comments }}</p>
              </div>
            </div>
            <div v-if="idx < approval.approval_steps.length - 1" class="absolute left-5 top-10 h-6 w-0.5 bg-gray-300"></div>
          </div>
        </div>
      </div>

      <!-- Request Details Content -->
      <div v-if="approval.details" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Additional Details</h3>
        <div class="prose prose-sm max-w-none">
          <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ approval.details }}</p>
        </div>
      </div>

      <!-- Approval History -->
      <div v-if="approval.history && approval.history.length > 0" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Decision History</h3>
        <div class="space-y-4">
          <div v-for="record in approval.history" :key="record.id" class="p-4 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 rounded-lg border border-gray-200 dark:border-surface-700">
            <div class="flex items-start justify-between mb-2">
              <p class="font-medium text-surface-900 dark:text-surface-50">{{ record.approver_name }}</p>
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  record.action === 'approved' ? 'bg-green-100 text-green-800' :
                  record.action === 'rejected' ? 'bg-red-100 text-red-800' :
                  'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatAction(record.action) }}
              </span>
            </div>
            <p class="text-sm text-surface-600 dark:text-surface-400">{{ formatDate(record.created_at) }}</p>
            <p v-if="record.comments" class="text-sm text-surface-700 dark:text-surface-300 mt-2">{{ record.comments }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Approval Modal -->
    <div v-if="showApprovalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">Approve Request</h2>
        <div class="mb-4">
          <label for="comments-optional" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Comments (Optional)</label>
          <textarea id="comments-optional"
            v-model="approvalComments"
            rows="3"
            placeholder="Add any comments about your approval..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
          ></textarea>
        </div>
        <div class="flex gap-2 justify-end">
          <button
            @click="closeModal"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
          >
            Cancel
          </button>
          <button
            @click="submitApproval"
            :disabled="submitting"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
          >
            {{ submitting ? 'Approving...' : 'Approve' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Rejection Modal -->
    <div v-if="showRejectionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">Reject Request</h2>
        <div class="mb-4">
          <label for="reason" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Reason *</label>
          <textarea id="reason"
            v-model="rejectionReason"
            rows="3"
            placeholder="Please provide a reason for rejection..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
            required
          ></textarea>
        </div>
        <div class="flex gap-2 justify-end">
          <button
            @click="closeModal"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
          >
            Cancel
          </button>
          <button
            @click="submitRejection"
            :disabled="submitting || !rejectionReason"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
          >
            {{ submitting ? 'Rejecting...' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-700 dark:text-red-300 mt-4">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRoute } from 'vue-router'

const route = useRoute()
const approval = ref({})
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const showApprovalModal = ref(false)
const showRejectionModal = ref(false)
const approvalComments = ref('')
const rejectionReason = ref('')

const statusClasses = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  delegated: 'bg-blue-100 text-blue-800'
}

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const formatAction = (action) => {
  return action.charAt(0).toUpperCase() + action.slice(1)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const loadApprovalRequest = async () => {
  try {
    const response = await fetch(`/api/v1/validation/approval-requests/${route.params.id}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      approval.value = await response.json()
    } else {
      error.value = 'Failed to load approval request'
    }
  } catch (err) {
    console.error('Failed to load approval:', err)
    error.value = 'An error occurred while loading the approval request'
  } finally {
    loading.value = false
  }
}

const openApprovalModal = () => {
  showApprovalModal.value = true
  approvalComments.value = ''
}

const openRejectionModal = () => {
  showRejectionModal.value = true
  rejectionReason.value = ''
}

const closeModal = () => {
  showApprovalModal.value = false
  showRejectionModal.value = false
  approvalComments.value = ''
  rejectionReason.value = ''
}

const submitApproval = async () => {
  submitting.value = true
  try {
    const response = await fetch(`/api/v1/validation/approval-requests/${route.params.id}/approve`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        comments: approvalComments.value
      })
    })
    if (response.ok) {
      closeModal()
      loadApprovalRequest()
    } else {
      error.value = 'Failed to approve request'
    }
  } catch (err) {
    console.error('Failed to approve:', err)
    error.value = 'An error occurred while approving'
  } finally {
    submitting.value = false
  }
}

const submitRejection = async () => {
  submitting.value = true
  try {
    const response = await fetch(`/api/v1/validation/approval-requests/${route.params.id}/reject`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        reason: rejectionReason.value
      })
    })
    if (response.ok) {
      closeModal()
      loadApprovalRequest()
    } else {
      error.value = 'Failed to reject request'
    }
  } catch (err) {
    console.error('Failed to reject:', err)
    error.value = 'An error occurred while rejecting'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadApprovalRequest()
})
</script>
