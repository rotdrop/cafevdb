CAFEVDB.Photo = {
    memberId:-1,
    data:{PHOTO:false},
    uploadPhoto:function(filelist) {
        var self = CAFEVDB.Photo;
	if (!filelist) {
	    OC.dialogs.alert(t('cafevdb', 'No files selected for upload.'), t('cafevdb', 'Error'));
	    return;
	}
	var file = filelist[0];
	var target = $('#file_upload_target');
	var form = $('#file_upload_form');
	var totalSize=0;
	if (file.size > $('#max_upload').val()) {
	    OC.dialogs.alert(t('cafevdb', 'The file you are trying to upload exceed the maximum size for file uploads on this server.'), t('cafevdb', 'Error'));
	    return;
	} else {
	    target.load(function() {
		var response= jQuery.parseJSON(target.contents().text());
		if (response != undefined && response.status == 'success') {
		    self.editPhoto(response.data.memberId, response.data.tmp);
		    //alert('File: ' + file.tmp + ' ' + file.name + ' ' + file.mime);
		} else {
		    OC.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
		}
	    });
	    form.submit();
	}
    },
    loadPhotoHandlers:function() {
	var phototools = $('#phototools');
	if (this.data.PHOTO) {
	    phototools.find('.delete').show();
	    phototools.find('.edit').show();
	} else {
	    phototools.find('.delete').hide();
	    phototools.find('.edit').hide();
	}
    },
    cloudPhotoSelected:function(path) {
        var self = CAFEVDB.Photo;
	$.getJSON(OC.filePath('cafevdb', 'ajax', 'memberphoto/oc_photo.php'),{'path':path,'MemberId':self.memberId},function(jsondata) {
	    if (jsondata.status == 'success') {
		//alert(jsondata.data.page);
		self.editPhoto(jsondata.data.memberId, jsondata.data.tmp)
		$('#edit_photo_dialog_img').html(jsondata.data.page);
	    } else {
		OC.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
	    }
	});
    },
    loadPhoto:function(memberId) {
	var self = CAFEVDB.Photo;
        if (typeof memberId !== 'undefined') {
            self.memberId = memberId;
        }
	$.getJSON(OC.filePath('cafevdb', 'ajax', 'memberphoto/currentphoto.php'),{'MemberId':self.memberId}, function(jsondata) {
	    if (jsondata.status == 'success') {
                self.data.PHOTO = true;
	    } else {
		//OC.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
                self.data.PHOTO = false;
	    }
            self.loadPhotoHandlers();
	});
	var refreshstr = '&refresh='+Math.random();
	$('#phototools li a').tipsy('hide');
	var wrapper = $('#cafevdb_musician_photo_wrapper');
	wrapper.addClass('loading').addClass('wait');
	delete this.photo;
	this.photo = new Image();
	$(this.photo).load(function () {
	    $('img.cafevdb_musician_photo').remove()
	    $(this).addClass('cafevdb_musician_photo');
	    wrapper.css('width', $(this).get(0).width + 10);
	    wrapper.removeClass('loading').removeClass('wait');
	    $(this).insertAfter($('#phototools')).fadeIn();
	}).error(function () {
	    // notify the user that the image could not be loaded
	    OC.dialogs.alert(t('cafevdb', 'Could not open member picture.'), t('cafevdb', 'Error'));
	    //self.notify({message:t('cafevdb', 'Error loading member picture.')});
	}).attr('src', OC.linkTo('cafevdb', 'memberphoto.php')+'?MemberId='+self.memberId+refreshstr);
	this.loadPhotoHandlers();
    },
    editCurrentPhoto:function() {
        var self = CAFEVDB.Photo;
	$.getJSON(OC.filePath('cafevdb', 'ajax', 'memberphoto/currentphoto.php'),{'MemberId':self.memberId},function(jsondata) {
	    if (jsondata.status == 'success') {
		//alert(jsondata.data.page);
		self.editPhoto(jsondata.data.memberId, jsondata.data.tmp);
		$('#edit_photo_dialog_img').html(jsondata.data.page);
	    } else {
		wrapper.removeClass('wait');
		OC.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
	    }
	});
    },
    editPhoto:function(id, tmpkey) {
	//alert('editPhoto: ' + tmpkey);
	$.getJSON(OC.filePath('cafevdb', 'ajax', 'memberphoto/cropphoto.php'),{'tmpkey':tmpkey,'MemberId':id, 'requesttoken':oc_requesttoken},function(jsondata) {
	    if (jsondata.status == 'success') {
		//alert(jsondata.data.page);
		$('#edit_photo_dialog_img').html(jsondata.data.page);
	    } else {
		OC.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
	    }
	});
	if ($('#edit_photo_dialog').dialog('isOpen') == true) {
	    $('#edit_photo_dialog').dialog('moveToTop');
	} else {
	    $('#edit_photo_dialog').dialog('open');
	}
    },
    savePhoto:function() {
        var self = CAFEVDB.Photo;
	var target = $('#crop_target');
	var form = $('#cropform');
	var wrapper = $('#cafevdb_musician_photo_wrapper');
	wrapper.addClass('wait');
	form.submit();
	target.load(function() {
	    var response=jQuery.parseJSON(target.contents().text());
	    if (response != undefined && response.status == 'success') {
		// load cropped photo.
		self.loadPhoto();
		self.data.PHOTO = true;
	    } else {
		OC.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
		wrapper.removeClass('wait');
	    }
	});
    },
    deletePhoto:function() {
        var self = CAFEVDB.Photo;
	var wrapper = $('#cafevdb_musician_photo_wrapper');
	wrapper.addClass('wait');
	$.getJSON(OC.filePath('cafevdb', 'ajax', 'memberphoto/deletephoto.php'),{'MemberId':this.memberId},function(jsondata) {
	    if (jsondata.status == 'success') {
		//alert(jsondata.data.page);
		self.loadPhoto();
	    }
	    else{
		wrapper.removeClass('wait');
		OC.dialogs.alert(jsondata.data.message, t('cafevdb', 'Error'));
	    }
	});
    },
    loadHandlers:function() {
        var self = CAFEVDB.Photo;
	var phototools = $('#phototools');
	$('#phototools li a').click(function() {
	    $(this).tipsy('hide');
	});
	$('#cafevdb_musician_photo_wrapper').hover(
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
	phototools.find('.upload').click(function() {
	    $('#file_upload_start').trigger('click');
	});
	phototools.find('.cloud').click(function() {
	    OC.dialogs.filepicker(t('cafevdb', 'Select photo'), self.cloudPhotoSelected, false, 'image', true);
	});
	phototools.find('.delete').click(function() {
	    $(this).tipsy('hide');
	    self.deletePhoto();
	    $(this).hide();
	});
	phototools.find('.edit').click(function() {
	    $(this).tipsy('hide');
	    self.editCurrentPhoto();
	});
	phototools.find('li a').tipsy();

	/* Initialize the photo edit dialog */
	$('#edit_photo_dialog').dialog({
	    autoOpen: false, modal: true, height: 'auto', width: 'auto'
	});
	$('#edit_photo_dialog' ).dialog( 'option', 'buttons', [
	    {
		text: "Ok",
		click: function() {
		    self.savePhoto(this);
		    $(this).dialog('close');
		}
	    },
	    {
		text: "Cancel",
		click: function() { $(this).dialog('close'); }
	    }
	] );

	// Profile picture upload handling
	// New profile picture selected
	$('#file_upload_start').change(function() {
	    self.uploadPhoto(this.files);
	});
	$('#cafevdb_musician_photo_wrapper').bind('dragover',function(event) {
	    $(event.target).addClass('droppable');
	    event.stopPropagation();
	    event.preventDefault();
	});
	$('#cafevdb_musician_photo_wrapper').bind('dragleave',function(event) {
	    $(event.target).removeClass('droppable');
	});
	$('#cafevdb_musician_photo_wrapper').bind('drop',function(event) {
	    event.stopPropagation();
	    event.preventDefault();
	    $(event.target).removeClass('droppable');
	    $.fileUpload(event.originalEvent.dataTransfer.files);
	});
    },
    dummy:0
};

$(document).ready(function() {
    var memberId = $('input[name="MemberId"]').val();
    if (typeof memberId !== 'undefined') {
        CAFEVDB.Photo.loadHandlers();
        CAFEVDB.Photo.loadPhoto(memberId);
    }
});

