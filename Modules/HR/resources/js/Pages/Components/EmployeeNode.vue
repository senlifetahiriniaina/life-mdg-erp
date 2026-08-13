<template>
  <div class="flex flex-col items-center">
    <!-- Employee Card -->
    <div class="bg-gradient-to-br from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/20 rounded-lg border border-primary-200 dark:border-primary-700 p-4 min-w-48 mb-6 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-primary-200 dark:bg-primary-700 flex items-center justify-center flex-shrink-0">
          <span class="text-xs font-bold text-primary-900 dark:text-primary-100">
            {{ employee.full_name.charAt(0) }}
          </span>
        </div>
        <div class="min-w-0">
          <p class="font-medium text-primary-900 dark:text-primary-100 truncate">
            {{ employee.full_name }}
          </p>
          <p class="text-xs text-primary-700 dark:text-primary-200 truncate">
            {{ employee.job_title || 'Employee' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Direct Reports -->
    <div v-if="directReports.length > 0" class="relative">
      <!-- Vertical line from parent to children -->
      <div class="absolute top-0 left-1/2 w-0.5 h-6 bg-surface-300 dark:bg-surface-600" style="transform: translateX(-50%)" />

      <!-- Children container -->
      <div class="flex gap-8 justify-center">
        <!-- Horizontal line connecting all children -->
        <div v-if="directReports.length > 1" class="absolute top-6 left-0 right-0 h-0.5 bg-surface-300 dark:bg-surface-600" />

        <!-- Each child -->
        <div v-for="child in directReports" :key="child.id" class="relative">
          <!-- Vertical line to child -->
          <div class="absolute top-0 left-1/2 w-0.5 h-6 bg-surface-300 dark:bg-surface-600" style="transform: translateX(-50%)" />

          <!-- Recursive child node -->
          <EmployeeNode :employee="child" :org-map="orgMap" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Employee {
  id: number
  full_name: string
  job_title: string | null
  manager_id: number | null
}

const props = defineProps<{
  employee: Employee
  orgMap: Map<number, Employee>
}>()

const directReports = computed(() => {
  const reports: Employee[] = []
  props.orgMap.forEach(emp => {
    if (emp.manager_id === props.employee.id) {
      reports.push(emp)
    }
  })
  return reports
})
</script>

<style scoped>
.flex > .relative {
  flex-shrink: 0;
}
</style>
