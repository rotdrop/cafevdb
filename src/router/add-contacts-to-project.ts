/**
 * @copyright Copyright (c) 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type {
  RouteRecordRaw,
} from 'vue-router';

import Console from '../util/console.ts';

const COMPONENT_NAME = 'AddContactsToProjectRoute';
const logger = new Console(COMPONENT_NAME);

const AddContactsToProject = async () => {
  return import('../components/AddContactsToProject.vue');
};

export const ADD_CONTACTS_TO_PROJECT_NAME = 'AddContactsToProject';

const addContactsToProjectsRoute: RouteRecordRaw = {
  path: 'add-contacts/:addContactsProjectName',
  name: ADD_CONTACTS_TO_PROJECT_NAME,
  component: AddContactsToProject,
  props: (route) => ({ projectName: route.params.addContactsProjectName }),
  beforeEnter: (to, from) => {
    logger.info('BEFORE ADD CONTACTS TO PROJECT ENTER', {
      to,
      from,
    });
    // preserve the post-data hash
    if (from.query.hash && !to.query.hash) {
      const target = {
        name: to.name!,
        params: to.params,
        query: { ...to.query || {}, hash: from.query.hash },
        replace: to.transition === 'replace',
      };
      return target;
    }
  },
};

export default addContactsToProjectsRoute;
