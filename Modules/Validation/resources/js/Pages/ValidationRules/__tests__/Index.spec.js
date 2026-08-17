import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() }),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  Dialog: { template: '<div v-if="visible"><slot /><slot name="footer" /></div>', props: ['visible'] },
}

// Raw Laravel paginator shape, NOT the {"data":...} envelope used elsewhere.
const mockPage = {
  data: [
    { id: 1, name: 'Email requis', field: 'email', type: 'email', message: null, params: {} },
  ],
  current_page: 1,
  last_page: 1,
}

describe('ValidationRules/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads rules from the raw-paginator response shape', async () => {
    axios.get.mockResolvedValue({ data: mockPage })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/validation-rules', { params: { page: 1 } })
    expect(wrapper.text()).toContain('Email requis')
    expect(wrapper.text()).toContain('email')
  })

  it('shows an empty state when there are no rules', async () => {
    axios.get.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1 } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune règle de validation')
  })

  it('rejects invalid JSON params instead of submitting', async () => {
    axios.get.mockResolvedValue({ data: mockPage })
    axios.post.mockResolvedValue({ data: { data: { id: 2 } } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    wrapper.vm.openCreate()
    wrapper.vm.form.type = 'min'
    wrapper.vm.paramsText = '{not valid json'
    await wrapper.vm.submitForm()

    expect(axios.post).not.toHaveBeenCalled()
    expect(wrapper.vm.paramsError).toBeTruthy()
  })
})
