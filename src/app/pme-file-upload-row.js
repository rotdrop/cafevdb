/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName, $ } from './globals.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as FileUpload from './file-upload.js';
import * as Page from './page.js';
import * as Notification from './notification.js';
import generateUrl from './generate-url.js';
import md5 from 'blueimp-md5';
// or: const md5 = require('blueimp-md5');
// but NOT: import { md5 } from 'blueimp-md5';
import modalizer from './modalizer.js';

const defaultUploadUrls = {
  upload: 'projects/participants/files/upload',
  delete: 'projects/participants/files/delete',
  stash: 'upload/stash',
};

const initFileUploadRow = function(projectId, musicianId, resizeCB, uploadUrls) {
  const $thisRow = $(this);
  const fieldId = $thisRow.data('fieldId');
  const optionKey = $thisRow.data('optionKey');
  const subDir = $thisRow.data('subDir');
  const fileName = $thisRow.data('fileName');
  const fileBase = $thisRow.data('fileBase');
  const widgetId = 'file-upload-' + optionKey + (fileBase || !fileName ? '' : '-md5-' + md5(fileName));
  const isCloudFolder = $thisRow.closest('td.participant-field').hasClass('cloud-folder');
  const uploadMultiple = isCloudFolder && !fileName;
  const $uploadUi = $('#fileUploadTemplate').octemplate({
    wrapperId: widgetId,
    formClass: 'file-upload-form',
    accept: '*',
    uploadName: 'files[' + optionKey + ']' + (uploadMultiple ? '[]' : ''),
    projectId,
    musicianId,
    uploadData: JSON.stringify($thisRow.data()),
  });
  const $oldUploadForm = $('#' + widgetId);
  if ($oldUploadForm.length === 0) {
    $('body').append($uploadUi);
  } else {
    $oldUploadForm.replaceWith($uploadUi);
  }
  $thisRow.data('uploadFormId', widgetId);
  uploadUrls = $.extend({}, defaultUploadUrls, uploadUrls);

  // const $parentFolder = $thisRow.find('.operation.open-parent');
  const $deleteUndelete = $thisRow.find('.operation.delete-undelete');
  const $downloadLink = $thisRow.find('a.download-link');
  const $placeholder = $thisRow.find('input.upload-placeholder');

  const noFile = $downloadLink.attr('href') === '';

  $deleteUndelete.prop('disabled', noFile);

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
        initFileUploadRow.apply($newRow);
        resizeCB();
      }
    } else {
      $downloadLink.attr('href', file.meta.download);
      $downloadLink.html(file.meta.baseName);
      $placeholder.val(file.meta.baseName);

      const noFile = $downloadLink.attr('href') === '';

      $deleteUndelete.prop('disabled', noFile);
    }
    $.fn.cafevTooltip.remove();
  };

  FileUpload.init({
    url: generateUrl(uploadUrls.upload),
    doneCallback,
    stopCallback: null,
    dropZone: $thisRow,
    containerSelector: '#' + widgetId,
    inputSelector: 'input[type="file"]',
    multiple: uploadMultiple,
  });

  $deleteUndelete.prop('disabled', $downloadLink.attr('href') === '');
  $thisRow.find('input.upload-placeholder, input.upload-replace')
    .off('click')
    .on('click', function(event) {
      const $fileUpload = $('#' + widgetId + ' input[type="file"]');
      $fileUpload.trigger('click');
      $.fn.cafevTooltip.remove();
      return false;
    });

  $thisRow.find('input.upload-from-cloud')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      console.info('upload from cloud');
      const filePickerObject = $thisRow.data('fileBase') || $thisRow.data('subDir');
      const filePickerCaption = filePickerObject
        ? t(appName, 'Select cloud-files for {object}', { object: filePickerObject })
        : t(appName, 'Select a file from the cloud');
      Dialogs.filePicker(
        filePickerCaption,
        function(paths) {
          console.info('GOT PATH', paths);
          $this.addClass('busy');
          if (!paths) {
            Dialogs.alert(t(appName, 'Empty response from file selection!'), t(appName, 'Error'));
            $this.removeClass('busy');
            return;
          }
          if (!Array.isArray(paths)) {
            paths = [paths];
          }
          $.post(generateUrl(uploadUrls.stash), { cloudPaths: paths })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
              $this.removeClass('busy');
            })
            .done(function(files) {
              if (!Array.isArray(files) || (!isCloudFolder && files.length !== 1)) {
                Dialogs.alert(
                  t(appName, 'Unable to copy selected file(s) {file}.', { file: paths.join(', ') }),
                  t(appName, 'Error'),
                  function() {
                    $this.removeClass('busy');
                  });
                return;
              }
              const formData = $uploadUi.find('form').serializeArray();
              formData.push({ name: 'files', value: JSON.stringify(files) });
              console.info('FORMDATA', formData);
              $.post(generateUrl(uploadUrls.upload), formData)
                .fail(function(xhr, status, errorThrown) {
                  Ajax.handleError(xhr, status, errorThrown);
                  $this.removeClass('busy');
                })
                .done(function(data) {
                  console.info('DONE DATA', data);
                  $.each(data, function(index, file) {
                    doneCallback(file, index, $uploadUi);
                  });
                  $this.removeClass('busy');
                });
            });
        },
        uploadMultiple,
        undefined, // mimetypeFilter
        undefined, // modal
        undefined, // type
        $thisRow.data('participantFolder'),
      );
      $.fn.cafevTooltip.remove();
      return false;
    });

  $deleteUndelete.off('click').on('click', function(event) {
    const $thisInput = $(this);
    const cleanup = function(thisRemoved) {
      Page.busyIcon(false);
      modalizer(false);
      $deleteUndelete.prop('disabled', $downloadLink.attr('href') === '');
      $thisInput.removeClass('busy');
    };

    modalizer(true);
    Page.busyIcon(true);
    $thisInput.addClass('busy');
    $deleteUndelete.prop('disabled', true);

    $.post(
      generateUrl(uploadUrls.delete), {
        musicianId,
        projectId,
        fieldId,
        optionKey,
        subDir,
        fileName,
      })
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, cleanup);
      })
      .done(function(data) {
        if (!Ajax.validateResponse(data, ['message'], cleanup)) {
          return;
        }
        if (isCloudFolder) {
          const widgetId = $thisRow.data('uploadFormId');
          $('#' + widgetId).remove();
          $.fn.cafevTooltip.remove();
          $thisRow.remove();
          resizeCB();
        } else {
          $downloadLink.attr('href', '');
          $downloadLink.html('');
          $placeholder.val('');
          $deleteUndelete.prop('disabled', $downloadLink.attr('href') === '');
        }
        Notification.messages(data.message);
        cleanup();
      });
  });
};

export default initFileUploadRow;
