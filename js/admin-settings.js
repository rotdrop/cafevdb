/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

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

$(function(){

    const $container = $('#' + CAFEVDB.appName + '-admin-settings');
    const $msg = $container.find('.msg');

    $container.find('input').blur(function(event){
        const $self = $(this);

        const name = $self.attr('name');
        const value = $self.val();

        $msg.hide();

	$.post(
          OC.generateUrl('/apps/cafevdb/settings/admin/set/' + name),
          { 'value': value })
        .done(function(data) {
          console.log(data);
	  $msg.html(data.message).show();
          if (data.wikiNameSpace !== undefined) {
            $container.find('input.wikiNameSpace').val(data.wikiNameSpace);
          }
        })
        .fail(function(xhr, status, errorThrown) {
          const response = CAFEVDB.ajaxFailData(xhr, status, errorThrown);
          console.log(response);
          if (response.message) {
	    $msg.html(response.message).show();
          }
        });
    });
});
