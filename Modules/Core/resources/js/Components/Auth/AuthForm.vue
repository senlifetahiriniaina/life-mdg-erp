<template>
  <div class="auth-form">
    <form @submit.prevent="handleSubmit">
      <div v-if="step === 'credentials'" class="form-group">
        <label for="email">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          placeholder="user@example.com"
          class="form-control"
          required
        />
        <span v-if="errors.email" class="error">{{ errors.email }}</span>
      </div>

      <div v-if="step === 'credentials'" class="form-group">
        <label for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          placeholder="••••••••"
          class="form-control"
          required
        />
        <span v-if="errors.password" class="error">{{ errors.password }}</span>
      </div>

      <div v-if="step === '2fa'" class="form-group">
        <label for="twofa">2FA Code</label>
        <input
          id="twofa"
          v-model="form.twoFaCode"
          type="text"
          placeholder="000000"
          class="form-control"
          maxlength="6"
          required
        />
        <span v-if="errors.twoFaCode" class="error">{{ errors.twoFaCode }}</span>
      </div>

      <button type="submit" class="btn btn-primary">
        {{ step === 'credentials' ? 'Login' : 'Verify' }}
      </button>

      <div v-if="error" class="alert alert-danger">{{ error }}</div>
    </form>

    <a v-if="step === 'credentials'" href="#" class="forgot-password">
      Forgot Password?
    </a>
  </div>
</template>

<script>
export default {
  name: 'AuthForm',
  props: {
    requiresTwoFactor: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      form: {
        email: '',
        password: '',
        twoFaCode: '',
      },
      errors: {},
      error: null,
      step: 'credentials',
    }
  },
  methods: {
    validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return re.test(email)
    },
    validateForm() {
      this.errors = {}
      if (!this.form.email) {
        this.errors.email = 'Email is required'
      } else if (!this.validateEmail(this.form.email)) {
        this.errors.email = 'Invalid email format'
      }
      if (!this.form.password) {
        this.errors.password = 'Password is required'
      }
      if (this.step === '2fa' && !this.form.twoFaCode) {
        this.errors.twoFaCode = '2FA code is required'
      }
      return Object.keys(this.errors).length === 0
    },
    async handleSubmit() {
      if (!this.validateForm()) {
        return
      }
      await this.login()
    },
    async login() {
      try {
        if (this.step === 'credentials') {
          const response = await this.$api.post('/auth/login', {
            email: this.form.email,
            password: this.form.password,
          })
          if (response.requiresTwoFactor) {
            this.step = '2fa'
          } else {
            this.$emit('login-success', response)
          }
        } else if (this.step === '2fa') {
          const response = await this.$api.post('/auth/verify-2fa', {
            email: this.form.email,
            code: this.form.twoFaCode,
          })
          this.$emit('login-success', response)
        }
      } catch (err) {
        this.error = err.message || 'Login failed'
      }
    },
  },
}
</script>

<style scoped>
.auth-form {
  max-width: 400px;
  margin: 0 auto;
}

.form-group {
  margin-bottom: 1rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--slate-200);
  border-radius: 4px;
}

.error {
  color: var(--red-500);
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.btn {
  width: 100%;
  padding: 0.75rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-primary {
  background-color: var(--halo-500);
  color: white;
}

.alert {
  padding: 0.75rem;
  margin-top: 1rem;
  border-radius: 4px;
}

.alert-danger {
  background-color: var(--red-50);
  color: var(--red-800);
  border: 1px solid var(--red-200);
}

.forgot-password {
  display: block;
  margin-top: 1rem;
  text-align: center;
}
</style>
