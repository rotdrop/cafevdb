import { OptionDefaults } from 'typedoc';

/** @type {Partial<import('typedoc').TypeDocOptions>} */
const config = {
  $schema: 'https://typedoc.org/schema.json',
  tsconfig: '../../tsconfig.json',
  entryPoints: [
    '../../src/',
    '../../build/ts-types/php-modules/',
    '../../build/ts-types/app-config.ts',
  ],
  entryPointStrategy: 'expand',
  excludeExternals: true,
  externalPattern: '**/node_modules/**/*',
  blockTags: [
    ...OptionDefaults.blockTags,
    '@copyright',
    '@file',
  ],
  out: '../../build/artifacts/doc/typedoc',
};

export default config;
