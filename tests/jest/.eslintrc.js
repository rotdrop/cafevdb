module.exports = {
  // plugins: ['import'],
  settings: {
    'import/resolver': {
      jest: {
        jestConfigFile: './jest.config.ts',
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
