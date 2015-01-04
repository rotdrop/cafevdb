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

var CAFEVDB = CAFEVDB || {};
CAFEVDB.FileUpload = CAFEVDB.FileUpload || {};

(function(window, $, FileUpload, undefined) {
  'use strict';
  FileUpload.uploadingFiles = {};

  FileUpload.cancelUploads = function() {
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

  /**To be called at some other document-ready invocation, as required. */
  FileUpload.init = function(options) {
    var defaultOptions = {
      doneCallback: null,
      stopCallback: null,
      dropZone: $(document),
      containerSelector: '#file_upload_wrapper',
      inputSelector: '#file_upload_start'
    };

    options = $.extend({}, defaultOptions, options);

    var container = $(options.containerSelector);
    var form = container.find('form.file_upload_form');
    var fileUploadStart = form.find(options.inputSelector);

    var fileUploadParam = {
      multipart: true,
      singleFileUploads: false,
      sequentialUploads: true,
      dropZone: options.dropZone, // restrict dropZone to content div
      //singleFileUploads is on by default, so the data.files array will always have length 1
      add: function(e, data) {
        for (var k = 0; k < data.files.length; ++k) {
          if(data.files[k].type === '' && data.files[k].size == 4096) {
	    data.textStatus = 'dirorzero';
	    data.errorThrown = t('cafevdb', 'Unable to upload your file as it is a directory or has 0 bytes');
	    var fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
	    fu._trigger('fail', e, data);
	    return true; //don't upload this file but go on with next in queue
          }
        }

        var totalSize=0;
        $.each(data.originalFiles, function(i,file){
	  totalSize+=file.size;
        });

        if(totalSize > form.find('#max_upload').val()){
	  data.textStatus = 'notenoughspace';
	  data.errorThrown = t('cafevdb', 'Not enough space available');
	  var fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
	  fu._trigger('fail', e, data);
	  return false; //don't upload anything
        }

        // start the actual file upload
        var jqXHR = data.submit();

        for (var k = 0; k < data.files.length; ++k) {
          // remember jqXHR to show warning to user when he navigates away but an upload is still in progress
          if (typeof data.context !== 'undefined' && data.context.data('type') === 'dir') {
	    var dirName = data.context.data('file');
	    if(typeof FileUpload.uploadingFiles[dirName] === 'undefined') {
	      FileUpload.uploadingFiles[dirName] = {};
	    }
	    FileUpload.uploadingFiles[dirName][data.files[k].name] = jqXHR;
          } else {
	    FileUpload.uploadingFiles[data.files[k].name] = jqXHR;
          }
        }
        //show cancel button
        if($('html.lte9').length === 0 && data.dataType !== 'iframe') {
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

        //IE < 10 does not fire the necessary events for the progress bar.
        if($('html.lte9').length > 0) {
	  return;
        }
        var progressBar = container.find('div.uploadprogressbar');
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
	  //hide notification after 5 sec
	  setTimeout(function() {
	    $('#notification').fadeOut();
	  }, 10000);
        }
        delete FileUpload.uploadingFiles[data.files[0].name];
        $(window).off('beforeunload');
      },
      progress: function(e, data) {
      },
      progressall: function(e, data) {
        //IE < 10 does not fire the necessary events for the progress bar.
        if($('html.lte9').length > 0) {
	  return;
        }
        //alert('total: '+ data.total+' loaded: '+data.loaded);
        var progress = (data.loaded/data.total)*100;
        container.find('div.uploadprogressbar').progressbar('value',progress);
      },
      /**
       * called for every successful upload
       * @param e
       * @param data
       */
      done:function(e, data) {
        // handle different responses (json or body from iframe for ie)
        var response;
        if (typeof data.result === 'string') {
	  response = data.result;
        } else {
	  //fetch response from iframe
	  response = data.result[0].body.innerText;
        }
        var result = $.parseJSON(response);

        var k;
        var errors = [];
        if (typeof result.length == 'undefined') {
          if (typeof result.status != 'undefined') {
            errors.push(result.data.message);
          } else {
            errors.push(t('cafevdb', 'Unknown error uploading files'));
          }
        } else {
          for (k = 0; k < result.length; ++k) {
	    if(typeof result[k] !== 'undefined' && result[k].status === 'success') {
	      var filename = result[k].data.originalname;

	      // delete jqXHR reference
	      if (typeof data.context !== 'undefined' && data.context.data('type') === 'dir') {
	        var dirName = data.context.data('file');
	        delete FileUpload.uploadingFiles[dirName][filename];
	        if ($.assocArraySize(FileUpload.uploadingFiles[dirName]) == 0) {
	          delete FileUpload.uploadingFiles[dirName];
	        }
	      } else {
	        delete FileUpload.uploadingFiles[filename];
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
	  var fu = $(this).data('blueimp-fileupload') || $(this).data('fileupload');
	  fu._trigger('fail', e, data);
	}
      },
      /**
       * called after last upload
       * @param e
       * @param data
       */
      stop: function(e, data) {
        var form = $(options.formSelector);

        if(data.dataType !== 'iframe') {
	  container.find('div.uploadprogresswrapper input.stop').hide();
        }

        //IE < 10 does not fire the necessary events for the progress bar.
        if ($('html.lte9').length > 0) {
	  return;
        }

        var progressBar = container.find('div.uploadprogressbar');
        progressBar.progressbar('value',100);
        progressBar.fadeOut();

        if (typeof options.stopCallback == 'function') {
          options.stopCallback(e, data);
        }

        $(window).off('beforeunload');
      }
    };

    var file_upload_handler = function() {
      fileUploadStart.fileupload(fileUploadParam);
    };

    if (container.length > 0) {
      $(file_upload_handler);
    }
    $.assocArraySize = function(obj) {
      // http://stackoverflow.com/a/6700/11236
      var size = 0, key;
      for (key in obj) {
	  if (obj.hasOwnProperty(key)) size++;
      }
      return size;
    };

    container.find('div.uploadprogresswrapper input.stop').on('click', function(event) {
      FileUpload.cancelUploads();
      return false;
    });

    //add multiply file upload attribute to all browsers except konqueror (which crashes when it's used)
    if (navigator.userAgent.search(/konqueror/i)==-1 || true) {
      fileUploadStart.attr('multiple','multiple')
    }
  };
})(window, jQuery, CAFEVDB.FileUpload);

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
