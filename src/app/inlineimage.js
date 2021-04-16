/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import generateUrl from './generate-url.js';
import * as Dialogs from './dialogs.js';
import * as Ajax from './ajax.js';

require('jquery-jcrop');
require('../legacy/nextcloud/jquery/octemplate.js');
require('inlineimage.css');

const photoUpload = function(wrapper, filelist) {
  if (!filelist) {
    Dialogs.alert(t(appName, 'No files selected for upload.'), t(appName, 'Error'));
    return;
  }
  const file = filelist[0];
  // var target = $('#file_upload_target');
  const form = $('#file_upload_form');
  // var totalSize=0;
  if (file.size > $('#max_upload').val()) {
    Dialogs.alert(t(appName, 'The file you are trying to upload exceed the maximum size of {max} for file uploads on this server.', { max: $('#max_upload_human').val() }), t(appName, 'Error'));
    return;
  }

  const uploadData = new FormData(form[0]);
  $.ajax({
    url: generateUrl('image/fileupload'),
    data: uploadData,
    type: 'POST',
    processData: false,
    contentType: false, // 'multipart/form-data' // ???
  })
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, ['tmpKey'])) {
        return;
      }
      editPhoto(wrapper, data.tmpKey);
    });
};

/**
 * Hide or show edit and delete buttons depending on whether there is
 * a real image.
 *
 * @param {Object} wrapper jQuery object for the wrapper-div.
 */
const photoLoadHandlers = function(wrapper) {
  const phototools = wrapper.find('.phototools');
  if (wrapper.data('PHOTO')) {
    phototools.find('.delete').show();
    phototools.find('.edit').show();
  } else {
    phototools.find('.delete').hide();
    phototools.find('.edit').hide();
  }
};

const photoCloudSelected = function(wrapper, path) {
  const imageInfo = wrapper.data('imageInfo');
  $.post(generateUrl('image/cloud'), $.extend({ path }, imageInfo))
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, ['tmpKey'])) {
        return;
      }
      editPhoto(wrapper, data.tmpKey);
    });
};

/**
 * Install the special upload and crop buttons inside the given
 * container.
 *
 * @param {jQuery} wrapper jQuery object containing image and
 * controls.
 *
 * @param {Function} callback TBD.
 */
const photoLoad = function(wrapper, callback) {
  const phototools = wrapper.find('.phototools');
  const imageInfo = wrapper.data('imageInfo');
  console.info('IMAGEINFO', imageInfo);
  const ownerId = imageInfo.ownerId;
  const imageId = imageInfo.imageId;
  const joinTable = imageInfo.joinTable;
  const imageSize = imageInfo.imageSize;
  // first determine if there is a photo ...
  $.get(
    generateUrl('image/' + joinTable + '/' + ownerId, {
      imageId,
      metaData: true,
    }))
    .fail(function(xhr, status, errorThrown) {
      if (xhr.status !== Ajax.httpStatus.NOT_FOUND) { // ok, no photo yet
        Ajax.handleError(xhr, status, errorThrown);
      }
      wrapper.data('PHOTO', false);
      photoLoadHandlers(wrapper);
    })
    .done(function(data) {
      wrapper.data('PHOTO', true);
      photoLoadHandlers(wrapper);
    })
    .always(function() {
      phototools.find('li a').cafevTooltip('hide');
      wrapper.addClass('loading').addClass('wait');
      wrapper.removeData('image');
      const image = $(new Image());
      wrapper.data('image', image);

      let requestParams = '?metaData=false';
      if (+imageId > 0) {
        requestParams += '&imageId=' + imageId;
      }
      requestParams += '&imageSize=' + imageSize
        + '&refresh=' + Math.random() // disable browser-caching
        + '&requesttoken=' + encodeURIComponent(OC.requestToken);
      image
        .on('load', function() {
          console.info('LOAD');
          wrapper.find('img.' + appName + '_inline_image').remove();
          image.addClass(appName + '_inline_image');
          image.addClass('zoomable');
          image.insertAfter(phototools);
          wrapper.css('width', $(this).get(0).width + 10);
          wrapper.removeClass('loading').removeClass('wait');
          image.fadeIn(callback);
        })
        .on('error', function(event) {
          console.info('ERROR');

          // BIG FAT NOTE: the "event" data passed to this error handler
          // just does not contain any information about the error-data
          // returned by the server. So only information is "there was an
          // error".

          Dialogs.alert(t(appName, 'Could not open image.'), t(appName, 'Error'), callback);
        })
        .attr('src', generateUrl('image/' + joinTable + '/' + ownerId + requestParams));
      console.info('IMAGESRC', image.attr('src'));
      photoLoadHandlers(wrapper);
    });
};

/**
 * Edit (crop) the current photo.
 *
 * @param {Object} wrapper jQuery object containing image and
 * controls.
 */
const photoEditCurrent = function(wrapper) {
  const imageInfo = wrapper.data('imageInfo');
  $.post(generateUrl('image/edit'), imageInfo)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
      wrapper.removeClass('wait');
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, [ 'tmpKey' ])) {
        return;
      }
      editPhoto(wrapper, data.tmpKey);
    });
};

const editPhoto = function(wrapper, tmpKey) {
  const imageInfo = wrapper.data('imageInfo');

  $.fn.cafevTooltip.remove();
  // Simple event handler, called from onChange and onSelect
  // event handlers, as per the Jcrop invocation above
  const showCoords = function(c) {
    $('#x1').val(c.x);
    $('#y1').val(c.y);
    $('#x2').val(c.x2);
    $('#y2').val(c.y2);
    $('#w').val(c.w);
    $('#h').val(c.h);
  };

  const clearCoords = function() {
    $('#coords input').val('');
  };

  const $cropBoxTmpl = $('#cropBoxTemplate');
  $('body').append('<div id="edit_photo_dialog"></div>');
  const $cropBoxForm = $cropBoxTmpl.octemplate($.extend({ tmpKey }, imageInfo))
  $(new Image())
    .on('load', function() {
      // var x = 5, y = 5, w = this.width-10, h = this.height-10;
      const x = 0;
      const y = 0;
      const w = this.width;
      const h = this.height;
      $(this).attr('id', 'cropbox');
      $(this).prependTo($cropBoxForm).fadeIn();
      const boxW = Math.min(imageInfo.imageSize, window.innerWidth * 0.95);
      const boxH = Math.min(imageInfo.imageSize, window.innerHeight * 0.80);

      $(this).Jcrop({
        onChange: showCoords,
        onSelect: showCoords,
        onRelease: clearCoords,
        maxSize: [window.innerWidth, window.innerHeight],
        bgColor: 'black',
        bgOpacity: 0.4,
        boxWidth: boxW,
        boxHeight: boxH,
        setSelect: [x + w, y + h, x, y],
        // aspectRatio: 0.8
      });
      $('#edit_photo_dialog').html($cropBoxForm).cafevDialog({
        modal: true,
        closeOnEscape: true,
        title: t(appName, 'Edit inline image'),
        resizable: 'true',
        resize: 'auto',
        height: 'auto',
        width: 'auto',
        buttons: [
          {
            text: t(appName, 'Ok'),
            click() {
              savePhoto(wrapper, $(this));
              $(this).dialog('close');
            },
          },
          {
            text: t(appName, 'Cancel'),
            click() { $(this).dialog('close'); },
          },
        ],
        close(event, ui) {
          $(this).dialog('destroy').remove();
          $('#edit_photo_dialog').remove();
        },
        open(event, ui) {
          showCoords({ x, y, x2: x + w - 1, y2: y + h - 1, w, h });
        },
      });
    })
    .on('error', function() {
      // no detailed information available here.
      OC.notify({ message: t(appName, 'Error loading inline image.') });
    })
    .attr(
      'src', generateUrl(
        'image/cache/'
	  + tmpKey
	  + '?requesttoken='
          + encodeURIComponent(OC.requestToken)));
};

const savePhoto = function(wrapper, $dlg) {
  const form = $dlg.find('.cropform');
  const q = form.serialize();
  console.log('savePhoto', q);
  $.post(generateUrl('image/save'), q)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
      wrapper.data('PHOTO', false);
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, ['imageId'])) {
        return;
      }
      const imageInfo = wrapper.data('imageInfo');
      imageInfo.imageId = data.imageId;
      createImageUploadForm(imageInfo);
      photoLoad(wrapper);
      wrapper.data('PHOTO', true);
    });
};

const deletePhoto = function(wrapper) {
  const imageInfo = wrapper.data('imageInfo');
  wrapper.addClass('wait');
  $.post(generateUrl('image/delete'), imageInfo)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
      wrapper.removeClass('wait');
    })
    .done(function(data) {
      imageInfo.imageId = -1;
      createImageUploadForm(imageInfo);
      photoLoad(wrapper);
      wrapper.data('PHOTO', false);
      wrapper.removeClass('wait');
    });
};

/**
 * Install the special upload and crop buttons inside the given
 * container.
 *
 * @param {Object} wrapper jQuery object containing image and
 * controls.
 */
const attachHandlers = function(wrapper) {
  const phototools = wrapper.find('.phototools');
  phototools.find('li a').click(function() {
    $(this).cafevTooltip('hide');
  });
  wrapper.hover(
    function() {
      phototools.slideDown(200);
    },
    function() {
      phototools.slideUp(200);
    }
  );
  phototools.hover(
    function() {
      $(this).removeClass('transparent');
    },
    function() {
      $(this).addClass('transparent');
    }
  );
  phototools.find('.upload').on('click', function(event) {
    $('#file_upload_start').trigger('click');
    event.stopImmediatePropagation();
    return false;
  });
  phototools.find('.cloud').on('click', function(event) {
    Dialogs.filePicker(
      t(appName, 'Select image'),
      function(path) {
        photoCloudSelected(wrapper, path);
      },
      false,
      ['image\\/.*'],
      true);
    event.stopImmediatePropagation();
    return false;
  });
  phototools.find('.delete').on('click', function(event) {
    const $self = $(this);
    $self.cafevTooltip('hide');
    deletePhoto(wrapper);
    $self.hide();
    event.stopImmediatePropagation();
    return false;
  });
  phototools.find('.edit').on('click', function(event) {
    $(this).cafevTooltip('hide');
    photoEditCurrent(wrapper);
    event.stopImmediatePropagation();
    return false;
  });
  phototools.find('li a').cafevTooltip();

  // Profile image upload handling
  // New profile image selected
  $('#file_upload_start').on('change', function() {
    photoUpload(this.files);
  });
  wrapper.bind('dragover', function(event) {
    $(event.target).addClass('droppable');
    event.stopPropagation();
    event.preventDefault();
  });
  wrapper.bind('dragleave', function(event) {
    $(event.target).removeClass('droppable');
  });
  wrapper.bind('drop', function(event) {
    event.stopPropagation();
    event.preventDefault();
    $(event.target).removeClass('droppable');
    photoUploadDragDrop(wrapper, event.originalEvent.dataTransfer.files);
  });
};

/**
 * Upload images with drag'n drop
 *
 * @param {Object} wrapper Wrapping div with data.
 *
 * @param {Array} files Files to be uploaded.
 */
const photoUploadDragDrop = function(wrapper, files) {
  const imageInfo = wrapper.data('imageInfo');
  if (files.length < 1) {
    return;
  }
  const file = files[0];
  if (file.size > $('#max_upload').val()) {
    Dialogs.alert(t(appName, 'The file you are trying to upload exceed the maximum size for file uploads on this server.'), t(appName, 'Upload too large'));
    return;
  }
  if (file.type.indexOf('image') !== 0) {
    Dialogs.alert(t(appName, 'Only image files can be used as profile picture.'), t(appName, 'Wrong file type'));
    return;
  }
  const xhr = new XMLHttpRequest();

  if (!xhr.upload) {
    Dialogs.alert(t(appName, 'Your browser doesn\'t support AJAX upload. Please click on the profile picture to select a photo to upload.'), t(appName, 'Error'));
  }
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) { // done
      const data = $.parseJSON(xhr.responseText);
      if (xhr.status === Ajax.httpStatus.OK) {
        if (!Ajax.validateResponse(data, ['tmpKey'])) {
          return;
        }
        editPhoto(wrapper, data.tmpKey);
      } else {
        Ajax.handleError(xhr, xhr.status, Ajax.httpStatus[xhr.status]);
      }
    }
  };

  xhr.upload.onprogress = function(e) {
    if (e.lengthComputable) {
      // const progress = Math.round((e.loaded * 100) / e.total);
      // if (_progress != 100){
      // }
    }
  };

  xhr.open(
    'POST',
    generateUrl('image/dragndrop', {
      ownerId: wrapper.data('ownerId'),
      imageId: wrapper.data('imageId'),
      joinTable: wrapper.data('joinTable'),
      imageSize: wrapper.data('imageSize'),
      requesttoken: encodeURIComponent(OC.requestToken),
      imageFile: encodeURIComponent(file.name),
    }),
    true);
  xhr.setRequestHeader('Cache-Control', 'no-cache');
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.setRequestHeader('X-File-Name', encodeURIComponent(file.name));
  xhr.setRequestHeader('X-File-Size', file.size);
  xhr.setRequestHeader('Content-Type', file.type);
  xhr.send(file);
};

const uploadFormId = function(imageInfo) {
  return 'image-upload-form-' + imageInfo.ownerId + '-' + imageInfo.imageId;
};

const createImageUploadForm = function(imageInfo) {
  $('#' + imageInfo.formId).remove();
  imageInfo.formId = uploadFormId(imageInfo);
  $('#' + imageInfo.formId).remove();
  const $imageUploadTemplate = $('#imageUploadTemplate');
  const $imageUploadForm = $imageUploadTemplate.octemplate(imageInfo);
  $('body').append($imageUploadForm);
};

/**
 * The document-ready handler, should also be called after
 * dynamically injecting html that needs the image upload
 * functionality.
 *
 * @param {Object} container jQuery object containing the
 * photo-wrapper div.
 *
 * @param {Function} callback TBD.
 */
const photoReady = function(container, callback) {
  const wrapper = container.find('.' + appName + '_inline_image_wrapper');
  const imageInfo = wrapper.data('imageInfo');
  callback = callback || function() {};
  if (imageInfo.joinTable === undefined
      || imageInfo.ownerId === undefined
      || +imageInfo.ownerId <= 0) {
    // still run the callback
    callback();
    return;
  }
  if (+imageInfo.imageSize <= 0) {
    imageInfo.imageSize = 400;
  }
  if (+imageInfo.imageId <= 0) {
    imageInfo.imageId = -1;
  }
  createImageUploadForm(imageInfo);
  photoLoad(wrapper, callback);
  attachHandlers(wrapper);
};

const photoPopup = function(image) {
  const overlay = $('<div id="photooverlay" style="width:auto;height:auto;"></div>');
  const imgClone = $(image).clone();
  imgClone.removeClass('zoomable');
  overlay.append(imgClone);
  $.fn.cafevTooltip.remove(); // get rid of disturbing tooltips.
  overlay.cafevDialog({
    title: t(appName, 'Photo Zoom'),
    position: {
      my: 'middle top+5%',
      at: 'middle top',
      of: '#app-content',
    },
    width: 'auto',
    height: 'auto',
    modal: true,
    closeOnEscape: false,
    dialogClass: 'photo-zoom no-close transparent-titlebar no-border content-box',
    resizable: false,
    open() {
      const dialogHolder = $(this);
      const dialogWidget = dialogHolder.dialog('widget');
      dialogHolder.on('click', function() {
        // @TODO should close when clicking anywhere apart from the move handle
        dialogHolder.dialog('close');
      });
      dialogHolder.imagesLoaded(function() {
        const title = dialogWidget.find('.ui-dialog-titlebar');
        const titleBarHeight = title.is(':visible') ? title.outerHeight() : '0';
        const newHeight = dialogWidget.height() - titleBarHeight;
        const newWidth = dialogWidget.width();

        const height = dialogHolder.height();
        const width = dialogHolder.width();
        const outerHeight = dialogHolder.outerHeight(true);
        const outerWidth = dialogHolder.outerWidth(true);

        let imageHeight = imgClone.height();
        const imageWidth = imgClone.width();
        const imageOuterHeight = imgClone.outerHeight(true);
        const imageOuterWidth = imgClone.outerWidth(true);

        console.log('inner w/h', width, height);
        console.log('outer w/h', outerWidth, outerHeight);
        console.log('new w/h', newWidth, newHeight);
        console.log('img w/h', imageWidth, imageHeight);
        console.log('img o w/h', imageOuterWidth, imageOuterHeight);

        /* newHeight and newWidth are the relevant sizes
         * which must hold the entire stuff.
         */
        const vOffset = outerHeight - height;
        const hOffset = outerWidth - width;
        const imgVOffset = imageOuterHeight - imageHeight;
        const imgHOffset = imageOuterWidth - imageWidth;
        const imageMaxHeight = Math.round(newHeight - vOffset - imgVOffset);
        const imageMaxWidth = Math.round(newWidth - hOffset - imgHOffset);
        const imageRatio = imageWidth / imageHeight;

        console.log('off h/v', hOffset, vOffset);
        console.log('imgoff h/v', imgHOffset, imgVOffset);
        console.log('img max w/h', imageMaxWidth, imageMaxHeight);

        if (imageHeight > imageMaxHeight) {
          if (imageMaxHeight * imageRatio > imageMaxWidth) {
            // scale width
            console.log('scale width to', imageMaxWidth);
            imgClone.width(imageMaxWidth);
          } else {
            // scale height
            console.log('scale height to', imageMaxHeight);
            imgClone.height(imageMaxHeight);
          }
        } else if (imageWidth > imageMaxWidth) {
          console.log('scale width to', imageMaxWidth);
          imgClone.width(imageMaxWidth);
        }

        console.log('new image w/h', imgClone.width(), imgClone.height());

        imageHeight = imgClone.height();
        if (imageHeight < imageMaxHeight) {
          console.log('shift image down by', (imageMaxHeight - imageHeight) / 2);
          // imgClone.css('margin-top', "+="+((imageMaxHeight - imageHeight) / 2));
        }
        // dialogWidget.css('width', imgClone.width() + imgHOffset + hOffset);
        // dialogWidget.css('width', 'auto');
      });
    },
    close() {
      const dialogHolder = $(this);
      // container.html(img);
      dialogHolder.dialog('close');
      dialogHolder.dialog('destroy').remove();
    },
  });
};

export {
  photoPopup as popup,
  photoReady as ready,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
