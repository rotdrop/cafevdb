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

    $('#exampletext').change(function(event){
	event.preventDefault();
	var post = $( "#exampletext" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'exampletext.php') , post, function(data){return;});
        return false;
    });

});
