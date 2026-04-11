import type {Config} from 'tailwindcss';

const config: Config = {
    content: ['./node_modules/flyonui/dist/*.js', './{app,package}/**/*.{js,ts,svelte,scss,css,html}', './view/**/*.php'],
    darkMode: ['class', 'dark'],
};
export default config;
