import { usePage } from '@inertiajs/vue3'
import { computed, type ComputedRef } from 'vue'

// Replacement for vue-router's `useRoute().params.id` — this app is Inertia-routed
// (vue-router isn't installed at all), and every current call site follows Laravel's
// RESTful `/resource/{id}` or `/resource/{id}/edit` convention with an integer ID.
export function useRouteId(): ComputedRef<string | undefined> {
  const page = usePage()
  return computed(() => {
    const segments = page.url.split('?')[0].split('/').filter(Boolean)
    return segments.find(s => /^\d+$/.test(s))
  })
}
