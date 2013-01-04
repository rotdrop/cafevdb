CAFEVDB = {
  name:'cafevdb'
};

$.extend({ alert: function (message, title) {
  $("<div></div>").dialog( {
    buttons: { "Ok": function () { $(this).dialog("close"); } },
    open: function(event, ui) {
      $(this).css({'max-height': 800, 'overflow-y': 'auto', 'height': 'auto'});
      $(this).dialog( "option", "resizable", false );
    },
    close: function (event, ui) { $(this).remove(); },
    resizable: false,
    title: title,
    modal: true,
    height: "auto"
  }).html(message);
}
});

// $.extend({
//   confirm: function(message, title, action) {
//     $("<div></div>").dialog({
//       // Remove the closing 'X' from the dialog
//       open: function(event, ui) {
//         $(".ui-dialog-titlebar-close").hide();
//         $(this).css({'max-height': 800, 'overflow-y': 'auto', 'height': 'auto'});
//         $(this).dialog( "option", "resizable", false );
//       }, 
//       buttons: {
//         Yes': function() {
//           $(this).dialog("close");
//           action(true);
//         },
//         'No': function() {
//           $(this).dialog("close");
//           action(false);
//         }
//       },
//       close: function(event, ui) { $(this).remove(); },
//       resizable: false,
//       title: title,
//       modal: true,
//       height: "auto"
//     }).text(message);
//   }
// });

$(document).ready(function(){

  $('#pme-export-choice').chosen({ disable_search_threshold: 10 });
  $('#pme-export-choice').change(function (event) {
    event.preventDefault();

    // determine the export format
    var selected = $("#pme-export-choice option:selected").val();

    var exportscript;
    switch (selected) {
    case 'HTML': exportscript = 'html.php'; break;
    case 'CSV': exportscript = 'csv.php'; break;
    case 'EXCEL': exportscript = 'excel.php'; break;
    default: exportscript = ''; break;
    }

    if (exportscript == '') {
      OC.dialogs.alert(t('cafevdb', 'Export to "'+selected+'" is not yet supported.'),
                       t('cafevdb', 'Unimplemented'));
    } else {

      // this will be the alternate form-action
      var exportscript = OC.filePath('cafevdb', 'ajax/export', exportscript);

      // this is the form; we need its values
      var form = $('form.pme-form');

      // Our export-script have the task to convert the display
      // PME-table into another format, so submitting the current
      // pme-form to another backend-script just makes sure sure that we
      // really get all selected parameters and can regenerate the
      // current view. Of course, this is then not really jQuery, and
      // the ajax/export/-scripts are not ajax scripts. But so what.
      var old_action= form.attr('action');
      form.attr('action', exportscript);
      form.submit();
      form.attr('action', old_action);
    }

    // Cheating. In principle we mus-use this as a simple pull-down
    // menu, so let the text remain at its default value.
    $("#pme-export-choice").children('option').each(function(i, elm) {
      $(elm).removeAttr('selected');
    });
    $("#pme-export-choice").trigger("liszt:updated");

    return false;
  });

  //    $('button.settings').tipsy({gravity:'ne', fade:true});
  $('button.viewtoggle').tipsy({gravity:'ne', fade:true});
  $('button').tipsy({gravity:'w', fade:true});
  $('input.cafevdb-control').tipsy({gravity:'nw', fade:true});
  $('#controls button').tipsy({gravity:'nw', fade:true});
  $('.pme-sort').tipsy({gravity: 'n', fade:true});
  $('.pme-misc-check').tipsy({gravity: 'nw', fade:true});
  $('label').tipsy({gravity:'ne', fade:true});

  $('#personalsettings .generalsettings').on(
    'click keydown', function(event) {
      event.preventDefault();

      $("#appsettings").tabs({ selected: 0});

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

    $visibility = $('input[name="headervisibility"]').val();
    if ($visibility == 'collapsed') {
      values += '&headervisibility=collapsed';
    }
    $.post('', values, function (data) {
      var newDoc = document.open("text/html"/*, "replace"*/);
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
      var newDoc = document.open("text/html"/*, "replace"*/);
      newDoc.write(data);
      newDoc.close();
    }, 'html');
    return false;
  });

});



// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

