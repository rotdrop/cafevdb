/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

import { globalState, $ } from './globals.js';
import * as Notification from './notification.js';
import * as Ajax from './ajax.js';
import * as CAFEVDB from './cafevdb.js';
import * as PHPMyEdit from './pme.js';
import generateUrl from './generate-url.js';

require('project-instrumentation-numbers.css');

const ready = function(selector) {
  const container = PHPMyEdit.container(selector);

  const transferButton = container.find('input.transfer-registered-instruments');
  if (transferButton.lenght <= 0) {
    return;
  }
  transferButton.off('click').on('click', function(event) {
    const post = $(this.form).serialize();

    Notification.hide(function() {
      $.post(
        generateUrl('instrumentation/adjust'),
        post)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown);
          // Anyhow, reload and see what happens. Hit
          // either the save and continue or the reload
          // button.
          PHPMyEdit.triggerSubmit('morechange', container)
            || PHPMyEdit.triggerSubmit('reloadview', container)
            || PHPMyEdit.triggerSubmit('reloadlist', container);
        })
        .done(function(data) {
          if (data.message !== '') {
            Notification.show(data.message, { timeout: 10 });
          }
          // Anyhow, reload and see what happens. Hit
          // either the save and continue or the reload
          // button.
          PHPMyEdit.triggerSubmit('morechange', container)
            || PHPMyEdit.triggerSubmit('reloadview', container)
            || PHPMyEdit.triggerSubmit('reloadlist', container);
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

  CAFEVDB.addReadyCallback(function() {
    const container = $(PHPMyEdit.defaultSelector + '.project-instrumentation-numbers');
    if (container.length <= 0) {
      return; // not for us
    }
    ready();
  });

};

export { documentReady };

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
