import { environment } from '$sdk/environment';

environment.app.theme = {
    light: import.meta.env.VITE_THEME_CONTROLLER_LIGHT ?? 'light',
    dark: import.meta.env.VITE_THEME_CONTROLLER_DARK ?? 'dark',
    skeleton: import.meta.env.VITE_THEME_SKELETON ?? '',
};

export { environment };

console.debug('env', environment);
