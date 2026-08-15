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

    async function get(url, options = {}) {
        loading.value = true
        try {
            const response = await axios.get(url, options.params ? { params: options.params } : undefined)
            return response.data
        } catch (error) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response?.data?.message ?? 'An unexpected error occurred',
                life: 5000,
            })
            throw error
        } finally {
            loading.value = false
        }
    }

    async function post(url, data, options = {}) {
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

    async function put(url, data, options = {}) {
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

    async function del(url, options = {}) {
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

    // Every real caller (RuleHistory.vue, RuleTestRunner.vue,
    // CookieConsentBanner.vue, LogicBuilder.vue) destructures
    // get/post/put/delete — this composable used to export apiPost/apiPut/
    // apiDelete (and no get() at all), so every one of those components
    // was crashing at runtime with "X is not a function" the moment they
    // called through this composable. `delete` is a reserved word as a
    // bare identifier but is valid as an object property name; callers
    // already destructure it as `delete: apiDelete` to sidestep that.
    return { loading, errors, get, post, put, delete: del, fieldError }
}
