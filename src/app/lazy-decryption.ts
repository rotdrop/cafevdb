/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { UnsealedData } from '../../build/ts-types/php-modules/Controller/DTO.ts';

import { translate as t } from '@nextcloud/l10n';
import { END_POINT as controllerEndPoint } from '../../build/ts-types/php-modules/Controller/CryptoController.ts';
import * as DataConstants from '../../build/ts-types/php-modules/PageRenderer/DataConstants.ts';
import { appName } from '../config.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import $ from './jquery.ts';
import {
  cellSelector as pmeCellSelector,
  classSelector as pmeClassSelector,
  inputSelector as pmeInputSelector,
  queryInfoSelector as pmeQueryInfoSelector,
} from './pme-selectors.ts';
import { options as getOptions, refreshWidget } from './select-utils.ts';

import { tooltipWideCssClass } from 'tooltips.scss';

const cryptoCache: Record<string, UnsealedData> = {};

const batchSize = 10;

let decryptionJobCount = 0;
let maxDecryptionJobCount = 0;

let decryptionDeferred = $.Deferred();
let decryptionPromise = decryptionDeferred.promise();

// array of $.ajax promises.
const decryptionCalls: JQuery.jqXHR[] = [];
const decryptionTimer: NodeJS.Timeout[] = [];

const increaseDecryptionJobCount = function(count: number) {
  decryptionJobCount += count;
  if (decryptionJobCount > maxDecryptionJobCount) {
    maxDecryptionJobCount = decryptionJobCount;
  }
  console.info('INCREMENT JOBS', decryptionJobCount, count, maxDecryptionJobCount);
};

const decreaseDecryptionJobCount = function(count: number) {
  decryptionJobCount -= count;
  console.info('DECREMENT JOBS', decryptionJobCount, count, maxDecryptionJobCount);
  if (decryptionJobCount <= 0) {
    decryptionCalls.splice(0);
    decryptionTimer.splice(0);
    console.info('RESOLVE WITH', maxDecryptionJobCount);
    decryptionDeferred.resolve(maxDecryptionJobCount);
    decryptionJobCount = 0;
    maxDecryptionJobCount = 0;
    decryptionDeferred = $.Deferred();
    decryptionPromise = decryptionDeferred.promise();
  }
};

const rejectDecryptionPromise = function() {
  const calls = decryptionCalls.splice(0);
  for (const promise of calls) {
    promise.abort('cancelled');
  }
  for (const timer of decryptionTimer.splice(0)) {
    clearTimeout(timer);
  }
  decryptionDeferred.reject(maxDecryptionJobCount);
  decryptionJobCount = 0;
  maxDecryptionJobCount = 0;
  decryptionDeferred = $.Deferred();
  decryptionPromise = decryptionDeferred.promise();
};

const metaDataText = function(metaData: Record<string, undefined|null|string>|string|string[]) {
  if (typeof metaData === 'string') {
    metaData = [metaData];
  } else if (!Array.isArray(metaData)) {
    const metaDataArray: string[] = [];
    for (const [key, value] of Object.entries(metaData).filter(([_key, value]) => !!value)) {
      metaDataArray.push(t(appName, key) + ': ' + value!);
    }
    metaData = metaDataArray;
  }
  return metaData.join('<br/>');
};

const getData = <K extends string>($element: JQuery, key: K, silent: boolean = false) => {
  const data = $element.attr(`data-${key}`);
  if (!silent && !data) {
    console.error('DATA ATTRIBUTE IS EMPTY DURING LAZY DECRYPTION', { $element, key });
  }
  return data;
};
const getDataOriginalValue = ($element: JQuery, silent: boolean = false) =>
  getData($element, DataConstants.DATA_PME_ORIGINAL_VALUE, silent);
const getDataCryptoHash = ($element: JQuery, silent: boolean = false) =>
  getData($element, DataConstants.DATA_CRYPTO_HASH, silent);
// sealed data is only there for special filter selects, otherwise use the option value
const getDataSealedValue = ($element: JQuery) =>
  getData($element, DataConstants.DATA_SEALED_VALUE, true);
const getDataMetaData = ($element: JQuery) => getData($element, DataConstants.DATA_META_DATA);
const getDataPMEValues = ($element: JQuery) => {
  const data = getData($element, DataConstants.DATA_PME_PME_VALUES);
  if (!data) {
    return undefined;
  }
  try {
    return JSON.parse(data);
  } catch {
    console.error('PARSING PME VALUES FAILED DURING LAZY DECRYPTION', { $element, data });
    return undefined;
  }
};

const getElementCryptoHash = ($element: JQuery) => {
  const hash = getDataCryptoHash($element, true) ?? getDataOriginalValue($element, true);
  if (hash === undefined) {
    console.error('UNABLE TO OBTAIN CRYPT-HASH FROM ELEMENT', { $element });
  }
  return hash;
};

const getCachedCryptoData = (hash: undefined|string) => hash ? cryptoCache[hash] : undefined;

const replaceElementEncryptionPlaceholder = function($element: JQuery, cryptoData?: UnsealedData) {
  if (!cryptoData) {
    cryptoData = getCachedCryptoData(getElementCryptoHash($element));
    if (!cryptoData) {
      return;
    }
  }
  $element.html(cryptoData.data)
    .removeClass('encryption-placeholder')
    .removeAttr('data-encrypted-value')
    .removeData('dataEncryptedValue');
  // remove background decryption hint
  $element
    .cafevTooltip('dispose')
    .removeAttr('title');
  if ($element.hasClass('meta-data-popup')) {
    if (cryptoData.metaData) {
      $element
        .attr('title', metaDataText(cryptoData.metaData))
        .cafevTooltip({ placement: 'auto' });
    }
  }
  const $tableCell = $element.closest(pmeCellSelector);
  if ($tableCell.length === 1 && $tableCell.find('.encryption-placeholder').length === 0) {
    const popupText: string[] = [];
    if ($tableCell.hasClass('cell-data-popup')) {
      popupText.push($tableCell.html());
    } else if ($tableCell.hasClass('meta-data-popup')) {
      $tableCell.find(`[data-${DataConstants.DATA_CRYPTO_HASH}]`).each(function() {
        const cryptoData = getCachedCryptoData(getElementCryptoHash($(this)));
        if (!cryptoData || !cryptoData.metaData) {
          return;
        }
        popupText.push(metaDataText(cryptoData.metaData));
      });
    }
    $tableCell
      .cafevTooltip('dispose')
      .attr('title', popupText.join('<hr/>'))
      .cafevTooltip({ placement: 'auto' });
  }
  const $queryInfo = $element.closest(pmeQueryInfoSelector);
  if ($queryInfo.length === 1 && $queryInfo.find('.encryption-placeholder').length === 0) {
    const popupText = $queryInfo.html();
    $queryInfo
      .cafevTooltip('dispose')
      .attr('title', popupText)
      .cafevTooltip({ placement: 'auto', cssClass: tooltipWideCssClass });
  }
};

const replaceEncryptionPlaceholder = function(
  cryptoData: UnsealedData,
  $container: JQuery,
  $filter: JQuery<HTMLSelectElement>,
  $option: JQuery,
) {
  $option.html(cryptoData.data);
  if ($filter.hasClass('meta-data-popup') && cryptoData.metaData) {
    $option
      .cafevTooltip('dispose')
      .attr('title', metaDataText(cryptoData.metaData))
      .cafevTooltip({ placement: 'auto' });
  }
  refreshWidget($filter);
  $container
    .find(`[data-${DataConstants.DATA_CRYPTO_HASH}="${cryptoData.hash}"].encryption-placeholder`)
    .each(function() { replaceElementEncryptionPlaceholder($(this), cryptoData); });
};

interface BatchJob {
  cryptoHash: string;
  sealedData: string;
}

/**
 * Background-fetch for encrypted PME fields, batch AJAX calls.
 *
 * @param $container TBD.
 */
const lazyBatchDecryptValues = function($container: JQuery) {
  // replace any remaining, also run if the cache is just used as is.
  decryptionPromise.always(() => {
    $container
      .find(`[data-${DataConstants.DATA_CRYPTO_HASH}].encryption-placeholder`)
      .each(function() { replaceElementEncryptionPlaceholder($(this)); });
  });
  const batchJobs: Record<string, Record<string, BatchJob>> = {};
  const batchOptions = {};
  const batchInputs = {};
  const $filters = $container.find(pmeClassSelector('select', 'filter') + '.lazy-decryption') as JQuery<HTMLSelectElement>;
  $filters.each(function() {
    const $filter = $(this);
    $filter.removeClass(DataConstants.CLASS_LAZY_DECRYPTION);
    const metaData = getDataMetaData($filter);
    if (!metaData) {
      return;
    }
    getOptions($filter).each(function() {
      const $option = $(this);
      const optionValue = $option.val();
      if (optionValue === '' || optionValue === '*' || optionValue === '!=%') {
        // skip special placeholder filters.
        return;
      }
      const cryptoHash = getElementCryptoHash($option);
      const cachedData = getCachedCryptoData(cryptoHash);
      if (cachedData) {
        replaceEncryptionPlaceholder(cachedData, $container, $filter, $option);
        return;
      }
      const sealedData = (getDataSealedValue($option) ?? $option.val()) as string|undefined;
      if (!cryptoHash || !sealedData || sealedData === '*') {
        return;
      }
      batchJobs[metaData] = batchJobs[metaData] || {};
      batchJobs[metaData][cryptoHash] = { sealedData, cryptoHash };
      batchOptions[cryptoHash] = { select: $filter, option: $option };
    });
  });
  const $inputs = $container.find(pmeInputSelector + '.lazy-decryption');
  $inputs.each(function() {
    const $input = $(this);
    const metaData = getDataMetaData($input);
    if (!metaData) {
      return;
    }
    const values: Record<string, string> = getDataPMEValues($input)?.values || {};
    const valueCryptoHash = getElementCryptoHash($input);
    for (const [sealedData, cryptoHash] of Object.entries(values)) {
      const cachedData = getCachedCryptoData(cryptoHash);
      if (cachedData) {
        values[sealedData] = cachedData.data;
        if (valueCryptoHash === cryptoHash) {
          $input.val(cachedData.data);
        }
      } else {
        batchJobs[metaData] = batchJobs[metaData] || {};
        batchJobs[metaData][cryptoHash] = { sealedData, cryptoHash };
        batchInputs[cryptoHash] = {
          input: $input,
          hash: cryptoHash,
          sealedData,
          values,
        };
      }
    }
  });
  // console.info('BATCH JOBS', batchJobs);
  for (const [metaData, jobs] of Object.entries(batchJobs)) {
    const jobsArray = Object.values(jobs);
    for (let i = 0; i < jobsArray.length; i += batchSize) {
      const valuesChunk = jobsArray.slice(i, i + batchSize);
      increaseDecryptionJobCount(valuesChunk.length);
      const timer = setTimeout(() => {
        const url = generateAppUrl(controllerEndPoint);
        const ajaxPromise = $.post(
          url,
          {
            sealedData: valuesChunk.map((job) => job.sealedData),
            metaData,
          },
        )
          .fail(function(xhr, textStatus, errorThrown) {
            console.info('DECRYPTION FAILED', valuesChunk, xhr, textStatus, errorThrown);
            decreaseDecryptionJobCount(valuesChunk.length);
          })
          .done(function(data) {
            for (const dataItem of data) {
              const cryptoHash = dataItem.hash;
              cryptoCache[cryptoHash] = dataItem;
              const batchOption = batchOptions[cryptoHash];
              if (batchOption) {
                replaceEncryptionPlaceholder(dataItem, $container, batchOption.select, batchOption.option);
              }
              const batchInput = batchInputs[cryptoHash];
              if (batchInput) {
                const $input = batchInput.input;
                const valueCryptoHash = getElementCryptoHash($input);
                if (valueCryptoHash === cryptoHash && $input.val() === cryptoHash) {
                  $input.val(dataItem.data);
                }
                batchInput.values[batchInput.sealedData] = dataItem.data;
                const pmeValues = getDataPMEValues($input);
                pmeValues.values = batchInput.values;
                $input.attr(`data-${DataConstants.DATA_PME_PME_VALUES}`, JSON.stringify(pmeValues));
              }
            }
            decreaseDecryptionJobCount(valuesChunk.length);
          });
        decryptionCalls.push(ajaxPromise);
      });
      decryptionTimer.push(timer);
    }
  }
  decreaseDecryptionJobCount(0); // resolves if nothing had to be done.
};

export default lazyBatchDecryptValues;

export {
  decryptionJobCount as jobCount,
  lazyBatchDecryptValues as lazyDecrypt,
  decryptionPromise as promise,
  rejectDecryptionPromise as reject,
};
