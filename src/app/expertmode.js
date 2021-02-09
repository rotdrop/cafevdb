/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import generateUrl from './generate-url.js';

const documentReady = function() {

  const container = $('.app-admin-settings');

  // container.on('click', 'button', function(event) {
  //   OC.dialogs.alert(t(appName, 'Unhandled expert operation: {operation}', {operation: $(this).val()}),
  //                    t(appName, 'Error'),
  //                    undefined, true, true);
  //   return false;
  // });

  const simpleActions = [
    'clearoutput',
    'example',
    'makeviews',
    'syncevents',
    'wikiprojecttoc',
    'attachwebpages',
    'sanitizephones',
    'geodata',
    'uuid',
    'imagemeta',
  ];

  simpleActions.forEach(function(action, index) {
    container.on('click', '#' + action, function() {
      const msg = container.find('.msg');
      const error = container.find('.error');
      $.post(generateUrl('expertmode/action/' + action), { data: {} })
        .done(function(data) {
          console.log(data);
          error.html('').hide();
          msg.html(data.message).show();
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown);
          msg.hide();
          error.html(Ajax.failMessage(xhr, status, errorThrown)).show();
        });
      return false;
    });
  });

  container.on('click', '#setupdb', function() {
    const msg = container.find('.msg');
    const error = container.find('.error');
    $.post(generateUrl('expertmode/action/setupdb'), { data: {} })
      .done(function(data) {
        console.log(data);
        if (!Ajax.validateResponse(data, ['success', 'error'])) {
          return;
        }
        Dialogs.alert(
          t(appName, 'Successfull:')
            + '<br/>'
            + data.data.success
            + '<br/>'
            + t(appName, 'Unsuccessfull:')
            + '<br/>'
            + '<pre>'
            + data.data.error
            + '</pre>',
          t(appName, 'Result of expert operation "setupdb"'),
          undefined, true, true);
        error.html('').hide();
        msg.html(data.message).show();
      })
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown);
        msg.html('').hide();
        error.html(Ajax.failMessage(xhr, status, errorThrown)).show();
      });
    return false;
  });

  /****************************************************************************
   *
   * Tooltips
   *
   ***************************************************************************/

  CAFEVDB.toolTipsInit('#appsettings_popup');

};

export default documentReady;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
