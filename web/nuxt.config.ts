// https://nuxt.com/docs/api/configuration/nuxt-config
import { PesonalPreset } from './app/theme/pesonal-preset'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@primevue/nuxt-module'],
  css: ['primeicons/primeicons.css', '~/assets/css/main.css'],
  primevue: {
    options: {
        theme: {
            preset: PesonalPreset,
            options: {
                darkModeSelector: false,
                cssLayer: false
            }
        }
    }
  }
})
