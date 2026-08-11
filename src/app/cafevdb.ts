/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { ReadyCallback } from './globalstate.ts';
import type { TooltipsStatistics } from './jquery-cafevdb-tooltips.ts';

import { translate as t } from '@nextcloud/l10n';
import { EnumPersonalSettingsKey } from '../../build/ts-types/php-modules/Controller.ts';
import * as BusEvents from '../event-bus-events.ts';
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts';
import { appContainerSelector, appName, globalState } from './globals.ts';
import {
  backGroundPromise as toolTipsBackgroundPromise,
} from './jquery-cafevdb-tooltips.ts';
import $, { jq } from './jquery.ts';
import {
  inputSelector as pmeInputSelector,
  tableSelector as pmeTableSelector,
  textareaInputSelector as pmeTextareaInputSelector,
  token as pmeToken,
} from './pme-selectors.ts';
import { urlDecode } from './url-decode.ts';

import { tooltipWideCssClass } from 'tooltips.scss';

require('cafevdb.scss');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

const oldInitialized = globalState.initialized && globalState.PHPMyEdit.initialized;

Object.assign(
  globalState,
  {
    appName,
    [EnumPersonalSettingsKey.TOOL_TIPS_ENABLED]: true,
    [EnumPersonalSettingsKey.WYSIWYG_EDITOR]: 'tinymce',
    language: 'en',
    readyCallbacks: [], // quasi-document-ready-callbacks
    creditsTimer: -1,
    phpUserAgent: t(appName, 'unknown'),
    subscribe: {},
    ...globalState,
    initialized: true,
  },
);

if (!oldInitialized && globalState.initialized && globalState.PHPMyEdit.initialized) {
  asyncEmit(BusEvents.GLOBAL_STATE_INITIALIZED, globalState);
}

/**
 * Register callbacks which are run after partial page reload in
 * order to "fake" document-ready. An alternate possibility would
 * have been to attach handlers to a custom signal and trigger that
 * signal if necessary.
 *
 * @param callBack TBD.
 */
const addReadyCallback = (callBack: ReadyCallback) => {
  globalState.readyCallbacks.push(callBack);
};

/**
 * Run artificial document-ready stuff.
 */
const runReadyCallbacks = async () => {
  const promises = globalState
    .readyCallbacks
    .filter((callback) => typeof callback === 'function')
    .map(async (callback) => await callback());
  return await Promise.allSettled(promises);
};

asyncSubscribe(BusEvents.LEGACY_PAGE_FINALIZE, async () => {
  const result = await runReadyCallbacks();
  return result;
});

/**
 * Steal the focus by moving it to a hidden element. Is there a
 * better way? The blur() method just does not work.
 */
const unfocus = () => {
  $('#focusstealer').trigger('focus');
};

/**
 * Create and submit a form with a POST request and given
 * parameters.
 *
 * @param url Location to post to.
 *
 * @param values Query string in GET notation.
 *
 * @param [method] Either 'get' or 'post', default is 'post'.
 */
const formSubmit = function(url: string, values: string, method: 'get'|'post' = 'post') {

  const $form = $('<form method="' + method + '" action="' + url + '"></form>');

  const splitValues = values.split('&');
  for (let i = 0; i < splitValues.length; ++i) {
    const nameValue = splitValues[i].split('=');
    $('<input />').attr('type', 'hidden')
      .attr('name', nameValue[0])
      .attr('value', urlDecode(nameValue[1]))
      .appendTo($form);
  }
  $form.appendTo($('#content'));
  $form.trigger('submit');
};

const toolTipsOnOff = function(onOff: boolean) {
  if (onOff === globalState.toolTipsEnabled) {
    return;
  }
  globalState.toolTipsEnabled = onOff;
  asyncEmit(BusEvents.TOGGLE_TOOLTIPS, {
    enabled: globalState.toolTipsEnabled,
  });
  if (globalState.toolTipsEnabled) {
    $.fn.cafevTooltip.enable();
  } else {
    $.fn.cafevTooltip.disable();
    $.fn.cafevTooltip.remove(); // remove any left-over items.
  }
};

if (!globalState.subscribe['toggle-tooltips']) {
  globalState.subscribe['toggle-tooltips'] = true;
  asyncSubscribe(BusEvents.TOGGLE_TOOLTIPS, (event) => {
    console.info('EVENT', event);
    // avoid  ping-pong
    if (event.enabled !== globalState.toolTipsEnabled) {
      toolTipsOnOff(event.enabled);
    }
  });
}

const toolTipsEnabled = () => globalState.toolTipsEnabled;

const snapperClose = () => {
  // snapper will close on clicking navigation entries
  console.info('SNAPPER CLOSE');
  $('#navigation-list li.nav-heading a').trigger('click');
};

type ToolTipSpec = {
  selector: string;
  options: TooltipOptions;
};

const toolTipSelectors: ToolTipSpec[] = [
  // by class
  {
    selector: [
      '.tooltip-auto',
      '.tooltip-left',
      '.tooltip-right',
      '.tooltip-top',
      '.tooltip-bottom',
    ].join(','),
    options: {},
  },
  // auto
  {
    selector: [
      '#app-navigation-toggle',
    ].join(','),
    options: {},
  },
  // left
  {
    selector: [
      'a.action.delete',
    ].join(','),
    options: { placement: 'left' },
  },
  // right
  {
    selector: [
      'select',
      'option',
      'button',
      '#upload',
      'input:not([type=hidden], .selectize-input-element)',
      'textarea',
    ].join(','),
    options: { placement: 'right' },
  },
  // top wide
  {
    selector: [
      pmeTextareaInputSelector,
      pmeInputSelector,
      pmeTableSelector + ' td',
    ].join(','),
    options: {
      placement: 'top',
      cssclass: tooltipWideCssClass,
    },
  },
  // top
  {
    selector: [
      'div.chosen-container',
      'label',
      '.displayName .action:not(.delete)',
      '.password .action:not(.delete)',
      '.selectedActions a',
      'a.action:not(.delete)',
      'td .modified',
      'td.lastLogin',
    ].join(','),
    options: { placement: 'top' },
  },
  // bottom wide
  {
    selector: [
      'select[class*="pme-filter"]',
      'input[class*="pme-filter"]',
      'td.' + pmeToken('sys') + ' ~ td.' + pmeToken('data') + ' .info',
    ].join(','),
    options: {
      placement: 'bottom',
      cssclass: tooltipWideCssClass,
    },
  },
  // bottom
  {
    selector: [
      'button.settings',
      '.' + pmeToken('sort'),
      ['', pmeToken('check'), pmeToken('misc')].join('.'),
      '.header-right img',
      'img',
      'li.' + pmeToken('navigation') + '.table-tabs',
      'table.' + pmeToken('main') + ' th',
    ].join(','),
    options: { placement: 'bottom' },
  },
];

/**
 * Initialize all tooltips on the given element and its children.
 *
 * @param containerSel TBD.
 */
const toolTipsInit = async (containerSel?: string|JQuery) => {

  // rejectToolTipsBackgroundPromise();

  if (typeof containerSel === 'undefined') {
    containerSel = appContainerSelector;
  }
  const $container = jq(containerSel);

  console.debug('tooltips container', containerSel, $container.length);

  const timestamp = Date.now();

  for (const toolTipSpec of toolTipSelectors) {
    const options = toolTipSpec.options;
    options.timestamp = timestamp;
    $container.find(toolTipSpec.selector).cafevTooltip(options);
  }

  toolTipsBackgroundPromise.then(
    (statistics) => {
      console.info('TOOLTIP STATISTICS', statistics);
      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }
    },
    (statistics: TooltipsStatistics) => {
      console.info('TOOLTIP BACKGROUND PROMISE REJECTED, TOOLTIP STATISTICS', statistics);
      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }
    },
  );
};

export {
  addReadyCallback,
  appName,
  formSubmit,
  globalState,
  runReadyCallbacks,
  snapperClose,
  toolTipsEnabled,
  toolTipsInit,
  toolTipsOnOff,
  unfocus,
};
