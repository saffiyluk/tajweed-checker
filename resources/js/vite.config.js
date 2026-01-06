// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',  // Only this one CSS file
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});