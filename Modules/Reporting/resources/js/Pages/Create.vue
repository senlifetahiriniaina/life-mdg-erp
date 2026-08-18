<template>
  <AppLayout>
    <Head title="Nouveau rapport — WideHalo ERP" />

    <div class="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Nouveau rapport</h1>
        <p class="text-surface-500 text-sm mt-1">Définissez un nouveau rapport personnalisé</p>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Nom du rapport</label>
          <InputText v-model="form.name" class="w-full" placeholder="Ex: Ventes mensuelles par région" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Module source</label>
          <Select v-model="form.module" :options="moduleOptions" option-label="label" option-value="value" class="w-full" placeholder="Sélectionner un module" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <Textarea v-model="form.description" class="w-full" rows="2" auto-resize />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Requête SQL (modèle)</label>
          <Textarea v-model="form.query_template" class="w-full font-mono text-sm" rows="4" placeholder="SELECT * FROM ... WHERE tenant_id = {{tenant_id}}" />
          <p class="text-xs text-surface-400 mt-1">Utilisez <code v-pre>{{tenant_id}}</code> et <code v-pre>{{param}}</code> pour les valeurs injectées automatiquement lors de l'exécution.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Format de sortie</label>
            <Select v-model="form.output_format" :options="formatOptions" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Type de rapport</label>
            <Select v-model="form.report_type" :options="typeOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>

        <div v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</div>

        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="router.visit('/reporting')" />
          <Button label="Créer" icon="pi pi-check" :loading="saving" @click="submit" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Button from 'primevue/button'
import AppLayout from '@/Layouts/AppLayout.vue'

// Chantier 8 (Reporting): ReportsIndex.vue's "Nouveau rapport" button
// navigated to /reporting/create, a route that didn't exist anywhere in
// the app. Minimal wrapper page — posts straight to the real, already-
// routed POST /api/v1/reporting/reports (ReportingController::storeReport).

const saving = ref(false)
const error = ref('')

const form = reactive({
  name: '',
  module: 'Sales',
  description: '',
  query_template: '',
  output_format: 'table',
  report_type: 'table',
})

const moduleOptions = [
  { label: 'Ventes', value: 'Sales' },
  { label: 'Comptabilité', value: 'Accounting' },
  { label: 'Inventaire', value: 'Inventory' },
  { label: 'RH', value: 'HR' },
  { label: 'CRM', value: 'CRM' },
]

const formatOptions = [
  { label: 'Tableau', value: 'table' },
  { label: 'Graphique', value: 'chart' },
  { label: 'KPI', value: 'kpi' },
  { label: 'PDF', value: 'pdf' },
  { label: 'Excel', value: 'excel' },
]

const typeOptions = [
  { label: 'Tableau', value: 'table' },
  { label: 'Graphique', value: 'chart' },
  { label: 'Tableau croisé', value: 'pivot' },
  { label: 'Export', value: 'export' },
]

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''

const submit = async () => {
  if (!form.name || !form.module || !form.query_template) {
    error.value = 'Le nom, le module et la requête SQL sont obligatoires.'
    return
  }

  error.value = ''
  saving.value = true
  try {
    const res = await fetch('/api/v1/reporting/reports', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(form),
    })

    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      error.value = data.message ?? 'Erreur lors de la création du rapport.'
      return
    }

    router.visit('/reporting')
  } catch (e) {
    error.value = 'Erreur réseau lors de la création du rapport.'
  } finally {
    saving.value = false
  }
}
</script>
