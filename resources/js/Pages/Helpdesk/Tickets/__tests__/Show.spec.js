import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Show from '../Show.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  router: { reload: vi.fn() },
  // useRbac (unlike useRoleAccess elsewhere) expects roles as {name}[] objects.
  usePage: () => ({ props: { auth: { user: { roles: [{ name: 'admin' }], permissions: [] } } } }),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  WorkflowStepper: { template: '<div />' },
  AiAssistantPanel: { template: '<div />' },
  Select: { template: '<select><slot /></select>', props: ['modelValue', 'options'] },
  Textarea: { template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />', props: ['modelValue'], emits: ['update:modelValue'] },
}

const mockTicket = {
  id: 42,
  ticket_number: 'TKT-042',
  subject: 'Impossible de me connecter',
  status: 'open',
  priority: 'high',
  channel: 'email',
  reporter_id: 5,
  reporter: { name: 'Client Test', email: 'client@test.mg' },
  assignee: null,
  team: { id: 3, name: 'Support N1' },
  created_at: '2026-05-01T10:00:00Z',
  comments: [
    { id: 1, content: 'Bonjour, ça ne marche pas.', is_internal: false, user_id: 5, user: { name: 'Client Test' }, created_at: '2026-05-01T10:05:00Z' },
  ],
}

function notFound() {
  return Promise.reject({ response: { status: 404 } })
}

function mountShow(getImpl) {
  axios.get.mockImplementation(getImpl)
  axios.post.mockResolvedValue({ data: {} })
  return mount(Show, { props: { ticket: mockTicket }, global: { stubs } })
}

describe('Helpdesk Tickets/Show.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders the real ticket and shows the "not yet analyzed" empty state for cs-ai panels with no data', async () => {
    const wrapper = mountShow(() => notFound())
    await flushPromises()

    expect(wrapper.text()).toContain('Impossible de me connecter')
    expect(wrapper.text()).toContain('Client Test')
    expect(wrapper.text()).toContain('Bonjour, ça ne marche pas.')
    // 7 cs-ai panels, all 404 → all show the empty state, none show an error
    expect(wrapper.text().match(/Pas encore analysé/g)?.length).toBe(7)
  })

  it('renders real sentiment data when the cs-ai endpoint has it precomputed', async () => {
    const wrapper = mountShow((url) => {
      if (url === '/api/v1/helpdesk/cs-ai/sentiment') {
        return Promise.resolve({ data: { sentiment: 'negative', confidence: 0.82 } })
      }
      return notFound()
    })
    await flushPromises()

    expect(wrapper.text()).toContain('negative')
    expect(wrapper.text()).toContain('82%')
  })

  it('resolves the ticket via the real API endpoint and reloads', async () => {
    const wrapper = mountShow(() => notFound())
    await flushPromises()

    await wrapper.vm.resolveTicket()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/helpdesk/tickets/42/resolve')
  })

  it('posts a public reply via the real comments endpoint', async () => {
    const wrapper = mountShow(() => notFound())
    await flushPromises()

    wrapper.vm.replyText = 'Merci, voici la solution.'
    await wrapper.vm.sendReply()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/helpdesk/tickets/42/comments', {
      content: 'Merci, voici la solution.',
      is_internal: false,
    })
  })
})
