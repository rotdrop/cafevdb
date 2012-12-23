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
      $('#dialog_holder').load(
        OC.filePath('cafevdb', 'ajax/events', 'events.php'),
        $(this),
        function() {
          var popup = $('#events').dialog({
            position: { my: "left top",
                        at: "left bottom",
                        of: "#controls",
                        offset: "10 10" },
            width : 500,
            height: 700,
            open  : function(){
              // quasi like document.ready(), it seems
              $.getScript(OC.filePath('cafevdb', 'js', 'events.js'),
                          function() { Events.UI.init(); });
            },
            close : function(event, ui) {
                $('#event').dialog('close');
              $(this).dialog('destroy').remove();
            }
          });
        });
    }
    return false;
  });
});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

