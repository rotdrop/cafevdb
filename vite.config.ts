import type { OutputAsset, OutputChunk } from 'rolldown';
import type { Config as SVGOConfig } from 'svgo';
import type { Plugin } from 'vite';

import { createAppConfig } from '@nextcloud/vite-config';
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
// import { patchCssModules } from 'vite-css-modules';
import svg from 'vite-plugin-svgo';
import { CSS, JS, WEB_ASSET_META } from './build/ts-types/php-modules/Constants.ts';

const appInfoFile = path.join(import.meta.dirname, 'appinfo/info.xml');
const appInfoContent = String(readFileSync(appInfoFile));
// const appVersion = appInfoContent.match(/<version>([^<]+)<\/version>/i)[1];
const appName = appInfoContent?.match(/<id>([^<]+)<\/id>/i)?.[1];
const assetsPrefix = (`${appName}-`).replaceAll(/[/\\]/g, '-');
const hashLength = 8;
const cssFolder = 'css' as const;
const jsFolder = 'js' as const;

if (path.dirname(WEB_ASSET_META) !== jsFolder) {
  throw new Error(`Inconsitency: "${WEB_ASSET_META}" does not start with "${jsFolder}/".`);
}

const svgoConfig: SVGOConfig = {
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
          // removeViewBox: false,
        },
      },
    },
  ],
};

const isEntryOutputChunk = (arg: OutputAsset|OutputChunk): arg is OutputChunk =>
  arg.type === 'chunk' && arg.isEntry === true;

/**
 * Record the assets in asset-metadata.json in order for the PHP code to find it.
 */
function postBuildHook(): Plugin {
  return {
    name: 'Post Build Hook',
    enforce: 'post',
    // apply: 'build',
    // generateBundle(_, bundle) {
    //   for (const output of Object.values(bundle)) {
    //     if (!isEntryOutputChunk(output)) {
    //       continue;
    //     }
    //     console.info('GENERATE BUNDLE', { ...output });
    //   }
    // },
    writeBundle(_, bundle) {
      const entryPoints: Record<string, { [JS]: string; [CSS]?: string }> = {};
      for (const output of Object.values(bundle)) {
        if (!isEntryOutputChunk(output)) {
          continue;
        }
        const hashedName = path.basename(output.fileName, '.mjs').replace(assetsPrefix, '');
        const base = hashedName.substring(0, hashedName.length - hashLength - 1);
        const cssAsset = path.join('css', `${assetsPrefix}${hashedName}.css`);
        entryPoints[base] = { [JS]: output.fileName, [CSS]: bundle[cssAsset]?.fileName };
      }
      const assetMetaPath = path.resolve(import.meta.dirname, WEB_ASSET_META);
      writeFileSync(assetMetaPath, JSON.stringify(entryPoints, undefined, 2));
    },
  };
}

const overrides = defineConfig({
  css: {
    preprocessorOptions: {
      scss: {
        // api: 'modern-compiler',
        loadPaths: [
          path.resolve(import.meta.dirname, './style'),
        ],
        additionalData: '$dokuWikiAppName: dokuwiki;',
        quietDeps: true,
      },
    },
    modules: {
      localsConvention: 'camelCaseOnly',
      exportGlobals: false,
      scopeBehaviour: 'global',
    },
    // transformer: 'lightningcss',
    // lightningcss: {
    //   cssModules: true,
    // },
  },
  define: {
    APP_NAME: JSON.stringify(appName),
  },
  optimizeDeps: {
    rolldownOptions: {
      transform: {
        target: 'esnext',
      },
    },
  },
  build: {
    target: 'esnext',
    cssCodeSplit: true,
    manifest: true,
    modulePreload: false,
    rolldownOptions: {
      transform: {
        target: 'esnext',
      },
      output: {
        intro: '',
        entryFileNames: () => {
          return `${jsFolder}/${assetsPrefix}[name]-[hash].mjs`;
        },
        chunkFileNames: () => {
          return `${jsFolder}/chunks/[name]-[hash].chunk.mjs`;
        },
      },
      logLevel: 'silent',
    },
  },
  resolve: {
    alias: [
      // Aliases are non-recursive and first-come first-serve
      { find: /^(chosen\/.*)$/, replacement: path.resolve(import.meta.dirname, './3rdparty/$1') },
      { find: /^(selectize\/.*\.scss)/, replacement: '$1' },
      { find: /^([^./@].*)\.scss$/, replacement: path.resolve(import.meta.dirname, './style/$1.scss') },
    ],
  },
  plugins: [
    // _@ts-expect-error 2345 Blah blah blah.
    svg(svgoConfig),
    postBuildHook(),
  ],
});

const appConfig = createAppConfig(
  {
    // entry points: {name: script}
    app: path.resolve(path.join('src', 'app.ts')),
    'vue-app': path.resolve(path.join('src', 'vue-app.ts')),
    'admin-settings': path.resolve(path.join('src', 'admin-settings.ts')),
    settings: path.resolve(path.join('src', 'settings.ts')),
    'background-jobs': path.resolve(path.join('src', 'background-jobs.ts')),
    'files-hooks': path.resolve(path.join('src', 'files-hooks.ts')),
    'files-sidebar-hooks': path.resolve(path.join('src', 'files-sidebar-hooks.ts')),
    'iframe-content-script': path.resolve(path.join('src', 'iframe-content-script.ts')),
  },
  {
    // coreJS: {
    //   modules: 'core-js/es',
    //   usage: true,
    // },
    config: overrides,
    assetsPrefix,
    assetFileNames: (assetInfo) => {
      const [name] = assetInfo.names;
      const extType = name.split('.').pop()!;
      if (/png|jpe?g|svg|gif|tiff|bmp|ico/i.test(extType)) {
        return `${cssFolder}/img/[name][extname]`;
      } else if (/css/i.test(extType)) {
        // we need hashed css name for css chunks as a cache buster
        return `${cssFolder}/[name]-[hash].css`;
      } else if (/woff2?|ttf|otf/i.test(extType)) {
        return `${cssFolder}/fonts/[name][extname]`;
      }
      return 'dist/[name]-[hash][extname]';
    },
    extractLicenseInformation: {},
    codeSplitting: {
      groups: [
        { name: 'shared', minShareCount: 2, minSize: 70_000 },
        { name: 'common', entriesAware: true, entriesAwareMergeThreshold: 90_000, minSize: 70_000 },
        { name: 'vendor', test: /node_modules/ },
        { name: 'remain' },
      ],
    },
  },
);

console.info(appConfig);

export default appConfig;
