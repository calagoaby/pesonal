// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',
    devtools: { enabled: true },
    modules: ['@pinia/nuxt', '@nuxt/eslint'],
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
        // Server-only (never sent to the browser). Production: NUXT_API_PROXY_TARGET is the API's
        // own URL and NUXT_API_PROXY_SECRET its PROXY_SECRET; /api and /sanctum are proxied there and
        // NUXT_PUBLIC_API_BASE is this frontend's address. Empty (local dev): the browser calls
        // apiBase directly and the proxy routes 404.
        apiProxyTarget: '',
        apiProxySecret: '',
        public: {
            // `php artisan serve --port=8080`. Not 8000: Homestead forwards 8000 and 8001.
            apiBase: 'http://localhost:8080',
            locale: 'en-PH',
            timezone: 'Asia/Manila',
            // Shown on /privacy; empty falls back to "Ask whoever invited you". Set via NUXT_PUBLIC_CONTACT_EMAIL.
            contactEmail: ''
        }
    },
    // Used only by the vercel preset, which Nitro picks itself when building on Vercel; a local
    // `nuxi build` stays node. Singapore, near the API, since every request makes that hop.
    // Hobby allows one function region, which this is.
    nitro: {
        vercel: {
            functions: { regions: ['sin1'] }
        }
    }
})