/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**@file
 *
 * General PME table stuff, popup-handling.
 */

var PHPMYEDIT = PHPMYEDIT || {};

(function(window, $, PHPMYEDIT, undefined) {
  'use strict';

  PHPMYEDIT.directChange            = false;
  PHPMYEDIT.filterSelectPlaceholder = 'Select a filter Option';
  PHPMYEDIT.filterSelectNoResult    = 'No values match';
  PHPMYEDIT.selectChosen            =  true;
  PHPMYEDIT.filterSelectChosenTitle = 'Select from the pull-down menu. Double-click will submit the form.';
  PHPMYEDIT.inputSelectPlaceholder  = 'Select an option';
  PHPMYEDIT.inputSelectNoResult     = 'No values match';
  PHPMYEDIT.inputSelectChosenTitle  = 'Select from the pull-down menu.';
  PHPMYEDIT.chosenPixelWidth        = [];
  PHPMYEDIT.pmePrefix               = 'pme';
  PHPMYEDIT.PMEPrefix               = PHPMYEDIT.pmePrefix.toUpperCase();

  PHPMYEDIT.defaultSelector         = '#cafevdb-page-body';
  PHPMYEDIT.dialogCSSId             = PHPMYEDIT.pmePrefix+'-table-dialog';
  PHPMYEDIT.tableLoadCallbacks      = [];

  // PHPMYEDIT.popupPosition           = { my: "middle top",
  //                                       at: "middle bottom+50px",
  //                                       of: "#header" };

  PHPMYEDIT.popupPosition           = { my: "left top",
                                        at: "left+5% top+5%",
                                        of: window };
  PHPMYEDIT.dialogOpen              = {};

  /**Generate a string with PME_sys_.... prefix.*/
  PHPMYEDIT.pmeSys = function(token) {
    return this.PMEPrefix+"_sys_"+token;
  };

  /**Generate a string with PME_data_.... prefix.*/
  PHPMYEDIT.pmeData = function(token) {
    return this.PMEPrefix+"_data_"+token;
  };

  /**Generate a string with pme-.... prefix.*/
  PHPMYEDIT.pmeToken = function(token) {
    return this.pmePrefix+"-"+token;
  };

  /**Generate an id selector with pme-.... prefix.*/
  PHPMYEDIT.pmeIdSelector = function(token) {
    return '#'+this.pmeToken(token);
  };

  /**Generate a class selector with pme-.... prefix.*/
  PHPMYEDIT.pmeClassSelector = function(element, token) {
    return element+'.'+this.pmeToken(token);
  };

  /**Generate a compound class selector with pme-.... prefix.*/
  PHPMYEDIT.pmeClassSelectors = function(element, tokens) {
    var pme = this;
    var elements = tokens.map(function(token) {
                     return pme.pmeClassSelector(element, token);
                   });
    return elements.join(',');
  };

  /**Generate a name selector with PME_sys_.... prefix.*/
  PHPMYEDIT.pmeSysNameSelector = function(element, token) {
    var pme = this;
    return element+'[name="'+pme.pmeSys(token)+'"]';
  };

  /**Generate a compound name selector with PME_sys_.... prefix.*/
  PHPMYEDIT.pmeSysNameSelectors = function(element, tokens) {
    var pme = this;
    var elements = tokens.map(function(token) {
                     return pme.pmeSysNameSelector(element, token);
                   });
    return elements.join(',');
  };

  /**Generate a navigation selector with pme-.... prefix.*/
  PHPMYEDIT.navigationSelector = function(token) {
    var pme = this;
    return '.'+pme.pmeToken('navigation')+' '+pme.pmeClassSelector('input', token);
  };

  /**Selector for main form*/
  PHPMYEDIT.formSelector = function() {
    return 'form.'+this.pmeToken('form');
  };

  /**Selector for main table*/
  PHPMYEDIT.tableSelector = function() {
    return 'table.'+this.pmeToken('main');
  };

  /**Genereate the default selector. */
  PHPMYEDIT.selector = function(selector) {
    if (typeof selector === 'undefined') {
      selector = this.defaultSelector;
    }
    return selector;
  };

  /**Generate the jQuery object corresponding to the ambient
   * element. If the given argument is already a jQuery object, then
   * just return the argument.
   */
  PHPMYEDIT.container = function(selector) {
    var container;
    if (selector instanceof jQuery) {
      container = selector;
    } else {
      selector = this.selector(selector);
      container = $(selector);
    }
    return container;
  };

  PHPMYEDIT.addTableLoadCallback = function(dpyClass, cbObject) {
    if (typeof cbObject.context === 'undefined') {
      cbObject.context = this;
    }
    if (typeof cbObject.parameters === 'undefined') {
      cbObject.parameters = [];
    }
    if (typeof cbObject.parameters !== 'object') {
      cbObject.parameters = [ cbObject.parameters ];
    }
    this.tableLoadCallbacks[dpyClass] = cbObject;
  };

  PHPMYEDIT.tableLoadCallback = function(dpyClass, selector, parameters, resizeReadyCB) {
    var cbHandle;

    if (typeof this.tableLoadCallbacks[dpyClass] !== 'undefined') {
      cbHandle = this.tableLoadCallbacks[dpyClass];
    } else {
//      alert("No Callback for "+dpyClass);
      return false;
    }

    if (typeof selector == 'undefined') {
      selector = PHPMYEDIT.defaultSelector;
    }
    if (typeof resizeReadyCB != 'function') {
      resizeReadyCB = function() {};
    }

    var callback = cbHandle.callback;
    var context  = cbHandle.context;
    if (typeof parameters == 'undefined') {
      parameters = {};
    }
    parameters = $.extend({ reason: null }, parameters);

    var args = [ selector, parameters, resizeReadyCB ];
    $.merge(args, cbHandle.parameters);

    return callback.apply(context, args);
  };

  /**Submit the base form in order to synchronize any changes caused
   * by the dialog form.
   */
  PHPMYEDIT.submitOuterForm = function(selector) {
    var pme = this;
    var outerSel = this.selector(selector);

    // try a reload while saving data. This is in order to resolve
    // inter-table dependencies like changed instrument lists and so
    // on. Be careful not to trigger top and bottom buttons.
    var outerForm = $(outerSel + ' ' + pme.formSelector());

    var button = $(outerForm).find('input[name$="morechange"],'+
                                   'input[name$="applyadd"],'+
                                   'input[name$="applycopy"],'+
                                   'input[name$="reloadchange"],'+
                                   'input[name$="reloadview"]').first();
    if (button.length > 0) {
      button.trigger('click');
    } else {
      // submit the outer form
      //outerForm.submit();
      this.pseudoSubmit(outerForm, $(), outerSel, 'pme');
    }
  }

  PHPMYEDIT.cancelDeferredReload = function(container) {
    var deferKey = this.pme_prefix + '-submitdefer';
    container.removeData(deferKey);
  };

  PHPMYEDIT.deferReload = function(container) {
    var deferKey = this.pme_prefix + '-submitdefer';
    var defer = $.Deferred();

    container.data(deferKey, defer.promise());

    return defer;
  };

  PHPMYEDIT.reloadDeferred = function(container)
  {
    var deferKey = this.pme_prefix + '-submitdefer';

    return $.when(container.data(deferKey));
  };

  /**Reload the current PME-dialog.
   *
   * @param container jQuery object describing the dialog-holder,
   * i.e. the div which contains the html.
   *
   * @param options The current dialog options. In particular
   * options.ReloadName and options.ReloadValue must hold name and
   * value of the curent (pseudo-) submit input
   * element. options.modified must already be up-to-date.
   *
   * @param callback The application provided callback which is used
   * to shape the HTML after loading.
   */
  PHPMYEDIT.tableDialogReload = function(options, callback) {
    var pme  = this;

    var reloadName  = options.ReloadName;
    var reloadValue = options.ReloadValue;

    var containerSel = '#'+options.DialogHolderCSSId;
    var container = $(containerSel);
    var contentsChanged = false;

    container.dialog('widget').addClass(pme.pmeToken('table-dialog-blocked'));
    container.find(pme.navigationSelector('reload')).addClass('loading');

    // Possibly delay reload until validation handlers have done their
    // work.
    pme.reloadDeferred(container).then(function() {
      var post = container.find(pme.formSelector()).serialize();

      // add the option values
      post += '&' + $.param(options);

      // add name and value of the "submit" button.
      var obj = {};
      obj[reloadName] = reloadValue;
      post += '&' + $.param(obj);

      //alert("Post: "+post);
      $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
             post,
             function (data) {

               if (!CAFEVDB.ajaxErrorHandler(data, [ 'contents' ])) {
                 var dialogWidget = container.dialog('widget');

                 CAFEVDB.Page.busyIcon(false);
                 dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
                 container.find(pme.navigationSelector('reload')).removeClass('loading');
                 return false;
               }

               if (data.status == 'success') {
                 // remove the WYSIWYG editor, if any is attached
                 CAFEVDB.removeEditor(container.find('textarea.wysiwygeditor'));

                 container.css('height', 'auto');
                 $.fn.cafevTooltip.remove();
                 container.html(data.data.contents);
                 container.find(pme.navigationSelector('reload')).addClass('loading');

                 // general styling
                 pme.init(containerSel);

                 // attach the WYSIWYG editor, if any
                 // editors may cause additional resizing
                 CAFEVDB.addEditor(container.find('textarea.wysiwygeditor'), function() {
                   container.dialog('option', 'height', 'auto');
                   //container.dialog('option', 'position', pme.popupPosition);

                   // re-attach events
                   pme.tableDialogHandlers(options, callback);
                 });
               }
               return false;
             });
    });
    return false;
  };

  /**Overload the phpMyEdit submit buttons in order to be able to
   * display the single data-set display, edit, add and copy form in a
   * popup.
   *
   * @param[in] options Object with additional params to the
   * pme-table.php AJAX callback. Must at least contain the
   * DisplayClass component.
   *
   * @param[in] callback Additional form validation callback. If
   * callback also attaches handlers to the save, change etc. buttons
   * then these should be attached as delegate event handlers to the
   * pme-form. The event handlers installed by this functions are
   * installed as delegate handlers at the \#pme-table-dialog div.
   *
   * @bug This function is by far too long.
   *
   * @param options Some stuff like the PHP content provider class, see
   * tableDialog().
   *
   * @param callback Function to be called in order to style the
   * dynamically loaded content.
   */
  PHPMYEDIT.tableDialogHandlers = function(options, callback) {
    var pme = this;

    if (typeof callback === 'undefined') {
      callback = function() { return false; };
    }

    var containerSel = '#'+options.DialogHolderCSSId;
    var container = $(containerSel);
    var contentsChanged = false;

    container.off('click', '**');

    pme.cancelDeferredReload(container);

    PHPMYEDIT.installTabHandler(container, function() {
      callback( { reason: 'tabChange' } );
    });

    // The easy one, but for changed contents
    var cancelButton = $(container).find(pme.pmeClassSelector('input', 'cancel'));
    cancelButton.off('click');
    cancelButton.on('click', function(event) {
      event.preventDefault();

      // When the initial dialog was in view-mode and we are not in
      // view-mode, then we return to view mode; for me "cancel" feels
      // more natural when the GUI returns to the previous dialog
      // instead of returning to the main table. We only have to look
      // at the name of "this": if it ends with "cancelview" then we
      // are cancelling a view and close the dialog, otherwise we
      // return to view mode.
      if (options.InitialViewOperation && $(this).attr('name').indexOf('cancelview') < 0) {
        options.ReloadName = options.InitialName;
        options.ReloadValue = options.InitialValue;
        pme.tableDialogReload(options, callback);
      } else {
        container.dialog('close');
      }

      return false;
    });

    // The complicated ones. This reloads new data.
    var ReloadButtonSel = pme.pmeClassSelectors(
      'input',
      [ 'change', 'delete', 'copy', 'apply', 'more', 'reload' ]);
    var reloadingButton = $(container).find(ReloadButtonSel);

    // remove non-delegate handlers and stop default actions in any case.
    reloadingButton.off('click');

    // install a delegate handler on the outer-most container which
    // finally will run after possible inner data-validation handlers
    // have been executed.
    container.off('click', ReloadButtonSel);
    container.on(
      'click',
      ReloadButtonSel,
      function(event) {
        event.preventDefault();

        var submitButton = $(this);
        var reloadName  = submitButton.attr('name');
        var reloadValue = submitButton.val();
        options.ReloadName = reloadName;
        options.ReloadValue = reloadValue;
        if (!submitButton.hasClass(pme.pmeToken('change')) &&
            !submitButton.hasClass(pme.pmeToken('delete')) &&
            !submitButton.hasClass(pme.pmeToken('copy')) &&
            !submitButton.hasClass(pme.pmeToken('reload'))) {
          options.modified = true;
        }
        pme.tableDialogReload(options, callback);

        return false;
      });

    /**************************************************************************
     *
     * In "edit" mode submit the "more" action and reload the
     * surrounding form. When not in edit mode the base form must be the same
     * as the overlay form and a simple form submit should suffice, in principle.
     * For "more add" we will have to adjust the logic.
     *
     * It is possible to reach the edit-form from "view-mode". In this
     * case we want that the save-button returns us to the view-mode
     * dialog. We achieve this by first simulating a "apply" event,
     * discarding the generated html-output and then re-submitting to
     * view-mode.
     *
     */
    var saveButtonSel = 'input.'+pme.pmeToken('save');
    var saveButton = $(container).find(saveButtonSel);
    saveButton.off('click');

    container
    .off('click', saveButtonSel)
    .on(
      'click',
      saveButtonSel,
      function(event) {

        event.preventDefault();

        container.find(pme.navigationSelector('reload')).addClass('loading');

        options.modified = true; // we are the save-button ...

        var applySelector = pme.pmeSysNameSelectors(
          'input',
          ['morechange', 'applyadd', 'applycopy']);
        var deleteSelector = pme.pmeSysNameSelector('input', 'savedelete');

        pme.reloadDeferred(container).then(function() {
          var post = $(container).find(pme.formSelector()).serialize();
          post += '&' + $.param(options);
          var name, value;

          var deleteButton = container.find(deleteSelector);
          if (deleteButton.length > 0) {
            name  = deleteButton.attr('name');
            value = deleteButton.val();
          } else {
            var applyButton = container.find(applySelector);
            if (applyButton.length > 0) {
              name  = applyButton.attr('name');
              value = applyButton.val();
            }
          }
          var obj = {};
          obj[name] = value;
          post += '&' + $.param(obj);

          //alert(post);

          OC.Notification.hide(function() {
            var dialogWidget = container.dialog('widget');

            dialogWidget.addClass(pme.pmeToken('table-dialog-blocked'));

            $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
                   post,
                   function (data) {
                     if (!CAFEVDB.ajaxErrorHandler(data, [ 'contents' ])) {
                       dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
                       container.find(pme.navigationSelector('reload')).removeClass('loading');
                       CAFEVDB.Page.busyIcon(false);
                       return false;
                     }

                     // phpMyEdit echos mySQL-errors back.
                     if (typeof data.data.sqlerror != 'undefined' &&
                         data.data.sqlerror.error != 0) {
                       $('#notification').text('MySQL Error: '+
                                               data.data.sqlerror.error+
                                               ': '+
                                               data.data.sqlerror.message);
	               $('#notification').fadeIn();
	               //hide notification after 5 sec
	               setTimeout(function() {
	                 $('#notification').fadeOut();
	               }, 10000);
                     }

                     if (options.InitialViewOperation && deleteButton.length <= 0) {
                       // return to initial view, but not after deletion
                       dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
                       options.ReloadName = options.InitialName;
                       options.ReloadValue = options.InitialValue;
                       pme.tableDialogReload(options, callback);
                     } else {
                       container.dialog('close');
                     }
                     container.find(pme.navigationSelector('reload')).removeClass('loading');
                     CAFEVDB.Page.busyIcon(false);

                     return false;
                   });
          });
        });
      return false;
    });

    // Finally do the styling ...
    callback( { reason: 'dialogOpen' } );
  };

  /**Post the contents of a pme-form via AJAX into a dialog
   * widget. Useful for editing, viewing etc. because this avoids the
   * need to reload the base table (when only viewing single
   * data-sets).
   *
   * @param form The form to take the informatoin from, including the
   * name of the PHP class which generates the response.
   *
   * @param element The input element which initiates the "form
   * submit". In particular, we assume PME "view operation" if element
   * carries a CSS class "pme-viewXXXXX" with XXXXX being anything.
   */
  PHPMYEDIT.tableDialog = function(form, element, containerSel) {
    var pme  = this;

    var post = form.serialize();
    var dpyClass = form.find('input[name="DisplayClass"]');

    if (dpyClass.length == 0) {
      // This just does not work.
      return false;
    }
    dpyClass = dpyClass.val();

    var viewOperation = false;
    var initialName;
    var initialValue;
    if (element.attr) {
      initialName = element.attr('name');
      if (initialName) {
        initialValue = element.val();
        var obj = {};
        obj[initialName] = initialValue;
        post += '&' + $.param(obj);
      }

      var cssClass = element.attr('class');
      if (cssClass) {
        viewOperation = cssClass.indexOf(pme.pmeToken('view')) > -1;
      }
    }

    //alert('post: '+CAFEVDB.print_r(post, true));

    var tableOptions = {
      AmbientContainerSelector: pme.selector(containerSel),
      DialogHolderCSSId: pme.dialogCSSId,
      DisplayClass: dpyClass,
      InitialViewOperation: viewOperation,
      InitialName: initialName,
      InitialValue: initialValue,
      ReloadName: initialName,
      ReloadValue: initialValue,
      ModalDialog: true,
      modified: false // avoid reload of base table unless necessary
    };
    pme.tableDialogOpen(tableOptions, post);
    return true;
  };

  /**Open directly the popup holding the form data. We listen for the
   * custom event 'pmedialog:changed' on the DialogHolder. This event will
   * be forwarded to the AmbientContainer. The idea is that we can
   * update the "modified" component of chained dialogs in a reliable
   * way.
   *
   * @param tableOptions Option array, see above
   *
   * @param post Additional query parameters. In principle it is also
   * possible to store all values in tableOptions, as this is added to
   * the query-string in any case.
   *
   *
   */
  PHPMYEDIT.tableDialogOpen = function(tableOptions, post) {
    var pme = this;

    var containerCSSId = tableOptions.DialogHolderCSSId;

    if (pme.dialogOpen[containerCSSId]) {
      return false;
    }

    pme.dialogOpen[containerCSSId] = true;

    CAFEVDB.Page.busyIcon(true);

    if (typeof tableOptions.ModalDialog == 'undefined') {
      tableOptions.ModalDialog = true;
    }
    if (typeof post == 'undefined') {
      post = $.param(tableOptions);
    } else {
      post += '&' + $.param(tableOptions);
    }
    $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
           post,
           function (data) {
             if (!CAFEVDB.ajaxErrorHandler(data, [ 'contents' ])) {
               CAFEVDB.Page.busyIcon(false);
               return false;
             }

             var containerSel = '#'+containerCSSId;
             var dialogHolder;
             dialogHolder = $('<div id="'+containerCSSId+'" class="resize-target"></div>');
             dialogHolder.html(data.data.contents);
             dialogHolder.data('AmbientContainer', tableOptions.AmbientContainerSelector);

             dialogHolder.on('pmedialog:changed', function(event) {
               //alert('Changed: '+containerCSSId);
               tableOptions.modified = true;
               return true; // let it bubble upwards ...
             });

             dialogHolder.find(pme.navigationSelector('reload')).addClass('loading');
             if (tableOptions.ModalDialog) {
               CAFEVDB.modalizer(true);
             }

             var popup = dialogHolder.cafevDialog({
               title: dialogHolder.find(pme.pmeClassSelector('span', 'short-title')).html(),
               position: pme.popupPosition,
               width: 'auto',
               height: 'auto',
               modal: false, //tableOptions.ModalDialog,
               closeOnEscape: false,
               dialogClass: pme.pmeToken('table-dialog')+' custom-close resize-target',
               resizable: false,
               open: function() {

                 //var tmp = CAFEVDB.modalWaitNotification("BlahBlah");
                 //tmp.dialog('close');
                 var dialogHolder = $(this);
                 var dialogWidget = dialogHolder.dialog('widget');

                 //dialogWidget.draggable('option', 'containment', '#content');

                 CAFEVDB.dialogToBackButton(dialogHolder);
                 CAFEVDB.dialogCustomCloseButton(dialogHolder, function(event, container) {
                   event.preventDefault();
                   var cancelButton = container.find(pme.pmeClassSelector('input', 'cancel')).first();
                   if (cancelButton.length > 0) {
                     event.stopImmediatePropagation();
                     cancelButton.trigger('click');
                   }
                   return false;
                 });

                 dialogWidget.addClass(pme.pmeToken('table-dialog-blocked'));

                 // general styling
                 pme.init(containerSel);

                 var resizeHandler = function(parameters) {
                   dialogHolder.dialog('option', 'height', 'auto');
                   dialogHolder.dialog('option', 'width', 'auto');
                   var newHeight = dialogWidget.height()
                                 - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
                   newHeight -= dialogHolder.outerHeight(true) - dialogHolder.height();
                   //alert("Setting height to " + newHeight);
                   dialogHolder.height(newHeight);
                 };

                 pme.tableDialogHandlers(tableOptions, function(parameters) {
                   dialogHolder.css('height', 'auto');

                   CAFEVDB.addEditor(dialogHolder.find('textarea.wysiwygeditor'), function() {
                     pme.transposeReady(containerSel);
                     pme.tableLoadCallback(tableOptions.DisplayClass, containerSel, parameters, function() {
                       resizeHandler(parameters);
                       dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
                       dialogHolder.dialog('moveToTop');
                       CAFEVDB.toolTipsInit(containerSel);
                       CAFEVDB.Page.busyIcon(false);
                       dialogHolder.find(pme.navigationSelector('reload')).removeClass('loading');
                     });
                     CAFEVDB.pmeTweaks(dialogHolder);
                   });
                 });

                 dialogHolder.on('resize', resizeHandler);
               },
               close: function(event) {
                 $.fn.cafevTooltip.remove();
                 var dialogHolder = $(this);

                 dialogHolder.find('iframe').removeAttr('src');

                 //alert($.param(tableOptions));
                 if (tableOptions.modified === true) {
                   //alert("Changed, triggerring on "+tableOptions.AmbientContainerSelector);
                   $(tableOptions.AmbientContainerSelector).trigger('pmedialog:changed');
                   pme.submitOuterForm(tableOptions.AmbientContainerSelector);
                 }

                 dialogHolder.dialog('destroy');

                 // At least konq. has the bug that removing a form
                 // with submit inputs will submit the form. Very strange.
                 dialogHolder.find('form input[type="submit"]').remove();
                 dialogHolder.remove();

                 // Remove modal plane if appropriate
                 CAFEVDB.modalizer(false);

                 pme.dialogOpen[containerCSSId] = false;

                 return false;
               }
             });
             return false;
           });
    return true;
  };

  /**Quasi-submit the pme-form.
   *
   * @param[in] form The jQuery object corresponding to the pme-form.
   *
   * @param[in] element The jQuery object corresponding to the element
   * causing the submit.
   *
   * @param[in] selector The CSS selector corresponding to the
   * surrounding container (div element)
   */
  PHPMYEDIT.pseudoSubmit = function(form, element, selector) {
    var pme = this;

    selector = this.selector(selector);
    var container = this.container(selector);

    var dpyClass = form.find('input[name="DisplayClass"]');
    if (dpyClass.length <= 0 || element.hasClass('formsubmit')) {
      form.off('submit');
      if (element.attr('name')) { // undefined == false
        form.append('<input type="hidden" '+
                    'name="'+element.attr('name')+'" '+
                    'value="'+element.val()+'"/>');
      }
      return form.submit();
    }

    CAFEVDB.Page.busyIcon(true);
    CAFEVDB.modalizer(true);

    dpyClass = dpyClass.val();
    // TODO: arguments
    var post = form.serialize();
    post += '&DisplayClass='+dpyClass;
    if (element.attr('name') &&
        (!element.is(':checkbox') || element.is(':checked'))) {
      var name  = element.attr('name');
      var value = element.val();
      var obj = {};
      obj[name] = value;
      post += '&' + $.param(obj);
    }
    $.post(OC.filePath('cafevdb', 'ajax/pme', 'pme-table.php'),
           post,
           function (data) {
             if (!CAFEVDB.ajaxErrorHandler(
               data, [ 'contents', 'history' ])) {
               // re-enable on error
               CAFEVDB.Page.busyIcon(false);
               CAFEVDB.modalizer(false);

               return false;
             }

             // phpMyEdit echos mySQL-errors back.
             if (typeof data.data.sqlerror != 'undefined' &&
                 data.data.sqlerror.error != 0) {
               $('#notification').text('MySQL Error: '+
                                       data.data.sqlerror.error+
                                       ': '+
                                       data.data.sqlerror.message);
	       $('#notification').fadeIn();
	       //hide notification after 5 sec
	       setTimeout(function() {
	         $('#notification').fadeOut();
	       }, 10000);
             }

             if (data.data.history.size > 0) {
               CAFEVDB.Page.historySize = data.data.history.size;
               CAFEVDB.Page.historyPosition = data.data.history.position;
               CAFEVDB.Page.updateHistoryControls();
             }
             $.fn.cafevTooltip.remove();

             CAFEVDB.removeEditor(container.find('textarea.wysiwygeditor'));
             container.html(data.data.contents);
             pme.init(selector);
             CAFEVDB.addEditor(container.find('textarea.wysiwygeditor'), function() {
               pme.transposeReady(selector);
               //alert('pseudo submit');
               pme.tableLoadCallback(dpyClass, selector, { reason: 'formSubmit' }, function() {});
               CAFEVDB.pmeTweaks(container);
               CAFEVDB.toolTipsInit(selector);

               /* kill the modalizer */
               CAFEVDB.Page.busyIcon(false);
               CAFEVDB.modalizer(false);
               CAFEVDB.unfocus(); // move focus away from submit button
             });
             return false;
           });
    return false;
  };

  /**Trigger either one of the upper or the lower button controls (but
   * not both!)
   */
  PHPMYEDIT.triggerSubmit = function(buttonName, containerSel) {
    var container = this.container(containerSel);
    var button = container.find('input[name="'+this.pmeSys(buttonName)+'"]').first();

    if (button.length > 0) {
      button.trigger('click');
      return true;
    } else {
      return false;
    }
  };

  /**Transpose the main tabel if desired. */
  PHPMYEDIT.transposeMainTable = function(selector, containerSel) {
    var pme = this;
    var container = this.container(containerSel);
    var table = container.find(selector);

    var headerRow = table.find('thead tr');
    headerRow.detach();
    if (headerRow.length > 0) {
      headerRow.prependTo( table.find('tbody') );
    }
    var t = table.find('tbody').eq(0);
    var sortinfo  = t.find(pme.pmeClassSelector('tr', 'sortinfo'));
    var queryinfo = t.find(pme.pmeClassSelector('tr', 'queryinfo'));
    // These are huge cells spanning the entire table, move them on
    // top of the transposed table afterwards.
    sortinfo.detach();
    queryinfo.detach();
    var r = t.find('tr');
    var cols= r.length;
    var rows= r.eq(0).find('td,th').length;
    var cell, next, tem, i = 0;
    var tb= $('<tbody></tbody>');

    while(i<rows){
      cell= 0;
      tem= $('<tr></tr>');
      while(cell<cols){
	next= r.eq(cell++).find('td,th').eq(0);
	tem.append(next);
      }
      tb.append(tem);
      ++i;
    }
    table.find('tbody').remove();
    $(tb).appendTo(table);
    if (table.find('thead').length > 0) {
      $(table)
	.find('tbody tr:eq(0)')
	.detach()
	.appendTo( table.find('thead') )
	.children()
	.each(function(){
          var tdclass = $(this).attr('class');
          if (tdclass.length > 0) {
            tdclass = ' class="'+tdclass+'"';
          } else {
            tdclass = "";
          }
          $(this).replaceWith('<th'+tdclass+' scope="col">'+$(this).html()+'</th>');
	});
    }
    queryinfo.prependTo(table.find('tbody'));
    sortinfo.prependTo(table.find('tbody'));

    if (true) {
      $(table)
        .find('tbody tr th:first-child')
        .each(function(){
          var thclass = $(this).attr('class');
          if (thclass.length > 0) {
            thclass = ' class="'+thclass+'"';
          } else {
            thclass = "";
          }
	  $(this).replaceWith('<td'+thclass+' scope="row">'+$(this).html()+'</td>');
        });
    }
    table.show();
  };

  /**Transpose the main table based on boolean value of transpose. */
  PHPMYEDIT.maybeTranspose = function(transpose, containerSel) {
    var pme = this;
    var container = this.container(containerSel);
    var pageitems;
    var tooltip = container.find('.tooltip');
    var doTooltip = tooltip.length > 0;

    var trUp      = pme.pmeIdSelector('transpose-up');
    var trDown    = pme.pmeIdSelector('transpose-down');
    var tr        = pme.pmeIdSelector('transpose');
    var trClass   = pme.pmeToken('transposed');
    var unTrClass = pme.pmeToken('untransposed');

    if (transpose) {
      tooltip.remove();
      this.transposeMainTable(pme.tableSelector(), container);
      pageitems = t('cafevdb', '#columns');

      container.find('input[name="Transpose"]').val('transposed');
      container.find(trUp).removeClass(unTrClass).addClass(trClass);
      container.find(trDown).removeClass(unTrClass).addClass(trClass);
      container.find(tr).removeClass(unTrClass).addClass(trClass);
    } else {
      tooltip.remove();
      this.transposeMainTable(pme.tableSelector(), container);
      pageitems = t('cafevdb', '#rows');

      container.find('input[name="Transpose"]').val('untransposed');
      container.find(trUp).removeClass(trClass).addClass(unTrClass);
      container.find(trDown).removeClass(trClass).addClass(unTrClass);
      container.find(tr).removeClass(trClass).addClass(unTrClass);
    }
    container.find(pme.pmeClassSelector('input', 'pagerows')).val(pageitems);
  };

  /**Ready callback.*/
  PHPMYEDIT.transposeReady = function(containerSel)  {
    var pme = this;

    var container = PHPMYEDIT.container(containerSel);

    var trUp      = pme.pmeIdSelector('transpose-up');
    var trDown    = pme.pmeIdSelector('transpose-down');
    var tr        = pme.pmeIdSelector('transpose');
    var trClass   = pme.pmeToken('transposed');
    var unTrClass = pme.pmeToken('untransposed');

    // Transpose or not: if there is a transpose button
    var inhibitTranspose = container.find('input[name="InhibitTranspose"]').val() == 'true';
    var controlTranspose = (container.find('input[name="Transpose"]').val() == 'transposed' ||
                            container.find(trUp).hasClass(trClass) ||
                            container.find(trDown).hasClass(trClass) ||
                            container.find(tr).hasClass(trClass));

    //alert('Inhibit: '+inhibitTranspose+' control: '+controlTranspose);

    if (!inhibitTranspose && controlTranspose) {
      this.maybeTranspose(true);
    } else {
      // Initially the tabel _is_ untransposed
      //CAFEVDB.PME.maybeTranspose(false); // needed?
    }
  };

  PHPMYEDIT.installFilterChosen = function(containerSel) {
    var pme = this;

    if (!pme.selectChosen) {
      return;
    }

    var pmeFilter = pme.pmeToken('filter');
    var pmeCompFilter = pme.pmeToken('comp-filter');

    var container = pme.container(containerSel);

    var noRes = pme.filterSelectNoResult;

    container.find("select."+pmeCompFilter).chosen({
      width:"auto",
      inherit_select_classes:true,
      disable_search_threshold: 10
    });

    // Provide a data-placeholder and also remove the match-all
    // filter, which is not needed when using chosen.
    container.find("select."+pmeFilter).attr("data-placeholder", pme.filterSelectPlaceholder);
    container.off('change', 'select.'+pmeFilter);
    container.find("select."+pmeFilter+" option[value='*']").remove();

    // Then the general stuff
    container.find("select."+pmeFilter).chosen({
      width:'100%', // This needs margin:0 and box-sizing:border-box to be useful.
      inherit_select_classes:true,
      no_results_text:noRes
    });

    var dblClickSel =
      'td.'+pmeFilter+' ul.chosen-choices li.search-field input[type="text"]'+','
                     + 'td.'+pmeFilter+' div.chosen-container'+','
                     + 'td.'+pmeFilter+' input[type="text"]';
    container.off('dblclick', dblClickSel);
    container.on('dblclick', dblClickSel, function(event) {
      event.preventDefault();
      // There doesn't seem to be a "this" for dblclick, though
      // searching the web did not reveal similar problems. Doesn't
      // matter, we just trigger the click on the query-submit button
      //return PHPMYEDIT.pseudoSubmit(container.find('form.pme-form'), $(event.target), containerSel);
      container.find('td.'+pmeFilter+' input.'+pme.pmeToken('query')).trigger('click');
    });

    container.find("td."+pmeFilter+" div.chosen-container").
      attr("title", pme.filterSelectChosenTitle);
  };

  PHPMYEDIT.installInputChosen = function(containerSel) {
    var pme = this;

    if (!pme.selectChosen) {
      return;
    }

    var pmeInput = pme.pmeToken('input');
    var pmeValue = pme.pmeToken('value');

    var container = pme.container(containerSel);

    var noRes = pme.inputSelectNoResult;

    // Provide a data-placeholder and also remove the match-all
    // filter, which is not needed when using chosen.
    container.find("select."+pmeInput).attr("data-placeholder", pme.inputSelectPlaceholder);
    container.off('change', 'select.'+pmeInput);
//    container.find("select."+pmeInput+" option[value='*']").remove();

    // Then the general stuff
    container.find("select."+pmeInput).each(function(index) {
      var self = $(this);
      if (self.hasClass('no-chosen')) {
        return;
      }
      self.chosen({
        //width:'100%',
        inherit_select_classes:true,
        disable_search_threshold: 10,
        no_results_text: noRes,
        allow_single_deselect: self.hasClass('allow-empty')
      });
    });

    // Set title explicitly
    container.find("td."+pmeInput+" div.chosen-container").attr("title", pme.inputSelectChosenTitle);
    container.find("td."+pmeValue+" div.chosen-container").attr("title", pme.inputSelectChosenTitle);

    // Copy over titles and tooltip classes
    container.find("td."+pmeValue).each(function(index) {
      var selectTitle = "";
      var selectBox = $(this).children("select."+pmeInput).first();
      var chosen = $(this).children("div.chosen-container").first();

      if (chosen.length == 0 || selectBox.length == 0) {
        return;
      }

      selectTitle = $(selectBox).attr("title");
      if (selectTitle === undefined || selectTitle.trim().length <= 0) {
        selectTitle = selectBox.attr("data-original-title");
      }
      if (selectTitle !== undefined && selectTitle.length > 0) {
        chosen.attr("title", selectTitle);
        chosen.addClass('tooltip-right tooltip-wide');
      }
    });

  };

  PHPMYEDIT.installTabHandler = function(containerSel, callback) {
    var pme = this;

    var container = pme.container(containerSel);

    if (typeof callback != 'function') {
      callback = function() {
        CAFEVDB.toolTipsInit(container);
      }
    }

    var tabsSelector = pme.pmeClassSelector('li', 'navigation')+'.table-tabs';

    container.
      off('click', tabsSelector).
      on('click', tabsSelector, function(event) {
      var form  = container.find(pme.formSelector());
      var table = form.find(pme.tableSelector());

      var oldTabClass = form.find('li.table-tabs.selected a').attr('href').substring(1);
      var tabClass = $(this).find('a').attr('href').substring(1);

      //alert('old tab: ' + oldTabClass + ' new tab: ' + tabClass);

      // Inject the display triggers ...
      table.removeClass(oldTabClass).addClass(tabClass);

      // Record the tab in the form data
      form.find('input[name="'+pme.pmeSys('cur_tab')+'"]').val(tabClass.substring(4));

      // for styling and logic ...
      form.find(tabsSelector).removeClass('selected');
      $(this).addClass('selected');

      // account for unstyled chosen selected
      var reattachChosen = false;
      var pfx = (tabClass == 'tab-all') ? '' : 'td.' + tabClass;
      var selector = pme.pmeClassSelectors(pfx+' '+'div.chosen-container',
                                           [ 'input', 'filter', 'comp-filter' ]);
      //alert('selector: '+selector);
      form.find(selector).each(function(idx) {
        if ($(this).width() == 0) {
          $(this).prev().chosen('destroy');
          reattachChosen = true;
        }
      });
      if (reattachChosen) {
        pme.installFilterChosen(container);
        pme.installInputChosen(container);
      }

      $.fn.cafevTooltip.remove();
      callback();

      return false;
    });
  };

  PHPMYEDIT.init = function(containerSel) {
    var pme = this;

    containerSel = pme.selector(containerSel);
    var tableSel = 'table.'+pme.pmeToken('main');
    var formSel = 'form.'+pme.pmeToken('form');
    var container = pme.container(containerSel);
    var form = container.find(formSel);
    var hiddenClass = pme.pmeToken('hidden');
    var pmeFilter = pme.pmeToken('filter');
    var pmeSearch = pme.pmeToken('search');
    var pmeHide = pme.pmeToken('hide');
    var pmeSort = pme.pmeToken('sort');
    var pmeGoto = pme.pmeToken('goto');
    var pmePageRows = pme.pmeToken('pagerows');

    //alert(containerSel+" "+container.length);

    //Disable page-rows and goto submits, just not necessary
    container.find('input.'+pmePageRows).on('click', function(event) {
      event.stopImmediatePropagation();
      return false;
    });
    container.find('input.'+pmeGoto).on('click', function(event) {
      event.stopImmediatePropagation();
      return false;
    });

    // Hide search fields
    container.on('click', tableSel+' input.'+pmeHide, function(event) {
      event.stopImmediatePropagation(); // don't submit, not necessary

      var table = container.find(tableSel);
      var form = container.find(formSel);

      $(this).addClass(hiddenClass);
      table.find('tr.'+pmeFilter).addClass(hiddenClass);
      table.find('input.'+pmeSearch).removeClass(hiddenClass);
      form.find('input[name="'+pme.pmeSys('fl')+'"]').val(0);

      return false;
    });

    // Show search fields
    container.on('click', tableSel+' input.'+pmeSearch, function(event) {
      event.stopImmediatePropagation(); // don't submit, not necessary

      var table = container.find(tableSel);
      var form = container.find(formSel);

      $(this).addClass(hiddenClass);
      table.find('tr.'+pmeFilter).removeClass(hiddenClass);
      table.find('input.'+pmeHide).removeClass(hiddenClass);
      form.find('input[name="'+pme.pmeSys('fl')+'"]').val(1);

      // maybe re-style chosen select-boxes
      var reattachChosen = false;
      var tabClass = form.find('input[name="'+pme.pmeSys('cur_tab')+'"]').val();
      var pfx = 'tbody tr td'+(!tabClass || tabClass == 'all' ? '' : '.tab-' + tabClass);
      var selector = pme.pmeClassSelectors(pfx+' '+'div.chosen-container',
                                           ['filter', 'comp-filter']);
      //alert('selector: '+selector);
      table.find(selector).each(function(idx) {
        if ($(this).width() == 0 || $(this).width() == 60) {
          $(this).prev().chosen('destroy');
          reattachChosen = true;
        }
      });
      if (reattachChosen) {
        pme.installFilterChosen(container);
      }

      return false;
    });

    var onChangeSel =
      'input[type="checkbox"].'+pmeSort+','+
      'select.'+pmeGoto+','+
      'select.'+pmePageRows;
    if (!pme.selectChosen) {
      onChangeSel += ','+'select.'+pmeFilter;
    }
    container.
      off('change', onChangeSel).
      on('change', onChangeSel, function(event) {
      event.preventDefault();
      return PHPMYEDIT.pseudoSubmit($(this.form), $(this), containerSel);
    });

    // view/change/copy/delete buttons lead to a a popup
    if (form.find('input[name="DisplayClass"]').length > 0) {
      var submitSel = formSel+' input[class$="navigation"]:submit'+','+
        formSel+' input.'+pme.pmeToken('add')+':submit';
      container.off('click', submitSel).
        on('click', submitSel, function(event) {
        var self = $(this);
        if (!self.hasClass(pme.pmeToken('custom'))) {
          event.preventDefault();
          event.stopImmediatePropagation();

          PHPMYEDIT.tableDialog($(this.form), $(this), containerSel);

          return false;
        } else {
          return true;
        }
      });

      // Trigger view or change "operation" when clicking on a data-row.
      var rowSelector = formSel+' td.'+pme.pmeToken('cell')+':not(.control)'
                      + ','
                      + formSel+' td.'+pme.pmeToken('navigation');
      container.off('click', rowSelector).
        on('click', rowSelector, function(event) {

        if (event.target != this) {
          var target = $(event.target);
          // divs and spans which make it up to here will be ignored,
          // everything else results in the default action.
          if (target.is('.'+pme.pmeToken('email-check'))) {
            return true;
          }
          if (target.is('.'+pme.pmeToken('debit-note-check'))) {
            return true;
          }
          if (target.is('.'+pme.pmeToken('bulkcommit-check'))) {
            return true;
          }
          if (target.is('.graphic-links')) {
            return false;
          }
          if (!target.is('span') && !target.is('div')) {
            return true;
          }
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        var recordId = $(this).parent().data(pme.pmePrefix+'_sys_rec');
        var recordKey = pme.pmeSys('rec');
        var recordEl;
        if (PHPMYEDIT.directChange) {
          recordEl = '<input type="hidden" class="'+pme.pmeToken('change-navigation')+'"'
                   +' value="Change?'+recordKey+'='+recordId+'"'
                   +' name="'+pme.pmeSys('operation')+'" />';
        } else {
          recordEl = '<input type="hidden" class="'+pme.pmeToken('view-navigation')+'"'
                   +' value="View?'+recordKey+'='+recordId+'"'
                   +' name="'+pme.pmeSys('operation')+'" />';
        }

        // "this" does not necessarily has a form attribute
        var form = container.find(formSel);
        PHPMYEDIT.tableDialog(form, $(recordEl), containerSel);
        return false;
      });
    }

    // All remaining submit event result in a reload
    var submitSel = formSel+' :submit';
    container.off('click', submitSel).
      on('click', submitSel, function(event) {
      event.preventDefault();

      //alert("Button: "+$(this).attr('name'));

      return PHPMYEDIT.pseudoSubmit($(this.form), $(this), containerSel);
    });

    PHPMYEDIT.installTabHandler(container);

    if (pme.selectChosen) {
      var gotoSelect = container.find("select."+pmeGoto);
      gotoSelect.chosen({
        width:"auto",
        inherit_select_classes:true,
        disable_search_threshold: 10
      });
      if (gotoSelect.is(':disabled')) {
        // there is only one page
        gotoSelect.attr('data-placeholder', '1');
      } else {
        gotoSelect.attr('data-placeholder', ' ');
      }
      container.find("select."+pmeGoto).trigger('chosen:updated');

      container.find("select."+pmePageRows).chosen({
        width:"auto",
        inherit_select_classes:true,
        disable_search:true
      });
    }

    var keyPressSel = 'input.'+pmeFilter;
    container.off('keypress', keyPressSel);
    container.on('keypress', keyPressSel, function(event) {
      var pressed_key;
      if (event.which) {
        pressed_key = event.which;
      } else {
        pressed_key = event.keyCode;
      }
      if (pressed_key == 13) { // enter pressed
        event.preventDefault();
        return PHPMYEDIT.pseudoSubmit($(this.form), $(this), containerSel);
      }
      return true; // other key pressed
    });

    pme.installFilterChosen(container);
    pme.installInputChosen(container);

  };
})(window, jQuery, PHPMYEDIT);

$(document).ready(function(){

  CAFEVDB.addReadyCallback(function() {
    PHPMYEDIT.transposeReady();
    PHPMYEDIT.init();
    PHPMYEDIT.dialogOpen = {}; // not clear in init on purpose
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
