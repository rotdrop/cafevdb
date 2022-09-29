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

const whiteList = {
  ...$.fn.tooltip.Constructor.Default.whiteList,
  table: [],
  thead: [],
  tbody: [],
  tr: [],
  td: [],
  th: [],
  dl: [],
  dt: [],
  dd: [],
};

const defaultOptions = {
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

let backGroundCount = 0;
let maxBackGroundCount = 0;

let backGroundDeferred = $.Deferred();
let backGroundPromise = backGroundDeferred.promise();

const rejectBackgroundPromise = function() {
  backGroundDeferred.reject(maxBackGroundCount);
  maxBackGroundCount = 0;
  backGroundDeferred = $.Deferred();
  backGroundPromise = backGroundDeferred.promise();
};

const unregisterBackgroundJob = function() {
  if (--backGroundCount === 0) {
    backGroundDeferred.resolve(maxBackGroundCount);
    maxBackGroundCount = 0;
    backGroundDeferred = $.Deferred();
    backGroundPromise = backGroundDeferred.promise();
  }
};

const lockElement = function($element) {
  if ($element.data(appName + '-tooltip-lock') === true) {
    return false;
  }
  $element.data(appName + '-tooltip-lock', true);
  if (++backGroundCount > maxBackGroundCount) {
    maxBackGroundCount = backGroundCount;
  }
  return true;
};

const unlockElement = function($element) {
  unregisterBackgroundJob();
  $element.data(appName + '-tooltip-lock', false);
};

function singleToolTipWorker(optionsForAll) {
  const $this = $(this);
  const selfOptions = $.extend(true, {}, optionsForAll);
  const classAttr = $this.attr('class');
  if (classAttr) {
    if (classAttr.match(/tooltip-off/) !== null) {
      $this.cafevTooltip('disable');
      unlockElement($this);
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
  $.fn.tooltip.call($this, 'dispose');
  const appTitle = $this.data(appName + 'Title');
  if (appTitle && !$this.attr('title')) {
    $this.attr('title', appTitle);
  } else {
    const originalTitle = $this.data('original-title');
    if (originalTitle && !$this.attr('title')) {
      $this.attr('title', originalTitle);
    }
  }
  $this.removeData(appName + 'Title');
  $this.removeAttr('data-' + appName + '-title');
  $this.removeAttr('data-original-title');
  $this.removeData('original-title');
  // const title = $this.attr('title');
  // if ((title === undefined || title.trim() === '') && !$this.is(':invalid')) {
  //   $this.removeAttr('title');
  //   $this.cafevTooltip('destroy');
  //   return;
  // }
  if (!selfOptions.title) {
    $this.data(appName + 'Title', $this.attr('title'));
    $this.attr('data-' + appName + '-title', $this.attr('title'));
    $this.removeAttr('title');
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
  $.fn.tooltip.call($this, selfOptions);
  unlockElement($this);
}

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
  const $this = this;
  if (typeof argument === 'undefined') {
    argument = {};
  }
  if (typeof argument === 'object' && argument != null) {
    const optionsForAll = $.extend(true, {}, defaultOptions, argument);
    if (typeof optionsForAll.placement === 'string') {
      const words = optionsForAll.placement.split(' ');
      if (words.length > 1) {
        for (const word of words) {
          if (word !== 'auto') {
            optionsForAll.placement = word;
            break;
          }
        }
      }
    }
    if (optionsForAll.cssclass && typeof optionsForAll.cssclass === 'string') {
      optionsForAll.cssclass = [optionsForAll.cssclass];
    }
    optionsForAll.cssclass.push('cafevdb');
    // Iterator over individual element in order to pick up the
    // correct class-arguments. The setTimeout() hack is in order to
    // fake background jobs and keep the UI somewhat response.
    //
    // @todo This has to be reworked, tooltips just take too much time.
    $this.each(function(index) {
      const $element = $(this);
      if (!lockElement($element)) {
        return;
      }
      setTimeout(() => singleToolTipWorker.call($(this), optionsForAll));
    });
  } else {
    if (argument === 'destroy') {
      arguments[0] = 'dispose';
    }
    $.fn.tooltip.apply(this, arguments);
  }
  return $this;
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

export {
  backGroundCount,
  backGroundPromise,
  rejectBackgroundPromise,
};

// Local Variables: ***
// indent-tabs-mode: nil ***
// js-indent-level: 2 ***
// End: ***
