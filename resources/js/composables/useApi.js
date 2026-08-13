import { ref } from 'vue'
import axios from 'axios'
import { useToast } from 'primevue/usetoast'

/**
 * Composable for making API calls with loading/error state management.
 */
export function useApi() {
    const loading = ref(false)
    const errors = ref({})
    const toast = useToast()

    async function apiPost(url, data, options = {}) {
        loading.value = true
        errors.value = {}
        try {
            const response = await axios.post(url, data)
            if (options.successMessage) {
                toast.add({ severity: 'success', summary: 'Success', detail: options.successMessage, life: 3000 })
            }
            return response.data
        } catch (error) {
            if (error.response?.status === 422) {
                errors.value = error.response.data.errors ?? {}
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: error.response?.data?.message ?? 'An unexpected error occurred',
                    life: 5000,
                })
            }
            throw error
        } finally {
            loading.value = false
        }
    }

    async function apiPut(url, data, options = {}) {
        loading.value = true
        errors.value = {}
        try {
            const response = await axios.put(url, data)
            if (options.successMessage) {
                toast.add({ severity: 'success', summary: 'Success', detail: options.successMessage, life: 3000 })
            }
            return response.data
        } catch (error) {
            if (error.response?.status === 422) {
                errors.value = error.response.data.errors ?? {}
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: error.response?.data?.message ?? 'An unexpected error occurred',
                    life: 5000,
                })
            }
            throw error
        } finally {
            loading.value = false
        }
    }

    async function apiDelete(url, options = {}) {
        loading.value = true
        try {
            await axios.delete(url)
            if (options.successMessage) {
                toast.add({ severity: 'success', summary: 'Deleted', detail: options.successMessage, life: 3000 })
            }
        } catch (error) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response?.data?.message ?? 'Delete failed',
                life: 5000,
            })
            throw error
        } finally {
            loading.value = false
        }
    }

    function fieldError(field) {
        return errors.value[field]?.[0] ?? null
    }

    return { loading, errors, apiPost, apiPut, apiDelete, fieldError }
}
