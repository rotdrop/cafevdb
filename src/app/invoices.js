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
import { appName } from './config.js';
import * as CAFEVDB from './cafevdb.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import { templateRenderer } from './template-renderer.js';
import * as Dialogs from './dialogs.js';
import initFileUploadRow from './pme-file-upload-row.js';
import ajaxDownload from './file-download.js';
import { pageRenderer } from './pme-state.js';
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
import {
  emit as asyncEmit,
  getEmitResult,
  subscribe as asyncSubscribe,
} from '../services/async-event-bus.ts';
import { INVOICE_ACTIONS_MENU } from '../mountable-component-names.ts';
import * as BusEvents from '../event-bus-events.ts';

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

asyncSubscribe(BusEvents.INVOICE_POPUP, async (event) => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE);
  await overviewPopup(PHPMyEdit.selector(), event);
  asyncEmit(BusEvents.POP_BUSY_STATE);
});

const overviewPopup = async function(containerSel, data) {
  const tableOptions = {
    ambientContainerSelector: containerSel,
    templateName,
    templateRenderer: templateRenderer(templateName),
    // Now special options for the dialog popup
    initialViewOperation: true,
    initialName: pmeSys('operation'),
    initialValue: 'View',
    reloadName: pmeSys('operation'),
    reloadValue: 'View',
    [pmeSys('rec')]: { id: data.invoiceId },
    [pmeSys('groupby_rec')]: {
      id: data.invoiceId,
      // eslint-disable-next-line camelcase
      InvoiceItems__master_key_: '0;' + data.invoiceId,
    },
    [pmeSys('mrec_rec')]: {
      id: data.invoiceId,
      // eslint-disable-next-line camelcase
      InvoiceItems__master_key_: '0;' + data.invoiceId,
    },
    projectId: data.projectId,
    projectName: data.projectName,
    modalDialog: true,
    modified: false,
  };
  await PHPMyEdit.tableDialogOpen(tableOptions);
};

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
    console.debug('MAX DECRYPTION JOBS HANDLED', maxJobs);
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

const actionMenu = async function($container) {

  const generateVueMenu = async ($actionMenu) => {
    const propsData = { ...$actionMenu.data('actionMenu') };
    propsData.enableOverviewItem = $container.find(pmeFormSelector).hasClass(pmeToken('list'));
    const vueMenu = await getEmitResult(
      asyncEmit(BusEvents.GET_VUE_COMPONENT, {
        name: INVOICE_ACTIONS_MENU,
        propsData,
      }),
    );
    const vueComponents = $container.data('vueComponents') || [];
    if (vueComponents.length === 0) {
      $container.data('vueComponents', vueComponents);
    }
    vueComponents.push(vueMenu);

    $actionMenu.data('vueMenu', vueMenu);
    await vueMenu.$mount($actionMenu.find('.vue-mount-point')[0]);
    return vueMenu;
  };

  const actionTriggerSelector = `.vue-action-menu-placeholder.${templateName} button.vue-mount-point`;
  $container
    .off('click', actionTriggerSelector)
    .on('click', actionTriggerSelector, async function(event) {

      $.fn.cafevTooltip.hide();

      const $actionMenu = $(this).parent();
      if ($actionMenu.data('vueMenu')) {
        // the menu already exists, just let it do its work
        return;
      }

      // otherwise intercept the event and mount the menu
      event.preventDefault();
      event.stopImmediatePropagation();

      const vueMenu = await generateVueMenu($actionMenu);
      const invoiceId = $actionMenu.data('actionMenu').invoiceId;

      asyncEmit(BusEvents.INVOICE_ACTIONS, {
        open: false,
        invoiceId: -invoiceId,
      });
      vueMenu.openMenu();

      return false;
    });

  $container
    .off('pme:contextmenu', 'tr.' + pmeToken('row'))
    .on('pme:contextmenu', 'tr.' + pmeToken('row'), async function(event, originalEvent, databaseIdentifier) {
      console.debug('CONTEXTMENU EVENT', $(this), event, originalEvent, databaseIdentifier);

      const $row = $(this);
      const $form = $row.closest(pmeFormSelector);
      let $actionMenuContainer;
      if ($form.is('.' + pmeToken('list'))) {
        $actionMenuContainer = $row.hasClass('following') ? $row.prevAll('.first').first() : $row;
      } else {
        $actionMenuContainer = $row.closest(pmeFormSelector);
      }
      const $actionMenu = $actionMenuContainer.find('.vue-action-menu-placeholder.' + templateName).first();

      if ($actionMenu.length === 0) {
        return;
      }

      originalEvent.preventDefault();
      originalEvent.stopImmediatePropagation();

      const vueMenu = $actionMenu.data('vueMenu') || await generateVueMenu($actionMenu);
      const invoiceId = $actionMenu.data('actionMenu').invoiceId;

      if (vueMenu.isOpen()) {
        vueMenu.closeMenu();
      } else {
        asyncEmit(BusEvents.INVOICE_ACTIONS, {
          open: false,
          invoiceId: -invoiceId,
        });
        vueMenu.openMenu(
          originalEvent.originalEvent.clientX,
          originalEvent.originalEvent.clientY,
        );
      }

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
        fileDownload($this.attr('href'), undefined, $this);
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
      console.debug('IGNORING WRONG TEMPLATE', { templateName });
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
