import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig, type UserConfig } from 'vite';
import { alias, renameSkeletonClasses, viteConfigurator } from './config/vite';

const minify = true;
const config: UserConfig = {
    build: { minify, sourcemap: true },
    plugins: [
        laravel({
            // those are the endpoints to use with the adapter
            input: ['app/app.css', 'app/app.ts'],
            // public directory relative to the project root
            publicDirectory: 'public',
            // build directory name relative to public
            buildDirectory: 'build',
            refresh: true,
            hotFile: 'public/build/hot',
        }),
        tailwindcss(),
        svelte(),
    ],
    resolve: {
        alias: [alias('@', 'app')],
        conditions: ['browser'],
    },
    server: { cors: true },
    // do not copy index.php
    publicDir: false,
};

export default defineConfig(viteConfigurator(config, renameSkeletonClasses(['btn', 'card'], 'skeleton-')));
