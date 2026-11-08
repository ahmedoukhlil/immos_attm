import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('commonjsHelpers')) {
                        return 'commonjs-helpers';
                    }

                    if (id.includes('/node_modules/@pdf-lib/standard-fonts/')) {
                        return 'pdf-lib-fonts';
                    }

                    if (id.includes('/node_modules/@pdf-lib/upng/')) {
                        return 'pdf-lib-png';
                    }

                    if (id.includes('/node_modules/pako/')) {
                        return 'pdf-lib-compression';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/chart.js',
                'resources/js/pwa-dependencies.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
