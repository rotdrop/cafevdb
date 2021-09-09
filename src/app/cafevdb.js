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

import { globalState, appName, $, jQuery } from './globals.js';
import generateUrl from './generate-url.js';
import { urlDecode } from './url-decode.js';
import { chosenActive } from './select-utils.js';
import { token as pmeToken } from './pme-selectors.js';

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
 * @returns {bool} TBD.
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
 * @param {String} message TBD.
 *
 * @returns {jQuery}
 */
const modalWaitNotification = function(message) {
  const dialogHolder = $('<div class="cafevdb modal-wait-notification"></div>');
  dialogHolder.html('<div class="cafevdb modal-wait-message">' + message + '</div>'
                    + '<div class="cafevdb modal-wait-animation"></div>');
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
    dialogClass: 'transparent no-close wait-notification cafevdb',
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

const stopRKey = function(evt) {
  evt = evt || event;
  const node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
  if ((evt.keyCode === 13) && (node.type === 'text')) {
    return false;
  }
  return true;
};

const fixupNoChosenMenu = function(select) {
  if (!chosenActive(select)) {
    // restore the data-placeholder as first option if chosen
    // is not active
    select.each(function(index) {
      const self = $(this);
      const placeHolder = self.data('placeholder');
      self.find('option:first').html(placeHolder);
    });
  }
};

/**
 * Create and submit a form with a POST request and given
 * parameters.
 *
 * @param {String} url Location to post to.
 *
 * @param {String} values Query string in GET notation.
 *
 * @param {String} method Either 'get' or 'post', default is 'post'.
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
  form.appendTo($('div#content')); // needed?
  form.submit();
};

/**
 * A variant of the old fashioned appsettings with a callback
 * instead of script loading
 *
 * @param {String} route TBD.
 *
 * @param {Function} callback TBD.
 */
const appSettings = function(route, callback) {
  const popup = $('#appsettings_popup');
  if (popup.is(':visible')) {
    popup.addClass('hidden').html('');
    // popup.hide().html('');
  } else {
    // const arrowclass = popup.hasClass('topright') ? 'up' : 'left';
    $.get(generateUrl(route))
      .done(function(data) {
        popup
          .html(data)
          .ready(function() {
            // assume the first element is a container div
            if (popup.find('.popup-title').length > 0) {
              popup.find('.popup-title').append('<a class="close"></a>');
              // popup.find(">:first-child").prepend('<a class="close"></a>').show();
            } else {
              popup.find('>:first-child').prepend('<div class="popup-title"><h2>' + t('core', 'Settings') + '</h2><a class="close"></a></div>');
            }
            popup.find('.close').bind('click', function() {
              popup.addClass('hidden').html('');
            });
            callback(popup);
            popup.find('>:first-child').removeClass('hidden');
            popup.removeClass('hidden');
          });
      })
      .fail(function(data) {
        console.log(data);
      });
  }
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
 * @param {String} selector jQuery element selector
 *
 * @param {Object} options Tool-tip options
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

function toolTipsEnabled() {
  return globalState.toolTipsEnabled;
}

const snapperClose = function() {
  // snapper will close on clicking navigation entries
  console.info('SNAPPER CLOSE');
  $('#navigation-list li.nav-heading a').trigger('click');
};

/**
 * Initialize our tipsy stuff. Only exchange for our own thingies, of course.
 *
 * @param {String|jQuery} containerSel TBD.
 */
const toolTipsInit = function(containerSel) {
  if (typeof containerSel === 'undefined') {
    containerSel = '#content.app-cafevdb';
  }
  const container = $(containerSel);

  console.debug('tooltips container', containerSel, container.length);

  // container.find('button.settings').cafevTooltip({ placement: 'bottom' });
  container.find('select').cafevTooltip({ placement: 'right' });
  container.find('option').cafevTooltip({ placement: 'right' });
  container.find('div.chosen-container').cafevTooltip({ placement: 'top' });
  container.find('button.settings').cafevTooltip({ placement: 'bottom' });
  container.find('.' + pmeToken('sort')).cafevTooltip({ placement: 'bottom' });
  container.find(['', pmeToken('check'), pmeToken('misc')].join('.')).cafevTooltip({ placement: 'bottom' });
  container.find('label').cafevTooltip({ placement: 'top' });
  container.find('.header-right img').cafevTooltip({ placement: 'bottom' });
  container.find('img').cafevTooltip({ placement: 'bottom' });
  container.find('button').cafevTooltip({ placement: 'right' });
  container.find('li.' + pmeToken('navigation') + '.table-tabs').cafevTooltip({ placement: 'bottom' });

  // pme input stuff and tables.
  container.find('textarea.' + pmeToken('input')).cafevTooltip(
    { placement: 'top', cssclass: 'tooltip-wide' });
  container.find('input.' + pmeToken('input')).cafevTooltip(
    { placement: 'top', cssclass: 'tooltip-wide' });
  container.find('table.' + pmeToken('main') + ' td').cafevTooltip(
    { placement: 'top', cssclass: 'tooltip-wide' });
  container.find('table.' + pmeToken('main') + ' th').cafevTooltip(
    { placement: 'bottom' });

  // original tipsy stuff
  container.find('.displayName .action').cafevTooltip({ placement: 'top' });
  container.find('.password .action').cafevTooltip({ placement: 'top' });
  container.find('#upload').cafevTooltip({ placement: 'right' });
  container.find('.selectedActions a').cafevTooltip({ placement: 'top' });
  container.find('a.action.delete').cafevTooltip({ placement: 'left' });
  container.find('a.action').cafevTooltip({ placement: 'top' });
  container.find('td .modified').cafevTooltip({ placement: 'top' });
  container.find('td.lastLogin').cafevTooltip({ placement: 'top', html: true });
  container.find('input:not([type=hidden])').cafevTooltip({ placement: 'right' });
  container.find('textarea').cafevTooltip({ placement: 'right' });

  // everything else.
  container.find('.tip').cafevTooltip({ placement: 'right' });

  container.find('select[class*="pme-filter"]').cafevTooltip(
    { placement: 'bottom', cssclass: 'tooltip-wide' }
  );
  container.find('input[class*="pme-filter"]').cafevTooltip(
    { placement: 'bottom', cssclass: 'tooltip-wide' }
  );
  container.find('td.' + pmeToken('sys') + ' ~ td.' + pmeToken('data') + ' .info').cafevTooltip(
    { placement: 'bottom', cssclass: 'tooltip-wide' }
  );

  container.find('[class*="tooltip-"]').each(function(index) {
    // console.log("tooltip autoclass", $(this), $(this).attr('title'));
    $(this).cafevTooltip({});
  });

  container.find('#app-navigation-toggle').cafevTooltip();

  // Tipsy greedily enables itself when attaching it to elements, so
  // ...
  if (globalState.toolTipsEnabled) {
    $.fn.cafevTooltip.enable();
  } else {
    $.fn.cafevTooltip.disable();
  }
};

export {
  appName,
  globalState,
  generateUrl,
  addReadyCallback,
  runReadyCallbacks,
  unfocus,
  modalWaitNotification,
  stopRKey,
  fixupNoChosenMenu,
  formSubmit,
  appSettings,
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
