<template>
  <AppLayout title="Authentification de domaine">
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-surface-50 dark:text-surface-50">Authentification de domaine</h1>
          <p class="text-sm text-gray-500 dark:text-surface-400 mt-1">Gérez les enregistrements SPF, DKIM et DMARC pour améliorer la délivrabilité.</p>
        </div>
        <Button label="Ajouter un domaine" icon="pi pi-plus" @click="openAddDialog" />
      </div>

      <!-- Info box -->
      <div class="bg-blue-50 dark:bg-surface-800 border border-blue-200 rounded-xl p-4 flex gap-3">
        <i class="pi pi-info-circle text-blue-500 text-xl flex-shrink-0 mt-0.5" />
        <div class="text-sm text-blue-800 space-y-1">
          <p class="font-semibold">Pourquoi configurer SPF, DKIM et DMARC ?</p>
          <p>Ces enregistrements DNS prouvent que vos emails sont légitimes, réduisent le risque d'être marqués comme spam et protègent votre domaine contre l'usurpation (phishing). Sans eux, vos campagnes peuvent ne jamais atteindre la boîte de réception de vos destinataires.</p>
        </div>
      </div>

      <!-- Domains table -->
      <DataTable :value="domains" :loading="loading" stripedRows class="rounded-xl shadow-sm border border-gray-100">
        <template #empty>
          <div class="text-center py-12 text-gray-400">
            <i class="pi pi-shield text-5xl mb-3 block" />
            <p>Aucun domaine configuré. Ajoutez votre premier domaine.</p>
          </div>
        </template>

        <Column field="domain" header="Domaine" class="font-medium" />

        <Column header="SPF" class="text-center">
          <template #body="{ data }">
            <i :class="['pi text-lg', data.verified_spf ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-400']" />
          </template>
        </Column>

        <Column header="DKIM" class="text-center">
          <template #body="{ data }">
            <i :class="['pi text-lg', data.verified_dkim ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-400']" />
          </template>
        </Column>

        <Column header="DMARC" class="text-center">
          <template #body="{ data }">
            <i :class="['pi text-lg', data.verified_dmarc ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-400']" />
          </template>
        </Column>

        <Column header="Statut">
          <template #body="{ data }">
            <Tag
              :value="statusLabel(data.auth_status)"
              :severity="statusSeverity(data.auth_status)"
            />
          </template>
        </Column>

        <Column header="Dernière vérification">
          <template #body="{ data }">
            <span class="text-sm text-gray-500 dark:text-surface-400">{{ data.last_checked_at ? formatDate(data.last_checked_at) : 'Jamais' }}</span>
          </template>
        </Column>

        <Column header="Actions" class="text-right">
          <template #body="{ data }">
            <div class="flex items-center gap-2 justify-end">
              <Button
                icon="pi pi-eye"
                text
                size="small"
                severity="secondary"
                v-tooltip.top="'Voir les enregistrements DNS'"
                @click="viewDns(data)"
              />
              <Button
                icon="pi pi-refresh"
                text
                size="small"
                :loading="verifying === data.id"
                v-tooltip.top="'Vérifier maintenant'"
                @click="verify(data)"
              />
              <Button
                icon="pi pi-trash"
                text
                size="small"
                severity="danger"
                v-tooltip.top="'Supprimer'"
                @click="confirmDelete(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Add domain dialog -->
    <Dialog v-model:visible="addDialogVisible" header="Ajouter un domaine" :style="{ width: '32rem' }" modal>
      <div class="space-y-4">
        <div>
          <label class="text-sm font-medium text-gray-700 dark:text-surface-100 block mb-1">Domaine <span class="text-red-500">*</span></label>
          <InputText v-model="form.domain" placeholder="monentreprise.com" class="w-full" :class="{ 'p-invalid': formErrors.domain }" />
          <small v-if="formErrors.domain" class="text-red-500">{{ formErrors.domain }}</small>
        </div>

        <div>
          <label class="text-sm font-medium text-gray-700 dark:text-surface-100 block mb-1">Politique DMARC</label>
          <Dropdown
            v-model="form.dmarc_policy"
            :options="dmarcOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Sélectionner..."
            class="w-full"
          />
          <p class="text-xs text-gray-400 mt-1">
            <span class="font-medium">none</span> — surveille uniquement ·
            <span class="font-medium">quarantine</span> — met en spam ·
            <span class="font-medium">reject</span> — rejette les emails non conformes
          </p>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="addDialogVisible = false" />
          <Button label="Créer le domaine" :loading="creating" @click="createDomain" />
        </div>
      </div>
    </Dialog>

    <!-- DNS instructions dialog -->
    <Dialog v-model:visible="dnsDialogVisible" header="Enregistrements DNS à créer" :style="{ width: '52rem' }" modal>
      <div class="space-y-4" v-if="selectedDomain">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800 flex gap-2">
          <i class="pi pi-exclamation-triangle flex-shrink-0 mt-0.5" />
          <p>Ajoutez ces enregistrements dans la zone DNS de <strong>{{ selectedDomain.domain }}</strong>, puis cliquez sur "Vérifier maintenant".</p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-surface-700">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-surface-800">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase">Hôte</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase">Valeur</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase">TTL</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(record, i) in selectedDomain.dns_records" :key="i" class="hover:bg-gray-50 dark:bg-surface-800">
                <td class="px-4 py-3">
                  <Tag :value="record.type" severity="secondary" />
                </td>
                <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-surface-100">{{ record.host }}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-surface-100 max-w-xs truncate" :title="record.value">{{ record.value }}</td>
                <td class="px-4 py-3 text-gray-500 dark:text-surface-400">{{ record.ttl }}</td>
                <td class="px-4 py-3">
                  <Button
                    icon="pi pi-copy"
                    text
                    size="small"
                    v-tooltip.top="'Copier la valeur'"
                    @click="copyValue(record.value)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-between pt-2">
          <Button
            label="Vérifier maintenant"
            icon="pi pi-refresh"
            severity="success"
            :loading="verifying === selectedDomain.id"
            @click="verify(selectedDomain)"
          />
          <Button label="Fermer" outlined @click="dnsDialogVisible = false" />
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Dropdown } from 'primevue'
import axios from 'axios'

const domains   = ref([])
const loading   = ref(false)
const verifying = ref(null)
const creating  = ref(false)

const addDialogVisible = ref(false)
const dnsDialogVisible = ref(false)
const selectedDomain   = ref(null)

const form = ref({ domain: '', dmarc_policy: 'none' })
const formErrors = ref({})

const dmarcOptions = [
  { label: 'none — surveillance uniquement', value: 'none' },
  { label: 'quarantine — rediriger en spam', value: 'quarantine' },
  { label: 'reject — rejeter les emails non conformes', value: 'reject' },
]

function statusLabel(status) {
  return { complete: 'Complet', partial: 'Partiel', none: 'Non configuré' }[status] ?? status
}

function statusSeverity(status) {
  return { complete: 'success', partial: 'warn', none: 'danger' }[status] ?? 'secondary'
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

async function fetchDomains() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/email/domain-auth')
    domains.value = res.data.data ?? []
  } catch {
    // silent
  } finally {
    loading.value = false
  }
}

function openAddDialog() {
  form.value = { domain: '', dmarc_policy: 'none' }
  formErrors.value = {}
  addDialogVisible.value = true
}

async function createDomain() {
  formErrors.value = {}
  if (!form.value.domain) {
    formErrors.value.domain = 'Le domaine est obligatoire.'
    return
  }
  creating.value = true
  try {
    const res = await axios.post('/api/v1/email/domain-auth', form.value)
    const created = res.data
    domains.value.push(created)
    addDialogVisible.value = false
    // Show DNS instructions immediately after creation
    selectedDomain.value = created
    dnsDialogVisible.value = true
  } catch (err) {
    if (err.response?.data?.errors?.domain) {
      formErrors.value.domain = err.response.data.errors.domain[0]
    }
  } finally {
    creating.value = false
  }
}

function viewDns(domain) {
  selectedDomain.value = domain
  dnsDialogVisible.value = true
}

async function verify(domain) {
  verifying.value = domain.id
  try {
    const res = await axios.post(`/api/v1/email/domain-auth/${domain.id}/verify`)
    const updated = res.data
    const idx = domains.value.findIndex(d => d.id === domain.id)
    if (idx !== -1) domains.value[idx] = updated
    if (selectedDomain.value?.id === domain.id) selectedDomain.value = updated
  } catch {
    // silent
  } finally {
    verifying.value = null
  }
}

async function confirmDelete(domain) {
  if (!confirm(`Supprimer le domaine "${domain.domain}" ?`)) return
  try {
    await axios.delete(`/api/v1/email/domain-auth/${domain.id}`)
    domains.value = domains.value.filter(d => d.id !== domain.id)
  } catch {
    // silent
  }
}

function copyValue(value) {
  navigator.clipboard?.writeText(value)
}

onMounted(fetchDomains)
</script>
