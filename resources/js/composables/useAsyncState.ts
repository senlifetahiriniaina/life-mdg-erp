import { ref, computed, Ref } from 'vue'

interface AsyncStateOptions {
  resetDataOnError?: boolean
  immediate?: boolean
}

export function useAsyncState<T>(
  initialData: T,
  options: AsyncStateOptions = {}
) {
  const data = ref<T>(initialData)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const isError = computed(() => !!error.value)

  const execute = async <R = T>(
    fn: () => Promise<R>,
    onSuccess?: (result: R) => void
  ): Promise<R | void> => {
    isLoading.value = true
    error.value = null

    try {
      const result = await fn()
      if (onSuccess) {
        onSuccess(result)
      } else {
        data.value = result as unknown as T
      }
      return result
    } catch (err: any) {
      error.value = err.message || 'An error occurred'
      if (options.resetDataOnError) {
        data.value = initialData
      }
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const reset = () => {
    data.value = initialData
    error.value = null
    isLoading.value = false
  }

  const clearError = () => {
    error.value = null
  }

  return {
    data: data as Ref<T>,
    isLoading,
    error,
    isError,
    execute,
    reset,
    clearError,
  }
}
