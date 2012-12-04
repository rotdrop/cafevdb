$(document).ready(function(){



	$('#somesetting').blur(function(event){
		event.preventDefault();
		var post = $( "#somesetting" ).serialize();
		$.post( OC.filePath('cafevdb', 'ajax', 'seturl.php') , post, function(data){
			$('#cafevdb .msg').text('Finished saving: ' + data);
		});
	});



});
