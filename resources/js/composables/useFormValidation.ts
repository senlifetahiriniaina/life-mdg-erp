import { ref, computed } from 'vue'

interface ValidationRule {
  required?: boolean
  min?: number
  max?: number
  pattern?: RegExp
  custom?: (value: any) => boolean | string
  message?: string
}

interface ValidationRules {
  [field: string]: ValidationRule | ValidationRule[]
}

interface FieldError {
  [field: string]: string[]
}

export function useFormValidation(rules: ValidationRules = {}) {
  const errors = ref<FieldError>({})
  const touched = ref<Set<string>>(new Set())
  const isValidating = ref(false)

  const hasErrors = computed(() => Object.keys(errors.value).length > 0)
  const isTouched = (field: string) => touched.value.has(field)

  const validateField = async (field: string, value: any): Promise<boolean> => {
    const fieldRules = rules[field]
    if (!fieldRules) return true

    const rulesList = Array.isArray(fieldRules) ? fieldRules : [fieldRules]
    const fieldErrors: string[] = []

    for (const rule of rulesList) {
      // Required validation
      if (rule.required && !value) {
        fieldErrors.push(rule.message || `${field} is required`)
        continue
      }

      if (!value) continue

      // Min length validation
      if (rule.min !== undefined && value.length < rule.min) {
        fieldErrors.push(rule.message || `${field} must be at least ${rule.min} characters`)
      }

      // Max length validation
      if (rule.max !== undefined && value.length > rule.max) {
        fieldErrors.push(rule.message || `${field} must not exceed ${rule.max} characters`)
      }

      // Pattern validation
      if (rule.pattern && !rule.pattern.test(value)) {
        fieldErrors.push(rule.message || `${field} format is invalid`)
      }

      // Custom validation
      if (rule.custom) {
        const customResult = rule.custom(value)
        if (customResult !== true) {
          fieldErrors.push(
            typeof customResult === 'string' ? customResult : rule.message || `${field} validation failed`
          )
        }
      }
    }

    if (fieldErrors.length > 0) {
      errors.value[field] = fieldErrors
    } else {
      delete errors.value[field]
    }

    return fieldErrors.length === 0
  }

  const validateForm = async (formData: Record<string, any>): Promise<boolean> => {
    isValidating.value = true
    let isValid = true

    for (const [field, value] of Object.entries(formData)) {
      const fieldValid = await validateField(field, value)
      isValid = isValid && fieldValid
    }

    isValidating.value = false
    return isValid
  }

  const markTouched = (field: string) => {
    touched.value.add(field)
  }

  const resetValidation = () => {
    errors.value = {}
    touched.value.clear()
    isValidating.value = false
  }

  const getFieldError = (field: string): string | undefined => {
    return errors.value[field]?.[0]
  }

  const hasFieldError = (field: string): boolean => {
    return Boolean(errors.value[field]?.length)
  }

  return {
    errors,
    touched,
    isValidating,
    hasErrors,
    isTouched,
    validateField,
    validateForm,
    markTouched,
    resetValidation,
    getFieldError,
    hasFieldError,
  }
}
