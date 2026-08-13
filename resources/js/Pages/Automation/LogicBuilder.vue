<template>
  <div class="logic-builder">
    <Head title="Logic Builder" />

    <div class="builder-header">
      <div class="header-content">
        <h1>Logic Builder</h1>
        <p class="subtitle">Create automation rules without code</p>
      </div>
      <button @click="createNew" class="btn-primary">
        <i class="pi pi-plus"></i> New Rule
      </button>
    </div>

    <div v-if="!selectedRule" class="rules-list">
      <div class="list-header">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search rules..."
          class="search-input"
        />
        <select v-model="filterTrigger" class="filter-select">
          <option value="">All Triggers</option>
          <option value="order_created">Order Created</option>
          <option value="task_updated">Task Updated</option>
          <option value="project_created">Project Created</option>
        </select>
      </div>

      <div v-if="loading" class="loading">
        <i class="pi pi-spin pi-spinner"></i> Loading rules...
      </div>

      <div v-else-if="filteredRules.length === 0" class="empty-state">
        <i class="pi pi-inbox"></i>
        <p>No rules found</p>
        <button @click="createNew" class="btn-secondary">Create First Rule</button>
      </div>

      <div v-else class="rules-grid">
        <div
          v-for="rule in filteredRules"
          :key="rule.id"
          class="rule-card"
          @click="selectRule(rule)"
        >
          <div class="rule-header">
            <h3>{{ rule.name }}</h3>
            <span class="trigger-badge">{{ rule.trigger }}</span>
          </div>
          <p class="rule-description">{{ rule.description }}</p>
          <div class="rule-meta">
            <span class="executions">{{ rule.execution_count }} executions</span>
            <span :class="['status', rule.is_enabled ? 'enabled' : 'disabled']">
              {{ rule.is_enabled ? 'Enabled' : 'Disabled' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="rule-editor">
      <button @click="selectedRule = null" class="btn-back">
        <i class="pi pi-arrow-left"></i> Back to List
      </button>

      <div class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab"
          :class="['tab', activeTab === tab && 'active']"
          @click="activeTab = tab"
        >
          {{ tab }}
        </button>
      </div>

      <!-- Rule Editor Tab -->
      <div v-if="activeTab === 'Editor'" class="tab-content">
        <form @submit.prevent="saveRule" class="rule-form">
          <div class="form-group">
            <label>Rule Name *</label>
            <input v-model="form.name" type="text" class="input" required />
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" class="textarea"></textarea>
          </div>

          <div class="form-group">
            <label>Trigger Event *</label>
            <select v-model="form.trigger" class="select" required>
              <option value="">Select trigger</option>
              <option value="order_created">Order Created</option>
              <option value="order_updated">Order Updated</option>
              <option value="task_created">Task Created</option>
              <option value="task_updated">Task Updated</option>
              <option value="project_created">Project Created</option>
            </select>
          </div>

          <div class="form-group">
            <label>Conditions *</label>
            <ConditionBuilder v-model="form.conditions" />
          </div>

          <div class="form-group">
            <label>Actions *</label>
            <ActionConfigurator v-model="form.actions" />
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save Rule' }}
            </button>
            <button
              v-if="selectedRule.id"
              type="button"
              class="btn-danger"
              @click="deleteRule"
            >
              Delete Rule
            </button>
          </div>
        </form>
      </div>

      <!-- Test Tab -->
      <div v-if="activeTab === 'Test'" class="tab-content">
        <RuleTestRunner :rule="selectedRule" />
      </div>

      <!-- History Tab -->
      <div v-if="activeTab === 'History'" class="tab-content">
        <RuleHistory :rule="selectedRule" />
      </div>

      <!-- Preview Tab -->
      <div v-if="activeTab === 'Preview'" class="tab-content">
        <RulePreview :rule="selectedRule" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import ConditionBuilder from '@/Components/Automation/ConditionBuilder.vue'
import ActionConfigurator from '@/Components/Automation/ActionConfigurator.vue'
import RuleTestRunner from '@/Components/Automation/RuleTestRunner.vue'
import RulePreview from '@/Components/Automation/RulePreview.vue'
import RuleHistory from '@/Components/Automation/RuleHistory.vue'
import { useApi } from '@/composables/useApi'

const { post, put, delete: apiDelete, get } = useApi()

const rules = ref([])
const selectedRule = ref(null)
const loading = ref(false)
const saving = ref(false)
const searchQuery = ref('')
const filterTrigger = ref('')
const activeTab = ref('Editor')
const tabs = ['Editor', 'Test', 'History', 'Preview']

const form = ref({
  name: '',
  description: '',
  trigger: '',
  conditions: { type: 'AND', rules: [] },
  actions: [],
})

const filteredRules = computed(() => {
  return rules.value.filter(rule => {
    const matchSearch = rule.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchTrigger = !filterTrigger.value || rule.trigger === filterTrigger.value
    return matchSearch && matchTrigger
  })
})

const loadRules = async () => {
  loading.value = true
  try {
    const response = await get('/api/v1/automation/rules')
    rules.value = response.data || []
  } finally {
    loading.value = false
  }
}

const createNew = () => {
  selectedRule.value = { id: null, is_enabled: true }
  form.value = {
    name: '',
    description: '',
    trigger: '',
    conditions: { type: 'AND', rules: [] },
    actions: [],
  }
  activeTab.value = 'Editor'
}

const selectRule = (rule) => {
  selectedRule.value = rule
  form.value = {
    name: rule.name,
    description: rule.description,
    trigger: rule.trigger,
    conditions: rule.conditions,
    actions: rule.actions,
  }
  activeTab.value = 'Editor'
}

const saveRule = async () => {
  saving.value = true
  try {
    if (selectedRule.value.id) {
      await put(`/api/v1/automation/rules/${selectedRule.value.id}`, form.value)
    } else {
      await post('/api/v1/automation/rules', form.value)
    }
    await loadRules()
    selectedRule.value = null
  } finally {
    saving.value = false
  }
}

const deleteRule = async () => {
  if (confirm('Delete this rule? This cannot be undone.')) {
    saving.value = true
    try {
      await apiDelete(`/api/v1/automation/rules/${selectedRule.value.id}`)
      await loadRules()
      selectedRule.value = null
    } finally {
      saving.value = false
    }
  }
}

loadRules()
</script>

<style scoped lang="scss">
.logic-builder {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.builder-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e5e7eb;

  h1 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
  }

  .subtitle {
    color: #666;
    margin: 0.5rem 0 0 0;
  }
}

.rules-list {
  .list-header {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;

    .search-input,
    .filter-select {
      padding: 0.5rem;
      border: 1px solid #ddd;
      border-radius: 0.375rem;
    }

    .search-input {
      flex: 1;
    }
  }

  .rules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;

    .rule-card {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      padding: 1.5rem;
      cursor: pointer;
      transition: all 0.2s;

      &:hover {
        border-color: #7c3aed;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
      }

      .rule-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;

        h3 {
          margin: 0;
          font-size: 1.1rem;
        }

        .trigger-badge {
          background: #f3e8ff;
          color: #7c3aed;
          padding: 0.25rem 0.75rem;
          border-radius: 9999px;
          font-size: 0.75rem;
          font-weight: 600;
        }
      }

      .rule-description {
        color: #666;
        margin: 0 0 1rem 0;
        font-size: 0.9rem;
      }

      .rule-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;

        .executions {
          color: #999;
        }

        .status {
          padding: 0.25rem 0.75rem;
          border-radius: 0.25rem;
          font-weight: 600;

          &.enabled {
            background: #d1fae5;
            color: #065f46;
          }

          &.disabled {
            background: #fee2e2;
            color: #991b1b;
          }
        }
      }
    }
  }
}

.empty-state {
  text-align: center;
  padding: 3rem;

  i {
    font-size: 3rem;
    color: #ddd;
    display: block;
    margin-bottom: 1rem;
  }

  p {
    color: #999;
    margin-bottom: 1.5rem;
  }
}

.loading {
  text-align: center;
  padding: 2rem;
  color: #666;

  i {
    margin-right: 0.5rem;
  }
}

.rule-editor {
  background: white;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  padding: 2rem;

  .btn-back {
    margin-bottom: 1.5rem;
    padding: 0.5rem 1rem;
    background: none;
    border: 1px solid #ddd;
    border-radius: 0.375rem;
    cursor: pointer;

    &:hover {
      background: #f9fafb;
    }
  }

  .tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #e5e7eb;

    .tab {
      padding: 0.75rem 1.5rem;
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      cursor: pointer;
      color: #666;
      font-weight: 500;

      &.active {
        color: #7c3aed;
        border-bottom-color: #7c3aed;
      }

      &:hover {
        color: #7c3aed;
      }
    }
  }

  .tab-content {
    animation: fadeIn 0.2s;
  }

  .rule-form {
    .form-group {
      margin-bottom: 1.5rem;

      label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
      }

      input,
      select,
      textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 0.375rem;
        font-family: inherit;

        &:focus {
          outline: none;
          border-color: #7c3aed;
          box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
      }

      textarea {
        min-height: 100px;
        resize: vertical;
      }
    }

    .form-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }
  }
}

.btn-primary,
.btn-secondary,
.btn-danger {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.btn-primary {
  background: #7c3aed;
  color: white;

  &:hover:not(:disabled) {
    background: #6d28d9;
  }
}

.btn-secondary {
  background: #f3f4f6;
  color: #1f2937;

  &:hover {
    background: #e5e7eb;
  }
}

.btn-danger {
  background: #fee2e2;
  color: #991b1b;

  &:hover:not(:disabled) {
    background: #fecaca;
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>
