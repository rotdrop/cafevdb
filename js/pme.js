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
  PHPMYEDIT.singleDeselectOffset    = 18;
  PHPMYEDIT.defaultSelector         = '#'+CAFEVDB.appName+'-page-body'; ///< for delegate handlers, survives pseudo-submit
  PHPMYEDIT.defaultInnerSelector    = 'inner'; ///< to override delegate handlers, survices pseudo-submit
  PHPMYEDIT.dialogCSSId             = PHPMYEDIT.pmePrefix+'-table-dialog';

  /****************************************************************************
   *
   * Mix-in PHP setup parameters.
   *
   */

  $.extend(PHPMYEDIT, PHPMYEDIT.initialState);

  /****************************************************************************
   *
   * Only non-configurable data below this point.
   *
   */

  PHPMYEDIT.tableLoadCallbacks      = [];

  // PHPMYEDIT.popupPosition           = { my: "middle top",
  //                                       at: "middle bottom+50px",
  //                                       of: "#header" };

  PHPMYEDIT.popupPosition           = { my: "left top",
                                        at: "left+5% top+5%",
                                        //of: window
                                        of: '#app-content'
                                      };
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

  /**Genereate the default selector.
   *
   * @param selector The selector to construct the final selector
   * from. Maybe a jQuery object.
   */
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

  /**Generate the jQuery object corresponding to the inner container
   * of the ambient container. If the given argument is already a
   * jQuery object, then just return its first div child.
   */
  PHPMYEDIT.inner = function(selector) {
    var container;
    if (selector instanceof jQuery) {
      container = selector;
    } else {
      selector = this.selector(selector);
      container = $(selector);
    }
    return container.children('div:first');
  };

  /**Notify the spectator about SQL errors.*/
  PHPMYEDIT.notifySqlError = function(data) {
    // phpMyEdit echos mySQL-errors back.
    //console.info(data);
    if (data.sqlerror && data.sqlerror.error != 0) {
      $('#notification').text('MySQL Error: '+
                              data.sqlerror.error+
                              ': '+
                              data.sqlerror.message);
      $('#notification').fadeIn();
      //hide notification after 5 sec
      setTimeout(function() {
        $('#notification').fadeOut();
      }, 10000);
    }
  };

  PHPMYEDIT.addTableLoadCallback = function(template, cbObject) {
    if (typeof cbObject.context === 'undefined') {
      cbObject.context = this;
    }
    if (typeof cbObject.parameters === 'undefined') {
      cbObject.parameters = [];
    }
    if (typeof cbObject.parameters !== 'object') {
      cbObject.parameters = [ cbObject.parameters ];
    }
    this.tableLoadCallbacks[template] = cbObject;
  };

  PHPMYEDIT.tableLoadCallback = function(template, selector, parameters, resizeReadyCB) {
    var cbHandle;

    if (typeof this.tableLoadCallbacks[template] !== 'undefined') {
      cbHandle = this.tableLoadCallbacks[template];
    } else {
//      alert("No Callback for "+template);
      console.info('no table load callback for ' + template);
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

    console.info("Run table load callback", callback);
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

    var submitNames = [
      'morechange',
      'applyadd',
      'applycopy',
      'reloadchange',
      'reloadview',
      'reloadlist'
    ];

    var button = $(outerForm).find(pme.pmeSysNameSelectors('input', submitNames)).first();
    if (button.length > 0) {
      console.info('submit outer form by trigger click');
      button.trigger('click');
    } else {
      console.info('submit outer form "hard"');
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

  /**Replace the content of the already opened dialog with the given HTML-data.*/
  PHPMYEDIT.tableDialogReplace = function(container, content, options, callback) {
    var pme = this;

    var containerSel = '#'+options.DialogHolderCSSId;

    // remove the WYSIWYG editor, if any is attached
    CAFEVDB.removeEditor(container.find('textarea.wysiwygeditor'));

    container.css('height', 'auto');
    $.fn.cafevTooltip.remove();
    container.off(); // remove ALL delegate handlers
    container.html(content);
    container.find(pme.navigationSelector('reload')).addClass('loading');

    // general styling
    pme.init(containerSel);

    // attach the WYSIWYG editor, if any
    // editors may cause additional resizing
    container.dialog('option', 'height', 'auto');
    //container.dialog('option', 'position', pme.popupPosition);

    // re-attach events
    pme.tableDialogHandlers(options, callback);
  };

  PHPMYEDIT.post = function(post, callbacks) {
    const dfltCallbacks = {
      fail: function(xhr, status, errorThrown) {},
      done: function(htmlContent, historySize, historyPosition) {}
    };
    callbacks = $.extend(dfltCallbacks, callbacks);

    $.post(CAFEVDB.generateUrl('page/pme'), post)
      .fail(function(xhr, status, errorThrown) {
        CAFEVDB.handleAjaxError(xhr, status, errorThrown);
        callbacks.fail(xhr, status, errorThrown);
      })
      // HTTP response
      .done(function (htmlContent, textStatus, request) {
        const historySize = request.getResponseHeader('X-'+CAFEVDB.appName+'-history-size');
        const historyPosition = request.getResponseHeader('X-'+CAFEVDB.appName+'-history-position');
        callbacks.done(htmlContent, historySize, historyPosition);
      });
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
    const pme  = this;

    const reloadName  = options.ReloadName;
    const reloadValue = options.ReloadValue;

    const containerSel = '#'+options.DialogHolderCSSId;
    const container = $(containerSel);

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

      PHPMYEDIT.post(post, {
        fail: function(xhr, status, errorThrown) {
          console.info('cleanup');
          const dialogWidget = container.dialog('widget');

          CAFEVDB.Page.busyIcon(false);
          dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
          container.find(pme.navigationSelector('reload')).removeClass('loading');
        },
        done: function(htmlContent, historySize, historyPosition) {
          pme.tableDialogReplace(container, htmlContent, options, callback);
        }
      });
    });
    return false;
  };

  /**
   * Overload the phpMyEdit submit buttons in order to be able to
   * display the single data-set display, edit, add and copy form in a
   * popup.
   *
   * @param options Object with additional params to the
   * pme-table.php AJAX callback. Must at least contain the
   * templateRenderer component.
   *
   * @param callback Additional form validation callback. If
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
    const pme = this;

    if (typeof callback === 'undefined') {
      callback = function() { return false; };
    }

    const containerSel = '#'+options.DialogHolderCSSId;
    const container = $(containerSel);
    var contentChanged = false;

    pme.cancelDeferredReload(container);

    /* form.
     * pme-list
     * pme-change
     * pme-view
     * pme-delete
     * pme-copyadd
     */
    if (container.find(pme.pmeClassSelector('form', 'list')).length) {
      // main list view, just leave as is.
      const resize = function(reason) {
        console.info("resize callback");
        callback( { reason: reason } );
        const reloadSel = pme.pmeClassSelector('input', 'reload');
        container.find(reloadSel)
        .off('click')
        .on('click', function(event) {
          pme.tableDialogReload(options, callback);
          return false;
        });
      };
      resize('dialogOpen');
      container.on('pmetable:layoutchange', function(event) {
        console.info('layout change');
        resize(null);
      });
      return;
    }

    container.off('click', '**');

    PHPMYEDIT.installTabHandler(container, function() {
      callback( { reason: 'tabChange' } );
    });

    // The easy one, but for changed content
    const cancelButton = $(container).find(pme.pmeClassSelector('input', 'cancel'));
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
    const ReloadButtonSel = pme.pmeClassSelectors(
      'input',
      [ 'change', 'delete', 'copy', 'apply', 'more', 'reload' ]);
    const reloadingButton = $(container).find(ReloadButtonSel);

    // remove non-delegate handlers and stop default actions in any case.
    reloadingButton.off('click');

    // install a delegate handler on the outer-most container which
    // finally will run after possible inner data-validation handlers
    // have been executed.
    container
    .off('click', ReloadButtonSel)
    .on(
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
    const saveButtonSel = 'input.'+pme.pmeToken('save');
    const saveButton = $(container).find(saveButtonSel);
    saveButton.off('click');

    container
    .off('click', saveButtonSel)
    .on('click', saveButtonSel, function(event) {
      event.preventDefault();

      container.find(pme.navigationSelector('reload')).addClass('loading');

      options.modified = true; // we are the save-button ...

      const applySelector = pme.pmeSysNameSelectors(
        'input',
        ['morechange', 'applyadd', 'applycopy']);
      const deleteSelector = pme.pmeSysNameSelector('input', 'savedelete');

      pme.reloadDeferred(container).then(function() {
        var post = $(container).find(pme.formSelector()).serialize();
        post += '&' + $.param(options);
        var name, value;

        const deleteButton = container.find(deleteSelector);
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

        CAFEVDB.Notification.hide(function() {
          const dialogWidget = container.dialog('widget');

          dialogWidget.addClass(pme.pmeToken('table-dialog-blocked'));

          pme.post(post, {
            fail: function(xhr, status, errorThrown) {
              console.info('cleanup');
              dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
              container.find(pme.navigationSelector('reload')).removeClass('loading');
              CAFEVDB.Page.busyIcon(false);
	    },
            done: function(htmlContent, historySize, historyPosition) {
              const op = $(htmlContent).find(pme.pmeSysNameSelector('input', 'op_name'));
              if (op.length > 0 &&
                  (op.val() === 'add' || op.val() === 'delete')) {
                // Some error occured. Stay in the given mode.

                $('#notification').text('An error occurred.'
                                       + ' The data has not been saved.'
                                       + ' Unfortunately, no further information is available.'
                                       + ' Sorry for that.');
                $('#notification').fadeIn();
                //hide notification after 5 sec
                setTimeout(function() {
                  $('#notification').fadeOut();
                }, 10000);
                pme.tableDialogReplace(container, htmlContent, options, callback);
                return;
              }

              if (options.InitialViewOperation && deleteButton.length <= 0) {
                // return to initial view, but not after deletion
                dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
                options.ReloadName = options.InitialName;
                options.ReloadValue = options.InitialValue;
                pme.tableDialogReload(options, callback);
              } else {
                console.info('trigger close dialog');
                container.dialog('close');
              }
              container.find(pme.navigationSelector('reload')).removeClass('loading');
              CAFEVDB.Page.busyIcon(false);
            }
          });
        });
        return false;
      });
    });
    // Finally do the styling ...
    callback( { reason: 'dialogOpen' } );
  };

  /**Post the content of a pme-form via AJAX into a dialog
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
    var templateRenderer = form.find('input[name="templateRenderer"]');

    if (templateRenderer.length == 0) {
      console.info('no template renderer');
      // This just does not work.
      return false;
    }
    templateRenderer = templateRenderer.val();

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
    var dialogCSSId = pme.dialogCSSId;
    if (containerSel !== pme.defaultSelector) {
      dialogCSSId = containerSel.substring(1)+'-'+dialogCSSId;
      console.info('parent selector', containerSel, 'dialog selector', dialogCSSId);
    }

    var tableOptions = {
      ambientContainerSelector: pme.selector(containerSel),
      DialogHolderCSSId: dialogCSSId,
      templateRenderer: templateRenderer,
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
    const pme = this;

    const containerCSSId = tableOptions.DialogHolderCSSId;

    const template = CAFEVDB.Page.templateFromRenderer(
      tableOptions.templateRenderer);

    console.info(containerCSSId, pme.dialogOpen);
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
    pme.post(post, {
      fail: function(xhr, status, errorThrown) {
        console.info('cleanup');
        CAFEVDB.Page.busyIcon(false);
        pme.qdialogOpen[containerCSSId] = false;
      },
      done: function(htmlContent, historySize, historyPosition) {
        const containerSel = '#'+containerCSSId;
        var dialogHolder;
        dialogHolder = $('<div id="'+containerCSSId+'" class="resize-target"></div>');
        dialogHolder.html(htmlContent);
        dialogHolder.data('AmbientContainer', tableOptions.ambientContainerSelector);

        dialogHolder.find(pme.navigationSelector('reload')).addClass('loading');
        if (tableOptions.ModalDialog) {
          CAFEVDB.modalizer(true);
        }

        const popup = dialogHolder.cafevDialog({
          title: dialogHolder.find(pme.pmeClassSelector('span', 'short-title')).html(),
          position: pme.popupPosition,
          width: 'auto',
          height: 'auto',
          modal: false, //tableOptions.ModalDialog,
          closeOnEscape: false,
          dialogClass: pme.pmeToken('table-dialog')+' custom-close resize-target',
          resizable: false,
          dragStart: function(event) {
            var self = $(this);
            var widget = self.dialog('widget');
            var cssWidth = widget.prop('style').width;
            if (cssWidth === 'auto') {
              self.data('drag-width-tweak', true);
              widget.width(widget.width()+1); // copy with jquery-ui + ff drag bug
            }
          },
          resize_: function() {
            console.info('jq resize');
          },
          open: function() {

            //var tmp = CAFEVDB.modalWaitNotification("BlahBlah");
            //tmp.dialog('close');
            const dialogHolder = $(this);
            const dialogWidget = dialogHolder.dialog('widget');

            //dialogWidget.draggable('option', 'containment', '#content');

            CAFEVDB.dialogToBackButton(dialogHolder);
            CAFEVDB.dialogCustomCloseButton(dialogHolder, function(event, container) {
              event.preventDefault();
              var cancelButton = container.find(pme.pmeClassSelector('input', 'cancel')).first();
              if (cancelButton.length > 0) {
                event.stopImmediatePropagation();
                cancelButton.trigger('click');
              } else {
                dialogHolder.dialog('close');
              }
              return false;
            });

            dialogWidget.addClass(pme.pmeToken('table-dialog-blocked'));

            // general styling
            pme.init(containerSel);
            console.info(containerSel);

            const resizeHandler = function(parameters) {
              console.info('pme-resize');
              dialogHolder.dialog('option', 'height', 'auto');
              dialogHolder.dialog('option', 'width', 'auto');
              var newHeight = dialogWidget.height()
                    - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
              newHeight -= dialogHolder.outerHeight(true) - dialogHolder.height();
              //alert("Setting height to " + newHeight);
              dialogHolder.height(newHeight);
            };

            pme.tableDialogHandlers(tableOptions, function(parameters) {
              parameters = $.extend({ reason: 'unknown' }, parameters);
              console.info("dialog handlers callback", parameters);
              dialogHolder.css('height', 'auto');
              switch (parameters.reason) {
              case 'dialogOpen':
                CAFEVDB.addEditor(dialogHolder.find('textarea.wysiwygeditor'), function() {
                  console.info('pme.addEditor');
                  pme.transposeReady(containerSel);
                  pme.tableLoadCallback(template, containerSel, parameters, function() {
                    console.info('tableLoadCallback');
                    resizeHandler(parameters);
                    dialogWidget.removeClass(pme.pmeToken('table-dialog-blocked'));
                    dialogHolder.dialog('moveToTop');
                    CAFEVDB.toolTipsInit(containerSel);
                    CAFEVDB.Page.busyIcon(false);
                    dialogHolder.find(pme.navigationSelector('reload')).removeClass('loading');
                  });
                  CAFEVDB.pmeTweaks(dialogHolder);
                  $.fn.cafevTooltip.remove();
                });
              case 'tabChange':
                console.info('tab change');
                resizeHandler(parameters);
              }
            });

            // install delegate handlers on the widget s.t. we
            // can call .off() on the container
            dialogWidget.on('resize', containerSel, function(event) {
              console.info("pme dialog resize handler");
              resizeHandler(event);
            });
            dialogWidget.on('pmedialog:changed', containerSel, function(event) {
              tableOptions.modified = true;
            });
          },
          close: function(event) {
            $.fn.cafevTooltip.remove();
            var dialogHolder = $(this);

            dialogHolder.find('iframe').removeAttr('src');

            //alert($.param(tableOptions));
            if (tableOptions.modified === true) {
              //alert("Changed, triggerring on "+tableOptions.ambientContainerSelector);
              $(tableOptions.ambientContainerSelector).trigger('pmedialog:changed');
              pme.submitOuterForm(tableOptions.ambientContainerSelector);
            }

            dialogHolder.dialog('destroy');

            // At least konq. has the bug that removing a form
            // with submit inputs will submit the form. Very strange.
            dialogHolder.find('form input[type="submit"]').remove();
            dialogHolder.remove();

            // Remove modal plane if appropriate
            CAFEVDB.modalizer(false);

            pme.dialogOpen[containerCSSId] = false;

            CAFEVDB.unfocus();

            return false;
          }
        });
        return;
      }
    });
    return true;
  };

  /**Quasi-submit the pme-form.
   *
   * @param form The jQuery object corresponding to the pme-form.
   *
   * @param element The jQuery object corresponding to the element
   * causing the submit.
   *
   * @param selector The CSS selector corresponding to the
   * surrounding container (div element)
   *
   * @param resetFilter Bool, post a sw=Clear sting in addition, causing
   * phpMyEdit to reset the filter.
   */
  PHPMYEDIT.pseudoSubmit = function(form, element, selector, resetFilter) {
    const pme = this;

    if (resetFilter === true) {
      form.append('<input type="hidden"'
                 + ' name="'+pme.pmeSys('sw')+'"'
                 + ' value="Clear"/>');
    }

    selector = this.selector(selector);
    const container = this.container(selector);

    var templateRenderer = form.find('input[name="templateRenderer"]');
    if (templateRenderer.length <= 0 || element.hasClass('formsubmit')) {
      form.off('submit');
      if (element.attr('name')) { // undefined == false
        form.append('<input type="hidden" '+
                    'name="'+element.attr('name')+'" '+
                    'value="'+element.val()+'"/>');
      }
      console.info('hard pseudo submit');
      form.submit();
      return false;
    }

    CAFEVDB.Page.busyIcon(true);
    CAFEVDB.modalizer(true);

    templateRenderer = templateRenderer.val();
    const template = CAFEVDB.Page.templateFromRenderer(templateRenderer);

    // TODO: arguments
    var post = form.serialize();
    post += '&templateRenderer='+templateRenderer;
    if (element.attr('name') &&
        (!element.is(':checkbox') || element.is(':checked'))) {
      var name  = element.attr('name');
      var value = element.val();
      var obj = {};
      obj[name] = value;
      post += '&' + $.param(obj);
    }

    pme.post(post, {
      fail: function(xhr, status, errorThrown) {
        console.info('cleanup');
        CAFEVDB.Page.busyIcon(false);
        CAFEVDB.modalizer(false);
      },
      done: function(htmlContent, historySize, historyPosition) {

        if (historySize > 0) {
          CAFEVDB.Page.historySize = historySize;
          CAFEVDB.Page.historyPosition = historyPosition;
          CAFEVDB.Page.updateHistoryControls();
        }
        $.fn.cafevTooltip.remove();

        CAFEVDB.removeEditor(container.find('textarea.wysiwygeditor'));
        pme.inner(container).html(htmlContent);
        pme.init(selector);
        console.info("Attaching editors");
        CAFEVDB.addEditor(container.find('textarea.wysiwygeditor'), function() {
          pme.transposeReady(selector);
          //alert('psegudo submit');
          pme.tableLoadCallback(template, selector, { reason: 'formSubmit' }, function() {});
          CAFEVDB.pmeTweaks(container);
          CAFEVDB.toolTipsInit(selector);

          /* kill the modalizer */
          CAFEVDB.Page.busyIcon(false);
          CAFEVDB.modalizer(false);
          CAFEVDB.unfocus(); // move focus away from submit button

          container.trigger('pmetable:layoutchange');
        });
      }
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
      disable_search_threshold: 10,
      single_backstroke_delete: false
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
      no_results_text:noRes,
      single_backstroke_delete: false
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
      var chosenOptions = {
        //width:'100%',
        inherit_select_classes:true,
        disable_search: self.hasClass('no-search'),
        disable_search_threshold: self.hasClass('no-search') ? 999999 : 10,
        no_results_text: noRes,
        allow_single_deselect: self.hasClass('allow-empty'),
        single_backstroke_delete: false
      };
      if (self.hasClass('allow-empty')) {
        chosenOptions.width = (this.offsetWidth + pme.singleDeselectOffset) + 'px';
console.info('width', this.offsetWidth, self.outerWidth(), self.outerWidth(true));
      }
      if (self.hasClass('chosen-width-auto')) {
        chosenOptions.width = 'auto';
      }
      self.chosen(chosenOptions);
    });

    // Set title explicitly
    container.find("td."+pmeInput+" div.chosen-container, td."+pmeValue+" div.chosen-container").
      not('[title][title!=""]').
      each(function(index) {
      $(this).attr("title", pme.inputSelectChosenTitle);
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

    container
    .off('click', tabsSelector)
    .on('click', tabsSelector, function(event) {

      var form  = container.find(pme.formSelector());
      var table = form.find(pme.tableSelector());

      var oldTabClass = form.find('li.table-tabs.selected a').attr('href').substring(1);
      var tabClass = $(this).find('a').attr('href').substring(1);

      console.info('old tab: ' + oldTabClass + ' new tab: ' + tabClass);

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
        if ($(this).width() <= pme.singleDeselectOffset) {
          $(this).prev().chosen('destroy');
          reattachChosen = true;
        }
      });
      if (reattachChosen) {
        console.info('reattach chosen');
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
    var container = pme.container(containerSel);
    console.info('PME.init(): container selector: ', containerSel);
    console.info('PME.init(): container found: ', container.length);

    var tableSel = 'table.'+pme.pmeToken('main');
    var formSel = 'form.'+pme.pmeToken('form');
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

      container.trigger('pmetable:layoutchange');

      return false;
    });

    // Show search fields
    container.on('click', tableSel+' input.'+pmeSearch, function(event) {
      event.stopImmediatePropagation(); // don't submit, not necessary
      console.info('show search');

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

      container.trigger('pmetable:layoutchange');

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
    if (form.find('input[name="templateRenderer"]').length > 0) {
      var submitSel = formSel+' input[class$="navigation"]:submit'+','+
        formSel+' input.'+pme.pmeToken('add')+':submit';
      container
      .off('click', submitSel)
      .on('click', submitSel, function(event) {
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
      container
      .off('click', rowSelector)
      .on('click', rowSelector, function(event) {

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
          if (target.hasClass('nav')) {
            return true;
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

        // "this" does not necessarily has a form attribute
        var form = container.find(formSel);
        if (form.hasClass(pme.pmeToken('direct-change')) || PHPMYEDIT.directChange) {
          recordEl = '<input type="hidden" class="'+pme.pmeToken('change-navigation')+'"'
                   +' value="Change?'+recordKey+'='+recordId+'"'
                   +' name="'+pme.pmeSys('operation')+'" />';
        } else {
          recordEl = '<input type="hidden" class="'+pme.pmeToken('view-navigation')+'"'
                   +' value="View?'+recordKey+'='+recordId+'"'
                   +' name="'+pme.pmeSys('operation')+'" />';
        }

        console.info('about to open dialog');
        PHPMYEDIT.tableDialog(form, $(recordEl), containerSel);
        return false;
      });
    }

    // All remaining submit event result in a reload
    var submitSel = formSel+' :submit';
    container
    .off('click', submitSel)
    .on('click', submitSel, function(event) {

      event.preventDefault();

      //alert("Button: "+$(this).attr('name'));
      console.info('submit');
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

    /* The next two handlers allow the chosen-dropdown to extend the
     * current dialog. This can happen for small dialog windows and/or
     * if the select box is close to the bottom of the page.
     *
     */
    var tableContainerId = PHPMYEDIT.pmeIdSelector('table-container');

    container.on('chosen:before_showing_dropdown', tableContainerId+' select', function(event) {
      if (!container.hasClass('ui-widget-content')) {
        return true;
      }
      if (container.hasVerticalScrollbar()) {
        return true;
      }
      var widget = container.cafevDialog('widget');
      var tableContainer = container.find(tableContainerId);
      if (widget.hasVerticalScrollbar() || tableContainer.hasVerticalScrollbar()) {
        return true;
      }
      widget.css('overflow', 'visible');
      container.css('overflow', 'visible');
      tableContainer.css('overflow', 'visible');
      return true;
    });

    // container.on('chosen:before_hiding_dropdown', tableContainerId+' select', function(event) {
    //   return true;
    // });

    // container.on('chosen:showing_dropdown', tableContainerId+' select', function(event) {
    //   console.info('chosen:showing_dropdown');
    //   return true;
    // });

    container.on('chosen:hiding_dropdown', tableContainerId+' select', function(event) {
      if (!container.hasClass('ui-widget-content')) {
        return true;
      }
      if (container.hasVerticalScrollbar()) {
        return true;
      }
      var widget = container.cafevDialog('widget');
      var tableContainer = container.find(tableContainerId);
      if (widget.hasVerticalScrollbar() || tableContainer.hasVerticalScrollbar()) {
        return true;
      }
      tableContainer.css('overflow', '');
      container.css('overflow', '');
      widget.css('overflow', '');
      return true;
    });

  };
})(window, jQuery, PHPMYEDIT);

$(document).ready(function(){

  CAFEVDB.addReadyCallback(function() {
    PHPMYEDIT.transposeReady();
    PHPMYEDIT.init();
    PHPMYEDIT.dialogOpen = {}; // not cleared in init on purpose
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
