$(document).ready(function(){

	$('#expertmode').blur(function(event){
		event.preventDefault();
		var post = $( "#expertmode" ).serialize();
		$.post( OC.filePath('cafevdb', 'ajax', 'personal-settings.php') , post, function(data){
			$('#cafevdb .msg').text('Finished saving: ' + data);
		});
	});



});
