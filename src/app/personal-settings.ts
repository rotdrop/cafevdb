/**
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
 */

import globalState from './globalstate.ts';
import $ from './jquery.ts';
import { appName } from '../config.ts';
import { setPersonalUrl } from './settings-urls.ts';
import * as CAFEVDB from './cafevdb.ts';
import * as Ajax from './ajax.ts';
import * as Notification from './notification.ts';
import { chosenActive, selected as selectedValues } from './select-utils.ts';
import { handleMenu as handleUserManualMenu } from './user-manual.ts';
import setDebugModes from './settings/debug-modes.ts';
import './settings/debug-query-sql-filter.ts';
import setDeselectInvisible from './settings/deselect-invisible.ts';
import setDirectChange from './settings/direct-change.ts';
import setExpertMode from './settings/expert-mode.ts';
import setFinanceMode from './settings/finance-mode.ts';
import setInitialFilterVisibility from './settings/initial-filter-visibility.ts';
import setPageRows from './settings/pagerows.ts';
import setRestoreHistory from './settings/restore-history.ts';
import setShowDisabled from './settings/show-disabled.ts';
import setTooltipsMode from './settings/tooltips.ts';
import { hiddenCssClass, appNameTag } from 'variables.scss';

require('nav-area-settings.scss');

type JQSelect = JQuery<HTMLSelectElement>;

const documentReady = function() {

  const container = $('.personal-settings');
  let msgElement = $('form.personal-settings .statusmessage');

  const showMessage = function(message: undefined|string|string[]) {
    if (message === undefined) {
      return [];
    }
    if (!Array.isArray(message)) {
      message = [message];
    }
    msgElement.html(message.join(' ')).show();
    return Notification.messages(message);
  };

  const chosenInit = function($container: JQuery) {
    ($container.find('select.pagerows') as JQSelect).each(function() {
      const self = $(this);
      // console.log("chosen pagerows", self);
      if (chosenActive(self)) {
        self.chosen('destroy');
      }
      self.chosen({
        disable_search: true,
        inherit_select_classes: true,
        title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
        width: '10ex',
      });
    });

    ($container.find('select.wysiwyg-editor') as JQSelect).each(function() {
      const self = $(this);
      if (chosenActive(self)) {
        console.debug('destroy chosen', self);
        self.chosen('destroy');
      }
      console.info('call chosen', self);
      self.chosen('destroy');
      self.show();
      self.chosen({
        inherit_select_classes: true,
        title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
        disable_search: true,
        width: 'auto',
      });
    });

    ($container.find('select.debugmode') as JQSelect).each(function() {
      const self = $(this);
      // console.log("chosen debugmode", self);
      if (chosenActive(self)) {
        self.chosen('destroy');
      }
      self.chosen({
        inherit_select_classes: true,
        title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
        disable_search: true,
        width: '100%',
      });
    });

    $container.find('.chosen-container').cafevTooltip();
  };

  container.on(appName + ':content-update', function(event) {
    if (event.target === this) {
      chosenInit($(this));
      msgElement = $('form.personal-settings .statusmessage');
    }
  });

  let firstReadyCallbackInvocation = true;
  CAFEVDB.addReadyCallback(async () => {
    console.info('PERSONAL READY CALLBACK');
    if (firstReadyCallbackInvocation) {
      firstReadyCallbackInvocation = false;
      return;
    }
    chosenInit(container);
  });

  chosenInit(container);

  // help-menu entries
  handleUserManualMenu(container);

  // tool-tips toggle
  container.on('change', '.tooltips', function(_event) {
    const $this = $(this);
    const checked = $this.prop('checked');
    setTooltipsMode(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.restorehistory', function() {
    const $this = $(this);
    const checked = $this.prop('checked');
    setRestoreHistory(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.filtervisibility', function() {
    const $this = $(this);
    const checked = $this.prop('checked');
    setInitialFilterVisibility(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.directchange', function(_event) {
    const $this = $(this);
    const checked = $this.prop('checked');
    setDirectChange(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.deselect-invisible-misc-recs', function() {
    const $this = $(this);
    const checked = $this.prop('checked');
    setDeselectInvisible(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.showdisabled', function() {
    const $this = $(this);
    const checked = $this.prop('checked');
    setShowDisabled(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.expert-mode', function() {
    const $this = $(this);
    const checked = $this.prop('checked');
    setExpertMode(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.finance-mode', function() {
    const $this = $(this);
    const checked = $this.prop('checked');
    setFinanceMode(checked, showMessage, $this);
    return false;
  });

  container.on('change', 'select.pagerows', function(this: HTMLSelectElement, _event) {
    const $this = $(this);
    const value = parseInt($this.val() as string);
    setPageRows(value, showMessage, $this);
    return false;
  });

  container.on('change', '.debugmode', function() {
    const $this = $(this);
    const post = $this.serializeArray();
    setDebugModes(post, showMessage, $this);
    return false;
  });

  container.on('change', 'select.wysiwyg-editor', function() {
    const $this = $(this);
    const value = $this.val() as string;
    $.post(setPersonalUrl('wysiwygEditor'), { value })
      .done(function(data) {
        showMessage(data.message);
        globalState.wysiwygEditor = value;
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        // console.error(data);
      });
    ($('.personal-settings select.wysiwyg-editor') as JQuery<HTMLSelectElement>).each(function() {
      if (this !== $this[0]) {
        selectedValues($(this), selectedValues($this) as string);
      }
    });
    return false;
  });

  /****************************************************************************
   *
   * Tooltips
   *
   ***************************************************************************/

  CAFEVDB.toolTipsInit('#personal-settings-container');
  console.info('PERSONAL INIT');
};

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

export default documentReady;
