/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from './app-info.js';
import globalState from './globalstate.js';
import { options as getOptions, refreshWidget } from './select-utils.js';
import {
  classSelector as pmeClassSelector,
  cellSelector as pmeCellSelector,
  inputSelector as pmeInputSelector,
  queryInfoSelector as pmeQueryInfoSelector,
} from './pme-selectors.js';
import generateUrl from './generate-url.js';
import jQuery from './jquery.js';
import { isPlainObject } from 'is-plain-object';

const $ = jQuery;

globalState.cryptoCache = globalState.cryptoCache || {};

const cryptoCache = globalState.cryptoCache;

const batchSize = 10;

let decryptionJobCount = 0;
let maxDecryptionJobCount = 0;

let decryptionDeferred = $.Deferred();
let decryptionPromise = decryptionDeferred.promise();

// array of $.ajax promises.
const decryptionCalls = [];
const decryptionTimer = [];

const increaseDecryptionJobCount = function(count) {
  decryptionJobCount += count;
  if (decryptionJobCount > maxDecryptionJobCount) {
    maxDecryptionJobCount = decryptionJobCount;
  }
  console.info('INCREMENT JOBS', decryptionJobCount, count, maxDecryptionJobCount);
};

const decreaseDecryptionJobCount = function(count) {
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

const metaDataText = function(metaData) {
  if (isPlainObject(metaData)) {
    const metaDataArray = [];
    for (const [key, value] of Object.entries(metaData)) {
      metaDataArray.push(t(appName, key) + ': ' + value);
    }
    metaData = metaDataArray;
  } else if (!Array.isArray(metaData)) {
    metaData = [metaData];
  }
  return metaData.join('<br/>');
};

const replaceEncryptionPlaceholder = function(data, $container, $filter, $option) {
  $option.html(data.data);
  if ($filter.hasClass('meta-data-popup') && data.metaData) {
    $option
      .cafevTooltip('dispose')
      .attr('title', metaDataText(data.metaData))
      .cafevTooltip({ placement: 'auto' });
  }
  refreshWidget($filter);
  $container.find('[data-crypto-hash="' + data.hash + '"].encryption-placeholder')
    .each(function() {
      const $this = $(this);
      $this.html(data.data)
        .removeClass('encryption-placeholder')
        .removeAttr('data-encrypted-value')
        .removeData('dataEncryptedValue');
      $this.cafevTooltip('dispose'); // remove background decryption hint
      if ($this.hasClass('meta-data-popup')) {
        const cryptoHash = $this.data('cryptoHash');
        const cryptoData = cryptoCache[cryptoHash];
        if (cryptoData && cryptoData.metaData) {
          $this
            .attr('title', metaDataText(cryptoData.metaData))
            .cafevTooltip({ placement: 'auto' });
        }
      }
      const $tableCell = $this.closest(pmeCellSelector);
      if ($tableCell.length === 1 && $tableCell.find('.encryption-placeholder').length === 0) {
        let popupText;
        if ($tableCell.hasClass('cell-data-popup')) {
          popupText = $tableCell.html();
        } else if ($tableCell.hasClass('meta-data-popup')) {
          popupText = [];
          $tableCell.find('[data-crypto-hash]').each(function() {
            const cryptoHash = $(this).data('cryptoHash');
            const cryptoData = cryptoCache[cryptoHash];
            if (!cryptoData || !cryptoData.metaData) {
              return;
            }
            popupText.push(metaDataText(cryptoData.metaData));
          });
          popupText = popupText.join('<hr/>');
        }
        $tableCell
          .cafevTooltip('dispose')
          .attr('title', popupText)
          .cafevTooltip({ placement: 'auto' });
      }
      const $queryInfo = $this.closest(pmeQueryInfoSelector);
      if ($queryInfo.length === 1 && $queryInfo.find('.encryption-placeholder').length === 0) {
        const popupText = $queryInfo.html();
        $queryInfo
          .cafevTooltip('dispose')
          .attr('title', popupText)
          .cafevTooltip({ placement: 'auto', cssClass: 'tooltip-wide' });
      }
    });
};

/**
 * Background-fetch for encrypted PME fields, batch AJAX calls.
 *
 * @param {jQuery} $container TBD.
 */
const lazyBatchDecryptValues = function($container) {
  const batchJobs = {};
  const batchOptions = {};
  const batchInputs = {};
  const $filters = $container.find(pmeClassSelector('select', 'filter') + '.lazy-decryption');
  $filters.each(function() {
    const $filter = $(this);
    $filter.removeClass('lazy-decryption');
    const metaData = $filter.data('metaData');
    const $options = getOptions($filter);
    $options.each(function() {
      const $option = $(this);
      const cryptoHash = $option.data('cryptoHash');
      const cachedData = cryptoCache[cryptoHash];
      if (cachedData) {
        replaceEncryptionPlaceholder(cachedData, $container, $filter, $option);
        return;
      }
      const sealedData = $option.data('sealedValue') || $option.val();
      if (!sealedData || sealedData === '' || sealedData === '*') {
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
    const metaData = $input.data('metaData');
    const values = $input.data('pmeValues').values || {};
    const valueCryptoHash = $input.data('originalValue');
    for (const [sealedData, cryptoHash] of Object.entries(values)) {
      const cachedData = cryptoCache[cryptoHash];
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
        const url = generateUrl('crypto/decryption/unseal/batch');
        const ajaxPromise = $.post(
          url, {
            sealedData: valuesChunk.map((job) => job.sealedData),
            metaData,
          })
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
                const valueCryptoHash = $input.data('originalValue');
                if (valueCryptoHash === cryptoHash && $input.val() === cryptoHash) {
                  $input.val(dataItem.data);
                }
                batchInput.values[batchInput.sealedData] = dataItem.data;
                $input.attr('data-pme-values', JSON.stringify(batchInput.values));
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

// /**
//  * Background-fetch for encrypted PME fields, one-by-one AJAX calls.
//  *
//  * @param {jQuery} $container TBD.
//  */
// const lazyDecryptValues = function($container) {
//   const $filters = $container.find(pmeClassSelector('select', 'filter') + '.lazy-decryption');
//   console.debug('FILTERS NEEDING DECRYPTION', $filters);
//   $filters.each(function() {
//     const $filter = $(this);
//     $filter.removeClass('lazy-decryption');
//     const metaData = $filter.data('metaData');
//     const $options = getOptions($filter);
//     $options.each(function() {
//       const $option = $(this);
//       const cryptoHash = $option.data('cryptoHash');
//       const cachedData = cryptoCache[cryptoHash];
//       if (cachedData) {
//         replaceEncryptionPlaceholder(cachedData, $container, $filter, $option);
//         return;
//       }
//       const sealedData = $option.val();
//       if (!sealedData) {
//         return;
//       }
//       setTimeout(() => {
//         const url = generateUrl('crypto/decryption/unseal');
//         $.post(url, { sealedData, metaData })
//           .fail(function(xhr, textStatus, errorThrown) {
//             console.info('DECRYPTION FAILED', sealedData, xhr, textStatus, errorThrown);
//           })
//           .done(function(data) {
//             cryptoCache[data.hash] = data;
//             replaceEncryptionPlaceholder(data, $container, $filter, $option);
//           });
//       });
//     });
//   });
// };

export default lazyBatchDecryptValues;

export {
  lazyBatchDecryptValues as lazyDecrypt,
  decryptionJobCount as jobCount,
  decryptionPromise as promise,
  rejectDecryptionPromise as reject,
  // lazyDecryptValues,
};
