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

});
