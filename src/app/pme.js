/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * @file
 *
 * General PME table stuff, popup-handling.
 */

import jQuery from './jquery.js';
import * as PMEState from './pme-state.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as Notification from './notification.js';
import * as WysiwygEditor from './wysiwyg-editor.js';
import * as DialogUtils from './dialog-utils.js';
import generateUrl from './generate-url.js';
import modalizer from './modalizer.js';
import checkInvalidInputs from './check-invalid-inputs.js';
import { tweaks as pmeTweaks, unTweak as pmeUnTweak } from './pme-tweaks.js';
import clear from '../util/clear-object.js';
import pmeQueryLogMenu from './pme-querylog.js';
import {
  deselectAll as selectDeselectAll,
  widget as selectWidget,
  getControlObject as getSelectConstrolObject,
} from './select-utils.js';
import * as qs from 'qs';
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
  container as pmeContainer,
} from './pme-selectors.js';
import 'jquery-ui/ui/effects/effect-highlight';
import 'jquery-ui/ui/widgets/sortable';
import 'selectize';
import 'selectize/dist/css/selectize.bootstrap4.css';
import mergician from 'mergician';
// import 'selectize/dist/css/selectize.css';
require('cafevdb-selectize.scss');

require('pme-table.scss');

const $ = jQuery;

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
const pmePageRenderer = PMEState.pageRenderer;

/**
 * Generate the jQuery object corresponding to the inner container
 * of the ambient container. If the given argument is already a
 * jQuery object, then just return its first div child.
 *
 * @param {object|string} selector TBD.
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

  return callback.apply(context, args);
};

/**
 * Submit the base form in order to synchronize any changes caused
 * by the dialog form.
 *
 * @param {string} outerSelector The CSS selector identifying the form
 * to reload.
 *
 * @param {object} options Further options. Currently:
 * @param {boolean} options.keepLocked Do not destroy "locking" modal
 * planes.
 * @param {boolean} options.keepBusy Do not reset the busy indicators.
 */
const pmeSubmitOuterForm = function(outerSelector, options) {
  outerSelector = pmeSelector(outerSelector);
  options = $.extend({}, { keepLocked: false, keepBusy: false, discard: false }, options);

  // try a reload while saving data. The purpose is to resolve
  // inter-table dependencies like changed instrument lists and so
  // on. Be careful not to trigger top and bottom buttons.
  const $outerForm = $(outerSelector + ' ' + pmeFormSelector);
  $outerForm.data('submitOptions', options);

  const submitNamesApply = [
    'morechange',
    'applyadd',
    'applycopy',
  ];
  const submitNamesReload = [
    'reloadchange',
    'reloadview',
    'reloadlist',
  ];
  const submitNames = options.discard
    ? submitNamesReload
    : submitNamesApply.concat(submitNamesReload);

  const button = $outerForm.find(pmeSysNameSelectors('input', submitNames)).first();
  if (button.length > 0) {
    button.trigger('click');
  } else {
    // submit the outer form
    // $outerForm.submit();
    pseudoSubmit($outerForm, $(), outerSelector, 'pme');
  }
};

const deferKey = pmePrefix + '-submitdefer';
const cancellableKey = pmePrefix + '-cancellable';

const cancelDeferredReload = function(container) {
  container.data(deferKey, []);
};

/**
 * Create a jQuery Deferred object in order post-one form submission
 * until after validation of data, for example.
 *
 * @param {jQuery} container TBD.
 *
 * @returns {object}
 */
const pmeDeferReload = function(container) {
  const defer = $.Deferred();
  const promises = container.data(deferKey) || [];
  promises.push(defer.promise());
  container.data(deferKey, promises);
  return defer;
};

const reloadDeferred = function(container) {
  return $.when.apply($, container.data(deferKey));
};

const pmeCancelBeforeSubmit = function(container) {
  const cancellable = container.data(cancellableKey) || [];
  for (const job of cancellable) {
    console.info('TRY ABORT JOB', job);
    job.abort('cancelled');
  }
  container.data(cancellableKey, []);
};

const pmePushCancellable = function(container, promise) {
  const cancellable = container.data(cancellableKey) || [];
  cancellable.push(promise);
  container.data(cancellableKey, cancellable);
};

/**
 * Replace the content of the already opened dialog with the given
 * HTML-data.
 *
 * @param {object} container TBD.
 *
 * @param {string} content TBD.
 *
 * @param {object} options TBD.
 *
 * @param {Function} callback TBD.
 *
 * @param {object} triggerData Additional data passed to the calling
 * event handler after being triggered artifically.
 */
const tableDialogReplace = function(container, content, options, callback, triggerData) {

  const containerSel = '#' + options.dialogHolderCSSId;

  // remote data/time widgets and other stuff
  pmeUnTweak(container);
  // remove the WYSIWYG editor, if any is attached
  WysiwygEditor.removeEditor(container.find('textarea.wysiwyg-editor'));

  container.css('height', 'auto');
  $.fn.cafevTooltip.remove();
  container.off(); // remove ALL delegate handlers
  container.html(content);
  container.find('iframe').on('load', function(event) {
    const $this = $(this);
    const data = $this.data();
    data.cafevdbLoadEvent = (data.cafevdbLoadEvent || 0) + 1;
    console.info('IFRAME LOAD', $this.attr('class'), data.cafevdbLoadEvent);
  });

  tableDialogLoadIndicator(container, true);

  // general styling, avoid submit handlers by second argument
  pmeInit(containerSel, true);

  const title = container.find(pmeClassSelector('span', 'short-title')).html();
  if (title) {
    container.dialog('option', 'title', title);
  }

  // attach the WYSIWYG editor, if any
  // editors may cause additional resizing
  container.dialog('option', 'height', 'auto');
  // container.dialog('option', 'position', popupPosition);

  // re-attach events
  tableDialogHandlers(options, callback, triggerData);
};

const pmeHalt = function() {
  PHPMyEdit.stopped = true;
};

const pmeIsHalted = function() {
  return !!PHPMyEdit.stopped;
};

const pmePost = function(post) {
  if (pmeIsHalted()) {
    // just return a promise which is never resolved.
    console.info('PME is halted, returning never-resolved promise.');
    return $.Deferred().promise();
  }
  return $.post(generateUrl('page/pme/load'), post)
    .then(
      function(htmlContent, textStatus, request) {
        const historyAction = request.getResponseHeader('X-' + appName + '-history-action');
        return $.Deferred().resolve(htmlContent, historyAction, post).promise();
      },
      function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown);
        return $.Deferred().reject(xhr, status, errorThrown).promise();
      });
};

const blockTableDialog = function(dialogHolder) {
  const $dialogWidget = dialogHolder.dialog('widget');
  if (!dialogHolder.data('z-index')) {
    $dialogWidget.data('z-index', parseInt($dialogWidget.css('z-index')));
  }
  $dialogWidget.addClass(pmeToken('table-dialog-blocked'));
};

const unblockTableDialog = function(dialogHolder) {
  dialogHolder.dialog('widget').removeClass(pmeToken('table-dialog-blocked'));
  dialogHolder.removeData('z-index');
};

const lockTableDialog = (container, state) => (state ? blockTableDialog(container) : unblockTableDialog(container));

const tableDialogLoadIndicator = function(container, state) {
  let reloadButton = container.data('reloadButton');
  if (!reloadButton) {
    reloadButton = container.find(pmeNavigationSelector('reload'));
  }
  if (state) {
    reloadButton.addClass('loading');
  } else {
    reloadButton.removeClass('loading');
  }
};

/**
 * Reload the current PME-dialog.
 *
 * @param {object} options The current dialog options. In particular
 * options.reloadName and options.reloadValue must hold name and
 * value of the curent (pseudo-) submit input
 * element. options.modified must already be up-to-date.
 *
 * @param {Function} callback The application provided callback which is used
 * to shape the HTML after loading.
 *
 * @param {object} triggerData Additional data passed to the calling
 * event handler after being triggered artifically.
 */
const tableDialogReload = function(options, callback, triggerData) {

  const reloadName = options.reloadName;
  const reloadValue = options.reloadValue;

  const containerSel = '#' + options.dialogHolderCSSId;
  const container = $(containerSel);

  if (container.data(pmeToken('reloading'))) {
    return;
  }
  container.data(pmeToken('reloading'), true);
  container.removeData('reloadButton');

  blockTableDialog(container);
  tableDialogLoadIndicator(container, true);

  // Possibly delay reload until validation handlers have done their
  // work.
  reloadDeferred(container).then(function() {

    pmeCancelBeforeSubmit(container);

    let post = container.find(pmeFormSelector).serialize();

    // add the option values
    post += '&' + $.param(options);

    // add name and value of the "submit" button.
    post += '&' + $.param({ [reloadName]: reloadValue });

    pmePost(post)
      .fail(function(xhr, status, errorThrown) {
        Page.busyIcon(false);
        unblockTableDialog(container);
        tableDialogLoadIndicator(container, false);
        container.data(pmeToken('reloading'), false);
      })
      .done(function(htmlContent, historyAction, post) {
        tableDialogReplace(container, htmlContent, options, callback, triggerData);
        container.data(pmeToken('reloading'), false);
      });
  });
};

/**
 * Overload the PHPMyEdit submit buttons in order to be able to
 * display the single data-set display, edit, add and copy form in a
 * popup.
 *
 * @param {object} options Object with additional params to the
 * pme-table.php AJAX callback. Must at least contain the
 * templateRenderer component.
 *
 * @param {Function} changeCallback Handler to call after dialog open
 * and tab change.
 *
 * @param {object} triggerData Optional additonal data passed to an
 * articifically triggered calling event handler. Will be passed on to
 * the changeCallback.
 */
const tableDialogHandlers = function(options, changeCallback, triggerData) {

  if (typeof changeCallback === 'undefined') {
    changeCallback = function(options) { return false; };
  }

  const containerSel = '#' + options.dialogHolderCSSId;
  const container = $(containerSel);

  cancelDeferredReload(container);

  /* form.
   * pme-list
   * pme-change
   * pme-view
   * pme-delete
   * pme-copyadd
   * pme-query
   */

  if (container.find(pmeClassSelector('form', 'list')).length) {
    // main list view, just leave as is.
    const resize = function(reason) {
      changeCallback({ reason });
      const reloadSel = pmeClassSelectors('input', ['reload', 'query']);
      container.find(reloadSel)
        .off('click')
        .on('click', function(event, triggerData) {
          tableDialogReload(options, changeCallback, triggerData);
          return false;
        });
    };
    resize('dialogOpen');
    container.on('pmetable:layoutchange', function(event) {
      resize('layoutChange');
    });
    return;
  }

  container.on('pmetable:layoutchange', function(event) {
    changeCallback({ reason: 'layoutChange' });
  });

  installTabHandler(container, function() {
    changeCallback({ reason: 'tabChange' });
  });

  const reloadButtonSelector = pmeClassSelectors(
    'input',
    ['change', 'delete', 'copy', 'apply', 'more', 'reload']);
  const reloadingButton = container.find(reloadButtonSelector);

  const saveButtonSelector = 'input.' + pmeToken('save');
  const saveButton = container.find(saveButtonSelector);

  const cancelButton = container.find(pmeClassSelector('input', 'cancel'));

  const allButtons = $().add(reloadingButton).add(saveButton).add(cancelButton);

  // The easy one, but for changed content
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
      if (options.initialViewOperation && $(this).attr('name').indexOf('cancelview') < 0) {
        options.reloadName = options.initialName;
        options.reloadValue = options.initialValue;
        tableDialogReload(options, changeCallback, triggerData);
      } else {
        container.dialog('close');
      }

      return false;
    });

  // The complicated ones. This reloads new data.

  // install a delegate handler on the outer-most container which
  // finally will run after possible inner data-validation handlers
  // have been executed.
  // remove non-delegate handlers and stop default actions in any case.
  reloadingButton.off('click');
  container
    .off('click', reloadButtonSelector)
    .on(
      'click',
      reloadButtonSelector,
      function(event, triggerData) {

        const $submitButton = $(this);

        const reloadName = $submitButton.attr('name');
        const reloadValue = $submitButton.val();
        options.reloadName = reloadName;
        options.reloadValue = reloadValue;
        if (!$submitButton.hasClass(pmeToken('change'))
            && !$submitButton.hasClass(pmeToken('delete'))
            && !$submitButton.hasClass(pmeToken('copy'))
            && !$submitButton.hasClass(pmeToken('reload'))) {
          // so this is pme-more, morechange, apply

          allButtons.prop('disabled', true);
          const cleanup = () => {
            allButtons.prop('disabled', false);
          };

          if (!checkInvalidInputs(container, {
            cleanup,
            afterDialog($invalidInputs) {
              cleanup();
            },
            timeout: 10000, // animation timeout
          })) {
            return false;
          }
          cleanup();

          options.modified = true;
        } else if ($submitButton.hasClass(pmeToken('reload'))) {
          // this is essentially a cancel, so remove 'modified'
          options.modified = false;
        }
        tableDialogReload(options, changeCallback, triggerData);

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

  saveButton.off('click');
  container
    .off('click', saveButtonSelector)
    .on('click', saveButtonSelector, function(event, triggerData) {

      if (container.data(pmeToken('saving'))) {
        return false;
      }
      container.data(pmeToken('saving'), true);

      allButtons.prop('disabled', true);

      $.fn.cafevTooltip.remove();
      tableDialogLoadIndicator(container, true);
      Page.busyIcon(true);

      const cleanup = () => {
        tableDialogLoadIndicator(container, false);
        Page.busyIcon(false);
        allButtons.prop('disabled', false);
        container.data(pmeToken('saving'), false);
      };

      // Brief front-end-check for empty required fields.
      if (!checkInvalidInputs(container, {
        cleanup,
        afterDialog($invalidInputs) {
          cleanup();
        },
        timeout: 10000, // animation timeout
      })) {
        return false;
      }

      options.modified = true; // we are the save-button ...

      const applySelector = pmeSysNameSelectors(
        'input',
        ['morechange', 'applyadd', 'applycopy']);
      const deleteSelector = pmeSysNameSelector('input', 'savedelete');

      reloadDeferred(container).then(function() {

        pmeCancelBeforeSubmit(container);

        let post = container.find(pmeFormSelector).serialize();
        post += '&' + $.param(options);

        const deleteButton = container.find(deleteSelector);
        if (deleteButton.length > 0) {
          post += '&' + $.param(deleteButton);
          post += '&' + $.param({ [pmeSys('operation')]: 'Null' }); // end-point, don't ouptput
        } else {
          const applyButton = container.find(applySelector);
          if (applyButton.length > 0) {
            post += '&' + $.param(applyButton);
          }
        }

        blockTableDialog(container);

        // @todo Error handling is flaky
        pmePost(post)
          .fail(function(xhr, status, errorThrown) {
            unblockTableDialog(container);
            cleanup();
          })
          .done(function(htmlContent, historyAction, post) {
            const op = $(htmlContent).find(pmeSysNameSelector('input', 'op_name'));
            if (op.length > 0 && (op.val() === 'add' || op.val() === 'delete')) {
              // Some error occured. Stay in the given mode.

              Notification.show(t(appName, 'An error occurred.'
                                  + ' The data has not been saved.'
                                  + ' Unfortunately, no further information is available.'
                                  + ' Sorry for that.'), { timeout: 15 });
              tableDialogReplace(container, htmlContent, options, changeCallback);
              container.data(pmeToken('saving'), false);
              allButtons.prop('disabled', false);
              return;
            }

            // Final invocation of callback in order to give it a
            // chance to parse the HTML response if necessary.
            changeCallback({
              reason: 'dialogClose',
              htmlResponse: htmlContent,
              closedBy: saveButton.attr('name'),
              triggerData,
            });

            if (options.initialViewOperation && deleteButton.length <= 0) {
              // return to initial view, but not after deletion
              unblockTableDialog(container);
              options.reloadName = options.initialName;
              options.reloadValue = options.initialValue;
              tableDialogReload(options, changeCallback, triggerData);
            } else {
              if (container.hasClass('ui-dialog-content')) {
                container.dialog('close');
                if (!options.modified) {
                  // otherwise the close() method will reload the
                  // form which in turn will update the icon state.
                  Page.busyIcon(false);
                }
              } else {
                tableDialogLoadIndicator(container, false);
                Page.busyIcon(false);
              }
            }
            allButtons.prop('disabled', false);
            container.data(pmeToken('saving'), false);
          });
      });
      return false;
    });

  // Finally do the styling ...
  changeCallback({
    reason: 'dialogOpen',
    triggerData,
  });

  if (options.modified && options.ambientContainerSelector) {
    // might be costly?
    pmeSubmitOuterForm(options.ambientContainerSelector, {
      keepLocked: true,
      discard: options.reloadMode === 'discard',
    });
  }
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
 * @param {string} containerSel TBD.
 *
 * @returns {boolean}
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
    post += '&' + $.param({ [initialName]: initialValue });
  }
  if (element.hasClass(pmeToken('add'))) {
    // start with all tabs open in when adding data
    post += '&' + $.param({ [pmeSys('cur_tab')]: 'all' });
  }

  const cssClass = element.attr('class');
  if (cssClass) {
    viewOperation = cssClass.indexOf(pmeToken('view')) > -1;
  }

  let dialogCSSId = PHPMyEdit.dialogCSSId;
  containerSel = pmeSelector(containerSel);
  if (containerSel !== pmeDefaultSelector) {
    if (containerSel.charAt(0) === '#') {
      dialogCSSId = containerSel.substring(1) + '-' + dialogCSSId;
    } else {
      dialogCSSId = containerSel + '.' + dialogCSSId;
    }
  }

  const tableOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: dialogCSSId,
    templateRenderer,
    initialViewOperation: viewOperation,
    initialName,
    initialValue,
    reloadName: initialName,
    reloadValue: initialValue,
    modalDialog: true,
    modified: false, // avoid reload of base table unless necessary
  };
  pmeTableDialogOpen(tableOptions, post);
  return true;
};

/**
 * Open directly the popup holding the form data. We listen for the
 * custom event 'pmedialog:changed' on the dialogHolder. This event will
 * be forwarded to the ambientContainer. The idea is that we can
 * update the "modified" component of chained dialogs in a reliable
 * way.
 *
 * @param {object} tableOptions Option array, see above
 *
 * @param {string} post Additional query parameters. In principle it
 * is also possible to store all values in tableOptions, as this is
 * added to the query-string in any case.
 *
 * @returns {boolean}
 */
const pmeTableDialogOpen = function(tableOptions, post) {

  const containerCSSId = tableOptions.dialogHolderCSSId;

  const template = Page.templateFromRenderer(
    tableOptions.templateRenderer);

  if (pmeOpenDialogs[containerCSSId]) {
    return false;
  }
  pmeOpenDialogs[containerCSSId] = true;

  Page.busyIcon(true);

  if (typeof tableOptions.modalDialog === 'undefined') {
    tableOptions.modalDialog = true;
  }
  if (typeof post === 'undefined') {
    post = $.param(tableOptions);
  } else {
    post += '&' + $.param(tableOptions);
  }
  if (!tableOptions[tableOptions.initialName]) {
    post += '&' + $.param({ [tableOptions.initialName]: tableOptions.initialValue });
  }

  pmePost(post)
    .fail(function(xhr, status, errorThrown) {
      Page.busyIcon(false);
      pmeOpenDialogs[containerCSSId] = false;
    })
    .done(function(htmlContent, historyAction, post) {
      const containerSel = '#' + containerCSSId;
      const dialogHolder = $('<div id="' + containerCSSId + '" class="' + containerCSSId + ' resize-target"></div>');
      dialogHolder.html(htmlContent);
      dialogHolder.find('iframe').on('load', function(event) {
        const $this = $(this);
        const data = $this.data();
        data.cafevdbLoadEvent = (data.cafevdbLoadEvent || 0) + 1;
        console.info('IFRAME LOAD', $this.attr('class'), data.cafevdbLoadEvent);
      });

      dialogHolder.data('ambientContainer', tableOptions.ambientContainerSelector);

      tableDialogLoadIndicator(dialogHolder, true);

      if (tableOptions.modalDialog) {
        modalizer(true);
      }
      dialogHolder.cafevDialog({
        title: dialogHolder.find(pmeClassSelector('span', 'short-title')).html(),
        position: popupPosition,
        width: 'auto',
        height: 'auto',
        modal: false, // tableOptions.modalDialog,
        closeOnEscape: false,
        dialogClass: pmeToken('table-dialog') + ' custom-close resize-target ' + template,
        resizable: false,
        dragStart(event) {
          const self = $(this);
          const widget = self.dialog('widget');
          const cssWidth = widget.prop('style').width;
          if (cssWidth === 'auto') {
            self.data('drag-width-tweak', true);
            widget.width(widget.width() + 1); // cope with jquery-ui + ff drag bug
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

          blockTableDialog(dialogHolder);

          const $staticReloadRequest = dialogHolder.find('input[name="' + pmeSys('reloadOuterForm') + '"]');
          console.info('STATIC RELOAD REQUEST', $staticReloadRequest);
          if ($staticReloadRequest.val()) {
            // reload outer form
            $(tableOptions.ambientContainerSelector).trigger('pmedialog:changed');
            pmeSubmitOuterForm(tableOptions.ambientContainerSelector);
            $staticReloadRequest.val('');
          }

          // general styling, avoid :submit handlers in dialog mode
          pmeInit(containerSel, true);

          const resizeHandler = function(parameters) {
            dialogHolder.dialog('option', 'height', 'auto');
            dialogHolder.dialog('option', 'width', 'auto');
            let newHeight = dialogWidget.height()
                - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
            newHeight -= dialogHolder.outerHeight(true) - dialogHolder.height();
            dialogHolder.height(newHeight);
            const form = dialogHolder.find('form.pme-form')[0];
            const html = $('html')[0];
            const dialog = dialogWidget[0];
            const scrollDelta = form.scrollWidth - form.clientWidth;
            if (scrollDelta > 0 && dialog.offsetWidth + scrollDelta < html.clientWidth) {
              console.debug('Compensating dialog width for pme-form vertical scrollbar');
              dialogWidget.css('width', (dialog.offsetWidth + scrollDelta) + 'px');
            }
          };

          tableDialogHandlers(tableOptions, function(parameters) {
            const defaultParameters = {
              reason: 'unknown',
              triggerData: {
                postOpen(dialogDiv) {
                  dialogDiv.dialog('moveToTop');
                },
              },
              tableOptions,
            };
            parameters = $.extend({}, defaultParameters, parameters);
            if (parameters.reason === 'unknown') {
              console.trace();
            }
            dialogHolder.css('height', 'auto');
            switch (parameters.reason) {
            case 'dialogClose':
              tableLoadCallback(template, containerSel, parameters, function(arg) {});
              break;
            case 'dialogOpen':
              WysiwygEditor.addEditor(dialogHolder.find('textarea.wysiwyg-editor:enabled'), function() {
                transposeReady(containerSel);
                pmeQueryLogMenu(containerSel);
                tableLoadCallback(template, containerSel, parameters, function(arg) {
                  const keepLocked = arg === true;
                  // console.trace();
                  // installInputChosen(containerSel);
                  resizeHandler(parameters);
                  parameters.triggerData.postOpen(dialogHolder);
                  CAFEVDB.toolTipsInit(containerSel);
                  if (!keepLocked) {
                    unblockTableDialog(dialogHolder);
                    Page.busyIcon(false);
                    tableDialogLoadIndicator(dialogHolder, false);
                  }
                });
                pmeTweaks(dialogHolder);
                $.fn.cafevTooltip.remove();
              });
              break;
            case 'tabChange':
              installInputChosen(containerSel, 'chosen-invisible');
              resizeHandler(parameters);
              break;
            case 'layoutChange':
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

          // remove data/time widgets and other stuff
          pmeUnTweak(dialogHolder);
          // remove the WYSIWYG editor, if any is attached
          WysiwygEditor.removeEditor(dialogHolder.find('textarea.wysiwyg-editor'));

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

          pmeOpenDialogs[containerCSSId] = false;

          CAFEVDB.unfocus();

          if (!tableOptions.modified) {
            // Remove modal plane if appropriate
            modalizer(false);
          }

          Notification.hide();

          return false;
        },
      });
    });
  return true;
};

/**
 * Quasi-submit the pme-form, returns the promise generated by the
 * pmePost().
 *
 * @param {jQuery} form The jQuery object corresponding to the pme-form.
 *
 * @param {jQuery} element The jQuery object corresponding to the element
 * causing the submit.
 *
 * @param {boolean} resetFilter Bool, post a sw=Clear string in addition,
 * causing PHPMyEdit to reset the filter.
 *
 * @returns {Promise}
 */
const pseudoSubmitPost = function(form, element, resetFilter) {

  if (resetFilter === true) {
    form.append('<input type="hidden"'
                + ' name="' + pmeSys('sw') + '"'
                + ' value="Clear"/>');
  }

  let post = form.serialize();

  // @todo: should not this be included in serialize() automatically?
  const templateRenderer = form.find('input[name="templateRenderer"]').val();
  post += '&' + $.param({ templateRenderer });
  if (element.attr('name')
      && (!element.is(':checkbox') || element.is(':checked'))) {
    post += '&' + $.param(element);
  }

  return pmePost(post);
};

/**
 * Quasi-submit the pme-form.
 *
 * @param {jQuery} form The jQuery object corresponding to the pme-form.
 *
 * @param {jQuery} element The jQuery object corresponding to the element
 * causing the submit.
 *
 * @param {string} selector The CSS selector corresponding to the
 * surrounding container (div element)
 *
 * @param {boolean} resetFilter Bool, post a sw=Clear string in addition,
 * causing PHPMyEdit to reset the filter.
 *
 * @returns {boolean}
 */
const pseudoSubmit = function(form, element, selector, resetFilter) {

  const submitOptions = form.data('submitOptions') || {};

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
    for (const [name, value] of Object.entries(submitOptions)) {
      form.append(
        '<input type="hidden" '
          + 'name="' + name + '" '
          + 'value="' + value + '"/>');
    }
    form.submit();
    return false;
  }

  if (!submitOptions.keepBusy) {
    Page.busyIcon(true);
  }
  if (!submitOptions.keepLocked) {
    modalizer(true);
  }

  templateRenderer = templateRenderer.val();
  const template = Page.templateFromRenderer(templateRenderer);

  pseudoSubmitPost(form, element, resetFilter)
    .fail(function(xhr, status, errorThrown) {
      Page.busyIcon(false);
      modalizer(false);
    })
    .done(function(htmlContent, historyAction, post) {

      if (historyAction === 'push') {
        Page.pushHistory(qs.parse(post, { allowSparse: true }));
      } else {
        Page.replaceHistory(qs.parse(post, { allowSparse: true }));
      }
      Page.updateHistoryControls();

      $.fn.cafevTooltip.remove();

      pmeUnTweak(container);
      WysiwygEditor.removeEditor(container.find('textarea.wysiwyg-editor'));
      pmeInner(container).html(htmlContent);

      container.find('iframe').on('load', function(event) {
        const $this = $(this);
        const data = $this.data();
        data.cafevdbLoadEvent = (data.cafevdbLoadEvent || 0) + 1;
        console.info('IFRAME LOAD', $this.attr('class'), data.cafevdbLoadEvent);
      });

      pmeInit(selector);
      WysiwygEditor.addEditor(container.find('textarea.wysiwyg-editor'), function() {
        transposeReady(selector);
        pmeQueryLogMenu(selector);
        tableLoadCallback(template, selector, { reason: 'formSubmit' }, function() {});
        pmeTweaks(container);
        CAFEVDB.toolTipsInit(selector);

        // kill the busy indicators and modalizer if appropriate
        if (!submitOptions.keepBusy) {
          Page.busyIcon(false);
        }
        if (!submitOptions.keepLocked) {
          modalizer(false);
        }
        CAFEVDB.unfocus(); // move focus away from submit button

        container.trigger('pmetable:layoutchange');
      });
    });
  return false;
};

/**
 * Trigger either one of the upper or the lower button controls (but
 * not both!)
 *
 * @param {string} buttonName TBD.
 *
 * @param {string} containerSel TBD.
 *
 * @returns {boolean}
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
 * @param {string} selector TBD.
 *
 * @param {string} containerSel TBD.
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
 * @param {boolean} transpose TBD.
 *
 * @param {string} containerSel TBD.
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
    transposeMainTable(pmeTableSelector, container);
    pageitems = t('cafevdb', '#columns');

    container.find('input[name="Transpose"]').val('transposed');
    container.find(trUp).removeClass(unTrClass).addClass(trClass);
    container.find(trDown).removeClass(unTrClass).addClass(trClass);
    container.find(tr).removeClass(unTrClass).addClass(trClass);
  } else {
    tooltip.remove();
    transposeMainTable(pmeTableSelector, container);
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
 * @param {string} containerSel TBD.
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
  const pmeCompFilter = pmeToken('filter-comp');

  const container = pmeContainer(containerSel);

  const noRes = PHPMyEdit.filterSelectNoResult;

  container.find('select.' + pmeCompFilter).chosen({
    width: 'auto',
    inherit_select_classes: true,
    title_attributes: ['title', 'data-original-title', 'data-cafevdb-title'],
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
    title_attributes: ['title', 'data-original-title', 'data-cafevdb-title'],
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

  container.find('td.' + pmeFilter + ' div.chosen-container').each(function() {
    const $chosen = $(this);
    const selectTitle = $chosen.prev('select').attr('title');
    $chosen.attr('title', selectTitle || PHPMyEdit.filterSelectChosenTitle);
  });
};

const removeButtonPlugin = {
  // eslint-disable-next-line camelcase
  remove_button: {
    title: t(appName, 'Remove'),
  },
};

const clearButtonPlugin = {
  // eslint-disable-next-line camelcase
  clear_button: {
    title: t(appName, 'Clear'),
  },
};

/**
 * Internal helper function.
 *
 * @param {string} containerSel TBD.
 *
 * @param {boolean} onlyClass TBD.
 */
function installInputSelectize(containerSel, onlyClass) {
  const pmeInput = pmeToken('input');

  const container = pmeContainer(containerSel);
  if (onlyClass === undefined) {
    onlyClass = 'selectize';
  }

  container.find('select.' + pmeInput + '.' + onlyClass).each(function(index) {
    const $self = $(this);
    const selectizeOptions = mergician({ appendArrays: true, dedupArrays: true })(
      {
        plugins: $self.prop('multiple') ? removeButtonPlugin : clearButtonPlugin,
        delimiter: ',',
        persist: false,
        hideSelected: false,
        openOnFocus: false,
        items: $self.data('initialValues'),
        // closeAfterSelect: true,
        create: false,
        inputClass: 'pme-selectize-input',
      },
      $self.data('selectizeOptions') || {}
    );
    if (selectizeOptions.create && selectizeOptions.create !== true) {
      const create = selectizeOptions.create;
      const inputField = create.inputField || 'input';
      const valueField = selectizeOptions.valueField || 'value';
      const labelField = selectizeOptions.labelField || 'text';
      if (create.url) {
        selectizeOptions.create = function(input, setterCallback) {
          $.post(generateUrl(create.url), {
            ...(create.post || {}),
            [inputField]: input,
          })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
              setterCallback(false);
            })
            .done(function(data) {
              if (!data || !data[valueField] || !data[labelField]) {
                setterCallback(false);
              }
              setterCallback(data);
            });
        };
      } else {
        selectizeOptions.create = function(input) { return { [valueField]: input, [labelField]: input }; };
      }
    }
    // console.info('SELECTIZE OPTIONS', { ...selectizeOptions });
    $self.selectize(selectizeOptions);
    const selectizeInstance = getSelectConstrolObject($self);
    selectizeInstance.$control_input.removeAttr('autofill');
    const $selectWidget = selectWidget($self);
    const toolTip = $self.attr('title') || $self.attr('data-original-title');
    if (toolTip) {
      $selectWidget.attr('title', toolTip).addClass('tooltip-auto').cafevTooltip();
    }
    selectizeInstance.off('before_dropdown_open');
    selectizeInstance.on('before_dropdown_open', function(event) {
      ensureDropdownVisibility(container);
      $.fn.cafevTooltip.remove();
    });
    selectizeInstance.off('dropdown_close');
    selectizeInstance.on('dropdown_close', function(event) {
      resetDropdownVisibility(container);
      $.fn.cafevTooltip.remove();
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
  container.find('select.' + pmeInput).each(function() {
    const $select = $(this);
    if (!$select.attr('data-placeholder')) {
      $select.attr('data-placeholder', PHPMyEdit.inputSelectPlaceholder);
    }
  });

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
      title_attributes: ['title', 'data-original-title', 'data-cafevdb-title'],
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
    .filter('[title=""],[title^="***DEBUG***"]')
    .each(function(index) {
      $(this).attr('title', PHPMyEdit.inputSelectChosenTitle);
    });

  installInputSelectize(containerSel);
};

const installTabHandler = function(containerSel, changeCallback) {

  const container = pmeContainer(containerSel);

  if (typeof changeCallback !== 'function') {
    changeCallback = function() {
      // CAFEVDB.toolTipsInit(container); THIS SHOULD NOT BE NEEDED?
    };
  }

  const tabsSelector = pmeClassSelector('li', 'navigation') + '.table-tabs';
  const form = container.find(pmeFormSelector);
  const table = form.find(pmeTableSelector);

  const $tabAnchor = form.find('li.table-tabs.selected a');
  const tabClasses = ['tab-' + $tabAnchor.data('tabIndex'), 'tab-' + $tabAnchor.data('tabId')];

  const updateTabReadOnlyFields = function(tabClasses) {
    const readWriteClasses = tabClasses.map(tabClass => tabClass + '-readwrite');
    form
      .find('td.' + pmeToken('value') + '.default-readonly').each(function() {
        const $td = $(this);
        const readWrite = readWriteClasses.some((cssClass) => $td.hasClass(cssClass));
        $td.find('label, input').prop('readonly', !readWrite);
        $td.find('input[type="checkbox"]').prop('disabled', !readWrite);
        if (readWrite) {
          // let the handler to its logic
          $td.find('input.' + pmeToken('input-lock')).trigger('change');
        }
      });
  };

  updateTabReadOnlyFields(tabClasses);

  container
    .off('click', tabsSelector)
    .on('click', tabsSelector, function(event) {
      const $this = $(this);

      // console.info('FORM', form.scrollLeft());
      form.scrollLeft(0);

      const $oldTabAnchor = form.find('li.table-tabs.selected a');
      const oldTabClasses = ['tab-' + $oldTabAnchor.data('tabIndex'), 'tab-' + $oldTabAnchor.data('tabId')];
      const $tabAnchor = $this.find('a');
      const tabClasses = ['tab-' + $tabAnchor.data('tabIndex'), 'tab-' + $tabAnchor.data('tabId')];

      // Inject the display triggers ...
      table.removeClass(oldTabClasses).addClass(tabClasses);

      // Record the tab in the form data
      form.find('input[name="' + pmeSys('cur_tab') + '"]').val($tabAnchor.data('tabIndex'));

      // for styling and logic ...
      form.find(tabsSelector).removeClass('selected');
      $this.addClass('selected');

      updateTabReadOnlyFields(tabClasses);

      // account for unstyled chosen selected
      let reattachChosen = false;
      const pfx = (tabClasses.includes('tab-all')) ? '' : 'td.' + tabClasses.join('.');
      const selector = pmeClassSelectors(
        pfx + ' ' + 'div.chosen-container',
        ['input', 'filter', 'filter-comp']);
      form.find(selector).each(function(idx) {
        const $this = $(this);
        if ($this.width() <= PHPMyEdit.singleDeselectOffset) {
          $this.prev().chosen('destroy');
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

/**
 * Fire a custom context menu event with the database key data if
 * right-clicking on a row.
 *
 * @param {jQuery} element The tr element of the list-view.
 *
 * @param {object} event The event which triggered the handler.
 *
 * @param {jQuery} container The form or div containing the form.
 */
const pmeContextMenu = function(element, event, container) {

  const $row = $(element).closest('tr.' + pmeToken('row'));
  const recordId = $row.data(pmePrefix + '_sys_rec');
  const groupByRecordId = $row.data(pmePrefix + '_sys_groupby_rec');

  const databaseRecords = {
    recordId,
    groupByRecordId,
  };

  console.info('CONTEXT DATA', databaseRecords, element, event, container);

  $row.trigger('pme:contextmenu', [event, databaseRecords]);
};

/**
 * Open the view or modification dialog for the data-set of the
 * respective row after clicking on the row.
 *
 * @param {jQuery} element The tr element of the list-view.
 *
 * @param {object} event The event which triggered the handler.
 *
 * @param {jQuery} container The form or div containing the form.
 */
const pmeOpenRowDialog = function(element, event, container) {

  if (event.target !== element) {
    const target = $(event.target);

    // skip active elements, they probably want to do their own stuff
    if (target.is('a') || target.is('input') || target.is('button')) {
      return;
    }

    if (target.is(['', pmeToken('misc'), pmeToken('check'), 'email'].join('.'))) {
      return;
    }
    if (target.is(['', pmeToken('misc'), pmeToken('check'), 'debit-note'].join('.'))) {
      return;
    }
    if (target.is(['', pmeToken('misc'), pmeToken('check'), 'bulkcommit'].join('.'))) {
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
    recordQuery.push(recordKey + '=' + encodeURIComponent(JSON.stringify(recordId)));
  } else {
    console.error('SCALAR RECORD ID', recordKey, recordId);
    console.trace('SCALAR RECORD ID');
    recordQuery.push(recordKey + '=' + recordId);
  }

  const groupByRecordId = $row.data(pmePrefix + '_sys_groupby_rec');
  const groupByRecordKey = pmeSys('groupby_rec');
  if (typeof groupByRecordId === 'object' && groupByRecordId !== null) {
    recordQuery.push(groupByRecordKey + '=' + encodeURIComponent(JSON.stringify(groupByRecordId)));
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

const tableContainerId = pmeIdSelector('table-container');
const dropdownSavedOverflow = 'dropdownSavedOverflow';

/**
 * Set the overflow of dialogs to 'visible' if they do not have
 * vertical scrollbars. This is used as callback for drop-down "open"
 * events in order to ensure the visibility of the drop-down menus if
 * the ambient dialog is too small.
 *
 * @param {jQuery} $container TBD.
 */
const ensureDropdownVisibility = function($container) {
  if (!$container.hasClass('ui-widget-content')) {
    return;
  }
  if ($container.hasVerticalScrollbar()) {
    return;
  }
  const $widget = $container.cafevDialog('widget');
  const $tableContainer = $container.find(tableContainerId);
  if ($widget.hasVerticalScrollbar() || $tableContainer.hasVerticalScrollbar()) {
    return;
  }
  const elements = [$container, $widget, $tableContainer];
  for (const $element of elements) {
    $element.data(dropdownSavedOverflow, $element[0].style.overflow || '');
    $element.css('overflow', 'visible');
  }
};

/**
 * Reset the CSS overflow property of dialogs to empty if they do not
 * have vertical scrollbars. This is used as callback for drop-down
 * "close" events in order to reset the visibility of the drop-down
 * menus when they are closing.
 *
 * @param {jQuery} $container TBD.
 */
const resetDropdownVisibility = function($container) {
  const elements = [
    $container,
    $container.cafevDialog('widget'),
    $container.find(tableContainerId),
  ];
  for (const $element of elements) {
    const savedOverflow = $element.data(dropdownSavedOverflow);
    if (savedOverflow !== undefined) {
      $element.css('overflow', savedOverflow);
      $element.removeData(dropdownSavedOverflow);
    }
  }
};

/**
 * @param {object} containerSel Selector of jQuery element of the
 * container around the form.
 *
 * @param {boolean} noSubmitHandlers Do not attach any handlers to the
 * submit buttons. This is used by the popup-dialogs which install
 * their own handlers.
 */
const pmeInit = function(containerSel, noSubmitHandlers) {

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
  const pmeGoto = pmeToken('goto');
  const pmePageRows = pmeToken('pagerows');

  noSubmitHandlers = noSubmitHandlers || false;

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

    const $table = container.find(tableSel);
    const $form = container.find(formSel);

    $(this).addClass(hiddenClass);

    $table.addClass(pmeFilter + '-hidden').removeClass(pmeFilter + '-visible');
    $table.find('tr.' + pmeFilter).addClass(hiddenClass);
    $table.find('input.' + pmeSearch).removeClass(hiddenClass);
    $form.find('input[name="' + pmeSys('fl') + '"]').val(0);

    container.trigger('pmetable:layoutchange');

    return false;
  });

  // Show search fields
  container.on('click', tableSel + ' input.' + pmeSearch, function(event) {
    event.stopImmediatePropagation(); // don't submit, not necessary

    const $table = container.find(tableSel);
    const $form = container.find(formSel);

    $(this).addClass(hiddenClass);

    $table.removeClass(pmeFilter + '-hidden').addClass(pmeFilter + '-visible');
    $table.find('tr.' + pmeFilter).removeClass(hiddenClass);
    $table.find('input.' + pmeHide).removeClass(hiddenClass);
    $form.find('input[name="' + pmeSys('fl') + '"]').val(1);

    // maybe re-style chosen select-boxes
    let reattachChosen = false;
    const tabClass = form.find('input[name="' + pmeSys('cur_tab') + '"]').val();
    const pfx = 'tbody tr td' + (!tabClass || tabClass === 'all' ? '' : '.tab-' + tabClass);
    const selector = pmeClassSelectors(
      pfx + ' ' + 'div.chosen-container',
      ['filter', 'filter-comp']);
    $table.find(selector).each(function(idx) {
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

  let onChangeSel = 'select.' + pmeGoto + ',' + 'select.' + pmePageRows;
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
    const rowSelector = formSel + ' tr:not(.disable-row-click)' + ' td.' + pmeToken('cell') + ':not(.control)';
    container
      .off('click', rowSelector)
      .on('click', rowSelector, function(event) {
        pmeOpenRowDialog(this, event, container);
      });
  }

  const contextMenuRowSelector = formSel + ' tr.' + pmeToken('row');
  container
    .off('contextmenu', contextMenuRowSelector)
    .on('contextmenu', contextMenuRowSelector, function(event) {
      if (event.ctrlKey) {
        return; // let the user see the normal context menu
      }
      pmeContextMenu(this, event, container);
    });

  if (!noSubmitHandlers) {
    // All remaining submit event result in a reload
    const submitSel = formSel + ' :submit:not(.action-menu-toggle)';
    container
      .off('click', submitSel)
      .on('click', submitSel, function(event) {
        return pseudoSubmit($(this.form), $(this), containerSel);
      });
  }

  installTabHandler(container);

  if (PHPMyEdit.selectChosen) {
    const gotoSelect = container.find('select.' + pmeGoto);
    gotoSelect.chosen({
      width: 'auto',
      inherit_select_classes: true,
      title_attributes: ['title', 'data-original-title', 'data-cafevdb-title'],
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
      title_attributes: ['title', 'data-original-title', 'data-cafevdb-title'],
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

  // @todo: the same for selectize
  container.on('chosen:before_showing_dropdown', tableContainerId + ' select', function(event) {
    ensureDropdownVisibility(container);
  });

  // container.on('chosen:before_hiding_dropdown', tableContainerId + ' select', function(event) {
  //   return true;
  // });

  // container.on('chosen:showing_dropdown', tableContainerId + ' select', function(event) {
  //   console.info('chosen:showing_dropdown');
  //   return true;
  // });

  container.on('chosen:hiding_dropdown', tableContainerId + ' select', function(event) {
    resetDropdownVisibility(container);
  });

  // Handle some special check-boxes disabling text-input fields
  container.on(
    'change', 'input[type="checkbox"].' + pmeToken('input-lock') + '.lock-empty',
    function(event) {
      const $this = $(this);
      const locked = !$this.prop('checked');
      const $input = $this.hasClass('left-of-input') ? $this.next().next() : $this.prev();
      $input.prop('readonly', locked);
      if (locked) {
        $input.val('');
        $input.attr('placeholder', $input.data('lockedPlaceholder'));
      } else {
        $input.attr('placeholder', $input.data('unlockedPlaceholder'));
      }
      return false;
    });

  container.on(
    'change', 'input[type="checkbox"].' + pmeToken('input-lock') + '.lock-unlock',
    function(event) {
      const $this = $(this);
      const checked = $this.prop('checked');
      const $input = $this.hasClass('left-of-input') ? $this.next().next() : $this.prev();
      $input.prop('readonly', checked);
      if (checked) {
        $input.addClass('readonly');
      } else {
        $input.removeClass('readonly');
      }
      return false;
    });

  container.on(
    'change click', 'td.' + pmeToken('value') + ' input.clear-field',
    function(event) {
      const $this = $(this);
      const $element = $this.parent().find('.' + pmeToken('input')).first();
      if ($element.is('select')) {
        selectDeselectAll($element);
      } else if ($element.is('input')) {
        $element.val('');
      }
      return false;
    });
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {
    transposeReady();
    pmeQueryLogMenu();
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
  lockTableDialog as tableDialogLock,
  tableDialogLoadIndicator,
  pmeSysNameSelector as sysNameSelector,
  pmeSubmitOuterForm as submitOuterForm,
  pmeClassSelectors as classSelectors,
  pmeOpenRowDialog as openRowDialog,
  pmePushCancellable as pushCancellable,
  pmeHalt as halt,
  pmeIsHalted as halted,
  pmePageRenderer as pageRenderer,
};

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
