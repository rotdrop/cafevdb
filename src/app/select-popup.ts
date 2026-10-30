/**
 * @file
 *
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *
 * @module
 */

import type { SelectizeAdapterOptions } from '../services/mountable-select.ts';

import { translate as t } from '@nextcloud/l10n';
import { appName } from '../config.ts';
import { getMountedSelectize } from '../services/mountable-select.ts';
import { destroyMountedSelects, mountedSelectScope } from '../services/mounted-select-scope.ts';
import { selectedOptionData } from '../services/select-options.ts';
import { toolTipsInit } from './cafevdb.ts';
import $ from './jquery.ts';
import { show as notificationShow } from './notification.ts';

import './jquery-extensions.ts';

interface SelectedOptionDTO {
  value: string;
  html: string;
  text: string;
}

interface Options {
  title: string;
  position: JQueryUI.JQueryPositionOptions;
  dialogClass?: string;
  saveText: string;
  saveTitle: string;
  cancelText: string;
  cancelTitle: string;
  buttons: JQueryUI.DialogButtonOptions[]; // additional buttons.
  openCallback?: (this: HTMLElement, $select: JQuery<HTMLSelectElement>) => void;
  saveCallback?: (this: HTMLElement, $select: JQuery<HTMLSelectElement>, selected: SelectedOptionDTO[]) => void;
  closeCallback?: (this: HTMLElement, $select: JQuery<HTMLSelectElement>) => void;
  select: SelectizeAdapterOptions;
}

/*
 * jQuery dialog popup with one multi-select widget in it.
 *
 * @param string The HTML content to show.
 */
const selectPopup = function(contents: string, userOptions: Partial<Options>) {
  const defaultOptions: Options = {
    title: t(appName, 'Choose some Options'),
    position: {
      my: 'center center',
      at: 'center center',
      of: window,
    },
    dialogClass: undefined,
    saveText: t(appName, 'Save'),
    saveTitle: t(
      appName,
      'Accept the currently selected options and return to the underlying form. ',
    ),
    cancelText: t(appName, 'Cancel'),
    cancelTitle: t(
      appName,
      'Discard the current selection and close the dialog. '
        + 'The initial set of selected options will remain unchanged.',
    ),
    buttons: [], // additional buttons.
    openCallback: undefined,
    saveCallback: undefined,
    closeCallback: undefined,
    select: {
      closeAfterSelect: true,
    },
  };
  const options: Options = {
    ...defaultOptions,
    ...userOptions,
  };

  const cssClass = (options.dialogClass ? options.dialogClass + ' ' : '') + 'select-popup-dialog';
  const dialogHolder = $('<div class="' + cssClass + '"></div>');
  dialogHolder.html(contents);
  const selectElement = dialogHolder.find<HTMLSelectElement>('select');
  $('body').append(dialogHolder);
  const scope = mountedSelectScope(dialogHolder[0]);
  let closed = false;
  let ready = false;

  let buttons: JQueryUI.DialogButtonOptions[] = [
    {
      text: options.saveText,
      // icon: { primary: 'ui-icon-check' },
      class: 'save',
      title: options.saveTitle,
      click(this: HTMLElement) {
        if (!ready) {
          return false;
        }
        const selectedOptions: SelectedOptionDTO[] = [];
        selectElement.each(function() {
          const value = getMountedSelectize(this)?.getValue();
          selectedOptions.push(...selectedOptionData(this, Array.isArray(value) ? value : value === null || value === undefined ? [] : [value]));
        });
        // alert('selected: ' + JSON.stringify(selectedOptions));
        if (typeof options.saveCallback === 'function') {
          options.saveCallback.call(this, selectElement, selectedOptions);
        }

        return false;
      },
    },
    {
      text: options.cancelText,
      class: 'cancel',
      title: options.cancelTitle,
      click() {
        $(this).dialog('close');
      },
    },
  ];
  buttons = buttons.concat(options.buttons);

  dialogHolder.cafevDialog({
    title: options.title,
    position: options.position,
    dialogClass: cssClass,
    modal: true,
    draggable: true,
    closeOnEscape: false,
    width: 'auto',
    height: 'auto',
    resizable: false,
    buttons,
    open(this: HTMLElement) {
      const dialogWidget = dialogHolder.dialog('widget');
      const save = dialogWidget.find('.ui-dialog-buttonset .ui-button.save');
      save.prop('disabled', true);
      void Promise.all(selectElement.toArray().map((select) => scope.mount(select, options.select)))
        .then(() => {
          if (closed) {
            return;
          }
          ready = true;
          save.prop('disabled', false);
          toolTipsInit(dialogWidget);
          dialogHolder.on('dblclick', '.pme-select', function(event) {
            if ($(event.target).closest('button').length === 0) {
              save.trigger('click');
              return false;
            }
          });
          options.openCallback?.call(this, selectElement);
        })
        .catch((error: unknown) => {
          console.error('Unable to mount popup select controls.', error);
          notificationShow(t(appName, 'Unable to initialize the select controls.'), { timeout: 15 });
          if (!closed) {
            dialogHolder.dialog('close');
          }
        });
    },
    close(this: HTMLElement) {
      closed = true;
      if (typeof options.closeCallback === 'function') {
        options.closeCallback.call(this, selectElement);
      }

      $.fn.cafevTooltip.remove();
      destroyMountedSelects(dialogHolder[0]);
      dialogHolder.dialog('destroy').remove();
    },
  });
};

export default selectPopup;
