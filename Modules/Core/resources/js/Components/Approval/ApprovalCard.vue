<template>
  <div class="approval-card" :class="{ 'is-urgent': isUrgent }">
    <div class="card-header">
      <h3>{{ approval.workflow_name }}</h3>
      <span class="badge" :class="statusClass">{{ approval.status }}</span>
    </div>

    <div class="card-body">
      <div class="approval-details">
        <p><strong>Subject:</strong> {{ approval.subject_type }} #{{ approval.subject_id }}</p>
        <p><strong>Initiator:</strong> {{ approval.initiated_by_name }}</p>
        <p><strong>Current Step:</strong> {{ approval.current_step }} of {{ totalSteps }}</p>
        <p v-if="approval.deadline" class="deadline" :class="{ 'is-overdue': isOverdue }">
          <strong>Deadline:</strong> {{ formatDate(approval.deadline) }}
        </p>
      </div>

      <div v-if="approval.status === 'pending'" class="decision-section">
        <textarea
          v-model="comment"
          placeholder="Add a comment (optional)"
          class="form-control"
          rows="3"
        ></textarea>

        <div class="button-group">
          <button
            class="btn btn-success"
            :disabled="isSubmitting"
            @click="submitDecision('approved')"
          >
            {{ isSubmitting ? 'Processing...' : 'Approve' }}
          </button>
          <button
            class="btn btn-danger"
            :disabled="isSubmitting"
            @click="submitDecision('rejected')"
          >
            {{ isSubmitting ? 'Processing...' : 'Reject' }}
          </button>
          <button
            v-if="canEscalate"
            class="btn btn-warning"
            :disabled="isSubmitting"
            @click="submitDecision('escalated')"
          >
            {{ isSubmitting ? 'Processing...' : 'Escalate' }}
          </button>
        </div>

        <div v-if="submitError" class="alert alert-danger">{{ submitError }}</div>
      </div>

      <div v-else class="decision-history">
        <h4>Decision History</h4>
        <div v-for="decision in approval.decisions" :key="decision.id" class="decision-item">
          <p>
            <strong>{{ decision.approver_name }}</strong> - {{ decision.decision }}
          </p>
          <p v-if="decision.comment" class="comment">{{ decision.comment }}</p>
          <p class="timestamp">{{ formatDate(decision.decided_at) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ApprovalCard',
  props: {
    approval: {
      type: Object,
      required: true,
      validator(obj) {
        return (
          obj.id &&
          obj.workflow_name &&
          obj.status &&
          obj.current_step &&
          obj.initiated_by_name
        )
      },
    },
    canEscalate: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      comment: '',
      isSubmitting: false,
      submitError: null,
    }
  },
  computed: {
    totalSteps() {
      return this.approval.total_steps || 1
    },
    statusClass() {
      const statuses = {
        pending: 'badge-warning',
        approved: 'badge-success',
        rejected: 'badge-danger',
        escalated: 'badge-info',
      }
      return statuses[this.approval.status] || 'badge-secondary'
    },
    isUrgent() {
      if (!this.approval.deadline) return false
      const now = new Date()
      const deadline = new Date(this.approval.deadline)
      const hoursLeft = (deadline - now) / (1000 * 60 * 60)
      return hoursLeft < 24 && hoursLeft > 0
    },
    isOverdue() {
      if (!this.approval.deadline) return false
      return new Date() > new Date(this.approval.deadline)
    },
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    },
    async submitDecision(decision) {
      this.submitError = null
      this.isSubmitting = true

      try {
        const response = await this.$api.post(
          `/api/v1/core/approvals/instances/${this.approval.id}/decide`,
          {
            decision,
            comment: this.comment || null,
          }
        )
        this.$emit('decision-submitted', { decision, comment: this.comment, response })
        this.comment = ''
      } catch (error) {
        this.submitError = error.message || 'Failed to submit decision'
      } finally {
        this.isSubmitting = false
      }
    },
  },
}
</script>

<style scoped>
.approval-card {
  border: 1px solid var(--slate-200);
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.approval-card.is-urgent {
  border-left: 4px solid var(--amber-400);
}

.card-header {
  background-color: var(--slate-50);
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--slate-200);
}

.card-header h3 {
  margin: 0;
  font-size: 1.25rem;
}

.badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
}

.badge-warning {
  background-color: var(--yellow-50);
  color: var(--yellow-700);
}

.badge-success {
  background-color: var(--green-50);
  color: var(--green-700);
}

.badge-danger {
  background-color: var(--red-50);
  color: var(--red-800);
}

.badge-info {
  background-color: var(--halo-100);
  color: var(--halo-800);
}

.badge-secondary {
  background-color: var(--slate-200);
  color: var(--slate-700);
}

.card-body {
  padding: 1rem;
}

.approval-details p {
  margin: 0.5rem 0;
}

.deadline {
  padding: 0.5rem;
  background-color: var(--slate-50);
  border-radius: 4px;
}

.deadline.is-overdue {
  background-color: var(--red-50);
  color: var(--red-600);
}

.decision-section {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--slate-100);
}

textarea {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--slate-200);
  border-radius: 4px;
  font-family: inherit;
  margin-bottom: 1rem;
}

.button-group {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.btn {
  flex: 1;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
  transition: opacity 0.2s;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-success {
  background-color: var(--green-500);
  color: white;
}

.btn-danger {
  background-color: var(--red-500);
  color: white;
}

.btn-warning {
  background-color: var(--amber-400);
  color: white;
}

.alert {
  padding: 0.75rem;
  border-radius: 4px;
  margin-top: 1rem;
}

.alert-danger {
  background-color: var(--red-50);
  color: var(--red-800);
  border: 1px solid var(--red-200);
}

.decision-history {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--slate-100);
}

.decision-history h4 {
  margin-top: 0;
}

.decision-item {
  margin-bottom: 1rem;
  padding: 0.75rem;
  background-color: var(--slate-50);
  border-radius: 4px;
}

.comment {
  margin: 0.5rem 0;
  font-style: italic;
  color: var(--slate-500);
}

.timestamp {
  font-size: 0.875rem;
  color: var(--slate-400);
  margin: 0;
}
</style>
