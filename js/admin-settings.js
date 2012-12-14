$(document).ready(function(){

    // 'show password' checkbox
    $('#CAFEVDBkey').showPassword();
    $("#cafevdbkey>#button").click( function(){
        if ($('#dbkey1').val() != '' && $('#CAFEVDBkey').val() != '') {
            // Serialize the data
            var post = $( "#CAFEVDBkey" ).serialize();
            $('#cafevdbkey>#changed').hide();
            $('#cafevdbkey>#error').hide();
            // Ajax foo
            $.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php'), post, function(data){
                if( data.status == "success" ){
                    $('#dbkey1').val('');
                    $('#CAFEVDBkey').val('');
                    $('#cafevdbkey>#changed').show();
                }
                else{
                    $('#cafevdbkey>#error').html( data.data.message );
                    $('#cafevdbkey>#error').show();
                }
            });
            return false;
        } else {
            $('#cafevdbkey>#changed').hide();
            $('#cafevdbkey>#error').show();
            return false;
        }

    });

    // 'show password' checkbox
    $('#CAFEVDBpass').showPassword();
    $("#cafevdbpass>#button").click( function(){
        if ($('#dbpass1').val() != '' && $('#CAFEVDBpass').val() != '') {
            // Serialize the data
            var post = $( "#CAFEVDBpass" ).serialize();
            $('#cafevdb>#changed').hide();
            $('#cafevdb>#error').hide();
            // Ajax foo
            $.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php'), post, function(data){
                if( data.status == "success" ){
                    $('#dbpass1').val('');
                    $('#CAFEVDBpass').val('');
                    $('#cafevdb>#changed').show();
                }
                else{
                    $('#cafevdb>#error').html( data.data.message );
                    $('#cafevdb>#error').show();
                }
            });
            return false;
        } else {
            $('#cafevdbpass>#changed').hide();
            $('#cafevdbpass>#error').show();
            return false;
        }

    });

    $('#CAFEVgroup').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVgroup" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php') , post, function(data){
	    $('#cafevdb .msg').text('Finished saving: ' + data);
	});
    });

    $('#CAFEVdbserver').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbserver" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php') , post, function(data){
	    $('#cafevdb .msg').text('Finished saving: ' + data);
	});
    });

    $('#CAFEVdbname').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbname" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php') , post, function(data){
	    $('#cafevdb .msg').text('Finished saving: ' + data);
	});
    });

    $('#CAFEVdbuser').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbuser" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php') , post, function(data){
	    $('#cafevdb .msg').text('Finished saving: ' + data);
	});
    });

    $('#CAFEVdbpasswd').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVdbpasswd" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php') , post, function(data){
	    $('#cafevdb .msg').text('Finished saving: ' + data);
	});
    });

});
