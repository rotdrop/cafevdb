/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { MessagesResponse } from '../../build/ts-types/php-modules/Controller/DTO.ts';
import type { TemplateParameters } from '../components/oc-template/oc-template-parameters.d.ts';
import type { ResponseData } from '../types/ajax/response-data.d.ts';

import { getRequestToken } from '@nextcloud/auth';
import { translate as t } from '@nextcloud/l10n';
import md5 from 'blueimp-md5';
import {
  END_POINT_FILES,
  FILE_ACTION_DELETE,
  FILE_ACTION_UPLOAD,
  BASE_PATH as projectParticipantsBasePath,
} from '../../build/ts-types/php-modules/Controller/ProjectParticipantsController.ts';
import { appName } from '../config.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import * as Ajax from './ajax.ts';
// or: const md5 = require('blueimp-md5');
// but NOT: import { md5 } from 'blueimp-md5';
import setAppBusyIndicators from './busy-indicators.ts';
import cloudFilePickerDialog from './cloud-file-picker-dialog.ts';
import * as Dialogs from './dialogs.ts';
import * as FileUpload from './file-upload.ts';
import $ from './jquery.ts';
import * as Notification from './notification.ts';
import { formSelector as pmeFormSelector } from './pme-selectors.ts';

import { disabledCssClass } from 'variables.scss';

const defaultUploadUrls = {
  upload: `${projectParticipantsBasePath}/${END_POINT_FILES}/${FILE_ACTION_UPLOAD}`,
  delete: `${projectParticipantsBasePath}/${END_POINT_FILES}/${FILE_ACTION_DELETE}`,
  stash: 'upload/stash',
};

const initFileUploadRow = function<E extends HTMLElement = HTMLTableRowElement>(
  this: E,
  projectId: number,
  musicianId: number,
  resizeCB: () => void,
  userUploadUrls?: Partial<typeof defaultUploadUrls>,
) {
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
  let uploadData: string;
  try {
    uploadData = JSON.stringify($thisRow.data());
  } catch (error) {
    console.error('JSON STRINGIFY ERROR', { error, data: $thisRow.data() });
    return;
  }
  const $uploadUi = $('#fileUploadTemplate').octemplate<TemplateParameters['fileUploadTemplate']>({
    wrapperId: widgetId,
    formClass: 'file-upload-form',
    accept: '*',
    uploadName: 'files[' + optionKey + ']' + (uploadMultiple ? '[]' : ''),
    projectId,
    musicianId,
    uploadData,
    requestToken: getRequestToken() ?? '',
  });
  const $oldUploadForm = $('#' + widgetId);
  if ($oldUploadForm.length === 0) {
    $('body').append($uploadUi);
  } else {
    $oldUploadForm.replaceWith($uploadUi);
  }

  $thisRow.data('uploadFormId', widgetId);
  const uploadUrls = { ...defaultUploadUrls, ...(userUploadUrls ?? {}) };

  const $parentFolder = $thisRow.find('.operation.open-parent');
  const $deleteUndelete = $thisRow.find('.operation.delete-undelete');
  const $downloadLink = $thisRow.find('a.download-link');
  const $placeholder = $thisRow.find('input.upload-placeholder');

  const noDownloadFile = () => $downloadLink.attr('href') === '';
  const noFilesAppLink = () => $parentFolder.attr('href') === '';

  const unmaskInputs = () => {
    $downloadLink.prop('disabled', noDownloadFile()).toggleClass(disabledCssClass, noDownloadFile());
    $deleteUndelete.prop('disabled', noDownloadFile()).toggleClass(disabledCssClass, noDownloadFile());
    $parentFolder.prop('disabled', noFilesAppLink()).toggleClass(disabledCssClass, noFilesAppLink());
  };
  const maskInputs = () => {
    $downloadLink.prop('disabled', true).toggleClass(disabledCssClass, true);
    $deleteUndelete.prop('disabled', true).toggleClass(disabledCssClass, true);
    $parentFolder.prop('disabled', true).toggleClass(disabledCssClass, true);
  };

  unmaskInputs();

  const setBusyIndicators = function(state: boolean) {
    setAppBusyIndicators(state, $pmeContainer);
    if (state) {
      $thisRow.addClass('busy');
    } else {
      $thisRow.removeClass('busy');
      unmaskInputs();
    }
  };

  const doneCallback: FileUpload.Options['doneCallback'] = function(file, _index, _$container) {
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
        initFileUploadRow.call($newRow[0], projectId, musicianId, resizeCB, uploadUrls);
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
    url: generateAppUrl(uploadUrls.upload),
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
    .on('click', function() {
      const $fileUpload = $('#' + widgetId + ' input[type="file"]');
      $fileUpload.trigger('click');
      $.fn.cafevTooltip.remove();
      return false;
    });

  $thisRow
    .find('.operation.upload-from-cloud')
    .off('click')
    .on('click', function() {
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
        handlePickedFiles(files, _paths, cleanup) {
          const formData = $uploadUi.find('form').serializeArray();
          formData.push({ name: 'files', value: JSON.stringify(files) });
          $.post(generateAppUrl(uploadUrls.upload), formData)
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

  $deleteUndelete.off('click').on('click', function() {

    const cleanup = function() {
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
    const doneHandler: Parameters<JQuery.jqXHR['done']>[0] = function(data: ResponseData<MessagesResponse>) {
      $.fn.cafevTooltip.remove();
      if (!Ajax.validateResponse(data, ['messages'], cleanup)) {
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
        $deleteUndelete.prop('disabled', noDownloadFile()).toggleClass(disabledCssClass, noDownloadFile());
        $parentFolder.prop('disabled', noFilesAppLink()).toggleClass(disabledCssClass, noFilesAppLink());

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
          },
        ));
      }
      Notification.messages(data.messages);
      cleanup();
    };
    const failHandler: Parameters<JQuery.jqXHR['fail']>[0] = function(xhr, status, errorThrown) {
      $.fn.cafevTooltip.remove();
      const data = Ajax.failData(xhr, status, errorThrown);
      console.debug('FAIL DATA', data);
      if (data.confirmation) {
        const { question, override, title } = data.confirmation;
        const text = [...data.messages];
        text.push(question);
        Dialogs.confirm(
          text.join('<br/>'),
          title || t(appName, 'Confirmation Required!'),
          {
            callback(answer) {
              if (answer) { // try again with force parameter
                postData[override] = true;
                $.post(
                  generateAppUrl(uploadUrls.delete),
                  postData,
                )
                  .fail(failHandler)
                  .done(doneHandler);
              }
            },
            modal: true,
            allowHtml: true,
          },
        );
      } else {
        Ajax.handleError(xhr, status, errorThrown, cleanup);
      }
    };

    $.post(generateAppUrl(uploadUrls.delete), postData)
      .fail(failHandler)
      .done(doneHandler);
  });
};

export default initFileUploadRow;
