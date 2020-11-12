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
  var self = this;
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
  var timer = setTimeout(function() {
    self.notifier.fadeOut();
    if(params.timeouthandler && $.isFunction(params.timeouthandler)) {
      params.timeouthandler(self.notifier.data(dataid));
      self.notifier.off('click');
      self.notifier.removeData(dataid);
    }
  }, params.timeout && $.isNumeric(params.timeout) ? parseInt(params.timeout)*1000 : 10000);
  var dataid = timer.toString();
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
  var Photo = function() {};
  Photo.itemId   = -1;
  Photo.imageItemTable = '';
  Photo.imageSize  = 400;
  Photo.data = {PHOTO:false};
  Photo.uploadPhoto = function(filelist) {
    var self = CAFEVDB.Photo;
    if (!filelist) {
      CAFEVDB.dialogs.alert(t('cafevdb', 'No files selected for upload.'), t('cafevdb', 'Error'));
      return;
    }
    var file = filelist[0];
    var target = $('#file_upload_target');
    var form = $('#file_upload_form');
    var totalSize=0;
    if (file.size > $('#max_upload').val()) {
      CAFEVDB.dialogs.alert(t('cafevdb', 'The file you are trying to upload exceed the maximum size for file uploads on this server.'), t('cafevdb', 'Error'));
      return;
    } else {
      target.unbind('load');
      target.load(function() {
        var response= jQuery.parseJSON(target.contents().text());
        if (response != undefined && response.status == 'success') {
          self.editPhoto(response.data.itemId, response.data.tmp);
          //alert('File: ' + file.tmp + ' ' + file.name + ' ' + file.mime);
        } else {
          CAFEVDB.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
        }
      });
      form.submit();
    }
  };
  Photo.loadPhotoHandlers = function() {
    var phototools = $('#phototools');
    if (this.data.PHOTO) {
      phototools.find('.delete').show();
      phototools.find('.edit').show();
    } else {
      phototools.find('.delete').hide();
      phototools.find('.edit').hide();
    }
  };
  Photo.cloudPhotoSelected = function(path) {
    var self = CAFEVDB.Photo;
    $.getJSON(OC.generateUrl('/apps/cafevdb/image/cloud/'+path),
              { 'item_id': self.itemId,
                'image_size': self.imageSize
              }, function(jsondata) {
                if (jsondata.status == 'success') {
                  //alert(jsondata.data.page);
                  self.editPhoto(jsondata.data.itemId, jsondata.data.tmp);
                  $('#edit_photo_dialog_img').html(jsondata.data.page);
                } else {
                  CAFEVDB.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
                }
              });
  };
  Photo.loadPhoto = function(itemId, imageItemTable, imageSize, callback) {
    var self = CAFEVDB.Photo;
    if (typeof itemId !== 'undefined') {
      self.itemId = itemId;
    }
    if (typeof imageItemTable !== 'undefined') {
      self.imageItemTable = imageItemTable;
    }
    if (typeof imageSize !== 'undefined') {
      self.imageSize = imageSize;
    }
    $.getJSON(OC.generateUrl('/apps/cafevdb/image/'+self.imageItemTable+'/'+self.itemId),
              { 'imageSize': self.imageSize },
	      function(jsondata) {
                if (jsondata.status == 'success') {
                  self.data.PHOTO = true;
                } else {
                  // Can happen if there is no photo yet.
                  //CAFEVDB.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
                  self.data.PHOTO = false;
                }
                self.loadPhotoHandlers();
              });
    $('#phototools li a').cafevTooltip('hide');
    var wrapper = $('#cafevdb_inline_image_wrapper');
    wrapper.addClass('loading').addClass('wait');
    delete this.photo;
    this.photo = new Image();
    console.log('image tag', this.photo);

    const sizeParam = '?ImageSize='+self.imageSize;
    const refreshParam = '&refresh='+Math.random(); // this disables browser-caching via URL change.

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
    .on('error', function () {
      // notify the user that the image could not be loaded
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
    .attr('src', OC.generateUrl('/apps/cafevdb/image/'+self.imageItemTable+'/'+self.itemId+sizeParam+refreshParam));
    this.loadPhotoHandlers();
  };
  Photo.editCurrentPhoto = function() {
    var self = CAFEVDB.Photo;
    $.getJSON(OC.generateUrl('/apps/cafevdb/image/'+self.imageItemTable+'/'+self.itemId),
              { 'image_size': self.imageSize },
              function(jsondata) {
                if (jsondata.status == 'success') {
                  self.editPhoto(jsondata.data.itemId, jsondata.data.tmp);
                  //$('#edit_photo_dialog_img').html(jsondata.data.page);
                } else {
                  wrapper.removeClass('wait');
                  CAFEVDB.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
                }
              });
  };
  Photo.editPhoto = function(imageId, tmpkey) {
    //console.log('editPhoto', imageId, tmpkey);
    $.fn.cafevTooltip.remove();
    // Simple event handler, called from onChange and onSelect
    // event handlers, as per the Jcrop invocation above
    var showCoords = function(c) {
      $('#x1').val(c.x);
      $('#y1').val(c.y);
      $('#x2').val(c.x2);
      $('#y2').val(c.y2);
      $('#w').val(c.w);
      $('#h').val(c.h);
    };

    var clearCoords = function() {
      $('#coords input').val('');
    };

    var self = CAFEVDB.Photo;
    if(!self.$cropBoxTmpl) {
      self.$cropBoxTmpl = $('#cropBoxTemplate');
    }
    $('body').append('<div id="edit_photo_dialog"></div>');
    var $dlg = self.$cropBoxTmpl.octemplate({
      ItemId: imageId,
      ImageItemTable: self.imageItemTable,
      ImageSize: self.imageSize,
      tmpkey: tmpkey
    });

    var cropphoto = new Image();
    $(cropphoto).load(function () {
      //var x = 5, y = 5, w = this.width-10, h = this.height-10;
      var x = 0, y = 0, w = this.width, h = this.height;
      $(this).attr('id', 'cropbox');
      $(this).prependTo($dlg).fadeIn();
      var photoDlg = $('#edit_photo_dialog');

      var boxW = Math.min(self.imageSize, window.innerWidth*0.95);
      var boxH = Math.min(self.imageSize, window.innerHeight*0.80);

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
    }).error(function () {
      OC.notify({message:t('cafevdb','Error loading inline image.')});
    }).attr('src', OC.linkTo('cafevdb', 'tmpimage.php')+'?tmpkey='+tmpkey);
  };
  Photo.savePhoto = function($dlg) {
    var self = CAFEVDB.Photo;
    var form = $dlg.find('#cropform');
    var q = form.serialize();
    console.log('savePhoto', q);
    $.post(OC.generateUrl('/apps/cafevdb/image/save'), q, function(response) {
      var jsondata = $.parseJSON(response);
      console.log('savePhoto, jsondata', typeof jsondata);
      if(jsondata && jsondata.status === 'success') {
        // load cropped photo.
        self.loadPhoto();
        self.data.PHOTO = true;
      } else {
        if(!jsondata) {
          OC.notify({message:t('cafevdb', 'Network or server error. Please inform administrator.')});
        } else {
          OC.notify({message: jsondata.data.message});
        }
      }
    });
  };
  Photo.deletePhoto = function() {
    var self = CAFEVDB.Photo;
    var wrapper = $('#cafevdb_inline_image_wrapper');
    wrapper.addClass('wait');
    $.post(OC.generateUrl('/apps/cafevdb/image/delete'),
              { 'join_table': self.imageItemTable,
                'owner_id': self.itemId,
                'image_id': -1
              },
              function(jsondata) {
                if (jsondata.status == 'success') {
                  //alert(jsondata.data.page);
                  self.loadPhoto();
                }
                else{
                  wrapper.removeClass('wait');
                  //CAFEVDB.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
                }
              });
  };
  Photo.loadHandlers = function() {
    var self = CAFEVDB.Photo;
    var phototools = $('#phototools');
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
    phototools.find('.upload').click(function(event) {
      $('#file_upload_start').trigger('click');
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('.cloud').click(function(event) {
      OC.dialogs.filepicker(t('cafevdb', 'Select image'), self.cloudPhotoSelected, false, 'image', true);
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('.delete').click(function(event) {
      $(this).cafevTooltip('hide');
      self.deletePhoto();
      $(this).hide();
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('.edit').click(function(event) {
      $(this).cafevTooltip('hide');
      self.editCurrentPhoto();
      event.stopImmediatePropagation();
      return false;
    });
    phototools.find('li a').cafevTooltip();

    // Profile image upload handling
    // New profile image selected
    $('#file_upload_start').change(function() {
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
    // Upload function for dropped contact photos files. Should go in the Contacts class/object.
    $.fileUpload = function(files){
      if (files.length < 1) {
        return;
      }
      var file = files[0];
      if(file.size > $('#max_upload').val()){
        CAFEVDB.dialogs.alert(t('cafevdb','The file you are trying to upload exceed the maximum size for file uploads on this server.'), t('cafevdb','Upload too large'));
        return;
      }
      if (file.type.indexOf("image") != 0) {
        CAFEVDB.dialogs.alert(t('cafevdb','Only image files can be used as profile picture.'), t('cafevdb','Wrong file type'));
        return;
      }
      var xhr = new XMLHttpRequest();

      if (!xhr.upload) {
        CAFEVDB.dialogs.alert(t('cafevdb', 'Your browser doesn\'t support AJAX upload. Please click on the profile picture to select a photo to upload.'), t('cafevdb', 'Error'))
      }
      xhr.onreadystatechange = function() {
        if (xhr.readyState == 4){
          var response = $.parseJSON(xhr.responseText);
          if(response.status == 'success') {
            if(xhr.status == 200) {
              CAFEVDB.Photo.editPhoto(response.data.itemId, response.data.tmp);
            } else {
              CAFEVDB.dialogs.alert(xhr.status + ': ' + xhr.responseText, t('cafevdb', 'Error'));
            }
          } else {
            CAFEVDB.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
          }
        }
      };

      xhr.upload.onprogress = function(e){
        if (e.lengthComputable){
          var _progress = Math.round((e.loaded * 100) / e.total);
          //if (_progress != 100){
          //}
        }
      };
      xhr.open('POST', OC.generateUrl('/apps/cafevdb/image/upload'+'?owner_id='+CAFEVDB.Photo.itemId+'&image_size='+CAFEVDB.Photo.imageSize+'&requesttoken='+oc_requesttoken+'&image_file='+encodeURIComponent(file.name)), true);
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
  Photo.ready = function(itemId, imageItemTable, callback) {
    var idField = $('#file_upload_form input[name="ItemId"]');
    var tableField = $('#file_upload_form input[name="ImageItemTable"]');
    if (typeof itemId == 'undefined') {
      itemId = idField.val();
    } else {
      idField.val(itemId);
    }
    if (typeof imageItemTable == 'undefined') {
      imageItemTable = tableField.val();
    } else {
      tableField.val(imageItemTable);
    }
    if (typeof itemId !== 'undefined' && typeof imageItemTable != 'undefined' && itemId >= 0) {
      var imageSize = $('input[name="ImageSize"]').val();
      if (typeof imageSize == 'undefined') {
        imageSize = 400;
      }
      this.loadHandlers();
      this.loadPhoto(itemId, imageItemTable, imageSize, callback);
    }
    $(this.uploadDragDrop);
  };

  Photo.popup = function(image) {
    var overlay = $('<div id="photooverlay" style="width:auto;height:auto;"></div>');
    var imgClone = $(image).clone();
    imgClone.removeClass('zoomable');
    overlay.append(imgClone);
    $.fn.cafevTooltip.remove(); // get rid of disturbing tooltips.
    var popup = overlay.cafevDialog({
      title: t('cafevdb', 'Photo Zoom'),
      position: { my: "middle top+5%",
                  at: "middle bottom",
                  of: "#controls" },
      width: 'auto',
      height: 'auto',
      modal: true,
      closeOnEscape: false,
      dialogClass: 'photo-zoom no-close transparent-titlebar no-border',
      resizable: false,
      open: function() {
        var dialogHolder = $(this);
        var dialogWidget = dialogHolder.dialog('widget');
        dialogHolder.click(function() {
          dialogHolder.dialog('close');
        });
        dialogHolder.imagesLoaded(function() {
          var title = dialogWidget.find('.ui-dialog-titlebar');
          var titleBarHeight = title.is(':visible') ? title.outerHeight() : '0';
          var newHeight = dialogWidget.height() - titleBarHeight;
          var newWidth = dialogWidget.width();

          var height = dialogHolder.height();
          var width = dialogHolder.width();
          var outerHeight = dialogHolder.outerHeight(true);
          var outerWidth = dialogHolder.outerWidth(true);

          var imageHeight = imgClone.height();
          var imageWidth  = imgClone.width();
          var imageOuterHeight = imgClone.outerHeight(true);
          var imageOuterWidth  = imgClone.outerWidth(true);

          console.log('inner w/h', width, height);
          console.log('outer w/h', outerWidth, outerHeight);
          console.log('new w/h', newWidth, newHeight);
          console.log('img w/h', imageWidth, imageHeight);
          console.log('img o w/h', imageOuterWidth, imageOuterHeight);

          /* newHeight and newWidth are the relevant sizes
           * which must hold the entire stuff.
           */
          var vOffset    = outerHeight - height;
          var hOffset    = outerWidth - width;
          var imgVOffset = imageOuterHeight - imageHeight;
          var imgHOffset = imageOuterWidth - imageWidth;
          var imageMaxHeight = Math.round(newHeight - vOffset - imgVOffset);
          var imageMaxWidth  = Math.round(newWidth - hOffset - imgHOffset);
          var imageRatio = imageWidth / imageHeight;

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
            imgClone.css('margin-top', "+="+((imageMaxHeight - imageHeight) / 2));
          }
          dialogWidget.css('width', imgClone.width() + imgHOffset + hOffset);
          //dialogWidget.css('width', 'auto');
        });
      },
      close: function() {
        var dialogHolder = $(this);
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
// js3-indent-level: 2 ***
// js3-label-indent-offset: -2 ***
// End: ***
