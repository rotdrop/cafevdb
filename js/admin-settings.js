$(document).ready(function(){

    $('#CAFEVgroup').blur(function(event){
	event.preventDefault();
	var post = $( "#CAFEVgroup" ).serialize();
	$.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php') , post, function(data){
	    $('#cafevdbadmin .msg').text('Finished saving: ' + data);
	});
    });

});
