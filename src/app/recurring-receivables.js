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

import { $ } from './globals.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';
import generateUrl from './generate-url.js';

const participantOptionHandlers = function(container, musicianId) {

  if (!musicianId) {
    return;
  }

  container = $(container);

  // Handle buttons to update or delete recurrent receivables
  container
    .find('form.pme-form tr.participant-field.recurring td.operations input.regenerate')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      const fieldId = row.data('fieldId');
      const optionKey = row.data('optionKey');
      const cleanup = function() {};
      const request = 'option/regenerate';
      $.post(
        generateUrl('projects/participant-fields/' + request), {
          data: {
            fieldId,
            key: optionKey,
            musicianId,
          },
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['amounts'], cleanup)) {
            return;
          }
          if (data.amounts[musicianId]) {
            row.find('input.pme-input.service-fee').val(data.amounts[musicianId]);
          }
          cleanup();
          Notification.messages(data.message);
        });
      return false;
    });

  container
    .find('form.pme-form tr.participant-field.recurring td.operations input.delete-undelete')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      // const fieldId = row.data('fieldId');
      const optionKey = row.data('optionKey');

      // could also search for name with field-id
      const inputs = container
        .find('input[value="' + optionKey + '"]')
        .add(row.find('.pme-input, .operation.regenerate'));

      if (row.hasClass('deleted')) {
        inputs.prop('disabled', false);
        row.removeClass('deleted');
      } else {
        inputs.prop('disabled', true);
        row.addClass('deleted');
      }

      return false;
    });

  container
    .find('form.pme-form tr.participant-field.recurring td.operations input.regenerate-all')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      const fieldId = row.data('fieldId');
      const cleanup = function() {};
      const request = 'option/regenerate';
      $.post(
        generateUrl('projects/participant-fields/' + request), {
          data: {
            fieldId,
            musicianId,
          },
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, [], cleanup)) {
            return;
          }
          // just trigger reload
          container.find('form.pme-form input.pme-reload').first().trigger('click');
          cleanup();
          Notification.messages(data.message);
        });
      return false;
    });
};

export {
  participantOptionHandlers,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
