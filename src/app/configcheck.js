/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import { appName } from './app-info.js';
import * as Page from './page.js';
import * as ProgressStatus from './progress-status.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as Notification from './notification.js';
import { addReadyCallback } from './cafevdb.js';
import generateUrl from './generate-url.js';
import setBusyIndicators from './busy-indicators.js';

/**
 * jQuery ready-callback used elsewhere.
 */
function documentReady() {

  const $container = $('#app-content');

  $container.on('click', '#configrecheck', function(event) {
    console.info('Hello recheck');
    Page.loadPage({ template: 'maintenance/configcheck' });
    return false;
  });

  const handleMigrations = function() {
    if ($container.find('.config-check').length <= 0) {
      return;
    }

    setBusyIndicators(true, $container, false);

    // check for pending migrations and handle them
    $.get(generateUrl('maintenance/migrations/unapplied'))
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, function() {
          setBusyIndicators(false, $container, false);
        });
      })
      .done(function(data) {
        setBusyIndicators(false, $container, false);
        if (data.migrations.length <= 0) {
          return;
        }
        let migrationList = '<dl class="migrations-list">';
        for (const [version, description] of Object.entries(data.migrations)) {
          migrationList += `<dt class="migration-version">${version}</dt><dd class="migration-description">${description}</dd>`;
        }
        migrationList += '</dl>';
        Dialogs.info(
          t(appName, 'Data-migrations need to be performed before proceeding to the orchestra app.')
            + '<p>'
            + migrationList
            + '<p>'
            + t(appName, 'Click the "ok" button to start the migrations.'),
          t(appName, 'Data Migration'),
          function(confirmation) {
            if (confirmation !== true) {
              return;
            }
            setBusyIndicators(true, $container, false);
            $.post(generateUrl('maintenance/migrations/apply/all'))
              .fail(function(xhr, status, errorThrown) {
                Ajax.handleError(xhr, status, errorThrown, function() {
                  setBusyIndicators(false, $container, false);
                });
              })
              .done(function(data) {
                Notification.show(
                  t(appName, 'Successfully applied the following migrations:')
                    + ' '
                    + data.migrations.handled.join(', '),
                  { timeout: 30 });
                let redirectTimeout = 10;
                const makeText = timeout => t(appName, 'Redirecting to the orchestra app in {timeout} seconds.', { timeout: redirectTimeout });
                const row = Notification.show(makeText(redirectTimeout));
                const second = 1000;
                const notifier = setInterval(() => {
                  row.text(makeText(--redirectTimeout));
                }, second);
                setTimeout(() => {
                  clearInterval(notifier);
                  setBusyIndicators(false, $container, false);
                  window.location.reload();
                }, redirectTimeout * 1000);
              });
          },
          true,
          true);
      });
  };
  addReadyCallback(handleMigrations);

  $container.on('click', '.progress-status.button', function(event) {
    console.info('Hello World');
    if (ProgressStatus.poll.active()) {
      ProgressStatus.poll.stop();
      return false;
    }
    $.post(generateUrl('foregroundjob/progress/create'), { target: 100, current: 0 })
      .fail(Ajax.handleError)
      .done(function(data) {
        console.log('Progress Create', data);
        const id = data.id;
        ProgressStatus.poll(
          id,
          {
            update(id, current, target, data) {
              $('#progress-status-info').html(current + ' of ' + target);
              console.info(current, target);
              return parseInt(current) < parseInt(target);
            },
            fail(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
            },
          });
        $.post(generateUrl('foregroundjob/progress/test'), { id, target: 100, data: { foo: 'bar' } })
          .fail(Ajax.handleError)
          .done(function(data) {});
      });
    return false;
  });

}

export {
  documentReady,
};
