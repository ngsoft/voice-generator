// import {svelte} from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import {defineConfig, type UserConfig} from 'vite';
import {alias, moveSkeletonClasses, viteConfigurator} from './config/vite';

const dev = 'prod' !== process.env.APP_ENV;


const config: UserConfig = {
    build: {
        sourcemap: dev, minify: true, chunkSizeWarningLimit: 500
    },
    plugins: [
        laravel({
            // those are the endpoints to use with the adapter
            input: ['app/app.css', 'app/app.ts'],
            // public directory relative to the project root
            publicDirectory: 'public',
            // build directory name relative to public
            buildDirectory: dev ? 'build' : 'assets/app',
            refresh: true,
            hotFile: 'public/build/hot',
        }),
        tailwindcss(),
        //svelte(),
    ],
    css: {
        preprocessorOptions: {
            scss: {silenceDeprecations: ['color-functions', 'global-builtin', 'import', 'slash-div']},
        },
    },
    resolve: {
        alias: [alias('@', 'app'), alias('$sdk', 'app/packages/sdk'), alias('$packages', 'app/packages')],
        conditions: ['browser'],
    },
    server: {cors: true},
    // do not copy index.php
    publicDir: false,

};

export default defineConfig(
    viteConfigurator(
        config,
        // Move skeleton css to configure utilities to load
        moveSkeletonClasses('app/theme/skeleton')
    )
);
