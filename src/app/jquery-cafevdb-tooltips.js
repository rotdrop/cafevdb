/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import jQuery from './jquery.js';
import { appName } from './app-info.js';
import 'bootstrap/js/dist/tooltip.js';

require('tooltips.scss');

const $ = jQuery;

console.log('jquery-cafevdb-tooltips');

const toolTipJobInitialTimeOut = 100; // ms
const toolTipJobRunnerTimeOut = 0; // ms

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
  timestamp: false,
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

let markCount = 0;

const getMarkCount = () => markCount;
const setMarkCount = (value) => { markCount = value; };

const markElement = function($element, timestamp) {
  if (timestamp !== false) {
    if ($element.data(appName + '-tooltip-timestamp') === timestamp) {
      markCount++;
      return false;
    }
    $element.data(appName + '-tooltip-timestamp', timestamp);
  }
  return true;
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

const toolTipsWorkQueue = [];

// const spaceRe = /\s+/;

function singleToolTipWorker($this, optionsForAll, jobChunkSize) {
  // const $this = this;
  const selfOptions = $.extend(true, {}, optionsForAll);
  const attrClass = $this.attr('class') || '';
  for (const cssClass of attrClass.split(/\s+/)) {
    switch (cssClass) {
    case 'tooltip-off':
      $this.cafevTooltip('disable');
      unlockElement($this);
      return;
    case 'tooltip-bottom':
      selfOptions.placement = 'bottom';
      break;
    case 'tooltip-top':
      selfOptions.placement = 'top';
      break;
    case 'tooltip-right':
      selfOptions.placement = 'right';
      break;
    case 'tooltip-left':
      selfOptions.placement = 'left';
      break;
    default:
      if (cssClass.startsWith('tooltip-')) {
        selfOptions.cssclass.push(cssClass);
      }
      break;
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
    selfOptions.template = `<div class="tooltip ${selfOptions.cssclass.join(' ')}" role="tooltip">
  <div class="tooltip-arrow"></div>
  <div class="tooltip-inner"></div>
</div>`;
  }
  $.fn.tooltip.call($this, selfOptions);
  unlockElement($this);
  jobChunkSize = jobChunkSize || 0;
  for (let i = 0; i < jobChunkSize - 1; i++) {
    const job = toolTipsWorkQueue.pop();
    if (job === undefined) {
      break;
    }
    singleToolTipWorker(job.element, job.options);
  }
  const job = toolTipsWorkQueue.pop();
  if (job !== undefined) {
    setTimeout(() => singleToolTipWorker(job.element, job.options, jobChunkSize), toolTipJobRunnerTimeOut);
  }
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
    // fake background jobs and keep the UI somewhat responsive.
    //
    // @todo This has to be reworked, tooltips just take too much time.
    $this.each(function(index) {
      const $element = $(this);
      if (!markElement($element, optionsForAll.timestamp)) {
        return;
      }
      if (!lockElement($element)) {
        return;
      }
      if (backGroundCount === 1) {
        setTimeout(() => singleToolTipWorker($element, optionsForAll, toolTipJobInitialTimeOut));
      } else {
        toolTipsWorkQueue.push({
          element: $element,
          options: optionsForAll,
        });
      }
    });
  } else {
    if (argument === 'destroy') {
      arguments[0] = 'dispose';
    }
    try {
      $.fn.tooltip.apply(this, arguments);
    } catch (e) {
      console.error('EXCEPTION DURING TOOLTIP HANDLING', this, arguments);
    }
    if (argument === 'dispose') {
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
    }
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
  setMarkCount,
  getMarkCount,
};

// Local Variables: ***
// indent-tabs-mode: nil ***
// js-indent-level: 2 ***
// End: ***
