/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName } from './globals.js';

globalState.FileUpload = {
  uploadingFiles: {},
};

const cancelUploads = function() {
  $.each(FileUpload.uploadingFiles, function(index, file) {
    if(typeof file['abort'] === 'function') {
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

/** To be called at some other document-ready invocation, as required. */
const init = function(options) {
  var defaultOptions = {
    doneCallback: null,
    stopCallback: null,
    dropZone: $(document),
    containerSelector: '#file_upload_wrapper',
    inputSelector: '#file_upload_start'
  };

  options = $.extend({}, defaultOptions, options);

  const container = $(options.containerSelector);
  const form = container.find('form.file_upload_form');
  const fileUploadStart = form.find(options.inputSelector);

  var fileUploadParam = {
    multipart: true,
    singleFileUploads: false,
    sequentialUploads: true,
    dropZone: options.dropZone, // restrict dropZone to content div
    // singleFileUploads is on by default, so the data.files array will always have length 1
    add: function(e, data) {
      for (let k = 0; k < data.files.length; ++k) {
        if(data.files[k].type === '' && data.files[k].size == 4096) {
          data.textStatus = 'dirorzero';
          data.errorThrown = t('cafevdb', 'Unable to upload your file as it is a directory or has 0 bytes');
          const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
          fu._trigger('fail', e, data);
          return true; // don't upload this file but go on with next in queue
        }
      }

      let totalSize = 0;
      $.each(data.originalFiles, function(i,file) {
        totalSize += file.size;
      });

      if (totalSize > form.find('#max_upload').val()) {
        data.textStatus = 'notenoughspace';
        data.errorThrown = t('cafevdb', 'Not enough space available');
        const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
        fu._trigger('fail', e, data);
        return false; // don't upload anything
      }

      // start the actual file upload
      const jqXHR = data.submit();

      for (let k = 0; k < data.files.length; ++k) {
        // remember jqXHR to show warning to user when he navigates away but an upload is still in progress
        if (typeof data.context !== 'undefined' && data.context.data('type') === 'dir') {
          const dirName = data.context.data('file');
          if (typeof globalState.FileUpload.uploadingFiles[dirName] === 'undefined') {
            globalState.FileUpload.uploadingFiles[dirName] = {};
          }
          globalState.FileUpload.uploadingFiles[dirName][data.files[k].name] = jqXHR;
        } else {
          globalState.FileUpload.uploadingFiles[data.files[k].name] = jqXHR;
        }
      }
      // show cancel button
      if ($('html.lte9').length === 0 && data.dataType !== 'iframe') {
        container.find('div.uploadprogresswrapper input.stop').show();
      }
      return false;
    },
    /**
     * called after the first add, does NOT have the data param
     * @param e
     */
    start: function(e) {
      // warn user not to leave the page while upload is in progress
      $(window).on('beforeunload', function(e) {
        if ($.assocArraySize(FileUpload.uploadingFiles) > 0) {
          return t('cafevdb', 'File upload is in progress. Leaving the page now will cancel the upload.');
        }
        return false;
      });

      // IE < 10 does not fire the necessary events for the progress bar.
      if ($('html.lte9').length > 0) {
        return;
      }
      const progressBar = container.find('div.uploadprogressbar');
      progressBar.progressbar({value:0});
      progressBar.fadeIn();
    },
    fail: function(e, data) {
      if (typeof data.textStatus !== 'undefined' && data.textStatus !== 'success' ) {
        if (data.textStatus === 'abort') {
          $('#notification').text(t('cafevdb', 'Upload cancelled.'));
        } else {
          // HTTP connection problem
          $('#notification').text(data.errorThrown);
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
    },
    progressall(e, data) {
      // IE < 10 does not fire the necessary events for the progress bar.
      if($('html.lte9').length > 0) {
        return;
      }
      // alert('total: '+ data.total+' loaded: '+data.loaded);
      const progress = (data.loaded/data.total)*100;
      container.find('div.uploadprogressbar').progressbar('value',progress);
    },
    /**
     * called for every successful upload
     * @param e
     * @param data
     */
    done(e, data) {
      // handle different responses (json or body from iframe for ie)
      let response;
      if (typeof data.result === 'string') {
        response = data.result;
      } else {
        // fetch response from iframe
        response = data.result[0].body.innerText;
      }
      const result = $.parseJSON(response);

      let k;
      const errors = [];
      if (typeof result.length == 'undefined') {
        if (typeof result.status != 'undefined') {
          errors.push(result.data.message);
        } else {
          errors.push(t('cafevdb', 'Unknown error uploading files'));
        }
      } else {
        for (k = 0; k < result.length; ++k) {
          if (typeof result[k] !== 'undefined' && result[k].status === 'success') {
            const filename = result[k].data.originalname;

            // delete jqXHR reference
            if (typeof data.context !== 'undefined' && data.context.data('type') === 'dir') {
              const dirName = data.context.data('file');
              delete globalState.FileUpload.uploadingFiles[dirName][filename];
              if ($.assocArraySize(globalState.FileUpload.uploadingFiles[dirName]) == 0) {
                delete globalState.FileUpload.uploadingFiles[dirName];
              }
            } else {
              delete globalState.FileUpload.uploadingFiles[filename];
            }

            if (typeof options.doneCallback == 'function') {
              options.doneCallback(result[k]);
            }

          } else {
            errors.push(result[k].data.message);
          }
        }
      }

      if (errors.length > 0) {
        data.textStatus = 'servererror';
        data.errorThrown = '';
        if (errors.length > 1) {
          for (k = 0; k < errors.length; ++k) {
            data.errorThrown += t('cafevdb', 'Error {NR}: ', { NR: k })+errors[k]+"\n";
          }
        } else {
          data.errorThrown += errors[0];
        }
        const fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
        fu._trigger('fail', e, data);
      }
    },
    /**
     * called after last upload
     * @param e
     * @param data
     */
    stop(e, data) {
      if (data.dataType !== 'iframe') {
        container.find('div.uploadprogresswrapper input.stop').hide();
      }

      // IE < 10 does not fire the necessary events for the progress bar.
      if ($('html.lte9').length > 0) {
        return;
      }

      const progressBar = container.find('div.uploadprogressbar');
      progressBar.progressbar('value',100);
      progressBar.fadeOut();

      if (typeof options.stopCallback == 'function') {
        options.stopCallback(e, data);
      }

      $(window).off('beforeunload');
    }
  };

  const file_upload_handler = function() {
    fileUploadStart.fileupload(fileUploadParam);
  };

  if (container.length > 0) {
    $(file_upload_handler);
  }
  $.assocArraySize = function(obj) {
    // http://stackoverflow.com/a/6700/11236
    let size = 0;
    for (const key in obj) {
      if (obj.hasOwnProperty(key)) size++;
    }
    return size;
  };

  container.find('div.uploadprogresswrapper input.stop').on('click', function(event) {
    FileUpload.cancelUploads();
    return false;
  });

  // add multiply file upload attribute to all browsers except konqueror (which crashes when it's used)
  if (navigator.userAgent.search(/konqueror/i) == -1 || true) {
    fileUploadStart.attr('multiple','multiple');
  }
};

export {
  init,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
