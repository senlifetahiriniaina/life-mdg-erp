import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import Index from '../Index.vue'

const i18n = createI18n({ legacy: false, locale: 'fr', messages: { fr: {} } })

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  router: { visit: vi.fn() },
}))

vi.mock('@/composables/useEcho', () => ({
  useHelpdeskChannel: vi.fn(),
}))

vi.mock('@/stores/help', () => ({
  useHelpStore: () => ({}),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  GuidedTour: { template: '<div />' },
  Paginator: { template: '<div />' },
  Dialog: { template: '<div><slot /></div>' },
  InputText: { template: '<input />' },
  Textarea: { template: '<textarea></textarea>' },
  Select: { template: '<select></select>' },
}

const mockTickets = {
  data: [
    { id: 42, subject: 'Impossible de se connecter', status: 'open', priority: 'high', channel: 'email', team: { name: 'Support N1' }, created_at: '2026-05-01T10:00:00Z' },
  ],
  total: 1,
  per_page: 20,
  current_page: 1,
}

describe('Helpdesk Tickets/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('navigates to the real ticket detail page when a row is clicked (was previously a dead row/button)', async () => {
    const wrapper = mount(Index, { props: { tickets: mockTickets }, global: { stubs, plugins: [i18n] } })

    await wrapper.find('.wh-dt-row').trigger('click')

    expect(router.visit).toHaveBeenCalledWith('/helpdesk/tickets/42')
  })

  it('navigates via the "Voir" eye button too', async () => {
    const wrapper = mount(Index, { props: { tickets: mockTickets }, global: { stubs, plugins: [i18n] } })

    await wrapper.find('.wh-row-btn').trigger('click')

    expect(router.visit).toHaveBeenCalledWith('/helpdesk/tickets/42')
  })
})
