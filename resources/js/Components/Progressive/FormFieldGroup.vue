<template>
  <div v-if="isVisible" class="space-y-4">
    <div v-for="field in visibleFields" :key="field.name" class="field-wrapper">
      <!-- Basic Level Fields -->
      <div v-if="field.level === 'basic' || !field.level" class="form-group">
        <label :for="field.name" class="block text-sm font-semibold text-fg-1 mb-1">
          {{ field.label }}
          <span v-if="field.required" class="text-error-600">*</span>
        </label>
        <component
          :is="field.component"
          :id="field.name"
          :modelValue="modelValue[field.name]"
          :disabled="field.disabled"
          class="w-full"
          @update:modelValue="updateField(field.name, $event)"
          v-bind="field.props"
        />
        <p v-if="field.hint" class="text-xs text-fg-4 mt-1">{{ field.hint }}</p>
      </div>

      <!-- Intermediate Level Fields (collapsible) -->
      <Fieldset v-else-if="field.level === 'intermediate'" :legend="field.label">
        <template #legend>
          <span class="flex items-center gap-2">
            {{ field.label }}
            <Tag value="Advanced" severity="info" class="text-xs" />
          </span>
        </template>
        <component
          :is="field.component"
          :modelValue="modelValue[field.name]"
          :disabled="field.disabled"
          class="w-full"
          @update:modelValue="updateField(field.name, $event)"
          v-bind="field.props"
        />
        <p v-if="field.hint" class="text-xs text-fg-4 mt-2">{{ field.hint }}</p>
      </Fieldset>

      <!-- Advanced/Expert Level Fields (expandable section) -->
      <Panel v-else :header="field.label" :toggleable="true">
        <template #icons>
          <Tag value="Expert" severity="warning" class="text-xs" />
        </template>
        <component
          :is="field.component"
          :modelValue="modelValue[field.name]"
          :disabled="field.disabled"
          class="w-full"
          @update:modelValue="updateField(field.name, $event)"
          v-bind="field.props"
        />
        <p v-if="field.hint" class="text-xs text-fg-4 mt-2">{{ field.hint }}</p>
      </Panel>
    </div>
  </div>

  <!-- Empty state when no fields are visible -->
  <div v-else class="text-center py-6 text-fg-4">
    <p>{{ emptyMessage }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoleAccess } from '@/Composables/useRoleAccess'
import Fieldset from 'primevue/fieldset'
import Panel from 'primevue/panel'
import Tag from 'primevue/tag'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
  fields: {
    type: Array,
    required: true,
  },
  featureLevel: {
    type: String,
    default: null,
  },
  emptyMessage: {
    type: String,
    default: 'No fields available for your role',
  },
})

const emit = defineEmits(['update:modelValue'])

const { getVisibleFormFields } = useRoleAccess()

const visibleFields = computed(() => {
  return getVisibleFormFields(props.fields, props.featureLevel)
})

const isVisible = computed(() => {
  return visibleFields.value.length > 0
})

const updateField = (fieldName, value) => {
  emit('update:modelValue', {
    ...props.modelValue,
    [fieldName]: value,
  })
}
</script>

<style scoped>
.field-wrapper {
  padding: 1rem;
  border-radius: 0.375rem;
  background-color: rgba(0, 0, 0, 0.02);
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
}
</style>
