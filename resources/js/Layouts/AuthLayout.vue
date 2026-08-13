<template>
  <div class="auth-layout">
    <div class="auth-container">
      <div class="auth-content">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Auth layout for login, register, and password reset pages
</script>

<style scoped>
.auth-layout {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.auth-container {
  width: 100%;
  max-width: 400px;
  padding: 2rem;
}

.auth-content {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  padding: 2rem;
}

@media (max-width: 640px) {
  .auth-container {
    max-width: 100%;
    padding: 1rem;
  }

  .auth-content {
    border-radius: 0;
    padding: 1.5rem;
  }
}
</style>
