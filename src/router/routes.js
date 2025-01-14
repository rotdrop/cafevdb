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

import { appName } from '../app/app-info.js';

const routes = [
  {
    path: '/',
    props: router => ({
      routeTitle: t(appName, 'Home'),
    }),
  },
  {
    path: '/f/projects',
    component: () => import('../views/Projects.vue'),
    name: 'projects',
    props: route => ({
      routeTitle: t(appName, 'Projects'),
    }),
  },
  {
    path: '/f/musicians',
    component: () => import('../views/Musicians.vue'),
    name: 'musicians',
    props: route => ({
      routeTitle: t(appName, 'Musicians'),
    }),
  },
  {
    path: '/f/project-participants/:projectId/:projectName?',
    component: () => import('../views/ProjectParticipants.vue'),
    name: 'project-participants',
    props: route => ({
      routeTitle: t(appName, 'Project Participants'),
      projectId: +route.params?.projectId || -1,
      projectName: route.params?.projectName || '',
    }),
  },
  {
    path: '/f/project-instrumentation-numbers/:projectId/:projectName?',
    component: () => import('../views/ProjectInstrumentationNumbers.vue'),
    name: 'project-instrumentation-numbers',
    props: route => ({
      routeTitle: t(appName, 'Instrumentation Numbers'),
      projectId: +route.params?.projectId || -1,
      projectName: route.params?.projectName || '',
    }),
  },
];

export default routes;
