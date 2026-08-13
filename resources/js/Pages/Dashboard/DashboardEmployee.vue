<template>
  <div class="space-y-6">
    <!-- Welcome Section -->
    <Card class="bg-gradient-to-r from-blue-50 to-purple-50 border-0">
      <template #title>Bienvenue {{ user.first_name }}! 👋</template>
      <p class="text-surface-600 dark:text-surface-400">Voici votre vue d'ensemble pour aujourd'hui</p>
    </Card>

    <!-- Personal Metrics -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <!-- My Tasks -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>📝 Mes Tâches</template>
        <div class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.my_tasks }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">{{ metrics.overdue_tasks }} en retard</p>
        <Button label="Voir Tâches" icon="pi pi-arrow-right" class="mt-4 w-full p-button-sm" />
      </Card>

      <!-- My Leave Balance -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>🏖️ Congés Restants</template>
        <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ metrics.leave_days_remaining }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Jours de congé disponibles cette année</p>
        <Button label="Demander Congés" icon="pi pi-calendar" class="mt-4 w-full p-button-sm p-button-success" />
      </Card>

      <!-- Payslip Status -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>💰 Bulletin de Paie</template>
        <div class="text-3xl font-bold text-violet-700 dark:text-violet-300">{{ formatCurrency(metrics.last_salary) }}</div>
        <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">Dernier salaire versé</p>
        <Button label="Consulter Bulletins" icon="pi pi-file" class="mt-4 w-full p-button-sm p-button-info" />
      </Card>
    </div>

    <!-- Tasks & Activities -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- My Tasks List -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>📋 Tâches d'Aujourd'hui</template>
        <div class="space-y-3">
          <div v-for="task in myTasks" :key="task.id" class="flex items-start border-b pb-2 last:border-b-0">
            <Checkbox :modelValue="task.completed" class="mt-1" />
            <div class="ml-3 flex-1">
              <p class="font-semibold text-surface-900 dark:text-surface-50" :class="task.completed ? 'line-through text-surface-500 dark:text-surface-400' : ''">
                {{ task.title }}
              </p>
              <p class="text-xs text-surface-600 dark:text-surface-400">
                {{ task.project }} | Échéance: {{ task.due_date }}
              </p>
            </div>
            <Tag v-if="!task.completed" :value="task.priority" :severity="getPriorityClass(task.priority)" />
          </div>
        </div>
      </Card>

      <!-- My Calendar -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>📅 Mon Calendrier</template>
        <div class="space-y-3">
          <div v-for="event in myEvents" :key="event.id" class="flex items-center border-l-4 pl-3 py-2 rounded" :class="getEventClass(event.type)">
            <div class="flex-1">
              <p class="font-semibold text-surface-900 dark:text-surface-50">{{ event.title }}</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ event.time }}</p>
            </div>
            <Button icon="pi pi-arrow-right" class="p-button-rounded p-button-sm p-button-text" />
          </div>
        </div>
      </Card>
    </div>

    <!-- Company Announcements -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>📣 Annonces Importantes</template>
      <div class="space-y-3">
        <div v-for="announcement in announcements" :key="announcement.id" class="bg-primary-50 dark:bg-primary-900/20 border-l-4 border-blue-500 pl-3 py-2 rounded">
          <p class="font-semibold text-surface-900 dark:text-surface-50">{{ announcement.title }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ announcement.message }}</p>
          <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ announcement.date }}</p>
        </div>
      </div>
    </Card>

    <!-- Performance & Development -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Skills -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>🎯 Mes Compétences</template>
        <div class="space-y-3">
          <div v-for="skill in mySkills" :key="skill.id">
            <div class="flex items-center justify-between mb-1">
              <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">{{ skill.name }}</p>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ skill.level }}%</p>
            </div>
            <ProgressBar :value="skill.level" style="height: 6px;" />
          </div>
        </div>
      </Card>

      <!-- Training Opportunities -->
      <Card class="bg-white dark:bg-surface-800">
        <template #title>🎓 Formations Recommandées</template>
        <div class="space-y-3">
          <div v-for="training in recommendedTrainings" :key="training.id" class="border-b pb-2 last:border-b-0">
            <p class="font-semibold text-surface-900 dark:text-surface-50">{{ training.name }}</p>
            <p class="text-xs text-surface-600 dark:text-surface-400">Durée: {{ training.duration }} | Niveau: {{ training.level }}</p>
            <Button label="S'Inscrire" class="mt-2 p-button-sm p-button-text" />
          </div>
        </div>
      </Card>
    </div>

    <!-- Quick Links -->
    <Card class="bg-white dark:bg-surface-800">
      <template #title>🔗 Accès Rapide</template>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <Button label="Mes Projets" icon="pi pi-briefcase" class="p-button-outlined w-full" />
        <Button label="Mon Équipe" icon="pi pi-users" class="p-button-outlined w-full" />
        <Button label="Documents" icon="pi pi-file" class="p-button-outlined w-full" />
        <Button label="Support IT" icon="pi pi-headphones" class="p-button-outlined w-full" />
        <Button label="Plus..." icon="pi pi-arrow-right" class="p-button-outlined w-full" />
      </div>
    </Card>

    <!-- Status -->
    <div class="bg-primary-50 dark:bg-primary-900/20 border border-blue-200 rounded-lg p-4">
      <p class="text-sm text-blue-900">
        <strong>ℹ️</strong> Vous avez {{ metrics.unread_messages }} messages non lus et {{ metrics.pending_approvals }} approbations en attente.
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'

const props = defineProps({
  metrics: {
    type: Object,
    required: true,
  },
  user: {
    type: Object,
    required: true,
  },
})

const myTasks = computed(() => [
  { id: 1, title: 'Finir le rapport Q2', project: 'Projet A', due_date: '15-05-2026', priority: 'high', completed: false },
  { id: 2, title: 'Réunion avec le client', project: 'Projet B', due_date: '13-05-2026', priority: 'high', completed: true },
  { id: 3, title: 'Vérifier les documents', project: 'Projet C', due_date: '20-05-2026', priority: 'medium', completed: false },
])

const myEvents = computed(() => [
  { id: 1, title: 'Standup Daily', time: '10:00 - 10:30', type: 'meeting' },
  { id: 2, title: 'Déjeuner Équipe', time: '12:30 - 13:30', type: 'social' },
  { id: 3, title: 'Réunion 1:1 Manager', time: '15:00 - 15:30', type: 'meeting' },
])

const announcements = computed(() => [
  { id: 1, title: 'Nouveau Système RH', message: 'Le nouveau portail RH sera lancé le 20 mai. Formation le 19 mai.', date: '10-05-2026' },
  { id: 2, title: 'Fermeture Bureau', message: 'Les bureaux seront fermés le 25 mai pour maintenance.', date: '09-05-2026' },
])

const mySkills = computed(() => [
  { id: 1, name: 'JavaScript', level: 85 },
  { id: 2, name: 'Vue.js', level: 80 },
  { id: 3, name: 'Laravel', level: 75 },
])

const recommendedTrainings = computed(() => [
  { id: 1, name: 'Advanced Vue.js Patterns', duration: '16h', level: 'Avancé' },
  { id: 2, name: 'Leadership Skills', duration: '8h', level: 'Débutant' },
])

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(value || 0)
}

const getPriorityClass = (priority) => {
  const classes = {
    high: 'danger',
    medium: 'warning',
    low: 'info',
  }
  return classes[priority] || 'info'
}

const getEventClass = (type) => {
  const classes = {
    meeting: 'border-blue-500 bg-primary-50 dark:bg-primary-900/20',
    social: 'border-green-500 bg-green-50 dark:bg-green-900/20',
    deadline: 'border-red-500 bg-red-50 dark:bg-red-900/20',
  }
  return classes[type] || 'border-gray-500 bg-gray-50'
}
</script>
