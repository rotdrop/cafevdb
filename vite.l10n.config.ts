/**
 * Vite configuration wrapper used by `make l10n-ignore`. It reuses the regular
 * configuration but moves the output directory away from the source tree
 * (the default of the Nextcloud config is the app root, which Vite warns
 * about) and disables CSS minification, which is irrelevant for the analysis. Nothing is written by the analysis build.
 */
import { mergeConfig, type ConfigEnv, type UserConfig } from 'vite';
import baseConfig from './vite.config.ts';

export default async (env: ConfigEnv): Promise<UserConfig> => {
  const base = typeof baseConfig === 'function' ? await baseConfig(env) : await baseConfig;
  return mergeConfig(base, { build: { outDir: 'build/l10n-vite', cssMinify: false } });
};
