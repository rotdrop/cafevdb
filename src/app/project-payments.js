/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { appName } from './app-info.js';
import * as CAFEVDB from './cafevdb.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import * as Page from './page.js';
import * as Dialogs from './dialogs.js';
import initFileUploadRow from './pme-file-upload-row.js';
import fileDownload from './file-download.js';
import { pageRenderer } from './pme-state.js';
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

require('project-payments.scss');
require('project-participant-fields-display.scss');

const isCompositeRow = function(rowTag) {
  return rowTag.startsWith('0;');
};

const findByName = function($container, name) {
  return $($container).find('[name="' + name + '"]').filter('input, select, textarea');
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

  const template = 'project-payments';
  const tableOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: template + '-dialog',
    template,
    templateRenderer: Page.templateRenderer(template),
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
  PHPMyEdit.tableDialogOpen($.extend({}, tableOptions, post));
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

const actionMenu = function(containerSel) {
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);

  $container.find('.menu-actions.dropdown-container .menu-action').on('click', function(event) {
    console.info('UNINPLEMENTED ACTION CALLBACK', $(this));
    return false;
  });

  $container
    .off('pme:contextmenu', 'tr.' + pmeToken('row'))
    .on('pme:contextmenu', 'tr.' + pmeToken('row'), function(event, originalEvent, databaseIdentifier) {
      console.info('CONTEXTMENU EVENT', $(this), event, originalEvent, databaseIdentifier);

      const $contentTarget = $(originalEvent.target).closest('.dropdown-content');
      console.info('TARGET', $contentTarget);
      if ($contentTarget.length > 0) {
        // use standard context menu inside dropdown
        return;
      }

      const $row = $(this);
      const $form = $row.closest(pmeFormSelector);
      const $actionMenuContainer = $form.is('.' + pmeToken('list')) ? $row : $row.closest(pmeFormSelector);
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

    $container
      .on('contextmenu', 'table.pme-main tr.composite-payment.first td', function(event) {
        if (event.ctrlKey) {
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
          const allowedOptionKeys = $musicianOption.data('data').split(',');
          $receivableOptions.each(function(index) {
            const $option = $(this);
            if ($option.val() !== '') {
              $option.prop('disabled', allowedOptionKeys.indexOf($option.val()) < 0);
            }
          });
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
          $musiciansOptions.each(function(index) {
            const $option = $(this);
            if ($option.val() !== '') {
              $option.prop('disabled', $option.data('data').split(',').indexOf(receivableKey) < 0);
            }
          });
        } else {
          $musiciansOptions.prop('disabled', false);
        }
        SelectUtils.refreshWidget($musicians);
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
            upload: 'finance/payments/documents/upload',
            delete: 'finance/payments/documents/delete',
          });
        const ambientContainerSelector = pmeParameters?.tableOptions?.ambientContainerSelector;
        if (ambientContainerSelector) {
          $(this).on('pme:upload-done pme:upload-deleted', (event) => {
            event.stopImmediatePropagation();
            $(ambientContainerSelector).trigger('pmedialog:changed');
            PHPMyEdit.submitOuterForm(ambientContainerSelector);
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
        const amountName = pmeData('ProjectPayments:amount');
        const subjectName = pmeData('ProjectPayments:subject');
        $ambientForm.append('<input type="hidden" name="' + paymentsIdName + '" value="' + rowTag + '"/>');
        const amountInput = findByName($ambientForm, amountName);
        amountInput.val(amountInput.val() + ',' + rowTag + ':' + findByName($pmeForm, amountName).val());
        const subjectInput = findByName($ambientForm, subjectName);
        subjectInput.val(subjectInput.val() + ',' + rowTag + ':' + findByName($pmeForm, subjectName).val());
      }
    }
  }

  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {

    const container = PHPMyEdit.container();

    if (!container.hasClass('project-payments')) {
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
