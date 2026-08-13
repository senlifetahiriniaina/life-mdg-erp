<template>
  <div class="workflow-visualizer">
    <div class="workflow-header">
      <h3>{{ workflow.name }}</h3>
      <span v-if="workflow.allow_parallel" class="badge badge-info">Parallel</span>
      <span v-else class="badge badge-secondary">Sequential</span>
    </div>

    <div class="workflow-description">
      <p>{{ workflow.description || 'No description provided' }}</p>
    </div>

    <div class="workflow-steps">
      <div
        v-for="(step, idx) in workflow.steps"
        :key="idx"
        class="step"
        :class="{
          'step-completed': isStepCompleted(idx),
          'step-current': isStepCurrent(idx),
          'step-pending': isStepPending(idx),
        }"
      >
        <div class="step-number">{{ idx + 1 }}</div>
        <div class="step-content">
          <h4>{{ step.label }}</h4>
          <p class="step-approver">
            Approver: <strong>{{ formatApprover(step) }}</strong>
          </p>
          <p v-if="step.timeout_hours" class="step-timeout">
            Timeout: {{ step.timeout_hours }} hours
          </p>
        </div>
        <div v-if="idx < workflow.steps.length - 1" class="step-connector"></div>
      </div>
    </div>

    <div v-if="currentInstance" class="instance-details">
      <h4>Current Progress</h4>
      <div class="progress-bar">
        <div
          class="progress-fill"
          :style="{ width: `${progressPercentage}%` }"
        ></div>
      </div>
      <p>
        Step {{ currentInstance.current_step }} of {{ workflow.steps.length }}
      </p>

      <div v-if="currentDecisions.length" class="decisions">
        <h5>Approvals Made</h5>
        <div v-for="decision in currentDecisions" :key="decision.id" class="decision-badge">
          <span class="decision-label">{{ decision.approver_name }}</span>
          <span class="decision-status" :class="`status-${decision.decision}`">
            {{ decision.decision }}
          </span>
        </div>
      </div>
    </div>

    <div class="workflow-actions">
      <button
        v-if="showEditButton"
        class="btn btn-secondary"
        @click="$emit('edit-workflow')"
      >
        Edit Workflow
      </button>
      <button
        v-if="showDeleteButton"
        class="btn btn-danger"
        @click="deleteWorkflow"
      >
        Delete
      </button>
    </div>

    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal" role="dialog" aria-modal="true">
        <h4>Confirm Delete</h4>
        <p>Delete workflow "{{ workflow.name }}"? This cannot be undone.</p>
        <div class="button-group">
          <button class="btn btn-danger" @click="confirmDelete">Delete</button>
          <button class="btn btn-secondary" @click="showDeleteConfirm = false">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WorkflowVisualizer',
  props: {
    workflow: {
      type: Object,
      required: true,
      validator(obj) {
        return obj.id && obj.name && Array.isArray(obj.steps)
      },
    },
    currentInstance: {
      type: Object,
      default: null,
    },
    showEditButton: {
      type: Boolean,
      default: false,
    },
    showDeleteButton: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      showDeleteConfirm: false,
      isDeleting: false,
    }
  },
  computed: {
    currentDecisions() {
      if (!this.currentInstance || !this.currentInstance.decisions) {
        return []
      }
      return this.currentInstance.decisions
    },
    progressPercentage() {
      if (!this.currentInstance) {
        return 0
      }
      return (this.currentInstance.current_step / this.workflow.steps.length) * 100
    },
  },
  methods: {
    formatApprover(step) {
      const type = step.approver_type
      const value = step.approver_value

      if (type === 'user') {
        return `User: ${value}`
      } else if (type === 'role') {
        return `Role: ${value}`
      } else if (type === 'department') {
        return `Department: ${value}`
      }
      return 'Unknown'
    },
    isStepCompleted(stepIndex) {
      if (!this.currentInstance) return false
      return stepIndex < this.currentInstance.current_step - 1
    },
    isStepCurrent(stepIndex) {
      if (!this.currentInstance) return false
      return stepIndex === this.currentInstance.current_step - 1
    },
    isStepPending(stepIndex) {
      if (!this.currentInstance) return true
      return stepIndex >= this.currentInstance.current_step - 1
    },
    deleteWorkflow() {
      this.showDeleteConfirm = true
    },
    async confirmDelete() {
      this.showDeleteConfirm = false
      this.isDeleting = true

      try {
        await this.$api.delete(`/api/v1/core/approvals/workflows/${this.workflow.id}`)
        this.$emit('workflow-deleted', this.workflow.id)
      } catch (error) {
        this.$emit('delete-error', error.message)
      } finally {
        this.isDeleting = false
      }
    },
  },
}
</script>

<style scoped>
.workflow-visualizer {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.workflow-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--slate-100);
}

.workflow-header h3 {
  margin: 0;
}

.badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
}

.badge-info {
  background-color: var(--halo-100);
  color: var(--halo-800);
}

.badge-secondary {
  background-color: var(--slate-200);
  color: var(--slate-700);
}

.workflow-description {
  margin-bottom: 2rem;
  padding: 1rem;
  background-color: var(--slate-50);
  border-radius: 4px;
}

.workflow-description p {
  margin: 0;
  color: var(--slate-500);
}

.workflow-steps {
  margin-bottom: 2rem;
}

.step {
  display: flex;
  margin-bottom: 2rem;
  opacity: 0.6;
  transition: opacity 0.2s;
}

.step.step-completed {
  opacity: 1;
}

.step.step-current {
  opacity: 1;
}

.step.step-pending:not(.step-current) {
  opacity: 0.6;
}

.step-number {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background-color: var(--slate-100);
  color: var(--slate-500);
  font-weight: bold;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.step.step-completed .step-number {
  background-color: var(--green-500);
  color: white;
}

.step.step-current .step-number {
  background-color: var(--halo-500);
  color: white;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
}

.step-content {
  margin-left: 1.5rem;
  flex: 1;
}

.step-content h4 {
  margin: 0 0 0.5rem 0;
  color: var(--slate-700);
}

.step-approver {
  margin: 0.25rem 0;
  font-size: 0.875rem;
  color: var(--slate-500);
}

.step-timeout {
  margin: 0.25rem 0;
  font-size: 0.875rem;
  color: var(--slate-400);
}

.step-connector {
  position: absolute;
  left: 74px;
  width: 2px;
  height: 80px;
  background-color: var(--slate-200);
  margin-top: 50px;
}

.instance-details {
  background-color: var(--halo-50);
  border-left: 4px solid var(--halo-500);
  padding: 1.5rem;
  border-radius: 4px;
  margin-bottom: 2rem;
}

.instance-details h4 {
  margin-top: 0;
  color: var(--halo-500);
}

.progress-bar {
  width: 100%;
  height: 20px;
  background-color: var(--slate-100);
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progress-fill {
  height: 100%;
  background-color: var(--green-500);
  transition: width 0.3s ease;
}

.decisions {
  margin-top: 1rem;
}

.decisions h5 {
  margin: 0 0 0.75rem 0;
}

.decision-badge {
  display: inline-block;
  background-color: white;
  border: 1px solid var(--slate-200);
  border-radius: 4px;
  padding: 0.5rem 0.75rem;
  margin-right: 0.5rem;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.decision-label {
  font-weight: 500;
}

.decision-status {
  margin-left: 0.5rem;
  padding: 0.15rem 0.5rem;
  border-radius: 3px;
  font-weight: 600;
  font-size: 0.75rem;
}

.status-approved {
  background-color: var(--green-50);
  color: var(--green-700);
}

.status-rejected {
  background-color: var(--red-50);
  color: var(--red-800);
}

.status-escalated {
  background-color: var(--yellow-50);
  color: var(--yellow-700);
}

.workflow-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
  border-top: 1px solid var(--slate-100);
  padding-top: 1rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}

.btn-secondary {
  background-color: var(--slate-500);
  color: white;
}

.btn-danger {
  background-color: var(--red-500);
  color: white;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.modal h4 {
  margin-top: 0;
}

.button-group {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
}
</style>
