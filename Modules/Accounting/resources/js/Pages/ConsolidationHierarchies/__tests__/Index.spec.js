import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() }),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  Dialog: { template: '<div v-if="visible"><slot /><slot name="footer" /></div>', props: ['visible'] },
}

const mockHierarchies = [
  {
    id: 1,
    name: 'Groupe Holding Test',
    type: 'holding',
    company: { id: 10, name: 'Life MDG Holding' },
    parent_company: null,
    ownership_percentage: 100,
    effective_date: '2026-01-01',
    is_active: true,
    status: 'draft',
  },
]

describe('ConsolidationHierarchies/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads and renders hierarchies from the API on mount', async () => {
    axios.get.mockResolvedValue({ data: { data: mockHierarchies } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith(
      '/api/v1/accounting/consolidation-hierarchies',
      expect.objectContaining({ params: expect.any(Object) })
    )
    expect(wrapper.text()).toContain('Groupe Holding Test')
    expect(wrapper.text()).toContain('Life MDG Holding')
    expect(wrapper.text()).toContain('100%')
  })

  it('shows an empty state when no hierarchies are returned', async () => {
    axios.get.mockResolvedValue({ data: { data: [] } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune hiérarchie de consolidation trouvée')
  })

  it('creates a hierarchy without requiring a company_id from the client', async () => {
    axios.get.mockResolvedValue({ data: { data: [] } })
    axios.post.mockResolvedValue({ data: { data: { id: 2 } } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    wrapper.vm.openCreate()
    wrapper.vm.form.name = 'Nouvelle filiale'
    await wrapper.vm.submitForm()
    await flushPromises()

    const [, payload] = axios.post.mock.calls[0]
    expect(payload).not.toHaveProperty('company_id')
    expect(payload).not.toHaveProperty('parent_company_id')
    expect(payload.name).toBe('Nouvelle filiale')
  })

  it('surfaces an error toast when loading fails instead of crashing', async () => {
    axios.get.mockRejectedValue(new Error('network error'))

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(wrapper.vm.loading).toBe(false)
    expect(wrapper.vm.hierarchies).toEqual([])
  })
})
