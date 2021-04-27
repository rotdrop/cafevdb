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
 * Collect some jQuery tweaks in this file.
 *
 */
import { $, appName } from './globals.js';
import generateId from './generate-id.js';

$('body').on('change', 'input[type="checkbox"].' + appName + '-lock-input-widget', function(event) {
  const $self = $(this);
  if ($self.hasClass('disabled')) {
    return false;
  }
  const $input = $($self.data('input'));
  $input.prop('readonly', $self.prop('checked'));
  return false;
});

$.fn.lockUnlock = function(argument) {
  if (arguments.length === 0 || (arguments.length === 1 && typeof argument === 'object' && argument !== null)) {
    argument = argument || {};
    const options = $.extend({}, {
      position: 'top',
      locked: false,
      hardLocked: false,
      cssClass: undefined,
    }, argument);
    options.locked = options.locked || options.hardLocked;
    let cssClass = appName + '-lock-input-widget' + ' lock-unlock' + ' checkbox';
    if (options.cssClass) {
      cssClass += ' ' + cssClass;
    }
    if (options.position) {
      cssClass += ' ' + options.position + '-padlock';
    }
    const locked = options.locked;
    const disabled = options.hardLocked;
    $(this).each(function(index) {
      const id = generateId();
      $(this).prop('readonly', locked)
        .after(
          '<input'
            + ' type="checkbox"'
            + ' class="' + cssClass + '"'
            + ' id="' + id + '"'
            + (locked ? ' checked' : '')
            + (disabled ? ' disabled' : '')
            + '/>'
            + '<label'
            + ' for="' + id + '"'
            + ' id="' + id + '-label"'
            + ' class="' + cssClass + '"'
            + ' title="' + t(appName, 'Lock or unlock this widget. Under certain circumstances the unlock-functionality is disabled or only available in expert mode.') + '"'
            + '>'
            + '</label>'
        )
        .data(appName + 'LockUnlockId', id);
      $('#' + id)
        .data('input', this)
        .data('options', options);
    });
  } else {
    const $self = $(this);
    const id = $self.data(appName + 'LockUnlockId');
    const command = arguments[0];
    switch (arguments[0]) {
    case 'disable': {
      const parameter = arguments.length === 1 ? true : !!arguments[1];
      if (parameter) {
        $('#' + id).addClass('disabled');
      } else {
        $('#' + id).removeClass('disabled');
      }
      break;
    }
    case 'enable': {
      const parameter = arguments.length === 1 ? false : !!arguments[1];
      if (parameter) {
        $('#' + id).addClass('disabled');
      } else {
        $('#' + id).removeClass('disabled');
      }
      break;
    }
    case 'lock': {
      if (arguments.length !== 2) {
        throw new Error(t(appName, '{command} expects an argument, but none was specified', { command }));
      }
      const parameter = !!arguments[1];
      $('#' + id).prop('checked', parameter).trigger('change');
      break;
    }
    case 'hardlock': {
      if (arguments.length !== 2) {
        throw new Error(t(appName, '{command} expects an argument, but none was specified', { command }));
      }
      const parameter = !!arguments[1];
      // don't trigger change, we just hard-lock the controls.
      $self.prop('readonly', parameter);
      $('#' + id).prop('disabled', parameter);
      break;
    }
    case 'destroy':
      $self.removeData(appName + 'LockUnlockId');
      $('#' + id).remove();
      $('#' + id + '-label').remove();
      return this;
    case 'checkbox':
      return $('#' + id);
    case 'label':
      return $('#' + id + '-label');
    case 'options':
      return $('#' + id).data('options');
    }
  }
  return this;
};

// Local Variables: ***
// indent-tabs-mode: nil ***
// js-indent-level: 2 ***
// End: ***
