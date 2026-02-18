/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState } from './globals.ts';
import $, { isJQuerySelect, type JQuerySelect } from './jquery.ts';
import * as CAFEVDB from './cafevdb.ts';
import { templateRenderer } from './template-renderer.ts';
import * as PHPMyEdit from './pme.ts';
import initFileUploadRow from './pme-file-upload-row.ts';
import fileDownload from './file-download.ts';
import {
  inputSelector as pmeInputSelector,
  formSelector as pmeFormSelector,
  valueSelector as pmeValueSelector,
  selectInputSelector as pmeSelectInputSelector,
  inputClassSelector as pmeInputClassSelector,
} from './pme-selectors.ts';
import {
  refreshWidget as refreshSelectWidget,
  options as getSelectOptions,
  optionByValue as getSelectOptionByValue,
} from './select-utils.ts';
import { TEMPLATE as template } from '../../build/ts-types/php-modules/PageRenderer/DonationReceipts.ts';
import type { TableLoadCallback } from './pme-state.ts';

require('./jquery-readonly.ts');
require('project-participant-fields-display.scss');
require('donation-receipts.scss');

const pmeFormInit = (
  containerSel: Parameters<TableLoadCallback['callback']>[1],
  parameters: Parameters<TableLoadCallback['callback']>[2]|undefined,
  resizeCB: Parameters<TableLoadCallback['callback']>[3],
) => {
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);
  const $form = $container.find(pmeFormSelector) as JQuery<HTMLFormElement>;

  if (!PHPMyEdit.hasEditableData($form)) {
    // no need to do further work
    return;
  }

  $container
    .off('click', 'a.download-link.ajax-download')
    .on('click', 'a.download-link.ajax-download', function() {
      const $this = $(this);
      const post = $this.data('post');
      fileDownload($this.attr('href')!, post);
      return false;
    });

  // upload supporting document(s)
  ($container
    .find('tr.supporting-document ' + pmeValueSelector + ' .file-upload-row') as JQuery<HTMLTableRowElement>)
    .each(function() {
      initFileUploadRow.call(
        this,
        -1, // projectId
        -1, // musicianId,
        resizeCB,
        {
          upload: 'documents/finance/' + template + '/upload',
          delete: 'documents/finance/' + template + '/delete',
        });
      const ambientContainerSelector = parameters?.tableDialogOptions?.ambientContainerSelector;
      if (ambientContainerSelector) {
        $(this).on('pme:upload-done pme:upload-deleted', (event) => {
          event.stopImmediatePropagation();
          $(ambientContainerSelector).trigger('pmedialog:changed');
          PHPMyEdit.submitOuterFormNoThrow(ambientContainerSelector);
        });
      }
    });

  let inputLock = false;

  $container.on('change', pmeSelectInputSelector + '.musician-id', function() {
    // avoid ping-pong
    if (inputLock) {
      return false;
    }
    inputLock = true;
    const $this = $(this);
    const musicianId = +$this.val() || 0;
    const $musicianOption = getSelectOptionByValue($this, '' + musicianId);
    const projects = $musicianOption.data('projects') || [];
    console.info('MUSICIAN', $musicianOption, projects);

    const $projectSelect = $container.find(pmeSelectInputSelector + '.project-id') as JQuery<HTMLSelectElement>;
    const $projectOptions = getSelectOptions($projectSelect);
    const selectableProjects: JQuery<HTMLOptionElement>[] = [];
    $projectOptions.each(function() {
      const $projectOption = $(this);
      const projectId = +$projectOption.val()!;
      if (musicianId > 0 && projects.find((id: number) => id === projectId) === undefined) {
        $projectOption.prop('selected', false).prop('disabled', true);
      } else {
        $projectOption.prop('disabled', false);
        selectableProjects.push($projectOption);
      }
    });
    if (selectableProjects.length === 1) {
      $projectOptions.each(function() { $(this).prop('disabled', false); });
      selectableProjects[0].prop('selected', true);
    }
    refreshSelectWidget($projectSelect);

    const $paymentsSelect = $container.find(pmeSelectInputSelector + '.composite-payment-id') as JQuerySelect;
    const $paymentOptions = getSelectOptions($paymentsSelect);
    const selectablePayments: JQuery<HTMLOptionElement>[] = [];
    $paymentOptions.each(function() {
      const $paymentOption = $(this);
      const thisMusicianId = +$paymentOption.data('musicianId');
      if (musicianId > 0 && musicianId !== thisMusicianId) {
        $paymentOption.prop('disabled', true);
      } else {
        $paymentOption.prop('disabled', false);
        selectablePayments.push($paymentOption);
      }
    });
    if (selectablePayments.length === 1) {
      $paymentOptions.each(function() { $(this).prop('disabled', false); });
      selectablePayments[0].prop('selected', true);
      $container.find(pmeInputClassSelector() + '.composite-payment-subject').val(
        selectablePayments[0].data('subject'),
      );
      $form.toggleClass($form.data('selfTestFailure'), !selectablePayments[0].data('status'));
    }
    refreshSelectWidget($paymentsSelect);

    setTimeout(() => { inputLock = false; }, 0);

    return false;
  });

  $container.on('change', pmeSelectInputSelector + '.project-id', function() {
    // avoid ping-pong
    if (inputLock) {
      return false;
    }
    inputLock = true;

    const $this = $(this);
    const projectId = +$this.val() || 0;

    const $musicianInput = $container.find(pmeInputClassSelector() + '.musician-id');
    if (isJQuerySelect($musicianInput)) {
      const $musicianOptions = getSelectOptions($musicianInput);
      const selectableMusicians: JQuery<HTMLOptionElement>[] = [];
      $musicianOptions.each(function() {
        const $musicianOption = $(this);
        const projects: number[] = $musicianOption.data('projects') || [];
        if (projectId > 0 && projects.find((id) => id === projectId) === undefined) {
          $musicianOption.prop('selected', false).prop('disabled', true);
        } else {
          $musicianOption.prop('disabled', false);
          selectableMusicians.push($musicianOption);
        }
      });
      if (selectableMusicians.length === 1) {
        $musicianOptions.each(function() { $(this).prop('disabled', false); });
        selectableMusicians[0].prop('selected', true);
      }
      refreshSelectWidget($musicianInput);
    }

    const $paymentsSelect = $container.find(pmeSelectInputSelector + '.composite-payment-id') as JQuerySelect;
    const $paymentOptions = getSelectOptions($paymentsSelect);
    const selectablePayments: JQuery<HTMLOptionElement>[] = [];
    $paymentOptions.each(function() {
      const $paymentOption = $(this);
      const thisProjectId = +$paymentOption.data('projectId');
      if (projectId > 0 && projectId !== thisProjectId) {
        $paymentOption.prop('disabled', true);
      } else {
        $paymentOption.prop('disabled', false);
        selectablePayments.push($paymentOption);
      }
    });
    if (selectablePayments.length === 1) {
      $paymentOptions.each(function() { $(this).prop('disabled', false); });
      selectablePayments[0].prop('selected', true);
      $container.find(pmeInputClassSelector() + '.composite-payment-subject').val(
        selectablePayments[0].data('subject'),
      );
      $form.toggleClass($form.data('selfTestFailure'), !selectablePayments[0].data('status'));
    }
    refreshSelectWidget($paymentsSelect);

    setTimeout(() => { inputLock = false; }, 0);

    return false;
  });

  $container.on('change', pmeSelectInputSelector + '.composite-payment-id', function() {
    // avoid ping-pong
    if (inputLock) {
      return false;
    }
    inputLock = true;

    const $this = $(this);
    const paymentId = +$this.val() || 0;
    const $paymentOption = getSelectOptionByValue($this, '' + paymentId);
    const projectId = $paymentOption.data('projectId');
    const musicianId = $paymentOption.data('musicianId');
    const amount = $paymentOption.data('amount');
    const amountWaived = $paymentOption.data('amountWaived');
    const status = $paymentOption.data('status');
    const subject = $paymentOption.data('subject');

    $container.find(pmeInputSelector + '.project-payment.amount').val(amount);
    $container.find(pmeInputSelector + '.project-payment.amount-waived').val(amountWaived);
    $container.find(pmeInputSelector + '.project-payment.status').val([status]);

    $form.toggleClass($form.data('selfTestFailure'), !status);

    // we re-enable all options but select the respective project and
    // person

    const $musicianInput = $container.find(pmeInputClassSelector() + '.musician-id');
    if (isJQuerySelect($musicianInput)) {
      const $musicianOptions = getSelectOptions($musicianInput);
      $musicianOptions.each(function() {
        const $musicianOption = $(this);
        const thisMusicianId = +$musicianOption.val()!;
        $musicianOption.prop('disabled', false).prop('selected', thisMusicianId === musicianId);
      });
      refreshSelectWidget($musicianInput);
    } else if ($musicianInput.is('input')) {
      $musicianInput.val($musicianInput.data('pmeValues').values[musicianId]);
    }

    const $projectInput = $container.find(pmeInputClassSelector() + '.project-id');
    if (isJQuerySelect($projectInput)) {
      const $projectOptions = getSelectOptions($projectInput);
      $projectOptions.each(function() {
        const $projectOption = $(this);
        const thisProjectId = +$projectOption.val()!;
        $projectOption.prop('disabled', false).prop('selected', thisProjectId === projectId);
      });
      refreshSelectWidget($projectInput);
    } else if ($projectInput.is('input')) {
      $projectInput.val($projectInput.data('pmeValues').values[projectId]);
    }

    const $subjectInput = $container.find(pmeInputClassSelector() + '.composite-payment-subject');
    console.info('SUBJECT INPUT', pmeInputClassSelector() + '.composite-payment-subject', $subjectInput);
    $subjectInput.val(subject);

    const $options = getSelectOptions($this);
    $options.each(function() { $(this).prop('disabled', false); });

    setTimeout(() => { inputLock = false; }, 0);

    return false;
  });
};

const documentReady = () => {

  PHPMyEdit.addTableLoadCallback(
    template, {
      callback(_template, selector, parameters, resizeCB) {
        if (parameters.reason === 'dialogOpen') {
          pmeFormInit(selector, parameters, resizeCB);
        }
        console.info('RESIZE DB');
        resizeCB();
      },
      context: globalState,
    });

  CAFEVDB.addReadyCallback(async () => {

    const container = PHPMyEdit.container();

    if (!container.hasClass(template)) {
      return;
    }

    const renderer = $(PHPMyEdit.defaultSelector).find('form.pme-form input[name="templateRenderer"]').val();
    if (renderer === templateRenderer(template)) {
      pmeFormInit(PHPMyEdit.defaultSelector, undefined, () => null);
    }
  });
};

export {
  documentReady,
};
