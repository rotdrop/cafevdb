/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName } from './globals.js';
import generateUrl from './generate-url.js';
import * as Dialogs from './dialogs.js';
import { selector as pmeSelector } from './pme.js';
import myTinyMCE from './tinymceinit';

const ClassicEditor = require('@ckeditor/ckeditor5-build-classic');

require('cafevdb.css');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

$.extend(
  globalState,
  $.extend({
    appName,
    toolTipsEnabled: true,
    wysiwygEditor: 'tinymce',
    language: 'en',
    readyCallbacks: [], // quasi-document-ready-callbacks
    creditsTimer: -1,
    adminContact: t(appName, 'unknown'),
    phpUserAgent: t(appName, 'unknown'),
  }, globalState)
);

/**
 * Register callbacks which are run after partial page reload in
 * order to "fake" document-ready. An alternate possibility would
 * have been to attach handlers to a custom signal and trigger that
 * signal if necessary.
 */
const addReadyCallback = function(callBack) {
  globalState.readyCallbacks.push(callBack);
};

/**
 * Run artificial document-ready stuff.
 *
 * @returns {bool} TBD.
 */
const runReadyCallbacks = function() {
  for (let idx = 0; idx < globalState.readyCallbacks.length; ++idx) {
    const callback = globalState.readyCallbacks[idx];
    if (typeof callback == 'function') {
      callback();
    }
  }
  return false;
};

/**
 * Add a WYSIWYG editor to the element specified by @a selector.
 *
 * @param {string} selector TBD.
 *
 * @param {Function} initCallback TBD.
 *
 * @param {int} initialHeight TBD.
 *
 */
const addEditor = function(selector, initCallback, initialHeight) {
  console.debug('CAFEVDB.addEditor');
  const editorElement = $(selector);
  if (!editorElement.length) {
    if (typeof initCallback == 'function') {
      initCallback();
    }
    return;
  }
  switch (globalState.wysiwygEditor) {
  default:
  case 'ckeditor':
    if (typeof initCallback != 'function') {
      initCallback = function() {};
    }
    console.debug("attach ckeditor");
    ClassicEditor
      .create(editorElement.get(0))
      .then(editor => {
        console.debug("ckeditor promise");
        editorElement.data('ckeditor', editor);
        initCallback();
      })
      .catch( error => {
        console.debug('There was a problem initializing the editor.', error );
        initCallback();
      });
    break;
  case 'tinymce': {
    // This is a Gurkerei
    $(document).on('focusin', function(e) {
      // e.stopImmediatePropagaion();
      // alert(CAFEVDB.print_r(e.target, true));
      if ($(e.target).closest(".mce-container").length) {
        e.stopImmediatePropagation();
      }
    });
    const plusConfig = {};
    if (!editorElement.is('textarea')) {
      plusConfig.inline = true;
    }
    if (typeof initialHeight != 'undefined') {
      plusConfig.height = initialHeight;
    }
    const mceDeferred = $.Deferred();
    mceDeferred.then(
      function() {
        console.info('MCE promise succeeded');
        if (typeof initCallback == 'function') {
          initCallback();
        }
      },
      function() {
        console.error('MCE promise failed');
        if (typeof initCallback == 'function') {
          initCallback();
        }
        editorElement.css('visibility', '');
      }
    );
    const mceConfig = myTinyMCE.getConfig(plusConfig);
    editorElement
      .off('cafevdb:tinymce-done')
      .on('cafevdb:tinymce-done', function(event) {
        console.info('tinyMCE init done callback');
        mceDeferred.resolve();
      });
    console.debug("attach tinymce");
    editorElement.tinymce(mceConfig);
    // wait for at most 5 seconds, then cancel
    const timeout = 10;
    setTimeout(function() {
      mceDeferred.reject();
    }, timeout * 1000);
    break;
  }
  };
};

/**
 * Remove a WYSIWYG editor from the element specified by @a selector.
 *
 * @param {String} selector TBD.
 */
const removeEditor = function(selector) {
  const editorElement = $(selector);
  if (!editorElement.length) {
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    if (editorElement.ckeditor) {
      editorElement.ckeditor().remove();
    }
    break;
  case 'tinymce':
    editorElement.tinymce().remove();
    break;
  default:
    if (editorElement.ckeditor) {
      editorElement.ckeditor().remove();
    }
    if (editorElement.tinymce) {
      editorElement.tinymce().remove();
    }
    break;
  };
};

/**Replace the contents of the given editor by contents. */
const updateEditor = function(selector, contents) {
  const editorElement = $(selector);
  var editor;
  if (!editorElement.length) {
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    if (editorElement.ckeditor) {
      editor = editorElement.ckeditor().ckeditorGet();
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
      editor = editorElement.ckeditor().ckeditorGet();
      editor.setData(contents);
      // ckeditor snapshots itself on update.
      //editor.undoManager.save(true);
    }
    break;
  };
};

/**
 * Generate a "snapshot", meaning an undo-level, for instance after
 * replacing all data by loading email templates and stuff.
 */
const snapshotEditor = function(selector) {
  const editorElement = $(selector);
  var editor;
  if (!editorElement.length) {
    return;
  }
  switch (globalState.wysiwygEditor) {
  case 'ckeditor':
    if (editorElement.ckeditor) {
      editor = editorElement.ckeditor().ckeditorGet();
      editor.undoManager.save(true);
    }
    break;
  case 'tinymce':
    editorElement.tinymce().undoManager.add();
    break;
  default:
    if (editorElement.ckeditor) {
      editor = editorElement.ckeditor().ckeditorGet();
      editor.undoManager.save(true);
    }
    break;
  };
};

/**
 * Steal the focus by moving it to a hidden element. Is there a
 * better way? The blur() method just does not work.
 */
const unfocus = function(element) {
  $('#focusstealer').focus();
};

/**
 * Generate some random Id. @TODO replace.
 */
const makeId = function(length) {
  if (typeof length === 'undefined') {
    length = 8;
  }

  var text = '';
  const possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

  for(var i = 0; i < length; i++) {
    text += possible.charAt(Math.floor(Math.random() * possible.length));
  }

  return text;
};

/**
 *Display a transparent modal dialog which blocks the UI.
 */
const modalWaitNotification = function(message) {
  const dialogHolder = $('<div class="cafevdb modal-wait-notification"></div>');
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
    dialogClass: 'transparent no-close wait-notification cafevdb',
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

/**
 * Unfortunately, the textare element does not fire a resize
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
const textareaResize = function(container, textarea, delay)
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

  const handler = function(event) {
    if (textarea.oldwidth  === null) {
      textarea.oldwidth  = textarea.style.width;
    }
    if (textarea.oldheight === null) {
      textarea.oldheight = textarea.style.height;
    }
    if (textarea.style.width != textarea.oldwidth || textarea.style.height != textarea.oldheight) {
      const self = this;
      if (delay > 0) {
        if (textarea.resize_timeout) {
          clearTimeout(textarea.resize_timeout);
        }
        textarea.resize_timeout = setTimeout(function() {
          $(self).resize();
        }, delay);
      } else {
        $(this).resize();
      }
      textarea.oldwidth  = textarea.style.width;
      textarea.oldheight = textarea.style.height;
    }
    return true;
  };
  const events = 'mouseup mousemove';
  if (container) {
    $(container).off(events, textarea).on(events, textarea, handler);
  } else {
    $(textarea).off(events).on(events, handler);
  }
};

const stopRKey = function(evt) {
  evt = (evt) ? evt : ((event) ? event : null);
  const node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
  if ((evt.keyCode == 13) && (node.type=="text"))  {
    return false;
  }
  return true;
};

const urlDecode = function(str) {
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

const urlEncode = function(str) {
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

/**
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
const queryData = function(queryString, preserveDuplicates) {

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
    const queryComponents = queryString.split(/[&;]/g);

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
      } else {
        // store the value
        result[key] = value;
      }
    }
  }
  return result;
};

const selectMenuReset = function(select) {
  // deselect menu item
  select.find('option').prop('selected', false);
  select.trigger("chosen:updated");
};

const chosenActive = function(select) {
  return select.data('chosen') != undefined;
};

const fixupNoChosenMenu = function(select) {
  if (!chosenActive(select)) {
    // restore the data-placeholder as first option if chosen
    // is not active
    select.each(function(index) {
      const self = $(this);
      const placeHolder = self.data('placeholder');
      self.find('option:first').html(placeHolder);
    });
  }
};

/*
 * jQuery dialog popup with one chosen multi-selelct box inside.
 */
const chosenPopup = function(contents, userOptions)
{
  const defaultOptions = {
    title: t(appName, 'Choose some Options'),
    position: { my: "center center",
                at: "center center",
                of: window },
    dialogClass: false,
    saveText: t(appName, 'Save'),
    saveTitle: t(appName,
                 'Accept the currently selected options and return to the underlying form. '),
    cancelText: t(appName, 'Cancel'),
    cancelTitle: t(appName,
                   'Discard the current selection and close the dialog. '+
                   'The initial set of selected options will remain unchanged.'),
    buttons: [], // additional buttons.
    openCallback: false,
    saveCallback: false,
    closeCallback: false
  };
  const options = $.extend({}, defaultOptions, userOptions);

  const cssClass = (options.dialogClass ? options.dialogClass + ' ' : '') + 'chosen-popup-dialog';
  const dialogHolder = $('<div class="'+cssClass+'"></div>');
  dialogHolder.html(contents);
  const selectElement = dialogHolder.find('select');
  $('body').append(dialogHolder);

  const buttons = [
    { text: options.saveText,
      //icons: { primary: 'ui-icon-check' },
      'class': 'save',
      title: options.saveTitle,
      click: function() {
        const self = this;

        var selectedOptions = [];
        selectElement.find('option:selected').each(function(idx) {
          const self = $(this);
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
      const dialogWidget = dialogHolder.dialog('widget');
      toolTipsInit(dialogWidget);
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

/**
 * Create and submit a form with a POST request and given
 * parameters.
 *
 * @param {String} url Location to post to.
 *
 * @param {String} values Query string in GET notation.
 *
 * @param {String} method Either 'get' or 'post', default is 'post'.
 */
const formSubmit = function(url, values, method) {

  if (typeof method === 'undefined') {
    method = 'post';
  }

  const form = $('<form method="'+method+'" action="'+url+'"></form>');

  const splitValues = values.split('&');
  for (var i = 0; i < splitValues.length; ++i) {
    var nameValue = splitValues[i].split('=');
    $('<input />').attr('type', 'hidden')
      .attr('name', nameValue[0])
      .attr('value', urldecode(nameValue[1]))
      .appendTo(form);
  }
  form.appendTo($('div#content')); // needed?
  form.submit();
};

const objectToHiddenInput = function(value, namePrefix)
{
  if (typeof namePrefix === 'undefined') {
    namePrefix = '';
  }
  if (typeof value !== 'object') {
    return '<input type="hidden" name="'+namePrefix+'" value="'+value+'"/>'+"\n";
  }
  var result = '';
  if (value.constructor === Array) {
    for (var idx = 0; idx < value.length; ++idx) {
      result += objectToHiddenInput(value[idx], namePrefix+'['+idx+']');
    }
  } else {
    for (var property in value) {
      if (value.hasOwnProperty(property)) {
        result += objectToHiddenInput(
          value[property], namePrefix === '' ? property : namePrefix+'['+property+']');
      }
    }
  }
  return result;
};

/**
 * A variant of the old fashioned appsettings with a callback
 * instead of script loading
 */
const appSettings = function(route, callback) {
  const popup = $('#appsettings_popup');
  if (popup.is(':visible')) {
    popup.addClass('hidden').html('');
    //popup.hide().html('');
  } else {
    const arrowclass = popup.hasClass('topright') ? 'up' : 'left';
    $.get(generateUrl(route))
      .done(function(data) {
        popup
          .html(data)
          .ready(function() {
            // assume the first element is a container div
            if (popup.find('.popup-title').length > 0) {
              popup.find('.popup-title').append('<a class="close"></a>');
              //popup.find(">:first-child").prepend('<a class="close"></a>').show();
            } else {
              popup.find(">:first-child").prepend('<div class="popup-title"><h2>' + t('core', 'Settings') + '</h2><a class="close"></a></div>');
            }
            popup.find('.close').bind('click', function() {
              popup.addClass('hidden').html('');
            });
            callback(popup);
            popup.find('>:first-child').removeClass('hidden');
            popup.removeClass('hidden');
          });
      })
      .fail(function(data) {
        console.log(data);
      });
  }
};


/**G
 * Generate a form with given values, inject action (URL) and target
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
const iframeFormSubmit = function(action, target, values)
{
  const form = $('<form method="post" action="'+action+'" target="'+target+'"></form>');
  if (values.constructor === Array) {
    // serializeArray() stuff
    for(var idx = 0; idx < values.length; ++idx) {
      form.append('<input type="hidden" name="'+values[idx].name+'" value="'+values[idx].value+'"/>');
    }
  } else {
    // object with { name: value }
    form.append(objectToHiddenInput(values));
  }
  $('body').append(form);
  form.submit().remove();
};

/**Handle the export menu actions.*/
const tableExportMenu = function(select) {
  // determine the export format
  const selected = select.find('option:selected').val();
  //$("select.pme-export-choice option:selected").val();

  // this is the form; we need its values
  const form = $('form.pme-form');

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
    Dialogs.alert(t(appName, 'Export to the following format is not yet supported:')
                          +' "'+selected+'"',
                          t(appName, 'Unimplemented'));
  } else {

    // this will be the alternate form-action
    exportscript = OC.filePath(appName, 'ajax/export', exportscript);

    // Our export-scripts have the task to convert the display
    // PME-table into another format, so submitting the current
    // pme-form to another backend-script just makes sure sure that we
    // really get all selected parameters and can regenerate the
    // current view. Of course, this is then not really jQuery, and
    // the ajax/export/-scripts are not ajax scripts. But so what.
    const old_action= form.attr('action');
    form.attr('action', exportscript);
    form.submit();
    form.attr('action', old_action);
  }

  // Cheating. In principle we mis-use this as a simple pull-down
  // menu, so let the text remain at its default value. Make sure to
  // also remove and re-attach the tool-tips, otherwise some of the
  // tips remain, because chosen() removes the element underneath.
  selectMenuReset(select);
  $.fn.cafevTooltip.remove();

  $('div.chosen-container').cafevTooltip({placement:'auto'});

  return false;

};

const exportMenu = function(containerSel) {
  if (typeof containerSel === 'undefined') {
    containerSel = '#cafevdb-page-body';
  }
  const container = $(containerSel);

  // Emulate a pull-down menu with export options via the chosen
  // plugin.
  const exportSelect = container.find('select.pme-export-choice');
  exportSelect.chosen({
    disable_search:true,
    inherit_select_classes:true
  });

  // install placeholder as first item if chosen is not active
  fixupNoChosenMenu(exportSelect);

  container.find('select.pme-export-choice').
    off('change').
    on('change', function (event) {
      event.preventDefault();

      return tableExportMenu($(this));
    });

};

/**
 * Open one invisible modal dialog in order to have a persistent
 * overlay for a group of dialogs.
 *
 * @param bool open
 */
const modalizer = function(open) {
  const modalizer = $('#cafevdb-modalizer');
  if (open) {
    if (modalizer.length > 0) {
      $('body').addClass('cafevdb-modalizer');
      return modalizer;
    }
    const dialogHolder = $('<div id="cafevdb-modalizer" class="cafevdb-modalizer"></div>');
    $('body').append(dialogHolder);
    dialogHolder.cafevDialog({
      title: '',
      position: {
        my: 'top left',
        at: 'top-100% left-100%',
        of: window,
      },
      width: '0px',
      height: '0px',
      modal: true,
      closeOnEscape: false,
      dialogClass: 'transparent no-close zero-size cafevdb-modalizer',
      resizable: false,
      open() {
        // This one must be ours.
        globalState.dialogOverlay = $('.ui-widget-overlay:last');
        $('body').addClass('cafevdb-modalizer');
      },
      close() {
        globalState.dialogOverlay = false;
        dialogHolder.dialog('close');
        dialogHolder.dialog('destroy').remove();
        $('body').removeClass('cafevdb-modalizer');
      },
    });
    return dialogHolder;
  } else {
    if (modalizer.length <= 0) {
      $('body').removeClass('cafevdb-modalizer');
      return true;
    }
    const overlayIndex = parseInt(modalizer.dialog('widget').css('z-index'));
    console.info('overlay index: ', overlayIndex);
    let numDialogs = 0;
    $('.ui-dialog.ui-widget').each(function(index) {
      const thisIndex = parseInt($(this).css('z-index'));
      console.info('that index: ', thisIndex);
      if (thisIndex >= overlayIndex) {
        ++numDialogs;
      }
    });

    console.info('num dialogs open: ', numDialogs);
    if (numDialogs > 1) {
      // one is the modalizer itself, of course.
      return modalizer;
    }

    modalizer.dialog('close');
    $('body').removeClass('cafevdb-modalizer');

    return true;
  }
};

/**
 * Add a to-back-button to the titlebar of a jQuery-UI dialog. The
 * purpose is to be able to move the top-dialog to be bottom-most,
 * juse above a potential "modal" window layer.
 */
const dialogToBackButton = function(dialogHolder) {
  const dialogWidget = dialogHolder.dialog('widget');
  const toBackButtonTitle = t(appName,
                              'If multiple dialogs are open, '+
                              'then move this one to the lowest layer '+
                              'and display it below the others. '+
                              'Clicking anywhere on the dialog will bring to the front again.');
  const toBackButton = $('<button class="toBackButton customDialogHeaderButton" title="'+toBackButtonTitle+'"></button>');
  toBackButton.button({label: '_',
                       icons: { primary: 'ui-icon-minusthick', secondary: null },
                       text: false});
  dialogWidget.find('.ui-dialog-titlebar').append(toBackButton);
  toBackButton.cafevTooltip({placement:'auto' });

  toBackButton.off('click');
  toBackButton.on('click', function() {
    const overlay = $('.ui-widget-overlay:last');
    var overlayIndex = 100; // OwnCloud header resides at 50.
    if (overlay.length > 0) {
      overlayIndex = parseInt(overlay.css('z-index'));
    }
    // will be only few, so what
    var needShuffle = false;
    $('.ui-dialog.ui-widget').not('.cafevdb-modalizer').each(function(index) {
      const thisIndex = parseInt($(this).css('z-index'));
      if (thisIndex == overlayIndex + 1) {
        needShuffle = true;
      }
    }).each(function(index) {
      if (needShuffle) {
        const thisIndex = parseInt($(this).css('z-index'));
        $(this).css('z-index', thisIndex + 1);
      }
    });
    dialogWidget.css('z-index', overlayIndex + 1);
    return false;
  });
};

/**
 * jQuery UI just is not flexible enough. We want to be able to
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
const dialogCustomCloseButton = function(dialogHolder, callback) {
  const dialogWidget = dialogHolder.dialog('widget');
  const customCloseButtonTitle = t(appName,
                                   'Close the current dialog and return to the view '+
                                   'which was active before this dialog had been opened. '+
                                   'If the current view shows a `Back\' button, then intentionally '+
                                   'clicking the close-button (THIS button) should just be '+
                                   'equivalent to clicking the `Back\' button');
  const customCloseButton = $('<button class="customCloseButton customDialogHeaderButton" title="'+customCloseButtonTitle+'"></button>');
  customCloseButton.button({label: 'x',
                            icons: { primary: 'ui-icon-closethick', secondary: null },
                            text: false});
  dialogWidget.find('.ui-dialog-titlebar').append(customCloseButton);
  customCloseButton.cafevTooltip({placement:'auto' });

  customCloseButton.off('click');
  customCloseButton.on('click', function(event) {
    if (typeof callback == 'function') {
      callback(event, dialogHolder);
    } else {
      dialogHolder.dialog('close');
    }
    return false;
  });
};

const attachToolTip = function(selector, options) {
  const defaultOptions = {
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
const applyToolTips = function(selector, options, container) {
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
  const classAttr = element.attr('class');
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
      for(var idx = 0; idx < tooltipClasses.length; ++idx) {
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

const toolTipsOnOff = function(onOff) {
  globalState.toolTipsEnabled = !!onOff;
  if (globalState.toolTipsEnabled) {
    $.fn.cafevTooltip.enable();
  } else {
    $.fn.cafevTooltip.disable();
    $.fn.cafevTooltip.remove(); // remove any left-over items.
  }
};

const snapperClose = function() {
  // snapper will close on clicking navigation entries
  $('#navigation-list li.nav-heading a').trigger('click');
};

/**
 * Initialize our tipsy stuff. Only exchange for our own thingies, of course.
 */
const toolTipsInit = function(containerSel) {
  if (typeof containerSel === 'undefined') {
    containerSel = '#content.app-cafevdb';
  }
  const container = $(containerSel);

  console.debug("tooltips container", containerSel, container.length);

  // container.find('button.settings').cafevTooltip({placement:'bottom'});
  container.find('select').cafevTooltip({placement:'right'});
  container.find('option').cafevTooltip({placement:'right'});
  container.find('div.chosen-container').cafevTooltip({placement:'top'});
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
  if (globalState.toolTipsEnabled) {
    $.fn.cafevTooltip.enable();
  } else {
    $.fn.cafevTooltip.disable();
  }
};

/**
 * Get or set the option value(s) of a select box.
 *
 * @param select The select element. If it is an ordinary input
 * element, then in "set" mode its value is set to optionValues.
 *
 * @param optionValues Single option value or array of option
 * values to set.
 */
const selectValues = function(select, optionValues) {
  select = $(select);
  const multiple = select.prop('multiple');
  if (typeof optionValues === 'undefined') {
    console.debug('selectValues read = ', select.val());
    const result = select.val();
    if (multiple && !result) {
      result = [];
    }
    return result;
  }
  if (!(optionValues instanceof Array)) {
    optionValues = [ optionValues ];
  }
  if (!multiple && optionValues.length > 1) {
    optionValues = [ optionValues[0] ];
  }
  // setter has to use foreach
  select.each(function(idx) {
    const self = $(this);
    if (!self.is('select')) {
      // graceful degrade for non selects
      self.val(optionValues[0] ? optionValues[0] : '');
      self.trigger('change');
      return true;
    }
    self.find('option').each(function(idx) {
      const option = $(this);
      option.prop('selected', optionValues.indexOf(option.val()) >= 0);
    });
    console.debug('selectValues', 'update chosen');
    self.trigger('chosen:updated'); // in case ...
    return true;
  });
  return true;
};

globalState.progressTimer = null;

const pollProgressStatus = function(id, callbacks, interval) {
  const defaultCallbacks = {
    'update': function(data) {},
    'fail': function(data) {}
  };
  callbacks = { ...defaultCallbacks, ...callbacks };
  interval = interval || 800;

  const poll = function() {
    $.get(generateUrl('foregroundjob/progress/'+id))
      .done(function(data) {
        if (!callbacks.update(data)) {
          console.debug("Finish polling");
          clearTimeout(globalState.progressTimer);
          globalState.progressTimer = false;
          return;
        }
        console.debug("Restart timer.");
        globalState.progressTimer = setTimeout(poll, interval);
      })
      .fail(function(xhr, status, errorThrown) {
        clearTimeout(globalState.progressTimer);
        globalState.progressTimer = false;
        callbacks.fail(xhr, status, errorThrown);
      });
  };
  poll();
};

pollProgressStatus.stop = function() {
  clearTimeout(globalState.progressTimer);
  globalState.progressTimer = false;
};

pollProgressStatus.active = function() {
  return !!globalState.progressTimer;
};

const documentReady = function() {
  // @@TODO perhaps collects these things in before-ready.js
  document.onkeypress = stopRKey;

  $('body').on('dblclick', '.oc-dialog', function() {
    $('.oc-dialog').toggleClass('maximize-width');
  });

  window.oldWidth = -1;
  window.oldHeight = -1;
  $(window).on('resize', function(event) {
    const win = this;
    if (!win.resizeTimeout) {
      const delay = 50;
      const width = (win.innerWidth > 0) ? win.innerWidth : screen.width;
      const height = (win.innerHeight > 0) ? win.innerHeight : screen.height;
      if (win.oldWidth != width || win.oldHeight != height) {
        console.debug('cafevdb size change', width, win.oldWidth, height, win.oldHeight);
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
  const content = $('#content');
  const appInnerContent = $('#app-inner-content');

  // Display the overview-page for the given project.
  content.on('click', 'ul#navigation-list li.nav-projectlabel-control a',
             function(event) {
               event.stopImmediatePropagation();
               const data = $(this).data('json');
               Projects.projectViewPopup(pmeSelector(), data);
               return false;
             });

  // Display the instrumentation numbers in a dialog widget
  content.on('click', 'ul#navigation-list li.nav-project-instrumentation-numbers-control a',
             function(event) {
               event.stopImmediatePropagation(); // this is vital
               const data = $(this).data('json');
               Projects.instrumentationNumbersPopup(pmeSelector(), data);
               return false;
             });

  addReadyCallback(function() {
    $('input.alertdata.cafevdb-page').each(function(index) {
      const title = $(this).attr('name');
      const text  = $(this).attr('value');
      Dialogs.alert(text, title, undefined, true, true);
    });

  });

  // fire an event when this have been finished
  console.debug("trigger loaded");
  $(document).trigger("cafevdb:donecafevdbjs");
};

export {
  globalState,
  generateUrl,
  addReadyCallback,
  runReadyCallbacks,
  addEditor,
  removeEditor,
  updateEditor,
  snapshotEditor,
  unfocus,
  makeId,
  modalWaitNotification,
  textareaResize,
  stopRKey,
  urlEncode,
  urlDecode,
  queryData,
  selectMenuReset,
  chosenActive,
  fixupNoChosenMenu,
  chosenPopup,
  formSubmit,
  objectToHiddenInput,
  appSettings,
  iframeFormSubmit,
  tableExportMenu,
  exportMenu,
  modalizer,
  dialogToBackButton,
  dialogCustomCloseButton,
  attachToolTip,
  applyToolTips,
  toolTipsOnOff,
  snapperClose,
  toolTipsInit,
  selectValues,
  pollProgressStatus,
  documentReady,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
