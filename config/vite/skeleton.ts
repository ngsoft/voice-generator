import fs from 'node:fs';

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
