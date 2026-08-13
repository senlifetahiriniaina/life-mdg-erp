import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * Composable for role-based access control and progressive disclosure
 * Usage: const { canAccess, hasRole, getFeatureLevel } = useRoleAccess()
 */
export function useRoleAccess() {
  const page = usePage()

  const userRoles = computed(() => {
    return page.props.auth.user?.roles?.map(r => r.name) || []
  })

  const userRole = computed(() => {
    return userRoles.value[0] || 'employee'
  })

  /**
   * Check if user has any of the specified roles
   */
  const hasRole = (roles) => {
    const rolesArray = Array.isArray(roles) ? roles : [roles]
    return rolesArray.some(role => userRoles.value.includes(role))
  }

  /**
   * Check if user can access a feature
   * Supports feature flags like 'feature.subfeature.action'
   */
  const canAccess = (feature) => {
    const roleFeatureMap = {
      'super-admin': ['*'], // Full access
      'admin': [
        'dashboard.*',
        'users.*',
        'modules.*',
        'reports.*',
        'settings.*',
      ],
      'manager': [
        'dashboard.overview',
        'team.*',
        'reports.read',
        'analytics.*',
      ],
      'sales-rep': [
        'crm.*',
        'dashboard.pipeline',
        'reports.sales',
      ],
      'accountant': [
        'accounting.*',
        'reports.financial',
        'dashboard.financial',
      ],
      'hr-manager': [
        'hr.*',
        'reports.hr',
        'dashboard.hr',
      ],
      'cashier': [
        'pos.transactions',
        'inventory.read',
      ],
      'warehouse-operator': [
        'inventory.operations',
        'warehouse.*',
      ],
      'customer-service': [
        'helpdesk.*',
        'crm.contacts.read',
      ],
      'community-manager': [
        'whatsapp.*',
        'email.campaigns',
      ],
      'production-manager': [
        'manufacturing.*',
        'reports.production',
      ],
      'employee': [
        'dashboard.personal',
        'hr.self-service',
        'documents.shared',
      ],
    }

    const currentRoleFeatures = roleFeatureMap[userRole.value] || []

    if (currentRoleFeatures.includes('*')) {
      return true
    }

    return currentRoleFeatures.some(allowedFeature => {
      if (allowedFeature.endsWith('.*')) {
        const prefix = allowedFeature.slice(0, -2)
        return feature.startsWith(prefix)
      }
      return allowedFeature === feature
    })
  }

  /**
   * Get feature complexity level for progressive disclosure
   * 'basic' | 'intermediate' | 'advanced' | 'expert'
   */
  const getFeatureLevel = (feature) => {
    const levelMap = {
      'super-admin': 'expert',
      'admin': 'advanced',
      'manager': 'advanced',
      'sales-rep': 'intermediate',
      'accountant': 'intermediate',
      'hr-manager': 'intermediate',
      'cashier': 'basic',
      'warehouse-operator': 'basic',
      'customer-service': 'intermediate',
      'community-manager': 'intermediate',
      'production-manager': 'intermediate',
      'employee': 'basic',
    }

    return levelMap[userRole.value] || 'basic'
  }

  /**
   * Get visible form fields based on feature level
   */
  const getVisibleFormFields = (allFields, featureLevel = null) => {
    const level = featureLevel || getFeatureLevel('form')

    const levelMap = {
      basic: 1,
      intermediate: 2,
      advanced: 3,
      expert: 4,
    }

    const currentLevel = levelMap[level] || 1

    return allFields.filter(field => {
      const fieldLevel = levelMap[field.level || 'basic'] || 1
      return fieldLevel <= currentLevel
    })
  }

  /**
   * Get visible sidebar menu items based on role
   */
  const getVisibleMenuItems = (allMenuItems) => {
    return allMenuItems.filter(item => {
      if (!item.requiredRole) return true
      return hasRole(item.requiredRole)
    })
  }

  return {
    userRoles,
    userRole,
    hasRole,
    canAccess,
    getFeatureLevel,
    getVisibleFormFields,
    getVisibleMenuItems,
  }
}
