Events={
  projectId: -1,
  projectName: '',
  Events:{
    // nothing
  },
  UI:{
    init: function() {
      //$('.tipsy').remove();

      $('button').tipsy({gravity:'ne', fade:true});
      $('input').tipsy({gravity:'ne', fade:true});
      $('label').tipsy({gravity:'ne', fade:true});

      if (toolTips) {
        $.fn.tipsy.enable();
      } else {
        $.fn.tipsy.disable();
      }

      $('#events #eventlistform :button').click(Events.UI.buttonClick);
    },

    relist: function(data) {
      if (data.status == "success") {
        $('#events div.listing').html(data.data.message);
        $('#events #debug').html(data.data.debug);
        $('#events #debug').show();
      } else {
        $('#events #debug').html(data.data.message);
        $('#events #debug').show();
      }

      $('.tipsy').remove();

      $('button').tipsy({gravity:'ne', fade:true});
      $('input').tipsy({gravity:'ne', fade:true});
      $('label').tipsy({gravity:'ne', fade:true});

      if (toolTips) {
        $.fn.tipsy.enable();
      } else {
        $.fn.tipsy.disable();
      }

      $('#events #eventlistform div.listing :button').click(Events.UI.buttonClick);

      return false;
    },
    buttonClick: function(event) {
      //event.preventDefault();

      var evntdlgopen = $('#event').dialog('isOpen');

      var post = $('#eventlistform').serializeArray();

      if(evntdlgopen == true){
        // TODO: save event
        $('#event').dialog('close');
      }

      $('#events #debug').hide();
      $('#events #debug').empty();

      var name = $(this).attr('name');
      if (name == 'concerts' ||
          name == 'rehearsals' ||
          name == 'other') {
        // These are the new-event buttons.

        if(evntdlgopen == true){
          return true;
        }

        var type = new Object();
        type['name']  = 'EventKind';
        type['value'] = $(this).attr('name');
        post.push(type);

        $('#dialog_holder').load(OC.filePath('cafevdb',
                                             'ajax/events',
                                             'new.form.php'),
                                 post, Calendar.UI.startEventDialog);
        
        return false;
      } else if (name == 'edit') {
        // Edit existing event

        var type = new Object();
        type['name']  = 'id';
        type['value'] = $(this).val();
        post.push(type);

        $('#dialog_holder').load(OC.filePath('calendar',
                                             'ajax/event',
                                             'edit.form.php'),
                                 post, Calendar.UI.startEventDialog);
        
        return false;
      } else if (name == 'delete' || name == 'detach') {
        // Delete existing event

        var type = new Object();
        type['name']  = 'EventId';
        type['value'] = $(this).val();
        post.push(type);

        var type = new Object();
        type['name']  = 'Action';
        type['value'] = $(this).attr('name');
        post.push(type);

        $.post(OC.filePath('cafevdb', 'ajax/events', 'delete.php'),
               post, Events.UI.relist);
        
        return false;
      } else if (name == 'select' || name == 'deselect') {

        var type = new Object();
        type['name']  = 'Action';
        type['value'] = $(this).attr('name');
        post.push(type);

        $.post(OC.filePath('cafevdb', 'ajax/events','emailselect.php'),
               post, Events.UI.relist);
        
        return false;
      } else if (name == 'sendmail') {

        $.post(OC.filePath('cafevdb', 'ajax/events','sendmail.php'),
               post, Events.UI.relist);
        
        return false;
      }

      return false;
    }
  }
}

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

