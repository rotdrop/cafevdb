$(document).ready(function(){

    $('#CAFEVgroup').blur(function(event){
	event.preventDefault();
        $('#cafevdbadmin .msg').hide();
	var post = $( "#CAFEVgroup" ).serialize();
	$.post(OC.filePath('cafevdb', 'ajax', 'admin-settings.php'),
               post,
               function(data){
                   if (data.status == 'success') {
	               $('#cafevdbadmin .msg').html(data.data.message);
                   } else {
	               $('#cafevdbadmin .msg').html(data.data.message);
                   }
                   $('#cafevdbadmin .msg').show();
	       }, 'json');
    });

});
