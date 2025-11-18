/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**
 * @file
 *
 * PME table epxort.
 */

import $ from './jquery.js';
import { appPrefix } from '../config.ts';
import { LEGACY_QUERY_LOG } from '../mountable-component-names.ts';
import { GET_VUE_COMPONENT } from '../event-bus-events.ts';
import { emit as asyncEmit, getEmitResult } from '../services/async-event-bus.ts';

const vueQueryLogKey = 'vueQueryLog';

const queryLogMenu = async ($queryLogProvider: JQuery) => {
  if ($queryLogProvider.data(vueQueryLogKey)) {
    return false;
  }
  const queryLog = $queryLogProvider.data('queryLog');
  console.info('QUERY LOG', { queryLog });
  const queryLogComponent: Vue = await getEmitResult(
    asyncEmit(GET_VUE_COMPONENT, { name: LEGACY_QUERY_LOG, propsData: { queryLog } }),
  );
  queryLogComponent.$mount($queryLogProvider.find('.vue-mount-point')[0]);
  $queryLogProvider.data(vueQueryLogKey, queryLogComponent);
  return queryLogComponent;
};

const pmeQueryLogMenu = (containerSel?: string) => {
  if (typeof containerSel === 'undefined') {
    containerSel = '#' + appPrefix('page-body');
  }
  const $container = $(containerSel);
  const vueComponents = $container.data('vueComponents') || [];
  if (vueComponents.length === 0) {
    $container.data('vueComponents', vueComponents);
  }
  const $queryLogProvider = $container.find('.query-log');
  $queryLogProvider
    .find('.query-log-trigger')
    .off('click')
    .on('click', function(_event) {
      queryLogMenu($queryLogProvider).then((component) => {
        if (component) {
          vueComponents.push(component);
        }
      });
      return false;
    });
};

export default pmeQueryLogMenu;
