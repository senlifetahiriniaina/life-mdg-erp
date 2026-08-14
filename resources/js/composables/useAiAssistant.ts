import { ref, onMounted } from 'vue'
import axios from 'axios'

export interface DecisionIndicator {
  label: string
  value: string
  status: 'ok' | 'warning' | 'critical'
}

export interface NextAction {
  label: string
  action: string
  module: string
}

export interface AiGuidance {
  enabled: boolean
  what_to_do: string
  how_to_do: string[]
  decision_indicators: DecisionIndicator[]
  warnings: string[]
  next_actions: NextAction[]
  tips: string[]
}

export function useAiAssistant(
  module: string,
  action: string,
  context: Record<string, unknown> = {},
) {
  const guidance = ref<AiGuidance | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchGuidance = async () => {
    loading.value = true
    error.value = null
    try {
      const locale = document.documentElement.lang || 'fr'
      const { data } = await axios.post('/api/v1/ai/assist', {
        module,
        action,
        context,
        locale,
      })
      guidance.value = data as AiGuidance
    } catch {
      error.value = 'Assistance IA non disponible'
    } finally {
      loading.value = false
    }
  }

  onMounted(fetchGuidance)

  return { guidance, loading, error, refresh: fetchGuidance }
}
