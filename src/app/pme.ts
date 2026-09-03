/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { MountableComponent } from '../services/mountable-components.ts';
import type {
  PageTemplateValue,
  TableDialogCallbackData,
  TableDialogOptions,
  TriggerData,
} from './pme-state.ts';
import type { TemplateRenderer } from './template-renderer.ts';

import { translate as t } from '@nextcloud/l10n';
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
  awaitEmit,
} from '@rotdrop/async-nextcloud-event-bus';
import { mergician } from 'mergician';
import { parse as qsParse } from 'qs';
import { RESIZE_TARGET, WYSIWYG_EDITOR } from '../../build/ts-types/php-modules/Controller/CssClasses.ts';
import { END_POINT as controllerEndPoint } from '../../build/ts-types/php-modules/Controller/PmeTableController.ts';
import { ALLOW_EMPTY, DIRECT_CHANGE } from '../../build/ts-types/php-modules/PageRenderer/CssClasses.ts';
import { DATA_PME_INITIAL_VALUES } from '../../build/ts-types/php-modules/PageRenderer/DataConstants.ts';
import { appName } from '../config.ts';
import {
  LEGACY_HISTORY_PATCH,
  LEGACY_HISTORY_UPDATE,
  LEGACY_PAGE_CLEANUP,
  LEGACY_SANITIZE_POST_DATA,
} from '../event-bus-events.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import clear from '../util/clear-object.ts';
import { close as closeActionMenus } from './action-menu.ts';
import * as Ajax from './ajax.ts';
import pageBusyIcon from './busy-icon.ts';
import * as CAFEVDB from './cafevdb.ts';
import checkInvalidInputs from './check-invalid-inputs.ts';
import * as DialogUtils from './dialog-utils.ts';
import $, { isJQuerySelect, jq } from './jquery.ts';
import modalizer from './modalizer.ts';
import * as Notification from './notification.ts';
import pmeQueryLogMenu from './pme-querylog.ts';
import {
  classSelector as pmeClassSelector,
  classSelectors as pmeClassSelectors,
  container as pmeContainer,
  data as pmeData,
  formEditSuffixes as pmeFormEditSuffixes,
  formSelector as pmeFormSelector,
  idSelector as pmeIdSelector,
  inputClassSelector as pmeInputClassSelector,
  inputSelector as pmeInputSelector,
  navigationSelector as pmeNavigationSelector,
  selectInputSelector as pmeSelectInputSelector,
  selector as pmeSelector,
  sys as pmeSys,
  sysNameSelector as pmeSysNameSelector,
  sysNameSelectors as pmeSysNameSelectors,
  tableSelector as pmeTableSelector,
  textareaInputSelector as pmeTextareaInputSelector,
  token as pmeToken,
  valueSelector as pmeValueSelector,
} from './pme-selectors.ts';
import {
  PHPMyEdit,
  defaultSelector as pmeDefaultSelector,
  dialogCSSId as pmeDialogCSSId,
  openDialogs as pmeOpenDialogs,
  prefix as pmePrefix,
} from './pme-state.ts';
import { tweaks as pmeTweaks, unTweak as pmeUnTweak } from './pme-tweaks.ts';
import {
  getControlObject as getSelectConstrolObject,
  refreshWidget as refreshSelectWidget,
  deselectAll as selectDeselectAll,
  widget as selectWidget,
} from './select-utils.ts';
import { templateFromRenderer } from './template-renderer.ts';
import * as WysiwygEditor from './wysiwyg-editor.ts';

import 'selectize/dist/css/selectize.bootstrap.css';
import 'jquery-ui/ui/effects/effect-highlight.js';
import 'jquery-ui/ui/widgets/sortable.js';
import { loadingCssClass } from 'variables.module.scss';

require('cafevdb-selectize.scss');

import 'pme-table.scss';

export type TableLoadCallback<T extends PageTemplateValue = PageTemplateValue> = {
  callback(
    template: T,
    selector: string,
    parameters: TableDialogCallbackData,
    resizeCB: () => void,
  ): void;
  context?: unknown;
};

const popupPosition = {
  my: 'left top',
  at: 'left+5% top+5%',
  // of: window
  of: '#app-content, #app-content-vue', // main?
};

/**
 * Cleanup vue-component when replacing parts of the DOM.
 *
 * @param $container TBD.
 */
const pmeDestroyVueComponents = function($container: JQuery) {
  console.info('CLEANUP TO BE ORPHANED VUE COMPONENTS', {
    $container,
    vueComponents: $container?.data('vueComponents'),
  });
  const vueComponents: MountableComponent[] = $container.data('vueComponents') || [];
  for (const component of vueComponents) {
    component.unmount();
    component.destroy();
    console.info('REMOVED VUE COMPONENT', { component });
  }
  $container.data('vueComponents', []);
};

asyncSubscribe(LEGACY_PAGE_CLEANUP, () => {
  pmeDestroyVueComponents(pmeContainer());
  // to do: find all open dialogs and do the same.
});

const pmeHasEditableData = (form: HTMLElement|JQuery<HTMLElement>) => {
  let $form = jq(form);
  if (!$form.is(pmeFormSelector)) {
    $form = $form.find(pmeFormSelector + ':first');
  }
  if ($form.length === 0) {
    return false;
  }
  return $form.is(pmeClassSelectors('', pmeFormEditSuffixes));
};

/**
 * Generate the jQuery object corresponding to the inner container
 * of the ambient container. If the given argument is already a
 * jQuery object, then just return its first div child.
 *
 * @param selector TBD.
 */
const pmeInner = function(selector: string|JQuery) {
  let $container: JQuery;
  if (selector instanceof $) {
    $container = selector;
  } else {
    selector = pmeSelector(selector);
    $container = $(selector);
  }
  return $container.children('div:first') as JQuery<HTMLDivElement>;
};

const tableLoadCallbacks: Record<string, TableLoadCallback> = {};

const pmeAddTableLoadCallback = <T extends PageTemplateValue>(
  template: T,
  cbObject: TableLoadCallback<T>,
) => {
  if (typeof cbObject.context === 'undefined') {
    cbObject.context = this;
  }
  tableLoadCallbacks[template] = cbObject;
};

const tableLoadCallback = <T extends PageTemplateValue>(
  template: T,
  selector: string,
  parameters?: TableDialogCallbackData,
  resizeReadyCB?: (keepLocked?: boolean) => void,
) => {
  let cbHandle: TableLoadCallback<T>;

  if (typeof tableLoadCallbacks[template] !== 'undefined') {
    cbHandle = tableLoadCallbacks[template];
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

  if (context) {
    return callback.call(context, template, selector, parameters ?? {}, resizeReadyCB);
  } else {
    return callback(template, selector, parameters ?? {}, resizeReadyCB);
  }
};

export type SubmitOptions = {
  keepLocked: boolean;
  keepBusy: boolean;
  discard: boolean;
};

/**
 * Submit the base form in order to synchronize any changes caused
 * by the dialog form.
 *
 * @param outerSelector The CSS selector identifying the form
 * to reload.
 *
 * @param [options] Further options. Currently:
 * @param [options.keepLocked] Do not destroy "locking" modal planes.
 * @param [options.keepBusy] TBD.
 * @param [options.discard] TBD.
 */
const pmeSubmitOuterForm = function(outerSelector: string|JQuery, options?: Partial<SubmitOptions>) {

  console.debug('SUBMIT OUTER FORM, NEEDS MORE WORK WITH VUE', {
    outerSelector,
    options,
  });

  outerSelector = pmeSelector(outerSelector);
  const submitOptions = { keepLocked: false, keepBusy: false, discard: false, ...(options ?? {}) };

  // try a reload while saving data. The purpose is to resolve
  // inter-table dependencies like changed instrument lists and so
  // on. Be careful not to trigger top and bottom buttons.
  const $outerForm = $(outerSelector + ' ' + pmeFormSelector) as JQuery<HTMLFormElement>;
  $outerForm.data('submitOptions', submitOptions);

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
  const submitNames = submitOptions.discard
    ? submitNamesReload
    : submitNamesApply.concat(submitNamesReload);

  const button = $outerForm.find(pmeSysNameSelectors('input', submitNames)).first();
  if (button.length > 0) {
    console.debug('TRIGGER APPLY BUTTON CLICK', button);
    const { promise, resolve, reject } = Promise.withResolvers();
    button.trigger('click', [{ resolve, reject }]);
    return promise;
  } else {
    // submit the outer form
    // $outerForm.submit();
    console.warn('PSEUDO SUBMIT ON', {
      $outerForm,
      outerSelector,
    });
    return pseudoSubmit($outerForm, $(), outerSelector, false);
  }
};

/**
 * Submit the base form in order to synchronize any changes caused by
 * the dialog form. Like pmeSubmitOuterForm() but does not throw an
 * error on failure.
 *
 * @param outerSelector The CSS selector identifying the form to
 * reload.
 *
 * @param [options] Further options.
 */
const pmeSubmitOuterFormNoThrow = function(outerSelector: string|JQuery, options?: Partial<SubmitOptions>) {
  (pmeSubmitOuterForm(outerSelector, options) as Promise<unknown>).then(
    (result) => console.info('RELOADING OUTER FORM COMPLETED', { result }),
    (error) => console.error('SUBMIT OUTER FORM', { error }),
  );
};

const deferKey = pmePrefix + '-submitdefer';
const cancellableKey = pmePrefix + '-cancellable';

type DeferReload = {
  tag: string;
  promise: Promise<string>;
  resolve: (result?: string) => void;
};

const cancelDeferredReload = <E extends HTMLElement>($container: JQuery<E>) => {
  console.info('CANCEL DEFER RELOAD');
  const promises: Record<string, PromiseWithResolvers<string>> = $container.data(deferKey) ?? {};
  for (const promise of Object.values(promises)) {
    promise.resolve('cancelled');
  }
  $container.data(deferKey, {});
};

/**
 * Create a jQuery Deferred object in order post-one form submission
 * until after validation of data, for example.
 *
 * @param $container TBD.
 *
 * @param tag TBD.
 */
const pmeDeferReload = <E extends HTMLElement, T extends string>(
  $container: JQuery<E>,
  tag: T,
) => {
  const { promise, resolve } = Promise.withResolvers<string>();
  const promises: Record<string, DeferReload> = $container.data(deferKey) ?? {};
  if (promises[tag]) {
    console.error('PROMISE ALREADY SET', { tag, promises });
    promises[tag].resolve('replaced');
  }
  promises[tag] = {
    tag,
    promise,
    resolve: (result) => {
      console.info('RESOLVING WITH', { tag, result });
      resolve(tag + (result ? ': ' + result : ''));
    },
  };
  console.info('DEFER RELOAD', { promises });
  return promises[tag];
};

const reloadDeferred = <E extends HTMLElement>($container: JQuery<E>) => {
  const promises: Record<string, PromiseWithResolvers<string>> = $container.data(deferKey) ?? {};
  console.info('RELOAD DEFERRED', { promises });
  return Promise.allSettled(Object.values(promises).map((withResolvers) => withResolvers.promise));
};

const pmeCancelBeforeSubmit = <E extends HTMLElement>($container: JQuery<E>) => {
  const cancellable = $container.data(cancellableKey) || [];
  for (const job of cancellable) {
    console.info('TRY ABORT JOB', job);
    job.abort('cancelled');
  }
  $container.data(cancellableKey, []);
};

const pmePushCancellable = <E extends HTMLElement, T>($container: JQuery<E>, promise: Promise<T>|JQuery.jqXHR<T>) => {
  const cancellable = $container.data(cancellableKey) || [];
  cancellable.push(promise);
  $container.data(cancellableKey, cancellable);
};

const tableDialogLoadIndicator = <E extends HTMLElement>($container: JQuery<E>, state: boolean) => {
  let $reloadButton = $container.data('reloadButton');
  if (!$reloadButton) {
    $reloadButton = $container.find(pmeNavigationSelector('reload'));
  }
  if (state) {
    $reloadButton.addClass(loadingCssClass);
  } else {
    $reloadButton.removeClass(loadingCssClass);
  }
};

/**
 * Replace the content of the already opened dialog with the given
 * HTML-data.
 *
 * @param $container TBD.
 *
 * @param content TBD.
 *
 * @param tableDialogOptions TBD.
 *
 * @param callback TBD.
 *
 * @param triggerData Additional data passed to the calling
 * event handler after being triggered artifically.
 */
const tableDialogReplace = <E extends HTMLElement>(
  $container: JQuery<E>,
  content: string,
  tableDialogOptions: TableDialogOptions,
  callback: (data: TableDialogCallbackData) => void,
  triggerData?: TriggerData,
) => {

  const containerSel = '#' + tableDialogOptions.dialogHolderCSSId;

  // remote data/time widgets and other stuff
  pmeUnTweak($container);
  // remove the WYSIWYG editor, if any is attached
  WysiwygEditor.removeEditor($container.find(`textarea.${WYSIWYG_EDITOR}`));

  pmeDestroyVueComponents($container);

  $container.css('height', 'auto');
  $.fn.cafevTooltip.remove();
  $container.off(); // remove ALL delegate handlers
  $container.html(content);
  $container.find('iframe').on('load', function() {
    const $this = $(this);
    const data = $this.data();
    const dataKey = appName + 'dbLoadEvent';
    data[dataKey] = (data[dataKey] || 0) + 1;
    console.info('IFRAME LOAD', $this.attr('class'), data[dataKey]);
  });

  tableDialogLoadIndicator($container, true);

  // general styling, avoid submit handlers by second argument
  pmeInit(containerSel, true);

  const title = $container.find(pmeClassSelector('span', 'short-title')).html();
  if (title) {
    $container.dialog('option', 'title', title);
  }

  // attach the WYSIWYG editor, if any
  // editors may cause additional resizing
  $container.dialog('option', 'height', 'auto');
  // $container.dialog('option', 'position', popupPosition);

  // re-attach events
  tableDialogHandlers(tableDialogOptions, callback, triggerData);
};

const pmeHalt = function() {
  PHPMyEdit.stopped = true;
};

const pmeIsHalted = function() {
  return !!PHPMyEdit.stopped;
};

const pmePost = (post: JQuery.PlainObject|string) => {
  // console.debug('PME POST', post);
  if (pmeIsHalted()) {
    // just return a promise which is never resolved.
    console.info('PME is halted, returning never-resolved promise.');
    return $.Deferred().promise();
  }
  closeActionMenus();
  return $.post(generateAppUrl(controllerEndPoint), post)
    .then(
      function(htmlContent, _textStatus, request) {
        console.info('RESOLVE IN PME POST');
        const historyAction = request.getResponseHeader(`X-${appName}-history-action`);
        return $.Deferred().resolve(htmlContent, historyAction, post).promise();
      },
      function(xhr, status, errorThrown) {
        console.info('REJECT IN PME POST');
        Ajax.handleError(xhr, status, errorThrown);
        return $.Deferred().reject(xhr, status, errorThrown).promise();
      },
    );
};

const blockTableDialog = <E extends HTMLElement>($dialogHolder: JQuery<E>) => {
  const $dialogWidget = $dialogHolder.dialog('widget');
  if (!$dialogHolder.data('z-index')) {
    $dialogWidget.data('z-index', parseInt($dialogWidget.css('z-index')));
  }
  $dialogWidget.addClass(pmeToken('table-dialog-blocked'));
};

const unblockTableDialog = <E extends HTMLElement>($dialogHolder: JQuery<E>) => {
  $dialogHolder.dialog('widget').removeClass(pmeToken('table-dialog-blocked'));
  $dialogHolder.removeData('z-index');
};

const lockTableDialog = <E extends HTMLElement>($container: JQuery<E>, state: boolean) =>
  (state ? blockTableDialog($container) : unblockTableDialog($container));

/**
 * Reload the current PME-dialog.
 *
 * @param tableDialogOptions The current dialog options. In particular
 * options.reloadName and options.reloadValue must hold name and
 * value of the curent (pseudo-) submit input
 * element. options.modified must already be up-to-date.
 *
 * @param callback The application provided callback which is used
 * to shape the HTML after loading.
 *
 * @param triggerData Additional data passed to the calling
 * event handler after being triggered artifically.
 */
const tableDialogReload = async (
  tableDialogOptions: TableDialogOptions,
  callback: (data: TableDialogCallbackData) => void,
  triggerData: TriggerData,
) => {

  const reloadName = tableDialogOptions.reloadName;
  const reloadValue = tableDialogOptions.reloadValue;

  const containerSel = '#' + tableDialogOptions.dialogHolderCSSId;
  const container = $(containerSel);

  if (container.data(pmeToken('reloading'))) {
    return;
  }
  container.data(pmeToken('reloading'), true);
  container.removeData('reloadButton');

  blockTableDialog(container);
  tableDialogLoadIndicator(container, true);

  pmeCancelBeforeSubmit(container);

  // Possibly delay reload until validation handlers have done their
  // work.
  console.info('BEFORE RELOAD DEFERRED');
  await reloadDeferred(container);

  let post = container.find(pmeFormSelector).serialize();

  // add the option values
  post += '&' + $.param(tableDialogOptions);

  // add name and value of the "submit" button.
  post += '&' + $.param({ [reloadName]: reloadValue });

  try {
    const htmlContent = await pmePost(post);
    tableDialogReplace(container, htmlContent, tableDialogOptions, callback, triggerData);
    container.data(pmeToken('reloading'), false);
    if (typeof triggerData?.resolve === 'function') {
      triggerData.resolve('reloaded');
    }
  } catch (error) {
    pageBusyIcon(false);
    unblockTableDialog(container);
    tableDialogLoadIndicator(container, false);
    container.data(pmeToken('reloading'), false);
    if (typeof triggerData?.reject === 'function') {
      const xhr = error as JQuery.jqXHR;
      const status = 'error';
      const errorThrown = xhr.statusText;
      triggerData.reject({ xhr, status, errorThrown });
    }
  }
};

/**
 * Overload the PHPMyEdit submit buttons in order to be able to
 * display the single data-set display, edit, add and copy form in a
 * popup.
 *
 * @param tableDialogOptions Object with additional params to the
 * pme-table.php AJAX callback. Must at least contain the
 * templateRenderer component.
 *
 * @param changeCallback Handler to call after dialog open
 * and tab change.
 *
 * @param triggerData Optional additonal data passed to an
 * articifically triggered calling event handler. Will be passed on to
 * the changeCallback.
 */
function tableDialogHandlers(
  tableDialogOptions: TableDialogOptions,
  changeCallback?: (data: TableDialogCallbackData) => void,
  triggerData?: TriggerData,
) {

  if (typeof changeCallback === 'undefined') {
    changeCallback = () => {};
  }

  const containerSel = '#' + tableDialogOptions.dialogHolderCSSId;
  const $container = $(containerSel);

  cancelDeferredReload($container);

  /* form.
   * pme-list
   * pme-change
   * pme-view
   * pme-delete
   * pme-copyadd
   * pme-query
   */

  if ($container.find(pmeClassSelector('form', 'list')).length) {
    // main list view, just leave as is.
    const resize = (reason: TableDialogCallbackData['reason']) => {
      changeCallback({ reason });
      const reloadSel = pmeClassSelectors('input', ['reload', 'query']);
      $container.find(reloadSel)
        .off('click')
        .on('click', function(_event, triggerData) {
          tableDialogReload(tableDialogOptions, changeCallback, triggerData);
          return false;
        });
    };
    resize('dialogOpen');
    $container.on('pmetable:layoutchange', function() {
      resize('layoutChange');
    });
    return;
  }

  $container.on('pmetable:layoutchange', function() {
    changeCallback({ reason: 'layoutChange' });
  });

  installTabHandler($container, function() {
    changeCallback({ reason: 'tabChange' });
  });

  const reloadButtonSelector = pmeClassSelectors(
    'input',
    ['change', 'delete', 'copy', 'apply', 'more', 'reload'],
  );
  const reloadingButton = $container.find(reloadButtonSelector);

  const saveButtonSelector = 'input.' + pmeToken('save');
  const saveButton = $container.find(saveButtonSelector);

  const cancelButton = $container.find(pmeClassSelector('input', 'cancel'));

  const allButtons = $().add(reloadingButton).add(saveButton).add(cancelButton);

  // The easy one, but for changed content
  cancelButton
    .off('click')
    .on('click', function(_event, triggerData) {

      // When the initial dialog was in view-mode and we are not in
      // view-mode, then we return to view mode; for me "cancel" feels
      // more natural when the GUI returns to the previous dialog
      // instead of returning to the main table. We only have to look
      // at the name of "this": if it ends with "cancelview" then we
      // are cancelling a view and close the dialog, otherwise we
      // return to view mode.
      if (tableDialogOptions.initialViewOperation && $(this).attr('name')!.indexOf('cancelview') < 0) {
        tableDialogOptions.reloadName = tableDialogOptions.initialName;
        tableDialogOptions.reloadValue = tableDialogOptions.initialValue;
        tableDialogReload(tableDialogOptions, changeCallback, triggerData);
      } else {
        $container.dialog('close');
        if (typeof triggerData?.resolve === 'function') {
          triggerData.resolve('cancelled');
        }
      }

      return false;
    });

  // The complicated ones. This reloads new data.

  // install a delegate handler on the outer-most container which
  // finally will run after possible inner data-validation handlers
  // have been executed.
  // remove non-delegate handlers and stop default actions in any case.
  reloadingButton.off('click');
  $container
    .off('click', reloadButtonSelector)
    .on(
      'click',
      reloadButtonSelector,
      function(_event, triggerData) {

        const $submitButton = $(this);

        const reloadName = $submitButton.attr('name')!;
        const reloadValue = $submitButton.val()!;
        tableDialogOptions.reloadName = reloadName;
        tableDialogOptions.reloadValue = reloadValue;
        if (!$submitButton.hasClass(pmeToken('change'))
            && !$submitButton.hasClass(pmeToken('delete'))
            && !$submitButton.hasClass(pmeToken('copy'))
            && !$submitButton.hasClass(pmeToken('reload'))) {
          // so this is pme-more, morechange, apply

          allButtons.prop('disabled', true);
          const cleanup = () => {
            allButtons.prop('disabled', false);
            unblockTableDialog($container);
            tableDialogLoadIndicator($container, false);
          };

          if (!checkInvalidInputs($container, {
            cleanup,
            beforeDialog($invalidInputs) {
              const $closestRows = $invalidInputs.closest('tr.pme-row');
              let minTab: 'all'|number = 'all';
              if ($closestRows.length === 1) {
                const tabs = $closestRows.attr('class')!.matchAll(/tab-(\d+)/g).toArray().map((match) => +match[1]);
                minTab = Math.min(...tabs);
              }
              $container.find('[data-tab-index=' + minTab + ']').trigger('click');
            },
            afterDialog(_$invalidInputs) {
              cleanup();
              if (typeof triggerData?.resolve === 'function') {
                triggerData.resolve('invalid'); // not reject
              }
            },
            timeout: 10000, // animation timeout
          })) {
            return false;
          }
          cleanup();

          tableDialogOptions.modified = true;
        } else if ($submitButton.hasClass(pmeToken('reload'))) {
          // this is essentially a cancel, so remove 'modified'
          tableDialogOptions.modified = false;
        }
        tableDialogReload(tableDialogOptions, changeCallback, triggerData);

        return false;
      },
    );

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
  $container
    .off('click', saveButtonSelector)
    .on('click', saveButtonSelector, function(_event, triggerData) {

      if ($container.data(pmeToken('saving'))) {
        return false;
      }
      $container.data(pmeToken('saving'), true);

      allButtons.prop('disabled', true);

      $.fn.cafevTooltip.remove();
      tableDialogLoadIndicator($container, true);
      pageBusyIcon(true);

      const cleanup = () => {
        tableDialogLoadIndicator($container, false);
        pageBusyIcon(false);
        allButtons.prop('disabled', false);
        $container.data(pmeToken('saving'), false);
      };

      // Brief front-end-check for empty required fields.
      if (!checkInvalidInputs($container, {
        cleanup,
        beforeDialog($invalidInputs) {
          const $closestRows = $invalidInputs.closest('tr.pme-row');
          let minTab: 'all'|number = 'all';
          if ($closestRows.length === 1) {
            const tabs = $closestRows.attr('class')!.matchAll(/tab-(\d+)/g).toArray().map((match) => +match[1]);
            minTab = Math.min(...tabs);
          }
          $container.find('[data-tab-index=' + minTab + ']').trigger('click');
        },
        afterDialog(_$invalidInputs) {
          cleanup();
          if (typeof triggerData?.resolve === 'function') {
            triggerData.resolve('invalid'); // not reject
          }
        },
        timeout: 10000, // animation timeout
      })) {
        return false;
      }

      tableDialogOptions.modified = true; // we are the save-button ...

      const applySelector = pmeSysNameSelectors(
        'input',
        ['morechange', 'applyadd', 'applycopy'],
      );
      const deleteSelector = pmeSysNameSelector('input', 'savedelete');

      pmeCancelBeforeSubmit($container);

      reloadDeferred($container).then(function() {

        let post = $container.find(pmeFormSelector).serialize();
        post += '&' + $.param(tableDialogOptions);

        const deleteButton = $container.find(deleteSelector);
        if (deleteButton.length > 0) {
          post += '&' + $.param(deleteButton);
          post += '&' + $.param({ [pmeSys('operation')]: 'Null' }); // end-point, don't ouptput
        } else {
          const applyButton = $container.find(applySelector);
          if (applyButton.length > 0) {
            post += '&' + $.param(applyButton);
          }
        }

        blockTableDialog($container);

        // @todo Error handling is flaky
        pmePost(post)
          .fail(function(xhr, status, errorThrown) {
            unblockTableDialog($container);
            cleanup();
            if (typeof triggerData?.reject === 'function') {
              triggerData.reject({ xhr, status, errorThrown });
            }
          })
          .done(function(htmlContent, _historyAction, _post) {
            const op = $(htmlContent).find(pmeSysNameSelector('input', 'op_name'));
            if (op.length > 0 && (op.val() === 'add' || op.val() === 'delete')) {
              // Some error occured. Stay in the given mode.

              Notification.show(t(appName, 'An error occurred.'
                                  + ' The data has not been saved.'
                                  + ' Unfortunately, no further information is available.'
                                  + ' Sorry for that.'), { timeout: 15 });
              tableDialogReplace($container, htmlContent, tableDialogOptions, changeCallback);
              $container.data(pmeToken('saving'), false);
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

            if (tableDialogOptions.initialViewOperation && deleteButton.length <= 0) {
              // return to initial view, but not after deletion
              unblockTableDialog($container);
              tableDialogOptions.reloadName = tableDialogOptions.initialName;
              tableDialogOptions.reloadValue = tableDialogOptions.initialValue;
              tableDialogReload(tableDialogOptions, changeCallback, triggerData);
            } else {
              if ($container.hasClass('ui-dialog-content')) {
                $container.dialog('close');
                if (!tableDialogOptions.modified) {
                  // otherwise the close() method will reload the
                  // form which in turn will update the icon state.
                  pageBusyIcon(false);
                }
              } else {
                tableDialogLoadIndicator($container, false);
                pageBusyIcon(false);
              }
              if (typeof triggerData?.resolve === 'function') {
                triggerData.resolve('deleted');
              }
            }
            allButtons.prop('disabled', false);
            $container.data(pmeToken('saving'), false);
          });
      });
      return false;
    });

  // Finally do the styling ...
  changeCallback({
    reason: 'dialogOpen',
    triggerData,
  });

  if (tableDialogOptions.modified && tableDialogOptions.ambientContainerSelector) {
    // might be costly?
    pmeSubmitOuterForm(tableDialogOptions.ambientContainerSelector, {
      keepLocked: true,
      discard: tableDialogOptions.reloadMode === 'discard',
    });
  }
}

/**
 * Post the content of a pme-form via AJAX into a dialog
 * widget. Useful for editing, viewing etc. because this avoids the
 * need to reload the base table (when only viewing single
 * data-sets).
 *
 * @param $form The form to take the informatoin from, including the
 * name of the PHP class which generates the response.
 *
 * @param $element The input element which initiates the "form
 * submit". In particular, we assume PME "view operation" if element
 * carries a CSS class "pme-viewXXXXX" with XXXXX being anything.
 *
 * @param containerSel TBD.
 */
const tableDialog = ($form: JQuery<HTMLFormElement>, $element: JQuery, containerSel: string|JQuery) => {

  let post = $form.serialize();
  const $templateRenderer = $form.find('input[name="templateRenderer"]');

  if ($templateRenderer.length === 0) {
    // This just does not work.
    return false;
  }
  const templateRenderer = $templateRenderer.val()! as TemplateRenderer<PageTemplateValue>;

  let viewOperation = false;

  const initialName = $element.attr('name')!;
  const initialValue = $element.val()! as string;
  if (initialName) {
    post += '&' + $.param({ [initialName]: initialValue });
  }
  if ($element.hasClass(pmeToken('add'))) {
    // start with all tabs open in when adding data
    post += '&' + $.param({ [pmeSys('cur_tab')]: 'all' });
  }

  const cssClass = $element.attr('class');
  if (cssClass) {
    viewOperation = cssClass.indexOf(pmeToken('view')) > -1;
  }

  let dialogCSSId = pmeDialogCSSId;
  containerSel = pmeSelector(containerSel);
  if (containerSel !== pmeDefaultSelector) {
    if (containerSel.charAt(0) === '#') {
      dialogCSSId = containerSel.substring(1) + '-' + dialogCSSId;
    } else {
      dialogCSSId = containerSel + '.' + dialogCSSId;
    }
  }

  const tableDialogOptions: TableDialogOptions = {
    ambientContainerSelector: containerSel,
    dialogHolderCSSId: dialogCSSId,
    templateRenderer,
    template: templateFromRenderer(templateRenderer),
    initialViewOperation: viewOperation,
    initialName,
    initialValue,
    reloadName: initialName,
    reloadValue: initialValue,
    modalDialog: true,
    modified: false, // avoid reload of base table unless necessary
  };
  pmeTableDialogOpen(tableDialogOptions, post);
  return true;
};

/**
 * Open directly the popup holding the form data. We listen for the
 * custom event 'pmedialog:changed' on the dialogHolder. This event will
 * be forwarded to the ambientContainer. The idea is that we can
 * update the "modified" component of chained dialogs in a reliable
 * way.
 *
 * @param tableDialogOptions Option array, see above
 *
 * @param [post] Additional query parameters. In principle it
 * is also possible to store all values in tableDialogOptions, as this is
 * added to the query-string in any case.
 */
async function pmeTableDialogOpen<T extends PageTemplateValue>(tableDialogOptions: TableDialogOptions<T>, post?: string|JQuery.PlainObject) {

  const containerCSSId = tableDialogOptions.dialogHolderCSSId;

  const template = templateFromRenderer(tableDialogOptions.templateRenderer);

  if (pmeOpenDialogs[containerCSSId]) {
    return false;
  }
  pmeOpenDialogs[containerCSSId] = true;

  pageBusyIcon(true);

  if (typeof tableDialogOptions.modalDialog === 'undefined') {
    tableDialogOptions.modalDialog = true;
  }
  if (typeof post === 'undefined') {
    post = $.param(tableDialogOptions);
  } else {
    if (typeof post !== 'string') {
      post = $.param(post);
    }
    post += '&' + $.param(tableDialogOptions);
  }
  if (!tableDialogOptions.initialName) {
    post += '&' + $.param({ initialName: tableDialogOptions.initialValue });
  }

  await new Promise((resolveOpenDialog, rejectOpenDialog) =>
    pmePost(post)
      .fail(function(_xhr, _status, errorThrown) {
        pageBusyIcon(false);
        pmeOpenDialogs[containerCSSId] = false;
        rejectOpenDialog(new Error(errorThrown));
      })
      .done(function(htmlContent, _historyAction, _post) {
        const containerSel = '#' + containerCSSId;
        const $dialogHolder = $(`<div id="${containerCSSId}" class="${containerCSSId} ${RESIZE_TARGET}"></div>`);
        $dialogHolder.html(htmlContent);
        $dialogHolder.find('iframe').on('load', function() {
          const $this = $(this);
          const data = $this.data();
          const dataKey = appName + 'LoadEvent';
          data[dataKey] = (data[dataKey] || 0) + 1;
          console.info('IFRAME LOAD', $this.attr('class'), data[dataKey]);
        });

        $dialogHolder.data('ambientContainer', tableDialogOptions.ambientContainerSelector);

        tableDialogLoadIndicator($dialogHolder, true);

        if (tableDialogOptions.modalDialog) {
          modalizer(true);
        }
        $dialogHolder.cafevDialog({
          title: $dialogHolder.find(pmeClassSelector('span', 'short-title')).html(),
          position: popupPosition,
          width: 'auto',
          height: 'auto',
          modal: false, // tableDialogOptions.modalDialog,
          closeOnEscape: false,
          dialogClass: `${pmeToken('table-dialog')} custom-close ${RESIZE_TARGET} ${template}`,
          resizable: false,
          dragStart() {
            const $widget = $dialogHolder.dialog('widget');
            const cssWidth = $widget.prop('style').width;
            if (cssWidth === 'auto') {
              $dialogHolder.data('drag-width-tweak', true);
              $widget.width($widget.width()! + 1); // cope with jquery-ui + ff drag bug
            }
          },
          resize() {
            console.info('jq resize');
          },
          open() {

            const $dialogWidget = $dialogHolder.dialog('widget');

            DialogUtils.toBackButton($dialogHolder);
            DialogUtils.customCloseButton($dialogHolder, function(event, container) {
              const cancelButton = container.find(pmeClassSelector('input', 'cancel')).first();
              if (cancelButton.length > 0) {
                event.stopImmediatePropagation();
                cancelButton.trigger('click');
              } else {
                $dialogHolder.dialog('close');
              }
              return false;
            });

            blockTableDialog($dialogHolder);

            const $staticReloadRequest = $dialogHolder.find('input[name="' + pmeSys('reloadOuterForm') + '"]');
            console.info('STATIC RELOAD REQUEST', $staticReloadRequest);
            if ($staticReloadRequest.val()) {
              // reload outer form
              $(tableDialogOptions.ambientContainerSelector).trigger('pmedialog:changed');
              pmeSubmitOuterForm(tableDialogOptions.ambientContainerSelector);
              $staticReloadRequest.val('');
            }

            // general styling, avoid :submit handlers in dialog mode
            pmeInit(containerSel, true);

            const resizeHandler = function() {
              $dialogHolder.dialog('option', 'height', 'auto');
              $dialogHolder.dialog('option', 'width', 'auto');
              let newHeight = $dialogWidget.height()! -
                $dialogWidget.find('.ui-dialog-titlebar').outerHeight()!;
              newHeight -= $dialogHolder.outerHeight(true)! - $dialogHolder.height()!;
              $dialogHolder.height(newHeight);
              const form = $dialogHolder.find('form.pme-form')[0];
              const html = $('html')[0];
              const dialog = $dialogWidget[0];
              const scrollDelta = form.scrollWidth - form.clientWidth;
              if (scrollDelta > 0 && dialog.offsetWidth + scrollDelta < html.clientWidth) {
                console.debug('Compensating dialog width for pme-form vertical scrollbar');
                $dialogWidget.css('width', (dialog.offsetWidth + scrollDelta) + 'px');
              }
            };

            tableDialogHandlers(tableDialogOptions, function(parameters: TableDialogCallbackData) {
              const defaultParameters: TableDialogCallbackData = {
                reason: 'unknown',
                triggerData: {
                  postOpen($dialogDiv: JQuery) {
                    $dialogDiv.dialog('moveToTop');
                  },
                },
                tableDialogOptions,
              };
              parameters = { ...defaultParameters, ...parameters };
              if (parameters.reason === 'unknown') {
                console.trace();
              }
              $dialogHolder.css('height', 'auto');
              switch (parameters.reason) {
                case 'dialogClose':
                  tableLoadCallback(template, containerSel, parameters, () => {});
                  pmeDestroyVueComponents(pmeContainer(containerSel));
                  break;
                case 'dialogOpen':
                  WysiwygEditor.addEditor($dialogHolder.find(`textarea.${WYSIWYG_EDITOR}`/* :enabled' */), function() {
                    transposeReady(containerSel);
                    pmeQueryLogMenu(containerSel);
                    tableLoadCallback(template, containerSel, parameters, (arg) => {
                      const keepLocked = arg === true;
                      // console.trace();
                      // installInputChosen(containerSel);
                      resizeHandler();
                      if (parameters.triggerData?.postOpen) {
                        parameters.triggerData.postOpen($dialogHolder);
                      }
                      CAFEVDB.toolTipsInit(containerSel);
                      if (!keepLocked) {
                        unblockTableDialog($dialogHolder);
                        pageBusyIcon(false);
                        tableDialogLoadIndicator($dialogHolder, false);
                      }
                      console.debug('RESOLVING PME TABLE DIALOG PROMISE');
                      resolveOpenDialog(true);
                    });
                    pmeTweaks($dialogHolder);
                    $.fn.cafevTooltip.remove();
                  });
                  break;
                case 'tabChange':
                  installInputChosen(containerSel, 'chosen-invisible');
                  resizeHandler();
                  break;
                case 'layoutChange':
                  resizeHandler();
                  break;
              }
            });

            // install delegate handlers on the widget s.t. we
            // can call .off() on the container
            $dialogWidget.on('resize.' + appName, containerSel, function() {
              resizeHandler();
            });
            $dialogWidget.on('pmedialog:changed', containerSel, function() {
              tableDialogOptions.modified = true;
            });
          },
          close() {
            $.fn.cafevTooltip.remove();

            // remove data/time widgets and other stuff
            pmeUnTweak($dialogHolder);
            // remove the WYSIWYG editor, if any is attached
            WysiwygEditor.removeEditor($dialogHolder.find(`textarea.${WYSIWYG_EDITOR}`));

            $dialogHolder.find('iframe').removeAttr('src');

            pmeDestroyVueComponents($dialogHolder);

            if (tableDialogOptions.modified === true) {
              // reload outer form
              $(tableDialogOptions.ambientContainerSelector).trigger('pmedialog:changed');
              pmeSubmitOuterForm(tableDialogOptions.ambientContainerSelector);
            }

            $dialogHolder.dialog('destroy');

            // At least konq. has the bug that removing a form
            // with submit inputs will submit the form. Very strange.
            $dialogHolder.find('form input[type="submit"]').remove();
            $dialogHolder.remove();

            pmeOpenDialogs[containerCSSId] = false;

            CAFEVDB.unfocus();

            if (!tableDialogOptions.modified) {
              // Remove modal plane if appropriate
              modalizer(false);
            }

            Notification.hide();

            return false;
          },
        });
      })); // promise ctor
}

/**
 * Quasi-submit the pme-form, returns the promise generated by the
 * pmePost().
 *
 * @param $form The jQuery object corresponding to the pme-form.
 *
 * @param $element The jQuery object corresponding to the element
 * causing the submit.
 *
 * @param resetFilter Bool, post a sw=Clear string in addition,
 * causing PHPMyEdit to reset the filter.
 */
const pseudoSubmitPost = ($form: JQuery<HTMLFormElement>, $element: JQuery, resetFilter: boolean = false) => {

  if (resetFilter === true) {
    $form.append('<input type="hidden"'
                + ' name="' + pmeSys('sw') + '"'
                + ' value="Clear"/>');
  }

  let postString = $form.serialize();

  // @todo: should not this be included in serialize() automatically?
  const templateRenderer = $form.find('input[name="templateRenderer"]').val();
  postString += '&' + $.param({ templateRenderer });
  if ($element.attr('name')
      && (!$element.is(':checkbox') || $element.is(':checked'))) {
    postString += '&' + $.param($element);
  }

  // convert to plain object
  const post = qsParse(postString, { allowSparse: true, duplicates: 'last' });
  const result = $.Deferred();
  awaitEmit(LEGACY_SANITIZE_POST_DATA, { post })
    .then(
      (postData) => {
        console.info('POST DATA', postData);
        pmePost(postData)
          .then(
            (htmlContent, historyAction, _post) => { result.resolve(htmlContent, historyAction, postData); },
            (xhr, status, errorThrown) => { result.reject(xhr, status, errorThrown); },
          );
      },
      (error) => {
        result.reject(error);
      },
    );
  return result;
};

/**
 * Quasi-submit the pme-form.
 *
 * @param $form The jQuery object corresponding to the pme-form.
 *
 * @param $element The jQuery object corresponding to the element
 * causing the submit.
 *
 * @param selector The CSS selector corresponding to the
 * surrounding container (div element)
 *
 * @param resetFilter Bool, post a sw=Clear string in addition,
 * causing PHPMyEdit to reset the filter.
 */
function pseudoSubmit($form: JQuery<HTMLFormElement>, $element: JQuery, selector: string, resetFilter: boolean = false) {

  const submitOptions = $form.data('submitOptions') || {};

  selector = pmeSelector(selector);
  const container = pmeContainer(selector);

  const $templateRenderer = $form.find('input[name="templateRenderer"]');
  if ($templateRenderer.length <= 0 || $element.hasClass('formsubmit')) {
    $form.off('submit');
    if ($element.attr('name')) { // undefined == false
      $form.append(
        '<input type="hidden" '
          + 'name="' + $element.attr('name') + '" '
          + 'value="' + $element.val() + '"/>',
      );
    }
    for (const [name, value] of Object.entries(submitOptions)) {
      $form.append(
        '<input type="hidden" '
          + 'name="' + name + '" '
          + 'value="' + value + '"/>',
      );
    }

    console.warn('PSEUDO SUBMIT VIA FORM SUBMIT');
    $form.trigger('submit');
    return Promise.resolve(true);
  }

  if (!submitOptions.keepBusy) {
    pageBusyIcon(true);
  }
  if (!submitOptions.keepLocked) {
    modalizer(true);
  }

  const templateRenderer = $templateRenderer.val()! as TemplateRenderer<PageTemplateValue>;
  const template = templateFromRenderer(templateRenderer);

  const result = pseudoSubmitPost($form, $element, resetFilter);
  console.info('PSEUDO SUBMIT POST YIELDS', result);
  return result
    .fail(function(_xhr, _status, _errorThrown) {
      pageBusyIcon(false);
      modalizer(false);
    })
    .done(async function(htmlBody, action, post) {

      console.info('DONE AFTER PSEUDO SUBMIT', action, post);

      pmeDestroyVueComponents(container);

      await asyncEmit(LEGACY_HISTORY_UPDATE, {
        post,
        htmlBody,
        action,
      });

      $.fn.cafevTooltip.remove();

      pmeUnTweak(container);
      WysiwygEditor.removeEditor(container.find(`textarea.${WYSIWYG_EDITOR}`));
      console.info('PME INNER / CONTAINER', pmeInner(container), container);

      container.find('iframe').on('load', function() {
        const $this = $(this);
        const data = $this.data();
        const dataKey = appName + 'LoadEvent';
        data[dataKey] = (data[dataKey] || 0) + 1;
        console.info('IFRAME LOAD', $this.attr('class'), data[dataKey]);
      });

      pmeInit(selector);
      console.info('AFTER PME INIT');
      WysiwygEditor.addEditor(container.find(`textarea.${WYSIWYG_EDITOR}`), function() {
        transposeReady(selector);
        pmeQueryLogMenu(selector);
        tableLoadCallback(template, selector, { reason: 'formSubmit' }, function() {});
        pmeTweaks(container);
        CAFEVDB.toolTipsInit(selector);

        // kill the busy indicators and modalizer if appropriate
        if (!submitOptions.keepBusy) {
          pageBusyIcon(false);
        }
        if (!submitOptions.keepLocked) {
          modalizer(false);
        }
        CAFEVDB.unfocus(); // move focus away from submit button

        container.trigger('pmetable:layoutchange');
      });
    });
}

/**
 * Trigger either one of the upper or the lower button controls (but
 * not both!)
 *
 * @param buttonName TBD.
 *
 * @param containerSel TBD.
 */
const pmeTriggerSubmit = (buttonName: string, containerSel: string|JQuery) => {
  const $container = pmeContainer(containerSel);
  const $button = $container.find('input[name="' + pmeSys(buttonName) + '"]').first();

  if ($button.length > 0) {
    $button.trigger('click');
    return true;
  } else {
    return false;
  }
};

/**
 * Transpose the main tabel if desired. This works on the HTML table
   element.
 *
 * @param selector TBD.
 *
 * @param $container TBD.
 */
const transposeMainTable = (selector: string, $container: JQuery) => {
  const $table = $container.find(selector);

  const $headerRow = $table.find('thead tr');
  $headerRow.detach();
  if ($headerRow.length > 0) {
    $headerRow.prependTo($table.find('tbody'));
  }
  const $t = $table.find('tbody').eq(0);
  const sortinfo = $t.find(pmeClassSelector('tr', 'sortinfo'));
  const queryinfo = $t.find(pmeClassSelector('tr', 'queryinfo'));
  // These are huge cells spanning the entire table, move them on
  // top of the transposed table afterwards.
  sortinfo.detach();
  queryinfo.detach();
  const $r = $t.find('tr') as JQuery<HTMLTableRowElement>;
  const cols = $r.length;
  const rows = $r.eq(0).find('td,th').length;
  let cell: number, $next: JQuery, $tem: JQuery<HTMLTableRowElement>;
  let i = 0;
  const $tb = $('<tbody></tbody>');

  while (i < rows) {
    cell = 0;
    $tem = $('<tr></tr>');
    while (cell < cols) {
      $next = $r.eq(cell++).find('td,th').eq(0);
      $tem.append($next);
    }
    $tb.append($tem);
    ++i;
  }
  $table.find('tbody').remove();
  $($tb).appendTo($table);
  if ($table.find('thead').length > 0) {
    $($table)
      .find('tbody tr:eq(0)')
      .detach()
      .appendTo($table.find('thead'))
      .children()
      .each(function() {
        let tdclass = $(this).attr('class');
        if (tdclass && tdclass.length > 0) {
          tdclass = ' class="' + tdclass + '"';
        } else {
          tdclass = '';
        }
        $(this).replaceWith('<th' + tdclass + ' scope="col">' + $(this).html() + '</th>');
      });
  }
  queryinfo.prependTo($table.find('tbody'));
  sortinfo.prependTo($table.find('tbody'));

  // if (true) {
  $($table)
    .find('tbody tr th:first-child')
    .each(function() {
      let thclass = $(this).attr('class');
      if (thclass && thclass.length > 0) {
        thclass = ' class="' + thclass + '"';
      } else {
        thclass = '';
      }
      $(this).replaceWith('<td' + thclass + ' scope="row">' + $(this).html() + '</td>');
    });
  // }
  $table.show();
};

/**
 * Transpose the main table based on boolean value of transpose.
 *
 * @param transpose TBD.
 *
 * @param [containerSel] TBD.
 */
const maybeTranspose = function(transpose: boolean, containerSel?: string) {
  const $container = pmeContainer(containerSel);
  let pageitems: string;
  const tooltip = $container.find('.tooltip');

  const trUp = pmeIdSelector('transpose-up');
  const trDown = pmeIdSelector('transpose-down');
  const tr = pmeIdSelector('transpose');
  const trClass = pmeToken('transposed');
  const unTrClass = pmeToken('untransposed');

  if (transpose) {
    tooltip.remove();
    transposeMainTable(pmeTableSelector, $container);
    pageitems = t(appName, '#columns');

    $container.find('input[name="Transpose"]').val('transposed');
    $container.find(trUp).removeClass(unTrClass).addClass(trClass);
    $container.find(trDown).removeClass(unTrClass).addClass(trClass);
    $container.find(tr).removeClass(unTrClass).addClass(trClass);
  } else {
    tooltip.remove();
    transposeMainTable(pmeTableSelector, $container);
    pageitems = t(appName, '#rows');

    $container.find('input[name="Transpose"]').val('untransposed');
    $container.find(trUp).removeClass(trClass).addClass(unTrClass);
    $container.find(trDown).removeClass(trClass).addClass(unTrClass);
    $container.find(tr).removeClass(trClass).addClass(unTrClass);
  }
  $container.find(pmeClassSelector('input', 'pagerows')).val(pageitems);
};

/**
 * Ready callback.
 *
 * @param [containerSel] TBD.
 */
function transposeReady(containerSel?: string) {

  const $container = pmeContainer(containerSel);

  const trUp = pmeIdSelector('transpose-up');
  const trDown = pmeIdSelector('transpose-down');
  const tr = pmeIdSelector('transpose');
  const trClass = pmeToken('transposed');
  // const unTrClass = pmeToken('untransposed');

  // Transpose or not: if there is a transpose button
  const inhibitTranspose = $container.find('input[name="InhibitTranspose"]').val() === 'true';
  const controlTranspose = ($container.find('input[name="Transpose"]').val() === 'transposed'
                            || $container.find(trUp).hasClass(trClass)
                            || $container.find(trDown).hasClass(trClass)
                            || $container.find(tr).hasClass(trClass));

  if (!inhibitTranspose && controlTranspose) {
    maybeTranspose(true);
  } else {
    // Initially the tabel _is_ untransposed
    // maybeTranspose(false); // needed?
  }
}

const installFilterChosen = function(containerSel: string|JQuery) {

  if (!PHPMyEdit.selectChosen) {
    return;
  }

  const pmeFilter = pmeToken('filter');
  const pmeCompFilter = pmeToken('filter-comp');

  const $container = pmeContainer(containerSel);

  const noRes = PHPMyEdit.filterSelectNoResult;

  $container.find('select.' + pmeCompFilter).chosen({
    width: 'auto',
    inherit_select_classes: true,
    title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
    disable_search_threshold: 10,
    single_backstroke_delete: false,
  });

  // Provide a data-placeholder and also remove the match-all
  // filter, which is not needed when using chosen.
  $container.find('select.' + pmeFilter).attr('data-placeholder', PHPMyEdit.filterSelectPlaceholder);
  $container.off('change', 'select.' + pmeFilter);
  $container.find('select.' + pmeFilter + ' option[value="*"]').remove();

  // Then the general stuff
  $container.find('select.' + pmeFilter).chosen({
    width: '100%', // This needs margin:0 and box-sizing:border-box to be useful.
    inherit_select_classes: true,
    title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
    no_results_text: noRes,
    single_backstroke_delete: false,
  });

  const dblClickSel =
    `td.${pmeFilter} ul.chosen-choices li.search-field input[type="text"]`
      + `, td.${pmeFilter} div.chosen-container`
      + `, td.${pmeFilter} input[type="text"]`;
  $container.off('dblclick', dblClickSel);
  $container.on('dblclick', dblClickSel, function(event) {
    event.preventDefault();
    // There doesn't seem to be a "this" for dblclick, though
    // searching the web did not reveal similar problems. Doesn't
    // matter, we just trigger the click on the query-submit button
    // pseudoSubmit($container.find('$form.pme-form'), $(event.target), containerSel);
    // return false;
    $container.find('td.' + pmeFilter + ' input.' + pmeToken('query')).trigger('click');
  });

  $container.find('td.' + pmeFilter + ' div.chosen-container').each(function() {
    const $chosen = $(this);
    const selectTitle = $chosen.prev('select').attr('title');
    $chosen.attr('title', selectTitle || PHPMyEdit.filterSelectChosenTitle);
  });
};

const removeButtonPlugin = {
  name: 'remove_button',
  options: {
    title: t(appName, 'Remove'),
  },
};

const clearButtonPlugin = {
  name: 'clear_button',
  options: {
    title: t(appName, 'Clear'),
  },
};

/**
 * Internal helper function.
 *
 * @param containerSel TBD.
 *
 * @param [onlyClass] TBD.
 */
function installInputSelectize(containerSel: string|JQuery, onlyClass: string = 'selectize') {
  const $container = pmeContainer(containerSel);

  ($container.find(pmeSelectInputSelector + '.' + onlyClass) as JQuery<HTMLSelectElement>).each(function() {
    const $self = $(this);
    const plugins = $self.hasClass('not-empty')
      ? []
      : [$self.prop('multiple') ? removeButtonPlugin : clearButtonPlugin];
    const pmeSelectizeOptions = { ...($self.data('selectizeOptions') || {}) };
    const createOptions = pmeSelectizeOptions.create ?? false;
    if (createOptions !== true) {
      delete pmeSelectizeOptions.create;
    }
    const selectizeOptions: Selectize.IOptions<string, { [key: string]: string }> = mergician({ appendArrays: true, dedupArrays: true })(
      {
        plugins,
        delimiter: ',',
        persist: false,
        hideSelected: false,
        openOnFocus: true, // false,
        items: JSON.parse($self.attr(`data-${DATA_PME_INITIAL_VALUES}`) ?? '[]'),
        // closeAfterSelect: true,
        create: false,
        inputClass: pmeToken('selectize-input'),
      },
      pmeSelectizeOptions,
    );
    if (createOptions && createOptions !== true) {
      const inputField = createOptions.inputField || 'input';
      const valueField = selectizeOptions.valueField || 'value';
      const labelField = selectizeOptions.labelField || 'text';
      if (createOptions.url) {
        selectizeOptions.create = function(inputData: string, setterCallback: (data: false|Record<string, string>) => void) {
          $.post(generateAppUrl(createOptions.url), {
            ...(createOptions.post || {}),
            [inputField]: inputData,
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
        selectizeOptions.create = function(inputData: string) {
          return { [valueField]: inputData, [labelField]: inputData };
        };
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
    selectizeInstance.on('before_dropdown_open', function() {
      ensureDropdownVisibility($container);
      $.fn.cafevTooltip.remove();
    });
    selectizeInstance.off('dropdown_close');
    selectizeInstance.on('dropdown_close', function() {
      resetDropdownVisibility($container);
      $.fn.cafevTooltip.remove();
    });
  });
}

/**
 * @param containerSel TBD.
 *
 * @param onlyClass TBD.
 */
function installInputChosen(containerSel: string|JQuery, onlyClass?: string) {

  if (!PHPMyEdit.selectChosen) {
    return;
  }

  const pmeInput = pmeToken('input');
  const pmeValue = pmeToken('value');

  const $container = pmeContainer(containerSel);

  const noRes = PHPMyEdit.inputSelectNoResult;

  // Provide a data-placeholder and also remove the match-all
  // filter, which is not needed when using chosen.
  $container.find(pmeSelectInputSelector).each(function() {
    const $select = $(this);
    if (!$select.attr('data-placeholder')) {
      $select.attr('data-placeholder', PHPMyEdit.inputSelectPlaceholder);
    }
  });

  $container.off('change', pmeSelectInputSelector);
  //    $container.find('select.' + pmeInput + ' option[value="*"]').remove();

  // Then the general stuff
  $container.find(pmeSelectInputSelector).each(function() {
    const self = $(this);
    if (self.hasClass('no-chosen') || (onlyClass !== undefined && !self.hasClass(onlyClass))) {
      return;
    }
    console.debug('destroy chosen');
    self.chosen('destroy');
    const chosenOptions: Chosen.Options = {
      // width:'100%',
      inherit_select_classes: true,
      title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
      disable_search: self.hasClass('no-search'),
      disable_search_threshold: self.hasClass('no-search') ? 999999 : 10,
      no_results_text: noRes,
      allow_single_deselect: self.hasClass(ALLOW_EMPTY),
      single_backstroke_delete: false,
    };
    if (self.hasClass(ALLOW_EMPTY)) {
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
  $container.find('td.' + pmeInput + ' div.chosen-container, td.' + pmeValue + ' div.chosen-container')
    .filter('[title=""],[title^="***DEBUG***"]')
    .each(function() {
      $(this).attr('title', PHPMyEdit.inputSelectChosenTitle);
    });

  installInputSelectize(containerSel);
}

/**
 * @param containerSel TBD.
 *
 * @param changeCallback TBD.
 */
function installTabHandler(containerSel: string|JQuery, changeCallback: () => void = () => {}) {

  const $container = pmeContainer(containerSel);

  const tabsSelector = pmeClassSelector('li', 'navigation') + '.table-tabs';
  const $form = $container.find(pmeFormSelector);
  const $table = $form.find(pmeTableSelector);

  const $tabAnchor = $form.find('li.table-tabs.selected a');
  const tabClasses = ['tab-' + $tabAnchor.data('tabIndex'), 'tab-' + $tabAnchor.data('tabId')];

  const updateTabReadOnlyFields = function(tabClasses: string[]) {
    const readWriteClasses = tabClasses.map((tabClass) => tabClass + '-readwrite');
    $form
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

  $container
    .off('click', tabsSelector)
    .on('click', tabsSelector, function() {
      const $this = $(this);

      // console.info('FORM', $form.scrollLeft());
      $form.scrollLeft(0);

      const $oldTabAnchor = $form.find('li.table-tabs.selected a');
      const oldTabClasses = ['tab-' + $oldTabAnchor.data('tabIndex'), 'tab-' + $oldTabAnchor.data('tabId')];
      const $tabAnchor = $this.find('a');
      const tabClasses = ['tab-' + $tabAnchor.data('tabIndex'), 'tab-' + $tabAnchor.data('tabId')];

      // Inject the display triggers ...
      $table.removeClass(oldTabClasses).addClass(tabClasses);

      // Record the tab in the form data
      const tabPostData = {
        [pmeSys('cur_tab')]: $tabAnchor.data('tabIndex'),
      };
      for (const [name, value] of Object.entries(tabPostData)) {
        $form.find(`input[name="${name}"]`).val(value);
      }

      // for styling and logic ...
      $form.find(tabsSelector).removeClass('selected');
      $this.addClass('selected');

      updateTabReadOnlyFields(tabClasses);

      // account for unstyled chosen selected
      let reattachChosen = false;
      const pfx = (tabClasses.includes('tab-all')) ? '' : 'td.' + tabClasses.join('.');
      const selector = pmeClassSelectors(
        `${pfx} div.chosen-container`,
        ['input', 'filter', 'filter-comp'],
      );
      $form.find(selector).each(function() {
        const $this = $(this);
        if ($this.width()! <= PHPMyEdit.singleDeselectOffset) {
          $this.prev().chosen('destroy');
          reattachChosen = true;
        }
      });
      if (reattachChosen) {
        installFilterChosen($container);
        installInputChosen($container);
      }

      $.fn.cafevTooltip.remove();

      changeCallback();

      // try update the route to reflect the tab change, but not when
      // changing tabs in JQ dialogs.
      if ($this.closest('.ui-dialog').length === 0) {
        console.info('TRY RECORD TAB CHANGE IN HISTORY');
        asyncEmit(LEGACY_HISTORY_PATCH, {
          patch: tabPostData,
          action: 'push',
        });
      }

      return false;
    });
}

/**
 * Fire a custom context menu event with the database key data if
 * right-clicking on a row.
 *
 * @param element The tr element of the list-view.
 *
 * @param event The event which triggered the handler.
 *
 * @param $container The form or div containing the $form.
 */
const pmeContextMenu = (element: HTMLElement, event: unknown, $container: JQuery) => {

  const $row = $(element).closest('tr.' + pmeToken('row'));
  const recordId = $row.data(pmePrefix + '_sys_rec');
  const groupByRecordId = $row.data(pmePrefix + '_sys_groupby_rec');

  const databaseRecords = {
    recordId,
    groupByRecordId,
  };

  console.info('CONTEXT DATA', { databaseRecords, element, event, $container });

  $row.trigger('pme:contextmenu', [event, databaseRecords]);
};

/**
 * Open the view or modification dialog for the data-set of the
 * respective row after clicking on the row.
 *
 * @param element The tr element of the list-view.
 *
 * @param event The event which triggered the handler.
 *
 * @param $container The form or div containing the $form.
 */
const pmeOpenRowDialog = function(element: HTMLElement, event: JQuery.ClickEvent, $container: JQuery) {

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

  let recordQuery: string|string[] = [];

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
  const $form = $container.find(formSel) as JQuery<HTMLFormElement>;
  let recordEl: undefined|string;
  console.info('DIRECT CHANGE PROBS', PHPMyEdit, $row, $form);
  if ($row.hasClass(pmeToken('change-enabled'))
      && ($form.hasClass(pmeToken(DIRECT_CHANGE)) || PHPMyEdit.directChange)) {
    recordEl = '<input type="hidden" class="' + pmeToken('change-navigation') + '"'
      + ' value="Change?' + recordQuery + '"'
      + ' name="' + pmeSys('operation') + '" />';
    console.info('DIRECT CHANGE SHOULD BE ENABLED');
  } else if ($row.hasClass(pmeToken('view-enabled'))) {
    recordEl = '<input type="hidden" class="' + pmeToken('view-navigation') + '"'
      + ' value="View?' + recordQuery + '"'
      + ' name="' + pmeSys('operation') + '" />';
    console.info('DIRECT CHANGE SHOULD BE DISABLED');
  }

  if (recordEl) {
    tableDialog($form, $(recordEl), $container);
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
 * @param $container TBD.
 */
function ensureDropdownVisibility($container: JQuery) {
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
}

/**
 * Reset the CSS overflow property of dialogs to empty if they do not
 * have vertical scrollbars. This is used as callback for drop-down
 * "close" events in order to reset the visibility of the drop-down
 * menus when they are closing.
 *
 * @param $container TBD.
 */
function resetDropdownVisibility($container: JQuery) {
  if (!$container.hasClass('ui-widget-content')) {
    return;
  }
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
}

/**
 * @param  containerSel Selector or jQuery element of the
 * container around the form.
 *
 * @param noSubmitHandlers Do not attach any handlers to the
 * submit buttons. This is used by the popup-dialogs which install
 * their own handlers.
 */
function pmeInit(containerSel?: string|JQuery, noSubmitHandlers?: boolean) {

  containerSel = pmeSelector(containerSel);
  const $container = pmeContainer(containerSel);
  console.debug('pmeInit(): container selector: ', containerSel);
  console.debug('pmeInit(): container found: ', $container.length);

  const tableSel = 'table.' + pmeToken('main');
  const formSel = 'form.' + pmeToken('form');
  const form = $container.find(formSel);
  const hiddenClass = pmeToken('hidden');
  const pmeFilter = pmeToken('filter');
  const pmeSearch = pmeToken('search');
  const pmeHide = pmeToken('hide');
  const pmeGoto = pmeToken('goto');
  const pmePageRows = pmeToken('pagerows');

  noSubmitHandlers = !!noSubmitHandlers;

  $container.find('tr.' + pmeToken('navigation') + '.' + pmeToken('down')).find('select, select + .chosen-container').addClass('chosen-dropup');

  // Disable page-rows and goto submits, just not necessary
  $container.find('input.' + pmePageRows).on('click', function(event) {
    const $pageSelect = $(this).parent().find('select.' + pmePageRows);
    $pageSelect.trigger('chosen:open');
    event.stopImmediatePropagation();
    return false;
  });
  $container.find('input.' + pmeGoto).on('click', function(event) {
    event.stopImmediatePropagation();
    return false;
  });

  // Hide search fields
  $container.on('click', tableSel + ' input.' + pmeHide, function(event) {
    event.stopImmediatePropagation(); // don't submit, not necessary

    const $table = $container.find(tableSel);
    const $form = $container.find(formSel);

    $(this).addClass(hiddenClass);

    $table.addClass(pmeFilter + '-hidden').removeClass(pmeFilter + '-visible');
    $table.find('tr.' + pmeFilter).addClass(hiddenClass);
    $table.find('input.' + pmeSearch).removeClass(hiddenClass);
    $form.find('input[name="' + pmeSys('fl') + '"]').val(0);

    $container.trigger('pmetable:layoutchange');

    return false;
  });

  // Show search fields
  $container.on('click', tableSel + ' input.' + pmeSearch, function(event) {
    event.stopImmediatePropagation(); // don't submit, not necessary

    const $table = $container.find(tableSel);
    const $form = $container.find(formSel);

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
      `${pfx} div.chosen-container`,
      ['filter', 'filter-comp'],
    );
    $table.find(selector).each(function() {
      if ($(this).width() === 0 || $(this).width() === 60) {
        $(this).prev().chosen('destroy');
        reattachChosen = true;
      }
    });
    if (reattachChosen) {
      installFilterChosen($container);
    }

    $container.trigger('pmetable:layoutchange');

    return false;
  });

  let onChangeSel = `select.${pmeGoto}, select.${pmePageRows}`;
  if (!PHPMyEdit.selectChosen) {
    onChangeSel += `, select.${pmeFilter}`;
  }
  $container
    .off('change', onChangeSel)
    .on('change', onChangeSel, function() {
      pseudoSubmit($(this.form), $(this), containerSel);
      return false;
    });

  // view/change/copy/delete buttons lead to a a popup
  if (form.find('input[name="templateRenderer"]').length > 0) {
    const submitSel = `${formSel} input[class$="navigation"]:submit`
      + ` ${formSel} input.${pmeToken('add')}:submit`;

    $container
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
    const rowSelector = `${formSel} tr:not(.disable-row-click) td.${pmeToken('cell')}:not(.control)`;
    $container
      .off('click', rowSelector)
      .on('click', rowSelector, function(event) {
        pmeOpenRowDialog(this, event, $container);
      });
  }

  const contextMenuRowSelector = `${formSel} tr.${pmeToken('row')}`;
  $container
    .off('contextmenu', contextMenuRowSelector)
    .on('contextmenu', contextMenuRowSelector, function(event) {
      if (event.ctrlKey) {
        return; // let the user see the normal context menu
      }
      pmeContextMenu(this, event, $container);
    });

  if (!noSubmitHandlers) {
    // All remaining submit event result in a reload
    const submitSel = formSel + ' :submit:not(.action-menu-toggle)';
    $container
      .off('click', submitSel)
      .on('click', submitSel, function() {
        pseudoSubmit($(this.form), $(this), containerSel);
        return false;
      });
  }

  installTabHandler($container);

  if (PHPMyEdit.selectChosen) {
    const gotoSelect = $container.find('select.' + pmeGoto);
    gotoSelect.chosen({
      width: 'auto',
      inherit_select_classes: true,
      title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
      disable_search_threshold: 10,
    });
    if (gotoSelect.is(':disabled')) {
      // there is only one page
      gotoSelect.attr('data-placeholder', '1');
    } else {
      gotoSelect.attr('data-placeholder', ' ');
    }
    $container.find('select.' + pmeGoto).trigger('chosen:updated');

    $container.find('select.' + pmePageRows).chosen({
      width: 'auto',
      inherit_select_classes: true,
      title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
      disable_search: true,
    });
  }

  const keyPressSel = 'input.' + pmeFilter;
  $container.off('keypress', keyPressSel);
  $container.on('keypress', keyPressSel, function(event) {
    const pressedKey = event.which;
    if (pressedKey === 13) { // enter pressed
      pseudoSubmit($(this.form), $(this), containerSel);
      return false;
    }
    return true; // other key pressed
  });

  installFilterChosen($container);
  installInputChosen($container);

  /* The next two handlers allow the chosen-dropdown to extend the
   * current dialog. This can happen for small dialog windows and/or
   * if the select box is close to the bottom of the page.
   *
   */

  // @todo: the same for selectize
  $container.on('chosen:before_showing_dropdown', tableContainerId + ' select', function() {
    ensureDropdownVisibility($container);
  });

  $container.on('chosen:hiding_dropdown', tableContainerId + ' select', function() {
    resetDropdownVisibility($container);
  });

  const trackEmptyValueClass = 'track-empty-value';
  const emptyValueClass = 'value-is-empty';
  const nonEmptyValueClass = 'value-is-non-empty';

  console.info('MARKING EMPTY INPUTS');
  $container.find(pmeValueSelector + '.' + trackEmptyValueClass).each(function() {
    const $this = $(this);
    const $row = $this.closest('tr');
    const empty = $this.html().trim() === '' || $this.find(pmeInputSelector).val() === '';
    $row.toggleClass(emptyValueClass, empty);
    $row.toggleClass(nonEmptyValueClass, !empty);
  });

  $container.on('blur, change', pmeInputSelector + '.' + trackEmptyValueClass, function() {
    const $input = $(this);
    const $row = $input.closest('tr');
    const empty = $input.val() === '';
    $row.toggleClass(emptyValueClass, empty);
    $row.toggleClass(nonEmptyValueClass, !empty);
  });

  // Handle some special check-boxes disabling text-input fields
  $container.on(
    'change',
    'input[type="checkbox"].' + pmeToken('input-lock') + '.lock-empty',
    function() {
      const $this = $(this);
      const locked = !$this.prop('checked');
      const containerSelector = $this.data('container') || pmeValueSelector;
      const $input = $this.closest(containerSelector).find(pmeInputClassSelector()).not($this);
      // const $input = $this.hasClass('left-of-input') ? $this.next().next() : $this.prev();
      $input.prop('readonly', locked);
      if ($this.hasClass('locked-disabled') || $input.hasClass('locked-disabled')) {
        $input.prop('disabled', locked);
      }
      if (locked) {
        $input.val($input.data('value') || '');
        $input.attr('placeholder', $input.data('lockedPlaceholder'));
      } else {
        $input.attr('placeholder', $input.data('unlockedPlaceholder'));
      }
      $input.toggleClass('readonly', locked);
      if (isJQuerySelect($input)) {
        refreshSelectWidget($input);
      }
      return false;
    },
  );

  $container.on(
    'change',
    'input[type="checkbox"].' + pmeToken('input-lock') + '.lock-unlock',
    function() {
      const $this = $(this);
      const locked = $this.prop('checked');
      const containerSelector = $this.data('container') || pmeValueSelector;
      const $input = $this.closest(containerSelector).find(pmeInputClassSelector()).not($this);
      // const $input = $this.hasClass('left-of-input') ? $this.next().next() : $this.prev();
      $input.prop('readonly', locked);
      if ($this.hasClass('locked-disabled') || $input.hasClass('locked-disabled')) {
        $input.prop('disabled', locked);
      }
      $input.toggleClass('readonly', locked);
      if (isJQuerySelect($input)) {
        refreshSelectWidget($input);
      }
      const mceInstance = $input.data('mceInstance');
      if (mceInstance) {
        mceInstance.mode.set(locked ? 'readonly' : 'design');
        mceInstance.getBody().setAttribute('contenteditable', !locked);
      }
      return false;
    },
  );

  $container.on(
    'change click',
    'td.' + pmeToken('value') + ' input.clear-field',
    function() {
      const $this = $(this);
      const $element = $this.parent().find('.' + pmeToken('input')).first();
      if (isJQuerySelect($element)) {
        selectDeselectAll($element);
      } else if ($element.is('input')) {
        $element.val('');
      }
      return false;
    },
  );
}

const documentReady = function() {

  CAFEVDB.addReadyCallback(async () => {
    transposeReady();
    pmeQueryLogMenu();
    pmeInit();
    clear(pmeOpenDialogs); // not cleared in init on purpose
  });
};

export {
  pmeAddTableLoadCallback as addTableLoadCallback,
  pmeClassSelector as classSelector,
  pmeClassSelectors as classSelectors,
  pmeContainer as container,
  pmeData as data,
  pmeDefaultSelector as defaultSelector,
  pmeDeferReload as deferReload,
  documentReady,
  pmeFormSelector as formSelector,
  pmeHalt as halt,
  pmeIsHalted as halted,
  pmeHasEditableData as hasEditableData,
  pmeIdSelector as idSelector,
  pmeInputClassSelector as inputClassSelector,
  pmeInputSelector as inputSelector,
  pmeOpenRowDialog as openRowDialog,
  pmePushCancellable as pushCancellable,
  pmeSelectInputSelector as selectInputSelector,
  pmeSelector as selector,
  pmeSubmitOuterForm as submitOuterForm,
  pmeSubmitOuterFormNoThrow as submitOuterFormNoThrow,
  pmeSys as sys,
  pmeSysNameSelector as sysNameSelector,
  tableDialogLoadIndicator,
  lockTableDialog as tableDialogLock,
  pmeTableDialogOpen as tableDialogOpen,
  pmeTextareaInputSelector as textareaInputSelector,
  pmeTriggerSubmit as triggerSubmit,
};
