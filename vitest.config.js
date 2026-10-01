import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

/**
 * Pruebas de componentes Vue (Vitest + @vue/test-utils + jsdom).
 *
 * Aparte de vite.config.js a propósito: el plugin de Laravel no sirve fuera
 * del build y aquí solo hace falta compilar `.vue` y el alias `@`.
 * jsdom no calcula diseño: las pruebas que dependen de medidas las fijan a
 * mano sobre los elementos.
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.ts'],
    },
});
