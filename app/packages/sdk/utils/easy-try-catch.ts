type AttemptSuccess<T> = readonly [T, undefined];
type AttemptFailure<E> = readonly [undefined, E];
type AttemptResult<T, E> = AttemptSuccess<T> | AttemptFailure<E>;
type AttemptResultAsync<T, E> = Promise<AttemptResult<T, E>>;

export function easyTryCatch<T = Promise<any>, E = Error>(operation: T): AttemptResultAsync<T, E>;
export function easyTryCatch<T = any, E = Error>(operation: () => T): AttemptResult<T, E>;
export function easyTryCatch<T = any, E = Error>(
    operation: Promise<T> | (() => T)
): AttemptResult<T, E> | AttemptResultAsync<T, E> {
    if (operation instanceof Promise) {
        return operation
            .then((value: T) => [value, undefined] as const)
            .catch((error: E) => [undefined, error] as const);
    }

    try {
        const data = operation();
        return [data, undefined];
    } catch (error) {
        return [undefined, error as E];
    }
}
