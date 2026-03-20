import path from 'node:path';
import type { Alias, ConfigEnv, UserConfig } from 'vite';

export * from './skeleton';

type ViteConfigurationHandler =
    | ((config: UserConfig, env: ConfigEnv) => UserConfig)
    | ((config: UserConfig, env: ConfigEnv) => void);

// { mode, command }: ConfigEnv
export function viteConfigurator(
    config: UserConfig,
    ...handlers: ViteConfigurationHandler[]
): (cfg: ConfigEnv) => UserConfig {
    return (env: ConfigEnv): UserConfig => {
        for (const handler of handlers) {
            const result = handler(config, env);
            if (result) {
                config = result;
            }
        }
        return config;
    };
}

let cwd!: string;

function normalizePath(input: string): string {
    return input.replaceAll('\\', '/').replace(/\/+$/, '');
}

function resolvePath(segment: string, ...segments: string[]): string {
    cwd ??= process.cwd();
    return normalizePath(path.resolve(cwd, segment, ...segments));
}

export function alias(alias: string | RegExp, toPath: string): Alias {
    return {
        find: alias,
        replacement: resolvePath(toPath),
    };
}
