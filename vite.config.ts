import { defineConfig } from 'vite';
import Unfonts from 'unplugin-fonts/vite';
import tailwindcss from '@tailwindcss/vite';
import fontConfig from './unfonts.config';
import symfonyPlugin from 'vite-plugin-symfony';
import { fileURLToPath, URL } from 'node:url';

// https://vite.dev/config/
export default defineConfig({
    plugins: [Unfonts(fontConfig as any), tailwindcss(), symfonyPlugin()],
    resolve: { alias: { '@': fileURLToPath(new URL('./assets', import.meta.url)) } },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
                silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import', 'slash-div'],
            },
        },
    },
    build: {
        target: 'esnext',
        chunkSizeWarningLimit: 1024,
        minify: false,
        rollupOptions: {
            input: {
                app: './assets/index.ts',
            },
        },
    },
});
