/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import cwd from 'cwd';
import path from 'node:path';
import { configDefaults, defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config.ts';

const APP_ROOT = cwd();

export default defineConfig(async (configEnv) => {
  const result = mergeConfig(
    configDefaults,
    mergeConfig(
      await viteConfig(configEnv),
      defineConfig({
        define: {
          TEST_ARTIFACTS: JSON.stringify(`${APP_ROOT}/build/artifacts/tests/vitest`),
        },
        resolve: {
          alias: [
            { find: '~', replacement: path.resolve(import.meta.dirname) },
          ],
        },
        test: {
          environment: 'jsdom',
          testTimeout: 15000,
          include: [
            './tests/vitest/**/*.{test,spec}.?(c|m)[jt]s?(x)',
          ],
          coverage: {
            // provider: 'instanbul'|'v8'
            enabled: true,
            reportsDirectory: './build/artifacts/tests/vitest/coverage',
            reporter: [
              'html',
              'text',
            ],
          },
        },
      }),
    ),
  );
  // const target = [
  //   // 'chrome124', // <- [BUNDLER_INITIALIZE_ERROR] 'chrome124' is already specified.
  //   'edge147',
  //   'firefox125',
  //   'ios17.5',
  //   'opera131',
  //   'safari17.6',
  // ];
  result.build.cssTarget = 'esnext';
  result.build.target = 'esnext';
  result.oxc.target = 'esnext';
  // console.info({
  //   // json: JSON.stringify(result, undefined, 2),
  //   alias: result.resolve.alias,
  //   build: result.build,
  //   rolldownOpt: result.optimizeDeps.rolldownOptions.transform.target,
  //   rolldownBuild: result.build.rolldownOptions.transform.target,
  // oxc: result.oxc,
  // });
  return result;
});
