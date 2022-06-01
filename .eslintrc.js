module.exports = {
  parserOptions: {
    sourceType: 'module',
    ecmaVersion: 2015,
  },
  globals: {
    __webpack_nonce__: true,
    __webpack_public_path__: true,
    _: true,
    $: true,
    jQuery: true,
    moment: true,
    escapeHTML: true,
    oc_userconfig: true,
    dayNames: true,
    firstDay: true,
    APP_NAME: true,
    XULDocument: true,
  },
  plugins: ['jsdoc'],
  rules: {
    // @nextcloud: force proper JSDocs
    'jsdoc/require-returns': 0,
    'jsdoc/require-returns-description': 0,
    'jsdoc/tag-lines': ['off'],
    //
    // use 2 SPACES indentation
    'vue/html-indent': ['error', 2],
    // PascalCase components names for vuejs
    // https://vuejs.org/v2/style-guide/#Single-file-component-filename-casing-strongly-recommended
    'vue/component-name-in-template-casing': ['error', 'PascalCase'],
    // force name
    'vue/match-component-file-name': ['error', {
      extensions: ['jsx', 'vue', 'js'],
      shouldMatchCase: true,
    }],
    // space before self-closing elements
    'vue/html-closing-bracket-spacing': 'error',
    // Do allow line-break before closing brackets
    'vue/html-closing-bracket-newline': ['error', { singleline: 'never', multiline: 'always' }],
    // code spacing with attributes
    'vue/max-attributes-per-line': ['error', {
      singleline: 3,
      multiline: {
        max: 1,
        allowFirstLine: true,
      },
    }],
    // custom event naming convention
    'vue/custom-event-name-casing': ['error', 'kebab-case', {
      // allows custom xxxx:xxx events formats
      ignores: ['/^[a-z]+(?:-[a-z]+)*:[a-z]+(?:-[a-z]+)*$/u'],
    }],
    //
    // other
    //
    'no-tabs': ['error', { allowIndentationTabs: false }],
    indent: ['error', 2],
    'no-mixed-spaces-and-tabs': 'error',
    //
    camelcase: [
      'error', {
        allow: [
          '^__webpack_[_a-z]+__$',
          '^oc_',
          '^print_r$',
          '_key$',
          'disable_search',
          'inherit_select_classes',
          'allow_single_deselect',
          'single_backstroke_delete',
          'no_results_text',
          'placeholder_text_single',
        ],
      },
    ],
    'node/no-unpublished-import': 'off',
    'node/no-unpublished-require': 'off',
    'no-console': 'off',
    semi: ['error', 'always'],
    'node/no-missing-import': [
      'error', {
        // 'allowModules': [],
        resolvePaths: [
          './src',
          './style',
          './3rdparty',
          './',
        ],
        tryExtensions: ['.js', '.json', '.node', '.css', '.vue'],
      },
    ],
    'operator-linebreak': [
      'error',
      'before',
      {
        'overrides': {
          '=': 'after',
          '+=': 'after',
          '-=': 'after',
        },
      },
    ],
    'node/no-missing-require': [
      'error', {
        // 'allowModules': [],
        resolvePaths: [
          './src',
          './style',
          './3rdparty',
          './',
        ],
        tryExtensions: ['.js', '.json', '.node', '.css', '.xml'],
      },
    ],
  }, // rules
  overrides: [
    {
      files: ['*.vue'],
      rules: {
        semi: ['error', 'never'],
      },
    },
  ],
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
