import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export type AppRole =
  | 'super-admin' | 'admin' | 'manager' | 'employee'
  | 'accountant' | 'hr-manager' | 'sales-rep'
  | 'system-admin' | 'security-admin' | 'billing-admin'
  | 'support-admin' | 'content-admin' | 'tenant-admin'
  | 'cashier' | 'community-manager' | 'production-manager'
  | 'logistics-manager' | 'service-partner' | 'brand-owner'
  | 'purchasing-manager' | 'warehouse-operator' | 'sales-manager'
  | 'project-manager' | 'finance-manager' | 'customer-service'
  | 'inventory-analyst' | 'marketplace-admin'

const ADMIN_ROLES: AppRole[] = [
  'super-admin', 'admin', 'system-admin', 'security-admin',
  'billing-admin', 'support-admin', 'content-admin', 'tenant-admin',
]

const ELEVATED_ROLES: AppRole[] = [
  ...ADMIN_ROLES, 'manager',
]

// Role display labels (FR)
export const ROLE_LABELS: Record<AppRole, string> = {
  'super-admin':        'Super Administrateur',
  'admin':              'Administrateur',
  'manager':            'Manager',
  'employee':           'Employé',
  'accountant':         'Comptable',
  'hr-manager':         'Responsable RH',
  'sales-rep':          'Commercial',
  'system-admin':       'Admin Système',
  'security-admin':     'Admin Sécurité',
  'billing-admin':      'Admin Facturation',
  'support-admin':      'Admin Support',
  'content-admin':      'Admin Contenu',
  'tenant-admin':       'Admin Tenant',
  'cashier':            'Caissier',
  'community-manager':  'Community Manager',
  'production-manager': 'Responsable de Production',
  'logistics-manager':  'Responsable Logistique',
  'service-partner':    'Partenaire Fournisseur de Service',
  'brand-owner':        'Partenaire Propriétaire de Marque',
  'purchasing-manager': 'Responsable des Achats',
  'warehouse-operator': 'Opérateur Entrepôt',
  'sales-manager':      'Manager Commercial',
  'project-manager':    'Chef de Projet',
  'finance-manager':    'Responsable Finance',
  'customer-service':   'Service Client',
  'inventory-analyst':  'Analyste Stock',
  'marketplace-admin':  'Admin Marketplace',
}

// Role category grouping for the UI
export const ROLE_GROUPS = [
  {
    label: 'Administration',
    roles: ['super-admin', 'admin', 'system-admin', 'security-admin', 'billing-admin', 'support-admin', 'content-admin', 'tenant-admin'] as AppRole[],
  },
  {
    label: 'Management',
    roles: ['manager', 'hr-manager', 'sales-manager', 'production-manager', 'logistics-manager', 'project-manager', 'finance-manager', 'purchasing-manager', 'marketplace-admin'] as AppRole[],
  },
  {
    label: 'Opérations',
    roles: ['employee', 'accountant', 'sales-rep', 'cashier', 'warehouse-operator', 'inventory-analyst', 'customer-service'] as AppRole[],
  },
  {
    label: 'Partenaires externes',
    roles: ['service-partner', 'brand-owner', 'community-manager'] as AppRole[],
  },
]

export function useRoleAccess() {
  const page = usePage()

  const userRoles = computed<AppRole[]>(() => (page.props.auth?.user?.roles ?? []) as AppRole[])
  const userPermissions = computed<string[]>(() => (page.props.auth?.user?.permissions ?? []) as string[])

  const hasRole = (role: AppRole | AppRole[]) => {
    const roles = Array.isArray(role) ? role : [role]
    return userRoles.value.some(r => roles.includes(r))
  }

  const hasAnyRole = (roles: AppRole[]) => userRoles.value.some(r => roles.includes(r))

  const hasPermission = (permission: string) => userPermissions.value.includes(permission)

  const isAdmin = computed(() => hasAnyRole(ADMIN_ROLES))
  const isElevated = computed(() => hasAnyRole(ELEVATED_ROLES))
  const isSuperAdmin = computed(() => hasRole('super-admin'))

  // True for roles that have restricted sidebar views
  const isRestrictedRole = computed(() =>
    !hasAnyRole([...ADMIN_ROLES, 'manager', 'employee', 'accountant', 'hr-manager', 'sales-rep'])
  )

  // Check if the user can perform an action on a module resource
  const can = (module: string, resource: string, action: string) =>
    isSuperAdmin.value || hasPermission(`${module}.${resource}.${action}`)

  const canViewModule = (module: string) =>
    isAdmin.value || !isRestrictedRole.value ||
    userRoles.value.some(role => {
      const access: Record<string, string[]> = {
        'cashier':            ['POS', 'Inventory'],
        'community-manager':  ['WhatsApp', 'Email', 'CRM'],
        'production-manager': ['Manufacturing', 'Inventory'],
        'logistics-manager':  ['Inventory', 'POS', 'Ecommerce'],
        'service-partner':    ['Helpdesk', 'Documents', 'Projects'],
        'brand-owner':        ['Ecommerce'],
        'purchasing-manager': ['Inventory', 'Accounting'],
        'warehouse-operator': ['Inventory'],
        'sales-manager':      ['CRM', 'Accounting', 'BI'],
        'project-manager':    ['Projects', 'HR', 'Documents'],
        'finance-manager':    ['Accounting', 'BI'],
        'customer-service':   ['Helpdesk', 'CRM'],
        'inventory-analyst':  ['Inventory', 'BI'],
        'marketplace-admin':  ['Ecommerce', 'Inventory', 'CRM', 'BI'],
      }
      return (access[role] ?? []).includes(module)
    })

  return {
    userRoles,
    userPermissions,
    hasRole,
    hasAnyRole,
    hasPermission,
    can,
    canViewModule,
    isAdmin,
    isElevated,
    isSuperAdmin,
    isRestrictedRole,
    ROLE_LABELS,
    ROLE_GROUPS,
  }
}
