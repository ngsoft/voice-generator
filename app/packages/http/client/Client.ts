/**
 * @module tinka
 */
import { FetchMiddleware } from './middlewares/FetchMiddleware';
import { Stack } from './Stack';
import type { IFetchRequest } from './types';

export class Client extends Stack<any, any> {
    constructor(defaultOptions?: IFetchRequest | FetchMiddleware) {
        super();

        if (defaultOptions instanceof FetchMiddleware) {
            this.addMiddleware(defaultOptions);
            return;
        }

        this.addMiddleware(new FetchMiddleware(defaultOptions));
    }
}
