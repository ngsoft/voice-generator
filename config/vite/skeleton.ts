import fs from 'node:fs';
import path from 'node:path';

function replaceFileContent(file: string, searchValue: string, replaceValue: string) {
    let content = fs.existsSync(file) ? fs.readFileSync(file, 'utf-8') : '';

    let search = searchValue,
        replace = replaceValue;
    if (file.endsWith('.css') && content.includes('@utility')) {
        search = `@utility ${searchValue}`;
        replace = `@utility ${replaceValue}`;
    }

    if (content.includes(searchValue) && !content.includes(replaceValue)) {
        console.log(`Found skeleton utility ${searchValue}, replacing by ${replaceValue}`);
        content = content.replaceAll(search, replace);
        console.log('Saving file', file);
        fs.writeFileSync(file, content, { encoding: 'utf-8' });
    }
}

export function renameSkeletonClasses(utilities: string[], prefix: string = 's-') {
    return () => {
        if (!prefix || !utilities.length) {
            return;
        }

        try {
            const dirs = [
                'node_modules/@skeletonlabs/skeleton/src/utilities/',
                'node_modules/@skeletonlabs/skeleton-common/src/components/',
                'node_modules/@skeletonlabs/skeleton-common/src/',
            ];
            for (const dir of dirs) {
                for (const file of fs.readdirSync(dir)) {
                    if (file.endsWith('.css') || file.endsWith('.ts')) {
                        for (const utility of utilities) {
                            replaceFileContent(dir + file, utility, prefix + utility);
                        }
                    }
                }
            }
        } catch (_) {}
    };
}

function scan_directory(dir: string): string[] {
    const cwd = path.resolve('.');
    const result = [];
    for (const file of fs.readdirSync(dir)) {
        const pth = path.resolve(dir, file);
        if (fs.lstatSync(pth).isDirectory()) {
            result.push(...scan_directory(pth));
        }
        if (file.endsWith('.css')) {
            result.push(pth.slice(cwd.length + 1).replaceAll('\\', '/'));
        }
    }

    return result;
}

export function moveSkeletonClasses(to: string) {
    return () => {
        if (!to) {
            return;
        }

        try {
            const dirs = ['node_modules/@skeletonlabs/skeleton/src/'];
            for (const dir of dirs) {
                for (const file of scan_directory(dir)) {
                    if (file.endsWith('.css')) {
                        const rel = file.slice(dir.length),
                            dest = path.resolve(to, rel);
                        if (!fs.existsSync(dest)) {
                            if (!fs.existsSync(path.dirname(dest))) {
                                fs.mkdirSync(path.dirname(dest), { recursive: true });
                            }

                            fs.copyFileSync(file, dest);
                            console.log(`Copied skeleton utility ${rel} to ${to}`);
                        }
                    }
                }
            }
        } catch (_) {}
    };
}
