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
 * Tweak the tooltips class.
 *
 */

import $ from './jquery.js';
import { appName } from './app-info.js';
import 'bootstrap/js/dist/tooltip';

require('tooltips.css');

console.log('jquery-cafevdb-tooltips');

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

/**
 * Extend the tooltips to honour some special class elements, and
 * attach user specified tooltip-... classes to the actual tooltip
 * popups.
 *
 * @param {object} argument TBD.
 *
 * @returns {object}
 *
 */
$.fn.cafevTooltip = function(argument) {
  if (typeof argument === 'undefined') {
    argument = {};
  }
  if (typeof argument === 'object' && argument != null) {
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
      const appTitle = self.data(appName + 'Title');
      if (appTitle && !self.attr('title')) {
        self.attr('title', appTitle);
      } else {
        const originalTitle = self.data('original-title');
        if (originalTitle && !self.attr('title')) {
          self.attr('title', originalTitle);
        }
      }
      self.removeData(appName + 'Title');
      self.removeAttr('data-' + appName + '-title');
      self.removeAttr('data-original-title');
      self.removeData('original-title');
      // const title = self.attr('title');
      // if ((title === undefined || title.trim() === '') && !self.is(':invalid')) {
      //   self.removeAttr('title');
      //   self.cafevTooltip('destroy');
      //   return;
      // }
      if (!selfOptions.title) {
        self.data(appName + 'Title', self.attr('title'));
        self.attr('data-' + appName + '-title', self.attr('title'));
        self.removeAttr('title');
        selfOptions.title = function() {
          const $this = $(this);
          const originalTitle = $this.data(appName + 'Title');
          if ($this.is(':invalid')) {
            const invalidHint = t(appName, 'Please fill out this field!');
            if (!selfOptions.html) {
              return invalidHint + (originalTitle ? '\n' + originalTitle : '');
            }
            let titleHtml = `<div class="tooltip-field-required">${invalidHint}</div>`;
            if (originalTitle) {
              titleHtml += `<div class="tooltip-original-title">${originalTitle}</div>`;
            }
            return titleHtml;
          }
          return originalTitle;
        };
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

// Local Variables: ***
// indent-tabs-mode: nil ***
// js-indent-level: 2 ***
// End: ***
