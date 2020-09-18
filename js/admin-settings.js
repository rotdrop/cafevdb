$(document).ready(function(){

    $('#orchestraUserGroup').blur(function(event){
	event.preventDefault();

        $('#cafevdb-admin-settings .msg').hide();
	var post = $("#cafevdb-admin-settings").serialize();
	$.post(
          OC.generateUrl('/apps/cafevdb/settings/admin/set'),
          post).done(function(data) {
          console.log(data);
	  $('#cafevdb-admin-settings .msg').html(data.message);
          $('#cafevdb-admin-settings .msg').show();
        })
        .fail(function(jqXHR) {
          console.log(JSON.parse(jqXHR.responseText));
	  $('#cafevdb-admin-settings .msg').html(JSON.parse(jqXHR.responseText).message);
          $('#cafevdb-admin-settings .msg').show();
        });
    });
});
