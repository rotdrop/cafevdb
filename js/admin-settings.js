$(document).ready(function(){

    $('#orchestraUserGroup').blur(function(event){
	event.preventDefault();

        $('#cafevdb-admin-settings .msg').hide();
	var post = $("#cafevdb-admin-settings").serialize();
	$.post(
          OC.generateUrl('/apps/cafevdb/settings/admin/set'),
          post)
        .done(function(data) {
          console.log(data);
	  $('#cafevdb-admin-settings .msg').html(data.message);
          $('#cafevdb-admin-settings .msg').show();
        })
        .fail(function(jqXHR) {
          const response = JSON.parse(jqXHR.responseText);
          console.log(response);
          if (response.message) {
	    $('#cafevdb-admin-settings .msg').html(response.message);
            $('#cafevdb-admin-settings .msg').show();
          }
        });
    });
});
