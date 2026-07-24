// https://nuxt.com/docs/api/configuration/nuxt-config
import { PesonalPreset } from './app/theme/pesonal-preset'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@primevue/nuxt-module'],
  css: ['~/assets/css/main.css'],
  // Fonts via <head> links (with preconnect) instead of a CSS @import.
  // @import forces: fetch CSS -> parse -> discover font -> fetch — a render-blocking
  // waterfall. Head links start the font download immediately and in parallel.
  app: {
    head: {
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;600&display=swap' }
      ]
    }
  },
  primevue: {
    options: {
      theme: {
        preset: PesonalPreset,
        options: { darkModeSelector: false, cssLayer: false }
      }
    }
  }
})
