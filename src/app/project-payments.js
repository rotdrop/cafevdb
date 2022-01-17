/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

import { $, appName } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as PHPMyEdit from './pme.js';
import * as SelectUtils from './select-utils.js';
import * as Page from './page.js';
import * as Dialogs from './dialogs.js';
import initFileUploadRow from './pme-file-upload-row.js';
import {
  sys as pmeSys,
  data as pmeData,
  formSelector as pmeFormSelector,
} from './pme-selectors.js';

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
 * @param {String} containerSel The ambient element of the container
 * (i.e. the base page, or the div holding the dialog this one was
 * initiated from.
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 */
const projectPaymentPopup = function(containerSel, post) {
  // Prepate the data-array for PHPMyEdit.tableDialogOpen(). The
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

const ready = function(selector, pmeParameters, resizeCB) {

  const $container = $(selector);

  if (pmeParameters.reason === 'dialogOpen') {

    $container
      .on('dblclick', 'table.pme-main tr.composite-payment.first td', function(event) {
        event.stopImmediatePropagation();
        PHPMyEdit.openRowDialog(this, event, $container);
        return false;
      });

    $container
      .on('click', 'table.pme-main tr.composite-payment.first td', function(event) {
        if ($(event.target).is('a.download-link, a.open-parent')) {
          return;
        }
        const $row = $(this).closest('tr.composite-payment.first');
        event.stopImmediatePropagation();
        $row.toggleClass('following-hidden');
        $row.find('input.expanded-marker').val($row.hasClass('following-hidden') ? 0 : 1);
        return false;
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

          projectPaymentPopup(selector, {
            projectId,
            musicianId,
            [pmeSys('rec')]: $actionOption.data('data').recordId,
            [pmeSys('groupby_rec')]: { id: $actionOption.data('data').recordId.id, ProjectPayments_key: 0 },
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
      });

  } // reason === 'dialogOpen'

  const tableOptions = pmeParameters.tableOptions || {};
  if (tableOptions.ambientContainerSelector) {

    const $pmeForm = (pmeParameters.reason === 'dialogClose')
      ? $(pmeParameters.htmlResponse).find(pmeFormSelector())
      : $container.find(pmeFormSelector());

    const paymentsRowTagName = pmeData('ProjectPayments:row_tag');
    const rowTag = findByName($pmeForm, paymentsRowTagName).val();

    // Make sure the current payment is present in the "master"
    // form. Otherwise a form submit would delete the current payment
    // again.
    if (rowTag && !isCompositeRow(rowTag)) {
      const $ambientContainer = $(tableOptions.ambientContainerSelector);
      const $ambientForm = $ambientContainer.find(pmeFormSelector());
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
  ready,
  documentReady,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
