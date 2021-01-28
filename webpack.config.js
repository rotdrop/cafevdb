const path = require('path');
const webpack = require('webpack');

module.exports = {
  entry: './src/cafevdb.js',
  output: {
    filename: 'cafevdb.js',
    path: path.resolve(__dirname, 'dist'),
  },
  devtool: false,//'source-map',
  resolve: {
    alias: {
      cafevdb$: path.resolve(__dirname, './src/cafevdb/core.js'),
    },
  },
  module: {
    rules: [
      {
        test: require.resolve('./src/cafevdb/core.js'),
        loader: 'expose-loader',
        options: {
          exposes: {
            globalName: 'CAFEVDB',
            override: true,
          },
        },
      },
    ],
  },
};
