import '@inertiajs/core'

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      auth?: {
        user?: {
          id?: number
          name?: string
          email?: string
          roles?: string[]
          permissions?: string[]
        }
      }
      enabledModules?: string[]
    }
  }
}
