/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

console.log("CAFEVDB: here I am");

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  CAFEVDB.name             = 'cafevdb';
  CAFEVDB.toolTipsEnabled  = true;
  CAFEVDB.wysiwygEditor    = 'tinymce';
  CAFEVDB.language         = 'en';
  CAFEVDB.readyCallbacks   = []; ///< quasi-document-ready-callbacks
  CAFEVDB.creditsTimer     = -1;
  CAFEVDB.adminEmail       = t('cafevdb', 'unknown');
  CAFEVDB.adminName        = t('cafevdb', 'unknown');
  CAFEVDB.phpUserAgent     = t('cafevdb', 'unknown');

  // overrides from PHP, see config.js
  $.extend(CAFEVDB, CAFEVDB.initialState);

  // export allowHtml
  CAFEVDB.dialogs = {
    alert: function(text, title, callback, modal, allowHtml) {
      return OC.dialogs.message(
        text,
        title,
        'alert',
        OC.dialogs.OK_BUTTON,
        callback,
        modal,
        allowHtml
      );
    }
  };

  /**Register callbacks which are run after partial page reload in
   * order to "fake" document-ready. An alternate possibility would
   * have been to attach handlers to a custom signal and trigger that
   * signal if necessary.
   */
  CAFEVDB.addReadyCallback = function(callBack) {
    this.readyCallbacks.push(callBack);
  };

  /**Run artificial document-ready stuff. */
  CAFEVDB.runReadyCallbacks = function() {
    var idx;
    for (idx = 0; idx < this.readyCallbacks.length; ++idx) {
      var callback = this.readyCallbacks[idx];
      if (typeof callback == 'function') {
        callback();
      }
    }
    return false;
  };

  /**Add a WYSIWYG editor to the element specified by @a selector. */
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
    default:
    case 'ckeditor':
      if (typeof initCallback != 'function') {
        initCallback = function() {};
      }
      ClassicEditor.create(editorElement.get(0))
      .then(editor => {
        editorElement.data('ckeditor', editor);
        initCallback();
      })
      .catch( error => {
        console.error( 'There was a problem initializing the editor.', error );
        initCallback();
      });
      break;
    case 'tinymce':
      $(document).on('focusin', function(e) {
	//e.stopImmediatePropagaion();
        //alert(CAFEVDB.print_r(e.target, true));
        if ($(e.target).closest(".mce-container").length) {
	  e.stopImmediatePropagation();
	}
      });
      var plusConfig = {};
      if (!editorElement.is('textarea')) {
        plusConfig.inline = true;
      }
      if (typeof initialHeight != 'undefined') {
        plusConfig.height = initialHeight;
      }
      var mceConfig = myTinyMCE.getConfig(plusConfig);
      editorElement.on('cafevdb:tinemce-done', function(event) {
	console.info('tinyMCE init done callback');
	if (typeof initCallback == 'function') {
	  initCallback();
	}
      });
      editorElement.tinymce(mceConfig);
      break;
    };
  };

  /**Remove a WYSIWYG editor from the element specified by @a selector. */
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

  /**Steal the focus by moving it to a hidden element. Is there a
   * better way? The blur() method just does not work.
   */
  CAFEVDB.unfocus = function(element) {
    $('#focusstealer').focus();
  };

  CAFEVDB.makeId = function(length) {
    if (typeof length === 'undefined') {
      length = 8;
    }

    var text = "";
    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    for(var i = 0; i < length; i++) {
      text += possible.charAt(Math.floor(Math.random() * possible.length));
    }

    return text;
  };

  /**Display a transparent modal dialog which blocks the UI.
   */
  CAFEVDB.modalWaitNotification = function(message) {
    var dialogHolder = $('<div class="cafevdb modal-wait-notification"></div>');
    dialogHolder.html('<div class="cafevdb modal-wait-message">'+message+'</div>'+
                      '<div class="cafevdb modal-wait-animation"></div>');
    $('body').append(dialogHolder);
    dialogHolder.find('div.modal-wait-animation').progressbar({ value: false });
    dialogHolder.cafevDialog({
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
      close: function() {
        dialogHolder.dialog('close');
        dialogHolder.dialog('destroy').remove();
      }
      });
    return dialogHolder;
  };

  /**Unfortunately, the textare element does not fire a resize
   * event. This function emulates one.
   *
   * @param container selector or jQuery of container for event
   * delegation.
   *
   * @param textarea selector or jQuery
   *
   * @param delay Optional, defaults to 50. If true, fire the event
   * immediately, if set, then this is a delay in ms.
   *
   *
   */
  CAFEVDB.textareaResize = function(container, textarea, delay)
  {
    if (typeof textarea === 'undefined' && typeof delay === 'undefined') {
      // Variant with one argument, argument must be textarea.
      textarea = container;
      delay = textarea;
      container = null;
    } else if (delay === 'undefined' && $.isNumeric(textarea)) {
      // Variant with two argument, argument must be textarea.
      textarea = container;
      delay = textarea;
      container = null;
    }
    // otherwise first two arguments are container and textarea.
    if (typeof delay == 'undefined') {
      delay = 50; // ms
    }

    var handler = function(event) {
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
    };
    var events = 'mouseup mousemove';
    if (container) {
      $(container).off(events, textarea).on(events, textarea, handler);
    } else {
      $(textarea).off(events).on(events, handler);
    }
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
    var repeat_char = function (len, pad_char) {
      var str = '';
      for (var i = 0; i < len; i++) {
        str += pad_char;
      }
      return str;
    };
    var formatArray = function (obj, cur_depth, pad_val, pad_char) {
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

  CAFEVDB.selectMenuReset = function(select) {
    // deselect menu item
    select.find('option').prop('selected', false);
    select.trigger("chosen:updated");
  };

  CAFEVDB.chosenActive = function(select) {
    return select.data('chosen') != undefined;
  };

  CAFEVDB.fixupNoChosenMenu = function(select) {
    if (!this.chosenActive(select)) {
      // restore the data-placeholder as first option if chosen
      // is not active
      select.each(function(index) {
        var self = $(this);
        var placeHolder = self.data('placeholder');
        self.find('option:first').html(placeHolder);
      });
    }
  }

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

    dialogHolder.cafevDialog({
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
        CAFEVDB.toolTipsInit(dialogWidget);
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

        $.fn.cafevTooltip.remove();
        dialogHolder.dialog('close');
        dialogHolder.dialog('destroy').remove();
      }
    });
  };

  /**Create and submit a form with a POST request and given
   * parameters.
   *
   * @param url Location to post to.
   *
   * @param values Query string in GET notation.
   *
   * @param method Either 'get' or 'post', default is 'post'.
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
    form.appendTo($('div#content')); // needed?
    form.submit();
  };

  CAFEVDB.objectToHiddenInput = function(value, namePrefix)
  {
    if (typeof namePrefix === 'undefined') {
      namePrefix = '';
    }
    if (typeof value !== 'object') {
      return '<input type="hidden" name="'+namePrefix+'" value="'+value+'"/>'+"\n";
    }
    var result = '';
    if (value.constructor === Array) {
      var idx;
      for (idx = 0; idx < value.length; ++idx) {
        result += this.objectToHiddenInput(value[idx], namePrefix+'['+idx+']');
      }
    } else {
      for (var property in value) {
        if (value.hasOwnProperty(property)) {
          result += this.objectToHiddenInput(
            value[property], namePrefix === '' ? property : namePrefix+'['+property+']');
        }
      }
    }
    return result;
  }

  /**A variant of the old fashioned appsettings with a callback
   * instead of script loading
   */
  CAFEVDB.appSettings = function(route, callback) {
    var popup = $('#appsettings_popup');
    if (popup.is(':visible')) {
      popup.hide().html('');
    } else {
      const arrowclass = popup.hasClass('topright') ? 'up' : 'left';
      $.get(OC.generateUrl('/apps/cafevdb/' + route))
	.done(function(data) {
	  popup.html(data).ready(function() {
	    // assume the first element is a container div
	    if (popup.find('.popup-title').length > 0) {
	      popup.find(">:first-child").prepend('<a class="close"></a>').show();
	    } else {
	      popup.find(">:first-child").prepend('<h2>' + t('core', 'Settings') + '</h2><a class="close"></a>').show();
	    }
	    popup.find('.close').bind('click', function() {
	      popup.hide().html('');
	    });
	    popup.removeClass('hidden');
	    callback(popup);
	  }).show();
	})
	.fail(function(data) {
	  console.log(data);
	});
    }
  };


  /**Generate a form with given values, inject action (URL) and target
   * (iframe, ..), add to document, submit, remove from document.
   *
   * @param action URL
   *
   * @param target IFRAME
   *
   * @param values An object, either like created by serializeArray()
   * orby serialize().
   *
   */
  CAFEVDB.iframeFormSubmit = function(action, target, values)
  {
    var idx;
    var form = $('<form method="post" action="'+action+'" target="'+target+'"></form>');
    if (values.constructor === Array) {
      // serializeArray() stuff
      for(idx = 0; idx < values.length; ++idx) {
        form.append('<input type="hidden" name="'+values[idx].name+'" value="'+values[idx].value+'"/>');
      }
    } else {
      // object with { name: value }
      form.append(this.objectToHiddenInput(values));
    }
    $('body').append(form);
    form.submit().remove();
  };

  /**Handle the export menu actions.*/
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
      CAFEVDB.dialogs.alert(t('cafevdb', 'Export to the following format is not yet supported:')
			    +' "'+selected+'"',
			    t('cafevdb', 'Unimplemented'));
    } else {

      // this will be the alternate form-action
      exportscript = OC.filePath('cafevdb', 'ajax/export', exportscript);

      // Our export-scripts have the task to convert the display
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
    CAFEVDB.selectMenuReset(select);
    $.fn.cafevTooltip.remove();

    $('div.chosen-container').cafevTooltip({placement:'auto'});

    return false;

  };

  CAFEVDB.exportMenu = function(containerSel) {
    if (typeof containerSel === 'undefined') {
      containerSel = '#cafevdb-page-body';
    }
    var container = $(containerSel);

    // Emulate a pull-down menu with export options via the chosen
    // plugin.
    var exportSelect = container.find('select.pme-export-choice');
    exportSelect.chosen({
      disable_search:true,
      inherit_select_classes:true
    });

    // install placeholder as first item if chosen is not active
    CAFEVDB.fixupNoChosenMenu(exportSelect);

    container.find('select.pme-export-choice').
      off('change').
      on('change', function (event) {
      event.preventDefault();

      return CAFEVDB.tableExportMenu($(this));
    });

  }

  /**Open one invisible modal dialog in order to have a persistent
   * overlay for a group of dialogs.
   */
  CAFEVDB.modalizer = function(open) {
    var modalizer = $('#cafevdb-modalizer');
    if (open) {
      if (modalizer.length > 0) {
        return modalizer;
      }
      var dialogHolder = $('<div id="cafevdb-modalizer" class="cafevdb-modalizer"></div>');
      $('body').append(dialogHolder);
      dialogHolder.cafevDialog({
        title: '',
        position: { my: "top left",
                    at: "top-100% left-100%",
                    of: window },
        width: '0px',
        height: '0px',
        modal: true,
        closeOnEscape: false,
        dialogClass: 'transparent no-close zero-size cafevdb-modalizer',
        resizable: false,
        open: function() {
          // This one must be ours.
          CAFEVDB.dialogOverlay = $('.ui-widget-overlay:last');
        },
        close: function() {
          CAFEVDB.dialogOverlay = false;
          dialogHolder.dialog('close');
          dialogHolder.dialog('destroy').remove();
        }
      });
      return dialogHolder;
    } else {
      if (modalizer.length <= 0) {
        return true;
      }
      var overlayIndex = parseInt(modalizer.dialog('widget').css('z-index'));
      //alert('overlay index: '+overlayIndex);
      var numDialogs = 0;
      $('.ui-dialog.ui-widget').each(function(index) {
        var thisIndex = parseInt($(this).css('z-index'));
        //alert('that index: '+thisIndex);
        if (thisIndex >= overlayIndex) {
          ++numDialogs;
        }
      });

      //alert('num dialogs open: '+numDialogs);
      if (numDialogs > 1) {
        // one is the modalizer itself, of course.
        return modalizer;
      }

      modalizer.dialog('close');

      return true;
    }
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
    toBackButton.cafevTooltip({placement:'auto' });

    toBackButton.off('click');
    toBackButton.on('click', function() {
      var overlay = $('.ui-widget-overlay:last');
      var overlayIndex = 100; // OwnCloud header resides at 50.
      if (overlay.length > 0) {
        overlayIndex = parseInt(overlay.css('z-index'));
      }
      // will be only few, so what
      var needShuffle = false;
      $('.ui-dialog.ui-widget').not('.cafevdb-modalizer').each(function(index) {
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
    customCloseButton.cafevTooltip({placement:'auto' });

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

    container.find('input.date').datepicker({
      dateFormat : 'dd.mm.yy', // this is 4-digit year
      minDate: '01.01.1940'
    });

    container.find('input.datetime').datepicker({
      dateFormat : 'dd.mm.yy', // this is 4-digit year
      minDate: '01.01.1990'
    });

    container.find('td.money, td.signed-number').filter(function() {
      return $.trim($(this).text()).indexOf("-") == 0;
    }).addClass("negative");


    $(PHPMYEDIT.defaultSelector + ' input.pme-email').
      off('click').
      on('click', function(event) {
      event.stopImmediatePropagation();
      CAFEVDB.Email.emailFormPopup($(this.form).serialize());
      return false;
    });

    var form = container.find('form.pme-form').first();
    form.find('a.email').off('click').on('click', function(event) {
      event.preventDefault();
      var href = $(this).attr('href');
      var recordId = href.match(/[?]recordId=(\d+)$/);
      if (typeof recordId[1] != 'undefined') {
        recordId = recordId[1];
      } else {
        return false; // Mmmh, echo error diagnostics to the user?
      }
      var post = form.serialize();
      post += '&PME_sys_mrecs[]=' + recordId;
      post += '&emailRecipients[MemberStatusFilter][0]=regular';
      post += '&emailRecipients[MemberStatusFilter][1]=passive';
      post += '&emailRecipients[MemberStatusFilter][2]=soloist';
      post += '&emailRecipients[MemberStatusFilter][3]=conductor';
      CAFEVDB.Email.emailFormPopup(post, true, true);
      return false;
    });

    // This could also be wrapped into a popup maybe, and lead back to
    // the brief-instrumentation table on success.
    //$(PHPMYEDIT.defaultSelector + ' input.pme-bulkcommit').addClass('formsubmit');
  };

  /**Popup a dialog with debug info if data.data.debug is set and non
   * empty.
   */
  CAFEVDB.debugPopup = function(data, callback) {
    if (typeof data.debug != 'undefined' && datadebug != '') {
      if (typeof callback != 'function') {
        callback = undefined;
      }
      CAFEVDB.dialogs.info('<div class="debug error contents">'+data.debug+'</div>',
			   t('cafevdb', 'Debug Information'),
			   callback, true, true);
    }
  };

  CAFEVDB.httpStatus = {
    '200': t('cafevdb', 'OK'),
    '201': t('cafevdb', 'Created'),
    '202': t('cafevdb', 'Accepted'),
    '203': t('cafevdb', 'Non-Authoritative Information'),
    '204': t('cafevdb', 'No Content'),
    '205': t('cafevdb', 'Reset Content'),
    '206': t('cafevdb', 'Partial Content'),
    '207': t('cafevdb', 'Multi-Status (WebDAV)'),
    '208': t('cafevdb', 'Already Reported (WebDAV)'),
    '226': t('cafevdb', 'IM Used'),
    '300': t('cafevdb', 'Multiple Choices'),
    '301': t('cafevdb', 'Moved Permanently'),
    '302': t('cafevdb', 'Found'),
    '303': t('cafevdb', 'See Other'),
    '304': t('cafevdb', 'Not Modified'),
    '305': t('cafevdb', 'Use Proxy'),
    '306': t('cafevdb', '(Unused)'),
    '307': t('cafevdb', 'Temporary Redirect'),
    '308': t('cafevdb', 'Permanent Redirect (experimental)'),
    '400': t('cafevdb', 'Bad Request'),
    '401': t('cafevdb', 'Unauthorized'),
    '402': t('cafevdb', 'Payment Required'),
    '403': t('cafevdb', 'Forbidden'),
    '404': t('cafevdb', 'Not Found'),
    '405': t('cafevdb', 'Method Not Allowed'),
    '406': t('cafevdb', 'Not Acceptable'),
    '407': t('cafevdb', 'Proxy Authentication Required'),
    '408': t('cafevdb', 'Request Timeout'),
    '409': t('cafevdb', 'Conflict'),
    '410': t('cafevdb', 'Gone'),
    '411': t('cafevdb', 'Length Required'),
    '412': t('cafevdb', 'Precondition Failed'),
    '413': t('cafevdb', 'Request Entity Too Large'),
    '414': t('cafevdb', 'Request-URI Too Long'),
    '415': t('cafevdb', 'Unsupported Media Type'),
    '416': t('cafevdb', 'Requested Range Not Satisfiable'),
    '417': t('cafevdb', 'Expectation Failed'),
    '418': t('cafevdb', 'I\'m a teapot (RFC 2324)'),
    '420': t('cafevdb', 'Enhance Your Calm (Twitter)'),
    '422': t('cafevdb', 'Unprocessable Entity (WebDAV)'),
    '423': t('cafevdb', 'Locked (WebDAV)'),
    '424': t('cafevdb', 'Failed Dependency (WebDAV)'),
    '425': t('cafevdb', 'Reserved for WebDAV'),
    '426': t('cafevdb', 'Upgrade Required'),
    '428': t('cafevdb', 'Precondition Required'),
    '429': t('cafevdb', 'Too Many Requests'),
    '431': t('cafevdb', 'Request Header Fields Too Large'),
    '444': t('cafevdb', 'No Response (Nginx)'),
    '449': t('cafevdb', 'Retry With (Microsoft)'),
    '450': t('cafevdb', 'Blocked by Windows Parental Controls (Microsoft)'),
    '451': t('cafevdb', 'Unavailable For Legal Reasons'),
    '499': t('cafevdb', 'Client Closed Request (Nginx)'),
    '500': t('cafevdb', 'Internal Server Error'),
    '501': t('cafevdb', 'Not Implemented'),
    '502': t('cafevdb', 'Bad Gateway'),
    '503': t('cafevdb', 'Service Unavailable'),
    '504': t('cafevdb', 'Gateway Timeout'),
    '505': t('cafevdb', 'HTTP Version Not Supported'),
    '506': t('cafevdb', 'Variant Also Negotiates (Experimental)'),
    '507': t('cafevdb', 'Insufficient Storage (WebDAV)'),
    '508': t('cafevdb', 'Loop Detected (WebDAV)'),
    '509': t('cafevdb', 'Bandwidth Limit Exceeded (Apache)'),
    '510': t('cafevdb', 'Not Extended'),
    '511': t('cafevdb', 'Network Authentication Required'),
    '598': t('cafevdb', 'Network read timeout error'),
    '599': t('cafevdb', 'Network connect timeout error')
  };

  /**Generate some diagnostic output, mostly needed during application
   * development. This is intended to be called from the fail()
   * callback.
   */
  CAFEVDB.handleAjaxError = function(xhr, textStatus, errorThrown, errorCB)
  {
    if (typeof errorCB == 'undefined') {
      errorCB = function () {}
    }

    // Seemingly Nextcloud always ever only returns one of these:
    const HTTP_STATUS_OK = 200;
    const HTTP_STATUS_BAD_REQUEST = 400;
    const HTTP_STATUS_UNAUTHORIZED = 401;
    const HTTP_STATUS_NOT_FOUND = 404;
    const HTTP_STATUS_CONFLICT = 409;
    const HTTP_STATUS_INTERNAL_SERVER_ERROR = 500;

    const failData = CAFEVDB.ajaxFailData(xhr, textStatus, errorThrown);
    console.info("AJAX failure data", failData);

    switch (textStatus) {
    case 'notmodified':
    case 'nocontent':
    case 'error':
    case 'timeout':
    case 'abort':
    case 'parsererror':
    case 'success': // this should not happen here
    default:
    }

    const caption = t('cafevdb', 'Error');
    var info = '<span class="http-status error">' + CAFEVDB.httpStatus[xhr.status] + '</span>';
    console.info(xhr.status, info, errorThrown, textStatus);

    var autoReport = '<a href="mailto:'
          + CAFEVDB.adminEmail
          + '?subject=' + '[CAFEVDB Error] Error Feedback'
          + '&body=' + encodeURIComponent(
	    'JavaScript User Agent:'
              + "\n"
              + navigator.userAgent
              + "\n"
              + "\n"
              + 'PHP User Agent:'
              + "\n"
              + CAFEVDB.phpUserAgent
              + "\n"
              + "\n"
              + 'Error Code: ' +  CAFEVDB.httpStatus[xhr.status]
              + "\n"
              + "\n"
	      + 'Error Data: ' + CAFEVDB.print_r(failData, true)
              + "\n")
          + '">'
          + CAFEVDB.adminName
          + '</a>';

    switch (xhr.status) {
    case HTTP_STATUS_OK:
    case HTTP_STATUS_BAD_REQUEST:
    case HTTP_STATUS_NOT_FOUND:
    case HTTP_STATUS_CONFLICT:
    case HTTP_STATUS_INTERNAL_SERVER_ERROR:
      if (failData.error) {
	info += ': ' + '<span class=""error name">' + failData.error + '</span>';
      }
      if (failData.message) {
        info += '<br/>' + failData.message;
      }
      info += '<div class="error toastify feedback-link">'
            + t('cafevdb', 'Feedback email: {AutoReport}', { AutoReport: autoReport }, -1, { escape: false })
            + '</div>';
      autoReport = '';
      if (failData.exception) {
        info += '<div class="exception error name"><pre>'+failData.exception+'</pre></div>'
	      + '<div class="exception error trace"><pre>'+failData.trace+'</pre></div>';
      }
      break;
    case HTTP_STATUS_UNAUTHORIZED:
      // no point in continuing, direct the user to the login page
      errorCB = function() {
        if(OC.webroot !== '') {
          window.location.replace(OC.webroot);
        } else {
          window.location.replace('/');
        }
      };

      var generalHint = t('cafevdb', 'Something went wrong.');
      generalHint += '<br/>'
                   + t('cafevdb', 'If it should be the case that you are already '
                                + 'logged in for a long time without interacting '
                                + 'with the web-app, then the reason for this '
                                + 'error is probably a simple timeout.');
      generalHint += '<br/>'
                   + t('cafevdb', 'I any case it may help to logoff and logon again, as a '
                                + 'temporary work-around. You will be redirected to the '
                                + 'log-in page when you close this window.');
      info += '<div class="error general">'+generalHint+'</div>';
      // info += '<div class="error toastify feedback-link">'
      //       + t('cafevdb', 'Feedback email: {AutoReport}', { AutoReport: autoReport }, -1, { escape: false })
      //       + '</div>';
      break;
    }

    console.info(info);
    CAFEVDB.dialogs.alert(
      info, caption, function() { errorCB(failData); }, true, true);
    return failData;
  };

  /**Generate some diagnostic output, mostly needed during
   * application development. This is intended to be called from the
   * done() callback after a successful AJAX call.
   *
   * @param data The data passed to the callback to $.post()
   *
   * @param required List of required fields in data.data.
   *
   */
  CAFEVDB.validateAjaxResponse = function(data, required, errorCB)
  {
    if (typeof data.data != 'undefined' && typeof data.data.status != 'undefined') {
      console.error('********** Success handler called as error handler ************');
      if (data.data.status != 'success') {
        CAFEVDB.handleAjaxError(null, data, null);
        return false;
      } else {
        data = data.data;
      }
    }
    if (typeof errorCB == 'undefined') {
      errorCB = function() {};
    }
    // error handling
    if (typeof data == 'undefined' || !data) {
      CAFEVDB.dialogs.alert(t('cafevdb', 'Unrecoverable unknown internal error, '+
                              'no further information available, sorry.'),
			    t('cafevdb', 'Internal Error'), errorCB, true);
      return false;
    }
    var missing = '';
    var idx;
    for (idx = 0; idx < required.length; ++idx) {
      if (typeof data[required[idx]] == 'undefined') {
        missing += t('cafevdb', 'Field {RequiredField} not present in AJAX response.',
                     { RequiredField: required[idx] })+"<br>";
      }
    }
    if (missing.length > 0) {
      var info = '';
      if (typeof data.message != 'undefined') {
	info += data.message;
      }
      if (missing.length > 0) {
        info += t('cafevdb', 'Missing data');
      }
      // Add missing fields only if no exception or setup-error was
      // caught as in this case no regular data-fields have been
      // constructed
      info += '<div class="missing error">'+missing+'</div>';

      // Display additional debug info if any
      CAFEVDB.debugPopup(data);

      var caption = data.caption;
      if (typeof caption == 'undefined' || caption == '') {
        caption = t('cafevdb', 'Error');
        data.caption = caption;
      }
      CAFEVDB.dialogs.alert(info, caption, errorCB, true, true);
      return false;
    }
    return true;
  };

  /**Fetch data from an error response.
   *
   * @param xhr jqXHR, see fail() method of jQuery ajax.
   *
   * @param status from jQuery, see fail() method of jQuery ajax.
   *
   * @param errorThrown, see fail() method of jQuery ajax.
   */
  CAFEVDB.ajaxFailData = function(xhr, status, errorThrown) {
    const ct = xhr.getResponseHeader("content-type") || "";
    var data = {
      'error': errorThrown,
      'status': status,
      'message': t('cafevdb', 'Unknown JSON error response to AJAX call: {status} / {error}')
    };
    if (ct.indexOf('html') > -1) {
      console.log('html response', xhr, status, errorThrown);
      console.log(xhr.status);
      data.message = t('cafevdb', 'HTTP error response to AJAX call: {code} / {error}',
                       {'code': xhr.status, 'error': errorThrown});
    } else if (ct.indexOf('json') > -1) {
      const response = JSON.parse(xhr.responseText);
      console.info('XHR response text', xhr.responseText);
      console.log('JSON response', response);
      data = {...data, ...response };
    } else {
      console.log('unknown response');
    }
    console.info(data);
    return data;
  };

  /**Generate some diagnostic output, mostly needed during application
   * development.
   *
   * @param xhr jqXHR, see fail() method of jQuery ajax.
   *
   * @param status from jQuery, see fail() method of jQuery ajax.
   *
   * @param errorThrown, see fail() method of jQuery ajax.
   */
  CAFEVDB.ajaxFailMessage = function(xhr, status, errorThrown) {
    return CAFEVDB.ajaxFailData(xhr, status, errorThrown).message;
  };

  CAFEVDB.attachToolTip = function(selector, options) {
    var defaultOptions = {
        container:'body',
        html:true,
        placement:'auto'
    };
    options = $.extend({}, defaultOptions, options);
    if (typeof options.placement == 'string') {
      options.placement = options.placement;
    }
    return $(selector).cafevTooltip(options);
  };

  /**Exchange "tipsy" tooltips already attached to an element by
   * something different. This has to be done the "hard" way: first
   * unset data('tipsy') by setting it to null, then call the
   * tipsy-constructor with the new values.
   *
   * @param selector jQuery element selector
   *
   * @param options Tool-tip options
   *
   * @param container Optional container containing selected
   * elements, i.e. tool-tip stuff will be applied to all elements
   * inside @a container matching @a selector.
   */
  CAFEVDB.applyToolTips = function(selector, options, container) {
    var element;
    if (selector instanceof jQuery) {
      element = selector;
    } else if (typeof container != 'undefined') {
      element = container.find(selector);
    } else {
      element = $(selector);
    }
    // remove any pending tooltip from the document
    $.fn.cafevTooltip.remove();

    // fetch suitable options from the elements class attribute
    var classOptions = { placement:'auto',
                         html: true };
    var classAttr = element.attr('class');
    var extraClass = false;
    if (options.hasOwnProperty('cssclass')) {
      extraClass = options.cssclass;
    }
    if (typeof classAttr != 'undefined') {
      if (classAttr.match(/tooltip-off/) !== null) {
        $(this).cafevTooltip('disable');
        return;
      }
      var tooltipClasses = classAttr.match(/tooltip-[a-z-]+/g);
      if (tooltipClasses) {
        var idx;
        for(idx = 0; idx < tooltipClasses.length; ++idx) {
          var tooltipClass = tooltipClasses[idx];
          var placement = tooltipClass.match(/^tooltip-(bottom|top|right|left)$/);
          if (placement && placement.length == 2 && placement[1].length > 0) {
            classOptions.placement = placement[1];
            continue;
          }
          extraClass = tooltipClass;
        }
      }
    }
    if (typeof options == 'undefined') {
      options = classOptions;
    } else {
      // supplied options override class options
      options = $.extend({}, classOptions, options);
    }

    if (extraClass) {
      options.template = '<div class="tooltip '
                       + extraClass
                       + '" role="tooltip">'
                       + '<div class="tooltip-arrow"></div>'
                       + '<div class="tooltip-inner"></div>'
                       + '</div>';
    }
    element.cafevTooltip('destroy'); // remove any already installed stuff
    element.cafevTooltip(options);   // make it new
  };

  CAFEVDB.toolTipsOnOff = function(onOff) {
    CAFEVDB.toolTipsEnabled = !!onOff;
    if (CAFEVDB.toolTipsEnabled) {
      $.fn.cafevTooltip.enable();
    } else {
      $.fn.cafevTooltip.disable();
      $.fn.cafevTooltip.remove(); // remove any left-over items.
    }
  };

  CAFEVDB.snapperClose = function() {
    // snapper will close on clicking navigation entries
    $('#navigation-list li.nav-heading a').trigger('click');
  };

  /**Initialize our tipsy stuff. Only exchange for our own thingies, of course.
   */
  CAFEVDB.toolTipsInit = function(containerSel) {
    if (typeof containerSel === 'undefined') {
      containerSel = '#content.app-cafevdb';
    }
    var container = $(containerSel);

    console.log("tooltips container", containerSel, container.length);

    // container.find('button.settings').cafevTooltip({placement:'bottom'});
    container.find('select').cafevTooltip({placement:'right'});
    container.find('option').cafevTooltip({placement:'right'});
    container.find('div.chosen-container').cafevTooltip({placement:'top'});
    container.find('form.cafevdb-control input').cafevTooltip({placement:'bottom'});
    container.find('button.settings').cafevTooltip({placement:'bottom'});
    container.find('.pme-sort').cafevTooltip({placement:'bottom'});
    container.find('.pme-misc-check').cafevTooltip({placement:'bottom'});
    container.find('label').cafevTooltip({placement:'top'});
    container.find('.header-right img').cafevTooltip({placement:'bottom'});
    container.find('img').cafevTooltip({placement:'bottom'});
    container.find('button').cafevTooltip({placement:'right'});
    container.find('li.pme-navigation.table-tabs').cafevTooltip({placement:'bottom'});

    // pme input stuff and tables.
    container.find('textarea.pme-input').cafevTooltip(
      {placement:'top', cssclass:'tooltip-wide'});
    container.find('input.pme-input').cafevTooltip(
      {placement:'top', cssclass:'tooltip-wide'});
    container.find('table.pme-main td').cafevTooltip(
      {placement:'top', cssclass:'tooltip-wide'});
    container.find('table.pme-main th').cafevTooltip(
      {placement:'bottom'});

    // original tipsy stuff
    container.find('.displayName .action').cafevTooltip({placement:'top'});
    container.find('.password .action').cafevTooltip({placement:'top'});
    container.find('#upload').cafevTooltip({placement:'right'});
    container.find('.selectedActions a').cafevTooltip({placement:'top'});
    container.find('a.action.delete').cafevTooltip({placement:'left'});
    container.find('a.action').cafevTooltip({placement:'top'});
    container.find('td .modified').cafevTooltip({placement:'top'});
    container.find('td.lastLogin').cafevTooltip({placement:'top', html:true});
    container.find('input:not([type=hidden])').cafevTooltip({placement:'right'});
    container.find('textarea').cafevTooltip({placement:'right'});

    // everything else.
    container.find('.tip').cafevTooltip({placement:'right'});

    container.find('select[class*="pme-filter"]').cafevTooltip(
      { placement:'bottom', cssclass:'tooltip-wide' }
    );
    container.find('input[class*="pme-filter"]').cafevTooltip(
      { placement:'bottom', cssclass:'tooltip-wide' }
    );

    container.find('[class*="tooltip-"]').each(function(index) {
      //console.log("tooltip autoclass", $(this), $(this).attr('title'));
      $(this).cafevTooltip({});
    });

    container.find('#app-navigation-toggle').cafevTooltip();

    // Tipsy greedily enables itself when attaching it to elements, so
    // ...
    if (CAFEVDB.toolTipsEnabled) {
      $.fn.cafevTooltip.enable();
    } else {
      $.fn.cafevTooltip.disable();
    }
  };

  /**Get or set the option value(s) of a select box.
   *
   * @param select The select element. If it is an ordinary input
   * element, then in "set" mode its value is set to optionValues.
   *
   * @param optionValues Single option value or array of option
   * values to set.
   */
  CAFEVDB.selectValues = function(select, optionValues) {
    select = $(select);
    var multiple = select.prop('multiple');
    if (typeof optionValues === 'undefined') {
      console.log('selectValues read = ', select.val());
      var result = select.val();
      if (multiple && !result) {
        result = [];
      }
      return select.val();
    }
    if (!(optionValues instanceof Array)) {
      optionValues = [ optionValues ];
    }
    if (!multiple && optionValues.length > 1) {
      optionValues = [ optionValues[0] ];
    }
    // setter has to use foreach
    select.each(function(idx) {
      var self = $(this);
      if (!self.is('select')) {
        // graceful degrade for non selects
        self.val(optionValues[0] ? optionValues[0] : '');
        return true;
      }
      self.find('option').each(function(idx) {
        var option = $(this);
        option.prop('selected', optionValues.indexOf(option.val()) >= 0);
      });
      console.log('selectValues', 'update chosen');
      self.trigger('chosen:updated'); // in case ...
      return true;
    });
    return true;
  };

  var progressTimer;
  CAFEVDB.pollProgressStatus = function(id, callbacks, interval) {
    const defaultCallbacks = {
      'update': function(data) {},
      'fail': function(data) {}
    };
    callbacks = { ...defaultCallbacks, ...callbacks };
    interval = interval || 800;

    const poll = function() {
      $.get(OC.generateUrl('/apps/cafevdb/foregroundjob/progress/'+id))
      .done(function(data) {
        if (!callbacks.update(data)) {
	  console.info("Finish polling");
          clearTimeout(progressTimer);
          progressTimer = false;
          return;
        }
	console.info("Restart timer.");
        progressTimer = setTimeout(poll, interval);
      })
      .fail(function(xhr, status, errorThrown) {
        clearTimeout(progressTimer);
        progressTimer = false;
        callbacks.fail(xhr, status, errorThrown);
      });
    };
    poll();
  };
  CAFEVDB.pollProgressStatus.stop = function() {
    clearTimeout(progressTimer);
    progressTimer = false;
  };
  CAFEVDB.pollProgressStatus.active = function() {
    return !!progressTimer;
  };

})(window, jQuery, CAFEVDB);

$(document).ready(function(){
  // @@TODO perhaps collects these things in before-ready.js
  document.onkeypress = CAFEVDB.stopRKey;

  var resizeCount = 0;

  window.oldWidth = -1;
  window.oldHeight = -1;
  $(window).on('resize', function(event) {
    var win = this;
    if (!win.resizeTimeout) {
      var delay = 50;
      var width = (win.innerWidth > 0) ? win.innerWidth : screen.width;
      var height = (win.innerHeight > 0) ? win.innerHeight : screen.height;
      if (win.oldWidth != width || win.oldHeight != height) {
        console.log('cafevdb size change', width, win.oldWidth, height, win.oldHeight);
        win.resizeTimeout = setTimeout(
          function() {
            win.resizeTimeout = null;
            $('.resize-target, .ui-dialog-content').trigger('resize');
          }, delay);
        win.oldHeight = height;
        win.oldWidth = width;
      }
    }
    return false;
  });

  /****************************************************************************
   *
   * Add handlers as delegates. Note however that the snapper is
   * attached to #app-content below #content, so it is not possible to
   * prevent the snapper events. If we want to change this we have to
   * insert another div-container inside #app-content.
   *
   */
  var content = $('#content');
  var appInnerContent = $('#app-inner-content');

  content.on('click', ':button.events',
             function(event) {
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

  // Display the overview-page for the given project.
  content.on('click', 'ul#navigation-list li.nav-projectlabelcontrol a',
             function(event) {
               event.stopImmediatePropagation();

               var data = $(this).data('json');

               CAFEVDB.Projects.projectViewPopup(PHPMYEDIT.selector(), data);
               return false;
             });

  // Display the instrumentation numbers in a dialog widget
  content.on('click', 'ul#navigation-list li.nav-projectinstrumentscontrol a',
             function(event) {
               var data = $(this).data('json');
               CAFEVDB.Projects.instrumentationNumbersPopup(PHPMYEDIT.defaultSelector, data);
               return false;
             });

  CAFEVDB.addReadyCallback(function() {
    $('input.alertdata.cafevdb-page').each(function(index) {
      var title = $(this).attr('name');
      var text  = $(this).attr('value');
      CAFEVDB.dialogs.alert(text, title, undefined, true, true);
    });

  });

  // fire an event when this have been finished
  console.log("trigger loaded");
  $(document).trigger("cafevdb:donecafevdbjs")
});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
