const findRoot = require('find-root');
const path = require('path');

module.exports = {
  // plugins: ['import'],
  settings: {
    'import/resolver': {
      jest: {
        jestConfigFile: path.resolve(findRoot(), './jest.config.ts'),
      },
    },
  },
  overrides: [
    {
      files: ['*.ts'],
      rules: {
        'n/no-missing-import': 'off',
      },
    },
  ],
};
