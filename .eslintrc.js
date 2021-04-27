module.exports = {
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
  },
  rules: {
    camelcase: [
      'error', {
        allow: [
          '^__webpack_[_a-z]+__$',
          '^oc_',
          '^print_r$',
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
    'no-tabs': 'ERROR',
    indent: ['error', 2],
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
        tryExtensions: ['.js', '.json', '.node', '.css'],
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
  },
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
