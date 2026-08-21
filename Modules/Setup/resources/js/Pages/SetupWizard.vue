<template>
  <AppLayout>
    <Head title="Assistant de configuration — Life MDG ERP" />

    <div class="space-y-6 max-w-4xl mx-auto">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Assistant de configuration
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Configurez votre ERP en moins de 5 minutes
          </p>
        </div>
        <Button
          icon="pi pi-times"
          label="Fermer"
          severity="secondary"
          outlined
          @click="router.visit('/setup')"
        />
      </div>

      <!-- Step indicator: shows where the user is AND what's left, per the
           user's original complaint that no screen surfaced this. -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <WorkflowStepper :steps="wizardSteps" :current-step="currentStepKey" :show-actions="false" :show-details="false" />
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">

        <!-- Step 1: Entreprise -->
        <div v-if="currentStepKey === 'company'" class="space-y-6">
          <StepHeader icon="pi-building" title="Bienvenue sur Life MDG ERP" subtitle="Commençons par les informations de votre entreprise" />

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Nom de l'entreprise" required :error="errors.company_name">
              <InputText v-model="company.company_name" placeholder="Ex: Life MDG Sarl" class="w-full" :class="{ 'p-invalid': errors.company_name }" />
            </Field>
            <Field label="Secteur d'activité">
              <Select v-model="company.industry" :options="industryOptions" option-label="label" option-value="value" placeholder="Sélectionner un secteur" class="w-full" />
            </Field>
            <Field label="Pays" required :error="errors.country_code">
              <Select v-model="company.country_code" :options="countryOptions" option-label="label" option-value="value" placeholder="Sélectionner un pays" class="w-full" filter :class="{ 'p-invalid': errors.country_code }" @change="onCountryChange" />
            </Field>
            <Field label="Devise">
              <Select v-model="company.currency_code" :options="currencyOptions" option-label="label" option-value="value" placeholder="Sélectionner une devise" class="w-full" />
            </Field>
            <Field label="Email de contact">
              <InputText v-model="company.email" type="email" placeholder="contact@entreprise.com" class="w-full" />
            </Field>
            <Field label="Téléphone">
              <InputText v-model="company.phone" placeholder="+261 34 00 000 00" class="w-full" />
            </Field>
          </div>
        </div>

        <!-- Step 2: Administrateur -->
        <div v-else-if="currentStepKey === 'admin'" class="space-y-6">
          <StepHeader icon="pi-user" title="Votre profil administrateur" subtitle="Ce compte reçoit le rôle admin pour piloter la configuration" />

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Nom complet">
              <InputText v-model="admin.name" class="w-full" />
            </Field>
            <Field label="Langue">
              <Select v-model="admin.locale" :options="localeOptions" option-label="label" option-value="value" class="w-full" />
            </Field>
            <Field label="Fuseau horaire">
              <InputText v-model="admin.timezone" placeholder="Africa/Antananarivo" class="w-full" />
            </Field>
          </div>

          <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 flex items-start gap-2">
            <i class="pi pi-info-circle text-blue-600 mt-0.5" />
            <span class="text-sm text-blue-700 dark:text-blue-300">
              Vous pourrez inviter d'autres utilisateurs et leur attribuer des rôles depuis
              <a href="/admin/users" class="underline font-medium">Admin → Utilisateurs</a> une fois cette étape terminée.
            </span>
          </div>
        </div>

        <!-- Step 3: Modules -->
        <div v-else-if="currentStepKey === 'modules'" class="space-y-4">
          <StepHeader icon="pi-th-large" title="Modules à activer" subtitle="Activez ou désactivez les modules dont votre équipe a besoin" />

          <div v-if="modulesLoading" class="text-center py-12">
            <ProgressSpinner />
          </div>

          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div
              v-for="mod in moduleCatalog"
              :key="mod.name"
              class="flex items-center justify-between gap-3 p-3 border rounded-lg border-surface-200 dark:border-surface-700"
            >
              <div class="min-w-0">
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ mod.name }}</p>
                <p class="text-xs text-surface-500 truncate">{{ mod.description || '—' }}</p>
              </div>
              <InputSwitch
                :model-value="mod.is_active"
                :disabled="moduleToggling === mod.name"
                @update:model-value="(val) => toggleModule(mod, val)"
              />
            </div>
          </div>
          <p v-if="moduleToggleError" class="text-sm text-red-500">{{ moduleToggleError }}</p>
        </div>

        <!-- Step 4: Règles de base -->
        <div v-else-if="currentStepKey === 'workflows'" class="space-y-6">
          <StepHeader icon="pi-sliders-h" title="Règles de base" subtitle="Paramètres transverses appliqués aux processus d'approbation" />

          <div class="flex items-center gap-3">
            <Checkbox v-model="workflows.approval_required" :binary="true" input-id="approval_required" />
            <label for="approval_required" class="text-sm text-surface-700 dark:text-surface-300">
              Exiger une approbation pour les processus sensibles (factures, bons de commande, congés)
            </label>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Canaux de notification</label>
            <div class="flex flex-wrap gap-4">
              <div v-for="ch in notificationChannelOptions" :key="ch.value" class="flex items-center gap-2">
                <Checkbox v-model="workflows.notification_channels" :value="ch.value" :input-id="`ch-${ch.value}`" />
                <label class="text-sm">{{ ch.label }}</label>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-surface-200 dark:border-surface-700">
            <ThresholdEditor module="achats" title="Seuils d'approbation — Bons de commande (Achats)" currency="XOF" />
            <ThresholdEditor module="accounting" title="Seuils d'approbation — Factures (Comptabilité)" currency="XOF" />
          </div>

          <div class="pt-2 border-t border-surface-200 dark:border-surface-700">
            <LeavePolicyEditor />
          </div>

          <div class="pt-2 border-t border-surface-200 dark:border-surface-700">
            <SlaPolicyEditor />
          </div>
        </div>

        <!-- Step 5: Applications -->
        <div v-else-if="currentStepKey === 'apps'" class="space-y-4">
          <StepHeader icon="pi-desktop" title="Applications" subtitle="Interfaces à activer pour votre équipe" />

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="app in appOptions" :key="app.value" class="flex items-center gap-3 p-3 border rounded-lg border-surface-200 dark:border-surface-700">
              <Checkbox v-model="apps[app.value]" :binary="true" :input-id="`app-${app.value}`" />
              <label :for="`app-${app.value}`" class="text-sm text-surface-700 dark:text-surface-300">{{ app.label }}</label>
            </div>
          </div>
        </div>

        <!-- Step 6: Terminé -->
        <div v-else-if="currentStepKey === 'complete'" class="space-y-6">
          <div v-if="!wizardCompleted" class="text-center py-6">
            <div class="w-16 h-16 bg-primary-50 dark:bg-primary-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="pi pi-check text-primary-600 dark:text-primary-400 text-2xl" />
            </div>
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Prêt à terminer</h2>
            <p class="text-surface-500 mt-2">Vérifiez le récapitulatif puis cliquez sur Terminer.</p>
          </div>

          <div v-if="!wizardCompleted" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-surface-50 dark:bg-surface-700 rounded-xl p-4 space-y-1 text-sm">
              <h3 class="font-semibold text-surface-700 dark:text-surface-300 mb-2">Entreprise</h3>
              <p><span class="text-surface-500">Nom :</span> {{ company.company_name || '—' }}</p>
              <p><span class="text-surface-500">Pays :</span> {{ countryLabel }}</p>
              <p><span class="text-surface-500">Devise :</span> {{ company.currency_code || '—' }}</p>
            </div>
            <div class="bg-surface-50 dark:bg-surface-700 rounded-xl p-4 space-y-1 text-sm">
              <h3 class="font-semibold text-surface-700 dark:text-surface-300 mb-2">Modules actifs</h3>
              <p>{{ activeModuleCount }} module(s) activé(s) sur {{ moduleCatalog.length }}</p>
            </div>
          </div>

          <div v-if="wizardCompleted" class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl flex items-center gap-3">
            <i class="pi pi-check-circle text-green-600 text-2xl" />
            <div>
              <p class="font-semibold text-green-800 dark:text-green-200">Configuration terminée</p>
              <p class="text-sm text-green-700 dark:text-green-300">Vous pouvez maintenant importer vos données existantes, ou continuer sans.</p>
            </div>
          </div>

          <!-- Optional data import sub-flow, only reachable after complete() -->
          <div v-if="wizardCompleted && !importSkipped" class="space-y-4 pt-4 border-t border-surface-200 dark:border-surface-700">
            <ImportDataFlow @skip="importSkipped = true" />
          </div>
          <div v-else-if="wizardCompleted && importSkipped" class="text-center py-6 text-surface-400 pt-4 border-t border-surface-200 dark:border-surface-700">
            <i class="pi pi-forward text-4xl mb-3 block" />
            <p>Import ignoré — vous pourrez importer vos données plus tard depuis la page Configuration.</p>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <div class="flex items-center justify-between">
        <Button v-if="!isFirstStep && !wizardCompleted" icon="pi pi-arrow-left" label="Précédent" severity="secondary" outlined @click="prevStep" />
        <div v-else />

        <div class="flex gap-3">
          <Button v-if="wizardCompleted" icon="pi pi-home" label="Aller au tableau de bord" @click="router.visit('/dashboard')" />
          <Button v-else-if="currentStepKey === 'complete'" icon="pi pi-check" label="Terminer" :loading="saving" @click="completeWizard" />
          <Button v-else icon-pos="right" icon="pi pi-arrow-right" label="Suivant" :loading="saving" @click="nextStep" />
        </div>
      </div>

      <!-- Chantier 32.10 (deep 14-layer audit, AI layer): the 6-step wizard
           previously had zero AI-assist integration at all — only the
           import sub-flow above (via ImportDataFlow's own step) had one,
           via SetupIndex.vue. `useAiAssistant()`'s composable resolves its
           action once at mount time and doesn't react to a changing step,
           so this page fetches guidance itself (same request shape/
           endpoint) and re-fetches whenever the wizard step changes. -->
      <AIAssistantPanel v-if="wizardGuidance" :guidance="wizardGuidance" class="fixed bottom-4 right-4 z-50" />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, defineComponent, h } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import InputSwitch from 'primevue/inputswitch'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import ProgressSpinner from 'primevue/progressspinner'
import AppLayout from '@/Layouts/AppLayout.vue'
import WorkflowStepper from '@/Components/UI/WorkflowStepper.vue'
import ImportDataFlow from '../Components/ImportDataFlow.vue'
import ThresholdEditor from '../Components/ThresholdEditor.vue'
import LeavePolicyEditor from '../Components/LeavePolicyEditor.vue'
import SlaPolicyEditor from '../Components/SlaPolicyEditor.vue'

// ────────────────────────────────────────────────────────────
// Small local helpers (kept in-file: purely presentational, not reused elsewhere)
// ────────────────────────────────────────────────────────────

const StepHeader = defineComponent({
  props: { icon: String, title: String, subtitle: String },
  setup(props) {
    return () => h('div', { class: 'text-center mb-6' }, [
      h('div', { class: 'w-16 h-16 bg-primary-50 dark:bg-primary-900/30 rounded-full flex items-center justify-center mx-auto mb-4' }, [
        h('i', { class: `pi ${props.icon} text-primary-600 dark:text-primary-400 text-2xl` }),
      ]),
      h('h2', { class: 'text-xl font-semibold text-surface-900 dark:text-surface-50' }, props.title),
      h('p', { class: 'text-surface-500 mt-2' }, props.subtitle),
    ])
  },
})

const Field = defineComponent({
  props: { label: String, required: Boolean, error: String },
  setup(props, { slots }) {
    return () => h('div', { class: 'space-y-2' }, [
      h('label', { class: 'block text-sm font-medium text-surface-700 dark:text-surface-300' }, [
        props.label,
        props.required ? h('span', { class: 'text-red-500' }, ' *') : null,
      ]),
      slots.default?.(),
      props.error ? h('small', { class: 'text-red-500' }, props.error) : null,
    ])
  },
})

// ────────────────────────────────────────────────────────────
// Wizard state — mirrors Modules\Setup\Services\SetupWizardService's
// 6 real steps (company/admin/modules/workflows/apps/complete),
// server-side-initialized from SetupWebController::wizard().
// ────────────────────────────────────────────────────────────

interface WizardState {
  step: number
  company: Record<string, unknown> | null
  admin: Record<string, unknown> | null
  modules: string[]
  workflows: { approval_required?: boolean; notification_channels?: string[] }
  apps: Record<string, boolean>
  completed: boolean
}

const props = defineProps<{ state: WizardState }>()

const STEP_KEYS = ['company', 'admin', 'modules', 'workflows', 'apps', 'complete'] as const
type StepKey = typeof STEP_KEYS[number]

const currentStepIndex = ref(Math.max(0, Math.min(props.state.step, STEP_KEYS.length - 1)))
const currentStepKey = computed<StepKey>(() => STEP_KEYS[currentStepIndex.value])
const isFirstStep = computed(() => currentStepIndex.value === 0)
const wizardCompleted = ref(props.state.completed)
const importSkipped = ref(false)
const saving = ref(false)
const errors = reactive<Record<string, string>>({})

// Chantier 32.10: real, per-step AI guidance — see the template's own
// AIAssistantPanel comment for why this isn't the shared useAiAssistant()
// composable (its action is fixed at mount time, not reactive to a
// changing wizard step).
interface WizardGuidance {
  enabled: boolean
  what_to_do: string
  how_to_do: string[]
  decision_indicators: Array<{ label: string; value: string; status: string }>
  warnings: string[]
  next_actions: Array<{ label: string; action: string; module: string }>
  tips: string[]
}
const wizardGuidance = ref<WizardGuidance | null>(null)
const fetchWizardGuidance = async () => {
  try {
    const locale = document.documentElement.lang || 'fr'
    const { data } = await axios.post('/api/v1/setup/ai/assist', {
      module: 'Setup',
      action: `wizard_${currentStepKey.value}`,
      context: { step: currentStepIndex.value + 1 },
      locale,
    })
    wizardGuidance.value = data as WizardGuidance
  } catch {
    wizardGuidance.value = null
  }
}
watch(currentStepKey, fetchWizardGuidance, { immediate: true })

const wizardSteps = [
  { key: 'company', label: 'Entreprise', icon: 'pi pi-building' },
  { key: 'admin', label: 'Administrateur', icon: 'pi pi-user' },
  { key: 'modules', label: 'Modules', icon: 'pi pi-th-large' },
  { key: 'workflows', label: 'Règles', icon: 'pi pi-sliders-h' },
  { key: 'apps', label: 'Applications', icon: 'pi pi-desktop' },
  { key: 'complete', label: 'Terminé', icon: 'pi pi-check' },
]

const company = reactive({
  company_name: (props.state.company?.company_name as string) ?? '',
  industry: (props.state.company?.industry as string) ?? '',
  country_code: (props.state.company?.country_code as string) ?? '',
  currency_code: (props.state.company?.currency_code as string) ?? '',
  email: (props.state.company?.email as string) ?? '',
  phone: (props.state.company?.phone as string) ?? '',
})

const admin = reactive({
  name: (props.state.admin?.name as string) ?? '',
  locale: (props.state.admin?.locale as string) ?? 'fr',
  timezone: (props.state.admin?.timezone as string) ?? '',
})

const workflows = reactive({
  approval_required: props.state.workflows?.approval_required ?? true,
  notification_channels: [...(props.state.workflows?.notification_channels ?? ['email'])],
})

// Trimmed to what this trimmed 27-module scope actually ships — the
// original WideHalo-era list also offered webapp-ecommerce/mobile, which
// don't correspond to anything real here (Ecommerce is out of scope, and
// there is no separate mobile app in life-mdg-erp).
const appOptions = [
  { value: 'webapp', label: 'Application web' },
  { value: 'api', label: 'API' },
]
const apps = reactive<Record<string, boolean>>({
  webapp: (props.state.apps?.webapp as boolean) ?? true,
  api: (props.state.apps?.api as boolean) ?? true,
})

// ────────────────────────────────────────────────────────────
// Options
// ────────────────────────────────────────────────────────────

const industryOptions = [
  { label: 'Commerce de détail', value: 'retail' },
  { label: 'Commerce de gros', value: 'wholesale' },
  { label: 'Industrie / Fabrication', value: 'manufacturing' },
  { label: 'Services', value: 'services' },
  { label: 'Agriculture', value: 'agriculture' },
  { label: 'Construction / BTP', value: 'construction' },
  { label: 'Transport / Logistique', value: 'transport' },
  { label: 'Santé', value: 'healthcare' },
  { label: 'Éducation', value: 'education' },
  { label: 'Finance / Banque', value: 'finance' },
  { label: 'Hôtellerie / Restauration', value: 'hospitality' },
  { label: 'Autre', value: 'other' },
]

const countryOptions = [
  { label: 'Madagascar', value: 'MG' },
  { label: 'Sénégal', value: 'SN' },
  { label: 'Côte d\'Ivoire', value: 'CI' },
  { label: 'Mali', value: 'ML' },
  { label: 'Burkina Faso', value: 'BF' },
  { label: 'Cameroun', value: 'CM' },
  { label: 'Gabon', value: 'GA' },
  { label: 'Nigeria', value: 'NG' },
  { label: 'Ghana', value: 'GH' },
  { label: 'Kenya', value: 'KE' },
  { label: 'Maroc', value: 'MA' },
  { label: 'Tunisie', value: 'TN' },
  { label: 'France', value: 'FR' },
]

const countryCurrencyMap: Record<string, string> = {
  MG: 'MGA', SN: 'XOF', CI: 'XOF', ML: 'XOF', BF: 'XOF',
  CM: 'XAF', GA: 'XAF',
  NG: 'NGN', GH: 'GHS', KE: 'KES', MA: 'MAD', TN: 'TND', FR: 'EUR',
}

const currencyOptions = [
  { label: 'MGA — Ariary malgache', value: 'MGA' },
  { label: 'XOF — Franc CFA UEMOA', value: 'XOF' },
  { label: 'XAF — Franc CFA CEMAC', value: 'XAF' },
  { label: 'NGN — Naira nigérian', value: 'NGN' },
  { label: 'GHS — Cedi ghanéen', value: 'GHS' },
  { label: 'KES — Shilling kényan', value: 'KES' },
  { label: 'MAD — Dirham marocain', value: 'MAD' },
  { label: 'TND — Dinar tunisien', value: 'TND' },
  { label: 'EUR — Euro', value: 'EUR' },
  { label: 'USD — Dollar américain', value: 'USD' },
]

const localeOptions = [
  { label: 'Français', value: 'fr' },
  { label: 'English', value: 'en' },
  { label: 'Português', value: 'pt' },
  { label: 'Español', value: 'es' },
]

const notificationChannelOptions = [
  { value: 'email', label: 'Email' },
  { value: 'sms', label: 'SMS' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'push', label: 'Notification push' },
]

const countryLabel = computed(() => countryOptions.find(o => o.value === company.country_code)?.label ?? company.country_code)

const onCountryChange = (event: { value: string }) => {
  const suggested = countryCurrencyMap[event.value]
  if (suggested) company.currency_code = suggested
}

// ────────────────────────────────────────────────────────────
// Modules step
// ────────────────────────────────────────────────────────────

interface ModuleEntry {
  name: string
  description: string
  is_active: boolean
  requires: string[]
}

// Chantier 32.10 (deep 14-layer audit, security layer): every mutating
// fetch() call below (module toggle, each wizard step, complete) was
// missing a CSRF header — this app runs Sanctum's statefulApi(), which
// activates real CSRF verification on same-origin browser requests; a raw
// fetch() never attaches one automatically, unlike axios. Invisible to
// this file's own Pest HTTP test (VerifyCsrfToken::runningUnitTests()
// unconditionally bypasses the check whenever APP_ENV=testing), so only a
// real browser-shaped request surfaces it — the exact same bug class
// already found and fixed for 9 Inventory/Logistics pages at Chantier 19.
// This means the real onboarding wizard — every one of its 6 steps, plus
// module toggling — has been 419'ing on every real (non-test) submission.
const getCsrf = (): string =>
  (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''

const moduleCatalog = ref<ModuleEntry[]>([])
const modulesLoading = ref(false)
const moduleToggling = ref<string | null>(null)
const moduleToggleError = ref('')
const activeModuleCount = computed(() => moduleCatalog.value.filter(m => m.is_active).length)

const fetchModuleCatalog = async () => {
  modulesLoading.value = true
  try {
    const res = await fetch('/api/v1/setup/wizard/modules/catalog', { headers: { Accept: 'application/json' } })
    const json = await res.json()
    moduleCatalog.value = json.data ?? []
  } finally {
    modulesLoading.value = false
  }
}

const toggleModule = async (mod: ModuleEntry, active: boolean) => {
  moduleToggling.value = mod.name
  moduleToggleError.value = ''
  try {
    const res = await fetch(`/api/v1/setup/v1/admin/modules/${mod.name}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify({ is_active: active }),
    })
    const json = await res.json()
    if (!res.ok) {
      moduleToggleError.value = json.message ?? 'Action refusée.'
      return
    }
    mod.is_active = json.data.is_active
  } finally {
    moduleToggling.value = null
  }
}

// ────────────────────────────────────────────────────────────
// API calls to the 6-step wizard
// ────────────────────────────────────────────────────────────

const postStep = async (path: string, payload: Record<string, unknown>): Promise<boolean> => {
  saving.value = true
  Object.keys(errors).forEach(k => delete errors[k])
  try {
    const res = await fetch(`/api/v1/setup/wizard/${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(payload),
    })
    const json = await res.json()
    if (!res.ok) {
      if (json.errors) {
        for (const [field, messages] of Object.entries(json.errors as Record<string, string[]>)) {
          errors[field] = messages[0]
        }
      }
      return false
    }
    return true
  } catch (e) {
    console.error(`Erreur étape ${path}`, e)
    return false
  } finally {
    saving.value = false
  }
}

const saveCurrentStep = async (): Promise<boolean> => {
  switch (currentStepKey.value) {
    case 'company':
      if (!company.company_name.trim() || !company.country_code) {
        if (!company.company_name.trim()) errors.company_name = 'Le nom est requis'
        if (!company.country_code) errors.country_code = 'Le pays est requis'
        return false
      }
      return postStep('company', { ...company })
    case 'admin':
      return postStep('admin', { ...admin })
    case 'modules':
      return postStep('modules', { modules: moduleCatalog.value.filter(m => m.is_active).map(m => m.name) })
    case 'workflows':
      return postStep('workflows', { ...workflows })
    case 'apps':
      return postStep('apps', { apps: { ...apps } })
    default:
      return true
  }
}

const nextStep = async () => {
  const ok = await saveCurrentStep()
  if (!ok) return
  currentStepIndex.value = Math.min(currentStepIndex.value + 1, STEP_KEYS.length - 1)
}

const prevStep = () => {
  currentStepIndex.value = Math.max(currentStepIndex.value - 1, 0)
}

const completeWizard = async () => {
  saving.value = true
  try {
    const res = await fetch('/api/v1/setup/wizard/complete', {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
    })
    if (res.ok) wizardCompleted.value = true
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (currentStepKey.value === 'modules' || !moduleCatalog.value.length) {
    fetchModuleCatalog()
  }
})
</script>
