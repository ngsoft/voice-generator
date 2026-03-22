/**
 * @see https://serhiikoziy.medium.com/creating-custom-iterators-and-iterables-in-javascript-cafb5796d5e2
 */
export function createRangeIterator(start: number, end: number): Iterator<number> {
    let current = start;
    return {
        next() {
            if (current <= end) {
                return { value: current++, done: false };
            }
            return { value: undefined, done: true };
        },
    };
}

export function createRange(start: number, end: number): Iterable<number> {
    return {
        [Symbol.iterator]() {
            return createRangeIterator(start, end);
        },
    };
}
