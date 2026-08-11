/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { EventArgs } from '@rotdrop/async-nextcloud-event-bus';
import type { TableDialogCallbackData } from './pme-state.ts';

import { translate as t } from '@nextcloud/l10n';
import { PAGE_RENDERER } from '../../build/ts-types/php-modules/PageRenderer/DataConstants.ts';
import { TEMPLATE as template } from '../../build/ts-types/php-modules/PageRenderer/Invoices.ts';
import * as BusEvents from '../event-bus-events.ts';
import { INVOICE_ACTIONS_MENU } from '../mountable-component-names.ts';
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
} from '../services/async-event-bus.ts';
import formatDate from '../util/formatDate.ts';
import setBusyIndicators from './busy-indicators.ts';
import * as CAFEVDB from './cafevdb.ts';
import { appName } from './config.ts';
import * as Dialogs from './dialogs.ts';
import ajaxDownload from './file-download.ts';
import $, { jq } from './jquery.ts';
import {
  promise as decryptionPromise,
  lazyDecrypt,
  reject as rejectDecryptionPromise,
} from './lazy-decryption.ts';
import initFileUploadRow from './pme-file-upload-row.ts';
import {
  data as pmeData,
  formSelector as pmeFormSelector,
  sys as pmeSys,
  valueSelector as pmeValueSelector,
} from './pme-selectors.ts';
import * as PHPMyEdit from './pme.ts';
import * as SelectUtils from './select-utils.ts';
import { templateRenderer } from './template-renderer.ts';
import actionMenu from './vue-action-menu.ts';

import './jquery-readonly.ts';
import 'invoices.scss';
import 'project-participant-fields-display.scss';

const isCompositeRow = (rowTag: string) => rowTag.startsWith('0;');

const findByName = ($container: JQuery, name: string) =>
  $($container).find('[name="' + name + '"]').filter('input, select, textarea');

const iiAmountName = pmeData('InvoiceItems:amount');
const iiSubjectName = pmeData('InvoiceItems:subject');
const iDueDateName = pmeData('due_date');

const overviewPopup = (containerSel: string, data: EventArgs[typeof BusEvents.LEGACY_RECORD_POPUP]) =>
  PHPMyEdit.tableDialogOpen({
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: template + '-overview-dialog',
    template,
    templateRenderer: templateRenderer(template),
    // Now special options for the dialog popup
    initialViewOperation: true,
    initialName: pmeSys('operation'),
    initialValue: 'View',
    reloadName: pmeSys('operation'),
    reloadValue: 'View',
    [pmeSys('rec')]: { id: data.entityId },
    [pmeSys('groupby_rec')]: {
      id: data.entityId,

      InvoiceItems__master_key_: '0;' + data.entityId,
    },
    [pmeSys('mrec_rec')]: {
      id: data.entityId,

      InvoiceItems__master_key_: '0;' + data.entityId,
    },
    projectId: data.projectId,
    projectName: data.projectName,
    modalDialog: true,
    modified: false,
  });

asyncSubscribe(BusEvents.LEGACY_RECORD_POPUP, async (event) => {
  if (event.template !== template) {
    return;
  }
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  await overviewPopup(PHPMyEdit.selector(), event);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

type ItemPopupData = {
  projectId: number;
  debitorId: number;
  [key: string]: number|string|Record<string, number|string>;
};

/**
 * Generate a popup in order to add a new InvoiceItem entity which
 * is always subordinate to a Invoice entity.
 *
 * @param containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param post Arguments object.
 * @param post.projectName Name of the project.
 * @param post.projectId Database id of the project.
 */
const invoiceItemPopup = (containerSel: string, post: ItemPopupData) =>
  PHPMyEdit.tableDialogOpen({
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: templateRenderer(template),
    projectId: post.projectId,
    projectName: post.projectName as string,
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
  }, post);

const backgroundDecryption = function(container: string|JQuery) {
  const $container = PHPMyEdit.container(container);
  rejectDecryptionPromise();
  console.time('DECRYPTION PROMISE');
  decryptionPromise.done((maxJobs) => {
    console.timeEnd('DECRYPTION PROMISE');
    console.debug('MAX DECRYPTION JOBS HANDLED', maxJobs);
  });
  lazyDecrypt($container);
};

const ready = function(selector: string|JQuery, pmeParameters: Partial<TableDialogCallbackData>, resizeCB: () => void = () => {}) {

  const $container = jq(selector);

  if (pmeParameters.reason !== 'dialogClose') {
    backgroundDecryption($container);
  }

  if (pmeParameters.reason === 'dialogOpen') {

    actionMenu($container, template, INVOICE_ACTIONS_MENU);

    // AJAX download support
    $container
      .on('click', 'a.download-link.ajax-download', function(this: HTMLAnchorElement, _event) {
        const $this = $(this);
        const $pmeContainer = $this.closest(pmeFormSelector);
        ajaxDownload($this.attr('href')!, undefined, {
          always() {
            setBusyIndicators(false, $pmeContainer, false);
          },
          setup() {
            setBusyIndicators(true, $pmeContainer, false);
          },
        });
        return false;
      });

    console.debug('INSTALL CONTEXT MENU', { $container, selector: 'table.pme-main tr.invoice.first td' });

    $container
      .on('contextmenu', 'table.pme-main tr.invoice.first td', function(event) {
        console.debug('CONTEXT MENU EVENT');
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
      .on('change', 'select.debitor-id', function(this: HTMLSelectElement, _event) {
        const $this = $(this);
        const debitorId = $this.val() as undefined|string;
        const $receivables = $container.find('select.receivable') as JQuery<HTMLSelectElement>;
        const $receivableOptions = $receivables.find('option');
        if (debitorId) {
          let $selectedReceivable: JQuery<HTMLOptionElement>;
          const $debitorOption = SelectUtils.optionByValue($this, debitorId);
          const debitorData = $debitorOption.data();
          const allowedOptionKeys = debitorData.keys;
          $receivableOptions.each(function() {
            const $option = $(this);
            if ($option.val() !== '') {
              $option.prop('disabled', allowedOptionKeys.indexOf($option.val()) < 0);
            }
            if ($option.is(':selected') && !$option.prop('disabled')) {
              $selectedReceivable = $option;
            }
          });
          const receivableKey = $receivables.val()! as string;
          if (receivableKey) {
            const $amountInput = findByName($container, iiAmountName);
            if (!(+$amountInput.val()!) || $amountInput.hasClass('auto-filled')) {
              const value = debitorData.values?.[receivableKey] || '';
              $amountInput.val(value);
              $amountInput.addClass('auto-filled');
            }
            const $dueDateInput = findByName($container, iDueDateName);
            if (!($dueDateInput.val()) || $dueDateInput.hasClass('auto-filled')) {
              $dueDateInput.val(formatDate($selectedReceivable!.data('data').dueDate));
              $dueDateInput.addClass('auto-filled');
            }
          }
        } else {
          $receivableOptions.prop('disabled', false);
        }
        SelectUtils.refreshWidget($receivables);
      });

    $container
      .on('change', 'select.receivable', function(this: HTMLSelectElement, _event) {
        const $this = $(this);
        const receivableKey = $this.val() as undefined|string;
        const $debitors = $container.find('select.debitor-id') as JQuery<HTMLSelectElement>;
        const $debitorsOptions = $debitors.find('option');
        if (receivableKey) {
          let $selectedDebitor: undefined|JQuery<HTMLOptionElement>;
          const $receivableOption = SelectUtils.optionByValue($this, receivableKey);
          $debitorsOptions.each(function() {
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
            if (!(+$amountInput.val()!) || $amountInput.hasClass('auto-filled')) {
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
      .on('change', '[name="' + iiAmountName + '"]', function(this: JQuery, _event) {
        $(this).removeClass('auto-filled');
      });

    $container
      .on('change', '[name="' + iiSubjectName + '"]', function(this: JQuery, _event) {
        $(this).removeClass('auto-filled');
      });

    $container
      .on('change', 'select.invoice-item-id', function(this: JQuery<HTMLSelectElement>, _event) {
        const $this = $(this);

        // deselect action option
        const $actionOption = SelectUtils.optionByValue($this, -1);
        if ($actionOption.is(':selected')) {
          $actionOption.prop('selected', false);
          SelectUtils.refreshWidget($this);

          let debitorIds: number[] = [];
          let projectIds: number[] = [];
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
              t(appName, 'Currently merging invoices for different projects ({projects}) is not supported, sorry.', { projects: projectIds.join(', ') }),
              undefined,
              false,
              true,
            );
          }
          if (debitorIds.length > 1) {
            Dialogs.alert(
              t(appName, 'Too many Debitors'),
              t(appName, 'Internal error: splits of invoices cannot belong to different debitors ({debitors}).', { debitors: debitorIds.join(', ') }),
              undefined,
              false,
              true,
            );
          }
          const projectId = projectIds[0];
          const debitorId = debitorIds[0];

          const recordId = $actionOption.data('data').recordId;
          const invoiceId = recordId.id;

          invoiceItemPopup(PHPMyEdit.selector(selector), {
            projectId,
            debitorId,
            [pmeData('id')]: invoiceId,
            [pmeData('Musicians:id')]: debitorId,
            [pmeSys('rec')]: recordId,
            [pmeSys('groupby_rec')]: { id: invoiceId, [`InvoiceItems${PAGE_RENDERER.masterFieldSuffix}`]: 0 },
          });

          return false;
        }
        return false;
      });

    // upload supporting document(s)
    const debitorId = parseInt(findByName($container, pmeData('debitor_id')).val()! as string);
    ($container
      .find('tr.written-invoice td.pme-value .file-upload-row') as JQuery<HTMLTableRowElement>)
      .each(function() {
        initFileUploadRow.call(
          this,
          -1, // projectId
          debitorId,
          resizeCB,
          {
            upload: 'documents/finance/' + template + '/upload',
            delete: 'documents/finance/' + template + '/delete',
          },
        );
        const ambientContainerSelector = pmeParameters?.tableDialogOptions?.ambientContainerSelector;
        if (ambientContainerSelector) {
          $(this).on('pme:upload-done pme:upload-deleted', (event) => {
            event.stopImmediatePropagation();
            $(ambientContainerSelector).trigger('pmedialog:changed');
            PHPMyEdit.submitOuterFormNoThrow(ambientContainerSelector);
          });
        }
      });

    $container
      .on('change', 'select.project-balance-documents', function(this: HTMLSelectElement, _event) {
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

  const tableOptions = pmeParameters.tableDialogOptions;
  if (tableOptions?.ambientContainerSelector) {

    const $pmeForm = (pmeParameters.reason === 'dialogClose')
      ? $(pmeParameters.htmlResponse!).find(pmeFormSelector)
      : $container.find(pmeFormSelector);

    const paymentsRowTagName = pmeData('InvoiceItems:row_tag');
    const rowTag = findByName($pmeForm, paymentsRowTagName).val()! as string;

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

  CAFEVDB.addReadyCallback(async () => {

    const $container = PHPMyEdit.container();

    if (!$container.hasClass(template)) {
      console.debug('IGNORING WRONG TEMPLATE', { template });
      return;
    }

    ready($container, { reason: 'dialogOpen' });
  });

};

export {
  backgroundDecryption,
  documentReady,
  ready,
};
