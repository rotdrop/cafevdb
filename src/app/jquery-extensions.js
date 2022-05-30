/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**
 * @file
 *
 * Collect some jQuery tweaks in this file.
 *
 */

import $ from './jquery.js';
import * as CAFEVDB from './cafevdb.js';
import './jquery-cafevdb-tooltips.js';

require('jquery-ui/ui/widgets/dialog');
require('jquery-ui/ui/widgets/resizable');

console.log('jquery-extensions');

/**
 * We leave it to the z-index-plane to disallow interaction. Every
 * input element above any modal dialog is allowed to interact with
 * the user.
 */
$.widget('ui.dialog', $.ui.dialog, {
  _allowInteraction(event) {
    return true;
  },
});

/**
 * Special dialog version which attaches the dialog to the
 * #content-wrapper div.
 *
 * @param {Object} argument TBD.
 *
 * @returns {Object}
 */
$.fn.cafevDialog = function(argument) {
  if (arguments.length === 1 && typeof argument === 'object' && argument !== null) {
    const options = {
      appendTo: '#cafevdb-general',
      // appendTop: 'body',
    };
    argument = $.extend({}, options, argument);
    if (argument.dialogClass) {
      argument.dialogClass += ' cafev cafevdb';
    } else {
      argument.dialogClass = 'cafev cafevdb';
    }
    if ($('#appsettings_popup').length === 0) {
      CAFEVDB.snapperClose();
    }
    console.trace('will open dialog');
    console.debug('will open dialog');
    $.fn.dialog.call(this, argument);
    if (this.dialog('option', 'draggable')) {
      console.debug('Try to set containment');
      $.fn.dialog.call(this, 'widget').draggable('option', 'containment', '#app-content');
    }
  } else {
    return $.fn.dialog.apply(this, arguments);
  }
  return this;
};

// $.extend($.ui.dialog.prototype.options, {
//   appendTo: '#content',
//   containment: '#content'
// });

/**
 * Determine whether scrollbars would be needed.
 * @returns {object}
 */
$.fn.needScrollbars = function() {
  const node = this.get(0);
  return {
    vertical: node.scrollHeight > node.offsetHeight,
    horizontal: node.scrollWidth > node.offsetWidth,
  };
};

/**
 * Determine whether scrollbars are actually present.
 *
 * We have here the problem that
 *
 * - node.boundingClientRect() does not return the scrollHeight/Width
 * - scrollHeight/Width is rounded
 * - clientHeight/Width is rounded
 *
 * Hence the +1 is an ugly tweak which seems to work a little bit.
 *
 * @returns {Object}
 *
 */
$.fn.hasScrollbars = function() {
  const node = this.get(0);
  return {
    vertical: node.scrollHeight > node.clientHeight + 1,
    horizontal: node.scrollWidth > node.clientWidth + 1,
  };
};

/**
 * Determine dimensions of scrollbars.
 *
 * @returns {Object}
 */
$.fn.scrollbarDimensions = function() {
  const node = this.get(0);
  return {
    height: node.offsetHeight - node.clientHeight + 1,
    width: node.offsetWidth - node.clientWidth + 1,
  };
};

/**
 * Determine whether we have a horizontal scrollbar.
 * @returns {bool}
 */
$.fn.hasHorizontalScrollbar = function() {
  const node = this.get(0);
  return node.scrollWidth > node.clientWidth + 1;
};

/**
 * Determine whether we have a vertical scrollbar.
 * @returns {bool}
 */
$.fn.hasVerticalScrollbar = function() {
  const node = this.get(0);
  return node.scrollHeight > node.clientHeight + 1;
};

/**
 * Determine vertical scrollbar width.
 * @returns {int}
 */
$.fn.verticalScrollbarWidth = function() {
  const node = this.get(0);
  return node.offsetWidth - node.clientWidth;
};

/**
 * Determine horizontal scrollbar height.
 * @returns {int}
 */
$.fn.horizontalScrollbarHeight = function() {
  const node = this.get(0);
  return node.offsetHeight - node.clientHeight;
};

$.extend({
  alert(message, title) {
    $('<div></div>').dialog({
      buttons: { Ok() { $(this).dialog('close'); } },
      open(event, ui) {
        $(this).css({ 'max-height': 800, 'overflow-y': 'auto', height: 'auto' });
        $(this).dialog('option', 'resizable', false);
      },
      close(event, ui) { $(this).remove(); },
      resizable: false,
      title,
      modal: true,
      height: 'auto',
    }).html(message);
  },
});

/**
 * Compute the maximum width of a set of elements
 * @returns {int}
 */
$.fn.maxWidth = function() {
  return Math.max.apply(null, this.map(function() {
    return $(this).width();
  }).get());
};

/**
 * Compute the maximum width of a set of elements
 *
 * @param {Object} extended Blah.
 *
 * @returns {int}
 */
$.fn.maxOuterWidth = function(extended) {
  return Math.max.apply(null, this.map(function() {
    return $(this).outerWidth(extended);
  }).get());
};

/**
 * Compute the maximum height of a set of elements
 *
 * @returns {int}
 */
$.fn.maxHeight = function() {
  return Math.max.apply(null, this.map(function() {
    return $(this).height();
  }).get());
};

/**
 * Compute the maximum height of a set of elements
 *
 * @param {Object} extended Blah.
 *
 * @returns {int}
 */
$.fn.maxOuterHeight = function(extended) {
  return Math.max.apply(null, this.map(function() {
    return $(this).outerHeight(extended);
  }).get());
};

/**
 * jQuery pixel/em conversion plugins: toEm() and toPx()
 * by Scott Jehl (scott@filamentgroup.com), http://www.filamentgroup.com
 * Copyright (c) Filament Group
 *
 * Dual licensed under the MIT
 * (filamentgroup.com/examples/mit-license.txt) or GPL
 * (filamentgroup.com/examples/gpl-license.txt) licenses.  Article:
 * http://www.filamentgroup.com/lab/update_jquery_plugin_for_retaining_scalable_interfaces_with_pixel_to_em_con/
 *
 * Options:
 *   scope: string or jQuery selector for font-size scoping
 * Usage Example: $(myPixelValue).toEm(); or $(myEmValue).toPx();
 *
 * @param {Object} settings TBD.
 *
 * @returns {float}
 */
$.fn.toEm = function(settings) {
  settings = $.extend({
    scope: 'body',
  }, settings);
  const that = parseInt(this[0], 10);
  const scopeTest = $('<div style="display: none; font-size: 1em; margin: 0; padding:0; height: auto; line-height: 1; border:0;">&nbsp;</div>').appendTo(settings.scope);
  const scopeVal = scopeTest.height();
  scopeTest.remove();
  return (that / scopeVal).toFixed(8) + 'em';
};

$.fn.toPx = function(settings) {
  settings = $.extend({
    scope: 'body',
  }, settings);
  const that = parseFloat(this[0]);
  const scopeTest = $('<div style="display: none; font-size: 1em; margin: 0; padding:0; height: auto; line-height: 1; border:0;">&nbsp;</div>').appendTo(settings.scope);
  const scopeVal = scopeTest.height();
  scopeTest.remove();
  return Math.round(that * scopeVal) + 'px';
};

// Local Variables: ***
// indent-tabs-mode: nil ***
// js-indent-level: 2 ***
// End: ***
