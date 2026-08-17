import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
}

const mockTemplates = {
  data: [
    { id: 1, title: 'Bienvenue', category: 'onboarding', language: 'fr', tone: 'friendly', usage_count: 12, avg_satisfaction_rating: 4.5, status: 'active' },
  ],
  meta: { total: 1 },
}

describe('AIBot/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads real response templates from the cs-ai endpoint', async () => {
    axios.get.mockResolvedValue({ data: mockTemplates })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/helpdesk/cs-ai/response-templates', expect.objectContaining({ params: expect.any(Object) }))
    expect(wrapper.text()).toContain('Bienvenue')
  })

  it('tests response suggestions against a real ticket id', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/helpdesk/cs-ai/response-templates') return Promise.resolve({ data: mockTemplates })
      if (url === '/api/v1/helpdesk/cs-ai/response-suggestions') {
        return Promise.resolve({ data: { suggestions: [{ id: 9, response: 'Bonjour, voici la solution.', reason: 'FAQ match', relevance: 0.9, confidence: 0.8 }] } })
      }
      return Promise.resolve({ data: {} })
    })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    wrapper.vm.testTicketId = 42
    await wrapper.vm.testSuggestions()
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/helpdesk/cs-ai/response-suggestions', { params: { ticket_id: 42 } })
    expect(wrapper.text()).toContain('Bonjour, voici la solution.')
  })
})
