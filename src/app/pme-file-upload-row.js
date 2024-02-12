/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as FileUpload from './file-upload.js';
import * as Notification from './notification.js';
import { formSelector as pmeFormSelector } from './pme-selectors.js';
import generateUrl from './generate-url.js';
import md5 from 'blueimp-md5';
// or: const md5 = require('blueimp-md5');
// but NOT: import { md5 } from 'blueimp-md5';
import setAppBusyIndicators from './busy-indicators.js';
import cloudFilePickerDialog from './cloud-file-picker-dialog.js';

const defaultUploadUrls = {
  upload: 'projects/participants/files/upload',
  delete: 'projects/participants/files/delete',
  stash: 'upload/stash',
};

const initFileUploadRow = function(projectId, musicianId, resizeCB, uploadUrls) {
  const $thisRow = $(this);
  const $pmeContainer = $thisRow.closest(pmeFormSelector);
  const fieldId = $thisRow.data('fieldId');
  const optionKey = $thisRow.data('optionKey');
  const subDir = $thisRow.data('subDir');
  const fileName = $thisRow.data('fileName');
  const fileBase = $thisRow.data('fileBase');
  const widgetId = 'file-upload-' + optionKey + (fileBase || !fileName ? '' : '-md5-' + md5(fileName));
  const isCloudFolder = $thisRow.closest('td.participant-field').hasClass('cloud-folder');
  // const storageType = $thisRow.data('storage');
  const uploadMultiple = isCloudFolder && !fileName;
  const $uploadUi = $('#fileUploadTemplate').octemplate({
    wrapperId: widgetId,
    formClass: 'file-upload-form',
    accept: '*',
    uploadName: 'files[' + optionKey + ']' + (uploadMultiple ? '[]' : ''),
    projectId,
    musicianId,
    uploadData: JSON.stringify($thisRow.data()),
    requestToken: OC.requestToken,
  });
  const $oldUploadForm = $('#' + widgetId);
  if ($oldUploadForm.length === 0) {
    $('body').append($uploadUi);
  } else {
    $oldUploadForm.replaceWith($uploadUi);
  }

  $thisRow.data('uploadFormId', widgetId);
  uploadUrls = $.extend({}, defaultUploadUrls, uploadUrls);

  const $parentFolder = $thisRow.find('.operation.open-parent');
  const $deleteUndelete = $thisRow.find('.operation.delete-undelete');
  const $downloadLink = $thisRow.find('a.download-link');
  const $placeholder = $thisRow.find('input.upload-placeholder');

  const noDownloadFile = () => $downloadLink.attr('href') === '';
  const noFilesAppLink = () => $parentFolder.attr('href') === '';

  const unmaskInputs = () => {
    $downloadLink.prop('disabled', noDownloadFile()).toggleClass('disabled', noDownloadFile());
    $deleteUndelete.prop('disabled', noDownloadFile()).toggleClass('disabled', noDownloadFile());
    $parentFolder.prop('disabled', noFilesAppLink()).toggleClass('disabled', noFilesAppLink());
  };
  const maskInputs = () => {
    $downloadLink.prop('disabled', true).toggleClass('disabled', true);
    $deleteUndelete.prop('disabled', true).toggleClass('disabled', true);
    $parentFolder.prop('disabled', true).toggleClass('disabled', true);
  };

  unmaskInputs();

  const setBusyIndicators = function(state) {
    setAppBusyIndicators(state, $pmeContainer);
    if (state) {
      $thisRow.addClass('busy');
    } else {
      $thisRow.removeClass('busy');
      unmaskInputs();
    }
  };

  const doneCallback = function(file, index, container) {
    if (!file.meta) {
      Notification.show(t(appName, 'File-upload feedback does not contain the required meta-information, the upload has probably failed.'));
      return;
    }

    Notification.messages(file.meta.messages);

    if (isCloudFolder) {
      if (!file.meta.conflict) {
        // clone current row and replace all appropriate values.
        const $newRow = $thisRow.clone();
        $newRow.find('a.download-link')
          .attr('href', file.meta.download)
          .html(file.meta.baseName);
        $newRow.find('input.upload-placeholder')
          .val(file.meta.baseName);
        $newRow.attr('data-file-name', file.meta.baseName);
        $newRow.data('fileName', file.meta.baseName);
        $newRow.insertBefore($thisRow);
        initFileUploadRow.call($newRow, projectId, musicianId, resizeCB, uploadUrls);
        resizeCB();
      }
    } else {
      $downloadLink.attr('href', file.meta.download);
      if (!$downloadLink.hasClass('static-content')) {
        $downloadLink.html(file.meta.baseName);
      }
      switch (file.meta.storageBackend) {
      case 'db':
        $placeholder.val(file.meta.fileId);
        break;
      case 'cloud':
      default:
        $placeholder.val(file.meta.baseName);
        break;
      }
      $parentFolder.attr('href', file.meta.filesApp);

      unmaskInputs();
    }
    $.fn.cafevTooltip.remove();
    $thisRow.trigger('pme:upload-done');
    setBusyIndicators(false);
  };

  FileUpload.init({
    url: generateUrl(uploadUrls.upload),
    doneCallback,
    stopCallback() {
      setBusyIndicators(false);
    },
    startCallback() {
      setBusyIndicators(true);
    },
    dropZone: $thisRow,
    containerSelector: '#' + widgetId,
    inputSelector: 'input[type="file"]',
    multiple: uploadMultiple,
  });

  unmaskInputs();

  $thisRow
    .find('input.upload-placeholder, .operation.upload-replace')
    .off('click')
    .on('click', function(event) {
      const $fileUpload = $('#' + widgetId + ' input[type="file"]');
      $fileUpload.trigger('click');
      $.fn.cafevTooltip.remove();
      return false;
    });

  $thisRow
    .find('.operation.upload-from-cloud')
    .off('click')
    .on('click', function(event) {
      const filePickerObject = $thisRow.data('fileBase') || $thisRow.data('subDir');
      const filePickerCaption = filePickerObject
        ? t(appName, 'Select cloud-files for {object}', { object: filePickerObject })
        : t(appName, 'Select a file from the cloud');

      cloudFilePickerDialog({
        setup: () => setBusyIndicators(true),
        cleanup: () => setBusyIndicators(false),
        filePickerCaption,
        stashUrl: uploadUrls.stash,
        multiple: uploadMultiple,
        initialCloudFolder: $thisRow.data('participantFolder'),
        handlePickedFiles(files, paths, cleanup) {
          const formData = $uploadUi.find('form').serializeArray();
          formData.push({ name: 'files', value: JSON.stringify(files) });
          $.post(generateUrl(uploadUrls.upload), formData)
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown, cleanup);
            })
            .done(function(data) {
              $.each(data, function(index, file) {
                doneCallback(file, index, $uploadUi);
              });
              cleanup();
              $thisRow.trigger('pme:upload-done');
            });
        },
      });
      $.fn.cafevTooltip.remove();
      return false;
    });

  $deleteUndelete.off('click').on('click', function(event) {

    const cleanup = function(thisRemoved) {
      setBusyIndicators(false);
    };

    setBusyIndicators(true);
    maskInputs();

    const postData = {
      musicianId,
      projectId,
      fieldId,
      optionKey,
      subDir,
      fileName,
    };
    const failHandler = function(xhr, status, errorThrown) {
      $.fn.cafevTooltip.remove();
      const data = Ajax.failData(xhr, status, errorThrown);
      console.debug('FAIL DATA', data);
      if (data.confirmation
          && data.confirmation.question
          && data.confirmation.override) {
        const text = [];
        if (data.message) {
          if (Array.isArray(data.message)) {
            text.push(...data.message);
          } else {
            text.push(data.message);
          }
        }
        text.push(data.confirmation.question);
        Dialogs.confirm(
          text.join('<br/>'),
          data.confirmation.title || t(appName, 'Confirmation Required!'),
          function(answer) {
            if (answer) { // try again with force parameter
              postData[data.confirmation.override] = true;
              $.post(
                generateUrl(uploadUrls.delete),
                postData)
                .fail(failHandler)
                .done(doneHandler);
            }
          },
          true,
          true);
      } else {
        Ajax.handleError(xhr, status, errorThrown, cleanup);
      }
    };
    const doneHandler = function(data) {
      $.fn.cafevTooltip.remove();
      if (!Ajax.validateResponse(data, ['message'], cleanup)) {
        return;
      }
      $thisRow.trigger('pme:upload-deleted');
      if (isCloudFolder) {
        const widgetId = $thisRow.data('uploadFormId');
        $('#' + widgetId).remove();
        $.fn.cafevTooltip.remove();
        $uploadUi.remove();
        $thisRow.remove();
        resizeCB();
      } else {
        $downloadLink.attr('href', '');
        $parentFolder.attr('href', '');
        if (!$downloadLink.hasClass('static-content')) {
          $downloadLink.html('');
        }
        $placeholder.val('');
        const fileBase = $thisRow.data('fileBase');
        $thisRow.data('fileName', fileBase);
        $thisRow.attr('data-file-name', fileBase);
        $deleteUndelete.prop('disabled', noDownloadFile()).toggleClass('disabled', noDownloadFile());
        $parentFolder.prop('disabled', noFilesAppLink()).toggleClass('disabled', noFilesAppLink());

        // replace the upload data
        //
        // @todo This has to be made more stable, filtering on key name is not good.
        $uploadUi.find('input[name="data"]').val(JSON.stringify(
          $thisRow.data(),
          (k, v) => {
            if (k.match(/tooltip/i)) {
              return undefined;
            }
            return v;
          }));
      }
      Notification.messages(data.message);
      cleanup();
    };

    $.post(generateUrl(uploadUrls.delete), postData)
      .fail(failHandler)
      .done(doneHandler);
  });
};

export default initFileUploadRow;
