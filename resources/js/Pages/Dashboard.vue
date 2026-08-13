<template>
  <AuthLayout>
    <Head title="Tableau de Bord" />

    <main class="px-4 py-6 sm:px-6 lg:px-8">
      <header class="mb-8">
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Bienvenue {{ user.name }}! 👋</h1>
        <p class="mt-1 text-surface-600 dark:text-surface-400">{{ roleGreeting }}</p>
      </header>

      <!-- Load appropriate dashboard component based on user role -->
      <section>
        <component
          :is="dashboardComponent"
          :metrics="metrics"
          :user="user"
        />
      </section>
    </main>
  </AuthLayout>
</template>

<script setup>
import { computed, defineAsyncComponent } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import DashboardSalesRep from './Dashboard/DashboardSalesRep.vue'
import DashboardAccountant from './Dashboard/DashboardAccountant.vue'
import DashboardHrManager from './Dashboard/DashboardHrManager.vue'
import DashboardManager from './Dashboard/DashboardManager.vue'
import DashboardAdmin from './Dashboard/DashboardAdmin.vue'
import DashboardCashier from './Dashboard/DashboardCashier.vue'
import DashboardWarehouseOperator from './Dashboard/DashboardWarehouseOperator.vue'
import DashboardCustomerService from './Dashboard/DashboardCustomerService.vue'
import DashboardCommunityManager from './Dashboard/DashboardCommunityManager.vue'
import DashboardProductionManager from './Dashboard/DashboardProductionManager.vue'
import DashboardEmployee from './Dashboard/DashboardEmployee.vue'

const page = usePage()
const user = computed(() => page.props.auth.user)
const metrics = computed(() => page.props.metrics || {})

const userRole = computed(() => {
  const roles = user.value?.roles || []
  if (roles.length === 0) return 'employee'

  const role = roles[0]?.name || 'employee'
  return role
})

const dashboardComponent = computed(() => {
  const roleMap = {
    'sales-rep': DashboardSalesRep,
    'accountant': DashboardAccountant,
    'hr-manager': DashboardHrManager,
    'manager': DashboardManager,
    'admin': DashboardAdmin,
    'super-admin': DashboardAdmin,
    'cashier': DashboardCashier,
    'warehouse-operator': DashboardWarehouseOperator,
    'customer-service': DashboardCustomerService,
    'community-manager': DashboardCommunityManager,
    'production-manager': DashboardProductionManager,
    'employee': DashboardEmployee,
  }

  return roleMap[userRole.value] || DashboardEmployee
})

const roleGreeting = computed(() => {
  const greetings = {
    'sales-rep': 'Voici votre pipeline et vos leads chauds pour aujourd\'hui',
    'accountant': 'Passez une bonne journée comptable! Voici ce qui vous attend',
    'hr-manager': 'Gérez votre équipe efficacement avec ces insights',
    'manager': 'Vue d\'ensemble de votre équipe et des projets',
    'admin': 'Santé du système et métriques globales',
    'super-admin': 'Santé du système et métriques globales',
    'cashier': 'Gérez votre caisse et les transactions du jour',
    'warehouse-operator': 'Voici les tâches de réception et d\'expédition d\'aujourd\'hui',
    'customer-service': 'Votre file d\'attente des tickets et KB',
    'community-manager': 'Gérez vos conversations WhatsApp et campagnes',
    'production-manager': 'Suiverez votre production et alertes maintenance',
    'employee': 'Voici vos tâches et informations pour aujourd\'hui',
  }

  return greetings[userRole.value] || 'Bienvenue sur WideHalo ERP'
})
</script>

<style scoped>
/* Styles spécifiques au dashboard si nécessaire */
</style>
