$(document).ready(function(){
    
    $('#expertmode').change(function(event){
	event.preventDefault();
	var post = $( "#expertmode" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'expertmode.php') , post, function(data){return;});
        if ($('#expertmode').attr('checked')) {
	    $('#expertbutton').show();
	    $('#expertbutton').css('float','left');
	    // $('#settingsbutton').hide();
	    // $('#expertbutton').css('display','inherit');
	    // $('#expertbutton').css('width','auto');
	    // $('#expertbutton').css('font-size','12px');
	    // $('#expertbutton').css('float','right');
	    // $('#settingsbutton').css('display','inherit');
	    // $('#settingsbutton').css('width','auto');
	    // $('#settingsbutton').css('font-size','12px');
	    // $('#settingsbutton').css('float','right');
        } else {
	    $('#expertbutton').css('display','none');
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
