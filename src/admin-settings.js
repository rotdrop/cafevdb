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

import { initialState } from './app/config.js';
import * as Ajax from './app/ajax.js';
import generateUrl from './app/generate-url.js';

const appName = initialState.appName;

$(function() {

  const $container = $('#' + appName + '-admin-settings');
  const $msg = $container.find('.msg');

  $container.find('input').blur(function(event) {
    const $self = $(this);

    const name = $self.attr('name');
    const value = $self.val();

    $msg.hide();

    $.post(
      generateUrl('/settings/admin/set/' + name), { value })
      .done(function(data) {
        console.log(data);
        $msg.html(data.message).show();
        if (data.wikiNameSpace !== undefined) {
          $container.find('input.wikiNameSpace').val(data.wikiNameSpace);
        }
      })
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown);
        $msg.html(Ajax.failMessage(xhr, status, errorThrown)).show();
      });
  });
});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
