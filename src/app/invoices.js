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

import $ from './jquery.js';
import { appName, cloudUser } from './config.js';
import generateAppUrl from './generate-url.js';
import * as CAFEVDB from './cafevdb.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import { templateRenderer } from './template-renderer.js';
import * as Dialogs from './dialogs.js';
import initFileUploadRow from './pme-file-upload-row.js';
import ajaxDownload from './file-download.js';
import { pageRenderer } from './pme-state.js';
import { showError, /* showSuccess, showInfo, TOAST_DEFAULT_TIMEOUT, */ TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs';
import setBusyIndicators from './busy-indicators.js';
import { filename } from './path.js';
import {
  valueSelector as pmeValueSelector,
  sys as pmeSys,
  data as pmeData,
  token as pmeToken,
  formSelector as pmeFormSelector,
} from './pme-selectors.js';
import {
  lazyDecrypt,
  reject as rejectDecryptionPromise,
  promise as decryptionPromise,
} from './lazy-decryption.js';
import formatDate from '../util/formatDate.js';

require('./jquery-readonly.js');
require('invoices.scss');
require('project-participant-fields-display.scss');

const isCompositeRow = function(rowTag) {
  return rowTag.startsWith('0;');
};

const findByName = function($container, name) {
  return $($container).find('[name="' + name + '"]').filter('input, select, textarea');
};

const iiAmountName = pmeData('InvoiceItems:amount');
const iiSubjectName = pmeData('InvoiceItems:subject');
const iDueDateName = pmeData('due_date');

const templateName = filename(__filename);

/**
 * Generate a popup in order to add a new InvoiceItem entity which
 * is always subordinate to a Invoice entity.
 *
 * @param {string} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const invoiceItemPopup = function(containerSel, post) {
  // Prepare the data-array for PHPMyEdit.tableDialogOpen(). The
  // instrumentation numbers are somewhat nasty and require too
  // many options.

  const template = templateName;
  const tableOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: templateRenderer(template),
    Table: 'Invoices',
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

const actionMenu = function(containerSel) {
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);
  const itemSelector = '.menu-actions.dropdown-container .dropdown-item';

  $container.on('click', itemSelector + '.disabled', function(event) {
    event.preventDefault();
    event.stopImmediatePropagation();
    return false;
  });
  $container.on('click', itemSelector, function(event) {
    const $this = $(this);
    const $menu = $this.closest('.menu-actions.dropdown-container');
    const menuData = $menu.data();

    const postData = {
      senderId: cloudUser.uid,
      operation: 'download',
      invoiceIds: [menuData.invoiceId],
      projectId: menuData.projectId,
    };

    const operation = $this.data('operation');

    switch (operation) {
    case 'invoice:download':
      fileDownload(
        generateAppUrl('documents/mail-merge'), {
          templateName: 'invoice',
          ...postData,
        },
        $menu,
      );
      break;
    case 'invoice:email':
      showError(t(appName, 'Unimplemented operation: {operation}', { operation }), { timeout: TOAST_PERMANENT_TIMEOUT });
      break;
    case 'invoice:download-data':
      fileDownload(
        generateAppUrl('documents/mail-merge'), {
          templateName: 'invoice',
          ...postData,
          operation: 'dataset',
        },
        $menu,
      );
      break;
    default:
      showError(t(appName, 'Unknown operation: {operation}', { operation }), { timeout: TOAST_PERMANENT_TIMEOUT });
      break;
    }
    // return false;
  });
  const $form = $container.find(pmeFormSelector);
  const listMode = $form.is('.' + pmeToken('list'));

  $container
    .off('pme:contextmenu', 'tr.' + pmeToken('row'))
    .on('pme:contextmenu', 'tr.' + pmeToken('row'), function(event, originalEvent, databaseIdentifier) {
      const $contentTarget = $(originalEvent.target).closest('.dropdown-content');
      if ($contentTarget.length > 0) {
        // use standard context menu inside dropdown
        return;
      }

      const $row = $(this);
      const $actionMenuContainer = listMode ? $row.closest('tbody').find('.invoice.first') : $form;
      const $actionMenu = $actionMenuContainer.find('.menu-actions.dropdown-container').first();

      if ($actionMenu.length === 0) {
        return;
      }

      const $actionMenuToggle = $actionMenu.find('.action-menu-toggle');
      const $actionMenuContent = $actionMenu.find('.dropdown-content');

      originalEvent.preventDefault();
      originalEvent.stopImmediatePropagation();

      $actionMenuContent.css({
        position: 'fixed',
        left: originalEvent.originalEvent.clientX,
        top: originalEvent.originalEvent.clientY,
      });
      $actionMenu.addClass('context-menu');
      $actionMenuToggle.trigger('click');

      return false;
    });
};

const ready = function(selector, pmeParameters, resizeCB) {

  const $container = $(selector);

  if (pmeParameters.reason !== 'dialogClose') {
    backgroundDecryption($container);
  }

  if (pmeParameters.reason === 'dialogOpen') {

    actionMenu($container);

    // AJAX download support
    $container
      .on('click', 'a.download-link.ajax-download', function(event) {
        const $this = $(this);
        fileDownload($this.attr('href'));
        return false;
      });

    console.info('INSTALL CONTEXT MENU', { $container, selector: 'table.pme-main tr.invoice.first td' });

    $container
      .on('contextmenu', 'table.pme-main tr.invoice.first td', function(event) {
        console.info('CONTEXT MENU EVENT');
        if (event.ctrlKey || $(event.target).closest('.dropdown-content').length > 0) {
          return; // let the user see the normal context menu
        }
        const $row = $(this).closest('tr.invoice.first');
        event.stopImmediatePropagation();
        $row.toggleClass('following-hidden');
        $row.find('input.expanded-marker').val($row.hasClass('following-hidden') ? 0 : 1);
        return false;
      });

    $container
      .on('change', 'select.debitor-id', function(event) {
        const $this = $(this);
        const debitorId = $this.val();
        const $receivables = $container.find('select.receivable');
        const $receivableOptions = $receivables.find('option');
        if (debitorId !== '') {
          let $selectedReceivable;
          const $debitorOption = SelectUtils.optionByValue($this, debitorId);
          const debitorData = $debitorOption.data();
          const allowedOptionKeys = debitorData.keys;
          $receivableOptions.each(function(index) {
            const $option = $(this);
            if ($option.val() !== '') {
              $option.prop('disabled', allowedOptionKeys.indexOf($option.val()) < 0);
            }
            if ($option.is(':selected') && !$option.prop('disabled')) {
              $selectedReceivable = $option;
            }
          });
          const receivableKey = $receivables.val();
          if (receivableKey) {
            const $amountInput = findByName($container, iiAmountName);
            if (!(+$amountInput.val()) || $amountInput.hasClass('auto-filled')) {
              const value = debitorData.values?.[receivableKey] || '';
              $amountInput.val(value);
              $amountInput.addClass('auto-filled');
            }
            const $dueDateInput = findByName($container, iDueDateName);
            if (!($dueDateInput.val()) || $dueDateInput.hasClass('auto-filled')) {
              $dueDateInput.val(formatDate($selectedReceivable.data('data').dueDate));
              $dueDateInput.addClass('auto-filled');
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
        const $debitors = $container.find('select.debitor-id');
        const $debitorsOptions = $debitors.find('option');
        if (receivableKey !== '') {
          let $selectedDebitor;
          const $receivableOption = SelectUtils.optionByValue($this, receivableKey);
          $debitorsOptions.each(function(index) {
            const $option = $(this);
            if ($option.val() !== '') {
              $option.prop('disabled', $option.data('keys').indexOf(receivableKey) < 0);
            }
            if ($option.is(':selected') && !$option.prop('disabled')) {
              $selectedDebitor = $option;
            }
          });
          if ($selectedDebitor) {
            const debitorData = $selectedDebitor.data();
            const $amountInput = findByName($container, iiAmountName);
            if (!(+$amountInput.val()) || $amountInput.hasClass('auto-filled')) {
              const value = debitorData.values?.[receivableKey];
              if (value) {
                $amountInput.val(value);
                $amountInput.addClass('auto-filled');
              }
            }
          }
          const $dueDateInput = findByName($container, iDueDateName);
          if (!($dueDateInput.val()) || $dueDateInput.hasClass('auto-filled')) {
            $dueDateInput.val(formatDate($receivableOption.data('data').dueDate));
            $dueDateInput.addClass('auto-filled');
          }
          const $subjectInput = findByName($container, iiSubjectName);
          if ($subjectInput.val() === '' || $subjectInput.hasClass('auto-filled')) {
            const $receivableOption = SelectUtils.optionByValue($this, receivableKey);
            const receivableText = $receivableOption.text();
            $subjectInput.val(receivableText);
            $subjectInput.addClass('auto-filled');
          }
        } else {
          $debitorsOptions.prop('disabled', false);
        }
        SelectUtils.refreshWidget($debitors);
      });

    $container
      .on('change', '[name="' + iiAmountName + '"]', function(event) {
        $(this).removeClass('auto-filled');
      });

    $container
      .on('change', '[name="' + iiSubjectName + '"]', function(event) {
        $(this).removeClass('auto-filled');
      });

    $container
      .on('change', 'select.invoice-item-id', function(event) {
        const $this = $(this);

        // deselect action option
        const $actionOption = SelectUtils.optionByValue($this, -1);
        if ($actionOption.is(':selected')) {
          $actionOption.prop('selected', false);
          SelectUtils.refreshWidget($this);

          let debitorIds = [];
          let projectIds = [];
          $this.find('option').each(function() {
            const $option = $(this);
            if ($option.is($actionOption)) {
              return;
            }
            const optionData = $option.data('data');
            debitorIds.push(optionData.debitorId);
            projectIds.push(optionData.projectId);
          });
          debitorIds = [...new Set(debitorIds)];
          projectIds = [...new Set(projectIds)];

          if (projectIds.length > 1) {
            Dialogs.alert(
              t(appName, 'Too many Projects'),
              t(appName, 'Currently merging invoices for different projects ({projects}) is not supported, sorry.',
                { projects: projectIds.join(', ') }),
              false, true);
          }
          if (debitorIds.length > 1) {
            Dialogs.alert(
              t(appName, 'Too many Debitors'),
              t(appName, 'Internal error: splits of invoices cannot belong to different debitors ({debitors}).',
                { debitors: debitorIds.join(', ') }),
              false, true);
          }
          const projectId = projectIds[0];
          const debitorId = debitorIds[0];

          const recordId = $actionOption.data('data').recordId;
          const invoiceId = recordId.id;

          invoiceItemPopup(selector, {
            projectId,
            debitorId,
            [pmeData('id')]: invoiceId,
            [pmeData('Musicians:id')]: debitorId,
            [pmeSys('rec')]: recordId,
            [pmeSys('groupby_rec')]: { id: invoiceId, ['InvoiceItems' + pageRenderer.masterFieldSuffix]: 0 },
          });

          return false;
        }
        return false;
      });

    // upload supporting document(s)
    const debitorId = findByName($container, pmeData('debitor_id')).val();
    $container
      .find('tr.written-invoice td.pme-value .file-upload-row')
      .each(function() {
        initFileUploadRow.call(
          this,
          -1, // projectId
          debitorId,
          resizeCB, {
            upload: 'documents/finance/' + templateName + '/upload',
            delete: 'documents/finance/' + templateName + '/delete',
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

    const paymentsRowTagName = pmeData('InvoiceItems:row_tag');
    const rowTag = findByName($pmeForm, paymentsRowTagName).val();

    // Make sure the current payment is present in the "master"
    // form. Otherwise a form submit would delete the current payment
    // again.
    if (rowTag && !isCompositeRow(rowTag)) {
      const $ambientContainer = $(tableOptions.ambientContainerSelector);
      const $ambientForm = $ambientContainer.find(pmeFormSelector);
      const paymentsIdName = pmeData('InvoiceItems:id[]');
      const $paymentOption = findByName($ambientContainer, paymentsIdName).find('option[value="' + rowTag + '"]');
      if ($paymentOption.length === 0) {
        $ambientForm.append('<input type="hidden" name="' + paymentsIdName + '" value="' + rowTag + '"/>');
        const $amountInput = findByName($ambientForm, iiAmountName);
        $amountInput.val($amountInput.val() + ',' + rowTag + ':' + findByName($pmeForm, iiAmountName).val());
        const $subjectInput = findByName($ambientForm, iiSubjectName);
        $subjectInput.val($subjectInput.val() + ',' + rowTag + ':' + findByName($pmeForm, iiSubjectName).val());
      }
    }
  }

  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {

    const container = PHPMyEdit.container();

    if (!container.hasClass(templateName)) {
      console.info('IGNORING WRONG TEMPLATE', { templateName });
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
