import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  usePage: () => ({ props: { auth: { user: { roles: ['manager'], permissions: [] } } } }),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
}

const mockInsights = [
  { id: 1, trend: 'up', title: 'Chiffre d\'affaires en hausse', text: 'Le CA est en hausse de 12%.', module: 'Accounting', value: '+12%', severity: 'success' },
  { id: 2, trend: 'down', title: 'Résolution tickets en baisse', text: 'Le délai a augmenté.', module: 'Helpdesk', value: '+8%', severity: 'warning' },
]

describe('AINarratives/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads real insights from the API on mount instead of showing hardcoded narratives', async () => {
    axios.get.mockResolvedValue({ data: { data: mockInsights } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/bi/insights')
    expect(wrapper.text()).toContain('Accounting')
    expect(wrapper.text()).toContain('Chiffre d\'affaires en hausse')
    expect(wrapper.text()).toContain('Helpdesk')
  })

  it('shows an empty state when no insights are returned', async () => {
    axios.get.mockResolvedValue({ data: { data: [] } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Aucun insight disponible')
  })

  it('generates an AI narrative via the real endpoint', async () => {
    axios.get.mockResolvedValue({ data: { data: mockInsights } })
    axios.post.mockResolvedValue({ data: { data: { narrative: 'Synthèse générée par Claude.' } } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    await wrapper.vm.generate()
    await flushPromises()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/bi/ai/narrative', { data: mockInsights })
    expect(wrapper.text()).toContain('Synthèse générée par Claude.')
  })
})
