import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

// Pesonal accent — "stamp red" (#C8472E) baked into PrimeVue's `primary` palette.
// 500 is the brand stamp; darker steps approach the pressed shade.
export const PesonalPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#faede9',
            100: '#f3d2c8',
            200: '#e9b0a0',
            300: '#de8b74',
            400: '#d26a4f',
            500: '#c8472e',
            600: '#b23c26',
            700: '#92301e',
            800: '#6f2417',
            900: '#4e1a10',
            950: '#2e0f09'
        }
    }
})