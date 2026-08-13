import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import GdprConsent from '../GdprConsent.vue'

describe('GdprConsent.vue', () => {
  let wrapper

  beforeEach(() => {
    localStorage.clear()
    wrapper = mount(GdprConsent, {
      global: {
        mocks: {
          $api: {
            post: vi.fn(),
          },
        },
      },
    })
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders consent banner on initial load', () => {
    expect(wrapper.find('.gdpr-banner').exists()).toBe(true)
    expect(wrapper.text()).toContain('Privacy & Consent')
  })

  it('hides banner when showInitial prop is false', async () => {
    wrapper = mount(GdprConsent, {
      props: { showInitial: false },
      global: {
        mocks: {
          $api: { post: vi.fn() },
        },
      },
    })

    expect(wrapper.vm.showBanner).toBe(false)
  })

  it('displays all consent options', () => {
    const options = ['Necessary', 'Analytics', 'Marketing', 'Preferences']
    options.forEach((option) => {
      expect(wrapper.text()).toContain(option)
    })
  })

  it('necessary consent is always enabled and disabled', () => {
    const necessaryCheckbox = wrapper.find('#consent-necessary')
    expect(necessaryCheckbox.element.checked).toBe(true)
    expect(necessaryCheckbox.element.disabled).toBe(true)
  })

  it('allows toggling analytics consent', async () => {
    const analyticsCheckbox = wrapper.find('#consent-analytics')
    expect(analyticsCheckbox.element.checked).toBe(false)

    await analyticsCheckbox.setValue(true)
    expect(wrapper.vm.consents.analytics).toBe(true)
  })

  it('accepts all consents', async () => {
    await wrapper.find('.banner-actions .btn-primary').trigger('click')

    expect(wrapper.vm.consents.necessary).toBe(true)
    expect(wrapper.vm.consents.analytics).toBe(true)
    expect(wrapper.vm.consents.marketing).toBe(true)
    expect(wrapper.vm.consents.preferences).toBe(true)
  })

  it('rejects optional consents', async () => {
    await wrapper.find('.banner-actions .btn-secondary').trigger('click')

    expect(wrapper.vm.consents.necessary).toBe(true)
    expect(wrapper.vm.consents.analytics).toBe(false)
    expect(wrapper.vm.consents.marketing).toBe(false)
    expect(wrapper.vm.consents.preferences).toBe(false)
  })

  it('saves preferences to localStorage', async () => {
    await wrapper.find('#consent-analytics').setValue(true)
    await wrapper.find('.banner-actions .btn-outline').trigger('click')

    const stored = JSON.parse(localStorage.getItem('gdpr-consent'))
    expect(stored.analytics).toBe(true)
    expect(stored.necessary).toBe(true)
  })

  it('emits consent-saved event', async () => {
    await wrapper.find('.banner-actions .btn-primary').trigger('click')

    expect(wrapper.emitted('consent-saved')).toBeTruthy()
    expect(wrapper.emitted('consent-saved')[0][0]).toEqual({
      necessary: true,
      analytics: true,
      marketing: true,
      preferences: true,
    })
  })

  it('hides banner after saving preferences', async () => {
    expect(wrapper.vm.showBanner).toBe(true)
    await wrapper.find('.banner-actions .btn-primary').trigger('click')
    expect(wrapper.vm.showBanner).toBe(false)
  })

  it('loads saved preferences from localStorage', () => {
    const saved = { necessary: true, analytics: true, marketing: false, preferences: true }
    localStorage.setItem('gdpr-consent', JSON.stringify(saved))

    wrapper = mount(GdprConsent, {
      global: {
        mocks: {
          $api: { post: vi.fn() },
        },
      },
    })

    expect(wrapper.vm.consents).toEqual(saved)
    expect(wrapper.vm.showBanner).toBe(false)
  })

  it('renders SAR modal when openSar is called', async () => {
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.sar-modal').exists()).toBe(true)
    expect(wrapper.text()).toContain('Subject Access Request')
  })

  it('closes SAR modal', async () => {
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.showSar).toBe(true)
    wrapper.vm.closeSar()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.showSar).toBe(false)
  })

  it('validates email in SAR form', async () => {
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('button:has-text("Request Data Export")').trigger('click')
    expect(wrapper.vm.sarError).toBe('Email is required')
  })

  it('validates email format in SAR', async () => {
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-email').setValue('invalid-email')
    await wrapper.find('button:has-text("Request Data Export")').trigger('click')
    expect(wrapper.vm.sarError).toContain('valid email')
  })

  it('submits valid SAR', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-email').setValue('user@example.com')
    await wrapper.find('button:has-text("Request Data Export")').trigger('click')

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith('/api/v1/core/gdpr/sar', {
      email: 'user@example.com',
      format: 'json',
    })
  })

  it('changes SAR format', async () => {
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-format').setValue('csv')
    expect(wrapper.vm.sarForm.format).toBe('csv')
  })

  it('disables submit button while loading SAR', async () => {
    wrapper.vm.$api.post.mockImplementation(
      () =>
        new Promise((resolve) => {
          setTimeout(resolve, 100)
        })
    )

    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-email').setValue('user@example.com')
    await wrapper.find('button:has-text("Request Data Export")').trigger('click')

    expect(wrapper.find('button:has-text("Processing...")').exists()).toBe(true)
  })

  it('emits sar-submitted event', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-email').setValue('user@example.com')
    await wrapper.find('button:has-text("Request Data Export")').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('sar-submitted')).toBeTruthy()
    expect(wrapper.emitted('sar-submitted')[0][0]).toEqual({
      email: 'user@example.com',
      format: 'json',
    })
  })

  it('displays confirming status after SAR submission', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-email').setValue('user@example.com')
    await wrapper.find('button:has-text("Request Data Export")').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.sarStatus).toBe('confirming')
    expect(wrapper.text()).toContain('Check your email')
  })

  it('handles SAR API errors', async () => {
    const errorMessage = 'Email not found in system'
    wrapper.vm.$api.post.mockRejectedValue(new Error(errorMessage))

    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-email').setValue('user@example.com')
    await wrapper.find('button:has-text("Request Data Export")').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.sarError).toBe(errorMessage)
  })

  it('validates valid email formats', () => {
    expect(wrapper.vm.validateEmail('user@example.com')).toBe(true)
    expect(wrapper.vm.validateEmail('test+tag@domain.co.uk')).toBe(true)
    expect(wrapper.vm.validateEmail('invalid@')).toBe(false)
    expect(wrapper.vm.validateEmail('invalid')).toBe(false)
  })

  it('clears SAR form when closing modal', async () => {
    wrapper.vm.openSar()
    await wrapper.vm.$nextTick()

    await wrapper.find('#sar-email').setValue('user@example.com')
    wrapper.vm.closeSar()

    expect(wrapper.vm.sarForm.email).toBe('')
    expect(wrapper.vm.sarStatus).toBe(null)
  })

  it('privacy link is displayed', () => {
    expect(wrapper.find('.privacy-link').exists()).toBe(true)
  })
})
