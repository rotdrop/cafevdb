const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
// const CopyWebpackPlugin = require('copy-webpack-plugin');
const BabelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const CssoWebpackPlugin = require('csso-webpack-plugin').default;
const ESLintPlugin = require('eslint-webpack-plugin');
const fs = require('fs');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');
const webpack = require('webpack');
const NodePolyfillPlugin = require('node-polyfill-webpack-plugin')
const TerserPlugin = require('terser-webpack-plugin');
const DeadCodePlugin = require('webpack-deadcode-plugin')
const Visualizer = require('webpack-visualizer-plugin2');
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
const productionMode = process.env.NODE_ENV === 'production';

module.exports = {
  entry: Object.entries({
    app: './src/app.js',
    'admin-settings': './src/admin-settings.js',
    settings: './src/settings.js',
    'background-jobs': './src/background-jobs.js',
    'files-hooks': './src/files-hooks.js',
    'files-hooks-ts': './src/files-hooks.ts',
    'files-sidebar-hooks': './src/files-sidebar-hooks.js',
    'iframe-content-script': './src/iframe-content-script.js',
  })
    .filter(([name, entry]) => !process.env.ONLY || process.env.ONLY === name)
    .reduce((a, [name, entry]) => Object.assign(a, { [name]: entry }), {}),
  output: {
    // path: path.resolve(__dirname, 'js'),
    path: path.resolve(__dirname, '.'),
    publicPath: '',
    filename: 'js/[name]-[contenthash].js',
    assetModuleFilename: 'js/assets/[name]-[hash][ext][query]',
    chunkFilename: 'js/chunks/[name]-[contenthash].js',
    clean: false,
    compareBeforeEmit: true, // true would break the Makefile
  },
  devtool: 'source-map',
  optimization: {
    minimize: productionMode,
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
      reportFilename: './statistics/bundle-analyzer.html',
    }),
    new Visualizer({
      filename: './statistics/visualizer-stats.html',
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      jquery: 'jquery',
      'window.$': 'jquery',
      'window.jQuery': 'jquery',
      process: 'process/browser.js',
    }),
    new MiniCssExtractPlugin({
      filename: 'css/[name]-[contenthash].css',
    }),
    new CssoWebpackPlugin(
      {
        pluginOutputPostfix: productionMode ? null : 'min',
      },
      productionMode ? /\.css$/ : /^$/
    ),
    new webpack.DefinePlugin({
      APP_NAME: JSON.stringify(appName),
    }),
    new HtmlWebpackPlugin({
      inject: false,
      filename: 'js/asset-meta.json',
      minify: false,
      templateContent(arg) {
        return JSON.stringify(arg.htmlWebpackPlugin.files, null, 2);
      },
    }),
    new ESLintPlugin({
      extensions: ['js', 'vue'],
      exclude: [
        'node_modules',
        '3rdparty',
        'src/legacy',
      ],
    }),
    new VueLoaderPlugin(),
    new NodePolyfillPlugin(),
    new DeadCodePlugin({
      patterns: [
        'src/**/*.(vue|js|jsx|css)',
        'style/**/*.scss',
      ],
      exclude: [
      ],
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
              additionalData: '$appName: ' + appName + '; $cssPrefix: ' + appName + '-' + '; $dokuWikiAppName: dokuwiki;',
            },
          },
        ],
      },
      {
        test: /\.(jpe?g|png|gif)$/i,
        type: 'asset', // 'asset/resource',
        generator: {
          filename: './css/img/[name]-[hash][ext]',
          publicPath: '../',
        },
      },
      {
        test: /\.svg$/i,
        loader: 'svgo-loader',
        type: 'asset', // 'asset/resource',
        generator: {
          filename: './css/img/[name]-[hash][ext]',
          publicPath: '../',
        },
        options: {
          multipass: true,
          js2svg: {
            indent: 2,
            pretty: true,
          },
          plugins: [
            {
              name: 'preset-default',
              params: {
                overrides: {
                  // viewBox is required to resize SVGs with CSS.
                  // @see https://github.com/svg/svgo/issues/1128
                  removeViewBox: false,
                },
              },
            },
          ],
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
      {
        test: /\.vue$/,
        loader: 'vue-loader',
        exclude: BabelLoaderExcludeNodeModulesExcept([
          'vue-material-design-icons',
          'emoji-mart-vue-fast',
        ]),
      },
      {
        test: /\.tsx?$/,
        use: [
          'babel-loader',
          {
            // Fix TypeScript syntax errors in Vue
            loader: 'ts-loader',
            options: {
              transpileOnly: true,
            },
          },
        ],
        exclude: BabelLoaderExcludeNodeModulesExcept([]),
      },
      {
        test: /\.js$/,
        loader: 'babel-loader',
        // automatically detect necessary packages to
        // transpile in the node_modules folder
        exclude: BabelLoaderExcludeNodeModulesExcept([
          '@nextcloud/dialogs',
          '@nextcloud/event-bus',
          'davclient.js',
          'nextcloud-vue-collections',
          'p-finally',
          'p-limit',
          'p-locate',
          'p-queue',
          'p-timeout',
          'p-try',
          'semver',
          'striptags',
          'toastify-js',
          'v-tooltip',
          'yocto-queue',
        ]),
      },
      {
        resourceQuery: /raw/,
        type: 'asset/source',
      },
      {
        test: /\.svg$/i,
        resourceQuery: /raw/,
        loader: 'svgo-loader',
        type: 'asset/source',
        options: {
          multipass: true,
          js2svg: {
            indent: 2,
            pretty: true,
          },
          plugins: [
            {
              name: 'preset-default',
              params: {
                overrides: {
                  // viewBox is required to resize SVGs with CSS.
                  // @see https://github.com/svg/svgo/issues/1128
                  removeViewBox: false,
                },
              },
            },
          ],
        },
      },
    ],
  },
  resolve: {
    modules: [
      // path.resolve(__dirname, 'node_modules'),
      path.resolve(__dirname, 'style'),
      path.resolve(__dirname, 'src'),
      path.resolve(__dirname, '3rdparty'),
      path.resolve(__dirname, 'img'),
      path.resolve(__dirname, '.'),
      'node_modules',
    ],
    alias: {
      core: path.resolve(__dirname, '../../core/src'),
      tinymce: path.resolve(__dirname, '3rdparty/tinymce/tinymce.min.js'),
      // 'jquery.tinymce': path.resolve(__dirname, '3rdparty/tinymce/jquery.tinymce.min.js'),
      'jquery.tinymce': path.resolve(__dirname, '3rdparty/tinymce/JqueryIntegration.js'),
      // 'canvas-to-blob': 'blueimp-canvas-to-blob',
      // 'load-image': 'blueimp-load-image',
      vue$: path.resolve('./node_modules/vue'),
    },
    fallback: {
      buffer: require.resolve('buffer'),
      http: require.resolve('stream-http'),
      https: require.resolve('https-browserify'),
      path: require.resolve('path-browserify'),
      stream: require.resolve('stream-browserify'),
    },
    extensions: ['*', '.js', '.vue', 'ts'],
    extensionAlias: {
      /**
       * Resolve TypeScript files when using fully-specified esm import paths
       * https://github.com/webpack/webpack/issues/13252
       */
      '.js': ['.js', '.ts'],
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
