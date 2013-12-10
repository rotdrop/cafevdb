/**Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  CAFEVDB.name             = 'cafevdb';
  CAFEVDB.headervisibility = 'expanded';
  CAFEVDB.toolTips         = true;
  CAFEVDB.wysiwygEditor    = 'tinymce';
  CAFEVDB.language         = 'en';
  CAFEVDB.addEditor = function(selector) {
    switch (this.wysiwygEditor) {
    case 'ckeditor':
      $(selector).ckeditor(function() {}, {/*enterMode:CKEDITOR.ENTER_P*/});
      break;
    case 'tinymce':
      $(document).on('focusin', function(e) {
        if ($(e.target).closest(".mce-window").length) {
	  e.stopImmediatePropagation();
	}
      });
      var mceConfig = myTinyMCE.config;
      //mceConfig.inline = true;
      $(selector).tinymce(mceConfig);
      break;
    default:
      $(selector).ckeditor(function() {}, {/*enterMode:CKEDITOR.ENTER_P*/});
      break;
    };
  };
  CAFEVDB.removeEditor = function(selector) {
    switch (this.wysiwygEditor) {
    case 'ckeditor':
      $(selector).ckeditor().remove();
      break;
    case 'tinymce':
      $(selector).tinymce().remove();
      break;
    default:
      $(selector).ckeditor().remove();
      break;
    };
  };
  CAFEVDB.broadcastHeaderVisibility = function(visibility) {

    // default: only distribute

    if (typeof visibility === 'undefined' || !visibility) {
      visibility = this.headervisibility;
    }

    // Sanity check
    if (visibility != 'expanded' && visibility != 'collapsed') {
      return;
    }

    // Keep in sync
    this.headervisibility = visibility;

    // Insert the new state into all hidden inputs for formsubmit
    $('input[name="headervisibility"]').each(function (idx) {
      $(this).val(visibility);
    });
  };
  CAFEVDB.stopRKey = function(evt) {
    var evt = (evt) ? evt : ((event) ? event : null);
    var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
    if ((evt.keyCode == 13) && (node.type=="text"))  {return false;}
  };
  /**Exchange "tipsy" tooltips already attached to an element by
   * something different. This has to be done the "hard" way: first
   * unset data('tipsy') by setting it to null, then call the
   * tipsy-constructor with the new values.
   *
   * @param[in] selector jQuery element selector
   *
   * @param[in] options Tipsy options
   */
  CAFEVDB.applyTipsy = function(selector, options) {
    $(selector).data('tipsy', null); // remove any already installed stuff
    $(selector).tipsy(options);      // make it new
  };
  
  CAFEVDB.urldecode = function(str) {
    // http://kevin.vanzonneveld.net
    // +   original by: Philip Peterson
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +      input by: AJ
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // +      input by: travc
    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Lars Fischer
    // +      input by: Ratheous
    // +   improved by: Orlando
    // +   reimplemented by: Brett Zamir (http://brett-zamir.me)
    // +      bugfixed by: Rob
    // +      input by: e-mike
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // +      input by: lovio
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // %        note 1: info on what encoding functions to use from: http://xkr.us/articles/javascript/encode-compare/
    // %        note 2: Please be aware that this function expects to decode from UTF-8 encoded strings, as found on
    // %        note 2: pages served as UTF-8
    // *     example 1: urldecode('Kevin+van+Zonneveld%21');
    // *     returns 1: 'Kevin van Zonneveld!'
    // *     example 2: urldecode('http%3A%2F%2Fkevin.vanzonneveld.net%2F');
    // *     returns 2: 'http://kevin.vanzonneveld.net/'
    // *     example 3: urldecode('http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3Dphp.js%26ie%3Dutf-8%26oe%3Dutf-8%26aq%3Dt%26rls%3Dcom.ubuntu%3Aen-US%3Aunofficial%26client%3Dfirefox-a');
    // *     returns 3: 'http://www.google.nl/search?q=php.js&ie=utf-8&oe=utf-8&aq=t&rls=com.ubuntu:en-US:unofficial&client=firefox-a'
    // *     example 4: urldecode('%E5%A5%BD%3_4');
    // *     returns 4: '\u597d%3_4'
    return decodeURIComponent((str + '').replace(/%(?![\da-f]{2})/gi, function () {
      // PHP tolerates poorly formed escape sequences
      return '%25';
    }).replace(/\+/g, '%20'));
  };

  CAFEVDB.urlencode = function(str) {
    // http://kevin.vanzonneveld.net
    // + original by: Philip Peterson
    // + improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // + input by: AJ
    // + improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // + improved by: Brett Zamir (http://brett-zamir.me)
    // + bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // + input by: travc
    // + input by: Brett Zamir (http://brett-zamir.me)
    // + bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // + improved by: Lars Fischer
    // + input by: Ratheous
    // + reimplemented by: Brett Zamir (http://brett-zamir.me)
    // + bugfixed by: Joris
    // + reimplemented by: Brett Zamir (http://brett-zamir.me)
    // % note 1: This reflects PHP 5.3/6.0+ behavior
    // % note 2: Please be aware that this function expects to encode into UTF-8 encoded strings, as found on
    // % note 2: pages served as UTF-8
    // * example 1: urlencode('Kevin van Zonneveld!');
    // * returns 1: 'Kevin+van+Zonneveld%21'
    // * example 2: urlencode('http://kevin.vanzonneveld.net/');
    // * returns 2: 'http%3A%2F%2Fkevin.vanzonneveld.net%2F'
    // * example 3: urlencode('http://www.google.nl/search?q=php.js&ie=utf-8&oe=utf-8&aq=t&rls=com.ubuntu:en-US:unofficial&client=firefox-a');
    // * returns 3: 'http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3Dphp.js%26ie%3Dutf-8%26oe%3Dutf-8%26aq%3Dt%26rls%3Dcom.ubuntu%3Aen-US%3Aunofficial%26client%3Dfirefox-a'
    str = (str + '').toString();

    // Tilde should be allowed unescaped in future versions of PHP (as reflected below), but if you want to reflect current
    // PHP behavior, you would need to add ".replace(/~/g, '%7E');" to the following.
    return encodeURIComponent(str).replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28').
      replace(/\)/g, '%29').replace(/\*/g, '%2A').replace(/%20/g, '+');
  };

  /**Create and submit a form with a POST request and given
   * parameters.
   *
   * @param[in] url Location to post to.
   *
   * @param[in] values Query string in GET notation.
   *
   * @param[in] method Either 'get' or 'post', default is 'post'.
   */
  CAFEVDB.formSubmit = function(url, values, method) {

    if (typeof method === 'undefined') {
      method = 'post';
    }

    var form = '<form method="'+method+'" action="'+url+'"></form>';

    form = $(form).appendTo($('body'));

    var splitValues = values.split('&');
    for (var i = 0; i < splitValues.length; ++i) {
      var nameValue = splitValues[i].split('=');
      $('<input />').attr('type', 'hidden')
        .attr('name', nameValue[0])
        .attr('value', CAFEVDB.urldecode(nameValue[1]))
        .appendTo(form);
    }
    form.submit();
  };

  CAFEVDB.tableExportMenu = function(select) {
    // determine the export format
    var selected = select.find('option:selected').val();
    //$("select.pme-export-choice option:selected").val();

    // this is the form; we need its values
    var form = $('form.pme-form');

    form.find('#exportmimetype').remove();

    var exportscript;
    switch (selected) {
    case 'HTML':
      exportscript = 'html.php';
      $('<input />').attr('type', 'hidden')
        .attr('name', 'mimetype')
        .attr('value', 'text/html')
        .attr('id', 'exportmimetype')
        .appendTo(form);
      break;
    case 'SSML':
      exportscript = 'html.php';
      $('<input />').attr('type', 'hidden')
        .attr('name', 'mimetype')
        .attr('value', 'application/spreadsheet')
        .attr('id', 'exportmimetype')
        .appendTo(form);
      break;
    case 'CSV': exportscript = 'csv.php'; break;
    case 'EXCEL': exportscript = 'excel.php'; break;
    default: exportscript = ''; break;
    }

    if (exportscript == '') {
      OC.dialogs.alert(t('cafevdb', 'Export to the following format is not yet supported:')
                       +' "'+selected+'"',
                       t('cafevdb', 'Unimplemented'));
    } else {

      // this will be the alternate form-action
      var exportscript = OC.filePath('cafevdb', 'ajax/export', exportscript);

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

    // Cheating. In principle we mis-use this as a simple pull-down
    // menu, so let the text remain at its default value. Make sure to
    // also remove and re-attach the tool-tips, otherwise some of the
    // tips remain, because chosen() removes the element underneath.
    
    select.children('option').each(function(i, elm) {
      $(elm).removeAttr('selected');
    });
    $('.tipsy').remove();

    select.trigger("chosen:updated");

    $('div.chosen-container').tipsy({gravity:'sw', fade:true});
    $('li.active-result').tipsy({gravity:'w', fade:true});

    return false;

  };

  CAFEVDB.projectActions = function(select) {
    
    // determine the export format
    var selected = select.find('option:selected').val();
    var values = select.attr('name');
    var optionValues = selected.split('?');

    selected = optionValues[0];

    switch (selected) {
    case 'events':
      if ($('#events').dialog('isOpen') == true) {
        $('#events').dialog('close').remove();
      } else {
        // We store the values in the name attribute as serialized
        // string.
        $.post(OC.filePath('cafevdb', 'ajax/events', 'events.php'),
               values, CAFEVDB.Events.UI.init, 'json');
      }
      break;
    case 'brief-instrumentation':
    case 'detailed-instrumentation':
    case 'project-instruments':
      values += '&Template='+selected;
      values += '&headervisibility='+CAFEVDB.headervisibility;

      CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');
      break;
    case 'profit-and-loss':
    case 'project-files':
      var url    = OC.linkTo('files', 'index.php');
      var values = 'dir='+optionValues[1];

      CAFEVDB.formSubmit(url, values, 'get');
      break;
    case 'project-wiki':
      var url    = OC.linkTo('dokuwikiembed', 'index.php');
      var values = 'wikiPath='+CAFEVDB.urlencode('/doku.php?id=')+optionValues[1];
      CAFEVDB.formSubmit(url, values, 'post');
      break;
    default:
      OC.dialogs.alert(t('cafevdb', 'Unknown operation:')
                       +' "'+selected+'"',
                       t('cafevdb', 'Unimplemented'));
    }
    
    // Cheating. In principle we mis-use this as a simple pull-down
    // menu, so let the text remain at its default value. Make sure to
    // also remove and re-attach the tool-tips, otherwise some of the
    // tips remain, because chosen() removes the element underneath.
    
    select.children('option').each(function(i, elm) {
      $(elm).removeAttr('selected');
    });
    $('.tipsy').remove();

    select.trigger("chosen:updated");

    $('div.chosen-container').tipsy({gravity:'sw', fade:true});
    $('li.active-result').tipsy({gravity:'w', fade:true});

    return false;
  };

})(window, jQuery, CAFEVDB);

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
//         'Yes': function() {
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

  document.onkeypress = CAFEVDB.stopRKey;

  // Emulate a pull-down menu with export options via the chosen
  // plugin.
  $('select.pme-export-choice').chosen({ disable_search:true });  
  $('select.pme-export-choice').change(function (event) {
    event.preventDefault();

    return CAFEVDB.tableExportMenu($(this));
  });

  $('select.pme-export-choice').on('chosen:showing_dropdown', function (chosen) {
    $('ul.chosen-results li.active-result').tipsy({gravity:'w', fade:true});
  });

  // emulate per-project action pull down menu via chosen
  $('select.project-actions').chosen({ disable_search:true });
  $('select.project-actions').change(function (event) {
    event.preventDefault();

    return CAFEVDB.projectActions($(this));
  });  
  $('select.project-actions').on('chosen:showing_dropdown', function (chosen) {
    $('ul.chosen-results li.active-result').tipsy({gravity:'w', fade:true});
  });

  //    $('button.settings').tipsy({gravity:'ne', fade:true});
  $('button.viewtoggle').tipsy({gravity:'ne', fade:true});
  $('div.viewtoggle').tipsy({gravity:'se', fade:true});
  $('select').tipsy({gravity:'w', fade:true});
  $('div.chosen-container').tipsy({gravity:'sw', fade:true});
  $('li.active-result').tipsy({gravity:'w', fade:true});
  $('form.cafevdb-control input').tipsy({gravity:'nw', fade:true});
  $('button.settings').tipsy({gravity:'ne', fade:true});
  $('.pme-sort').tipsy({gravity: 'n', fade:true});
  $('.pme-misc-check').tipsy({gravity: 'nw', fade:true});
  $('label').tipsy({gravity:'se', fade:true});
  $('.header-right img').tipsy({gravity:'ne', fade:true});
  $('img').tipsy({gravity:'nw', fade:true});
  $('button').tipsy({gravity:'w', fade:true});

  CAFEVDB.applyTipsy('select[class|="pme-filter"]',
                     {gravity:'n', fade:true, html:true, className:'tipsy-wide'});

  CAFEVDB.applyTipsy('input[class|="pme-filter"]',
                     {gravity:'n', fade:true, html:true, className:'tipsy-wide'});

  CAFEVDB.applyTipsy('label[class$="memberstatus-label"]',
                     {gravity:'n', fade:true, html:true, className:'tipsy-wide'});

  $('textarea[class^="pme-input"]').tipsy(
    {gravity:'sw', fade:true, html:true, className:'tipsy-wide'});

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
    } else {
      // We store the values in the name attribute as serialized
      // string.
      var values = $(this).attr('name');
      $.post(OC.filePath('cafevdb', 'ajax/events', 'events.php'),
             values, CAFEVDB.Events.UI.init, 'json');
    }
    return false;
  });

  $(':button.instrumentation').click(function(event) {
    event.preventDefault();

    var values = $(this).attr('name');
    values += '&headervisibility='+CAFEVDB.headervisibility;

    CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');

    return false;
  });

  $(':button.register-musician').click(function(event) {
    event.preventDefault();
    var values = $(this).attr('name');

    CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');

    return false;
  });

  $('input.alertdata.cafevdb-page').each(function(index) {
    var title = $(this).attr('name');
    var text  = $(this).attr('value');
    OC.dialogs.alert('<div class="cafevdb-error">'+text+'</div>', title, null, true);
    //$('#cafevdb-error-block').append('<u>'+title+'</u><br/>'+text+'<br/>',
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

