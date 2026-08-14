<template>
  <AppLayout>
    <Head title="Assistant de configuration — WideHalo ERP" />

    <div class="space-y-6 max-w-4xl mx-auto">
      <!-- Page header -->
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
          v-if="currentStep > 0"
          icon="pi pi-times"
          label="Annuler"
          severity="secondary"
          outlined
          @click="cancelWizard"
        />
      </div>

      <!-- Step indicator -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <Steps :model="steps" :activeStep="currentStep" class="mb-0" />
      </div>

      <!-- Step content -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">

        <!-- Step 0: Bienvenue + Infos entreprise -->
        <div v-if="currentStep === 0" class="space-y-6">
          <div class="text-center mb-6">
            <div class="w-16 h-16 bg-primary-50 dark:bg-primary-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="pi pi-building text-primary-600 dark:text-primary-400 text-2xl" />
            </div>
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Bienvenue sur WideHalo ERP</h2>
            <p class="text-surface-500 mt-2">Commençons par les informations de votre entreprise</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
                Nom de l'entreprise <span class="text-red-500">*</span>
              </label>
              <InputText
                v-model="companyInfo.name"
                placeholder="Ex: Acme Sarl"
                class="w-full"
                :class="{ 'p-invalid': errors.name }"
              />
              <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
                Secteur d'activité <span class="text-red-500">*</span>
              </label>
              <Select
                v-model="companyInfo.industry"
                :options="industryOptions"
                option-label="label"
                option-value="value"
                placeholder="Sélectionner un secteur"
                class="w-full"
                :class="{ 'p-invalid': errors.industry }"
              />
              <small v-if="errors.industry" class="text-red-500">{{ errors.industry }}</small>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
                Pays <span class="text-red-500">*</span>
              </label>
              <Select
                v-model="companyInfo.country"
                :options="countryOptions"
                option-label="label"
                option-value="value"
                placeholder="Sélectionner un pays"
                class="w-full"
                filter
                :class="{ 'p-invalid': errors.country }"
                @change="onCountryChange"
              />
              <small v-if="errors.country" class="text-red-500">{{ errors.country }}</small>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
                Devise <span class="text-red-500">*</span>
              </label>
              <Select
                v-model="companyInfo.currency"
                :options="currencyOptions"
                option-label="label"
                option-value="value"
                placeholder="Sélectionner une devise"
                class="w-full"
                :class="{ 'p-invalid': errors.currency }"
              />
              <small v-if="errors.currency" class="text-red-500">{{ errors.currency }}</small>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
                Email de contact
              </label>
              <InputText
                v-model="companyInfo.email"
                type="email"
                placeholder="contact@entreprise.com"
                class="w-full"
              />
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
                Téléphone
              </label>
              <InputText
                v-model="companyInfo.phone"
                placeholder="+221 77 000 00 00"
                class="w-full"
              />
            </div>
          </div>
        </div>

        <!-- Step 1: Source des données -->
        <div v-else-if="currentStep === 1" class="space-y-6">
          <div class="text-center mb-6">
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Source des données</h2>
            <p class="text-surface-500 mt-2">Comment souhaitez-vous importer vos données existantes ?</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="source in dataSources"
              :key="source.value"
              class="border-2 rounded-xl p-5 cursor-pointer transition-all"
              :class="[
                selectedDataSource === source.value
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                  : 'border-surface-200 dark:border-surface-700 hover:border-surface-300 dark:hover:border-surface-600'
              ]"
              @click="selectedDataSource = source.value"
             role="button" tabindex="0" @keydown.enter.prevent="selectedDataSource = source.value">
              <div class="flex items-start gap-3">
                <div
                  class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                  :class="source.iconBg"
                >
                  <i :class="['text-xl', source.icon, source.iconColor]" />
                </div>
                <div>
                  <h3 class="font-semibold text-surface-900 dark:text-surface-50">{{ source.label }}</h3>
                  <p class="text-sm text-surface-500 mt-1">{{ source.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- File upload -->
          <div v-if="selectedDataSource === 'file'" class="mt-4 space-y-4">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
                Choisir un fichier (Excel, CSV ou PDF)
              </label>
              <FileUpload
                mode="basic"
                accept=".xlsx,.xls,.csv,.pdf"
                :max-file-size="10000000"
                choose-label="Parcourir..."
                class="w-full"
                @select="onFileSelect"
              />
              <small class="text-surface-400">Formats supportés : .xlsx, .xls, .csv, .pdf — max 10 Mo</small>
            </div>
            <div v-if="selectedFile" class="flex items-center gap-2 text-sm text-surface-700 dark:text-surface-300 bg-surface-50 dark:bg-surface-700 rounded-lg p-3">
              <i class="pi pi-file text-primary-500" />
              <span>{{ selectedFile.name }}</span>
              <span class="text-surface-400">({{ formatFileSize(selectedFile.size) }})</span>
              <Button icon="pi pi-times" text severity="danger" size="small" @click="selectedFile = null" />
            </div>
          </div>

          <!-- Skip data import -->
          <div v-if="selectedDataSource === 'skip'" class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
              <i class="pi pi-info-circle" />
              <span class="text-sm">Vous pourrez toujours importer des données ultérieurement depuis le menu Paramètres.</span>
            </div>
          </div>
        </div>

        <!-- Step 2: Analyse du fichier -->
        <div v-else-if="currentStep === 2" class="space-y-6">
          <div class="text-center mb-4">
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Analyse du fichier</h2>
            <p class="text-surface-500 mt-2">WideHalo a détecté les colonnes suivantes dans votre fichier</p>
          </div>

          <div v-if="analysisLoading" class="text-center py-12">
            <ProgressSpinner />
            <p class="text-surface-500 mt-4">Analyse en cours…</p>
          </div>

          <div v-else-if="analysisResult" class="space-y-4">
            <!-- Summary -->
            <div class="grid grid-cols-3 gap-4">
              <div class="bg-surface-50 dark:bg-surface-700 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ analysisResult.row_count }}</p>
                <p class="text-sm text-surface-500">Lignes détectées</p>
              </div>
              <div class="bg-surface-50 dark:bg-surface-700 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ analysisResult.columns?.length ?? 0 }}</p>
                <p class="text-sm text-surface-500">Colonnes détectées</p>
              </div>
              <div class="bg-surface-50 dark:bg-surface-700 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ analysisResult.detected_format?.toUpperCase() }}</p>
                <p class="text-sm text-surface-500">Format</p>
              </div>
            </div>

            <!-- Detected columns -->
            <div>
              <h3 class="font-medium text-surface-700 dark:text-surface-300 mb-3">Colonnes détectées</h3>
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-for="col in analysisResult.columns"
                  :key="col"
                  :value="col"
                  severity="secondary"
                />
              </div>
            </div>

            <!-- Preview data -->
            <div v-if="analysisResult.preview_data?.length">
              <h3 class="font-medium text-surface-700 dark:text-surface-300 mb-3">Aperçu des données (5 premières lignes)</h3>
              <div class="overflow-x-auto rounded-lg border border-surface-200 dark:border-surface-700">
                <DataTable
                  :value="analysisResult.preview_data"
                  class="p-datatable-sm"
                  striped-rows
                >
                  <Column
                    v-for="col in analysisResult.columns"
                    :key="col"
                    :field="col"
                    :header="col"
                    style="min-width: 120px"
                  />
                </DataTable>
              </div>
            </div>

            <!-- Warnings -->
            <div v-if="analysisResult.warnings?.length" class="space-y-2">
              <h3 class="font-medium text-surface-700 dark:text-surface-300">Avertissements</h3>
              <div
                v-for="(warning, i) in analysisResult.warnings"
                :key="i"
                class="flex items-start gap-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg"
              >
                <i class="pi pi-exclamation-triangle text-yellow-600 mt-0.5" />
                <span class="text-sm text-yellow-800 dark:text-yellow-200">{{ warning }}</span>
              </div>
            </div>
          </div>

          <div v-else-if="selectedDataSource === 'skip'" class="text-center py-12 text-surface-400">
            <i class="pi pi-forward text-4xl mb-4 block" />
            <p>Aucun fichier à analyser — vous avez choisi de démarrer sans données.</p>
          </div>
        </div>

        <!-- Step 3: Correspondance des champs (AI) -->
        <div v-else-if="currentStep === 3" class="space-y-6">
          <div class="flex items-center justify-between mb-2">
            <div>
              <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Correspondance des champs</h2>
              <p class="text-surface-500 mt-1 text-sm">
                WideHalo AI a suggéré les correspondances ci-dessous. Vous pouvez les ajuster manuellement.
              </p>
            </div>
            <Button
              icon="pi pi-sparkles"
              label="Régénérer avec l'IA"
              outlined
              size="small"
              :loading="mappingLoading"
              @click="fetchMappingSuggestions"
            />
          </div>

          <div v-if="mappingLoading" class="text-center py-12">
            <ProgressSpinner />
            <p class="text-surface-500 mt-4">L'IA analyse vos colonnes…</p>
          </div>

          <div v-else-if="fieldMappings.length" class="space-y-3">
            <!-- AI confidence note -->
            <div class="flex items-center gap-2 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
              <i class="pi pi-sparkles text-primary-600" />
              <span class="text-sm text-primary-800 dark:text-primary-200">
                Les suggestions sont générées par l'IA. Vérifiez les correspondances avant de continuer.
              </span>
            </div>

            <!-- Mapping table -->
            <DataTable :value="fieldMappings" class="p-datatable-sm" striped-rows>
              <Column field="source_column" header="Colonne source (votre fichier)" style="min-width: 200px">
                <template #body="{ data }">
                  <span class="font-mono text-sm text-surface-700 dark:text-surface-300">{{ data.source_column }}</span>
                </template>
              </Column>
              <Column field="target_field" header="Champ WideHalo" style="min-width: 200px">
                <template #body="{ data }">
                  <Select
                    v-model="data.target_field"
                    :options="targetFieldOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Ignorer cette colonne"
                    show-clear
                    class="w-full text-sm"
                  />
                </template>
              </Column>
              <Column field="confidence" header="Confiance IA" style="width: 140px">
                <template #body="{ data }">
                  <div v-if="data.confidence !== undefined" class="flex items-center gap-2">
                    <div class="flex-1 bg-surface-200 dark:bg-surface-600 rounded-full h-2">
                      <div
                        class="h-2 rounded-full transition-all"
                        :class="confidenceColor(data.confidence)"
                        :style="{ width: `${Math.round(data.confidence * 100)}%` }"
                      />
                    </div>
                    <span class="text-xs text-surface-500 w-8 text-right">{{ Math.round(data.confidence * 100) }}%</span>
                  </div>
                  <span v-else class="text-surface-400 text-xs">Manuel</span>
                </template>
              </Column>
              <Column header="Statut" style="width: 80px">
                <template #body="{ data }">
                  <Tag
                    v-if="data.target_field"
                    value="Mappé"
                    severity="success"
                    class="text-xs"
                  />
                  <Tag
                    v-else
                    value="Ignoré"
                    severity="secondary"
                    class="text-xs"
                  />
                </template>
              </Column>
            </DataTable>

            <div class="text-sm text-surface-400">
              {{ mappedCount }} / {{ fieldMappings.length }} colonnes mappées
            </div>
          </div>

          <div v-else-if="selectedDataSource === 'skip'" class="text-center py-12 text-surface-400">
            <i class="pi pi-forward text-4xl mb-4 block" />
            <p>Aucune correspondance à configurer.</p>
          </div>
        </div>

        <!-- Step 4: Confirmation et import -->
        <div v-else-if="currentStep === 4" class="space-y-6">
          <div class="text-center mb-4">
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Confirmation et import</h2>
            <p class="text-surface-500 mt-2">Vérifiez le récapitulatif avant de lancer l'import</p>
          </div>

          <!-- Summary cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-surface-50 dark:bg-surface-700 rounded-xl p-4 space-y-2">
              <h3 class="font-semibold text-surface-700 dark:text-surface-300">Entreprise</h3>
              <dl class="space-y-1 text-sm">
                <div class="flex justify-between">
                  <dt class="text-surface-500">Nom</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ companyInfo.name }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-surface-500">Secteur</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ industryLabel }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-surface-500">Pays</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ countryLabel }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-surface-500">Devise</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ companyInfo.currency }}</dd>
                </div>
              </dl>
            </div>

            <div class="bg-surface-50 dark:bg-surface-700 rounded-xl p-4 space-y-2">
              <h3 class="font-semibold text-surface-700 dark:text-surface-300">Import</h3>
              <dl class="space-y-1 text-sm">
                <div class="flex justify-between">
                  <dt class="text-surface-500">Source</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ dataSourceLabel }}</dd>
                </div>
                <div v-if="selectedFile" class="flex justify-between">
                  <dt class="text-surface-500">Fichier</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ selectedFile.name }}</dd>
                </div>
                <div v-if="analysisResult" class="flex justify-between">
                  <dt class="text-surface-500">Lignes</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ analysisResult.row_count }}</dd>
                </div>
                <div v-if="fieldMappings.length" class="flex justify-between">
                  <dt class="text-surface-500">Champs mappés</dt>
                  <dd class="font-medium text-surface-900 dark:text-surface-50">{{ mappedCount }} / {{ fieldMappings.length }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Import progress -->
          <div v-if="importLoading || importResult" class="space-y-3">
            <div class="flex items-center justify-between text-sm">
              <span class="text-surface-700 dark:text-surface-300 font-medium">Progression de l'import</span>
              <span class="text-surface-500">{{ importProgress }}%</span>
            </div>
            <ProgressBar :value="importProgress" class="h-3" />
            <p v-if="importStatusMessage" class="text-sm text-surface-500">{{ importStatusMessage }}</p>
          </div>

          <!-- Import result -->
          <div v-if="importResult && !importLoading">
            <div
              v-if="importResult.success"
              class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl"
            >
              <i class="pi pi-check-circle text-green-600 text-2xl" />
              <div>
                <p class="font-semibold text-green-800 dark:text-green-200">Import terminé avec succès</p>
                <p class="text-sm text-green-700 dark:text-green-300">
                  {{ importResult.imported_rows }} ligne(s) importée(s) ·
                  {{ importResult.skipped_rows ?? 0 }} ignorée(s) ·
                  {{ importResult.errors_count ?? 0 }} erreur(s)
                </p>
              </div>
            </div>
            <div
              v-else
              class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl"
            >
              <i class="pi pi-times-circle text-red-600 text-2xl mt-0.5" />
              <div>
                <p class="font-semibold text-red-800 dark:text-red-200">Erreur lors de l'import</p>
                <p class="text-sm text-red-700 dark:text-red-300">{{ importResult.message }}</p>
              </div>
            </div>
          </div>

          <!-- Warning before launch -->
          <div v-if="!importResult && !importLoading" class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <div class="flex items-center gap-2 text-yellow-700 dark:text-yellow-300">
              <i class="pi pi-info-circle" />
              <span class="text-sm">Cliquez sur <strong>Lancer l'import</strong> pour démarrer. Cette action peut prendre quelques minutes.</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation buttons -->
      <div class="flex items-center justify-between">
        <Button
          v-if="currentStep > 0 && !importResult?.success"
          icon="pi pi-arrow-left"
          label="Précédent"
          severity="secondary"
          outlined
          @click="prevStep"
        />
        <div v-else />

        <div class="flex gap-3">
          <!-- Final: go to dashboard -->
          <Button
            v-if="importResult?.success"
            icon="pi pi-home"
            label="Aller au tableau de bord"
            @click="goToDashboard"
          />
          <!-- Step 4: launch import -->
          <Button
            v-else-if="currentStep === 4 && !importLoading"
            icon="pi pi-play"
            label="Lancer l'import"
            :loading="importLoading"
            @click="executeImport"
          />
          <!-- Other steps: next -->
          <Button
            v-else-if="currentStep < 4"
            icon-pos="right"
            icon="pi pi-arrow-right"
            :label="currentStep === 1 && selectedDataSource === 'skip' ? 'Ignorer l\'import' : 'Suivant'"
            :loading="analysisLoading"
            @click="nextStep"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import Steps from 'primevue/steps'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const { isAdmin } = useRoleAccess()
const canManage = computed(() => isAdmin.value)
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


// ────────────────────────────────────────────────────────────
// Types
// ────────────────────────────────────────────────────────────

interface CompanyInfo {
  name: string
  country: string
  industry: string
  currency: string
  email: string
  phone: string
}

interface AnalysisResult {
  session_id: string
  detected_format: string
  row_count: number
  columns: string[]
  preview_data: Record<string, string>[]
  warnings: string[]
}

interface FieldMapping {
  source_column: string
  target_field: string | null
  confidence?: number
}

interface ImportResult {
  success: boolean
  message?: string
  imported_rows: number
  skipped_rows?: number
  errors_count?: number
}

// ────────────────────────────────────────────────────────────
// Wizard state
// ────────────────────────────────────────────────────────────

const currentStep = ref(0)

const steps = [
  { label: 'Entreprise' },
  { label: 'Source' },
  { label: 'Analyse' },
  { label: 'Mapping' },
  { label: 'Import' },
]

const companyInfo = reactive<CompanyInfo>({
  name: '',
  country: '',
  industry: '',
  currency: '',
  email: '',
  phone: '',
})

const errors = reactive<Partial<Record<keyof CompanyInfo, string>>>({})

const selectedDataSource = ref<string>('file')
const selectedFile = ref<File | null>(null)

const analysisLoading = ref(false)
const analysisResult = ref<AnalysisResult | null>(null)

const mappingLoading = ref(false)
const fieldMappings = ref<FieldMapping[]>([])

const importLoading = ref(false)
const importProgress = ref(0)
const importStatusMessage = ref('')
const importResult = ref<ImportResult | null>(null)

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
  // Africa
  { label: 'Sénégal', value: 'SN' },
  { label: 'Côte d\'Ivoire', value: 'CI' },
  { label: 'Mali', value: 'ML' },
  { label: 'Burkina Faso', value: 'BF' },
  { label: 'Niger', value: 'NE' },
  { label: 'Togo', value: 'TG' },
  { label: 'Bénin', value: 'BJ' },
  { label: 'Guinée', value: 'GN' },
  { label: 'Cameroun', value: 'CM' },
  { label: 'Gabon', value: 'GA' },
  { label: 'Congo', value: 'CG' },
  { label: 'Madagascar', value: 'MG' },
  { label: 'Nigeria', value: 'NG' },
  { label: 'Ghana', value: 'GH' },
  { label: 'Kenya', value: 'KE' },
  { label: 'Tanzanie', value: 'TZ' },
  { label: 'Maroc', value: 'MA' },
  { label: 'Tunisie', value: 'TN' },
  { label: 'Algérie', value: 'DZ' },
  { label: 'Égypte', value: 'EG' },
  // Asia
  { label: 'Chine', value: 'CN' },
  { label: 'Inde', value: 'IN' },
  { label: 'Japon', value: 'JP' },
  { label: 'Corée du Sud', value: 'KR' },
  { label: 'Singapour', value: 'SG' },
  { label: 'Thaïlande', value: 'TH' },
  { label: 'Vietnam', value: 'VN' },
  // Other
  { label: 'France', value: 'FR' },
  { label: 'Belgique', value: 'BE' },
  { label: 'Suisse', value: 'CH' },
]

const countryCurrencyMap: Record<string, string> = {
  SN: 'XOF', CI: 'XOF', ML: 'XOF', BF: 'XOF', NE: 'XOF', TG: 'XOF', BJ: 'XOF', GN: 'GNF',
  CM: 'XAF', GA: 'XAF', CG: 'XAF',
  MG: 'MGA', NG: 'NGN', GH: 'GHS', KE: 'KES', TZ: 'TZS',
  MA: 'MAD', TN: 'TND', DZ: 'DZD', EG: 'EGP',
  CN: 'CNY', IN: 'INR', JP: 'JPY', KR: 'KRW', SG: 'SGD', TH: 'THB', VN: 'VND',
  FR: 'EUR', BE: 'EUR', CH: 'CHF',
}

const currencyOptions = [
  { label: 'XOF — Franc CFA UEMOA', value: 'XOF' },
  { label: 'XAF — Franc CFA CEMAC', value: 'XAF' },
  { label: 'GHS — Cedi ghanéen', value: 'GHS' },
  { label: 'NGN — Naira nigérian', value: 'NGN' },
  { label: 'KES — Shilling kényan', value: 'KES' },
  { label: 'TZS — Shilling tanzanien', value: 'TZS' },
  { label: 'MGA — Ariary malgache', value: 'MGA' },
  { label: 'MAD — Dirham marocain', value: 'MAD' },
  { label: 'TND — Dinar tunisien', value: 'TND' },
  { label: 'DZD — Dinar algérien', value: 'DZD' },
  { label: 'EGP — Livre égyptienne', value: 'EGP' },
  { label: 'EUR — Euro', value: 'EUR' },
  { label: 'USD — Dollar américain', value: 'USD' },
  { label: 'CNY — Yuan chinois', value: 'CNY' },
  { label: 'INR — Roupie indienne', value: 'INR' },
  { label: 'JPY — Yen japonais', value: 'JPY' },
  { label: 'SGD — Dollar singapourien', value: 'SGD' },
]

const dataSources = [
  {
    value: 'file',
    label: 'Fichier (Excel / CSV / PDF)',
    description: 'Importez vos données depuis un fichier Excel, CSV ou PDF.',
    icon: 'pi pi-file-excel',
    iconBg: 'bg-green-100 dark:bg-green-900/30',
    iconColor: 'text-green-600 dark:text-green-400',
  },
  {
    value: 'skip',
    label: 'Démarrer sans données',
    description: 'Commencez avec un ERP vide et saisissez les données manuellement.',
    icon: 'pi pi-play',
    iconBg: 'bg-blue-100 dark:bg-blue-900/30',
    iconColor: 'text-blue-600 dark:text-blue-400',
  },
]

const targetFieldOptions = [
  // Common
  { label: 'Nom complet', value: 'full_name' },
  { label: 'Prénom', value: 'first_name' },
  { label: 'Nom de famille', value: 'last_name' },
  { label: 'Email', value: 'email' },
  { label: 'Téléphone', value: 'phone' },
  { label: 'Adresse', value: 'address' },
  { label: 'Ville', value: 'city' },
  { label: 'Pays', value: 'country' },
  // Products
  { label: 'Référence produit', value: 'product_sku' },
  { label: 'Nom du produit', value: 'product_name' },
  { label: 'Prix unitaire', value: 'unit_price' },
  { label: 'Quantité en stock', value: 'stock_quantity' },
  // Contacts / CRM
  { label: 'Entreprise', value: 'company_name' },
  { label: 'Titre / Poste', value: 'job_title' },
  // Finance
  { label: 'Montant', value: 'amount' },
  { label: 'Date de transaction', value: 'transaction_date' },
  { label: 'Référence facture', value: 'invoice_ref' },
]

// ────────────────────────────────────────────────────────────
// Computed
// ────────────────────────────────────────────────────────────

const mappedCount = computed(() => fieldMappings.value.filter(m => m.target_field).length)

const industryLabel = computed(
  () => industryOptions.find(o => o.value === companyInfo.industry)?.label ?? companyInfo.industry,
)

const countryLabel = computed(
  () => countryOptions.find(o => o.value === companyInfo.country)?.label ?? companyInfo.country,
)

const dataSourceLabel = computed(
  () => dataSources.find(d => d.value === selectedDataSource.value)?.label ?? selectedDataSource.value,
)

// ────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────

const formatFileSize = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
}

const confidenceColor = (confidence: number): string => {
  if (confidence >= 0.8) return 'bg-green-500'
  if (confidence >= 0.5) return 'bg-yellow-500'
  return 'bg-red-400'
}

// ────────────────────────────────────────────────────────────
// Validation
// ────────────────────────────────────────────────────────────

const validateStep0 = (): boolean => {
  Object.keys(errors).forEach(k => delete (errors as Record<string, string>)[k])
  let valid = true
  if (!companyInfo.name.trim()) { errors.name = 'Le nom est requis'; valid = false }
  if (!companyInfo.country) { errors.country = 'Le pays est requis'; valid = false }
  if (!companyInfo.industry) { errors.industry = 'Le secteur est requis'; valid = false }
  if (!companyInfo.currency) { errors.currency = 'La devise est requise'; valid = false }
  return valid
}

// ────────────────────────────────────────────────────────────
// Event handlers
// ────────────────────────────────────────────────────────────

const onCountryChange = (event: { value: string }) => {
  const suggested = countryCurrencyMap[event.value]
  if (suggested) companyInfo.currency = suggested
}

const onFileSelect = (event: { files: File[] }) => {
  selectedFile.value = event.files[0] ?? null
}

// ────────────────────────────────────────────────────────────
// API calls
// ────────────────────────────────────────────────────────────

const analyzeFile = async () => {
  if (selectedDataSource.value === 'skip' || !selectedFile.value) return

  analysisLoading.value = true
  analysisResult.value = null
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('company_name', companyInfo.name)
    formData.append('country', companyInfo.country)

    const res = await fetch('/api/v1/setup/analyze-file', {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: formData,
    })
    analysisResult.value = await res.json()
  } catch (e) {
    console.error('Erreur analyse fichier', e)
  } finally {
    analysisLoading.value = false
  }
}

const fetchMappingSuggestions = async () => {
  if (selectedDataSource.value === 'skip' || !analysisResult.value) return

  mappingLoading.value = true
  try {
    const res = await fetch('/api/v1/setup/suggest-mapping', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        session_id: analysisResult.value.session_id,
        columns: analysisResult.value.columns,
        industry: companyInfo.industry,
      }),
    })
    const data = await res.json()
    const suggested: Record<string, { target_field: string; confidence: number }> =
      data.suggested_mapping ?? {}

    fieldMappings.value = (analysisResult.value?.columns ?? []).map(col => ({
      source_column: col,
      target_field: suggested[col]?.target_field ?? null,
      confidence: suggested[col]?.confidence,
    }))
  } catch (e) {
    console.error('Erreur suggestion mapping', e)
    // Fallback: build empty mappings
    fieldMappings.value = (analysisResult.value?.columns ?? []).map(col => ({
      source_column: col,
      target_field: null,
    }))
  } finally {
    mappingLoading.value = false
  }
}

const executeImport = async () => {
  importLoading.value = true
  importProgress.value = 0
  importStatusMessage.value = 'Préparation de l\'import…'
  importResult.value = null

  try {
    // Simulate incremental progress
    const progressInterval = setInterval(() => {
      if (importProgress.value < 85) {
        importProgress.value += Math.floor(Math.random() * 8) + 2
        importStatusMessage.value = importProgress.value < 40
          ? 'Validation des données…'
          : importProgress.value < 70
          ? 'Insertion des enregistrements…'
          : 'Finalisation…'
      }
    }, 600)

    const payload: Record<string, unknown> = {
      company: companyInfo,
    }
    if (analysisResult.value) {
      payload.session_id = analysisResult.value.session_id
      payload.field_mapping = Object.fromEntries(
        fieldMappings.value
          .filter(m => m.target_field)
          .map(m => [m.source_column, m.target_field]),
      )
    }

    const res = await fetch('/api/v1/setup/execute-import', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    })

    clearInterval(progressInterval)
    importProgress.value = 100
    importResult.value = await res.json()
    importStatusMessage.value = importResult.value?.success
      ? 'Import terminé !'
      : 'L\'import a rencontré une erreur.'
  } catch (e) {
    console.error('Erreur import', e)
    importResult.value = {
      success: false,
      message: 'Erreur réseau ou serveur. Veuillez réessayer.',
      imported_rows: 0,
    }
  } finally {
    importLoading.value = false
  }
}

// ────────────────────────────────────────────────────────────
// Navigation
// ────────────────────────────────────────────────────────────

const nextStep = async () => {
  if (currentStep.value === 0) {
    if (!validateStep0()) return
  }

  if (currentStep.value === 1) {
    // Move to analysis step and trigger analysis
    currentStep.value++
    await analyzeFile()
    return
  }

  if (currentStep.value === 2) {
    // Move to mapping step and fetch suggestions
    currentStep.value++
    await fetchMappingSuggestions()
    return
  }

  currentStep.value = Math.min(currentStep.value + 1, steps.length - 1)
}

const prevStep = () => {
  currentStep.value = Math.max(currentStep.value - 1, 0)
}

const cancelWizard = () => {
  router.visit('/setup')
}

const goToDashboard = () => {
  router.visit('/dashboard')
}

onMounted(() => {
  // Nothing to preload
})
</script>
