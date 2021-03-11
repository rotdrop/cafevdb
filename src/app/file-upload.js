/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// @todo remove this file.

import { globalState, appName, $ } from './globals.js';
import * as Notification from './notification.js';
import * as Ajax from './ajax.js';

require('jquery-ui/ui/widgets/progressbar');
require('blueimp-file-upload');
require('blueimp-file-upload/js/jquery.iframe-transport');

const FileUpload = globalState.FileUpload = {
  uploadingFiles: {},
};

const cancelUploads = function() {
  $.each(FileUpload.uploadingFiles, function(index, file) {
    if (typeof file.abort === 'function') {
      file.abort();
    } else {
      $.each(file, function(i, f) {
        f.abort();
        delete file[i];
      });
      delete FileUpload.uploadingFiles[index];
    }
  });
};

/**
 * To be called at some other document-ready invocation, as required.
 *
 * @param {Object} options TBD.
 */
function init(options) {
  const defaultOptions = {
    doneCallback: null,
    stopCallback: null,
    dropZone: $(document),
    containerSelector: '#file_upload_wrapper',
    inputSelector: '#file_upload_start',
    progressTemplate: 'Uploading {n} files, {percentage}%, {loaded} of {total} bytes at {rate} bytes/s',
  };

  options = $.extend({}, defaultOptions, options);

  const container = $(options.containerSelector);
  const form = container.find('form.file_upload_form');
  const fileUploadStart = form.find(options.inputSelector);
  const uploadProgressWrapper = container.find('div.uploadprogresswrapper');
  const progressBar = uploadProgressWrapper.find('div.uploadprogressbar');

  const fileUploadParam = {
    // forceIframeTransport: true,
    initialIframeSrc: 'http://',
    dataType: 'json',
    multipart: true,
    singleFileUploads: false,
    sequentialUploads: false,
    dropZone: options.dropZone, // restrict dropZone to content div
    add(e, data) {
      for (let k = 0; k < data.files.length; ++k) {
        if (data.files[k].type === '' && data.files[k].size === 4096) {
          data.textStatus = 'dirorzero';
          data.errorThrown = t(appName, 'Unable to upload your file as it is a directory or has 0 bytes');
          const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
          fu._trigger('fail', e, data);
          return true; // don't upload this file but go on with next in queue
        }
      }

      let totalSize = 0;
      $.each(data.originalFiles, function(i, file) {
        totalSize += file.size;
      });

      if (totalSize > form.find('#max_upload').val()) {
        data.textStatus = 'notenoughspace';
        data.errorThrown = t(appName, 'Not enough space available');
        const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
        fu._trigger('fail', e, data);
        return false; // don't upload anything
      }

      // start the actual file upload
      const jqXHR = data.submit();

      for (const file of data.files) {
        // remember jqXHR to show warning to user when he navigates away but an upload is still in progress
        globalState.FileUpload.uploadingFiles[file.name] = jqXHR;
      }

      return false;
    },
    send(event, data) {
      console.info('SEND DATA', Object.assign({}, data));
    },
    /**
     * called after the first add, does NOT have the data param
     *
     * @param {Object} event TBD.
     */
    start(event) {
      // warn user not to leave the page while upload is in progress
      $(window).on('beforeunload', function(e) {
        if ($.assocArraySize(FileUpload.uploadingFiles) > 0) {
          return t(appName, 'File upload is in progress. Leaving the page now will cancel the upload.');
        }
        return false;
      });
      if (!uploadProgressWrapper.hasClass('ui-dialog-content')) {
        uploadProgressWrapper.cafevDialog({
          width: '100vw',
        });
      }
      progressBar.progressbar({ value: 0 });
      progressBar.fadeIn();
      uploadProgressWrapper.find('input.stop').show();
    },
    fail(event, data) {
      if (typeof data.textStatus !== 'undefined' && data.textStatus !== 'success') {
        if (data.textStatus === 'abort') {
          Notification.show(t(appName, 'Upload cancelled.'), { timeout: 15 });
        } else {
          Ajax.handleError(data.jqXHR, data.textStatus, data.errorThrown);
        }
        $('#notification').fadeIn();
        // hide notification after 5 sec
        setTimeout(function() {
          $('#notification').fadeOut();
        }, 10000);
      }
      delete globalState.FileUpload.uploadingFiles[data.files[0].name];
      $(window).off('beforeunload');
    },
    progress(e, data) {
      const title = t(appName, options.progressTemplate, {
        n: data.files.length,
        percentage: ((data.loaded / data.total) * 100).toFixed(1),
        loaded: data.loaded,
        total: data.total,
        rate: data.bitrate,
      });
      uploadProgressWrapper.cafevDialog('option', 'title', title);
    },
    progressall(e, data) {
      console.info('PROGRESSALL', data);
      const progress = (data.loaded / data.total) * 100;
      progressBar.progressbar('value', progress);
    },
    /**
     * called for every successful upload
     *
     * @param {Object} event TBD.
     *
     * @param {Object} data TBD.
     */
    done(event, data) {
      const result = data.result;

      let k;
      const errors = [];
      if (!Array.isArray(result)) {
        errors.push(t(appName, 'Unknown error uploading files'));
      } else {
        for (const upload of result) {
          if (upload.error !== 0) {
            errors.push(upload.str_error);
            continue;
          }
          const filename = upload.original_name;

          // delete jqXHR reference
          delete globalState.FileUpload.uploadingFiles[filename];

          if (typeof options.doneCallback === 'function') {
            options.doneCallback(upload);
          }
        }
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
     * @param {Object} event TBD.
     *
     * @param {Object} data TBD.
     */
    stop(event, data) {
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
  };

  if (options.url) {
    fileUploadParam.url = options.url;
  }

  const fileUploadHandler = function() {
    fileUploadStart.fileupload(fileUploadParam);
  };

  if (container.length > 0) {
    $(fileUploadHandler);
  }
  $.assocArraySize = function(obj) {
    // http://stackoverflow.com/a/6700/11236
    return Object.keys(obj).length;
  };

  container.find('div.uploadprogresswrapper input.stop').on('click', function(event) {
    cancelUploads();
    return false;
  });

  // add multiply file upload attribute to all browsers except konqueror (which crashes when it's used)
  // if (navigator.userAgent.search(/konqueror/i) === -1 || true) {
  fileUploadStart.attr('multiple', 'multiple');
  // }
}

export {
  init,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
