/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import { appName } from '../config.ts';
import * as Page from './page.ts';
import * as Ajax from './ajax.ts';
import * as Dialogs from './dialogs.ts';
import * as Notification from './notification.ts';
import { addReadyCallback } from './cafevdb.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import setBusyIndicators from './busy-indicators.ts';
import { HISTORY_GO_REQUEST } from '../event-bus-events.ts';
import { emit as asyncEmit } from '../services/async-event-bus.ts';
import { translate as t } from '@nextcloud/l10n';
import {
  BASE_PATH as migrationsBasePath,
  END_POINT_APPLY as migrationsApply,
} from '../../build/ts-types/php-modules/Controller/MigrationsController.ts';
import type { ResponseData } from '../types/ajax/response-data.d.ts';
import type { ApplyMigrationsResponse } from '../../build/ts-types/php-modules/Controller/DTO.ts';

/**
 * jQuery ready-callback used elsewhere.
 */
function documentReady() {

  const $container = $('#app-content, #app-content-vue');

  $container.on('click', '#configrecheck', function() {
    console.info('Hello recheck');
    Page.loadPage({ template: 'maintenance/configcheck' }, true /* keepHistory */);
    return false;
  });

  let migrationDialogActive = false;

  const handleMigrations = async () => {
    if ($container.find('.config-check').length <= 0 || migrationDialogActive) {
      return;
    }

    migrationDialogActive = true;

    setBusyIndicators(true, $container, false);

    // check for pending migrations and handle them
    $.get(generateAppUrl(migrationsBasePath))
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, function() {
          setBusyIndicators(false, $container, false);
          migrationDialogActive = false;
        });
      })
      .done(function(data) {
        setBusyIndicators(false, $container, false);
        if (data.migrations.length <= 0) {
          migrationDialogActive = false;
          return;
        }
        let migrationList = '<dl class="migrations-list">';
        for (const [version, description] of Object.entries(data.migrations)) {
          migrationList += `<dt class="migration-version">${version}</dt><dd class="migration-description">${description}</dd>`;
        }
        migrationList += '</dl>';
        Dialogs.confirm(
          t(appName, 'Data-migrations need to be performed before proceeding to the orchestra app.')
            + '<p>'
            + migrationList
            + '<p>'
            + t(appName, 'Click the "ok" button to start the migrations.'),
          t(appName, 'Data Migration'),
          function(confirmation) {
            if (confirmation !== true) {
              migrationDialogActive = false;
              return;
            }
            setBusyIndicators(true, $container, false);
            $.post(generateAppUrl(`${migrationsBasePath}/${migrationsApply}`))
              .fail(function(xhr, status, errorThrown) {
                Ajax.handleError(xhr, status, errorThrown, function() {
                  setBusyIndicators(false, $container, false);
                  migrationDialogActive = false;
                });
              })
              .done(function(data: ResponseData<ApplyMigrationsResponse>) {
                if (!Ajax.validateResponse(data, ['payload', 'handled', 'failing'])) {
                  return;
                }
                Notification.show(
                  t(appName, 'Successfully applied the following migrations:')
                    + ' '
                    + data.handled.join(', '),
                  { timeout: 30 });
                let redirectTimeout = 10;
                const makeText = (timeout: number) => t(appName, 'Redirecting to the orchestra app in {timeout} seconds.', { timeout });
                const toast = Notification.show(makeText(redirectTimeout));
                const second = 1000;
                const notifier = setInterval(() => {
                  try {
                    toast.toastElement!.firstChild!.textContent = makeText(--redirectTimeout);
                  } catch (e) {
                    console.error('TOAST ERROR', toast, e);
                  }
                }, second);
                migrationDialogActive = false;
                setTimeout(() => {
                  clearInterval(notifier);
                  setBusyIndicators(false, $container, false);
                  Notification.hide();
                  // post a back-request to the Vue-router
                  asyncEmit(HISTORY_GO_REQUEST, { level: -1 }); // i.e. "back"
                }, redirectTimeout * 1000);
              });
          },
          true,
          true);
      });
  };
  addReadyCallback(handleMigrations);
}

export {
  documentReady,
};
