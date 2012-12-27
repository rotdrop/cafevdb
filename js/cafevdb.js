$(document).ready(function(){

  //    $('button.settings').tipsy({gravity:'ne', fade:true});
  $('button').tipsy({gravity:'w', fade:true});
  $('input.cafevdb-control').tipsy({gravity:'nw', fade:true});
  $('#controls button').tipsy({gravity:'nw', fade:true});
  $('.pme-sort').tipsy({gravity: 'n', fade:true});
  $('.pme-misc-check').tipsy({gravity: 'nw', fade:true});

  $('#personalsettings .generalsettings').on(
    'click keydown', function(event) {
      event.preventDefault();
      OC.appSettings({appid:'cafevdb', loadJS:true,
                      cache:false, scriptName:'settings.php'});
    });

  $('#personalsettings .expert').on('click keydown', function(event) {
    event.preventDefault();
    OC.appSettings({appid:'cafevdb', loadJS:'expertmode.js',
                    cache:false, scriptName:'expert.php'});
  });
  
  OCCategories.app = 'calendar';
  OCCategories.changed = function(categories) {
    Calendar.UI.categoriesChanged(categories);
  }

  $(':button.events').click(function(event) {
    event.preventDefault();
    if ($('#events').dialog('isOpen') == true) {
      $('#events').dialog('close').remove();
      $('#events').dialog('destroy').remove();
    } else {
      // We store the values in the name attribute as serialized
      // string.
      var values = $(this).attr('name');
      $.post(OC.filePath('cafevdb', 'ajax/events', 'events.php'),
             values, Events.UI.init, 'json');
    }
    return false;
  });

  $(':button.instrumentation').click(function(event) {
    // This seems to work like an artificial form-submit, but there
    // may be better ways ...
    event.preventDefault();
    var values = $(this).attr('name');
    $.post('', values, function (data) {
      var newDoc = document.open("text/html", "replace");
      newDoc.write(data);
      newDoc.close();
    }, 'html');
    return false;
  });

  $(':button.register-musician').click(function(event) {
    // This seems to work like an artificial form-submit, but there
    // may be better ways ...
    event.preventDefault();
    var values = $(this).attr('name');
    $.post('', values, function (data) {
      var newDoc = document.open("text/html", "replace");
      newDoc.write(data);
      newDoc.close();
    }, 'html');
    return false;
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

