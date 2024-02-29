/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, appNameTag, $, jQuery } from './globals.js';
import { urlDecode } from './url-decode.js';
import {
  token as pmeToken,
  textareaInputSelector as pmeTextareaInputSelector,
  inputSelector as pmeInputSelector,
  tableSelector as pmeTableSelector,
} from './pme-selectors.js';
import {
  backGroundPromise as toolTipsBackgroundPromise,
  rejectBackgroundPromise as rejectToolTipsBackgroundPromise,
  getMarkCount,
  setMarkCount,
} from './jquery-cafevdb-tooltips.js';

require('cafevdb.scss');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

$.extend(
  globalState,
  $.extend({
    appName,
    toolTipsEnabled: true,
    wysiwygEditor: 'tinymce',
    language: 'en',
    readyCallbacks: [], // quasi-document-ready-callbacks
    creditsTimer: -1,
    adminContact: t(appName, 'unknown'),
    phpUserAgent: t(appName, 'unknown'),
  }, globalState)
);

/**
 * Register callbacks which are run after partial page reload in
 * order to "fake" document-ready. An alternate possibility would
 * have been to attach handlers to a custom signal and trigger that
 * signal if necessary.
 *
 * @param {Function} callBack TBD.
 */
const addReadyCallback = function(callBack) {
  globalState.readyCallbacks.push(callBack);
};

/**
 * Run artificial document-ready stuff.
 *
 * @returns {boolean} TBD.
 */
const runReadyCallbacks = function() {
  for (let idx = 0; idx < globalState.readyCallbacks.length; ++idx) {
    const callback = globalState.readyCallbacks[idx];
    if (typeof callback === 'function') {
      callback();
    }
  }
  return false;
};

/**
 * Steal the focus by moving it to a hidden element. Is there a
 * better way? The blur() method just does not work.
 *
 * @param {jQuery} element TBD.
 */
const unfocus = function(element) {
  $('#focusstealer').focus();
};

/**
 * Display a transparent modal dialog which blocks the UI.
 *
 * @param {string} message TBD.
 *
 * @returns {jQuery}
 */
const modalWaitNotification = function(message) {
  const dialogHolder = $('<div class="' + appNameTag + ' modal-wait-notification"></div>');
  dialogHolder.html('<div class="' + appNameTag + ' modal-wait-message">' + message + '</div>'
                    + '<div class="' + appNameTag + ' modal-wait-animation"></div>');
  $('body').append(dialogHolder);
  dialogHolder.find('div.modal-wait-animation').progressbar({ value: false });
  dialogHolder.cafevDialog({
    title: '',
    position: {
      my: 'center',
      at: 'center center-20%',
      of: window,
    },
    width: '80%',
    height: 'auto',
    modal: true,
    closeOnEscape: false,
    dialogClass: 'transparent no-close wait-notification ' + appNameTag,
    resizable: false,
    open() {
    },
    close() {
      dialogHolder.dialog('close');
      dialogHolder.dialog('destroy').remove();
    },
  });
  return dialogHolder;
};

/**
 * Create and submit a form with a POST request and given
 * parameters.
 *
 * @param {string} url Location to post to.
 *
 * @param {string} values Query string in GET notation.
 *
 * @param {string} method Either 'get' or 'post', default is 'post'.
 */
const formSubmit = function(url, values, method) {

  if (typeof method === 'undefined') {
    method = 'post';
  }

  const form = $('<form method="' + method + '" action="' + url + '"></form>');

  const splitValues = values.split('&');
  for (let i = 0; i < splitValues.length; ++i) {
    const nameValue = splitValues[i].split('=');
    $('<input />').attr('type', 'hidden')
      .attr('name', nameValue[0])
      .attr('value', urlDecode(nameValue[1]))
      .appendTo(form);
  }
  form.appendTo($('#content'));
  form.submit();
};

const attachToolTip = function(selector, options) {
  const defaultOptions = {
    container: 'body',
    html: true,
    placement: 'auto',
  };
  options = $.extend({}, defaultOptions, options);
  return $(selector).cafevTooltip(options);
};

/**
 * Exchange "tipsy" tooltips already attached to an element by
 * something different. This has to be done the "hard" way: first
 * unset data('tipsy') by setting it to null, then call the
 * tipsy-constructor with the new values.
 *
 * @param {string} selector jQuery element selector
 *
 * @param {object} options Tool-tip options
 *
 * @param {jQuery} container Optional container containing selected
 * elements, i.e. tool-tip stuff will be applied to all elements
 * inside @a container matching @a selector.
 */
const applyToolTips = function(selector, options, container) {
  let element;
  if (selector instanceof jQuery) {
    element = selector;
  } else if (typeof container !== 'undefined') {
    element = container.find(selector);
  } else {
    element = $(selector);
  }
  // remove any pending tooltip from the document
  $.fn.cafevTooltip.remove();

  // fetch suitable options from the elements class attribute
  const classOptions = {
    placement: 'auto',
    html: true,
  };
  const classAttr = element.attr('class');
  let extraClass = false;
  if (options.cssclass) {
    extraClass = options.cssclass;
  }
  if (typeof classAttr !== 'undefined') {
    if (classAttr.match(/tooltip-off/) !== null) {
      $(this).cafevTooltip('disable');
      return;
    }
    const tooltipClasses = classAttr.match(/tooltip-[a-z-]+/g);
    if (tooltipClasses) {
      for (let idx = 0; idx < tooltipClasses.length; ++idx) {
        const tooltipClass = tooltipClasses[idx];
        const placement = tooltipClass.match(/^tooltip-(bottom|top|right|left)$/);
        if (placement && placement.length === 2 && placement[1].length > 0) {
          classOptions.placement = placement[1];
          continue;
        }
        extraClass = tooltipClass;
      }
    }
  }
  if (typeof options === 'undefined') {
    options = classOptions;
  } else {
    // supplied options override class options
    options = $.extend({}, classOptions, options);
  }

  if (extraClass) {
    options.template = '<div class="tooltip '
      + extraClass
      + '" role="tooltip">'
      + '<div class="tooltip-arrow"></div>'
      + '<div class="tooltip-inner"></div>'
      + '</div>';
  }
  element.cafevTooltip('destroy'); // remove any already installed stuff
  element.cafevTooltip(options); // make it new
};

const toolTipsOnOff = function(onOff) {
  globalState.toolTipsEnabled = !!onOff;
  if (globalState.toolTipsEnabled) {
    $.fn.cafevTooltip.enable();
  } else {
    $.fn.cafevTooltip.disable();
    $.fn.cafevTooltip.remove(); // remove any left-over items.
  }
};

/**
 * @returns {boolean}
 */
function toolTipsEnabled() {
  return globalState.toolTipsEnabled;
}

const snapperClose = function() {
  // snapper will close on clicking navigation entries
  console.info('SNAPPER CLOSE');
  $('#navigation-list li.nav-heading a').trigger('click');
};

const toolTipSelectors = [
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
 * @param {string|jQuery} containerSel TBD.
 *
 * @todo This function performs too much work and is too unstructured.
 */
const toolTipsInit = function(containerSel) {

  console.time('TOOLTIPS');

  rejectToolTipsBackgroundPromise();

  console.time('TOOLTIP PROMISE');

  if (typeof containerSel === 'undefined') {
    containerSel = '#content.app-' + appName;
  }
  const container = $(containerSel);

  console.debug('tooltips container', containerSel, container.length);

  setMarkCount(0);
  const timestamp = Date.now();

  for (const toolTipSpec of toolTipSelectors) {
    const options = toolTipSpec.options;
    options.timestamp = timestamp;
    container.find(toolTipSpec.selector).cafevTooltip(options);
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

  if (globalState.toolTipsEnabled) {
    $.fn.cafevTooltip.enable();
  } else {
    $.fn.cafevTooltip.disable();
  }

  toolTipsBackgroundPromise
    .done((maxJobs) => {
      console.timeEnd('TOOLTIP PROMISE');
      console.info('TOOLTIP JOBS HANDLED', maxJobs);
    })
    .fail((maxJobs) => {
      console.timeEnd('TOOLTIP PROMISE');
      console.info('RECOMPUTE TOOLTIPS, TOOLTIPS HANDLED SO FAR', maxJobs);
    });

  console.timeEnd('TOOLTIPS');

  console.info('SKIPPED MARKED', getMarkCount());
};

export {
  appName,
  globalState,
  addReadyCallback,
  runReadyCallbacks,
  unfocus,
  modalWaitNotification,
  formSubmit,
  snapperClose,
  attachToolTip,
  applyToolTips,
  toolTipsOnOff,
  toolTipsInit,
  toolTipsEnabled,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
