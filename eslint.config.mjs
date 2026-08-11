// -*- mode: javascript -*-

import { recommended } from '@nextcloud/eslint-config';
import { defineConfig, globalIgnores } from 'eslint/config';

const configOptions = [
  ...recommended,
  {
    name: 'undo gitignores',
    ignores: [
      '!build',
      'build/*',
      '!build/ts-types/',
    ],
  },
  {
    files: ['**/*.vue'],
    rules: {
      'vue/no-useless-v-bind': [
        'error',
        {
          ignoreIncludesComment: true,
          ignoreStringEscape: false,
        },
      ],
      'vue/attribute-hyphenation': ['error', 'never'],
      'vue/html-indent': ['error', 2],
      'vue/html-closing-bracket-newline': ['error', {
        singleline: 'never',
        multiline: 'always',
      }],
      'vue/first-attribute-linebreak': [
        'error',
        {
          multiline: 'beside',
        },
      ],
      semi: ['error', 'never'],
      'no-tabs': ['error', {
        allowIndentationTabs: false,
      }],
      indent: [
        'error',
        2,
        {
          SwitchCase: 1,
        },
      ],
      '@stylistic/indent': ['error', 2],
      // '@stylistic/indent-binary-ops': ['error', 2],
      '@stylistic/indent-binary-ops': 'off', // this is just brain damaged madness
      '@stylistic/padded-blocks': 'off',
      '@stylistic/implicit-arrow-linebreak': 'off',
      '@stylistic/space-infix-ops': [
        'error',
        {
          ignoreTypes: true,
        },
      ],
      '@stylistic/function-paren-newline': [
        'error',
        'consistent',
      ],
      'no-mixed-spaces-and-tabs': 'error',
      'no-console': 'off',
      'antfu/top-level-function': 'off',
    },
  },
  {
    files: ['**/*.js', '**/*.mjs', '**/*.ts', '**/*.cts', '**/*.mts', '**/*.tsx'],
    rules: {
      '@stylistic/max-statements-per-line': [
        'error',
        {
          max: 2,
        },
      ],
      '@stylistic/semi': ['error', 'always'],
      '@stylistic/indent': ['error', 2],
      // '@stylistic/indent-binary-ops': ['error', 2],
      '@stylistic/indent-binary-ops': 'off', // this is just brain damaged madness leading to unreadable code
      '@stylistic/function-paren-newline': [
        'error',
        'consistent',
      ],
      '@stylistic/space-infix-ops': [
        'error',
        {
          ignoreTypes: true,
        },
      ],
      '@stylistic/implicit-arrow-linebreak': 'off',
      '@stylistic/member-delimiter-style': [
        'error',
        {
          multiline: {
            delimiter: 'semi',
          },
        },
      ],
      '@stylistic/padded-blocks': 'off',
      // 'n/no-unpublished-import': 'off',
      // 'n/no-unpublished-require': 'off',
      'no-tabs': ['error', {
        allowIndentationTabs: false,
      }],
      indent: [
        'error',
        2,
        {
          SwitchCase: 1,
        },
      ],
      'no-mixed-spaces-and-tabs': 'error',
      semi: ['error', 'always'],
      'no-console': 'off',
      // 'n/no-missing-require': ['error', {
      //   resolvePaths: ['./src', './style', './'],
      //   tryExtensions: ['.js', '.json', '.node', '.css', '.scss', '.ts', '.xml', '.vue'],
      // }],
      'antfu/top-level-function': 'off',
      '@stylistic/operator-linebreak': [
        'error',
        'after',
        {
          overrides: {
            '=': 'after',
            '|': 'before',
            '||': 'before',
            '&&': 'before',
            '?': 'before',
            '+': 'before',
            ':': 'before',
          },
        },
      ],
    },
  },
  globalIgnores([
    '!build/ts-types',
    '!build/ts-types/**',
  //   'src/toolkit/util/file-node-helper.js',
  //   'src/toolkit/util/file-download.js',
  //   'src/toolkit/util/dialogs.js',
  //   'src/toolkit/util/ajax.js',
  //   'src/toolkit/util/jquery.js',
  //   'src/toolkit/types/event-bus.d.ts',
  //   'src/toolkit/util/axios-file-download.ts',
  //   'src/toolkit/util/file-node-helper.ts',
  //   'src/toolkit/util/nextcloud-sidebar-root.ts',
  ]),
  {
    files: [
      '**/*.ts',
      '**/*.cts',
      '**/*.mts',
      '**/*.tsx',
      '**/*.vue',
    ],
    rules: {
      '@typescript-eslint/no-unused-vars': [
        'warn',
        {
          argsIgnorePattern: '^_',
        },
      ],
      '@typescript-eslint/no-require-imports': [
        'error',
        {
          allow: ['.*\\.scss$'],
        },
      ],
    },
  },
];

// console.info(configOptions);

export default defineConfig(configOptions);
