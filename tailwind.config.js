import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: ["./assets/**/*.{js,ts}", "./assets/**/*.{scss,css}", "./templates/**/*.php"],
    plugins: [forms],
    darkMode: ["class", '[data-mode="dark"]', '[data-bs-theme="dark"]'],
};
