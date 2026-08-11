/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2021-2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// @todo remove this file.

import type { UploadFileData } from '../../build/ts-types/php-modules/Controller/DTO.ts';

import { formatFileSize } from '@nextcloud/files';
import { translate as t } from '@nextcloud/l10n';
import { appName } from '../config.ts';
import * as Ajax from './ajax.ts';
import $ from './jquery.ts';
import * as Notification from './notification.ts';

import 'jquery-ui/ui/widgets/progressbar';
import 'blueimp-file-upload';
import 'blueimp-file-upload/js/jquery.iframe-transport';

type jqXHR = JQuery.jqXHR;
const uploadingFiles: Record<string, jqXHR|jqXHR[]> = {};

export type UploadFile = UploadFileData;

interface UploadData {
  result: UploadFileData[]|Record<string, UploadFileData>;
  files: UploadFileData[];
  originalFiles: UploadFileData[];
  textStatus: 'dirorzero'|string;
  errorThrown: string;
  submit: () => jqXHR;
  jqXHR: jqXHR;
  loaded: number;
  total: number;
  bitrate: number;
}

const cancelUploads = function() {
  $.each(uploadingFiles, function(index, file) {
    if (Array.isArray(file)) {
      $.each(file, function(i, f) {
        f.abort();
        delete file[i];
      });
    } else {
      file.abort();
    }
    delete uploadingFiles[index];
  });
};

/**
 * @param file TBD.
 * @param _index TBD.
 * @param $container TBD.
 */
function defaultDoneCallback(file: UploadFileData, _index: number|string|symbol, $container: JQuery) {
  file.status = 'new';
  // file.index = index;
  if (!$container.data('files')) {
    $container.data('files', []);
  }
  $container.data('files').push(file);
  console.info($container.data('files'));
}

export interface Options {
  doneCallback: typeof defaultDoneCallback;
  startCallback?: (event: unknown) => void;
  stopCallback?: (event: unknown, data: UploadData) => void;
  failCallback?: (event: unknown, data: UploadData) => void;
  dropZone: JQuery;
  containerSelector: string;
  inputSelector: string;
  progressTemplate: string;
  multiple: boolean;
  url?: string;
}

const defaultOptions: Options = {
  doneCallback: defaultDoneCallback,
  dropZone: $('body'),
  containerSelector: '#file_upload_wrapper',
  inputSelector: '#file_upload_start',
  progressTemplate: 'Uploading {n} files, {percentage}%, {loaded} of {total} bytes at {rate} bytes/s',
  multiple: true,
};

/**
 * To be called at some other document-ready invocation, as required.
 *
 * @param parameters TBD.
 */
function init(parameters: Partial<Options>) {

  const options = { ...defaultOptions, ...parameters };

  const $container = $(options.containerSelector);
  const form = $container.find('form.file-upload-form');
  const fileUploadStart = form.find(options.inputSelector);
  const uploadProgressWrapper = $container.find('div.uploadprogresswrapper');
  const progressBar = uploadProgressWrapper.find('div.uploadprogressbar');

  const fileUploadParam = {
    // forceIframeTransport: true,
    initialIframeSrc: 'http://',
    dataType: 'json',
    multipart: true,
    singleFileUploads: false,
    sequentialUploads: false,
    dropZone: options.dropZone, // restrict dropZone to content div
    add(_event: unknown, data: UploadData) {
      for (let k = 0; k < data.files.length; ++k) {
        if (data.files[k].type === '' && data.files[k].size === 4096) {
          data.textStatus = 'dirorzero';
          data.errorThrown = t(appName, 'Unable to upload your file as it is a directory or has 0 bytes');
          const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
          fu._trigger('fail', _event, data);
          return true; // don't upload this file but go on with next in queue
        }
      }

      let totalSize = 0;
      $.each(data.originalFiles, function(_i, file) {
        totalSize += file.size;
      });

      if (totalSize > parseInt(form.find('#max_upload').val() + '')) {
        data.textStatus = 'notenoughspace';
        data.errorThrown = t(appName, 'Not enough space available');
        const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
        fu._trigger('fail', _event, data);
        return false; // don't upload anything
      }

      // start the actual file upload
      const jqXHR = data.submit();

      for (const file of data.files) {
        // remember jqXHR to show warning to user when he navigates away but an upload is still in progress
        uploadingFiles[file.name] = jqXHR;
      }

      return false;
    },
    send(_event: unknown, data: UploadData) {
      console.debug('SEND DATA', { ...data });
    },
    /**
     * called after the first add, does NOT have the data param
     *
     * @param event TBD.
     */
    start(event: unknown) {
      // warn user not to leave the page while upload is in progress
      $(window).on('beforeunload', function() {
        if (Object.keys(uploadingFiles).length > 0) {
          return t(appName, 'File upload is in progress. Leaving the page now will cancel the upload.');
        }
        return false;
      });
      if (!uploadProgressWrapper.hasClass('ui-dialog-content')) {
        uploadProgressWrapper.cafevDialog({
          width: '100vw',
          height: 'auto',
          minHeight: 50,
          resizable: false,
        });
      }
      progressBar.progressbar({ value: 0 });
      progressBar.fadeIn();
      uploadProgressWrapper.find('input.stop').show();

      if (typeof options.startCallback === 'function') {
        options.startCallback(event);
      }
    },
    fail(event: unknown, data: UploadData) {
      if (typeof data.textStatus !== 'undefined' && data.textStatus !== 'success') {
        if (data.textStatus === 'abort') {
          Notification.show(t(appName, 'Upload cancelled.'), { timeout: 15 });
        } else {
          Ajax.handleError(data.jqXHR, data.textStatus, data.errorThrown);
        }
      }
      delete uploadingFiles[data.files[0].name];
      if (typeof options.failCallback === 'function') {
        options.failCallback(event, data);
      }
      $(window).off('beforeunload');
    },
    progress(_event: unknown, data: UploadData) {
      const title = t(appName, options.progressTemplate, {
        n: data.files.length,
        percentage: ((data.loaded / data.total) * 100).toFixed(1),
        loaded: formatFileSize(data.loaded),
        total: formatFileSize(data.total),
        rate: formatFileSize(data.bitrate),
      });
      uploadProgressWrapper.cafevDialog('option', 'title', title);
    },
    progressall(_event: unknown, data: UploadData) {
      const progress = ((data.loaded / data.total) * 100).toFixed(1);
      progressBar.progressbar('value', progress);
    },
    /**
     * called for every successful upload
     *
     * @param event TBD.
     *
     * @param data TBD.
     */
    done(event: unknown, data: UploadData) {

      const result = data.result;

      const errors: string[] = [];

      const processUpload = function(upload: UploadFileData, index: string|number) {

        if ((upload.error ?? 0) !== 0) {
          errors.push(upload.str_error!);
          return;
        }
        if (!upload.original_name) {
          errors.push(t(appName, 'Property "{property}" is missing.', { property: 'original_name' }));
          return;
        }
        const filename = upload.original_name;

        // delete jqXHR reference
        delete uploadingFiles[filename];

        if (typeof options.doneCallback === 'function') {
          options.doneCallback(upload, index, $container);
        }
      };

      if (Array.isArray(result)) {
        result.forEach(processUpload);
      } else {
        Object.entries(result).forEach(([key, value]) => processUpload(value, key));
      }

      // @todo Is this the "best" of all possibilities?
      if (errors.length > 0) {
        data.textStatus = 'servererror';
        data.errorThrown = '';
        if (errors.length > 1) {
          for (const error of errors) {
            data.errorThrown += t(appName, ' Error: {error}.', { error });
          }
        } else {
          data.errorThrown += errors[0];
        }
        const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
        fu._trigger('fail', event, data);
      }
    },
    /**
     * called after last upload
     *
     * @param event TBD.
     *
     * @param data TBD.
     */
    stop(event: unknown, data: UploadData) {
      uploadProgressWrapper.find('input.stop').hide();
      progressBar.progressbar('value', 100);
      progressBar.fadeOut();

      if (typeof options.stopCallback === 'function') {
        options.stopCallback(event, data);
      }

      if (uploadProgressWrapper.hasClass('ui-dialog-content')) {
        uploadProgressWrapper.cafevDialog('destroy');
      }

      $(window).off('beforeunload');
    },
    url: options.url,
  };

  const fileUploadHandler = function() {
    fileUploadStart.fileupload(fileUploadParam);
  };

  if ($container.length > 0) {
    $(fileUploadHandler);
  }

  $container.find('div.uploadprogresswrapper input.stop').on('click', function() {
    cancelUploads();
    return false;
  });

  // add multiply file upload attribute to all browsers except konqueror (which crashes when it's used)
  // if (navigator.userAgent.search(/konqueror/i) === -1 || true) {
  fileUploadStart.prop('multiple', options.multiple);
  // }
}

export {
  init,
};

export default init;
