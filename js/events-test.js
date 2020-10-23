$(document).ready(function() {
  $('.new-event.button').on('click', function(event) {
    console.log($('#dialog_holder'));
    $.post(
      OC.generateUrl('/apps/cafevdb/legacy/events/forms/new'),
      { 'ProjectId': '99999',
        'ProjectName': 'Test',
        'EventKind': 'other',
        'protectCategories': 1
      })
     .done(function(data) {
       $('#dialog_holder').html(data);
       CAFEVDB.Legacy.Calendar.UI.startEventDialog();
     })
     .fail(function(xhr, status, errorThrown) {
       const msg = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown);
      OC.dialogs.alert(msg, t('cafevdb', 'Event-testing caught an error'));
     });
  });
  $('.edit-event.button').on('click', function(event) {
    console.log($('#dialog_holder'));
    $.post(
      OC.generateUrl('/apps/cafevdb/legacy/events/forms/edit'),
      { 'ProjectId': '99999',
        'ProjectName': 'Test',
        'EventKind': 'other',
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
       const msg = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown);
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
       const msg = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown);
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
      $('#progress-status-info').html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown));
    })
    .done(function(data) {
      $.post(OC.generateUrl('/apps/cafevdb/foregroundjob/progress/test'), { 'id': id })
	.fail(function(xhr, status, errorThrown) {
          $('#progress-status-info').html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown));
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
            $('#progress-status-info').html(CAFEVDB.ajaxFailMessage(xhr, status, errorThrown));
          }
	});
    });
  });

});

// Local Variables: ***
// indent-tabs-node: nil ***
// js-indent-level: 2 ***
// End: ***
