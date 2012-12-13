$(document).ready(function(){

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
