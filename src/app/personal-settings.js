/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import globalState from './globalstate.js';
import $ from './jquery.js';
import { appName, appNameTag } from './app-info.js';
import { setPersonalUrl } from './settings-urls.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';
import { chosenActive, selected as selectedValues } from './select-utils.js';
import { handleMenu as handleUserManualMenu } from './user-manual.js';
import setFinanceMode from './finance-mode.js';
import setExpertMode from './expert-mode.js';
import setShowDisabled from './show-disabled.js';
import setDebugModes from './debug-modes.js';
import setTooltipsMode from './tooltips.js';
import setPageRows from './pagerows.js';

require('nav-area-settings.scss');

const documentReady = function() {

  const container = $('.personal-settings');
  let msgElement = $('form.personal-settings .statusmessage');

  const showMessage = function(message) {
    msgElement.html(message).show();
    Notification.messages(message);
  };

  const chosenInit = function(container) {
    container.find('select.pagerows').each(function(index) {
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

    container.find('select.wysiwyg-editor').each(function(index) {
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

    container.find('select.debugmode').each(function(index) {
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

    container.find('.chosen-container').cafevTooltip();
  };

  container.on(appName + ':content-update', function(event) {
    if (event.target === this) {
      chosenInit($(this));
      msgElement = $('form.personal-settings .statusmessage');
    }
  });

  let firstReadyCallbackInvocation = true;
  CAFEVDB.addReadyCallback(function() {
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
  container.on('change', '.tooltips', function(event) {
    const $this = $(this);
    const checked = $this.prop('checked');
    setTooltipsMode(checked, showMessage, $this);
    return false;
  });

  container.on('change', '.restorehistory', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('restorehistory'), { value: checked })
      .done(function(data) {
        showMessage(data.message);
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        // console.error(data);
      });
    $('.personal-settings input[type="checkbox"].restorehistory').prop('checked', checked);
    return false;
  });

  container.on('change', '.filtervisibility', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('filtervisibility'), { value: checked })
      .done(function(data) {
        showMessage(data.message);
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        // console.error(data);
      });
    if (checked) {
      $('input.pme-search').trigger('click');
    } else {
      $('input.pme-hide').trigger('click');
    }
    $('.personal-settings input[type="checkbox"].filtervisibility').prop('checked', checked);
    return false;
  });

  container.on('change', '.directchange', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('directchange'), { value: checked })
      .done(function(data) {
        showMessage(data.message);
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        // console.error(data);
      });
    if (globalState.PHPMyEdit !== undefined) {
      globalState.PHPMyEdit.directChange = checked;
    }
    $('.personal-settings input[type="checkbox"].directchange').prop('checked', checked);
    return false;
  });

  container.on('change', '.deselect-invisible-misc-recs', function(event) {
    const self = $(this);
    const checked = self.prop('checked');
    $.post(setPersonalUrl('deselectInvisibleMiscRecs'), { value: checked })
      .done(function(data) {
        showMessage(data.message);
        console.log(data);
      })
      .fail(function(xhr, status, errorThrown) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        // console.error(data);
      });
    if (globalState.PHPMyEdit !== undefined) {
      globalState.PHPMyEdit.deselectInvisibleMiscRecs = checked;
    }
    $('.personal-settings input[type="checkbox"].deselect-invisible-misc-recs').prop('checked', checked);
    return false;
  });

  container.on('change', '.showdisabled', function(event) {
    const checked = $(this).prop('checked');
    setShowDisabled(checked);
    return false;
  });

  container.on('change', '.expert-mode', function(event) {
    const checked = $(this).prop('checked');
    setExpertMode(checked, showMessage);
    return false;
  });

  container.on('change', '.finance-mode', function(event) {
    const checked = $(this).prop('checked');
    setFinanceMode(checked, showMessage);
    return false;
  });

  container.on('change', '.pagerows', function(event) {
    const $this = $(this);
    const value = $this.val();
    setPageRows(value, showMessage, $this);
    return false;
  });

  container.on('change', '.debugmode', function(event) {
    const $this = $(this);
    const post = $this.serializeArray();
    setDebugModes(post, showMessage, $this);
    return false;
  });

  container.on('change', '.wysiwyg-editor', function(event) {
    const $this = $(this);
    const value = $this.val();
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
    $('.personal-settings select.wysiwyg-editor').each(function(index) {
      if (this !== $this[0]) {
        selectedValues($(this), selectedValues($this));
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

export default documentReady;

/****************************************************************************
 * Credits list
 *
 ***************************************************************************/

const updateCredits = function() {
  const numItems = 5;
  const items = [];
  const numTotal = $(`div.${appNameTag}.about div.product.credits.list ul li`).length;
  for (let i = 0; i < numItems; ++i) {
    items.push(Math.round(Math.random() * (numTotal - 1)));
  }
  $(`div.${appNameTag}.about div.product.credits.list ul li`).each(function(index) {
    if (items.includes(index)) {
      $(this).removeClass('hidden');
    } else {
      $(this).addClass('hidden');
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

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
