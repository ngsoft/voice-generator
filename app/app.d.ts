// See https://svelte.dev/docs/kit/types#app.d.ts
// for information about these interfaces
declare global {
    interface Window {
        // unsafeWindow;
        // _; // loadash
        // $: typeof import("jquery");
        // jQuery: typeof import("jquery");
    }
    namespace App {
        // interface Error {}
        // interface Locals {}
        // interface PageData {}
        // interface PageState {}
        // interface Platform {}
    }
}

export {};
