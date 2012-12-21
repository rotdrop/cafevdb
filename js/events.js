$('#events #newevent_form :button').click(function(event){
    //event.preventDefault();
    
    if($('#event').dialog('isOpen') == true){
      // TODO: save event
      $('#event').dialog('close');
      return true;
    }

    var post = $('#newevent_form').serializeArray();
    var type = new Object();
    type['name']  = 'EventKind';
    type['value'] = $(this).attr('name');
    post.push(type);

    
    $('#dialog_holder').load(OC.filePath('cafevdb', 'ajax/events', 'new.form.php'),
                             post, Calendar.UI.startEventDialog);

    return false;
});
