<template>
  <div v-if="showBanner" class="gdpr-banner">
    <div class="banner-content">
      <h3>Privacy & Consent</h3>
      <p>
        We use cookies and collect personal data to enhance your experience, provide personalized
        content, and analyze our website usage. You can manage your preferences below.
      </p>

      <div class="consent-options">
        <div class="consent-item">
          <input id="consent-necessary" v-model="consents.necessary" type="checkbox" disabled />
          <label for="consent-necessary">
            <strong>Necessary</strong> - Essential for site functionality (always enabled)
          </label>
        </div>

        <div class="consent-item">
          <input id="consent-analytics" v-model="consents.analytics" type="checkbox" />
          <label for="consent-analytics">
            <strong>Analytics</strong> - Help us improve by analyzing usage patterns
          </label>
        </div>

        <div class="consent-item">
          <input id="consent-marketing" v-model="consents.marketing" type="checkbox" />
          <label for="consent-marketing">
            <strong>Marketing</strong> - Personalized ads and promotional content
          </label>
        </div>

        <div class="consent-item">
          <input id="consent-preferences" v-model="consents.preferences" type="checkbox" />
          <label for="consent-preferences">
            <strong>Preferences</strong> - Remember your settings and choices
          </label>
        </div>
      </div>

      <div class="banner-actions">
        <button class="btn btn-primary" @click="acceptAll">Accept All</button>
        <button class="btn btn-secondary" @click="rejectOptional">Reject Optional</button>
        <button class="btn btn-outline" @click="savePreferences">Save Preferences</button>
      </div>

      <a href="#" class="privacy-link">Privacy Policy</a>
    </div>
  </div>

  <div v-if="showSar" class="sar-modal">
    <div class="modal-overlay" @click="closeSar" aria-hidden="true"></div>
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="sar-modal-title">
      <div class="modal-header">
        <h3 id="sar-modal-title">Subject Access Request (SAR)</h3>
        <button aria-label="Fermer" class="close-btn" @click="closeSar">×</button>
      </div>

      <div class="modal-body">
        <p>
          You can request a copy of your personal data in a machine-readable format. We will
          process your request within 30 days.
        </p>

        <div class="form-group">
          <label for="sar-email">Confirm your email address</label>
          <input
            id="sar-email"
            v-model="sarForm.email"
            type="email"
            class="form-control"
            placeholder="your@email.com"
          />
          <span v-if="sarError" class="error">{{ sarError }}</span>
        </div>

        <div class="form-group">
          <label for="sar-format">Preferred format</label>
          <select id="sar-format" v-model="sarForm.format" class="form-control">
            <option value="json">JSON</option>
            <option value="csv">CSV</option>
            <option value="xml">XML</option>
          </select>
        </div>

        <div v-if="sarStatus === 'confirming'" class="status-message">
          <p>Check your email for confirmation link (valid for 24 hours)</p>
        </div>

        <div v-if="sarStatus === 'confirmed'" class="status-message success">
          <p>Request confirmed! Your data export will be ready soon.</p>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="closeSar">Cancel</button>
        <button
          class="btn btn-primary"
          :disabled="sarLoading"
          @click="submitSar"
        >
          {{ sarLoading ? 'Processing...' : 'Request Data Export' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GdprConsent',
  props: {
    showInitial: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      showBanner: true,
      showSar: false,
      consents: {
        necessary: true,
        analytics: false,
        marketing: false,
        preferences: false,
      },
      sarForm: {
        email: '',
        format: 'json',
      },
      sarStatus: null,
      sarError: null,
      sarLoading: false,
    }
  },
  mounted() {
    this.loadConsentPreferences()
    if (!this.showInitial) {
      this.showBanner = false
    }
  },
  methods: {
    loadConsentPreferences() {
      try {
        const stored = localStorage.getItem('gdpr-consent')
        if (stored) {
          const parsed = JSON.parse(stored)
          this.consents = { ...this.consents, ...parsed }
          this.showBanner = false
        }
      } catch (e) {
        console.error('Failed to load consent preferences')
      }
    },
    savePreferences() {
      localStorage.setItem('gdpr-consent', JSON.stringify(this.consents))
      this.$emit('consent-saved', this.consents)
      this.showBanner = false
    },
    acceptAll() {
      this.consents = {
        necessary: true,
        analytics: true,
        marketing: true,
        preferences: true,
      }
      this.savePreferences()
    },
    rejectOptional() {
      this.consents = {
        necessary: true,
        analytics: false,
        marketing: false,
        preferences: false,
      }
      this.savePreferences()
    },
    openSar() {
      this.showSar = true
      this.sarForm.email = ''
      this.sarStatus = null
      this.sarError = null
    },
    closeSar() {
      this.showSar = false
      this.sarForm.email = ''
      this.sarStatus = null
      this.sarError = null
    },
    validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return re.test(email)
    },
    async submitSar() {
      this.sarError = null

      if (!this.sarForm.email) {
        this.sarError = 'Email is required'
        return
      }

      if (!this.validateEmail(this.sarForm.email)) {
        this.sarError = 'Please enter a valid email'
        return
      }

      this.sarLoading = true

      try {
        await this.$api.post('/api/v1/core/gdpr/sar', {
          email: this.sarForm.email,
          format: this.sarForm.format,
        })
        this.sarStatus = 'confirming'
        this.$emit('sar-submitted', { email: this.sarForm.email, format: this.sarForm.format })
      } catch (error) {
        this.sarError = error.message || 'Failed to submit SAR'
      } finally {
        this.sarLoading = false
      }
    },
  },
}
</script>

<style scoped>
.gdpr-banner {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: var(--slate-700);
  color: white;
  padding: 2rem;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.2);
  z-index: 1000;
  max-height: 400px;
  overflow-y: auto;
}

.banner-content {
  max-width: 1000px;
  margin: 0 auto;
}

.banner-content h3 {
  margin-top: 0;
}

.consent-options {
  margin: 1.5rem 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.consent-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.consent-item input {
  margin-top: 0.25rem;
  cursor: pointer;
}

.consent-item label {
  margin: 0;
  cursor: pointer;
}

.banner-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
  flex-wrap: wrap;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}

.btn-primary {
  background-color: var(--halo-500);
  color: white;
}

.btn-secondary {
  background-color: var(--slate-500);
  color: white;
}

.btn-outline {
  background-color: transparent;
  border: 1px solid white;
  color: white;
}

.privacy-link {
  display: block;
  margin-top: 1rem;
  color: var(--slate-400);
  text-decoration: none;
  font-size: 0.875rem;
}

.privacy-link:hover {
  text-decoration: underline;
}

.sar-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  position: relative;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  width: 90%;
  max-width: 500px;
}

.modal-header {
  padding: 2rem;
  border-bottom: 1px solid var(--slate-100);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 2rem;
  cursor: pointer;
  color: var(--slate-400);
}

.modal-body {
  padding: 2rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--slate-200);
  border-radius: 4px;
}

.error {
  color: var(--red-500);
  font-size: 0.875rem;
  margin-top: 0.25rem;
  display: block;
}

.status-message {
  padding: 1rem;
  background-color: var(--halo-50);
  border-left: 3px solid var(--halo-500);
  border-radius: 4px;
  color: var(--halo-800);
  margin-bottom: 1rem;
}

.status-message.success {
  background-color: var(--green-50);
  border-left-color: var(--green-500);
  color: var(--green-700);
}

.status-message p {
  margin: 0;
}

.modal-footer {
  padding: 1.5rem 2rem;
  border-top: 1px solid var(--slate-100);
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.modal-footer .btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
