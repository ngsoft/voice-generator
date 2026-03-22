const values: Map<BackedEnum, any> = new Map();
let undef: undefined;

export function getClass(param: Function | Object): string {
    if (param instanceof Function) {
        return param.name;
    }
    return Object.getPrototypeOf(param).constructor.name;
}

export class BackedEnum {
    get name() {
        return (
            Object.keys(this.constructor).find(
                // @ts-expect-error
                (x) => this.constructor[x] === this
            ) ?? ''
        );
    }

    get value() {
        return values.get(this);
    }

    static tryFrom(value: any): BackedEnum | null {
        try {
            return this.from(value);
        } catch (_error) {}
        return null;
    }

    static from(value: any): BackedEnum {
        const instance = this.cases().find((x) => x.value === value);
        if (!instance) {
            throw new Error(`Cannot find enum ${getClass(this)} value`);
        }
        return instance;
    }

    static get default(): BackedEnum | undefined {
        return this.cases()[0];
    }

    static cases(): any[] {
        // @ts-expect-error
        return this.keys().map((x) => this[x]);
    }

    static keys(): string[] {
        return Object.keys(this).filter(
            // @ts-expect-error
            (x) => x.slice(0, 1).match(/[A-Z]/) && this[x] instanceof BackedEnum
        );
    }

    static get size(): number {
        return this.keys().length;
    }

    constructor(value: any) {
        if (Object.getPrototypeOf(this) === BackedEnum.prototype) {
            throw new Error('Cannot instantiate BackedEnum directly, it must be extended.');
        } else if (undef === value || typeof value === 'function') {
            throw new TypeError('value is not valid');
        }
        values.set(this, value);
    }
}
