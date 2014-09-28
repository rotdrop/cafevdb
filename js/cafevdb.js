/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  CAFEVDB.addEditor = function(selector, initCallback, initialHeight) {
    var editorElement;
    if (selector instanceof jQuery) {
      editorElement = selector;
    } else {
      editorElement = $(selector);
    }
    if (!editorElement.length) {
      if (typeof initCallback == 'function') {
        initCallback();
      }
      return;
    }
    switch (this.wysiwygEditor) {
    case 'ckeditor':
      if (typeof initCallback != 'function') {
        initCallback = function() {};
      }
      editorElement.ckeditor(initCallback, {/*enterMode:CKEDITOR.ENTER_P*/});
      break;
    case 'tinymce':
      $(document).on('focusin', function(e) {
        if ($(e.target).closest(".mce-window").length) {
	  e.stopImmediatePropagation();
	}
      });
      var plusConfig;
      if (typeof initialHeight != 'undefined') {
        plusConfig = { height: initialHeight };
      } else {
        plusConfig = undefined;
      }
      var mceConfig = myTinyMCE.getConfig(plusConfig);
        // {
        //   setup: function(editor) {
        //     myTinyMCE.config.setup(editor);
        //   },
        // });
      editorElement.tinymce(mceConfig);
      // post-render callback? This is really quere. There is
      // something really broken with the tinzMCE setup.
      if (typeof initCallback == 'function') {
        setTimeout(initCallback, 500);
      }
      break;
    default:
      if (typeof initCallback != 'function') {
        initCallback = function() {};
      }
      editorElement.ckeditor(initCallback, {/*enterMode:CKEDITOR.ENTER_P*/});
      break;
    };
  };

  CAFEVDB.removeEditor = function(selector) {
    var editorElement;
    if (selector instanceof jQuery) {
      editorElement = selector;
    } else {
      editorElement = $(selector);
    }
    if (!editorElement.length) {
      return;
    }
    switch (this.wysiwygEditor) {
    case 'ckeditor':
      if (editorElement.ckeditor) {
        editorElement.ckeditor().remove()
      }
      break;
    case 'tinymce':
      editorElement.tinymce().remove();
      break;
    default:
      if (editorElement.ckeditor) {
        editorElement.ckeditor().remove()
      }
      break;
    };
  };

  /**Replace the contents of the given editor by contents. */
  CAFEVDB.updateEditor = function(selector, contents) {
    var editorElement;
    if (selector instanceof jQuery) {
      editorElement = selector;
    } else {
      editorElement = $(selector);
    }
    if (!editorElement.length) {
      return;
    }
    switch (this.wysiwygEditor) {
    case 'ckeditor':
      if (editorElement.ckeditor) {
        var editor = editorElement.ckeditor().ckeditorGet();
        editor.setData(contents);
        // ckeditor snapshots itself on update.
        //editor.undoManager.save(true);
      }
      break;
    case 'tinymce':
      editorElement.tinymce().setContent(contents);
      editorElement.tinymce().undoManager.add();
      break;
    default:
      if (editorElement.ckeditor) {
        var editor = editorElement.ckeditor().ckeditorGet();
        editor.setData(contents);
        // ckeditor snapshots itself on update.
        //editor.undoManager.save(true);
      }
      break;
    };
  }

  /**Generate a "snapshot", meaning an undo-level, for instance after
   * replacing all data by loading email templates and stuff.
   */
  CAFEVDB.snapshotEditor = function(selector) {
    var editorElement;
    if (selector instanceof jQuery) {
      editorElement = selector;
    } else {
      editorElement = $(selector);
    }
    if (!editorElement.length) {
      return;
    }
    switch (this.wysiwygEditor) {
    case 'ckeditor':
      if (editorElement.ckeditor) {
        var editor = editorElement.ckeditor().ckeditorGet();
        editor.undoManager.save(true);
      }
      break;
    case 'tinymce':
      editorElement.tinymce().undoManager.add();
      break;
    default:
      if (editorElement.ckeditor) {
        var editor = editorElement.ckeditor().ckeditorGet();
        editor.undoManager.save(true);
      }
      break;
    };
  };

  /**Display a transparent modal dialog which blocks the UI
   * 
   */
  CAFEVDB.modalWaitNotification = function(message) {
    var dialogHolder = $('<div class="cafevdb modal-wait-notification"></div>');
    dialogHolder.html('<div class="cafevdb modal-wait-message">'+message+'</div>'+
                      '<div class="cafevdb modal-wait-animation"></div>');
    $('body').append(dialogHolder);
    dialogHolder.find('div.modal-wait-animation').progressbar({ value: false });
    dialogHolder.dialog({
      title: '',
      position: { my: "center",
                  at: "center center-20%",
                  of: window },
      width: '80%',
      height: 'auto',
      modal: true,
      closeOnEscape: false,
      dialogClass: 'transparent no-close wait-notification',
      resizable: false,
      open: function() {
      },
      cloase: function() {
        dialogHolder.dialog('close');
        dialogHolder.dialog('destroy').remove();
      }
      });
    return dialogHolder;
  };

  /**Unfortunately, the textare element does not fire a resize
   * event. This function emulates one.
   *
   * @param textarea jQuery descriptor for the textarea element
   *
   * @param delay Optional, defaults to 50. If true, fire the event
   * immediately, if set, then this is a delay in ms.
   * 
   *
   */
  CAFEVDB.textareaResize = function(textarea, delay) {
    if (typeof delay == 'undefined') {
      delay = 50; // ms
    }
    textarea.off('mouseup mousemove');
    textarea.on('mouseup mousemove', function() {
      if (this.oldwidth  === null) {
        this.oldwidth  = this.style.width;
      }
      if (this.oldheight === null) {
        this.oldheight = this.style.height;
      }
      if (this.style.width != this.oldwidth || this.style.height != this.oldheight) {
        var self = this;
        if (delay > 0) {
          if (this.resize_timeout) {
            clearTimeout(this.resize_timeout);
          }
          this.resize_timeout = setTimeout(function() {
            $(self).resize();
          }, delay);
        } else {
          $(this).resize();
        }
        this.oldwidth  = this.style.width;
        this.oldheight = this.style.height;
      }
      return true;
    });
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
    if ((evt.keyCode == 13) && (node.type=="text"))  {
      return false;
    }
    return true;
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

  CAFEVDB.queryData = function(queryString, preserveDuplicates) {
    /*
     *
     * QueryData.js
     *
     * A function to parse data from a query string
     *
     * Created by Stephen Morley - http://code.stephenmorley.org/ - and released under
     * the terms of the CC0 1.0 Universal legal code:
     *
     * http://creativecommons.org/publicdomain/zero/1.0/legalcode
     *
     * Creates an object containing data parsed from the specified query string. The
     * parameters are:
     *
     * queryString        - the query string to parse. The query string may start
     *                      with a question mark, spaces may be encoded either as
     *                      plus signs or the escape sequence '%20', and both
     *                      ampersands and semicolons are permitted as separators.
     *                      This optional parameter defaults to query string from
     *                      the page URL.
     * preserveDuplicates - true if duplicate values should be preserved by storing
     *                      an array of values, and false if duplicates should
     *                      overwrite earler occurrences. This optional parameter
     *                      defaults to false.
     */

    var result = {};
    
    // if a query string wasn't specified, use the query string from the URL
    if (queryString == undefined){
      queryString = location.search ? location.search : '';
    }
    
    // remove the leading question mark from the query string if it is present
    if (queryString.charAt(0) == '?') queryString = queryString.substring(1);
    
    // check whether the query string is empty
    if (queryString.length > 0){
      
      // replace plus signs in the query string with spaces
      queryString = queryString.replace(/\+/g, ' ');

      // split the query string around ampersands and semicolons
      var queryComponents = queryString.split(/[&;]/g);

      // loop over the query string components
      for (var index = 0; index < queryComponents.length; index ++){
        // extract this component's key-value pair
        var keyValuePair = queryComponents[index].split('=');
        var key          = decodeURIComponent(keyValuePair[0]);
        var value        = keyValuePair.length > 1
                         ? decodeURIComponent(keyValuePair[1])
                         : '';
        // check whether duplicates should be preserved
        if (preserveDuplicates){
          // create the value array if necessary and store the value
          if (!(key in result)) result[key] = [];
          result[key].push(value);
        }else{
          // store the value
          result[key] = value;
        } 
      } 
    }
    return result;
  };

  CAFEVDB.print_r = function(array, return_val) {
    // discuss at: http://phpjs.org/functions/print_r/
    // original by: Michael White (http://getsprink.com)
    // improved by: Ben Bryan
    // improved by: Brett Zamir (http://brett-zamir.me)
    // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // input by: Brett Zamir (http://brett-zamir.me)
    // depends on: echo
    // example 1: print_r(1, true);
    // returns 1: 1
    var output = '',
        pad_char = ' ',
        pad_val = 4,
        d = window.document,
        getFuncName = function (fn) {
          var name = (/\W*function\s+([\w\$]+)\s*\(/)
                     .exec(fn);
          if (!name) {
            return '(Anonymous)';
          }
          return name[1];
        };
    repeat_char = function (len, pad_char) {
      var str = '';
      for (var i = 0; i < len; i++) {
        str += pad_char;
      }
      return str;
    };
    formatArray = function (obj, cur_depth, pad_val, pad_char) {
      if (cur_depth > 0) {
        cur_depth++;
      }
      var base_pad = repeat_char(pad_val * cur_depth, pad_char);
      var thick_pad = repeat_char(pad_val * (cur_depth + 1), pad_char);
      var str = '';
      if (typeof obj === 'object' && obj !== null && obj.constructor && getFuncName(obj.constructor) !==
          'PHPJS_Resource') {
        str += 'Array\n' + base_pad + '(\n';
        for (var key in obj) {
          if (Object.prototype.toString.call(obj[key]) === '[object Array]') {
            str += thick_pad + '[' + key + '] => ' + formatArray(obj[key], cur_depth + 1, pad_val, pad_char);
          } else {
            str += thick_pad + '[' + key + '] => ' + obj[key] + '\n';
          }
        }
        str += base_pad + ')\n';
      } else if (obj === null || obj === undefined) {
        str = '';
      } else {
        // for our "resource" class
        str = obj.toString();
      }
      return str;
    };
    output = formatArray(array, 0, pad_val, pad_char);
    if (return_val !== true) {
      if (d.body) {
        window.echo(output);
      } else {
        try {
          // We're in XUL, so appending as plain text won't work; trigger an error out of XUL
          d = XULDocument;
          window.echo('<pre xmlns="http://www.w3.org/1999/xhtml" style="white-space:pre;">' + output + '</pre>');
        } catch (e) {
          // Outputting as plain text may work in some plain XML
          window.echo(output);
        }
      }
      return true;
    }
    return output;
  };

  /*jQuery dialog popup with one chosen multi-selelct box inside.
   * 
   */
  CAFEVDB.chosenPopup = function(contents, userOptions)
  {
    var CAFEVDB = this;

    var options = {
      title: t('cafevdb', 'Choose some Options'),
      position: { my: "center center",
                  at: "center center",
                  of: window },
      dialogClass: false,
      saveText: t('cafevdb', 'Save'),
      saveTitle: t('cafevdb',
                   'Accept the currently selected options and return to the underlying form. '),
      cancelText: t('cafevdb', 'Cancel'),
      cancelTitle: t('cafevdb',
                     'Discard the current selection and close the dialog. '+
                     'The initial set of selected options will remain unchanged.'),
      buttons: [], // additional buttons.
      openCallback: false,
      saveCallback: false,
      closeCallback: false
    }
    $.extend(options, userOptions);

    var cssClass = (options.dialogClass ? options.dialogClass + ' ' : '') + 'chosen-popup-dialog';
    var dialogHolder = $('<div class="'+cssClass+'"></div>');
    dialogHolder.html(contents);
    var selectElement = dialogHolder.find('select');
    $('body').append(dialogHolder);

    var buttons = [
      { text: options.saveText,
        //icons: { primary: 'ui-icon-check' },
        'class': 'save',
        title: options.saveTitle,
        click: function() {
          var self = this;

          var selectedOptions = [];
          selectElement.find('option:selected').each(function(idx) {
            var self = $(this);
            selectedOptions[idx] = { 'value': self.val(),
                                     'html' : self.html(),
                                     'text' : self.text() };
          });
          //alert('selected: '+JSON.stringify(selectedOptions));
          if (typeof options.saveCallback == 'function') {
            options.saveCallback.call(this, selectElement, selectedOptions);
          }

          return false;
        }
      },
      { text: options.cancelText,
        'class': 'cancel',
        title: options.cancelTitle,
        click: function() {
       $(this).dialog("close");
     }
      }
    ];
    buttons = buttons.concat(options.buttons);

    dialogHolder.dialog({
      title: options.title,
      position: options.position,
      dialogClass: cssClass,
      modal:true,
      draggable:false,
      closeOnEscape:false,
      width:'auto',
      height:'auto',
      resizable:false,
      buttons: buttons,
      open:function() {
        selectElement.chosen(); //{disable_search_threshold: 10});
        var dialogWidget = dialogHolder.dialog('widget');
        CAFEVDB.tipsy(dialogWidget);
        dialogHolder.find('.chosen-container').off('dblclick').
          on('dblclick', function(event) {
            dialogWidget.find('.ui-dialog-buttonset .ui-button.save').trigger('click');
            return false;
          });

        if (typeof options.openCallback == 'function') {
          options.openCallback.call(this, selectElement);
        }
      },
      close:function() {
        if (typeof options.closeCallback == 'function') {
          options.closeCallback.call(this, selectElement);
        }

        $('.tipsy').remove();
        dialogHolder.dialog('close');
        dialogHolder.dialog('destroy').remove();
      }
    });
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

    form = $(form);

    var splitValues = values.split('&');
    for (var i = 0; i < splitValues.length; ++i) {
      var nameValue = splitValues[i].split('=');
      $('<input />').attr('type', 'hidden')
        .attr('name', nameValue[0])
        .attr('value', CAFEVDB.urldecode(nameValue[1]))
        .appendTo(form);
    }
    form.appendTo($('body')); // needed?
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
      exportscript = OC.filePath('cafevdb', 'ajax/export', exportscript);

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

  CAFEVDB.exportMenu = function(containerSel) {
    if (typeof containerSel === 'undefined') {
      containerSel = '#cafevdb-page-body';
    }
    var container = $(containerSel);

    // Emulate a pull-down menu with export options via the chosen
    // plugin.
    container.find('select.pme-export-choice').chosen({ disable_search: true });  
    container.find('select.pme-export-choice').change(function (event) {
      event.preventDefault();
      
      return CAFEVDB.tableExportMenu($(this));
    });
    
    container.find('select.pme-export-choice').on('chosen:showing_dropdown', function (chosen) {
      container.find('ul.chosen-results li.active-result').tipsy({gravity:'w', fade:true});
    });
  }

  /**Exchange "tipsy" tooltips already attached to an element by
   * something different. This has to be done the "hard" way: first
   * unset data('tipsy') by setting it to null, then call the
   * tipsy-constructor with the new values.
   *
   * @param[in] selector jQuery element selector
   *
   * @param[in] options Tipsy options
   */
  CAFEVDB.applyTipsy = function(selector, options, container) {
    var element;
    if (selector instanceof jQuery) {
      element = selector;
    } else if (typeof container != 'undefined') {
      element = container.find(selector);
    } else {
      element = $(selector);
    }
    // fetch suitable options from the elements class attribute
    var classOptions = { fade: true,
                         html: true,
                         gravity: 'se' };
    var classAttr = element.attr('class');
    if (typeof classAttr != 'undefined') {
      if (classAttr.match(/tipsy-off/) !== null) {
        $(this).tipsy('disable');
        return;
      }
      var tipsyClasses = classAttr.match(/tipsy-[a-z]+/g);
      var idx;
      for(idx = 0; idx < tipsyClasses.length; ++idx) {
        var tipsyClass = tipsyClasses[idx];
        var gravity = tipsyClass.match(/^tipsy-([senw]{1,2})$/);
        if (gravity && gravity.length == 2 && gravity[1].length > 0) {
          classOptions.gravity = gravity[1];
          continue;
        }
        classOptions.className = tipsyClass;
      }
    }
    if (typeof options == 'undefined') {
      options = classOptions;
    } else {
      // supplied options override class options
      options = $.extend({}, classOptions, options);
    }
    element.data('tipsy', null); // remove any already installed stuff
    element.tipsy(options);      // make it new
  };

  /**Add a to-back-button to the titlebar of a jQuery-UI dialog. The
   * purpose is to be able to move the top-dialog to be bottom-most,
   * juse above a potential "modal" window layer.
   */
  CAFEVDB.dialogToBackButton = function(dialogHolder) {
    var dialogWidget = dialogHolder.dialog('widget');
    var toBackButtonTitle = t('cafevdb',
                              'If multiple dialogs are open, '+
                              'then move this one to the lowest layer '+
                              'and display it below the others. '+
                              'Clicking anywhere on the dialog will bring to the front again.');
    var toBackButton = $('<button class="toBackButton customDialogHeaderButton" title="'+toBackButtonTitle+'"></button>');
    toBackButton.button({label: '_',
                         icons: { primary: 'ui-icon-minusthick', secondary: null },
                         text: false});
    dialogWidget.find('.ui-dialog-titlebar').append(toBackButton);
    toBackButton.tipsy({gravity: 'ne', fade: true });

    toBackButton.off('click');
    toBackButton.on('click', function() {
      var overlay = $('div.ui-widget-overlay');
      var overlayIndex = 100; // OwnCloud header resides at 50.
      if (overlay.length > 0) {
        overlayIndex = parseInt(overlay.css('z-index'));
      }
      // will be only few, so what
      var needShuffle = false;
      $('.ui-dialog.ui-widget').each(function(index) {
        var thisIndex = parseInt($(this).css('z-index'));
        if (thisIndex == overlayIndex + 1) {
          needShuffle = true;
        }
      }).each(function(index) {
        if (needShuffle) {
          var thisIndex = parseInt($(this).css('z-index'));
          $(this).css('z-index', thisIndex + 1);
        }
      });
      dialogWidget.css('z-index', overlayIndex + 1);
      return false;
    });
  };

  /**jQuery UI just is not flexible enough. We want to be able to
   * completely intercept the things the close button initiates. I
   * just did not find any other way than to hide the close button
   * completely and add another button with the same layout instead,
   * but this time with complete control over the events triggered bz
   * this button. GNAH. BIG GNAH!
   * 
   * If callback is undefined, then simply call the close
   * method. Otherwise it is called like callback(event, dialogHolder).
   * 
   */
  CAFEVDB.dialogCustomCloseButton = function(dialogHolder, callback) {
    var dialogWidget = dialogHolder.dialog('widget');
    var customCloseButtonTitle = t('cafevdb',
                                   'Close the current dialog and return to the view '+
                                   'which was active before this dialog had been opened. '+
                                   'If the current view shows a `Back\' button, then intentionally '+
                                   'clicking the close-button (THIS button) should just be '+
                                   'equivalent to clicking the `Back\' button');
    var customCloseButton = $('<button class="customCloseButton customDialogHeaderButton" title="'+customCloseButtonTitle+'"></button>');
    customCloseButton.button({label: 'x',
                              icons: { primary: 'ui-icon-closethick', secondary: null },
                              text: false});
    dialogWidget.find('.ui-dialog-titlebar').append(customCloseButton);
    customCloseButton.tipsy({gravity: 'ne', fade: true });

    customCloseButton.off('click');
    customCloseButton.on('click', function(event) {
      if (typeof callback == 'function') {
        callback(event, dialogHolder)
      } else {
        dialogHolder.dialog('close');
      }
      return false;
    });
  };

  /**Some general PME tweaks.
   */
  CAFEVDB.pmeTweaks = function(container) {
    if (typeof container == 'undefined') {
      container = $('body');
    }

    container.find('input[class^="pme-input-"][class$="-birthday"]').datepicker({
      dateFormat : 'dd.mm.yy', // this is 4-digit year
      minDate: '01.01.1940'
    });
    
    container.find('input[class^="pme-input-"][class$="-date"]').datepicker({
      dateFormat : 'dd.mm.yy', // this is 4-digit year
      minDate: '01.01.1990'
    });
    
    container.find('td[class$="-money"]').filter(function() {
      return $.trim($(this).text()).indexOf("-") == 0;
    }).addClass("negative");


    $(PHPMYEDIT.defaultSelector + ' input.pme-email').off('click').on('click', function(event) {
      event.stopImmediatePropagation();
      CAFEVDB.Email.emailFormPopup($(this.form).serialize());
      return false;
    });

    // This could also be wrapped into a popup maybe, and lead back to
    // the brief-instrumentation table on success.
    $(PHPMYEDIT.defaultSelector + ' input.pme-bulkcommit').addClass('formsubmit');
  };

  /**Generate some diagnostic output, mostly needed during application
   *development.
   *
   * @param data The data passed to the callback to $.post()
   * 
   * @param required List of required fields in data.data.
   * 
   */
  CAFEVDB.ajaxErrorHandler = function(data, required, errorCB) {
    if (typeof errorCB == 'undefined') {
      errorCB = function() {};
    }
    // error handling
    if (typeof data == 'undefined' ||
        typeof data.status == 'undefined' ||
        typeof data.data == 'undefined') {
      OC.dialogs.alert(t('cafevdb', 'Unrecoverable unknown internal error, '+
                         'no further information available, sorry.'),
                       t('cafevdb', 'Internal Error'), errorCB, true);
      return false;
    }
    var missing = '';
    var idx;
    for (idx = 0; idx < required.length; ++idx) {
      if (typeof data.data[required[idx]] == 'undefined') {
        missing += t('cafevdb', 'Field {RequiredField} not present in AJAX response.',
                     { RequiredField: required[idx] })+"<br>";
      }
    }
    if (missing.length > 0 || data.status != 'success') {
      var info = '';
      if (typeof data.data.message != 'undefined') {
	info += data.data.message;
      } else if (missing.length > 0) {
        info += t('cafevdb', 'Missing data');
      } else {
	info += t('cafevdb', 'Unknown error :(');
      }
      if (typeof data.data.error != 'undefined' && data.data.error == 'exception') {
	info += '<div class="exception error name"><pre>'+data.data.exception+'</pre></div>';
	info += '<div class="exception error trace"><pre>'+data.data.trace+'</pre></div>';
      } else if (missing.length > 0) {
        // Add missing fields only if no exception was caught as in
        // this case no regular data-fields have been constructed
        info += '<div class="missing error">'+missing+'</div>';
      }
      if (data.data.debug != '') {
        OC.dialogs.info('<div class="debug error contents">'+data.data.debug+'</div>', t('cafevdb', 'Debug Information'), undefined, true, true);
      }
      var caption = data.data.caption;
      if (typeof caption == 'undefined' || caption == '') {
        caption = t('cafevdb', 'Error');
      }
      OC.dialogs.alert(info, caption, errorCB, true, true);
      return false;
    }
    return true;
  };

  /**Initialize our tipsy stuff. Only exchange for our own thingies, of course.
   */
  CAFEVDB.tipsy = function(containerSel) {
    if (typeof containerSel === 'undefined') {
      containerSel = '#cafevdb-page-body';
    }
    var container = $(containerSel);

    $.fn.tipsy.defaults.html = true;

    // container.find('button.settings').tipsy({gravity:'ne', fade:true});
    container.find('button.viewtoggle').tipsy({gravity:'ne', fade:true});
    container.find('div.viewtoggle').tipsy({gravity:'se', fade:true});
    container.find('select').tipsy({gravity:'w', fade:true});
    container.find('div.chosen-container').tipsy({gravity:'sw', fade:true});
    container.find('li.active-result').tipsy({gravity:'w', fade:true});
    container.find('form.cafevdb-control input').tipsy({gravity:'nw', fade:true});
    container.find('button.settings').tipsy({gravity:'ne', fade:true});
    container.find('.pme-sort').tipsy({gravity: 'n', fade:true});
    container.find('.pme-misc-check').tipsy({gravity: 'nw', fade:true});
    container.find('label').tipsy({gravity:'se', fade:true});
    container.find('.header-right img').tipsy({gravity:'ne', fade:true});
    container.find('img').tipsy({gravity:'nw', fade:true});
    container.find('button').tipsy({gravity:'w', fade:true});

    // original tipsy stuff
    container.find('.displayName .action').tipsy({gravity:'se', fade:true, live:true});
    container.find('.password .action').tipsy({gravity:'se', fade:true, live:true});
    container.find('#upload').tipsy({gravity:'w', fade:true});
    container.find('.selectedActions a').tipsy({gravity:'s', fade:true, live:true});
    container.find('a.action.delete').tipsy({gravity:'e', fade:true, live:true});
    container.find('a.action').tipsy({gravity:'s', fade:true, live:true});
    container.find('td .modified').tipsy({gravity:'s', fade:true, live:true});
    container.find('td.lastLogin').tipsy({gravity:'s', fade:true, html:true});
    container.find('input:not([type=hidden])').tipsy({gravity:'w', fade:true});

    // everything else.
    container.find('.tip').tipsy({gravity:'w', fade:true});

    CAFEVDB.applyTipsy('select[class|="pme-filter"]',
                       {gravity:'n', fade:true, html:true, className:'tipsy-wide'},
                       container);

    CAFEVDB.applyTipsy('input[class|="pme-filter"]',
                       {gravity:'n', fade:true, html:true, className:'tipsy-wide'},
                       container);

    CAFEVDB.applyTipsy('label[class$="memberstatus-label"]',
                       {gravity:'n', fade:true, html:true, className:'tipsy-wide'},
                       container);

    container.find('textarea[class^="pme-input"]').tipsy(
      {gravity:'sw', fade:true, html:true, className:'tipsy-wide'});

    container.find('[class*="tipsy-"]').each(function(index) {
      var options = { fade: true,
                      html: true };
      var element = $(this);
      var classAttr = element.attr('class');
      if (classAttr.match(/tipsy-off/) !== null) {
        $(this).tipsy('disable');
        return;
      }
      var tipsyClasses = classAttr.match(/tipsy-[a-z]+/);
      var idx;
      for(idx = 0; idx < tipsyClasses.length; ++idx) {
        var tipsyClass = tipsyClasses[idx];
        var gravity = tipsyClass.match(/^tipsy-([senw]{1,2})$/);
        if (gravity && gravity.length == 2 && gravity[1].length > 0) {
          options.gravity = gravity[1];
          continue;
        }
        options.className = tipsyClass;
      }
      var oldTipsy = $(this).data('tipsy');
      $(this).data('tipsy', null); // remove any already installed stuff
      $(this).tipsy($.extend({}, oldTipsy, options));
    });
  }

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

$(document).ready(function(){

  document.onkeypress = CAFEVDB.stopRKey;

  CAFEVDB.exportMenu();

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

  $(':button.musician-instrument-insurance').click(function(event) {
    event.preventDefault();
    var values = $(this).attr('name');

    CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');

    return false;
  });

  $('input.alertdata.cafevdb-page').each(function(index) {
    var title = $(this).attr('name');
    var text  = $(this).attr('value');
    OC.dialogs.alert(text, title, undefined , true, true);
  });

  $('#missing-musicians-block').dialog({
    //dialogClass: 'no-close',
    width:'auto',
    height:'auto',
    resizable: false,
    autoResize: true,
    position:{my:'left top',
              at:'left+1% bottom+10%',
              of:'form.pme-form'
             }
  });

  $('form#projectlabelcontrol :submit').click(function(event) {
    event.preventDefault();

    var form = $(this.form);
    var pseudoSubmit = form.find('input.pme-view');
    PHPMYEDIT.tableDialog($(this.form), pseudoSubmit);

    return false;
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

