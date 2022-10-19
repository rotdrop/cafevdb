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
import { classSelector as pmeClassSelector, cellSelector as pmeCellSelector } from './pme-selectors.js';
import generateUrl from './generate-url.js';
import jQuery from './jquery.js';
import { isPlainObject } from 'is-plain-object';

const $ = jQuery;

globalState.cryptoCache = globalState.cryptoCache || {};

const cryptoCache = globalState.cryptoCache;

const batchSize = 10;

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

const replaceEncryptionPlaceholder = async function(data, $container, $filter, $option) {
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
    });
};

/**
 * Background-fetch for encrypted PME fields, batch AJAX calls.
 *
 * @param {jQuery} $container TBD.
 */
const lazyBatchDecryptValues = function($container) {
  const $filters = $container.find(pmeClassSelector('select', 'filter') + '.lazy-decryption');
  console.debug('FILTERS NEEDING DECRYPTION', $filters);
  $filters.each(function() {
    const $filter = $(this);
    $filter.removeClass('lazy-decryption');
    const metaData = $filter.data('metaData');
    const $options = getOptions($filter);
    const batchJobs = [];
    const batchOptions = [];
    $options.each(function() {
      const $option = $(this);
      const cryptoHash = $option.data('cryptoHash');
      const cachedData = cryptoCache[cryptoHash];
      if (cachedData) {
        replaceEncryptionPlaceholder(cachedData, $container, $filter, $option);
        return;
      }
      const sealedData = $option.val();
      if (!sealedData || sealedData === '' || sealedData === '*') {
        return;
      }
      batchJobs.push({ sealedData, cryptoHash });
      batchOptions[cryptoHash] = $option;
    });
    for (let i = 0; i < batchJobs.length; i += batchSize) {
      setTimeout(() => {
        const valuesChunk = batchJobs.slice(i, i + batchSize);
        const url = generateUrl('crypto/decryption/unseal/batch');
        $.post(url, { sealedData: valuesChunk.map((job) => job.sealedData), metaData })
          .fail(function(xhr, textStatus, errorThrown) {
            console.info('DECRYPTION FAILED', valuesChunk, xhr, textStatus, errorThrown);
          })
          .done(function(data) {
            for (const dataItem of data) {
              const cryptoHash = dataItem.hash;
              cryptoCache[cryptoHash] = dataItem;
              if (!batchOptions[cryptoHash]) {
                console.info('BUG', batchOptions, dataItem);
              }
              replaceEncryptionPlaceholder(dataItem, $container, $filter, batchOptions[cryptoHash]);
            }
          });
      });
    }
  });
};

/**
 * Background-fetch for encrypted PME fields, one-by-one AJAX calls.
 *
 * @param {jQuery} $container TBD.
 */
const lazyDecryptValues = function($container) {
  const $filters = $container.find(pmeClassSelector('select', 'filter') + '.lazy-decryption');
  console.debug('FILTERS NEEDING DECRYPTION', $filters);
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
      const sealedData = $option.val();
      if (!sealedData) {
        return;
      }
      setTimeout(() => {
        const url = generateUrl('crypto/decryption/unseal');
        $.post(url, { sealedData, metaData })
          .fail(function(xhr, textStatus, errorThrown) {
            console.info('DECRYPTION FAILED', sealedData, xhr, textStatus, errorThrown);
          })
          .done(function(data) {
            cryptoCache[data.hash] = data;
            replaceEncryptionPlaceholder(data, $container, $filter, $option);
          });
      });
    });
  });
};

export default lazyBatchDecryptValues;

export {
  lazyBatchDecryptValues,
  lazyDecryptValues,
};
