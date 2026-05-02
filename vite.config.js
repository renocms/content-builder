import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import { createExtensionConfig } from '../reno-cms/tools/vite/createExtensionConfig.mjs';

const packageDirectory = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(
    createExtensionConfig({
        packageDirectory,
        base: '/js/reno/content-builder/build/',
        entryDefinitions: [
            {
                type: 'directory',
                relativeDirectory: 'components',
                extension: '.vue',
            },
            {
                type: 'directory',
                relativeDirectory: 'plugins',
                extension: '.js',
            },
        ],
        externalizeCmsRuntime: true,
    }),
);
