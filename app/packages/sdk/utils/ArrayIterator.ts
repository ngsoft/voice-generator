export class ArrayIterator<T> implements Iterator<T> {
    private index: number;
    private done: boolean;
    constructor(
        private values: T[],
        private transformer: (value: T) => any = (value: T) => value
    ) {
        this.index = 0;
        this.done = false;
    }
    next(): IteratorResult<T, number | undefined> {
        if (this.done) {
            return {
                done: this.done,
                value: undefined,
            };
        }
        if (this.index === this.values.length) {
            this.done = true;
            return {
                done: this.done,
                value: this.index,
            };
        }
        const value = this.transformer(this.values[this.index]);
        this.index++;
        return {
            done: this.done,
            value,
        };
    }
}
