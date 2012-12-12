$(document).ready(function(){
    
    $('#expertmode').change(function(event){
	event.preventDefault();
	var post = $( "#expertmode" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax/settings', 'expertmode.php') , post, function(data){return;});
        return false;
    });
    //$('#expertmode').chosen();

});
