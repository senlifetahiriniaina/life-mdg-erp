<template>
  <div class="custom-field-editor">
    <div class="editor-header">
      <h3>{{ isEdit ? 'Edit Field' : 'Create Field' }}</h3>
    </div>

    <form @submit.prevent="handleSubmit">
      <div class="form-group">
        <label for="field-name">Field Name</label>
        <input
          id="field-name"
          v-model="form.name"
          type="text"
          class="form-control"
          placeholder="e.g., Customer Type"
          required
        />
        <span v-if="errors.name" class="error">{{ errors.name }}</span>
      </div>

      <div class="form-group">
        <label for="field-type">Field Type</label>
        <select
          id="field-type"
          v-model="form.type"
          class="form-control"
          required
          @change="updateOptions"
        >
          <option value="">Select a type</option>
          <option value="text">Text</option>
          <option value="textarea">Textarea</option>
          <option value="number">Number</option>
          <option value="date">Date</option>
          <option value="select">Dropdown</option>
          <option value="checkbox">Checkbox</option>
          <option value="radio">Radio</option>
        </select>
        <span v-if="errors.type" class="error">{{ errors.type }}</span>
      </div>

      <div class="form-group">
        <label for="field-label">Label</label>
        <input
          id="field-label"
          v-model="form.label"
          type="text"
          class="form-control"
          placeholder="Label shown to users"
        />
      </div>

      <div class="form-group">
        <label>
          <input v-model="form.required" type="checkbox" />
          Required
        </label>
      </div>

      <div v-if="hasOptions" class="form-group">
        <label for="options-one-per-line">Options (one per line)</label>
        <textarea id="options-one-per-line"
          v-model="form.optionsText"
          class="form-control"
          placeholder="Option 1&#10;Option 2&#10;Option 3"
          rows="4"
        ></textarea>
        <small class="help-text">Enter each option on a new line</small>
      </div>

      <div class="form-group">
        <label>
          <input v-model="form.active" type="checkbox" />
          Active
        </label>
      </div>

      <div class="button-group">
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
          {{ isSubmitting ? 'Saving...' : 'Save Field' }}
        </button>
        <button
          v-if="isEdit"
          type="button"
          class="btn btn-danger"
          :disabled="isSubmitting"
          @click="deleteField"
        >
          Delete
        </button>
      </div>

      <div v-if="submitError" class="alert alert-danger">{{ submitError }}</div>
    </form>

    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal" role="dialog" aria-modal="true">
        <h4>Confirm Delete</h4>
        <p>Are you sure you want to delete this field? This cannot be undone.</p>
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
  name: 'CustomFieldEditor',
  props: {
    field: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      form: {
        name: '',
        type: '',
        label: '',
        required: false,
        active: true,
        optionsText: '',
      },
      errors: {},
      submitError: null,
      isSubmitting: false,
      showDeleteConfirm: false,
    }
  },
  computed: {
    isEdit() {
      return this.field && this.field.id
    },
    hasOptions() {
      return ['select', 'radio', 'checkbox'].includes(this.form.type)
    },
  },
  watch: {
    field: {
      handler(newField) {
        if (newField) {
          this.form.name = newField.name || ''
          this.form.type = newField.type || ''
          this.form.label = newField.label || ''
          this.form.required = newField.required || false
          this.form.active = newField.active !== false
          if (newField.options && Array.isArray(newField.options)) {
            this.form.optionsText = newField.options.join('\n')
          }
        }
      },
      immediate: true,
    },
  },
  methods: {
    updateOptions() {
      if (!this.hasOptions) {
        this.form.optionsText = ''
      }
    },
    validateForm() {
      this.errors = {}

      if (!this.form.name.trim()) {
        this.errors.name = 'Field name is required'
      } else if (this.form.name.length < 2) {
        this.errors.name = 'Field name must be at least 2 characters'
      } else if (this.form.name.length > 100) {
        this.errors.name = 'Field name cannot exceed 100 characters'
      }

      if (!this.form.type) {
        this.errors.type = 'Field type is required'
      }

      if (this.hasOptions && !this.form.optionsText.trim()) {
        this.errors.options = 'At least one option is required for this field type'
      }

      return Object.keys(this.errors).length === 0
    },
    async handleSubmit() {
      if (!this.validateForm()) {
        return
      }

      this.submitError = null
      this.isSubmitting = true

      try {
        const payload = {
          name: this.form.name.trim(),
          type: this.form.type,
          label: this.form.label || this.form.name,
          required: this.form.required,
          active: this.form.active,
        }

        if (this.hasOptions) {
          payload.options = this.form.optionsText
            .split('\n')
            .map((o) => o.trim())
            .filter((o) => o)
        }

        const method = this.isEdit ? 'put' : 'post'
        const url = this.isEdit
          ? `/api/v1/core/custom-fields/${this.field.id}`
          : '/api/v1/core/custom-fields'

        const response = await this.$api[method](url, payload)
        this.$emit('saved', response)
      } catch (error) {
        this.submitError = error.message || 'Failed to save field'
      } finally {
        this.isSubmitting = false
      }
    },
    deleteField() {
      this.showDeleteConfirm = true
    },
    async confirmDelete() {
      this.showDeleteConfirm = false
      this.submitError = null
      this.isSubmitting = true

      try {
        await this.$api.delete(`/api/v1/core/custom-fields/${this.field.id}`)
        this.$emit('deleted', this.field.id)
      } catch (error) {
        this.submitError = error.message || 'Failed to delete field'
      } finally {
        this.isSubmitting = false
      }
    },
  },
}
</script>

<style scoped>
.custom-field-editor {
  background: white;
  border-radius: 8px;
  padding: 2rem;
}

.editor-header {
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--slate-100);
  padding-bottom: 1rem;
}

.editor-header h3 {
  margin: 0;
}

.form-group {
  margin-bottom: 1.5rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

input[type='text'],
input[type='number'],
select,
textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--slate-200);
  border-radius: 4px;
  font-family: inherit;
}

textarea {
  resize: vertical;
}

input[type='checkbox'] {
  margin-right: 0.5rem;
}

.error {
  color: var(--red-500);
  font-size: 0.875rem;
  margin-top: 0.25rem;
  display: block;
}

.help-text {
  display: block;
  margin-top: 0.5rem;
  color: var(--slate-500);
  font-size: 0.875rem;
}

.button-group {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background-color: var(--halo-500);
  color: white;
  flex: 1;
}

.btn-secondary {
  background-color: var(--slate-500);
  color: white;
}

.btn-danger {
  background-color: var(--red-500);
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
</style>
