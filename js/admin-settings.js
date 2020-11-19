var CAFEVDB = CAFEVDB || { appName: 'cafevdb' };

/**Fetch data from an error response.
 *
 * @param xhr jqXHR, see fail() method of jQuery ajax.
 *
 * @param status from jQuery, see fail() method of jQuery ajax.
 *
 * @param errorThrown, see fail() method of jQuery ajax.
 */
CAFEVDB.ajaxFailData = function(xhr, status, errorThrown) {
  const ct = xhr.getResponseHeader("content-type") || "";
  var data = {
    'error': errorThrown,
    'status': status,
    'message': t(CAFEVDB.appName, 'Unknown JSON error response to AJAX call: {status} / {error}')
  };
  if (ct.indexOf('html') > -1) {
    console.debug('html response', xhr, status, errorThrown);
    console.debug(xhr.status);
    data.message = t(CAFEVDB.appName, 'HTTP error response to AJAX call: {code} / {error}',
                     {'code': xhr.status, 'error': errorThrown});
  } else if (ct.indexOf('json') > -1) {
    const response = JSON.parse(xhr.responseText);
    //console.info('XHR response text', xhr.responseText);
    //console.log('JSON response', response);
    data = {...data, ...response };
  } else {
    console.log('unknown response');
  }
  //console.info(data);
  return data;
};

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
        .fail(function(xhr, status, errorThrown) {
          const response = CAFEVDB.ajaxFailData(xhr, status, errorThrown);
          console.log(response);
          if (response.message) {
	    $('#cafevdb-admin-settings .msg').html(response.message);
            $('#cafevdb-admin-settings .msg').show();
          }
        });
    });
});
