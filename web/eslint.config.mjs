// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

// Rules that encode CLAUDE.md's conventions; everything else is @nuxt/eslint's default.
export default withNuxt({
    rules: {
        'vue/block-order': ['error', { order: ['script', 'template', 'style'] }],
        // Nuxt names components by folder (components/dashboard/Card.vue → <DashboardCard>),
        // so a one-word filename is already a multi-word component name.
        'vue/multi-word-component-names': 'off',
        // Vue 3 allows fragment roots; pages put sheets and toasts beside the main element.
        'vue/no-multiple-template-root': 'off',
        // Optional props are left undefined on purpose; a default would only restate that (CLAUDE.md: comment/code only what's needed).
        'vue/require-default-prop': 'off',
        'eol-last': ['error', 'never'],
        // House style is `<SharedPerf />`; html-self-closing's fix alone writes `<div/>`.
        'vue/html-closing-bracket-spacing': ['error', { selfClosingTag: 'always' }],
    },
}, {
    // Scoped to TS/Vue: the rule needs the typescript parser, which plain .mjs files don't get.
    files: ['**/*.{ts,vue}'],
    rules: {
        // snake_case function names must use the arrow form instead.
        '@typescript-eslint/naming-convention': ['error', { selector: 'function', format: ['camelCase'] }],
    },
})