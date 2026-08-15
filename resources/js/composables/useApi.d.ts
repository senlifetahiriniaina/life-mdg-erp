import type { Ref } from 'vue'

interface ApiCallOptions {
  successMessage?: string
  params?: Record<string, unknown>
}

export declare function useApi(): {
  loading: Ref<boolean>
  errors: Ref<Record<string, string[]>>
  get<T = unknown>(url: string, options?: ApiCallOptions): Promise<T>
  post<T = unknown>(url: string, data?: unknown, options?: ApiCallOptions): Promise<T>
  put<T = unknown>(url: string, data?: unknown, options?: ApiCallOptions): Promise<T>
  delete(url: string, options?: ApiCallOptions): Promise<void>
  fieldError(field: string): string | null
}
