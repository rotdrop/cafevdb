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
/**
 * @file
 *
 * General PME table stuff, popup-handling.
 */

import { $, jQuery } from './globals.js';
import * as PMEState from './pme-state.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as Notification from './notification.js';
import * as WysiwygEditor from './wysiwyg-editor.js';
import * as DialogUtils from './dialog-utils.js';
import * as Dialogs from './dialogs.js';
import checkInvalidInputs from './check-invalid-inputs.js';
import pmeTweaks from './pme-tweaks.js';
import clear from '../util/clear-object.js';
import {
  sys as pmeSys,
  data as pmeData,
  token as pmeToken,
  idSelector as pmeIdSelector,
  classSelector as pmeClassSelector,
  classSelectors as pmeClassSelectors,
  sysNameSelector as pmeSysNameSelector,
  sysNameSelectors as pmeSysNameSelectors,
  navigationSelector as pmeNavigationSelector,
  formSelector as pmeFormSelector,
  tableSelector as pmeTableSelector,
  selector as pmeSelector,
  rec as pmeRec,
  container as pmeContainer,
} from './pme-selectors.js';
import 'jquery-ui/ui/effects/effect-highlight';
import 'jquery-ui/ui/widgets/sortable';
import 'selectize';
import 'selectize/dist/css/selectize.bootstrap4.css';
// import 'selectize/dist/css/selectize.css';
require('cafevdb-selectize.css');

require('pme-table.scss');

const popupPosition = {
  my: 'left top',
  at: 'left+5% top+5%',
  // of: window
  of: '#app-content',
};

const appName = PMEState.appName;
const PHPMyEdit = PMEState.PHPMyEdit;
const pmeDefaultSelector = PMEState.defaultSelector;
const pmePrefix = PMEState.prefix;
const pmeOpenDialogs = PMEState.openDialogs;

/**
 * Generate the jQuery object corresponding to the inner container
 * of the ambient container. If the given argument is already a
 * jQuery object, then just return its first div child.
 *
 * @param {Object|String} selector TBD.
 *
 * @returns {jQuery}
 */
const pmeInner = function(selector) {
  let container;
  if (selector instanceof jQuery) {
    container = selector;
  } else {
    selector = pmeSelector(selector);
    container = $(selector);
  }
  return container.children('div:first');
};

const pmeAddTableLoadCallback = function(template, cbObject) {
  if (typeof cbObject.context === 'undefined') {
    cbObject.context = this;
  }
  if (typeof cbObject.parameters === 'undefined') {
    cbObject.parameters = [];
  }
  if (typeof cbObject.parameters !== 'object') {
    cbObject.parameters = [cbObject.parameters];
  }
  PHPMyEdit.tableLoadCallbacks[template] = cbObject;
};

const tableLoadCallback = function(template, selector, parameters, resizeReadyCB) {
  let cbHandle;

  if (typeof PHPMyEdit.tableLoadCallbacks[template] !== 'undefined') {
    cbHandle = PHPMyEdit.tableLoadCallbacks[template];
  } else {
    // console.info('no table load callback for ' + template);
    throw new Error('no table load callback for ' + template);
  }

  if (typeof selector === 'undefined') {
    selector = pmeDefaultSelector;
  }
  if (typeof resizeReadyCB !== 'function') {
    resizeReadyCB = function() {};
  }

  const callback = cbHandle.callback;
  const context = cbHandle.context;
  if (typeof parameters === 'undefined') {
    parameters = {};
  }
  parameters = $.extend({ reason: null }, parameters);

  const args = [selector, parameters, resizeReadyCB];
  $.merge(args, cbHandle.parameters);

  console.debug('Run table load callback for ' + template, callback);
  return callback.apply(context, args);
};

/**
 * Submit the base form in order to synchronize any changes caused
 * by the dialog form.
 *
 * @param {String} selector TBD.
 */
const pmeSubmitOuterForm = function(selector) {
  const outerSel = pmeSelector(selector);

  // try a reload while saving data. The purpose is to resolve
  // inter-table dependencies like changed instrument lists and so
  // on. Be careful not to trigger top and bottom buttons.
  const outerForm = $(outerSel + ' ' + pmeFormSelector());

  const submitNames = [
    'morechange',
    'applyadd',
    'applycopy',
    'reloadchange',
    'reloadview',
    'reloadlist',
  ];

  const button = $(outerForm).find(pmeSysNameSelectors('input', submitNames)).first();
  if (button.length > 0) {
    button.trigger('click');
  } else {
    // submit the outer form
    // outerForm.submit();
    pseudoSubmit(outerForm, $(), outerSel, 'pme');
  }
};

const cancelDeferredReload = function(container) {
  const deferKey = pmePrefix + '-submitdefer';
  container.removeData(deferKey);
};

const pmeDeferReload = function(container) {
  const deferKey = pmePrefix + '-submitdefer';
  const defer = $.Deferred();

  container.data(deferKey, defer.promise());

  return defer;
};

const reloadDeferred = function(container) {
  const deferKey = pmePrefix + '-submitdefer';

  return $.when(container.data(deferKey));
};

/**
 * Replace the content of the already opened dialog with the given
 * HTML-data.
 *
 * @param {Object} container TBD.
 *
 * @param {String} content TBD.
 *
 * @param {Object} options TBD.
 *
 * @param {Function} callback TBD.
 *
 * @param {Object} triggerData Additional data passed to the calling
 * event handler after being triggered artifically.
 */
const tableDialogReplace = function(container, content, options, callback, triggerData) {

  const containerSel = '#' + options.DialogHolderCSSId;

  // remove the WYSIWYG editor, if any is attached
  WysiwygEditor.removeEditor(container.find('textarea.wysiwyg-editor'));

  container.css('height', 'auto');
  $.fn.cafevTooltip.remove();
  container.off(); // remove ALL delegate handlers
  container.html(content);
  container.find(pmeNavigationSelector('reload')).addClass('loading');

  // general styling
  pmeInit(containerSel);

  // attach the WYSIWYG editor, if any
  // editors may cause additional resizing
  container.dialog('option', 'height', 'auto');
  // container.dialog('option', 'position', popupPosition);

  // re-attach events
  tableDialogHandlers(options, callback, triggerData);
};

const pmePost = function(post, callbacks) {
  const dfltCallbacks = {
    fail(xhr, status, errorThrown) {},
    done(htmlContent, historySize, historyPosition) {},
  };
  callbacks = $.extend(dfltCallbacks, callbacks);

  $.post(CAFEVDB.generateUrl('page/pme/load'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
      callbacks.fail(xhr, status, errorThrown);
    })
  // HTTP response
    .done(function(htmlContent, textStatus, request) {
      const historySize = parseInt(request.getResponseHeader('X-' + appName + '-history-size'));
      const historyPosition = parseInt(request.getResponseHeader('X-' + appName + '-history-position'));
      callbacks.done(htmlContent, historySize, historyPosition);
    });
};

/**
 * Reload the current PME-dialog.
 *
 * @param {Object} options The current dialog options. In particular
 * options.ReloadName and options.ReloadValue must hold name and
 * value of the curent (pseudo-) submit input
 * element. options.modified must already be up-to-date.
 *
 * @param {Function} callback The application provided callback which is used
 * to shape the HTML after loading.
 *
 * @param {Object} triggerData Additional data passed to the calling
 * event handler after being triggered artifically.
 */
const tableDialogReload = function(options, callback, triggerData) {

  const reloadName = options.ReloadName;
  const reloadValue = options.ReloadValue;

  const containerSel = '#' + options.DialogHolderCSSId;
  const container = $(containerSel);

  container.dialog('widget').addClass(pmeToken('table-dialog-blocked'));
  container.find(pmeNavigationSelector('reload')).addClass('loading');

  // Possibly delay reload until validation handlers have done their
  // work.
  reloadDeferred(container).then(function() {
    let post = container.find(pmeFormSelector()).serialize();

    // add the option values
    post += '&' + $.param(options);

    // add name and value of the "submit" button.
    const obj = {};
    obj[reloadName] = reloadValue;
    post += '&' + $.param(obj);

    pmePost(post, {
      fail(xhr, status, errorThrown) {
        const dialogWidget = container.dialog('widget');

        Page.busyIcon(false);
        dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
        container.find(pmeNavigationSelector('reload')).removeClass('loading');
      },
      done(htmlContent, historySize, historyPosition) {
        tableDialogReplace(container, htmlContent, options, callback, triggerData);
      },
    });
  });
};

/**
 * Overload the PHPMyEdit submit buttons in order to be able to
 * display the single data-set display, edit, add and copy form in a
 * popup.
 *
 * @param {Object} options Object with additional params to the
 * pme-table.php AJAX callback. Must at least contain the
 * templateRenderer component.
 *
 * @param {Function} changeCallback Handler to call after dialog open
 * and tab change.
 *
 * @param {Object} triggerData Optional additonal data passed to an
 * articifically triggered calling event handler. Will be passed on to
 * the changeCallback.
 */
const tableDialogHandlers = function(options, changeCallback, triggerData) {

  if (typeof changeCallback === 'undefined') {
    changeCallback = function(options) { return false; };
  }

  const containerSel = '#' + options.DialogHolderCSSId;
  const container = $(containerSel);

  cancelDeferredReload(container);

  /* form.
   * pme-list
   * pme-change
   * pme-view
   * pme-delete
   * pme-copyadd
   */
  if (container.find(pmeClassSelector('form', 'list')).length) {
    // main list view, just leave as is.
    const resize = function(reason) {
      changeCallback({ reason });
      const reloadSel = pmeClassSelector('input', 'reload');
      container.find(reloadSel)
        .off('click')
        .on('click', function(event, triggerData) {
          tableDialogReload(options, changeCallback, triggerData);
          return false;
        });
    };
    resize('dialogOpen');
    container.on('pmetable:layoutchange', function(event) {
      resize(null);
    });
    return;
  }

  container.off('click', '**');

  installTabHandler(container, function() {
    changeCallback({ reason: 'tabChange' });
  });

  // The easy one, but for changed content
  const cancelButton = $(container).find(pmeClassSelector('input', 'cancel'));
  cancelButton
    .off('click')
    .on('click', function(event, triggerData) {

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
        tableDialogReload(options, changeCallback, triggerData);
      } else {
        container.dialog('close');
      }

      return false;
    });

  // The complicated ones. This reloads new data.
  const ReloadButtonSel = pmeClassSelectors(
    'input',
    ['change', 'delete', 'copy', 'apply', 'more', 'reload']);
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
      function(event, triggerData) {
        const submitButton = $(this);

        const reloadName = submitButton.attr('name');
        const reloadValue = submitButton.val();
        options.ReloadName = reloadName;
        options.ReloadValue = reloadValue;
        if (!submitButton.hasClass(pmeToken('change'))
            && !submitButton.hasClass(pmeToken('delete'))
            && !submitButton.hasClass(pmeToken('copy'))
            && !submitButton.hasClass(pmeToken('reload'))) {
          // so this is pme-more, morechange

          if (!checkInvalidInputs(container)) {
            return false;
          }

          options.modified = true;
        }
        tableDialogReload(options, changeCallback, triggerData);
        // might be costly?
        // pmeSubmitOuterForm(options.ambientContainerSelector);

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
  const saveButtonSel = 'input.' + pmeToken('save');
  const saveButton = $(container).find(saveButtonSel);
  saveButton.off('click');

  container
    .off('click', saveButtonSel)
    .on('click', saveButtonSel, function(event, triggerData) {

      const reloadButton = container.find(pmeNavigationSelector('reload'));
      reloadButton.addClass('loading');

      // Brief front-end-check for empty required fields.
      if (!checkInvalidInputs(
        container, function() {
          reloadButton.removeClass('loading');
          Page.busyIcon(false);
        })) {
        return false;
      }

      options.modified = true; // we are the save-button ...

      const applySelector = pmeSysNameSelectors(
        'input',
        ['morechange', 'applyadd', 'applycopy']);
      const deleteSelector = pmeSysNameSelector('input', 'savedelete');

      reloadDeferred(container).then(function() {
        let post = $(container).find(pmeFormSelector()).serialize();
        post += '&' + $.param(options);
        let name, value;

        const deleteButton = container.find(deleteSelector);
        if (deleteButton.length > 0) {
          name = deleteButton.attr('name');
          value = deleteButton.val();
        } else {
          const applyButton = container.find(applySelector);
          if (applyButton.length > 0) {
            name = applyButton.attr('name');
            value = applyButton.val();
          }
        }
        const obj = {};
        obj[name] = value;
        post += '&' + $.param(obj);

        Notification.hide(function() {
          const dialogWidget = container.dialog('widget');

          dialogWidget.addClass(pmeToken('table-dialog-blocked'));

          // @todo Error handling is flaky
          pmePost(post, {
            fail(xhr, status, errorThrown) {
              dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
              reloadButton.removeClass('loading');
              Page.busyIcon(false);
            },
            done(htmlContent, historySize, historyPosition) {
              const op = $(htmlContent).find(pmeSysNameSelector('input', 'op_name'));
              if (op.length > 0 && (op.val() === 'add' || op.val() === 'delete')) {
                // Some error occured. Stay in the given mode.

                Notification.show(t(appName, 'An error occurred.'
                                    + ' The data has not been saved.'
                                    + ' Unfortunately, no further information is available.'
                                    + ' Sorry for that.'), { timeout: 15 });
                tableDialogReplace(container, htmlContent, options, changeCallback);
                return;
              }

              if (options.InitialViewOperation && deleteButton.length <= 0) {
                // return to initial view, but not after deletion
                dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
                options.ReloadName = options.InitialName;
                options.ReloadValue = options.InitialValue;
                tableDialogReload(options, changeCallback, triggerData);
              } else {
                if (container.hasClass('ui-dialog-content')) {
                  container.dialog('close');
                }
              }
              reloadButton.removeClass('loading');
              Page.busyIcon(false);
            },
          });
        });
      });
      return false;
    });

  // Finally do the styling ...
  changeCallback({
    reason: 'dialogOpen',
    triggerData,
  });
};

/**
 * Post the content of a pme-form via AJAX into a dialog
 * widget. Useful for editing, viewing etc. because this avoids the
 * need to reload the base table (when only viewing single
 * data-sets).
 *
 * @param {jQuery} form The form to take the informatoin from, including the
 * name of the PHP class which generates the response.
 *
 * @param {jQuery} element The input element which initiates the "form
 * submit". In particular, we assume PME "view operation" if element
 * carries a CSS class "pme-viewXXXXX" with XXXXX being anything.
 *
 * @param {String} containerSel TBD.
 *
 * @returns {bool}
 */
const tableDialog = function(form, element, containerSel) {

  let post = form.serialize();
  let templateRenderer = form.find('input[name="templateRenderer"]');

  if (templateRenderer.length === 0) {
    // This just does not work.
    return false;
  }
  templateRenderer = templateRenderer.val();

  let viewOperation = false;

  const initialName = element.attr('name');
  const initialValue = element.val();
  if (initialName) {
    const obj = {};
    obj[initialName] = initialValue;
    post += '&' + $.param(obj);
  }

  const cssClass = element.attr('class');
  if (cssClass) {
    viewOperation = cssClass.indexOf(pmeToken('view')) > -1;
  }

  let dialogCSSId = PHPMyEdit.dialogCSSId;
  containerSel = pmeSelector(containerSel);
  if (containerSel !== pmeDefaultSelector) {
    dialogCSSId = containerSel.substring(1) + '-' + dialogCSSId;
  }

  const tableOptions = {
    ambientContainerSelector: containerSel,
    DialogHolderCSSId: dialogCSSId,
    templateRenderer,
    InitialViewOperation: viewOperation,
    InitialName: initialName,
    InitialValue: initialValue,
    ReloadName: initialName,
    ReloadValue: initialValue,
    ModalDialog: true,
    modified: false, // avoid reload of base table unless necessary
  };
  pmeTableDialogOpen(tableOptions, post);
  return true;
};

/**
 * Open directly the popup holding the form data. We listen for the
 * custom event 'pmedialog:changed' on the DialogHolder. This event will
 * be forwarded to the AmbientContainer. The idea is that we can
 * update the "modified" component of chained dialogs in a reliable
 * way.
 *
 * @param {Object} tableOptions Option array, see above
 *
 * @param {String} post Additional query parameters. In principle it
 * is also possible to store all values in tableOptions, as this is
 * added to the query-string in any case.
 *
 * @returns {bool}
 */
const pmeTableDialogOpen = function(tableOptions, post) {

  const containerCSSId = tableOptions.DialogHolderCSSId;

  const template = Page.templateFromRenderer(
    tableOptions.templateRenderer);

  if (pmeOpenDialogs[containerCSSId]) {
    return false;
  }
  pmeOpenDialogs[containerCSSId] = true;

  Page.busyIcon(true);

  if (typeof tableOptions.ModalDialog === 'undefined') {
    tableOptions.ModalDialog = true;
  }
  if (typeof post === 'undefined') {
    post = $.param(tableOptions);
  } else {
    post += '&' + $.param(tableOptions);
  }
  pmePost(post, {
    fail(xhr, status, errorThrown) {
      Page.busyIcon(false);
      pmeOpenDialogs[containerCSSId] = false;
    },
    done(htmlContent, historySize, historyPosition) {
      const containerSel = '#' + containerCSSId;
      const dialogHolder = $('<div id="' + containerCSSId + '" class="resize-target"></div>');
      dialogHolder.html(htmlContent);
      dialogHolder.data('AmbientContainer', tableOptions.ambientContainerSelector);

      dialogHolder.find(pmeNavigationSelector('reload')).addClass('loading');
      if (tableOptions.ModalDialog) {
        CAFEVDB.modalizer(true);
      }

      dialogHolder.cafevDialog({
        title: dialogHolder.find(pmeClassSelector('span', 'short-title')).html(),
        position: popupPosition,
        width: 'auto',
        height: 'auto',
        modal: false, // tableOptions.ModalDialog,
        closeOnEscape: false,
        dialogClass: pmeToken('table-dialog') + ' custom-close resize-target ' + template,
        resizable: false,
        dragStart(event) {
          const self = $(this);
          const widget = self.dialog('widget');
          const cssWidth = widget.prop('style').width;
          if (cssWidth === 'auto') {
            self.data('drag-width-tweak', true);
            widget.width(widget.width() + 1); // copy with jquery-ui + ff drag bug
          }
        },
        resize() {
          console.info('jq resize');
        },
        open() {

          const dialogHolder = $(this);
          const dialogWidget = dialogHolder.dialog('widget');

          DialogUtils.toBackButton(dialogHolder);
          DialogUtils.customCloseButton(dialogHolder, function(event, container) {
            const cancelButton = container.find(pmeClassSelector('input', 'cancel')).first();
            if (cancelButton.length > 0) {
              event.stopImmediatePropagation();
              cancelButton.trigger('click');
            } else {
              dialogHolder.dialog('close');
            }
            return false;
          });

          dialogWidget.addClass(pmeToken('table-dialog-blocked'));

          // general styling
          pmeInit(containerSel);

          const resizeHandler = function(parameters) {
            dialogHolder.dialog('option', 'height', 'auto');
            dialogHolder.dialog('option', 'width', 'auto');
            let newHeight = dialogWidget.height()
                - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
            newHeight -= dialogHolder.outerHeight(true) - dialogHolder.height();
            dialogHolder.height(newHeight);
          };

          tableDialogHandlers(tableOptions, function(parameters) {
            const defaultParameters = {
              reason: 'unknown',
              triggerData: {
                postOpen() {},
              },
            };
            parameters = $.extend({}, defaultParameters, parameters);
            if (parameters.reason === 'unknown') {
              console.trace();
            }
            dialogHolder.css('height', 'auto');
            switch (parameters.reason) {
            case 'dialogOpen':
              WysiwygEditor.addEditor(dialogHolder.find('textarea.wysiwyg-editor'), function() {
                transposeReady(containerSel);
                tableLoadCallback(template, containerSel, parameters, function() {
                  // console.trace();
                  // installInputChosen(containerSel);
                  resizeHandler(parameters);
                  dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
                  dialogHolder.dialog('moveToTop');
                  CAFEVDB.toolTipsInit(containerSel);
                  Page.busyIcon(false);
                  dialogHolder.find(pmeNavigationSelector('reload')).removeClass('loading');
                });
                pmeTweaks(dialogHolder);
                $.fn.cafevTooltip.remove();
                parameters.triggerData.postOpen();
              });
              break;
            case 'tabChange':
              installInputChosen(containerSel, 'chosen-invisible');
              resizeHandler(parameters);
              break;
            }
          });

          // install delegate handlers on the widget s.t. we
          // can call .off() on the container
          dialogWidget.on('resize', containerSel, function(event) {
            resizeHandler(event);
          });
          dialogWidget.on('pmedialog:changed', containerSel, function(event) {
            tableOptions.modified = true;
          });
        },
        close(event) {
          $.fn.cafevTooltip.remove();
          const dialogHolder = $(this);

          dialogHolder.find('iframe').removeAttr('src');

          if (tableOptions.modified === true) {
            // reload outer form
            $(tableOptions.ambientContainerSelector).trigger('pmedialog:changed');
            pmeSubmitOuterForm(tableOptions.ambientContainerSelector);
          }

          dialogHolder.dialog('destroy');

          // At least konq. has the bug that removing a form
          // with submit inputs will submit the form. Very strange.
          dialogHolder.find('form input[type="submit"]').remove();
          dialogHolder.remove();

          // Remove modal plane if appropriate
          CAFEVDB.modalizer(false);

          pmeOpenDialogs[containerCSSId] = false;

          CAFEVDB.unfocus();

          return false;
        },
      });
    },
  });
  return true;
};

/**
 * Quasi-submit the pme-form.
 *
 * @param {jQuery} form The jQuery object corresponding to the pme-form.
 *
 * @param {jQuery} element The jQuery object corresponding to the element
 * causing the submit.
 *
 * @param {String} selector The CSS selector corresponding to the
 * surrounding container (div element)
 *
 * @param {bool} resetFilter Bool, post a sw=Clear string in addition,
 * causing PHPMyEdit to reset the filter.
 *
 * @returns {bool}
 */
const pseudoSubmit = function(form, element, selector, resetFilter) {

  if (resetFilter === true) {
    form.append('<input type="hidden"'
                + ' name="' + pmeSys('sw') + '"'
                + ' value="Clear"/>');
  }

  selector = pmeSelector(selector);
  const container = pmeContainer(selector);

  let templateRenderer = form.find('input[name="templateRenderer"]');
  if (templateRenderer.length <= 0 || element.hasClass('formsubmit')) {
    form.off('submit');
    if (element.attr('name')) { // undefined == false
      form.append(
        '<input type="hidden" '
          + 'name="' + element.attr('name') + '" '
          + 'value="' + element.val() + '"/>');
    }
    form.submit();
    return false;
  }

  Page.busyIcon(true);
  CAFEVDB.modalizer(true);

  templateRenderer = templateRenderer.val();
  const template = Page.templateFromRenderer(templateRenderer);

  let post = form.serialize();
  post += '&templateRenderer=' + templateRenderer;
  if (element.attr('name')
      && (!element.is(':checkbox') || element.is(':checked'))) {
    const name = element.attr('name');
    const value = element.val();
    const obj = {};
    obj[name] = value;
    post += '&' + $.param(obj);
  }

  pmePost(post, {
    fail(xhr, status, errorThrown) {
      Page.busyIcon(false);
      CAFEVDB.modalizer(false);
    },
    done(htmlContent, historySize, historyPosition) {

      if (historySize > 0) {
        Page.updateHistoryControls(historyPosition, historySize);
      }
      $.fn.cafevTooltip.remove();

      WysiwygEditor.removeEditor(container.find('textarea.wysiwyg-editor'));
      pmeInner(container).html(htmlContent);
      pmeInit(selector);
      WysiwygEditor.addEditor(container.find('textarea.wysiwyg-editor'), function() {
        transposeReady(selector);
        tableLoadCallback(template, selector, { reason: 'formSubmit' }, function() {});
        pmeTweaks(container);
        CAFEVDB.toolTipsInit(selector);

        /* kill the modalizer */
        Page.busyIcon(false);
        CAFEVDB.modalizer(false);
        CAFEVDB.unfocus(); // move focus away from submit button

        container.trigger('pmetable:layoutchange');
      });
    },
  });
  return false;
};

/**
 * Trigger either one of the upper or the lower button controls (but
 * not both!)
 *
 * @param {String} buttonName TBD.
 *
 * @param {String} containerSel TBD.
 *
 * @returns {bool}
 */
const pmeTriggerSubmit = function(buttonName, containerSel) {
  const container = pmeContainer(containerSel);
  const button = container.find('input[name="' + pmeSys(buttonName) + '"]').first();

  if (button.length > 0) {
    button.trigger('click');
    return true;
  } else {
    return false;
  }
};

/**
 * Transpose the main tabel if desired.
 *
 * @param {String} selector TBD.
 *
 * @param {String} containerSel TBD.
 */
const transposeMainTable = function(selector, containerSel) {
  const container = pmeContainer(containerSel);
  const table = container.find(selector);

  const headerRow = table.find('thead tr');
  headerRow.detach();
  if (headerRow.length > 0) {
    headerRow.prependTo(table.find('tbody'));
  }
  const t = table.find('tbody').eq(0);
  const sortinfo = t.find(pmeClassSelector('tr', 'sortinfo'));
  const queryinfo = t.find(pmeClassSelector('tr', 'queryinfo'));
  // These are huge cells spanning the entire table, move them on
  // top of the transposed table afterwards.
  sortinfo.detach();
  queryinfo.detach();
  const r = t.find('tr');
  const cols = r.length;
  const rows = r.eq(0).find('td,th').length;
  let cell, next, tem;
  let i = 0;
  const tb = $('<tbody></tbody>');

  while (i < rows) {
    cell = 0;
    tem = $('<tr></tr>');
    while (cell < cols) {
      next = r.eq(cell++).find('td,th').eq(0);
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
      .appendTo(table.find('thead'))
      .children()
      .each(function() {
        let tdclass = $(this).attr('class');
        if (tdclass.length > 0) {
          tdclass = ' class="' + tdclass + '"';
        } else {
          tdclass = '';
        }
        $(this).replaceWith('<th' + tdclass + ' scope="col">' + $(this).html() + '</th>');
      });
  }
  queryinfo.prependTo(table.find('tbody'));
  sortinfo.prependTo(table.find('tbody'));

  // if (true) {
  $(table)
    .find('tbody tr th:first-child')
    .each(function() {
      let thclass = $(this).attr('class');
      if (thclass.length > 0) {
        thclass = ' class="' + thclass + '"';
      } else {
        thclass = '';
      }
      $(this).replaceWith('<td' + thclass + ' scope="row">' + $(this).html() + '</td>');
    });
  // }
  table.show();
};

/**
 * Transpose the main table based on boolean value of transpose.
 *
 * @param {bool} transpose TBD.
 *
 * @param {String} containerSel TBD.
 */
const maybeTranspose = function(transpose, containerSel) {
  const container = pmeContainer(containerSel);
  let pageitems;
  const tooltip = container.find('.tooltip');

  const trUp = pmeIdSelector('transpose-up');
  const trDown = pmeIdSelector('transpose-down');
  const tr = pmeIdSelector('transpose');
  const trClass = pmeToken('transposed');
  const unTrClass = pmeToken('untransposed');

  if (transpose) {
    tooltip.remove();
    transposeMainTable(pmeTableSelector(), container);
    pageitems = t('cafevdb', '#columns');

    container.find('input[name="Transpose"]').val('transposed');
    container.find(trUp).removeClass(unTrClass).addClass(trClass);
    container.find(trDown).removeClass(unTrClass).addClass(trClass);
    container.find(tr).removeClass(unTrClass).addClass(trClass);
  } else {
    tooltip.remove();
    transposeMainTable(pmeTableSelector(), container);
    pageitems = t('cafevdb', '#rows');

    container.find('input[name="Transpose"]').val('untransposed');
    container.find(trUp).removeClass(trClass).addClass(unTrClass);
    container.find(trDown).removeClass(trClass).addClass(unTrClass);
    container.find(tr).removeClass(trClass).addClass(unTrClass);
  }
  container.find(pmeClassSelector('input', 'pagerows')).val(pageitems);
};

/**
 * Ready callback.
 *
 * @param {String} containerSel TBD.
 */
const transposeReady = function(containerSel) {

  const container = pmeContainer(containerSel);

  const trUp = pmeIdSelector('transpose-up');
  const trDown = pmeIdSelector('transpose-down');
  const tr = pmeIdSelector('transpose');
  const trClass = pmeToken('transposed');
  // const unTrClass = pmeToken('untransposed');

  // Transpose or not: if there is a transpose button
  const inhibitTranspose = container.find('input[name="InhibitTranspose"]').val() === 'true';
  const controlTranspose = (container.find('input[name="Transpose"]').val() === 'transposed'
                            || container.find(trUp).hasClass(trClass)
                            || container.find(trDown).hasClass(trClass)
                            || container.find(tr).hasClass(trClass));

  if (!inhibitTranspose && controlTranspose) {
    maybeTranspose(true);
  } else {
    // Initially the tabel _is_ untransposed
    // maybeTranspose(false); // needed?
  }
};

const installFilterChosen = function(containerSel) {

  if (!PHPMyEdit.selectChosen) {
    return;
  }

  const pmeFilter = pmeToken('filter');
  const pmeCompFilter = pmeToken('comp-filter');

  const container = pmeContainer(containerSel);

  const noRes = PHPMyEdit.filterSelectNoResult;

  container.find('select.' + pmeCompFilter).chosen({
    width: 'auto',
    inherit_select_classes: true,
    disable_search_threshold: 10,
    single_backstroke_delete: false,
  });

  // Provide a data-placeholder and also remove the match-all
  // filter, which is not needed when using chosen.
  container.find('select.' + pmeFilter).attr('data-placeholder', PHPMyEdit.filterSelectPlaceholder);
  container.off('change', 'select.' + pmeFilter);
  container.find('select.' + pmeFilter + ' option[value="*"]').remove();

  // Then the general stuff
  container.find('select.' + pmeFilter).chosen({
    width: '100%', // This needs margin:0 and box-sizing:border-box to be useful.
    inherit_select_classes: true,
    no_results_text: noRes,
    single_backstroke_delete: false,
  });

  const dblClickSel =
      'td.' + pmeFilter + ' ul.chosen-choices li.search-field input[type="text"]' + ','
      + 'td.' + pmeFilter + ' div.chosen-container' + ','
      + 'td.' + pmeFilter + ' input[type="text"]';
  container.off('dblclick', dblClickSel);
  container.on('dblclick', dblClickSel, function(event) {
    event.preventDefault();
    // There doesn't seem to be a "this" for dblclick, though
    // searching the web did not reveal similar problems. Doesn't
    // matter, we just trigger the click on the query-submit button
    // return pseudoSubmit(container.find('form.pme-form'), $(event.target), containerSel);
    container.find('td.' + pmeFilter + ' input.' + pmeToken('query')).trigger('click');
  });

  container.find('td.' + pmeFilter + ' div.chosen-container')
    .attr('title', PHPMyEdit.filterSelectChosenTitle);
};

function installInputSelectize(containerSel, onlyClass) {
  const pmeInput = pmeToken('input');

  const container = pmeContainer(containerSel);
  if (onlyClass === undefined) {
    onlyClass = 'selectize';
  }

  container.find('select.' + pmeInput + '.' + onlyClass).each(function(index) {
    const $self = $(this);
    const plugins = ['remove_button'];
    if ($self.hasClass('drag-drop')) {
      plugins.push('drag_drop');
    }
    $self.selectize({
      plugins,
      delimiter: ',',
      persist: false,
      openOnFocus: false,
      items: $self.data('initialValues'),
      // closeAfterSelect: true,
      create: function(input) {
        return {
          value: input,
          text: input,
        };
      },
    });
  });
}

const installInputChosen = function(containerSel, onlyClass) {

  if (!PHPMyEdit.selectChosen) {
    return;
  }

  const pmeInput = pmeToken('input');
  const pmeValue = pmeToken('value');

  const container = pmeContainer(containerSel);

  const noRes = PHPMyEdit.inputSelectNoResult;

  // Provide a data-placeholder and also remove the match-all
  // filter, which is not needed when using chosen.
  container.find('select.' + pmeInput).attr('data-placeholder', PHPMyEdit.inputSelectPlaceholder);
  container.off('change', 'select.' + pmeInput);
  //    container.find('select.' + pmeInput + ' option[value="*"]').remove();

  // Then the general stuff
  container.find('select.' + pmeInput).each(function(index) {
    const self = $(this);
    if (self.hasClass('no-chosen') || (onlyClass !== undefined && !self.hasClass(onlyClass))) {
      return;
    }
    console.debug('destroy chosen');
    self.chosen('destroy');
    const chosenOptions = {
      // width:'100%',
      inherit_select_classes: true,
      disable_search: self.hasClass('no-search'),
      disable_search_threshold: self.hasClass('no-search') ? 999999 : 10,
      no_results_text: noRes,
      allow_single_deselect: self.hasClass('allow-empty'),
      single_backstroke_delete: false,
    };
    if (self.hasClass('allow-empty')) {
      chosenOptions.width = (this.offsetWidth + PHPMyEdit.singleDeselectOffset) + 'px';
      if (!self.is(':visible')) {
        self.addClass('chosen-invisible'); // kludge, correct later
      } else {
        self.removeClass('chosen-invisible');
      }
    }
    if (self.hasClass('chosen-width-auto')) {
      chosenOptions.width = 'auto';
    }
    console.debug('add chosen');
    self.chosen(chosenOptions);
  });

  // Set title explicitly
  container.find('td.' + pmeInput + ' div.chosen-container, td.' + pmeValue + ' div.chosen-container')
    .not('[title][title!=""]')
    .each(function(index) {
      $(this).attr('title', PHPMyEdit.inputSelectChosenTitle);
    });

  installInputSelectize(containerSel);
};

const installTabHandler = function(containerSel, changeCallback) {

  const container = pmeContainer(containerSel);

  if (typeof changeCallback !== 'function') {
    changeCallback = function() {
      CAFEVDB.toolTipsInit(container);
    };
  }

  const tabsSelector = pmeClassSelector('li', 'navigation') + '.table-tabs';

  container
    .off('click', tabsSelector)
    .on('click', tabsSelector, function(event) {

      const form = container.find(pmeFormSelector());
      const table = form.find(pmeTableSelector());

      const oldTabClass = form.find('li.table-tabs.selected a').attr('href').substring(1);
      const tabClass = $(this).find('a').attr('href').substring(1);

      // Inject the display triggers ...
      table.removeClass(oldTabClass).addClass(tabClass);

      // Record the tab in the form data
      form.find('input[name="' + pmeSys('cur_tab') + '"]').val(tabClass.substring(4));

      // for styling and logic ...
      form.find(tabsSelector).removeClass('selected');
      $(this).addClass('selected');

      // account for unstyled chosen selected
      let reattachChosen = false;
      const pfx = (tabClass === 'tab-all') ? '' : 'td.' + tabClass;
      const selector = pmeClassSelectors(
        pfx + ' ' + 'div.chosen-container',
        ['input', 'filter', 'comp-filter']);
      form.find(selector).each(function(idx) {
        if ($(this).width() <= PHPMyEdit.singleDeselectOffset) {
          $(this).prev().chosen('destroy');
          reattachChosen = true;
        }
      });
      if (reattachChosen) {
        installFilterChosen(container);
        installInputChosen(container);
      }

      $.fn.cafevTooltip.remove();

      changeCallback();

      return false;
    });
};

const pmeOpenRowDialog = function(element, event, container) {

  console.info('CONTAINER', container);

  if (event.target !== element) {
    const target = $(event.target);
    // divs and spans which make it up to here will be ignored,
    // everything else results in the default action.
    if (target.is('.' + pmeToken('misc-check') + '.email')) {
      return;
    }
    if (target.is('.' + pmeToken('misc-check') + '.debit-note')) {
      return;
    }
    if (target.is('.' + pmeToken('misc-check') + '.bulkcommit')) {
      return;
    }
    if (target.is('.graphic-links')) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    if (target.hasClass('nav')) {
      return;
    }
    // if (!target.is('span') && !target.is('div')) {
    //   return;
    // }
  }

  // @todo needed?
  event.preventDefault();
  event.stopImmediatePropagation();

  let recordQuery = [];

  const $row = $(element).closest('tr.' + pmeToken('row'));
  const recordId = $row.data(pmePrefix + '_sys_rec');
  const recordKey = pmeSys('rec');
  if (typeof recordId === 'object' && recordId !== null) {
    for (const property in recordId) {
      recordQuery.push(recordKey + '[' + property + ']=' + recordId[property]);
    }
  } else {
    recordQuery.push(recordKey + '=' + recordId);
  }

  const groupByRecordId = $row.data(pmePrefix + '_sys_groupby_rec');
  const groupByRecordKey = pmeSys('groupby_rec');
  if (typeof groupByRecordId === 'object' && groupByRecordId !== null) {
    for (const property in groupByRecordId) {
      recordQuery.push(groupByRecordKey + '[' + property + ']=' + groupByRecordId[property]);
    }
  }

  recordQuery = recordQuery.join('&');

  // @TODO The following is a real ugly kludge
  // "element" does not necessarily has a form attribute
  const formSel = 'form.' + pmeToken('form');
  const form = container.find(formSel);
  let recordEl;
  if ($row.hasClass(pmeToken('change-enabled'))
      && (form.hasClass(pmeToken('direct-change')) || PHPMyEdit.directChange)) {
    recordEl = '<input type="hidden" class="' + pmeToken('change-navigation') + '"'
      + ' value="Change?' + recordQuery + '"'
      + ' name="' + pmeSys('operation') + '" />';
  } else if ($row.hasClass(pmeToken('view-enabled'))) {
    recordEl = '<input type="hidden" class="' + pmeToken('view-navigation') + '"'
      + ' value="View?' + recordQuery + '"'
      + ' name="' + pmeSys('operation') + '" />';
  }

  if (recordEl) {
    tableDialog(form, $(recordEl), container);
  }
};

const pmeInit = function(containerSel) {

  console.info('INIT SELECTOR', containerSel);

  containerSel = pmeSelector(containerSel);
  const container = pmeContainer(containerSel);
  console.debug('pmeInit(): container selector: ', containerSel);
  console.debug('pmeInit(): container found: ', container.length);

  const tableSel = 'table.' + pmeToken('main');
  const formSel = 'form.' + pmeToken('form');
  const form = container.find(formSel);
  const hiddenClass = pmeToken('hidden');
  const pmeFilter = pmeToken('filter');
  const pmeSearch = pmeToken('search');
  const pmeHide = pmeToken('hide');
  const pmeSort = pmeToken('sort');
  const pmeGoto = pmeToken('goto');
  const pmePageRows = pmeToken('pagerows');

  container.find('tr.' + pmeToken('navigation') + '.' + pmeToken('down')).find('select, select + .chosen-container').addClass('chosen-dropup');

  // Disable page-rows and goto submits, just not necessary
  container.find('input.' + pmePageRows).on('click', function(event) {
    event.stopImmediatePropagation();
    return false;
  });
  container.find('input.' + pmeGoto).on('click', function(event) {
    event.stopImmediatePropagation();
    return false;
  });

  // Hide search fields
  container.on('click', tableSel + ' input.' + pmeHide, function(event) {
    event.stopImmediatePropagation(); // don't submit, not necessary

    const table = container.find(tableSel);
    const form = container.find(formSel);

    $(this).addClass(hiddenClass);
    table.find('tr.' + pmeFilter).addClass(hiddenClass);
    table.find('input.' + pmeSearch).removeClass(hiddenClass);
    form.find('input[name="' + pmeSys('fl') + '"]').val(0);

    container.trigger('pmetable:layoutchange');

    return false;
  });

  // Show search fields
  container.on('click', tableSel + ' input.' + pmeSearch, function(event) {
    event.stopImmediatePropagation(); // don't submit, not necessary

    const table = container.find(tableSel);
    const form = container.find(formSel);

    $(this).addClass(hiddenClass);
    table.find('tr.' + pmeFilter).removeClass(hiddenClass);
    table.find('input.' + pmeHide).removeClass(hiddenClass);
    form.find('input[name="' + pmeSys('fl') + '"]').val(1);

    // maybe re-style chosen select-boxes
    let reattachChosen = false;
    const tabClass = form.find('input[name="' + pmeSys('cur_tab') + '"]').val();
    const pfx = 'tbody tr td' + (!tabClass || tabClass === 'all' ? '' : '.tab-' + tabClass);
    const selector = pmeClassSelectors(
      pfx + ' ' + 'div.chosen-container',
      ['filter', 'comp-filter']);
    table.find(selector).each(function(idx) {
      if ($(this).width() === 0 || $(this).width() === 60) {
        $(this).prev().chosen('destroy');
        reattachChosen = true;
      }
    });
    if (reattachChosen) {
      installFilterChosen(container);
    }

    container.trigger('pmetable:layoutchange');

    return false;
  });

  let onChangeSel =
      'input[type="checkbox"].' + pmeSort
      + ','
      + 'select.' + pmeGoto
      + ','
      + 'select.' + pmePageRows;
  if (!PHPMyEdit.selectChosen) {
    onChangeSel += ',' + 'select.' + pmeFilter;
  }
  container
    .off('change', onChangeSel)
    .on('change', onChangeSel, function(event) {
      return pseudoSubmit($(this.form), $(this), containerSel);
    });

  // view/change/copy/delete buttons lead to a a popup
  if (form.find('input[name="templateRenderer"]').length > 0) {
    const submitSel = formSel + ' input[class$="navigation"]:submit' + ','
        + formSel + ' input.' + pmeToken('add') + ':submit';
    container
      .off('click', submitSel)
      .on('click', submitSel, function(event) {
        const self = $(this);

        if (!self.hasClass(pmeToken('custom'))) {
          event.preventDefault();
          event.stopImmediatePropagation();

          tableDialog($(this.form), $(this), containerSel);
        }
      });

    // Trigger view or change "operation" when clicking on a data-row.
    console.info('GENERAL ROW DELEGATE');
    const rowSelector = formSel + ' td.' + pmeToken('cell') + ':not(.control)';
    container
      .off('click', rowSelector)
      .on('click', rowSelector, function(event) {
        pmeOpenRowDialog(this, event, container);
      });
  }

  // All remaining submit event result in a reload
  const submitSel = formSel + ' :submit';
  container
    .off('click', submitSel)
    .on('click', submitSel, function(event) {

      return pseudoSubmit($(this.form), $(this), containerSel);
    });

  installTabHandler(container);

  if (PHPMyEdit.selectChosen) {
    const gotoSelect = container.find('select.' + pmeGoto);
    gotoSelect.chosen({
      width: 'auto',
      inherit_select_classes: true,
      disable_search_threshold: 10,
    });
    if (gotoSelect.is(':disabled')) {
      // there is only one page
      gotoSelect.attr('data-placeholder', '1');
    } else {
      gotoSelect.attr('data-placeholder', ' ');
    }
    container.find('select.' + pmeGoto).trigger('chosen:updated');

    container.find('select.' + pmePageRows).chosen({
      width: 'auto',
      inherit_select_classes: true,
      disable_search: true,
    });
  }

  const keyPressSel = 'input.' + pmeFilter;
  container.off('keypress', keyPressSel);
  container.on('keypress', keyPressSel, function(event) {
    let pressedKey;
    if (event.which) {
      pressedKey = event.which;
    } else {
      pressedKey = event.keyCode;
    }
    if (pressedKey === 13) { // enter pressed
      return pseudoSubmit($(this.form), $(this), containerSel);
    }
    return true; // other key pressed
  });

  installFilterChosen(container);
  installInputChosen(container);

  /* The next two handlers allow the chosen-dropdown to extend the
   * current dialog. This can happen for small dialog windows and/or
   * if the select box is close to the bottom of the page.
   *
   */
  const tableContainerId = pmeIdSelector('table-container');

  container.on('chosen:before_showing_dropdown', tableContainerId + ' select', function(event) {
    if (!container.hasClass('ui-widget-content')) {
      return true;
    }
    if (container.hasVerticalScrollbar()) {
      return true;
    }
    const widget = container.cafevDialog('widget');
    const tableContainer = container.find(tableContainerId);
    if (widget.hasVerticalScrollbar() || tableContainer.hasVerticalScrollbar()) {
      return true;
    }
    widget.css('overflow', 'visible');
    container.css('overflow', 'visible');
    tableContainer.css('overflow', 'visible');
    return true;
  });

  // container.on('chosen:before_hiding_dropdown', tableContainerId + ' select', function(event) {
  //   return true;
  // });

  // container.on('chosen:showing_dropdown', tableContainerId + ' select', function(event) {
  //   console.info('chosen:showing_dropdown');
  //   return true;
  // });

  container.on('chosen:hiding_dropdown', tableContainerId + ' select', function(event) {
    if (!container.hasClass('ui-widget-content')) {
      return true;
    }
    if (container.hasVerticalScrollbar()) {
      return true;
    }
    const widget = container.cafevDialog('widget');
    const tableContainer = container.find(tableContainerId);
    if (widget.hasVerticalScrollbar() || tableContainer.hasVerticalScrollbar()) {
      return true;
    }
    tableContainer.css('overflow', '');
    container.css('overflow', '');
    widget.css('overflow', '');
    return true;
  });

  // Handle some special check-boxes disabling text-input fields
  container.on(
    'change', 'input[type="checkbox"].' + pmeToken('input-lock-empty'),
    function(event) {
      const $this = $(this);
      const checked = $this.prop('checked');
      const $input = $this.hasClass('left-lock') ? $this.next().next() : $this.prev();
      $input.prop('readonly', !checked);
      if (!checked) {
        $input.val('');
      }
      return false;
    });

  container.on(
    'change', 'input[type="checkbox"].' + pmeToken('input-lock-unlock'),
    function(event) {
      const $this = $(this);
      const checked = $this.prop('checked');
      const $input = $this.hasClass('left-lock') ? $this.next().next() : $this.prev();
      $input.prop('readonly', checked);
      if (checked) {
        $input.addClass('readonly');
      } else {
        $input.removeClass('readonly');
      }
      return false;
    });
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {
    transposeReady();
    pmeInit();
    clear(pmeOpenDialogs); // not cleared in init on purpose
  });
};

export {
  documentReady,
  pmeAddTableLoadCallback as addTableLoadCallback,
  pmeSelector as selector,
  pmeContainer as container,
  pmeDefaultSelector as defaultSelector,
  pmeDeferReload as deferReload,
  pmeFormSelector as formSelector,
  pmeClassSelector as classSelector,
  pmeSys as sys,
  pmeData as data,
  pmeIdSelector as idSelector,
  pmeTriggerSubmit as triggerSubmit,
  pmeTableDialogOpen as tableDialogOpen,
  pmeRec as rec,
  pmeSysNameSelector as sysNameSelector,
  pmeSubmitOuterForm as submitOuterForm,
  pmeClassSelectors as classSelectors,
  pmeOpenRowDialog as openRowDialog,
};

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
