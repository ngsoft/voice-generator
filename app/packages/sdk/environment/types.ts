export type ContextMap = Map<string, any>;

interface Context {
    context: ContextMap;

    [name: string]: any;
}

export interface AppData extends Context {
    id: string;
    title: string;
    prefix?: string;
}

export interface GlobalData extends Context {
    window: Window;
    document: Document;
    production: boolean;
    app: AppData;
}
