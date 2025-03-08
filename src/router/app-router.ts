/**
 * @copyright Copyright (c) 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 *
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

import { appName } from '../config.ts';
import Vue from 'vue';
import Router from 'vue-router';
import type { RouterOptions, Route } from 'vue-router';
import { generateUrl } from '@nextcloud/router';
import appRoutes from './routes.js';
import { isNavigationFailure, NavigationFailureType } from 'vue-router'
import Console from '../util/console.ts';

const COMPONENT_NAME = 'app-router';
const logger = new Console(COMPONENT_NAME);

Vue.use(Router);

const base = generateUrl('/apps/' + appName);

const options: RouterOptions = {
  mode: 'history',
  base,
  linkActiveClass: 'active',
  routes: appRoutes,
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) {
      return { behavior: 'smooth', ...savedPosition };
    } else if (to.hash) {
      return {
        selector: to.hash,
        behavior: 'smooth',
      };
    }
  },
  // Disable throwing errors on redirection. We use this to
  // re-"mis"-use the calendar-app editor widgets which otherwise would
  // lead to an unhandled promise error.
  navigationPromiseFactory(arg) {
    const { promise, resolve, reject } = Promise.withResolvers<Route>();

    arg(resolve, (error) => {
      logger.debug('NAVIGATION PROMISE REJECT', { error });
      if (isNavigationFailure(error, NavigationFailureType.redirected)) {
        logger.debug('Catch and ignore redirection navigation error', { error});
        resolve(error.to);
      // } else if (isNavigationFailure(error, NavigationFailureType.aborted)
      //            && error.to.path.endsWith('--never--')) {
      //   resolve(error.from);
      } else {
        reject(error);
      }
    });

    return promise;
  }
};

const router = new Router(options);

export default router;
