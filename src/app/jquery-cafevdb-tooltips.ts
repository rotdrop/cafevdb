/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import { appName } from '../config.ts';
import toolTipProvider from 'bootstrap/js/dist/tooltip.js';
import { translate as t } from '@nextcloud/l10n';
import { joinLiterals } from '../toolkit/util/string-literals.ts';
import type Tooltip from 'bootstrap/js/dist/tooltip.js';

require('tooltips.scss');

type TooltipOptions = Tooltip.Options & {
  cssclass: string[],
  timestamp: boolean,
};

const vendorOriginalTitleKey = 'bsOriginalTitle' as const;
const vendorOriginalTitleAttribute = 'data-bs-original-title' as const;
const appTitleKey = joinLiterals('')(appName, 'Title');
const appTitleAttribute = joinLiterals('')('data-', appName, '-title');

const toolTipJobInitialTimeOut = 100; // ms
const toolTipJobRunnerTimeOut = 0; // ms

const allowList = {
  ...toolTipProvider.Default.allowList,
  table: [] as string[],
  thead: [] as string[],
  tbody: [] as string[],
  tr: [] as string[],
  td: [] as string[],
  th: [] as string[],
  dl: [] as string[],
  dt: [] as string[],
  dd: [] as string[],
};

const Placements = ['auto', 'left', 'right', 'top', 'bottom'] as const;

const defaultOptions: Partial<TooltipOptions> = {
  container: 'body',
  html: true,
  sanitize: true, // @todo just tweak whitelist
  allowList,
  placement: 'auto',
  cssclass: [] as string[],
  fallbackPlacements: ['top', 'right', 'bottom', 'left'],
  boundary: 'clippingParents',
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

const markElement = function($element: JQuery, timestamp: boolean) {
  if (timestamp !== false) {
    if ($element.data(appName + '-tooltip-timestamp') === timestamp) {
      return false;
    }
    $element.data(appName + '-tooltip-timestamp', timestamp);
  }
  return true;
};

const lockElement = function($element: JQuery) {
  if ($element.data(appName + '-tooltip-lock') === true) {
    return false;
  }
  $element.data(appName + '-tooltip-lock', true);
  if (++backGroundCount > maxBackGroundCount) {
    maxBackGroundCount = backGroundCount;
  }
  return true;
};

const unlockElement = function($element: JQuery) {
  unregisterBackgroundJob();
  $element.data(appName + '-tooltip-lock', false);
};

const toolTipsWorkQueue: {
  element: JQuery,
  options: TooltipOptions,
}[] = [];

// const spaceRe = /\s+/;

function singleToolTipWorker($this: JQuery, optionsForAll: TooltipOptions, jobChunkSize?: number) {
  // const $this = this;
  const selfOptions: TooltipOptions = $.extend(true, {}, optionsForAll);
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
  $this.tooltip('dispose');
  // $.fn.tooltip.call($this, 'dispose');
  const appTitle = $this.data(appTitleKey);
  if (appTitle && !$this.attr('title')) {
    $this.attr('title', appTitle);
  } else {
    const originalTitle = $this.data(vendorOriginalTitleKey);
    if (originalTitle && !$this.attr('title')) {
      $this.attr('title', originalTitle);
    }
  }
  $this.removeData(appTitleKey);
  $this.removeAttr(appTitleAttribute);
  $this.removeAttr(vendorOriginalTitleAttribute);
  $this.removeData(vendorOriginalTitleKey);
  if (!selfOptions.title) {
    $this.data(appTitleKey, $this.attr('title') ?? null);
    $this.attr(appTitleAttribute, $this.attr('title') ?? null);
    $this.removeAttr('title');
    selfOptions.title = function() {
      const $this = $(this);
      const originalTitle = $this.data(appTitleKey);
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
  $this.tooltip(selfOptions);
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

type TooltipArgument = Parameters<Tooltip.jQueryInterface>[0];

function cafevTooltip<T extends HTMLElement>(this: JQuery<T>, config?: Partial<TooltipOptions>|TooltipArgument) {
  // eslint-disable-next-line @typescript-eslint/no-this-alias
  const $this = this; // $(this);
  if (arguments.length === 1 && typeof config !== 'string') {
    const optionsForAll = $.extend(true, {}, defaultOptions, config);
    if (typeof optionsForAll.placement === 'string') {
      const words = optionsForAll.placement.split(' ');
      for (const word of words) {
        // @ts-expect-error 2345 includes really should allow an arbitrary string
        if (Placements.includes(word)) {
          optionsForAll.placement = word as typeof Placements[number];
          break;
        }
      }
    }
    if (optionsForAll.cssclass && typeof optionsForAll.cssclass === 'string') {
      optionsForAll.cssclass = [optionsForAll.cssclass];
    }
    optionsForAll.cssclass!.push('app-' + appName);
    // Iterator over individual element in order to pick up the
    // correct class-arguments. The setTimeout() hack is in order to
    // fake background jobs and keep the UI somewhat responsive.
    //
    // @todo This has to be reworked, tooltips just take too much time.
    $this.each(function() {
      const $element = $(this);
      if (!markElement($element, !!optionsForAll.timestamp)) {
        return;
      }
      if (!lockElement($element)) {
        return;
      }
      if (backGroundCount === 1) {
        setTimeout(() => singleToolTipWorker($element, optionsForAll as TooltipOptions, toolTipJobInitialTimeOut));
      } else {
        toolTipsWorkQueue.push({
          element: $element,
          options: optionsForAll as TooltipOptions,
        });
      }
    });
  } else {
    try {
      $this.tooltip(config);
    } catch (e) {
      console.error('EXCEPTION DURING TOOLTIP HANDLING', { self: this, config });
    }
    if (config === 'dispose') {
      const appTitle = $this.data(appTitleKey);
      if (appTitle && !$this.attr('title')) {
        $this.attr('title', appTitle);
      } else {
        const originalTitle = $this.data(vendorOriginalTitleKey);
        if (originalTitle && !$this.attr('title')) {
          $this.attr('title', originalTitle);
        }
      }
      $this.removeData(appTitleKey);
      $this.removeAttr(appTitleAttribute);
      $this.removeAttr(vendorOriginalTitleAttribute);
      $this.removeData(vendorOriginalTitleKey);
    }
  }
  return $this;
}

cafevTooltip.enable = () => {
  $('[' + appTitleAttribute + ']').cafevTooltip('enable');
};
cafevTooltip.disable = () => {
  $('[' + appTitleAttribute + ']').cafevTooltip('disable');
};
// remove left-over tooltips
cafevTooltip.remove = () => {
  $('div.tooltip[role=tooltip]').each(function() {
    const $tip = $(this);
    const id = $tip.attr('id');
    $('[aria-describedby=' + id + ']').removeAttr('aria-describedby');
    $tip.remove();
  });
};
cafevTooltip.hide = () => {
  $('[' + appTitleAttribute + ']').cafevTooltip('hide');
};

$.fn.cafevTooltip = cafevTooltip;

export {
  backGroundCount,
  backGroundPromise,
  rejectBackgroundPromise,
};
