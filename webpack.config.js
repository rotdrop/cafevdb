const path = require('path');
const webpack = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const Visualizer = require('webpack-visualizer-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
// const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  entry: {
    app: './src/app.js',
    'admin-settings': './src/admin-settings.js',
    settings: './src/settings.js',
  },
  output: {
    // path: path.resolve(__dirname, 'js'),
    path: path.resolve(__dirname, '.'),
    filename: 'js/[name].js',
  },
  devtool: 'source-map',
  optimization: {
    minimize: (process.env.NODE_ENV === 'production'),
    minimizer: [
      new TerserPlugin({
        cache: true,
        parallel: true,
        sourceMap: true, // Must be set to true if using source-maps in production
        terserOptions: {
          // https://github.com/webpack-contrib/terser-webpack-plugin#terseroptions
        },
      }),
      new CssMinimizerPlugin(),
    ],
  },
  plugins: [
    new BundleAnalyzerPlugin({
      analyzerPort: 11111,
      analyzerMode: 'static',
      openAnalyzer: false,
    }),
    new Visualizer({
      filename: './visualizer-stats.html',
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      jquery: 'jquery',
      'window.$': 'jquery',
      'window.jQuery': 'jquery',
    }),
    new MiniCssExtractPlugin({
      filename: 'css/[name].css',
    }),
    // new CopyWebpackPlugin({
    //   patterns: [
    //     { from: './3rdparty/tinymce/plugins', to: 'js/plugins' },
    //     { from: './3rdparty/tinymce/themes', to: 'js/themes' },
    //     { from: './3rdparty/tinymce/skins', to: 'js/skins' },
    //     { from: './3rdparty/tinymce/skins', to: 'js/langs' },
    //   ],
    // }),
  ],
  module: {
    noParse: /(ckeditor.js|tinymce.min.js)/,
    rules: [
      {
        test: /\.xml$/i,
        use: 'xml-loader',
      },
      {
        test: /\.css$/,
        use: [
          // 'style-loader',
          MiniCssExtractPlugin.loader,
          'css-loader',
        ],
      },
      {
        test: /\.s(a|c)ss$/,
        use: [
          // 'style-loader',
          MiniCssExtractPlugin.loader,
          'css-loader',
          'sass-loader',
        ],
      },
      {
        test: /\.(jpe?g|png|gif|svg)$/i,
        loader: 'file-loader',
        options: {
          name: '[name].[ext]',
          outputPath: 'css/img/',
          publicPath: 'img',
          useRelativePaths: true,
        },
      },
    ],
  },
  resolve: {
    modules: [
      'node_modules',
      'style',
      'src',
      '3rdparty',
      path.resolve(__dirname, '.'),
    ],
    alias: {
      core: path.resolve(__dirname, '../../core/src'),
      tinymce: path.resolve(__dirname, '3rdparty/tinymce/tinymce.min.js'),
      // 'jquery.tinymce': path.resolve(__dirname, '3rdparty/tinymce/jquery.tinymce.min.js'),
      'jquery.tinymce': path.resolve(__dirname, '3rdparty/tinymce/JqueryIntegration.js'),
    },
  },
  // externals: {
  //   tinymce: 'window tinymce',
  // },
};

/**
 * Local Variables: ***
 * js-indent-level: 2 ***
 * indent-tabs-mode: nil ***
 * End: ***
 */
