import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/vue/app.js'],
            ssr: 'resources/vue/ssr.js',
        }),
        vue(
            {
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }
        ),
    ],
    css: {
        postcss: 'resources/postcss.config.js'
    },
    resolve: {
        alias: {
            '@': '/resources/vue',
        },
    },
    ssr: {
        noExternal: [
            /.*/
        ],
    },
    build: {
        // Maybe it is better to use sentry cli instead of public map file!
        sourcemap: true,
    }
});
