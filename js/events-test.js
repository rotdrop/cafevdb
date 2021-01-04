/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * @file
 * @TODO make this a general config check script.
 *
 */

/** @var Global app name space. */
var CAFEVDB = CAFEVDB || {};

$(function() {

  CAFEVDB.addReadyCallback(function() {

    $('#configrecheck').on('click', function(event) {
      $('#reloadbutton').trigger('click');
      return false;
    });

    $('.new-event.button').on('click', function(event) {
      console.log($('#dialog_holder'));
      $.post(
        OC.generateUrl('/apps/cafevdb/legacy/events/forms/new'),
        { 'projectId': '99999',
          'projectName': 'Test',
          'eventKind': 'other',
          'protectCategories': 1
        })
      .done(function(data) {
        $('#dialog_holder').html(data);
        CAFEVDB.Legacy.Calendar.UI.startEventDialog();
      })
      .fail(function(xhr, status, errorThrown) {
        const msg = CAFEVDB.Ajax.failMessage(xhr, status, errorThrown);
        OC.dialogs.alert(msg, t('cafevdb', 'Event-testing caught an error'));
      });
    });
    $('.edit-event.button').on('click', function(event) {
      console.log($('#dialog_holder'));
      $.post(
        OC.generateUrl('/apps/cafevdb/legacy/events/forms/edit'),
        { 'projectId': '99999',
          'projectName': 'Test',
          'eventKind': 'other',
	  'calendarid': $('#edit-event-test-calendar-id').val(),
          'uri': $('#edit-event-test-uri').val(),
          'protectCategories': 1
        })
      .done(function(data) {
        $('#dialog_holder').html(data);
        console.log("calling start event");
        CAFEVDB.Legacy.Calendar.UI.startEventDialog();
      })
      .fail(function(xhr, status, errorThrown) {
        console.log("failed starting event");
        const msg = CAFEVDB.Ajax.failMessage(xhr, status, errorThrown);
        OC.dialogs.alert(msg, t('cafevdb', 'Event-testing caught an error'));
      });
    });
    $('.geo-coding.button').on('click', function(event) {
      $.post(
        OC.generateUrl('/apps/cafevdb/expertmode/action/geodata'),
        {	'limit': 10 })
      .done(function(data) {
        console.log("triggered geo-data retrieval");
      })
      .fail(function(xhr, status, errorThrown) {
        console.log("failed triggering geo-data retrieval");
        const msg = CAFEVDB.Ajax.failMessage(xhr, status, errorThrown);
        OC.dialogs.alert(msg, t('cafevdb', 'Geo-Data testing caught an error'));
      });
    });
    $('.progress-status.button').on('click', function(event) {
      if (CAFEVDB.pollProgressStatus.active()) {
        CAFEVDB.pollProgressStatus.stop();
        return;
      }
      const id = 1;
      $.post(OC.generateUrl('/apps/cafevdb/foregroundjob/progress/create'),
	     { 'id': id, 'target': 100, 'current': 0 })
      .fail(function(xhr, status, errorThrown) {
        $('#progress-status-info').html(CAFEVDB.Ajax.failMessage(xhr, status, errorThrown));
      })
      .done(function(data) {
        $.post(OC.generateUrl('/apps/cafevdb/foregroundjob/progress/test'), { 'id': id })
	.fail(function(xhr, status, errorThrown) {
          $('#progress-status-info').html(CAFEVDB.Ajax.failMessage(xhr, status, errorThrown));
	});
        CAFEVDB.pollProgressStatus(
	  id,
	  {
            'update': function(data) {
              $('#progress-status-info').html(data.current + ' of ' + data.target);
	      console.info(data.current, data.target);
              return parseInt(data.current) < parseInt(data.target);
            },
            'fail': function(xhr, status, errorThrown) {
              $('#progress-status-info').html(CAFEVDB.Ajax.failMessage(xhr, status, errorThrown));
            }
	  });
      });
    });

  });

});

// Local Variables: ***
// indent-tabs-node: nil ***
// js-indent-level: 2 ***
// End: ***
