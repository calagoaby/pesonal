import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

// Pesonal brand blue (#1763D8) baked into PrimeVue's `primary` palette.
// 500 is the brand; darker steps approach the pressed shade (#0F4CA8).
export const PesonalPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#e9f1fe',
      100: '#cfe0fc',
      200: '#a6c5fa',
      300: '#7aa8f5',
      400: '#4587ee',
      500: '#1763d8',
      600: '#1356bf',
      700: '#0f4ca8',
      800: '#0b3c83',
      900: '#0a2f63',
      950: '#071f42'
    }
  }
})