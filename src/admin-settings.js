/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
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

import { $, appName } from './app/globals.js';
import * as Ajax from './app/ajax.js';
import generateUrl from './app/generate-url.js';
import './app/jquery-cafevdb-tooltips.js';

require('admin-settings.scss');

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

$(function() {

  const $container = $('#' + appName + '-admin-settings');
  const $msg = $container.find('.msg');

  $container.find('[title]').cafevTooltip({ placement: 'auto' });

  const $orchestraUserGroup = $container.find('input.orchestraUserGroup');

  const orchestraUserGroupAutocomplete = [];
  for (const [key, value] of Object.entries($orchestraUserGroup.data('cloudGroups'))) {
    orchestraUserGroupAutocomplete.push(key);
    orchestraUserGroupAutocomplete.push(value);
  }

  console.info('GROUP AC', orchestraUserGroupAutocomplete);

  $orchestraUserGroup.autocomplete({
    source: orchestraUserGroupAutocomplete,
    open(event, ui) {
      $.fn.cafevTooltip.remove();
    },
  });

  $container.find('input[type="text"]').blur(function(event) {
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

  $container.on('click', 'input[type="button"]', function(event) {
    const $self = $(this);

    const name = $self.attr('name');

    $msg.hide();

    $.post(
      generateUrl('/settings/admin/set/' + name), { value: name })
      .done(function(data) {
        console.log(data);
        const message = Array.isArray(data.message)
          ? data.message.join('<br/>')
          : data.message;
        $msg.html(message).show();
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
