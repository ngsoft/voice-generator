export type VariadicFunction = (...args: any[]) => any;
export type CallbackFunction = () => void;

export interface AttributeAccessor {
    get: (attribute: string, defaultValue: any) => any;
    set: (attribute: string, value: any) => AttributeAccessor;
    remove: (attribute: string) => AttributeAccessor;
}
