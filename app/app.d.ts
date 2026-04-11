import type { IStaticMethods } from "flyonui/flyonui";

declare global {
    interface Window {
        HSStaticMethods: IStaticMethods;
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


