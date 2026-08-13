import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AuthForm from '../AuthForm.vue'

describe('AuthForm.vue', () => {
  let wrapper

  beforeEach(() => {
    wrapper = mount(AuthForm, {
      global: {
        mocks: {
          $api: {
            post: vi.fn(),
          },
        },
      },
    })
  })

  it('renders login form with email and password fields', () => {
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').text()).toContain('Login')
  })

  it('validates email format before submission', async () => {
    await wrapper.find('input[type="email"]').setValue('invalid-email')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.vm.errors.email).toBeDefined()
    expect(wrapper.vm.errors.email).toBe('Invalid email format')
  })

  it('requires email field', async () => {
    await wrapper.find('input[type="password"]').setValue('password123')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.vm.errors.email).toBe('Email is required')
  })

  it('requires password field', async () => {
    await wrapper.find('input[type="email"]').setValue('user@example.com')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.vm.errors.password).toBe('Password is required')
  })

  it('submits valid credentials and emits login-success', async () => {
    const mockResponse = { token: 'abc123', user: { id: 1 } }
    wrapper.vm.$api.post.mockResolvedValue(mockResponse)

    await wrapper.find('input[type="email"]').setValue('user@example.com')
    await wrapper.find('input[type="password"]').setValue('password123')
    await wrapper.find('form').trigger('submit')

    await wrapper.vm.$nextTick()

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith('/auth/login', {
      email: 'user@example.com',
      password: 'password123',
    })
    expect(wrapper.emitted('login-success')).toBeTruthy()
    expect(wrapper.emitted('login-success')[0][0]).toEqual(mockResponse)
  })

  it('displays 2FA code input when 2FA response received', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ requiresTwoFactor: true })

    await wrapper.find('input[type="email"]').setValue('user@example.com')
    await wrapper.find('input[type="password"]').setValue('password123')
    await wrapper.find('form').trigger('submit')

    await wrapper.vm.$nextTick()

    expect(wrapper.vm.step).toBe('2fa')
    expect(wrapper.find('input[type="text"]').exists()).toBe(true)
    expect(wrapper.find('input[type="text"]').attributes('placeholder')).toBe('000000')
  })

  it('submits 2FA code correctly', async () => {
    wrapper.vm.step = '2fa'
    wrapper.vm.form.email = 'user@example.com'
    const mockResponse = { token: 'verified_token' }
    wrapper.vm.$api.post.mockResolvedValue(mockResponse)

    await wrapper.vm.$nextTick()
    await wrapper.find('input[type="text"]').setValue('123456')
    await wrapper.find('form').trigger('submit')

    await wrapper.vm.$nextTick()

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith('/auth/verify-2fa', {
      email: 'user@example.com',
      code: '123456',
    })
  })

  it('handles API errors gracefully', async () => {
    const errorMessage = 'Invalid credentials'
    wrapper.vm.$api.post.mockRejectedValue(new Error(errorMessage))

    await wrapper.find('input[type="email"]').setValue('user@example.com')
    await wrapper.find('input[type="password"]').setValue('wrongpassword')
    await wrapper.find('form').trigger('submit')

    await wrapper.vm.$nextTick()

    expect(wrapper.vm.error).toBe(errorMessage)
    expect(wrapper.find('.alert-danger').exists()).toBe(true)
  })

  it('clears errors on valid input', async () => {
    wrapper.vm.errors.email = 'Invalid format'
    await wrapper.find('input[type="email"]').setValue('user@example.com')
    await wrapper.find('input[type="password"]').setValue('password123')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.vm.errors.email).toBeUndefined()
  })

  it('shows forgot password link on credentials step', () => {
    expect(wrapper.vm.step).toBe('credentials')
    expect(wrapper.find('.forgot-password').exists()).toBe(true)
  })

  it('validates valid email format', () => {
    expect(wrapper.vm.validateEmail('user@example.com')).toBe(true)
    expect(wrapper.vm.validateEmail('test+tag@domain.co.uk')).toBe(true)
    expect(wrapper.vm.validateEmail('invalid@')).toBe(false)
    expect(wrapper.vm.validateEmail('invalid')).toBe(false)
  })
})
