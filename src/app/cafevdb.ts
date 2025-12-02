/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, appContainerSelector } from './globals.ts';
import $, { jq } from './jquery.ts';
import type { ReadyCallback } from './globalstate.ts';
import { urlDecode } from './url-decode.ts';
import { translate as t } from '@nextcloud/l10n';
import {
  token as pmeToken,
  textareaInputSelector as pmeTextareaInputSelector,
  inputSelector as pmeInputSelector,
  tableSelector as pmeTableSelector,
} from './pme-selectors.ts';
import {
  backGroundPromise as toolTipsBackgroundPromise,
  rejectBackgroundPromise as rejectToolTipsBackgroundPromise,
} from './jquery-cafevdb-tooltips.ts';
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts';
import * as BusEvents from '../event-bus-events.ts';
import { EnumPersonalSettingsKey } from '../../build/ts-types/php-modules/Controller.ts';

require('cafevdb.scss');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

Object.assign(
  globalState,
  Object.assign(
    {
      appName,
      [EnumPersonalSettingsKey.TOOL_TIPS_ENABLED]: true,
      [EnumPersonalSettingsKey.WYSIWYG_EDITOR]: 'tinymce',
      language: 'en',
      readyCallbacks: [], // quasi-document-ready-callbacks
      creditsTimer: -1,
      phpUserAgent: t(appName, 'unknown'),
      subscribe: {},
    },
    globalState,
    { initialized: true },
  ),
);

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
  options: TooltipOptions,
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
      cssclass: 'tooltip-wide',
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
      cssclass: 'tooltip-wide',
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
 * Initialize our tipsy stuff. Only exchange for our own thingies, of course.
 *
 * @param containerSel TBD.
 *
 * @todo This function performs too much work and is too unstructured.
 */
const toolTipsInit = (containerSel?: string|JQuery) => {

  console.time('TOOLTIPS');

  rejectToolTipsBackgroundPromise();

  console.time('TOOLTIP PROMISE');

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

  // for (const toolTipSpec of toolTipSelectors) {
  //   toolTipSpec.options.timestamp = timestamp;
  // }

  // container.find('*').each(function() {
  //   const $this = $(this);
  //   for (const toolTipSpec of toolTipSelectors) {
  //     if ($this.is(toolTipSpec.selector)) {
  //       $this.cafevTooltip(toolTipSpec.options);
  //     }
  //   }
  // });

  toolTipsBackgroundPromise
    .done((maxJobs) => {
      console.timeEnd('TOOLTIP PROMISE');
      console.info('TOOLTIP JOBS HANDLED', maxJobs);
      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }
    })
    .fail((maxJobs) => {
      console.timeEnd('TOOLTIP PROMISE');
      console.info('FAIL RECOMPUTE TOOLTIPS, TOOLTIPS HANDLED SO FAR', maxJobs);
      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }
    });

  console.timeEnd('TOOLTIPS');
};

export {
  appName,
  globalState,
  addReadyCallback,
  runReadyCallbacks,
  unfocus,
  formSubmit,
  snapperClose,
  toolTipsOnOff,
  toolTipsInit,
  toolTipsEnabled,
};
