const tsParser = require("@typescript-eslint/parser");
const typescriptEslintPlugin = require("@typescript-eslint/eslint-plugin");
const prettierPlugin = require("eslint-plugin-prettier");

module.exports = [
    {
        files: ["**/*.ts", "**/*.svelte"],
        languageOptions: {
            parser: tsParser,
            ecmaVersion: 2022,
            sourceType: "module",
        },
        plugins: {
            "@typescript-eslint": typescriptEslintPlugin,
            prettier: prettierPlugin,
        },
        rules: {
            "prettier/prettier": [
                "error",
                {
                    printWidth: 80,
                    semi: true,
                    bracketSameLine: false,
                    trailingComma: "es5",
                    tabWidth: 4,
                    singleQuote: true,
                    bracketSpacing: true,
                    arrowParens: "always",
                    typeAnnotationSpacing: true,
                },
            ],
            "brace-style": ["error", "1tbs"],
        },
    },
];
