$(document).ready(function() {
    $('.new-event.button').on('click', function(event) {
	console.log($('#dialog_holder'));
	$('#dialog_holder').load(
	    OC.generateUrl('/apps/cafevdb/events/forms/new'),
            {}, Calendar.UI.startEventDialog);
    });
});
