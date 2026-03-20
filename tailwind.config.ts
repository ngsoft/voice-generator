import type {Config} from 'tailwindcss';

const config: Config = {
    content: ['./{app,package}/**/*.{js,ts,svelte,scss,css,html}', './view/**/*.php'],
    darkMode: ['class', 'dark'],
};
export default config;
