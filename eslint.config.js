import pluginVue from 'eslint-plugin-vue'
import js from '@eslint/js'
import tsParser from '@typescript-eslint/parser'

export default [
  js.configs.recommended,
  ...pluginVue.configs['flat/essential'],
  {
    files: ['resources/js/**/*.{js,vue}'],
    languageOptions: {
      parserOptions: {
        parser: tsParser,
        extraFileExtensions: ['.vue'],
        ecmaFeatures: { jsx: true },
      },
    },
    rules: {
      'no-unused-vars': 'warn',
      'no-undef': 'off',
      'no-empty': 'warn',
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'warn',
      'vue/no-parsing-error': 'warn',
    },
  },
]
