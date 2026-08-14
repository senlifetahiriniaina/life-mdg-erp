import { ref, onMounted, type Ref, type ComputedRef } from 'vue'
import axios from 'axios'

interface StrategyObjective {
  id: number | string
  title?: string
  name?: string
  type?: string
  metric?: number
  target?: number
  owner?: string
  parent?: string | null
  hierarchy?: string[]
  progress_percentage?: number
}

interface StrategyHierarchy {
  objective?: StrategyObjective
  plan?: { id: number; name: string }
  links?: unknown[]
}

export function useStrategicLink(
  linkableType: string,
  linkableId: number | string | null | Ref<string | number | null> | ComputedRef<string | number | null>
) {
  const objective = ref<StrategyObjective | null>(null)
  const hierarchy = ref<StrategyHierarchy | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const resolveId = () => {
    if (linkableId && typeof linkableId === 'object' && 'value' in linkableId) {
      return linkableId.value
    }
    return linkableId
  }

  const fetchObjective = async () => {
    const id = resolveId()
    if (!linkableType || !id) return

    isLoading.value = true
    error.value = null
    try {
      const { data } = await axios.get(`/api/v1/strategy/resource/${linkableType}/${id}`)
      objective.value = data.hierarchy?.objective || null
      hierarchy.value = data.hierarchy || null
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Failed to fetch strategic link'
      console.error('Error fetching strategic link:', e)
      error.value = msg
      objective.value = null
      hierarchy.value = null
    } finally {
      isLoading.value = false
    }
  }

  const linkToObjective = async (objectiveId: number | string, contributionValue: number | null = null) => {
    error.value = null
    try {
      await axios.post('/api/v1/strategy/objective-links/link', {
        objective_id: objectiveId,
        linkable_type: linkableType,
        linkable_id: resolveId(),
        contribution_value: contributionValue,
      })
      await fetchObjective()
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to link objective'
    }
  }

  const unlinkFromObjective = async () => {
    error.value = null
    try {
      await axios.delete(`/api/v1/strategy/resource/${linkableType}/${resolveId()}`)
      objective.value = null
      hierarchy.value = null
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to unlink objective'
    }
  }

  const updateContribution = async (linkId: number | string, newValue: number, unitType: string | null = null) => {
    error.value = null
    try {
      await axios.put(`/api/v1/strategy/objective-links/${linkId}`, {
        contribution_value: newValue,
        unit_type: unitType,
      })
      await fetchObjective()
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to update contribution'
    }
  }

  onMounted(() => fetchObjective())

  return {
    objective,
    hierarchy,
    isLoading,
    error,
    linkToObjective,
    unlinkFromObjective,
    updateContribution,
    refetch: fetchObjective,
  }
}
