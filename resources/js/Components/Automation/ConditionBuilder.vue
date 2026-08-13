<template>
  <div class="condition-builder">
    <div class="condition-group" :style="{ paddingLeft: depth * 20 + 'px' }">
      <div class="group-header">
        <select v-model="localValue.type" class="type-select">
          <option value="AND">AND (all must be true)</option>
          <option value="OR">OR (any can be true)</option>
        </select>
        <button @click="addCondition" class="btn-add" title="Add condition">
          <i class="pi pi-plus"></i> Add Condition
        </button>
        <button @click="addGroup" class="btn-add" title="Add condition group">
          <i class="pi pi-plus"></i> Add Group
        </button>
      </div>

      <div class="rules-list">
        <div
          v-for="(rule, index) in localValue.rules"
          :key="index"
          class="rule-item"
        >
          <!-- Nested group -->
          <div v-if="rule.type" class="nested-group">
            <ConditionBuilder
              :model-value="rule"
              @update:model-value="updateRule(index, $event)"
              :depth="depth + 1"
            />
            <button
              @click="removeRule(index)"
              class="btn-remove"
              title="Remove group"
            >
              <i class="pi pi-trash"></i>
            </button>
          </div>

          <!-- Single condition -->
          <div v-else class="condition-row">
            <select v-model="rule.field" class="field-select">
              <option value="">Select field</option>
              <option value="status">Status</option>
              <option value="grand_total">Grand Total</option>
              <option value="user_id">User ID</option>
              <option value="created_at">Created At</option>
              <option value="is_active">Is Active</option>
            </select>

            <select v-model="rule.operator" class="operator-select">
              <option value="equals">Equals</option>
              <option value="not_equals">Not Equals</option>
              <option value="contains">Contains</option>
              <option value="starts_with">Starts With</option>
              <option value="greater_than">Greater Than</option>
              <option value="less_than">Less Than</option>
              <option value="is_empty">Is Empty</option>
              <option value="is_not_empty">Is Not Empty</option>
              <option value="in_list">In List</option>
            </select>

            <input
              v-if="!['is_empty', 'is_not_empty'].includes(rule.operator)"
              v-model="rule.value"
              type="text"
              class="value-input"
              placeholder="Enter value"
            />

            <button
              @click="removeRule(index)"
              class="btn-remove"
              title="Remove condition"
            >
              <i class="pi pi-trash"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'

interface Condition {
  field?: string
  operator?: string
  value?: any
  type?: 'AND' | 'OR'
  rules?: any[]
}

const props = defineProps<{
  modelValue: Condition
  depth?: number
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Condition]
}>()

const depth = computed(() => props.depth || 0)
const localValue = ref(props.modelValue)

watch(
  () => props.modelValue,
  (newVal) => {
    localValue.value = newVal
  },
  { deep: true }
)

watch(
  () => localValue.value,
  (newVal) => {
    emit('update:modelValue', newVal)
  },
  { deep: true }
)

const addCondition = () => {
  if (!localValue.value.rules) {
    localValue.value.rules = []
  }
  localValue.value.rules.push({
    field: '',
    operator: 'equals',
    value: '',
  })
}

const addGroup = () => {
  if (!localValue.value.rules) {
    localValue.value.rules = []
  }
  localValue.value.rules.push({
    type: 'AND',
    rules: [],
  })
}

const removeRule = (index: number) => {
  localValue.value.rules?.splice(index, 1)
}

const updateRule = (index: number, value: Condition) => {
  if (localValue.value.rules) {
    localValue.value.rules[index] = value
  }
}
</script>

<style scoped lang="scss">
.condition-builder {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  padding: 1rem;
  margin: 0.5rem 0;

  .condition-group {
    .group-header {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1rem;

      .type-select {
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 0.375rem;
        background: white;
        font-weight: 600;

        &:focus {
          outline: none;
          border-color: #7c3aed;
        }
      }

      .btn-add {
        padding: 0.5rem 1rem;
        background: #e0e7ff;
        color: #7c3aed;
        border: 1px solid #c7d2fe;
        border-radius: 0.375rem;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;

        &:hover {
          background: #c7d2fe;
        }

        i {
          margin-right: 0.25rem;
        }
      }
    }

    .rules-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;

      .rule-item {
        display: flex;
        gap: 0.5rem;
        align-items: flex-start;

        .nested-group {
          flex: 1;
          display: flex;
          gap: 0.5rem;
        }

        .condition-row {
          display: flex;
          gap: 0.5rem;
          flex: 1;
          align-items: center;
          background: white;
          padding: 0.75rem;
          border-radius: 0.375rem;
          border: 1px solid #ddd;

          .field-select,
          .operator-select,
          .value-input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 0.375rem;
            font-size: 0.9rem;

            &:focus {
              outline: none;
              border-color: #7c3aed;
            }
          }

          .field-select {
            flex: 0 0 150px;
          }

          .operator-select {
            flex: 0 0 130px;
          }

          .value-input {
            flex: 1;
          }
        }

        .btn-remove {
          padding: 0.5rem;
          background: #fee2e2;
          color: #991b1b;
          border: 1px solid #fecaca;
          border-radius: 0.375rem;
          cursor: pointer;
          transition: all 0.2s;

          &:hover {
            background: #fecaca;
          }
        }
      }
    }
  }
}
</style>
