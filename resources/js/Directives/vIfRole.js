/**
 * Progressive Disclosure Directive
 * Hides elements based on user role
 * Usage: v-if-role="['admin', 'manager']"
 */

export default {
  mounted(el, binding, vnode) {
    const allowedRoles = Array.isArray(binding.value) ? binding.value : [binding.value]
    const userRoles = vnode.appContext.config.globalProperties.$page.props.auth.user?.roles?.map(r => r.name) || []

    const hasAccess = allowedRoles.some(role => userRoles.includes(role))

    if (!hasAccess) {
      el.style.display = 'none'
    }
  }
}
