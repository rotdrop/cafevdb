$(document).ready(function(){

    $('button').tipsy({gravity:'ne', fade:true});
    $('input').tipsy({gravity:'ne', fade:true});
    $('label').tipsy({gravity:'ne', fade:true});

    if (toolTips) {
        $.fn.tipsy.enable();
    } else {
        $.fn.tipsy.disable();
    }
    
    $('#expertmode').change(function(event){
	event.preventDefault();
	var post = $( "#expertmode" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'expertmode.php') , post, function(data){return;});
        if ($('#expertmode').attr('checked')) {
	    $('#expertbutton').show();
	    $('#expertbutton').css('float','left');
        } else {
	    $('#expertbutton').css('display','none');
        }
        return false;
    });

    $('#tooltips').change(function(event){
	event.preventDefault();
	var post = $( "#tooltips" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'tooltips.php') , post, function(data){return;});
        if ($('#tooltips').attr('checked')) {
            $.fn.tipsy.enable();
        } else {
            $.fn.tipsy.disable();
        }
        return false;
    });

    // 'show password' checkbox
    var tmp = $('#cafevdbkey #encryptionkey').val();
    $('#cafevdbkey #encryptionkey').showPassword();
    $('#cafevdbkey #encryptionkey').val(tmp);
    $("#cafevdbkey #button").click( function(){
        // We allow empty keys, meaning no encryption
        if ($('#cafevdbkey #password').val() != '' && (true || $('#cafevdbkey #encryptionkey').val() != '')) {
            // Serialize the data
            var post = $( "#cafevdbkey" ).serialize();
            $('#cafevdbkey #changed').hide();
            $('#cafevdbkey #error').hide();
            // Ajax foo
            $.post( OC.filePath('cafevdb', 'ajax/settings', 'encryptionkey.php'), post, function(data){
                if( data.status == "success" ){
                    $('#cafevdbkey #dbkey1').val('');
                    $('#cafevdbkey #encryptionkey').val('');
                    $('#cafevdbkey #changed').show();
                }
                else{
                    $('#cafevdbkey #error').html( data.data.message );
                    $('#cafevdbkey #error').show();
                }
            });
            return false;
        } else {
            $('#cafevdbkey #changed').hide();
            $('#cafevdbkey #error').show();
            return false;
        }

    });

    $('#exampletext').change(function(event){
	event.preventDefault();
	var post = $( "#exampletext" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'exampletext.php') , post, function(data){return;});
        return false;
    });


    // Application settings stuff

    // DB-Password
    // 'show password' checkbox
    $('#CAFEVDBpass').showPassword();
    $("#cafevdbpass #button").click( function(){
        if ($('#CAFEVDBpass').val() != '') {
            // Serialize the data
            var post = $("#cafevdbpass").serialize();
            $('#cafevdbpass #changed').hide();
            $('#cafevdbpass #error').hide();
            // Ajax foo
            $.post( OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'), post, function(data){
                if( data.status == "success" ){
                    $('#dbpass1').val('');
                    $('#CAFEVDBpass').val('');
                    $('#cafevdbpass #changed').show();
                } else{
                    $('#cafevdbpass #error').html( data.data.message );
                    $('#cafevdbpass #error').show();
                }
            });
            return false;
        } else {
            $('#cafevdbpass #changed').hide();
            $('#cafevdbpass #error').show();
            return false;
        }

    });

    // Encryption-key
    // 'show password' checkbox
    $('#CAFEVDBkey').showPassword();
    $("#cafevdbkey #button").click( function(){
        // We allow empty keys, meaning no encryption
        if (true || ($('#dbkey1').val() != '' && $('#CAFEVDBkey').val() != '')) {
            // Serialize the data
            var post = $("#cafevdbkey").serialize();
            $('#cafevdbkey #changed').hide();
            $('#cafevdbkey #error').hide();
            $('#cafevdbkey #insecure').hide();
            // Ajax foo
            $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'), post, function(data){
                if (data.status == "success"){
                    $('#cafevdbkey #changed').show();
                    if ($('#CAFEVDBkey').val() == '') {
                        $('#cafevdbkey #insecure').show();
                    }
                    $('#dbkey1').val('');
                    $('#CAFEVDBkey').val('');
                } else {
                    $('#cafevdbkey #error').html('<em>'+data.data.message+'</em>');
                    $('#cafevdbkey #error').show();
                }
            });
            return false;
        } else {
            $('#cafevdbkey #changed').hide();
            $('#cafevdbkey #error').show();
            return false;
        }
    });

    $('#cafevdbkeydistribute #button').click(function(){
        var post = $("#cafevdbkeydistribute").serialize();
        $('#cafevdbkeydistribute #msg').hide();
        $.post(OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php'), post, function(data){
            $('#cafevdbkeydistribute #msg').html('<em>'+data.data.message+'</em>');
            $('#cafevdbkeydistribute #msg').show();
        });
    });

    $('#CAFEVdbserver').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbserver" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php') , post, function(data){
	    $('#cafevdbsettings .msg').text('Finished saving: ' + data);
	});
    });

    $('#CAFEVdbname').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbname" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php') , post, function(data){
	    $('#cafevdbsettings .msg').text('Finished saving: ' + data);
	});
    });

    $('#CAFEVdbuser').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbuser" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php') , post, function(data){
	    $('#cafevdbsettings .msg').text('Finished saving: ' + data);
	});
    });

    $('#CAFEVdbpasswd').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbpasswd" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'app-settings.php') , post, function(data){
	    $('#cafevdbsettings .msg').text('Finished saving: ' + data);
	});
    });

});
