import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

// Simple button component for testing
const Button = defineComponent({
  props: {
    label: String,
    disabled: Boolean,
    variant: {
      type: String,
      default: 'primary'
    }
  },
  emits: ['click'],
  setup(props, { emit }) {
    return () => h(
      'button',
      {
        disabled: props.disabled,
        class: `btn btn-${props.variant}`,
        onClick: () => emit('click')
      },
      props.label
    )
  }
})

describe('Button Component', () => {
  it('renders with label prop', () => {
    const wrapper = mount(Button, {
      props: { label: 'Click me' }
    })
    expect(wrapper.text()).toBe('Click me')
  })

  it('emits click event when clicked', async () => {
    const wrapper = mount(Button, {
      props: { label: 'Submit' }
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('is disabled when disabled prop is true', () => {
    const wrapper = mount(Button, {
      props: { label: 'Disabled', disabled: true }
    })
    expect(wrapper.attributes('disabled')).toBeDefined()
  })

  it('applies variant class', () => {
    const wrapper = mount(Button, {
      props: { label: 'Secondary', variant: 'secondary' }
    })
    expect(wrapper.classes()).toContain('btn-secondary')
  })

  it('does not emit click when disabled', async () => {
    const wrapper = mount(Button, {
      props: { label: 'Disabled', disabled: true }
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })
})
