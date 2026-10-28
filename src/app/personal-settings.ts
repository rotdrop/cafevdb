/**
 * @file
 *
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *
 * @module
 */

import globalState from './globalstate.ts';
import $ from './jquery.ts';

// The setters subscribe to the event-bus on import, they have to be
// available even if the personal settings form is never opened.
import './settings/debug-modes.ts';
import './settings/debug-query-sql-filter.ts';
import './settings/deselect-invisible.ts';
import './settings/direct-change.ts';
import './settings/expert-mode.ts';
import './settings/finance-mode.ts';
import './settings/initial-filter-visibility.ts';
import './settings/pagerows.ts';
import './settings/restore-history.ts';
import './settings/show-disabled.ts';
import './settings/tooltips.ts';
import { appNameTag, hiddenCssClass } from 'variables.module.scss';

require('nav-area-settings.scss');

/****************************************************************************
 * Credits list
 *
 ***************************************************************************/

const updateCredits = function() {
  const numItems = 5;
  const items: number[] = [];
  const numTotal = $(`div.${appNameTag}.about div.product.credits.list ul li`).length;
  for (let i = 0; i < numItems; ++i) {
    items.push(Math.round(Math.random() * (numTotal - 1)));
  }
  $(`div.${appNameTag}.about div.product.credits.list ul li`).each(function(index) {
    if (items.includes(index)) {
      $(this).removeClass(hiddenCssClass);
    } else {
      $(this).addClass(hiddenCssClass);
    }
  });
};

export const updateCreditsTimer = function() {

  if (globalState.creditsTimer) {
    clearInterval(globalState.creditsTimer);
  }

  globalState.creditsTimer = setInterval(function() {
    if ($(`div.${appNameTag}.about div.product.credits.list:visible`).length > 0) {
      console.log('Updating credits.');
      updateCredits();
    } else {
      console.debug('Clearing credits timer.');
      clearInterval(globalState.creditsTimer);
    }
  }, 60000);

};
