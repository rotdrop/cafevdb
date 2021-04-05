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
/**
 * @file
 *
 * Collect some jQuery tweaks in this file.
 *
 */

import { $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import 'bootstrap/js/dist/tooltip';

require('tooltips.css');

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
    console.log('will open dialog');
    $.fn.dialog.call(this, argument);
    if (this.dialog('option', 'draggable')) {
      console.log('Try to set containment');
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

/**
 * Extend the tooltips to honour some special class elements, and
 * attach user specified tooltip-... classes to the actual tooltip
 * popups.
 *
 * @param {Object} argument TBD.
 *
 * @returns {Object}
 *
 */
$.fn.cafevTooltip = function(argument) {
  if (typeof argument === 'undefined') {
    argument = {};
  }
  if (typeof argument === 'object' && argument != null) {
    const whiteList = $.extend(
      {},
      $.fn.tooltip.Constructor.Default.whiteList,
      {
        table: [], thead: [], tbody: [], tr: [], td: [], th: [], dl: [], dt: [], dd: [],
      }
    );
    const options = {
      container: 'body',
      html: true,
      sanitize: true, // @todo just tweak whitelist
      whiteList,
      placement: 'auto',
      cssclass: [],
      fallbackPlacement: 'flip',
      boundary: 'viewport',
      // delay: { show: 500, hide: 100000 },
    };
    argument = $.extend(true, {}, options, argument);
    if (typeof argument.placement === 'string') {
      const words = argument.placement.split(' ');
      if (words.length > 1) {
        for (const word of words) {
          if (word !== 'auto') {
            argument.placement = word;
            break;
          }
        }
      }
    }
    if (argument.cssclass && typeof argument.cssclass === 'string') {
      argument.cssclass = [argument.cssclass];
    }
    argument.cssclass.push('cafevdb');
    // iterator over individual element in order to pick up the
    // correct class-arguments.
    this.each(function(index) {
      const self = $(this);
      const selfOptions = $.extend(true, {}, argument);
      const classAttr = self.attr('class');
      if (classAttr) {
        if (classAttr.match(/tooltip-off/) !== null) {
          self.cafevTooltip('disable');
          return;
        }
        const tooltipClasses = classAttr.match(/tooltip-[a-z-]+/g);
        if (tooltipClasses) {
          for (let idx = 0; idx < tooltipClasses.length; ++idx) {
            const tooltipClass = tooltipClasses[idx];
            const placement = tooltipClass.match(/^tooltip-(bottom|top|right|left)$/);
            if (placement && placement.length === 2 && placement[1].length > 0) {
              selfOptions.placement = placement[1];
              continue;
            }
            selfOptions.cssclass.push(tooltipClass);
          }
        }
      }
      $.fn.tooltip.call(self, 'dispose');
      const originalTitle = self.data('original-title');
      if (originalTitle && !self.attr('title')) {
        self.attr('title', originalTitle);
      }
      self.removeAttr('data-original-title');
      self.removeData('original-title');
      const title = self.attr('title');
      if (title === undefined || title.trim() === '') {
        self.removeAttr('title');
        self.cafevTooltip('destroy');
        return;
      }
      if (!selfOptions.template) {
        selfOptions.template = '<div class="tooltip '
          + selfOptions.cssclass.join(' ')
          + '" role="tooltip">'
          + '<div class="tooltip-arrow"></div>'
          + '<div class="tooltip-inner"></div>'
          + '</div>';
      }
      $.fn.tooltip.call(self, selfOptions);
    });
  } else {
    if (argument === 'destroy') {
      arguments[0] = 'dispose';
    }
    $.fn.tooltip.apply(this, arguments);
  }
  return this;
};

$.fn.cafevTooltip.enable = function() {
  $('[data-original-title]').cafevTooltip('enable');
};

$.fn.cafevTooltip.disable = function() {
  $('[data-original-title]').cafevTooltip('disable');
};

// remove left-over tooltips
$.fn.cafevTooltip.remove = function() {
  $('div.tooltip[role=tooltip]').each(function(index) {
    const tip = $(this);
    const id = tip.attr('id');
    $('[aria-describedby=' + id + ']').removeAttr('aria-describedby');
    $(this).remove();
  });
};

$.fn.cafevTooltip.hide = function() {
  $('[data-original-title]').cafevTooltip('hide');
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
