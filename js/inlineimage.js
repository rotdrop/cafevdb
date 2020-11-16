/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * Simply notifier
 * Arguments:
 * @param message The text message to show.
 * @param timeout The timeout in seconds before the notification disappears. Default 10.
 * @param timeouthandler A function to run on timeout.
 * @param clickhandler A function to run on click. If a timeouthandler is given it will be cancelled on click.
 * @param data An object that will be passed as argument to the timeouthandler and clickhandler functions.
 * @param cancel If set cancel all ongoing timer events and hide the notification.
 */
OC.notify = function(params) {
  const self = this;
  if(!self.notifier) {
    self.notifier = $('#notification');
  }
  if(params.cancel) {
    self.notifier.off('click');
    for(var id in self.notifier.data()) {
      if($.isNumeric(id)) {
        clearTimeout(parseInt(id));
      }
    }
    self.notifier.text('').fadeOut().removeData();
    return;
  }
  self.notifier.text(params.message);
  self.notifier.fadeIn().css('display', 'inline');
  self.notifier.on('click', function() { $(this).fadeOut();});
  const timer = setTimeout(function() {
    self.notifier.fadeOut();
    if(params.timeouthandler && $.isFunction(params.timeouthandler)) {
      params.timeouthandler(self.notifier.data(dataid));
      self.notifier.off('click');
      self.notifier.removeData(dataid);
    }
  }, params.timeout && $.isNumeric(params.timeout) ? parseInt(params.timeout)*1000 : 10000);
  const dataid = timer.toString();
  if(params.data) {
    self.notifier.data(dataid, params.data);
  }
  if(params.clickhandler && $.isFunction(params.clickhandler)) {
    self.notifier.on('click', function() {
      clearTimeout(timer);
      self.notifier.off('click');
      params.clickhandler(self.notifier.data(dataid));
      self.notifier.removeData(dataid);
    });
  }
};

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  'use strict';
  const Photo = function() {};
  Photo.ownerId   = -1;
  Photo.imageItmTable = '';
  Photo.imageSize  = 400;
  Photo.data = {PHOTO:false};
  Photo.uploadPhoto = function(filelist) {
    const self = CAFEVDB.Photo;
    if (!filelist) {
      CAFEVDB.dialogs.alert(t('cafevdb', 'No files selected for upload.'), t('cafevdb', 'Error'));
      return;
    }
    const file = filelist[0];
    //var target = $('#file_upload_target');
    const form = $('#file_upload_form');
    //var totalSize=0;
    if (file.size > $('#max_upload').val()) {
      CAFEVDB.dialogs.alert(t('cafevdb', 'The file you are trying to upload exceed the maximum size of {max} for file uploads on this server.', { max: $('#max_upload_human').val()}), t('cafevdb', 'Error'));
      return;
    } else {
      const uploadData = new FormData(form[0]);
      $.ajax({
        url: OC.generateUrl('/apps/cafevdb/image/fileupload'),
        data: uploadData,
        type: 'POST',
        processData: false,
        contentType: false // 'multipart/form-data' // ???
      })
      .fail(function(xhr, status, errorThrown) {
        CAFEVDB.handleAjaxError(xhr, status, errorThrown);
      })
      .done(function(data) {
        if (!CAFEVDB.validateAjaxResponse(data, [ 'ownerId', 'tmpKey' ])) {
          return;
        }
        self.editPhoto(data.ownerId, data.tmpKey);
      });
    }
  };
  Photo.loadPhotoHandlers = function() {
    const phototools = $('#phototools');
    if (this.data.PHOTO) {
      phototools.find('.delete').show();
      phototools.find('.edit').show();
    } else {
      phototools.find('.delete').hide();
      phototools.find('.edit').hide();
    }
  };
  Photo.cloudPhotoSelected = function(path) {
    const self = CAFEVDB.Photo;
    $.post(OC.generateUrl('/apps/cafevdb/image/cloud'),
           { 'path': path,
             'ownerId': self.ownerId,
             'joinTable': self.joinTable,
             'imageSize': self.imageSize })
    .fail(function(xhr, status, errorThrown) {
      CAFEVDB.handleAjaxError(xhr, status, errorThrown);
    })
    .done(function(data) {
      if (!CAFEVDB.validateAjaxResponse(data, [ 'ownerId', 'tmpKey' ])) {
        return;
      }
      self.editPhoto(data.ownerId, data.tmpKey);
    });
  };
  Photo.loadPhoto = function(ownerId, joinTable, imageSize, callback) {
    const self = CAFEVDB.Photo;
    if (typeof ownerId !== 'undefined') {
      self.ownerId = ownerId;
    }
    if (typeof joinTable !== 'undefined') {
      self.joinTable = joinTable;
    }
    if (typeof imageSize !== 'undefined') {
      self.imageSize = imageSize;
    }
    // first determine if there is a photo ...
    $.get(
      OC.generateUrl('/apps/cafevdb/image/' + self.joinTable+ '/' + self.ownerId),
      { 'metaData': true })
     .fail(function(xhr, status, errorThrown) {
       if (xhr.status != CAFEVDB.httpStatus.NOT_FOUND) { // ok, no photo yet
         CAFEVDB.handleAjaxError(xhr, status, errorThrown);
       }
       self.data.PHOTO = false;
       self.loadPhotoHandlers();
     })
    .done(function(data) {
      self.data.PHOTO = true;
      self.loadPhotoHandlers();
    });
    $('#phototools li a').cafevTooltip('hide');
    const wrapper = $('#cafevdb_inline_image_wrapper');
    wrapper.addClass('loading').addClass('wait');
    delete this.photo;
    this.photo = new Image();
    console.log('image tag', this.photo);

    const requestParams =
      '?metaData=false'
      + '&refresh=' + Math.random() // disable browser-caching
      + '&requesttoken=' + encodeURIComponent(OC.requestToken);
    $(this.photo)
    .on('load', function () {
      $('img.cafevdb_inline_image').remove();
      $(this).addClass('cafevdb_inline_image');
      $(this).addClass('zoomable');
      $(this).insertAfter($('#phototools'));
      wrapper.css('width', $(this).get(0).width + 10);
      wrapper.removeClass('loading').removeClass('wait');
      $(this).fadeIn(function() {
        if (typeof callback == 'function') {
          callback();
        }
      });
    })
    .on('error', function (event) {

      // BIG FAT NOTE: the "event" data passed to this error handler
      // just does not contain any information about the error-data
      // returned by the server. So only information is "there was an
      // error".

      CAFEVDB.dialogs.alert(
        t('cafevdb', 'Could not open image.'), t('cafevdb', 'Error'),
        function () {
          if (typeof callback == 'function') {
            // Still the callback needs to run ...
            callback();
          }
        });
      //self.notify({message:t('cafevdb', 'Error loading image.')});
    })
    .attr('src', OC.generateUrl('/apps/cafevdb/image/'+self.joinTable+'/'+self.ownerId+requestParams));
    this.loadPhotoHandlers();
  };
  Photo.editCurrentPhoto = function() {
    const self = CAFEVDB.Photo;
    $.post(OC.generateUrl('/apps/cafevdb/image/edit'),
           { 'ownerId': self.ownerId,
             'joinTable': self.joinTable,
             'imageSize': self.imageSize })
     .fail(function(xhr, status, errorThrown) {
       CAFEVDB.handleAjaxError(xhr, status, errorThrown);
       wrapper.removeClass('wait');
     })
     .done(function(data) {
       if (!CAFEVDB.validateAjaxResponse(data, [ 'ownerId', 'tmpKey' ])) {
         return;
       }
       self.editPhoto(data.ownerId, data.tmpKey);
     });
  };
  Photo.editPhoto = function(ownerId, tmpKey) {
    //console.log('editPhoto', ownerId, tmpKey);
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

    const self = CAFEVDB.Photo;
    if(!self.$cropBoxTmpl) {
      self.$cropBoxTmpl = $('#cropBoxTemplate');
    }
    $('body').append('<div id="edit_photo_dialog"></div>');
    const $dlg = self.$cropBoxTmpl.octemplate({
      ownerId: ownerId,
      joinTable: self.joinTable,
      imageSize: self.imageSize,
      tmpKey: tmpKey
    });

    const cropphoto = new Image();
    $(cropphoto)
    .on('load', function () {
      //var x = 5, y = 5, w = this.width-10, h = this.height-10;
      const x = 0, y = 0, w = this.width, h = this.height;
      $(this).attr('id', 'cropbox');
      $(this).prependTo($dlg).fadeIn();
      const photoDlg = $('#edit_photo_dialog');

      const boxW = Math.min(self.imageSize, window.innerWidth*0.95);
      const boxH = Math.min(self.imageSize, window.innerHeight*0.80);

      $(this).Jcrop({
        onChange:       showCoords,
        onSelect:       showCoords,
        onRelease:      clearCoords,
        maxSize:        [window.innerWidth, window.innerHeight],
        bgColor:        'black',
        bgOpacity:      .4,
        boxWidth:       boxW,
        boxHeight:      boxH,
        setSelect:      [ x+w, y+h, x, y ]//,
        //aspectRatio: 0.8
      });
      $('#edit_photo_dialog').html($dlg).cafevDialog({
        modal: true,
        closeOnEscape: true,
        title:  t('cafevdb', 'Edit inline image'),
        resizable: 'true',
        resize: 'auto',
        height: 'auto',
        width: 'auto',
        buttons: [ { text: t('cafevdb', "Ok"),
                     click: function() {
                       self.savePhoto($(this));
                       $(this).dialog('close');
                     }
                   },
                   { text: t('cafevdb', "Cancel"),
                     click: function() { $(this).dialog('close'); }
                   },
                 ],
        close: function(event, ui) {
          $(this).dialog('destroy').remove();
          $('#edit_photo_dialog').remove();
        },
        open: function(event, ui) {
          showCoords({x:x,y:y,x2:x+w-1,y2:y+h-1,w:w,h:h});
        }
      });
    })
    .on('error', function () {
      // no detailed information available here.
      OC.notify({message:t('cafevdb','Error loading inline image.')});
    })
    .attr(
      'src', OC.generateUrl(
	'/apps/cafevdb/image/cache/'
	  + tmpKey
	  + '?requesttoken='+encodeURIComponent(OC.requestToken)));
  };
  Photo.savePhoto = function($dlg) {
    const self = CAFEVDB.Photo;
    const form = $dlg.find('#cropform');
    const q = form.serialize();
    console.log('savePhoto', q);
    $.post(OC.generateUrl('/apps/cafevdb/image/save'), q)
    .fail(function(xhr, status, errorThrown) {
      CAFEVDB.handleAjaxError(xhr, status, errorThrown);
      self.data.PHOTO = false;
    })
    .done(function(data) {
      console.log(data); // unused
      self.loadPhoto();
      self.data.PHOTO = true;
    });
  };
  Photo.deletePhoto = function() {
    const self = CAFEVDB.Photo;
    const wrapper = $('#cafevdb_inline_image_wrapper');
    wrapper.addClass('wait');
    $.post(OC.generateUrl('/apps/cafevdb/image/delete'),
           { 'joinTable': self.joinTable,
             'ownerId': self.ownerId
           })
    .fail(function(xhr, status, errorThrown) {
      CAFEVDB.handleAjaxError(xhr, status, errorThrown);
      wrapper.removeClass('wait');
    })
    .done(function(data) {
      self.loadPhoto();
    });
  };
  Photo.loadHandlers = function() {
    const self = CAFEVDB.Photo;
    const phototools = $('#phototools');
    $('#phototools li a').click(function() {
      $(this).cafevTooltip('hide');
    });
    $('#cafevdb_inline_image_wrapper').hover(
      function () {
        phototools.slideDown(200);
      },
      function () {
        phototools.slideUp(200);
      }
    );
    phototools.hover(
      function () {
        $(this).removeClass('transparent');
      },
      function () {
        $(this).addClass('transparent');
      }
    );
    phototools.find('.upload').on('click', function(event) {
      $('#file_upload_start').trigger('click');
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('.cloud').on('click', function(event) {
      OC.dialogs.filepicker(t('cafevdb', 'Select image'), self.cloudPhotoSelected, false, [ 'image\\/.*' ], true);
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('.delete').on('click', function(event) {
      $(this).cafevTooltip('hide');
      self.deletePhoto();
      $(this).hide();
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('.edit').on('click', function(event) {
      $(this).cafevTooltip('hide');
      self.editCurrentPhoto();
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('li a').cafevTooltip();

    // Profile image upload handling
    // New profile image selected
    $('#file_upload_start').on('change', function() {
      self.uploadPhoto(this.files);
    });
    $('#cafevdb_inline_image_wrapper').bind('dragover',function(event) {
      $(event.target).addClass('droppable');
      event.stopPropagation();
      event.preventDefault();
    });
    $('#cafevdb_inline_image_wrapper').bind('dragleave',function(event) {
      $(event.target).removeClass('droppable');
    });
    $('#cafevdb_inline_image_wrapper').bind('drop',function(event) {
      event.stopPropagation();
      event.preventDefault();
      $(event.target).removeClass('droppable');
      $.fileUpload(event.originalEvent.dataTransfer.files);
    });
  };
  Photo.uploadDragDrop = function() {
    // Upload function for dropped images
    $.fileUpload = function(files){
      if (files.length < 1) {
        return;
      }
      const file = files[0];
      if(file.size > $('#max_upload').val()){
        CAFEVDB.dialogs.alert(t('cafevdb', 'The file you are trying to upload exceed the maximum size for file uploads on this server.'), t('cafevdb','Upload too large'));
        return;
      }
      if (file.type.indexOf("image") != 0) {
        CAFEVDB.dialogs.alert(t('cafevdb', 'Only image files can be used as profile picture.'), t('cafevdb','Wrong file type'));
        return;
      }
      const xhr = new XMLHttpRequest();

      if (!xhr.upload) {
        CAFEVDB.dialogs.alert(t('cafevdb', 'Your browser doesn\'t support AJAX upload. Please click on the profile picture to select a photo to upload.'), t('cafevdb', 'Error'))
      }
      xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) { // done
          const response = $.parseJSON(xhr.responseText);
          if (xhr.status == CAFEVDB.httpStatus.OK) {
            CAFEVDB.Photo.editPhoto(response.ownerId, response.tmpKey);
          } else {
            CAFEVDB.handleAjaxError(xhr, xhr.status, CAFEVDB.httpStatus[xhr.status]);
          }
        }
      };

      xhr.upload.onprogress = function(e){
        if (e.lengthComputable){
          const _progress = Math.round((e.loaded * 100) / e.total);
          //if (_progress != 100){
          //}
        }
      };

      xhr.open('POST', OC.generateUrl(
        '/apps/cafevdb/image/dragndrop'
        + '?ownerId=' + CAFEVDB.Photo.ownerId
        + '&joinTable=' + CAFEVDB.Photo.joinTable
        + '&imageSize=' + CAFEVDB.Photo.imageSize
        + '&requesttoken=' + encodeURIComponent(OC.requestToken)
        + '&imageFile=' + encodeURIComponent(file.name)),
               true);
      xhr.setRequestHeader('Cache-Control', 'no-cache');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('X-File-Name', encodeURIComponent(file.name));
      xhr.setRequestHeader('X-File-Size', file.size);
      xhr.setRequestHeader('Content-Type', file.type);
      xhr.send(file);
    }
  };

  /**The document-ready handler, should also be called after
   * dynamically injecting html that needs the image upload
   * functionality.
   */
  Photo.ready = function(ownerId, joinTable, callback) {
    const ownerIdField = $('#file_upload_form input[name="ownerId"]');
    const joinTableField = $('#file_upload_form input[name="joinTable"]');
    if (typeof ownerId == 'undefined') {
      ownerId = ownerIdField.val();
    } else {
      ownerIdField.val(ownerId);
    }
    if (typeof joinTable == 'undefined') {
      joinTable = joinTableField.val();
    } else {
      joinTableField.val(joinTable);
    }
    if (typeof ownerId !== 'undefined' && typeof joinTable != 'undefined' && ownerId >= 0) {
      var imageSize = $('input[name="ImageSize"]').val();
      if (typeof imageSize == 'undefined') {
        imageSize = 400;
      }
      this.loadHandlers();
      this.loadPhoto(ownerId, joinTable, imageSize, callback);
    }
    $(this.uploadDragDrop);
  };

  Photo.popup = function(image) {
    const overlay = $('<div id="photooverlay" style="width:auto;height:auto;"></div>');
    const imgClone = $(image).clone();
    imgClone.removeClass('zoomable');
    overlay.append(imgClone);
    $.fn.cafevTooltip.remove(); // get rid of disturbing tooltips.
    const popup = overlay.cafevDialog({
      title: t('cafevdb', 'Photo Zoom'),
      position: { my: "middle top+5%",
                  at: "middle bottom",
                  of: "#controls" },
      width: 'auto',
      height: 'auto',
      modal: true,
      closeOnEscape: false,
      dialogClass: 'photo-zoom no-close transparent-titlebar no-border content-box',
      resizable: false,
      open: function() {
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

          var imageHeight = imgClone.height();
          const imageWidth  = imgClone.width();
          const imageOuterHeight = imgClone.outerHeight(true);
          const imageOuterWidth  = imgClone.outerWidth(true);

          console.log('inner w/h', width, height);
          console.log('outer w/h', outerWidth, outerHeight);
          console.log('new w/h', newWidth, newHeight);
          console.log('img w/h', imageWidth, imageHeight);
          console.log('img o w/h', imageOuterWidth, imageOuterHeight);

          /* newHeight and newWidth are the relevant sizes
           * which must hold the entire stuff.
           */
          const vOffset    = outerHeight - height;
          const hOffset    = outerWidth - width;
          const imgVOffset = imageOuterHeight - imageHeight;
          const imgHOffset = imageOuterWidth - imageWidth;
          const imageMaxHeight = Math.round(newHeight - vOffset - imgVOffset);
          const imageMaxWidth  = Math.round(newWidth - hOffset - imgHOffset);
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
            //imgClone.css('margin-top', "+="+((imageMaxHeight - imageHeight) / 2));
          }
          //dialogWidget.css('width', imgClone.width() + imgHOffset + hOffset);
          //dialogWidget.css('width', 'auto');
        });
      },
      close: function() {
        const dialogHolder = $(this);
        //container.html(img);
        dialogHolder.dialog('close');
        dialogHolder.dialog('destroy').remove();
      },
    });
  };

  CAFEVDB.Photo = Photo;

})(window, jQuery, CAFEVDB);

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
