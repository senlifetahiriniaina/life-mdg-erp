import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Composable for list page filtering with debounced search and Inertia navigation.
 *
 * @param {string} routeName - Inertia route name to navigate to on filter change
 * @param {object} initialFilters - Initial filter values from page props
 * @param {number} debounceMs - Debounce delay for search input (default 400ms)
 */
export function useFilters(routeName, initialFilters = {}, debounceMs = 400) {
    const filters = ref({ ...initialFilters })
    let debounceTimer = null

    function applyFilters() {
        const params = {}
        for (const [key, val] of Object.entries(filters.value)) {
            if (val !== null && val !== undefined && val !== '') {
                params[key] = val
            }
        }
        router.get(route(routeName), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }

    function onSearch() {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(applyFilters, debounceMs)
    }

    function onFilter() {
        applyFilters()
    }

    function resetFilters() {
        for (const key of Object.keys(filters.value)) {
            filters.value[key] = null
        }
        applyFilters()
    }

    return { filters, onSearch, onFilter, resetFilters }
}
