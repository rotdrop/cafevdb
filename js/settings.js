$(document).ready(function() {

    $('button').tipsy({gravity:'ne', fade:true});
    $('input').tipsy({gravity:'ne', fade:true});
    $('label').tipsy({gravity:'ne', fade:true});

    if (toolTips) {
        $.fn.tipsy.enable();
    } else {
        $.fn.tipsy.disable();
    }
    
    $('#expertmode').change(function(event) {
	event.preventDefault();
	var post = $("#expertmode").serialize();
	$.post(OC.filePath('cafevdb', 'ajax/settings', 'expertmode.php') , post, function(data) {return;});
        if ($('#expertmode').attr('checked')) {
	    $('#expertbutton').show();
	    $('#expertbutton').css('float','left');
        } else {
	    $('#expertbutton').css('display','none');
        }
        return false;
    });

    $('#debugmode').change(function(event) {
	event.preventDefault();
	var post = $("#debugmode").serialize();
	$.post(OC.filePath('cafevdb', 'ajax/settings', 'debugmode.php') , post, function(data) {return;});
        if ($('#debugmode').attr('checked')) {
	    $('#debugbutton').show();
	    $('#debugbutton').css('float','left');
        } else {
	    $('#debugbutton').css('display','none');
        }
        return false;
    });

    $('#tooltips').change(function(event) {
	event.preventDefault();
	var post = $("#tooltips").serialize();
	$.post(OC.filePath('cafevdb', 'ajax/settings', 'tooltips.php') , post, function(data) {return;});
        if ($('#tooltips').attr('checked')) {
            $.fn.tipsy.enable();
        } else {
            $.fn.tipsy.disable();
        }
        return false;
    });

    // 'show password' checkbox
    var tmp = $('#userkey #encryptionkey').val();
    $('#userkey #encryptionkey').showPassword();
    $('#userkey #encryptionkey').val(tmp);
    $("#userkey #button").click(function() {
        // We allow empty keys, meaning no encryption
        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
        if ($('#userkey #password').val() != '' && (true || $('#userkey #encryptionkey').val() != '')) {
            // Serialize the data
            var post = $("#userkey").serialize();
            // Ajax foo
            $.post(OC.filePath('cafevdb', 'ajax/settings', 'encryptionkey.php'),
                   post, function(data) {
                if(data.status == "success") {
                    $('#userkey input[name="dbkey1"]').val('');
                    $('#userkey input[name="userkey"]').val('');
                    $('#userkey input[name="userkey-clone"]').val('');
                    $('#userkey #changed').show();
                } else{
                    $('#userkey #error').html(data.data.message);
                    $('#userkey #error').show();
                }
            });
            return false;
        } else {
            $('#userkey #error').show();
            return false;
        }

    });

    $('#exampletext').change(function(event) {
	event.preventDefault();
	var post = $("#exampletext").serialize();
	$.post(OC.filePath('cafevdb', 'ajax/settings', 'exampletext.php') , post, function(data) {return;});
        return false;
    });

    ///////////////////////////////////////////////////////////////////////////
    //
    // Application settings stuff
    //
    ///////////////////////////////////////////////////////////////////////////

    // Encryption-key
    // 'show password' checkbox
    $('#systemkey #key').showPassword();
    $("#systemkey #button").click(function() {
        // We allow empty keys, meaning no encryption
        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
        if ($('#systemkey #oldkey').val() != $('#systemkey #key').val()) {
            // Serialize the data
            var post = $("#systemkey").serialize();
            // Ajax foo
            $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'), post, function(data) {
                if (data.status == "success") {
                    $('#systemkey #changed').show();
                    if ($('#systemkey #key').val() == '') {
                        $('#systemkey #insecure').show();
                    }
                    $('#systemkey input[name="oldkey"]').val('');
                    $('#systemkey input[name="systemkey"]').val('');
                    $('#systemkey input[name="systemkey-clone"]').val('');
                    if ($('#systemkey input[name="systemkey-clone"]').is(':visible')) {
                        $('#systemkey-show').removeAttr('checked');
                        $('#systemkey-show').click();
                        $('#systemkey-show').removeAttr('checked');
                   }
                } else {
                    $('#systemkey #error').html(data.data.message);
                    $('#systemkey #error').show();
                }
            });
            return false;
        } else {
            $('#systemkey #equal').show();
            if ($('#systemkey #oldkey').val() == '') {
                $('#systemkey #insecure').show();
            }
            return false;
        }
    });

    $('#keydistribute #button').click(function() {
        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
        $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
               $(this),
               function(data) {
            $('#keydistribute #msg').html(data.data.message);
            $('#keydistribute #msg').show();
        });
    });

    ///////////////////////////////////////////////////////////////////////////
    //
    // data-base
    //
    ///////////////////////////////////////////////////////////////////////////

    $('#dbgeneral :input').blur(function(event) {
	event.preventDefault();
        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
	$.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
               $(this),
               function(data) {
                   if (data.status == "success") {
	               $('#dbsettings #msg').html(data.data.message);
	               $('#dbsettings #msg').show();
                   }
                   return false;
	       }, 'json');
    });

    // DB-Password
    // 'show password' checkbox
    $('#dbpassword #dbpassword').showPassword();
    $("#dbpassword #button").click(function() {
        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
        if ($('#dbpassword #password').val() != '') {
            // Serialize the data
            var post = $("#dbpassword").serialize();
            // Ajax foo
            $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
                    post, function(data) {
                if(data.status == "success") {
                    //$('#dbpassword input[name="dbpass1"]').val('');
                    $('#dbpassword input[name="password"]').val('');
                    $('#dbpassword input[name="password-clone"]').val('');
                    $('#dbpassword #changed').show();
                } else{
                    $('#dbpassword #error').html(data.data.message);
                    $('#dbpassword #error').show();
                }
            });
            return false;
        } else {
            $('#dbpassword #error').show();
            return false;
        }
    });

    ///////////////////////////////////////////////////////////////////////////
    //
    // Events, calendars
    //
    ///////////////////////////////////////////////////////////////////////////

    $('#eventsettings #calendars :input, #eventsettings #sharinguser :input').blur(function(event) {
        event.preventDefault();
        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
	$.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
               $(this),
               function(data) {
	           $('#eventsettings #msg').html(data.data.message);
	           $('#eventsettings #msg').show();
	       });
    });

    // Share-owner's password
    // 'show password' checkbox
    $('#sharingpassword #password').showPassword();
    $('#sharingpassword #change').click(function(event) {
        event.preventDefault();

        // We allow empty keys, meaning no encryption
        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
	$('#eventsettings #msg').empty();

        // Generate the request by hand
        var post = Array();

        var input1 = $('#sharingpassword input[name="sharingpassword-clone"]');
        var input2 = $('#sharingpassword input[name="sharingpassword"]');

        // Check both inputs fors consistency, because we are fiddling
        // with an auto-generated password below
        var pass1 = input1.val();
        var pass2 = input2.val();

        if (pass1 != pass2) {
            var type = new Object();
            type['name']  = 'error';
            type['value'] = 'Visible and invisible passwords do not match.';
            post.push(type);
        } else if (pass1 == '') {
            var type = new Object();
            type['name']  = 'error';
            type['value'] = 'Password is empty.';
            post.push(type);
        } else {
            var type = new Object();
            type['name']  = 'sharingpassword';
            type['value'] = pass1;
            post.push(type);
        }
        // Ajax foo
        $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
               post,
               function(data) {
                   if (data.status == "success") {
                       input1.val('');
                       input2.val('');
                   }
                   $('#eventsettings #msg').html(data.data.message);
                   $('#eventsettings #msg').show();
                   return false;
               });
        return false;
    });

    $('#sharingpassword #generate').click(function(event) {
        event.preventDefault();

        $('div.statusmessage').hide();
        $('span.statusmessage').hide();
	$('#eventsettings #msg').empty();
        if ($('#sharingpassword #password').is(':visible')) {
            $('#sharingpassword-show').attr('checked','checked');
            $('#sharingpassword-show').click();
            $('#sharingpassword-show').attr('checked','checked');
        }
        // Ajax foo
        $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'),
               $(this),
               function(data) {
                   if (data.status == "success") {
                       // Make sure both inputs have the same value
                       $('#sharingpassword input[name="sharingpassword-clone"]').val(data.data.message);
                       $('#sharingpassword input[name="sharingpassword"]').val(data.data.message);
                   } else {
                       $('#eventsettings #msg').html(data.data.message);
                       $('#eventsettings #msg').show();
                   }
                   return false;
               });

        return false;
    });
        

});
