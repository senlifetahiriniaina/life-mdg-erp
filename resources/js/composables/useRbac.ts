import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const MODULE_ROLES: Record<string, string[]> = {
  Accounting: ['accountant', 'accounting-manager', 'admin', 'super-admin'],
  Achats: ['purchasing-agent', 'purchasing-manager', 'admin', 'super-admin'],
  AI: ['admin', 'super-admin', 'it-admin', 'ai-analyst'],
  Analytics: ['bi-analyst', 'reporting-analyst', 'strategy-analyst', 'manager', 'admin', 'super-admin'],
  API: ['admin', 'super-admin', 'it-admin', 'api-manager', 'api-admin'],
  Assets: ['assets-manager', 'admin', 'super-admin'],
  AuditLog: ['compliance-officer', 'it-admin', 'admin', 'super-admin'],
  BI: ['bi-analyst', 'reporting-analyst', 'strategy-analyst', 'manager', 'admin', 'super-admin'],
  Contracts: ['contracts-manager', 'legal-officer', 'admin', 'super-admin'],
  CRM: ['crm-manager', 'sales-rep', 'sales-manager', 'admin', 'super-admin'],
  CustomerService: ['helpdesk-agent', 'helpdesk-manager', 'customer-service', 'customer-service-agent', 'admin', 'super-admin'],
  Discussion: ['employee', 'hr-employee', 'admin', 'super-admin'],
  Documents: ['documents-manager', 'employee', 'admin', 'super-admin'],
  Ecommerce: ['ecommerce-manager', 'ecommerce-operator', 'admin', 'super-admin'],
  Email: ['marketing-manager', 'email-specialist', 'admin', 'super-admin'],
  Helpdesk: ['helpdesk-agent', 'helpdesk-manager', 'admin', 'super-admin'],
  HR: ['hr-manager', 'hr-employee', 'admin', 'super-admin'],
  Integration: ['it-admin', 'admin', 'super-admin'],
  Inventory: ['inventory-manager', 'warehouse-staff', 'admin', 'super-admin'],
  Logistics: ['logistics-manager', 'logistics-agent', 'admin', 'super-admin'],
  Manufacturing: ['manufacturing-manager', 'manufacturing-engineer', 'production-operator', 'admin', 'super-admin'],
  MarketingAutomation: ['marketing-manager', 'email-specialist', 'admin', 'super-admin'],
  Messaging: ['admin', 'super-admin', 'it-admin', 'messaging-manager', 'marketing-manager'],
  Mobile: ['admin', 'super-admin'],
  MobileSync: ['admin', 'super-admin', 'it-admin'],
  Notes: ['employee', 'manager', 'admin', 'super-admin'],
  Payroll: ['hr-manager', 'payroll-manager', 'admin', 'super-admin'],
  Planning: ['hr-manager', 'planning-manager', 'admin', 'super-admin'],
  PLM: ['manufacturing-manager', 'product-manager', 'admin', 'super-admin'],
  POS: ['pos-manager', 'pos-cashier', 'admin', 'super-admin'],
  Projects: ['project-manager', 'project-member', 'admin', 'super-admin'],
  Quality: ['quality-inspector', 'quality-manager', 'admin', 'super-admin'],
  RealTime: ['admin', 'super-admin', 'it-admin'],
  Reporting: ['bi-analyst', 'reporting-analyst', 'strategy-analyst', 'accountant', 'finance-manager', 'admin', 'super-admin'],
  Sales: ['sales-rep', 'sales-manager', 'admin', 'super-admin'],
  Security: ['security-admin', 'compliance-officer', 'it-admin', 'admin', 'super-admin'],
  Settings: ['admin', 'super-admin'],
  Setup: ['admin', 'super-admin'],
  Strategy: ['admin', 'super-admin', 'manager', 'bi-analyst', 'strategy-analyst'],
  Shared: ['employee', 'admin', 'super-admin'],
  SMS: ['marketing-manager', 'messaging-manager', 'admin', 'super-admin'],
  SmartTable: ['employee', 'manager', 'admin', 'super-admin'],
  Timesheets: ['employee', 'hr-manager', 'admin', 'super-admin'],
  Validation: ['manager', 'admin', 'super-admin'],
  Workflow: ['admin', 'super-admin', 'it-admin', 'workflow-manager'],
  WhatsApp: ['marketing-manager', 'messaging-manager', 'crm-manager', 'admin', 'super-admin'],
  Dashboard: ['employee', 'manager', 'admin', 'super-admin'],
  Admin: ['admin', 'super-admin'],
  Import: ['admin', 'super-admin', 'data-manager'],
  Core: ['employee', 'manager', 'admin', 'super-admin'],
  default: ['admin', 'super-admin'],
}

const MANAGER_ROLES: string[] = [
  'crm-manager', 'sales-manager', 'hr-manager', 'accounting-manager', 'inventory-manager',
  'manufacturing-manager', 'logistics-manager', 'ecommerce-manager', 'helpdesk-manager',
  'pos-manager', 'project-manager', 'quality-manager', 'planning-manager', 'purchasing-manager',
  'marketing-manager', 'it-admin', 'security-admin', 'compliance-officer', 'assets-manager',
  'bi-analyst', 'reporting-analyst', 'documents-manager', 'contracts-manager', 'product-manager',
  'manager',
  // New roles (Phase RBAC extension)
  'ai-analyst', 'api-manager', 'api-admin', 'messaging-manager', 'strategy-analyst',
  'workflow-manager', 'payroll-manager', 'customer-service', 'customer-service-agent',
  'finance-manager',
]

interface UserRole {
  name: string
}

interface PageProps {
  [key: string]: unknown
  auth?: {
    user?: {
      roles?: UserRole[]
      name?: string
      id?: number | string
    }
  }
}

export function useRbac(moduleName?: string) {
  const page = usePage<PageProps>()
  const roles = computed(() => page.props.auth?.user?.roles?.map((r: UserRole) => r.name) || [])

  const isAdmin = computed(() => roles.value.some((r: string) => ['admin', 'super-admin'].includes(r)))

  const isManager = computed(() =>
    isAdmin.value || roles.value.some((r: string) => MANAGER_ROLES.includes(r))
  )

  const moduleRoles = MODULE_ROLES[moduleName ?? ''] ?? MODULE_ROLES.default

  const canView = computed(() =>
    isAdmin.value || roles.value.some((r: string) => moduleRoles.includes(r))
  )

  const canManage = computed(() =>
    isAdmin.value || roles.value.some((r: string) =>
      moduleRoles.filter((mr: string) => MANAGER_ROLES.includes(mr) || ['admin', 'super-admin'].includes(mr)).includes(r)
    )
  )

  const canCreate = computed(() => canManage.value)

  const canEdit = computed(() => canManage.value)

  const canDelete = computed(() => isAdmin.value)

  const userRole = computed(() => roles.value[0] || 'employee')

  const userName = computed(() => page.props.auth?.user?.name || '')

  const userId = computed(() => page.props.auth?.user?.id || null)

  return {
    roles,
    isAdmin,
    isManager,
    canView,
    canManage,
    canCreate,
    canEdit,
    canDelete,
    userRole,
    userName,
    userId,
  }
}
