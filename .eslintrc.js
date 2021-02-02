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
  },
  rules: {
    'camelcase': [
      'error', {
        allow: [
          '^__webpack_[_a-z]+__$',
        ],
      },
    ],
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
        ],
        tryExtensions: ['.js', '.json', '.node', '.css']
      },
    ],
    'node/no-missing-require': [
      'error', {
        // 'allowModules': [],
        resolvePaths: [
          './src',
          './style',
          './3rdparty',
        ],
        tryExtensions: ['.js', '.json', '.node', '.css']
      },
    ],
  },
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
