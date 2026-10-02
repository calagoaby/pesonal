// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',
    devtools: { enabled: true },
    modules: ['@pinia/nuxt'],
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
    runtimeConfig: {
        // Server-side renders only. Inside the web container localhost is the container
        // itself, so docker-compose points this at Laravel's service name. Empty falls
        // back to public.apiBase, which is right when Nuxt runs straight on the host.
        apiBase: '',
        public: {
            // Where the browser reaches Laravel: its host-published port.
            apiBase: 'http://localhost:8000',
            locale: 'en-PH',
            timezone: 'Asia/Manila'
        }
    }
})