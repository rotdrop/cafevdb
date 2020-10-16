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
});

// Local Variables: ***
// indent-tabs-node: nil ***
// js-indent-level: 2 ***
// End: ***
