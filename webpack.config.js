const path = require('path');
const webpack = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const CssoWebpackPlugin = require('csso-webpack-plugin').default;
const Visualizer = require('webpack-visualizer-plugin2');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
// const CopyWebpackPlugin = require('copy-webpack-plugin');
const fs = require('fs');
const xml2js = require('xml2js');

const infoFile = path.join(__dirname, 'appinfo/info.xml');
let appInfo;
xml2js.parseString(fs.readFileSync(infoFile), function(err, result) {
  if (err) {
    throw err;
  }
  appInfo = result;
});
const appName = appInfo.info.id[0];

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
    assetModuleFilename: 'assets/[name]-[hash].[ext]',
  },
  devtool: 'source-map',
  optimization: {
    minimize: (process.env.NODE_ENV === 'production'),
    minimizer: [
      new TerserPlugin({
        parallel: true,
        //
        // minify: TerserPlugin.uglifyJsMinify,
        // `terserOptions` options will be passed to `uglify-js`
        // Link to options - https://github.com/mishoo/UglifyJS#minify-options,
        //
        // minify: TerserPlugin.swcMinify,
        // `terserOptions` options will be passed to `swc` (`@swc/core`)
        // Link to options - https://swc.rs/docs/config-js-minify
        //
        // minify: TerserPlugin.esbuildMinify,
        // `terserOptions` options will be passed to `esbuild`
        // Link to options - https://esbuild.github.io/api/#minify
        // Note: the `minify` options is true by default (and override other `minify*` options), so if you want to disable the `minifyIdentifiers` option (or other `minify*` options) please use:
        // terserOptions: {
        //   minify: false,
        //   minifyWhitespace: true,
        //   minifyIdentifiers: false,
        //   minifySyntax: true,
        // },
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
      chunkFilename: "[id].css",
    }),
    new webpack.DefinePlugin({
      APP_NAME: JSON.stringify(appName),
    }),
  ],
  module: {
    noParse: /(ckeditor.js|tinymce.min.js)/,
    rules: [
      // {
      //   test: /blueimp/,
      //   parser: { amd: false },
      // },
      // {
      //   parser: { amd: false },
      // },
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
          {
            loader: 'sass-loader',
            options: {
              // Prefer `dart-sass`
              implementation: require('sass'),
              additionalData: '$appName: ' + appName + '; $cssPrefix: ' + appName + '-;',
            },
          },
        ],
      },
      {
        test: /\.(jpe?g|png|gif)$/i,
        type: 'asset', // 'asset/resource',
        generator: {
          filename: 'css/img/[name]-[hash][ext]',
        },
      },
      {
        test: /\.svg$/i,
        use: 'svgo-loader',
        type: 'asset', // 'asset/resource',
        generator: {
          filename: 'css/img/[name]-[hash][ext]',
        },
      },
      // {
      //   test: /\.false(jpe?g|png|gif|svg)$/i,
      //   loader: 'file-loader',
      //   options: {
      //     digestType: 'base74',
      //     hashType: 'sha512',
      //     lenght: 16,
      //     name: '[name]-[hash].[ext]',
      //     outputPath: 'css/img/',
      //     publicPath: 'img',
      //     useRelativePaths: true,
      //   },
      // },
      {
        test: /\.handlebars/,
        loader: 'handlebars-loader',
        options: {
          extensions: '.handlebars',
        },
      },
    ],
  },
  resolve: {
    modules: [
      'node_modules',
      path.resolve(__dirname, 'style'),
      path.resolve(__dirname, 'src'),
      path.resolve(__dirname, '3rdparty'),
      path.resolve(__dirname, '.'),
    ],
    alias: {
      core: path.resolve(__dirname, '../../core/src'),
      tinymce: path.resolve(__dirname, '3rdparty/tinymce/tinymce.min.js'),
      // 'jquery.tinymce': path.resolve(__dirname, '3rdparty/tinymce/jquery.tinymce.min.js'),
      'jquery.tinymce': path.resolve(__dirname, '3rdparty/tinymce/JqueryIntegration.js'),
      // 'canvas-to-blob': 'blueimp-canvas-to-blob',
      // 'load-image': 'blueimp-load-image',
    },
    fallback: {
      path: require.resolve('path-browserify'),
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
