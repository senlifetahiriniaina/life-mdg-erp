<template>
  <AppLayout>
    <Head title="Paramètres — WideHalo ERP" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Paramètres
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Configurez votre entreprise et vos préférences ERP
          </p>
        </div>
        <div class="flex items-center gap-3">
          <span v-if="saveSuccess" class="text-green-600 text-sm font-medium flex items-center gap-1">
            <span class="inline-block w-4 h-4 rounded-full bg-green-500 text-white text-center leading-4 text-xs">✓</span>
            Enregistré
          </span>
          <Button
            icon="pi pi-save"
            label="Enregistrer"
            :loading="saving"
            @click="saveSettings"
          />
        </div>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" class="mb-4" />

      <!-- Settings tabs -->
      <TabView v-model:activeIndex="activeTab">

        <!-- ── Général ── -->
        <TabPanel header="Général">
          <div class="space-y-6 pt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Nom de l'entreprise</label>
                <InputText v-model="settings.company_name" class="w-full" placeholder="Acme Sarl" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Numéro SIRET / RC</label>
                <InputText v-model="settings.company_registration" class="w-full" placeholder="RC-SN-2024-B-1234" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Email</label>
                <InputText v-model="settings.company_email" type="email" class="w-full" placeholder="contact@entreprise.com" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Téléphone</label>
                <InputText v-model="settings.company_phone" class="w-full" placeholder="+221 77 000 00 00" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">URL du logo</label>
                <InputText v-model="settings.logo_url" class="w-full" placeholder="https://..." />
                <img v-if="settings.logo_url" :src="settings.logo_url" alt="Logo" class="h-10 mt-1 rounded object-contain border border-surface-200" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Site web</label>
                <InputText v-model="settings.website" class="w-full" placeholder="https://..." />
              </div>
              <div class="space-y-2 md:col-span-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Adresse</label>
                <Textarea v-model="settings.company_address" class="w-full" rows="3" placeholder="Rue des Almadies, Dakar, Sénégal" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Fuseau horaire</label>
                <Select
                  v-model="settings.timezone"
                  :options="timezoneOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                  filter
                />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Devise par défaut</label>
                <Select
                  v-model="settings.currency"
                  :options="currencyOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Langue de l'interface</label>
                <Select
                  v-model="settings.locale"
                  :options="localeOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Pays</label>
                <Select
                  v-model="settings.country"
                  :options="countryOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                  filter
                />
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- ── Notifications ── -->
        <TabPanel header="Notifications">
          <div class="space-y-6 pt-4">
            <p class="text-xs text-surface-400">Choisissez les canaux et événements pour lesquels vous souhaitez recevoir des notifications.</p>

            <!-- Channel header row -->
            <div class="grid grid-cols-4 gap-2 text-xs font-semibold text-surface-500 uppercase px-1 pb-1 border-b border-surface-200 dark:border-surface-700">
              <div class="col-span-1">Événement</div>
              <div class="text-center">E-mail</div>
              <div class="text-center">SMS</div>
              <div class="text-center">Push</div>
            </div>

            <div
              v-for="notif in notificationEvents"
              :key="notif.key"
              class="grid grid-cols-4 gap-2 items-center py-2 border-b border-surface-100 dark:border-surface-700 last:border-0"
            >
              <div>
                <p class="font-medium text-surface-700 dark:text-surface-300 text-sm">{{ notif.label }}</p>
                <p class="text-xs text-surface-400 mt-0.5">{{ notif.description }}</p>
              </div>
              <div class="flex justify-center">
                <ToggleSwitch v-model="settings.notifications[notif.key + '_email']" />
              </div>
              <div class="flex justify-center">
                <ToggleSwitch v-model="settings.notifications[notif.key + '_sms']" />
              </div>
              <div class="flex justify-center">
                <ToggleSwitch v-model="settings.notifications[notif.key + '_push']" />
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- ── Sécurité ── -->
        <TabPanel header="Sécurité">
          <div class="space-y-6 pt-4">
            <!-- 2FA -->
            <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4 space-y-3 border border-surface-200 dark:border-surface-700">
              <h3 class="font-semibold text-surface-800 dark:text-surface-200 text-sm">Authentification</h3>
              <div class="flex items-center justify-between py-2">
                <div>
                  <p class="font-medium text-surface-700 dark:text-surface-300 text-sm">Double authentification (2FA)</p>
                  <p class="text-xs text-surface-400 mt-0.5">Ajoute une couche de sécurité supplémentaire à la connexion</p>
                </div>
                <ToggleSwitch v-model="settings.two_factor_enabled" />
              </div>
              <div class="flex items-center justify-between py-2">
                <div>
                  <p class="font-medium text-surface-700 dark:text-surface-300 text-sm">Session unique</p>
                  <p class="text-xs text-surface-400 mt-0.5">Déconnecte les autres sessions actives à la connexion</p>
                </div>
                <ToggleSwitch v-model="settings.single_session" />
              </div>
            </div>

            <!-- Session timeout -->
            <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4 space-y-3 border border-surface-200 dark:border-surface-700">
              <h3 class="font-semibold text-surface-800 dark:text-surface-200 text-sm">Timeout de session</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Délai d'inactivité (minutes)</label>
                  <Select
                    v-model="settings.session_timeout"
                    :options="sessionTimeoutOptions"
                    option-label="label"
                    option-value="value"
                    class="w-full"
                  />
                </div>
              </div>
            </div>

            <!-- Password policy -->
            <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4 space-y-3 border border-surface-200 dark:border-surface-700">
              <h3 class="font-semibold text-surface-800 dark:text-surface-200 text-sm">Politique de mot de passe</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Longueur minimale</label>
                  <Select
                    v-model="settings.password_min_length"
                    :options="[{label:'8 caractères',value:8},{label:'10 caractères',value:10},{label:'12 caractères',value:12},{label:'16 caractères',value:16}]"
                    option-label="label"
                    option-value="value"
                    class="w-full"
                  />
                </div>
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Expiration (jours)</label>
                  <Select
                    v-model="settings.password_expiry_days"
                    :options="[{label:'Jamais',value:0},{label:'30 jours',value:30},{label:'60 jours',value:60},{label:'90 jours',value:90}]"
                    option-label="label"
                    option-value="value"
                    class="w-full"
                  />
                </div>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                <div class="flex items-center justify-between py-1">
                  <p class="text-sm text-surface-700 dark:text-surface-300">Majuscule obligatoire</p>
                  <ToggleSwitch v-model="settings.password_require_uppercase" />
                </div>
                <div class="flex items-center justify-between py-1">
                  <p class="text-sm text-surface-700 dark:text-surface-300">Chiffre obligatoire</p>
                  <ToggleSwitch v-model="settings.password_require_number" />
                </div>
                <div class="flex items-center justify-between py-1">
                  <p class="text-sm text-surface-700 dark:text-surface-300">Caractère spécial obligatoire</p>
                  <ToggleSwitch v-model="settings.password_require_special" />
                </div>
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- ── Intégrations ── -->
        <TabPanel header="Intégrations">
          <div class="space-y-6 pt-4">
            <!-- API Key -->
            <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4 space-y-3 border border-surface-200 dark:border-surface-700">
              <h3 class="font-semibold text-surface-800 dark:text-surface-200 text-sm">Clé API</h3>
              <div class="flex items-center gap-3">
                <InputText
                  :value="showApiKey ? settings.api_key : maskApiKey(settings.api_key)"
                  readonly
                  class="w-full font-mono text-sm"
                />
                <Button
                  :icon="showApiKey ? 'pi pi-eye-slash' : 'pi pi-eye'"
                  text
                  @click="showApiKey = !showApiKey"
                  v-tooltip="showApiKey ? 'Masquer' : 'Afficher'"
                />
                <Button
                  icon="pi pi-refresh"
                  label="Regénérer"
                  severity="secondary"
                  size="small"
                  :loading="regeneratingKey"
                  @click="regenerateApiKey"
                />
              </div>
              <p class="text-xs text-surface-400">Utilisez cette clé pour accéder à l'API REST WideHalo depuis vos applications externes.</p>
            </div>

            <!-- Webhook URL -->
            <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4 space-y-3 border border-surface-200 dark:border-surface-700">
              <h3 class="font-semibold text-surface-800 dark:text-surface-200 text-sm">Webhook sortant</h3>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">URL de destination</label>
                <InputText v-model="settings.webhook_url" class="w-full" placeholder="https://hooks.example.com/widehalo" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Secret HMAC (optionnel)</label>
                <InputText v-model="settings.webhook_secret" class="w-full font-mono" placeholder="••••••••••••••••" type="password" />
              </div>
              <div class="flex items-center justify-between py-1">
                <p class="text-sm text-surface-700 dark:text-surface-300">Activer les webhooks</p>
                <ToggleSwitch v-model="settings.webhook_enabled" />
              </div>
              <p class="text-xs text-surface-400">Les webhooks envoient les événements ERP (factures, commandes, RH…) en temps réel vers votre URL.</p>
            </div>

            <!-- External connectors (info) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <div
                v-for="connector in connectors"
                :key="connector.key"
                class="flex items-center gap-3 p-3 rounded-xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800"
              >
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-lg" :class="connector.bg">{{ connector.icon }}</div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-surface-700 dark:text-surface-300 truncate">{{ connector.label }}</p>
                  <p class="text-xs text-surface-400">{{ connector.status }}</p>
                </div>
                <Button label="Config" size="small" text severity="secondary" />
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- ── OHADA / Fiscal ── -->
        <TabPanel header="OHADA / Fiscal">
          <div class="space-y-6 pt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Plan comptable</label>
                <Select
                  v-model="settings.ohada_plan"
                  :options="ohadaPlanOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
                <p class="text-xs text-surface-400">SYSCOHADA révisé 2017 est recommandé pour la plupart des pays OHADA.</p>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Début de l'exercice fiscal</label>
                <Select
                  v-model="settings.fiscal_year_start"
                  :options="fiscalYearStartOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Taux TVA par défaut (%)</label>
                <InputText v-model="settings.default_vat_rate" class="w-full" type="number" min="0" max="100" step="0.5" placeholder="18" />
                <p class="text-xs text-surface-400">Sénégal: 18% — Cameroun: 19.25% — France: 20% — Kenya: 16%</p>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Taux IS (%)</label>
                <InputText v-model="settings.corporate_tax_rate" class="w-full" type="number" min="0" max="100" step="0.5" placeholder="30" />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Format des numéros de facture</label>
                <InputText v-model="settings.invoice_number_format" class="w-full" placeholder="FAC-{YYYY}-{NNN}" />
                <p class="text-xs text-surface-400">Variables: {YYYY} année, {MM} mois, {NNN} séquence</p>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Délai de paiement par défaut (jours)</label>
                <Select
                  v-model="settings.default_payment_terms"
                  :options="[{label:'Comptant',value:0},{label:'7 jours',value:7},{label:'15 jours',value:15},{label:'30 jours',value:30},{label:'60 jours',value:60},{label:'90 jours',value:90}]"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </div>
            </div>

            <!-- OHADA alerts -->
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-200 dark:border-amber-800 space-y-2">
              <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Conformité OHADA</p>
              <div class="flex items-center justify-between py-1">
                <p class="text-sm text-surface-700 dark:text-surface-300">Alertes de conformité SYSCOHADA</p>
                <ToggleSwitch v-model="settings.ohada_compliance_alerts" />
              </div>
              <div class="flex items-center justify-between py-1">
                <p class="text-sm text-surface-700 dark:text-surface-300">Validation des écritures selon OHADA</p>
                <ToggleSwitch v-model="settings.ohada_entry_validation" />
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- ── Apparence ── -->
        <TabPanel header="Apparence">
          <div class="space-y-6 pt-4">
            <!-- Theme -->
            <div class="space-y-3">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Thème</label>
              <div class="grid grid-cols-3 gap-3">
                <button
                  v-for="theme in themeOptions"
                  :key="theme.value"
                  @click="settings.theme = theme.value"
                  :class="[
                    'flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all',
                    settings.theme === theme.value
                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                      : 'border-surface-200 dark:border-surface-700 hover:border-surface-300'
                  ]"
                >
                  <span class="text-2xl">{{ theme.icon }}</span>
                  <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ theme.label }}</span>
                </button>
              </div>
            </div>

            <!-- Sidebar -->
            <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700 space-y-3">
              <h3 class="font-semibold text-surface-800 dark:text-surface-200 text-sm">Barre latérale</h3>
              <div class="flex items-center justify-between py-1">
                <div>
                  <p class="text-sm text-surface-700 dark:text-surface-300">Réduite par défaut</p>
                  <p class="text-xs text-surface-400 mt-0.5">La sidebar affiche uniquement les icônes au démarrage</p>
                </div>
                <ToggleSwitch v-model="settings.sidebar_collapsed" />
              </div>
              <div class="flex items-center justify-between py-1">
                <div>
                  <p class="text-sm text-surface-700 dark:text-surface-300">Mode compact</p>
                  <p class="text-xs text-surface-400 mt-0.5">Réduit l'espacement dans les listes et tableaux</p>
                </div>
                <ToggleSwitch v-model="settings.compact_mode" />
              </div>
            </div>

            <!-- Date / number format -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Format de date</label>
                <Select
                  v-model="settings.date_format"
                  :options="[{label:'DD/MM/YYYY',value:'DD/MM/YYYY'},{label:'MM/DD/YYYY',value:'MM/DD/YYYY'},{label:'YYYY-MM-DD',value:'YYYY-MM-DD'}]"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Séparateur décimal</label>
                <Select
                  v-model="settings.decimal_separator"
                  :options="[{label:'Virgule : 1 234,56',value:','},{label:'Point : 1,234.56',value:'.'}]"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </div>
            </div>
          </div>
        </TabPanel>

      </TabView>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { Head, usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import ToggleSwitch from 'primevue/toggleswitch'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const roles = computed(() => (page.props.auth as any)?.user?.roles?.map((r: any) => r.name) || [])
const isAdmin = computed(() => roles.value.some((r: string) => ['admin', 'super-admin'].includes(r)))

const saving = ref(false)
const saveSuccess = ref(false)
const showApiKey = ref(false)
const regeneratingKey = ref(false)
const activeTab = ref(0)

const settings = reactive({
  // Général
  company_name: '',
  company_registration: '',
  company_email: '',
  company_phone: '',
  company_address: '',
  logo_url: '',
  website: '',
  country: 'SN',
  currency: 'XOF',
  locale: 'fr',
  timezone: 'Africa/Dakar',
  // Notifications (per-event per-channel)
  notifications: {
    invoice_email: true,  invoice_sms: false,  invoice_push: true,
    payment_email: true,  payment_sms: true,   payment_push: true,
    stock_email: true,    stock_sms: false,     stock_push: true,
    leave_email: true,    leave_sms: false,     leave_push: false,
    report_email: true,   report_sms: false,    report_push: false,
  } as Record<string, boolean>,
  // Sécurité
  two_factor_enabled: false,
  single_session: false,
  session_timeout: 60,
  password_min_length: 8,
  password_expiry_days: 90,
  password_require_uppercase: true,
  password_require_number: true,
  password_require_special: false,
  // Intégrations
  api_key: 'wh_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
  webhook_url: '',
  webhook_secret: '',
  webhook_enabled: false,
  // OHADA
  ohada_plan: 'syscohada_2017',
  fiscal_year_start: '01-01',
  default_vat_rate: 18,
  corporate_tax_rate: 30,
  invoice_number_format: 'FAC-{YYYY}-{NNN}',
  default_payment_terms: 30,
  ohada_compliance_alerts: true,
  ohada_entry_validation: true,
  // Apparence
  theme: 'auto',
  sidebar_collapsed: false,
  compact_mode: false,
  date_format: 'DD/MM/YYYY',
  decimal_separator: ',',
})

// ── Options ──────────────────────────────────────────────────────────────

const countryOptions = [
  { label: 'Sénégal', value: 'SN' },
  { label: 'Côte d\'Ivoire', value: 'CI' },
  { label: 'Cameroun', value: 'CM' },
  { label: 'Mali', value: 'ML' },
  { label: 'Burkina Faso', value: 'BF' },
  { label: 'Niger', value: 'NE' },
  { label: 'Togo', value: 'TG' },
  { label: 'Bénin', value: 'BJ' },
  { label: 'Guinée', value: 'GN' },
  { label: 'Madagascar', value: 'MG' },
  { label: 'Kenya', value: 'KE' },
  { label: 'Ghana', value: 'GH' },
  { label: 'Nigeria', value: 'NG' },
  { label: 'Maroc', value: 'MA' },
  { label: 'France', value: 'FR' },
  { label: 'Inde', value: 'IN' },
  { label: 'Chine', value: 'CN' },
]

const currencyOptions = [
  { label: 'XOF — Franc CFA UEMOA', value: 'XOF' },
  { label: 'XAF — Franc CFA CEMAC', value: 'XAF' },
  { label: 'MGA — Ariary malgache', value: 'MGA' },
  { label: 'KES — Shilling kényan', value: 'KES' },
  { label: 'GHS — Cedi ghanéen', value: 'GHS' },
  { label: 'NGN — Naira nigérian', value: 'NGN' },
  { label: 'MAD — Dirham marocain', value: 'MAD' },
  { label: 'EUR — Euro', value: 'EUR' },
  { label: 'USD — Dollar américain', value: 'USD' },
  { label: 'CNY — Yuan chinois', value: 'CNY' },
  { label: 'INR — Roupie indienne', value: 'INR' },
  { label: 'JPY — Yen japonais', value: 'JPY' },
]

const localeOptions = [
  { label: 'Français', value: 'fr' },
  { label: 'English', value: 'en' },
  { label: 'Español', value: 'es' },
  { label: 'Português', value: 'pt' },
  { label: 'العربية', value: 'ar' },
  { label: 'Kiswahili', value: 'sw' },
  { label: 'Malagasy', value: 'mg' },
  { label: 'Hausa', value: 'ha' },
  { label: '中文', value: 'zh' },
  { label: 'हिन्दी', value: 'hi' },
]

const timezoneOptions = [
  { label: 'Africa/Dakar (UTC+0)', value: 'Africa/Dakar' },
  { label: 'Africa/Abidjan (UTC+0)', value: 'Africa/Abidjan' },
  { label: 'Africa/Douala (UTC+1)', value: 'Africa/Douala' },
  { label: 'Africa/Lagos (UTC+1)', value: 'Africa/Lagos' },
  { label: 'Africa/Nairobi (UTC+3)', value: 'Africa/Nairobi' },
  { label: 'Indian/Antananarivo (UTC+3)', value: 'Indian/Antananarivo' },
  { label: 'Africa/Casablanca (UTC+1)', value: 'Africa/Casablanca' },
  { label: 'Europe/Paris (UTC+1/+2)', value: 'Europe/Paris' },
  { label: 'Asia/Kolkata (UTC+5:30)', value: 'Asia/Kolkata' },
  { label: 'Asia/Shanghai (UTC+8)', value: 'Asia/Shanghai' },
  { label: 'Asia/Tokyo (UTC+9)', value: 'Asia/Tokyo' },
]

const sessionTimeoutOptions = [
  { label: '15 minutes', value: 15 },
  { label: '30 minutes', value: 30 },
  { label: '1 heure', value: 60 },
  { label: '4 heures', value: 240 },
  { label: '8 heures', value: 480 },
  { label: 'Jamais', value: 0 },
]

const ohadaPlanOptions = [
  { label: 'SYSCOHADA révisé 2017', value: 'syscohada_2017' },
  { label: 'SYSCEBNL (non-profits)', value: 'syscebnl' },
  { label: 'IFRS', value: 'ifrs' },
  { label: 'PCG France', value: 'pcg_fr' },
]

const fiscalYearStartOptions = [
  { label: '1er janvier', value: '01-01' },
  { label: '1er avril', value: '04-01' },
  { label: '1er juillet', value: '07-01' },
  { label: '1er octobre', value: '10-01' },
]

const themeOptions = [
  { value: 'light', label: 'Clair', icon: '☀️' },
  { value: 'dark', label: 'Sombre', icon: '🌙' },
  { value: 'auto', label: 'Auto', icon: '💻' },
]

const notificationEvents = [
  { key: 'invoice', label: 'Factures', description: 'Nouvelle facture, rappel d\'échéance' },
  { key: 'payment', label: 'Paiements', description: 'Paiement reçu ou en retard' },
  { key: 'stock', label: 'Stock', description: 'Alerte de stock sous le seuil minimum' },
  { key: 'leave', label: 'Congés RH', description: 'Demande, approbation ou refus' },
  { key: 'report', label: 'Rapports', description: 'Rapport généré et disponible' },
]

const connectors = [
  { key: 'google', label: 'Google Workspace', icon: 'G', bg: 'bg-blue-100 text-blue-700', status: 'Non connecté' },
  { key: 'orange_money', label: 'Orange Money', icon: '🟠', bg: 'bg-orange-100', status: 'Non connecté' },
  { key: 'wave', label: 'Wave', icon: '🌊', bg: 'bg-cyan-100', status: 'Non connecté' },
  { key: 'shopify', label: 'Shopify', icon: '🛍', bg: 'bg-green-100', status: 'Non connecté' },
  { key: 'zapier', label: 'Zapier', icon: '⚡', bg: 'bg-orange-100', status: 'Non connecté' },
  { key: 'mtn', label: 'MTN MoMo', icon: '📱', bg: 'bg-yellow-100', status: 'Non connecté' },
]

// ── Helpers ──────────────────────────────────────────────────────────────

const maskApiKey = (key: string) => {
  if (!key) return ''
  return key.substring(0, 8) + '••••••••••••••••••••' + key.slice(-4)
}

// ── API calls ────────────────────────────────────────────────────────────

const fetchSettings = async () => {
  try {
    const res = await fetch('/api/v1/settings', { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const data = await res.json()
      Object.assign(settings, data)
    }
  } catch (e) {
    // Use defaults on failure
  }
}

const saveSettings = async () => {
  saving.value = true
  saveSuccess.value = false
  try {
    await fetch('/api/v1/settings', {
      method: 'PUT',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(settings),
    })
    saveSuccess.value = true
    setTimeout(() => { saveSuccess.value = false }, 3000)
  } catch (e) {
    console.error('Erreur sauvegarde paramètres', e)
  } finally {
    saving.value = false
  }
}

const regenerateApiKey = async () => {
  if (!confirm('Regénérer la clé API ? L\'ancienne clé sera immédiatement révoquée.')) return
  regeneratingKey.value = true
  try {
    const res = await fetch('/api/v1/settings/api-key/regenerate', {
      method: 'POST',
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const data = await res.json()
      settings.api_key = data.api_key
      showApiKey.value = true
    }
  } catch (e) {
    console.error('Erreur regénération clé', e)
  } finally {
    regeneratingKey.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
const { guidance } = useAiAssistant('Settings', 'configure_settings')
</script>
