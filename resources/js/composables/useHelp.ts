import { ref, readonly } from 'vue'
import axios from 'axios'

export interface HelpContent {
    title: string
    summary: string
    tips: string[]
    docs_url: string
}

const cache = new Map<string, HelpContent>()
const current = ref<HelpContent | null>(null)
const isLoading = ref(false)

/**
 * Contextual in-app help composable.
 *
 * Usage:
 *   const { help, isLoading, loadHelp } = useHelp()
 *   onMounted(() => loadHelp('crm.contacts'))
 */
export function useHelp() {
    async function loadHelp(key: string): Promise<void> {
        if (!key) return

        if (cache.has(key)) {
            current.value = cache.get(key)!
            return
        }

        isLoading.value = true
        try {
            const { data } = await axios.get<HelpContent>('/api/v1/help/context', {
                params: { key },
            })
            cache.set(key, data)
            current.value = data
        } catch {
            current.value = null
        } finally {
            isLoading.value = false
        }
    }

    function clearHelp(): void {
        current.value = null
    }

    return {
        help: readonly(current),
        isLoading: readonly(isLoading),
        loadHelp,
        clearHelp,
    }
}
