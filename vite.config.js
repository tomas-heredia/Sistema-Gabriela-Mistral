import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        // Sin esto el minificador de CSS reescribe los breakpoints como `@media (width>=640px)`,
        // que los navegadores viejos no entienden (se quedan con el layout de celular).
        cssTarget: ['chrome80', 'edge80', 'firefox72', 'safari13'],
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
