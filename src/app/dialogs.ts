/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2023, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * @file Short popup dialogs. ATM just a wrapper around the old legacy
 * OC dialogs.
 */

import type { IDialogButton, IFilePickerButton } from '@nextcloud/dialogs';
import type { INode } from '@nextcloud/files';

import { DialogBuilder, getFilePickerBuilder } from '@nextcloud/dialogs';
import { FileType } from '@nextcloud/files';
import { translate as t } from '@nextcloud/l10n';
import { basename } from 'path';
import { appName } from '../config.ts';
import { hasProperty } from '../toolkit/types/type-traits.ts';
import $ from './jquery.js';

import { appNameTag } from 'variables.scss';

require('dialogs.scss');

export const YES_NO_BUTTONS = 70;
export const OK_BUTTONS = 71;
export type LegacyButtonType = typeof YES_NO_BUTTONS | typeof OK_BUTTONS;

export const DIALOG_TYPE_ALERT = 'alert';
export const DIALOG_TYPE_NOTICE = 'notice';
export const DIALOG_TYPE_INFO = 'info';
export type LegacyDialogType = typeof DIALOG_TYPE_ALERT | typeof DIALOG_TYPE_INFO | typeof DIALOG_TYPE_NOTICE;

interface LegacyCallback { (result: boolean): void; _clicked?: boolean }
const isLegacyCallback = (arg: unknown): arg is LegacyCallback => typeof arg === 'function';
interface LegacyButtonInfo {
  confirm?: string;
  cancel?: string;
  type: LegacyButtonType;
}
const isLegacyButtonInfo = (buttons: unknown): buttons is LegacyButtonInfo => !!buttons && typeof buttons === 'object';

const getLegacyButtons = (buttons: LegacyButtonType|LegacyButtonInfo|undefined, callback: LegacyCallback) => {
  const buttonList: IDialogButton[] = [];

  const isButtonInfo = isLegacyButtonInfo(buttons);
  switch (isButtonInfo ? buttons.type : buttons) {
    case YES_NO_BUTTONS:
      buttonList.push({
        label: (buttons as undefined|LegacyButtonInfo)?.cancel ?? t('core', 'No'),
        callback: () => {
          callback._clicked = true;
          callback(false);
        },
      });
      buttonList.push({
        label: (buttons as undefined|LegacyButtonInfo)?.confirm ?? t('core', 'Yes'),
        variant: 'primary',
        callback: () => {
          callback._clicked = true;
          callback(true);
        },
      });
      break;
    case OK_BUTTONS:
      buttonList.push({
        label: (buttons as undefined|LegacyButtonInfo)?.confirm ?? t('core', 'OK'),
        variant: 'primary',
        callback: () => {
          callback._clicked = true;
          callback(true);
        },
      });
      break;
    default:
      console.error('Invalid button definition', { buttons });
      break;
  }
  return buttonList;
};

export interface LegacyMessageParameters {
  content: string;
  title: string;
  dialogType: LegacyDialogType;
  buttons?: LegacyButtonType|LegacyButtonInfo;
  callback: LegacyCallback;
  modal: boolean;
  allowHtml: boolean;
  dialogClasses?: string|string[];
}
export type LegacyDialogParameters = Omit<LegacyMessageParameters, 'dialogType'>;
const isLegacyMessageParameters = <T extends Partial<LegacyDialogParameters> = LegacyMessageParameters>(arg: unknown): arg is T =>
  (typeof arg !== 'string') && (arg as Record<string, unknown>).content !== undefined;

const message = ({
  content,
  title,
  dialogType,
  buttons,
  callback = () => {},
  modal,
  allowHtml,
  dialogClasses,
}: LegacyMessageParameters) => {

  console.info('MESSAGE', { content, title, dialogType, buttons, callback, modal, allowHtml, dialogClasses });

  const cssClasses = [appNameTag, 'legacy-dialog', dialogType];
  if (dialogClasses) {
    if (Array.isArray(dialogClasses)) {
      cssClasses.splice(0, 0, ...dialogClasses);
    } else {
      cssClasses.push(dialogClasses);
    }
  }

  const builder = (new DialogBuilder())
    .setName(title)
    .setText(allowHtml ? '' : content)
    .setButtons(getLegacyButtons(buttons, callback))
    .setDialogClasses(cssClasses);

  switch (dialogType) {
    case DIALOG_TYPE_ALERT:
      builder.setSeverity('warning');
      break;
    case DIALOG_TYPE_NOTICE:
      builder.setSeverity('info');
      break;
    default:
      break;
  }

  const dialog = builder.build();

  if (allowHtml) {
    dialog.setHTML(content);
  }

  const promise = dialog.show()
    .then(() => {
      if (!callback._clicked) {
        callback(false);
      }
    })
    .catch((error) => {
      if (error instanceof Error && error.message === 'Dialog closed') {
        callback(false);
      } else {
        throw error;
      }
    });

  $('body').find('.legacy-dialog.' + appNameTag)
    .closest('.modal-container')
    .addClass(cssClasses);

  return promise;
};

type AlertInfoOptions = Omit<LegacyDialogParameters, 'callback'|'buttons'|'modal'|'allowHtml'> & Partial<LegacyDialogParameters>;

const alertInfoNotice = (dialogType: LegacyDialogType, content: string|AlertInfoOptions, title?: string, callback?: LegacyCallback, modal?: boolean, allowHtml?: boolean) => {
  const defaultArg = {
    dialogType,
    buttons: OK_BUTTONS,
    callback: () => {},
    modal: false,
    allowHtml: false,
  } as Omit<LegacyMessageParameters, 'title'|'content'>;
  return message(
    isLegacyMessageParameters<AlertInfoOptions>(content)
      ? { ...defaultArg, ...content }
      : {
        ...defaultArg,
        content,
        title: title as string,
        callback: callback ?? function(_arg: boolean) {},
        modal: modal || false,
        allowHtml: allowHtml || false,
      },
  );
};

const alert = (content: string|AlertInfoOptions, title?: string, callback?: LegacyCallback, modal?: boolean, allowHtml?: boolean) =>
  alertInfoNotice(DIALOG_TYPE_ALERT, content, title, callback, modal, allowHtml);

const info = (content: string|AlertInfoOptions, title?: string, callback?: LegacyCallback, modal?: boolean, allowHtml?: boolean) =>
  alertInfoNotice(DIALOG_TYPE_INFO, content, title, callback, modal, allowHtml);

const notice = (content: string|AlertInfoOptions, title?: string, callback?: LegacyCallback, modal?: boolean, allowHtml?: boolean) =>
  alertInfoNotice(DIALOG_TYPE_NOTICE, content, title, callback, modal, allowHtml);

/**
 * Confirm Callback.
 *
 * @param {boolean} decision TBD.
 */

export const CONFIRM_DEFAULT_CANCEL = 'cancel';
export const CONFIRM_DEFAULT_CONFIRM = 'confirm';
export type ConfirmDefault = typeof CONFIRM_DEFAULT_CANCEL | typeof CONFIRM_DEFAULT_CONFIRM;
export type LegacyConfirmOptions = Partial<LegacyMessageParameters> & { default?: ConfirmDefault };

/**
 * @param content TBD.
 * @param title TBD.
 * @param options TBD.
 * @param modal TBD.
 * @param allowHtml TBD.
 */
const confirm = (content: string, title: string, options?: LegacyCallback|LegacyConfirmOptions, modal?: boolean, allowHtml?: boolean) => {
  const defaultOptions = {
    callback() {},
    modal: false,
    allowHtml: false,
    default: CONFIRM_DEFAULT_CONFIRM,
  } as Required<Pick<LegacyConfirmOptions, 'callback'|'modal'|'allowHtml'|'default'>>;
  if (isLegacyCallback(options)) {
    options = {
      callback: options,
      modal,
      allowHtml,
    };
  }

  const parameters: typeof defaultOptions & Omit<LegacyConfirmOptions, keyof typeof defaultOptions> = { ...defaultOptions, ...options };

  if (!parameters.buttons || parameters.buttons === YES_NO_BUTTONS) {
    parameters.buttons = {
      type: YES_NO_BUTTONS,
    };
  }

  console.debug('CONFIRM CONTENT', { content, title, parameters, modal, allowHtml });

  return new Promise<boolean>((resolve, _reject) =>
    message({
      content,
      title,
      dialogType: 'notice',
      buttons: parameters.buttons,
      callback: (answer) => {
        parameters.callback(answer);
        // do not reject, as this triggers an exception
        resolve(answer);
      },
      modal: parameters.modal,
      allowHtml: parameters.allowHtml,
      dialogClasses: parameters.dialogClasses,
    }).then(() => {
      $('body').find('.oc-dialog-buttonrow.twobuttons').each(function() {
        const $buttonRow = $(this);
        const $confirmButton = $buttonRow.find('button.primary.' + appName);
        if (parameters.default === CONFIRM_DEFAULT_CANCEL) {
          const $cancelButton = $buttonRow.find('button:not(.primary)');
          $confirmButton.removeClass('primary');
          $cancelButton.addClass('primary');
        }
      });
    }));
};

/**
 * Popup a dialog with debug info if data.data.debug is set and non
 * empty.
 *
 * @param data TBD.
 *
 * @param callback TBD.
 */
const debugPopup = (data: unknown, callback?: () => void) => {
  if (hasProperty('debug', data)) {
    if (typeof callback !== 'function') {
      callback = () => {};
    }
    info({
      title: t(appName, 'Debug Information'),
      content: '<div class="debug error contents">' + data.debug + '</div>',
      callback,
      modal: true,
      allowHtml: true,
    });
  }
};

export interface LegacyFilePickerParameters {
  title: string;
  callback: (paths: string|string[]) => void;
  multiple?: boolean;
  modal?: boolean; // unused
  allowDirectories?: boolean;
  startPath?: string;
}

const getNodePath = (node: INode) => {
  const root = node?.root || '';
  let path = node?.path || '';
  // TODO: Fix this in @nextcloud/files
  if (path.startsWith(root)) {
    path = path.slice(root.length) || '/';
  }
  return path;
};

const filePicker = (options: LegacyFilePickerParameters) => {
  const builder = getFilePickerBuilder(options.title);

  return builder.setButtonFactory((nodes, path) => {
    const [node] = nodes;
    const target = node?.displayname || node?.basename || basename(path);

    const isDirectory = nodes.length === 0
      || nodes.reduce((result: boolean, node: INode) => result || node.type === FileType.Folder, false);
    const result: IFilePickerButton[] = [{
      callback: options.multiple
        ? (nodes) => options.callback(nodes.map(getNodePath))
        : (nodes) => options.callback(getNodePath(nodes[0])),
      label: node && !options.multiple ? t('core', 'Choose {file}', { file: target }) : t('core', 'Choose'),
      variant: 'primary' as const,
      disabled: !options.allowDirectories && isDirectory,
    }];
    console.info('BUTTON FACTORY', { result, nodes, path, isDirectory, options });
    return result;
  })
    .allowDirectories(options.allowDirectories === true)
    .setMultiSelect(options.multiple === true)
    .startAt(options.startPath || '')
    .build()
    .pick();
};

const attachDialogHandlers = () => {

  const $container = $('body');

  if ($container.data(appName + 'DialogHandlersAttached')) {
    return;
  }

  $container.data(appName + 'DialogHandlersAttached', true);

  const dialogContainerSelector = '.modal-container.legacy-dialog.' + appNameTag;

  $container
    .off('dblclick', dialogContainerSelector + ' .dialog__name')
    .on('dblclick', dialogContainerSelector + ' .dialog__name', function(event) {
      $(this).closest(dialogContainerSelector).toggleClass('maximize-width');
      event.stopImmediatePropagation();
      return false;
    });

  $container
    .off('dblclick', dialogContainerSelector + ' .exception.error')
    .on('dblclick', dialogContainerSelector + ' .exception.error', function(event) {
      $(this).closest(dialogContainerSelector).find('.trace').toggleClass('visible');
      event.stopImmediatePropagation();
      return false;
    });
};

export {
  alert,
  attachDialogHandlers,
  confirm,
  debugPopup,
  filePicker,
  info,
  notice,
};
