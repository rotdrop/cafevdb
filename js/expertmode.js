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

$(document).ready(function() {

  const container = $('.app-admin-settings');

  // container.on('click', 'button', function(event) {
  //   OC.dialogs.alert(t('cafevdb', 'Unhandled expert operation: {operation}', {operation: $(this).val()}),
  //                    t('cafevdb', 'Error'),
  //                    undefined, true, true);
  //   return false;
  // });

  const simpleActions = [
    'clearoutput',
    'example',
    'makeviews',
    'syncevents',
    'wikiprojecttoc',
    'attachwebpages',
    'sanitizephones',
    'geodata',
    'uuid',
    'imagemeta',
  ];

  simpleActions.forEach(function(action, index) {
    container.on('click', '#' + action, function() {
      const msg = container.find('.msg');
      $.post(OC.generateUrl('/apps/cafevdb/expertmode/action/' + action), { 'data': {} })
	.done(function(data) {
	  console.log(data);
	  msg.html(data.message).show();
	})
	.fail(function(jqXHR) {
	  console.log(jqXHR);
	  const response = JSON.parse(jqXHR.responseText);
	  if (response.message) {
	    msg.html(response.message).show();
	  }
	});
      return false;
    });
  });

  container.on('click', '#setupdb', function() {
    const msg = container.find('.msg');
    $.post(OC.generateUrl('/apps/cafevdb/expertmode/action/setupdb'), { 'data': {} })
      .done(function(data) {
	console.log(data);
             if (!CAFEVDB.ajaxErrorHandler(
	       { 'data': data,
		 'status': 'success'
	       }, ['success', 'error'])) {
               return;
             }
             OC.dialogs.alert(t('cafevdb', 'Successfull:')+
                              '<br/>'+
                              data.data.success+
                              '<br/>'+
                              t('cafevdb', 'Unsuccessfull:')+
                              '<br/>'+
                              '<pre>'+
                              data.data.error+
                              '</pre>',
                              t('cafevdb', 'Result of expert operation "setupdb"'),
                              undefined, true, true);
        msg.html(data.message).show();
      })
      .fail(function(jqXHR) {
	console.log(jqXHR);
	const response = JSON.parse(jqXHR.responseText);
	console.log(response);
	if (response.message) {
	  msg.html(response.message).show();
	}
      });
    return false;
  });

  ///////////////////////////////////////////////////////////////////////////
  //
  // Tooltips
  //
  ///////////////////////////////////////////////////////////////////////////

  CAFEVDB.toolTipsInit('#appsettings_popup');

});
