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

$(function() {

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
      const error = container.find('.error');
      $.post(OC.generateUrl('/apps/cafevdb/expertmode/action/' + action), { 'data': {} })
	.done(function(data) {
	  console.log(data);
          error.html('').hide();
	  msg.html(data.message).show();
	})
        .fail(function(xhr, status, errorThrown) {
          CAFEVDB.Ajax.handleError(xhr, status, errorThrown);
          msg.hide();
          error.html(CAFEVDB.Ajax.failMessage(xhr, status, errorThrown)).show();
        });
      return false;
    });
  });

  container.on('click', '#setupdb', function() {
    const msg = container.find('.msg');
    const error = container.find('.error');
    $.post(OC.generateUrl('/apps/cafevdb/expertmode/action/setupdb'), { 'data': {} })
      .done(function(data) {
	console.log(data);
        if (!CAFEVDB.Ajax.validateResponse(data, [ 'success', 'error' ])) {
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
        error.html('').hide();
        msg.html(data.message).show();
      })
      .fail(function(xhr, status, errorThrown) {
        CAFEVDB.Ajax.handleError(xhr, status, errorThrown);
        msg.html('').hide();
        error.html(CAFEVDB.Ajax.failMessage(xhr, status, errorThrown)).show();
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

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
