/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, $ } from './globals.ts';
import * as Notification from './notification.ts';
import * as Ajax from './ajax.ts';
import * as CAFEVDB from './cafevdb.ts';
import * as PHPMyEdit from './pme.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';

require('project-instrumentation-numbers.scss');

const ready = function(selector?: string) {
  const $container = PHPMyEdit.container(selector);

  const $transferButton = $container.find('input.transfer-registered-instruments') as JQuery<HTMLInputElement>;
  if ($transferButton.length <= 0) {
    return;
  }
  $transferButton.off('click').on('click', function() {
    const post = $(this.form!).serialize();

    Notification.hide(function() {
      $.post(
        generateAppUrl('instrumentation/adjust'),
        post)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown);
          // Anyhow, reload and see what happens. Hit
          // either the save and continue or the reload
          // button.
          PHPMyEdit.triggerSubmit('morechange', $container)
            || PHPMyEdit.triggerSubmit('reloadview', $container)
            || PHPMyEdit.triggerSubmit('reloadlist', $container);
        })
        .done(function(data) {
          Notification.messages(data.message);

          // Anyhow, reload and see what happens. Hit
          // either the save and continue or the reload
          // button.
          PHPMyEdit.triggerSubmit('morechange', $container)
            || PHPMyEdit.triggerSubmit('reloadview', $container)
            || PHPMyEdit.triggerSubmit('reloadlist', $container);
        });
    });

    return false;
  });
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback(
    'project-instrumentation-numbers',
    {
      callback(selector, parameters, resizeCB) {
        if (parameters.reason !== 'dialogOpen') {
          resizeCB();
          return;
        }
        ready(selector);
        resizeCB();
      },
      context: globalState,
      parameters: [],
    });

  CAFEVDB.addReadyCallback(async () => {
    const $container = $(PHPMyEdit.defaultSelector + '.project-instrumentation-numbers');
    if ($container.length <= 0) {
      return; // not for us
    }
    ready();
  });

};

export { documentReady };
