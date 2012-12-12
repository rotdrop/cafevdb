$(document).ready(function(){

	$('#dbserver').blur(function(event){
		event.preventDefault();
		var post = $( "#dbserver" ).serialize();
		$.post( OC.filePath('cafevdb', 'ajax', 'admin-settings.php') , post, function(data){
			$('#cafevdb .msg').text('Finished saving: ' + data);
		});
	});



});
