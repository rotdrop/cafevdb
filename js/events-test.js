$(document).ready(function() {
  $('.new-event.button').on('click', function(event) {
    console.log($('#dialog_holder'));
    $.post(
      OC.generateUrl('/apps/cafevdb/legacy/events/forms/new'),
      { 'ProjectId': '99999',
        'ProjectName': 'Test',
        'EventKind': 'other'
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
        'uri': $(this).data('uri')
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
});

// LocalVariables: ***
// indent-tabs-node: nil ***
// js-indent-level: 2 ***
// End: ***
