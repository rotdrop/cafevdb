/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import { appName } from './config.js';
import * as CAFEVDB from './cafevdb.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import { templateRenderer } from './template-renderer.js';
import * as Dialogs from './dialogs.ts';
import initFileUploadRow from './pme-file-upload-row.js';
import ajaxDownload from './file-download.js';
import { pageRenderer } from './pme-state.js';
import setBusyIndicators from './busy-indicators.js';
import { filename } from './path.js';
import {
  valueSelector as pmeValueSelector,
  sys as pmeSys,
  data as pmeData,
  formSelector as pmeFormSelector,
} from './pme-selectors.js';
import {
  lazyDecrypt,
  reject as rejectDecryptionPromise,
  promise as decryptionPromise,
} from './lazy-decryption.js';
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
} from '../services/async-event-bus.ts';
import { PROJECT_PAYMENT_ACTIONS_MENU } from '../mountable-component-names.ts';
import * as BusEvents from '../event-bus-events.ts';
import actionMenu from './vue-action-menu.ts';

require('project-payments.scss');
require('project-participant-fields-display.scss');

const isCompositeRow = function(rowTag) {
  return rowTag.startsWith('0;');
};

const findByName = function($container, name) {
  return $($container).find('[name="' + name + '"]').filter('input, select, textarea');
};

const ppAmountName = pmeData('ProjectPayments:amount');
const ppSubjectName = pmeData('ProjectPayments:subject');

const template = filename(__filename);

asyncSubscribe(BusEvents.LEGACY_RECORD_POPUP, async (event) => {
  if (event.template !== template) {
    return;
  }
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  await overviewPopup(PHPMyEdit.selector(), event);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

const overviewPopup = async function(containerSel, data) {
  const entityId = data.entityId;
  const tableOptions = {
    ambientContainerSelector: containerSel,
    template,
    templateRenderer: templateRenderer(template),
    // Now special options for the dialog popup
    initialViewOperation: true,
    initialName: pmeSys('operation'),
    initialValue: 'View',
    reloadName: pmeSys('operation'),
    reloadValue: 'View',
    [pmeSys('rec')]: { id: entityId },
    [pmeSys('groupby_rec')]: {
      id: entityId,
      // eslint-disable-next-line camelcase
      ProjectPayments__master_key_: '0;' + entityId,
    },
    [pmeSys('mrec_rec')]: {
      id: data.invoiceId,
      // eslint-disable-next-line camelcase
      ProjectPayments__master_key_: '0;' + entityId,
    },
    projectId: data.projectId,
    projectName: data.projectName,
    modalDialog: true,
    modified: false,
  };
  await PHPMyEdit.tableDialogOpen(tableOptions);
};

/**
 * Generate a popup in order to add a new split-transaction, i.e. a
 * ProjectPayment entity which is always subordinate to a
 * CompositePayment entity.
 *
 * @param {string} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const projectPaymentPopup = function(containerSel, post) {
  // Prepare the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const tableOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: templateRenderer(template),
    Table: 'CompositePayments',
    projectId: post.projectId,
    projectName: post.projectName,
    // Now special options for the dialog popup
    initialViewOperation: false,
    initialName: pmeSys('operation'),
    initialValue: 'Change',
    reloadName: pmeSys('operation'),
    reloadValue: 'Change',
    // reloadMode: 'discard',
    [pmeSys('operation')]: 'Change',
    modalDialog: false,
    modified: false,
  };
  PHPMyEdit.tableDialogOpen({ ...tableOptions, ...post });
};

const backgroundDecryption = function(container) {
  const $container = PHPMyEdit.container(container);
  rejectDecryptionPromise();
  console.time('DECRYPTION PROMISE');
  decryptionPromise.done((maxJobs) => {
    console.timeEnd('DECRYPTION PROMISE');
    console.info('MAX DECRYPTION JOBS HANDLED', maxJobs);
  });
  lazyDecrypt($container);
};

const fileDownload = (url, post, $menu) => {
  const $pmeContainer = $menu.closest(pmeFormSelector);
  ajaxDownload(url, post, {
    always() {
      setBusyIndicators(false, $pmeContainer, false);
      $menu.removeClass('loading');
      $menu.find('button').prop('disabled', false);
    },
    setup() {
      $menu.find('button').prop('disabled', true);
      $menu.addClass('loading');
      setBusyIndicators(true, $pmeContainer, false);
    },
  });
};

const ready = function(selector, pmeParameters, resizeCB) {

  const $container = $(selector);

  if (pmeParameters.reason !== 'dialogClose') {
    backgroundDecryption($container);
  }

  if (pmeParameters.reason === 'dialogOpen') {

    actionMenu($container, template, PROJECT_PAYMENT_ACTIONS_MENU);

    // AJAX download support
    $container
      .on('click', 'a.download-link.ajax-download', function(event) {
        const $this = $(this);
        fileDownload($this.attr('href'));
        return false;
      });

    $container
      .on('contextmenu', 'table.pme-main tr.composite-payment.first td', function(event) {
        if (event.ctrlKey || $(event.target).closest('.dropdown-content').length > 0) {
          return; // let the user see the normal context menu
        }
        const $row = $(this).closest('tr.composite-payment.first');
        event.stopImmediatePropagation();
        $row.toggleClass('following-hidden');
        $row.find('input.expanded-marker').val($row.hasClass('following-hidden') ? 0 : 1);
        return false;
      });

    $container
      .on('change', 'select.instrumentation-id', function(event) {
        const $this = $(this);
        const musicianId = $this.val();
        const $receivables = $container.find('select.receivable');
        const $receivableOptions = $receivables.find('option');
        if (musicianId !== '') {
          const $musicianOption = SelectUtils.optionByValue($this, musicianId);
          const musicianData = $musicianOption.data();
          const allowedOptionKeys = musicianData.keys;
          $receivableOptions.each(function(index) {
            const $option = $(this);
            if ($option.val() !== '') {
              $option.prop('disabled', allowedOptionKeys.indexOf($option.val()) < 0);
            }
          });
          const receivableKey = $receivables.val();
          if (receivableKey !== '') {
            const $amountInput = findByName($container, ppAmountName);
            if (!(+$amountInput.val()) || $amountInput.hasClass('auto-filled')) {
              const value = musicianData.values?.[receivableKey] || '';
              $amountInput.val(value);
              $amountInput.addClass('auto-filled');
            }
          }
        } else {
          $receivableOptions.prop('disabled', false);
        }
        SelectUtils.refreshWidget($receivables);
      });

    $container
      .on('change', 'select.receivable', function(event) {
        const $this = $(this);
        const receivableKey = $this.val();
        const $musicians = $container.find('select.instrumentation-id');
        const $musiciansOptions = $musicians.find('option');
        if (receivableKey !== '') {
          let $selectedMusician;
          $musiciansOptions.each(function(index) {
            const $option = $(this);
            if ($option.val() !== '') {
              $option.prop('disabled', $option.data('keys').indexOf(receivableKey) < 0);
            }
            if ($option.is(':selected') && !$option.prop('disabled')) {
              $selectedMusician = $option;
            }
          });
          if ($selectedMusician) {
            const musicianData = $selectedMusician.data();
            const $amountInput = findByName($container, ppAmountName);
            if (!(+$amountInput.val()) || $amountInput.hasClass('auto-filled')) {
              const value = musicianData.values?.[receivableKey];
              if (value) {
                $amountInput.val(value);
                $amountInput.addClass('auto-filled');
              }
            }
          }
          const $subjectInput = findByName($container, ppSubjectName);
          if ($subjectInput.val() === '' || $subjectInput.hasClass('auto-filled')) {
            const $receivableOption = SelectUtils.optionByValue($this, receivableKey);
            const receivableText = $receivableOption.text();
            $subjectInput.val(receivableText);
            $subjectInput.addClass('auto-filled');
          }
        } else {
          $musiciansOptions.prop('disabled', false);
        }
        SelectUtils.refreshWidget($musicians);
      });

    $container
      .on('change', '[name="' + ppAmountName + '"]', function(event) {
        $(this).removeClass('auto-filled');
      });

    $container
      .on('change', '[name="' + ppSubjectName + '"]', function(event) {
        $(this).removeClass('auto-filled');
      });

    $container
      .on('change', 'select.payment-id', function(event) {
        const $this = $(this);

        // deselect action option
        const $actionOption = SelectUtils.optionByValue($this, -1);
        if ($actionOption.is(':selected')) {
          $actionOption.prop('selected', false);
          SelectUtils.refreshWidget($this);

          let musicianIds = [];
          let projectIds = [];
          $this.find('option').each(function() {
            const $option = $(this);
            if ($option.is($actionOption)) {
              return;
            }
            const optionData = $option.data('data');
            musicianIds.push(optionData.musicianId);
            projectIds.push(optionData.projectId);
          });
          musicianIds = [...new Set(musicianIds)];
          projectIds = [...new Set(projectIds)];

          if (projectIds.length > 1) {
            Dialogs.alert(
              t(appName, 'Too many Projects'),
              t(appName, 'Currently merging composite-payments for different projects ({projects}) is not supported, sorry.',
                { projects: projectIds.join(', ') }),
              false, true);
          }
          if (musicianIds.length > 1) {
            Dialogs.alert(
              t(appName, 'Too many Musicians'),
              t(appName, 'Internal error: splits of composite-payments cannot belong to different musicians ({musicians}).',
                { musicians: musicianIds.join(', ') }),
              false, true);
          }
          const projectId = projectIds[0];
          const musicianId = musicianIds[0];

          const recordId = $actionOption.data('data').recordId;
          const compositePaymentId = recordId.id;

          projectPaymentPopup(selector, {
            projectId,
            musicianId,
            [pmeData('id')]: compositePaymentId,
            [pmeData('Musicians:id')]: musicianId,
            [pmeSys('rec')]: recordId,
            [pmeSys('groupby_rec')]: { id: compositePaymentId, ['ProjectPayments' + pageRenderer.masterFieldSuffix]: 0 },
          });

          return false;
        }
        return false;
      });

    // upload supporting document(s)
    const musicianId = findByName($container, pmeData('Musicians:id')).val();
    $container
      .find('tr.supporting-document td.pme-value .file-upload-row')
      .each(function() {
        initFileUploadRow.call(
          this,
          -1, // projectId
          musicianId,
          resizeCB, {
            upload: 'documents/finance/' + template + '/upload',
            delete: 'documents/finance/' + template + '/delete',
          });
        const ambientContainerSelector = pmeParameters?.tableOptions?.ambientContainerSelector;
        if (ambientContainerSelector) {
          $(this).on('pme:upload-done pme:upload-deleted', (event) => {
            event.stopImmediatePropagation();
            $(ambientContainerSelector).trigger('pmedialog:changed');
            PHPMyEdit.submitOuterFormNoThrow(ambientContainerSelector);
          });
        }
      });

    $container
      .on('change', 'select.project-balance-documents', function(event) {
        const $this = $(this);
        const $option = SelectUtils.selectedOptions($this);
        const $cell = $this.closest(pmeValueSelector);
        const $filesAppAnchor = $cell.find('.open-parent');
        const filesAppLink = $filesAppAnchor.data('parent-link');
        if ($option.length > 0) {
          $filesAppAnchor.attr('href', filesAppLink + '/' + $option.data('data'));
        } else {
          $filesAppAnchor.attr('href', filesAppLink);
        }
        return false;
      });

  } // reason === 'dialogOpen'

  const tableOptions = pmeParameters.tableOptions || {};
  if (tableOptions.ambientContainerSelector) {

    const $pmeForm = (pmeParameters.reason === 'dialogClose')
      ? $(pmeParameters.htmlResponse).find(pmeFormSelector)
      : $container.find(pmeFormSelector);

    const paymentsRowTagName = pmeData('ProjectPayments:row_tag');
    const rowTag = findByName($pmeForm, paymentsRowTagName).val();

    // Make sure the current payment is present in the "master"
    // form. Otherwise a form submit would delete the current payment
    // again.
    if (rowTag && !isCompositeRow(rowTag)) {
      const $ambientContainer = $(tableOptions.ambientContainerSelector);
      const $ambientForm = $ambientContainer.find(pmeFormSelector);
      const paymentsIdName = pmeData('ProjectPayments:id[]');
      const $paymentOption = findByName($ambientContainer, paymentsIdName).find('option[value="' + rowTag + '"]');
      if ($paymentOption.length === 0) {
        $ambientForm.append('<input type="hidden" name="' + paymentsIdName + '" value="' + rowTag + '"/>');
        const $amountInput = findByName($ambientForm, ppAmountName);
        $amountInput.val($amountInput.val() + ',' + rowTag + ':' + findByName($pmeForm, ppAmountName).val());
        const $subjectInput = findByName($ambientForm, ppSubjectName);
        $subjectInput.val($subjectInput.val() + ',' + rowTag + ':' + findByName($pmeForm, ppSubjectName).val());
      }
    }
  }

  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {

    const container = PHPMyEdit.container();

    if (!container.hasClass(template)) {
      return;
    }

    ready(container, { reason: 'dialogOpen' }, function() {});
  });

};

export {
  backgroundDecryption,
  ready,
  documentReady,
};
