/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import { refreshWidget as refreshSelectWidget } from './select-utils.js';

$.fn.readonly = function(state) {
  if (state === undefined) {
    state = true;
  } else {
    state = !!state;
  }
  this.each(function() {
    const $this = $(this);
    $this.prop('readonly', state);
    if ($this.is('select')) {
      // single-select can be handled like radio buttons
      if (!$this.prop('multiple')) {
        $this.find('option').each(function() {
          const $option = $(this);
          if (!state) {
            const restoreDisabled = $option.data('readonlyRestoreDisabled');
            if (restoreDisabled !== undefined) {
              $option.prop('disabled', restoreDisabled);
            }
          } else {
            $option.data('readonlyRestoreDisabled', $option.prop('disabled'));
            $option.prop('disabled', !$option.prop('selected'));
          }
        });
      } else {
        let name = $this.attr('name');
        if (!name.endsWith('[]')) {
          name += '[]';
        }
        $this.find('option').each(function() {
          const $option = $(this);
          let placeholder = $option.data('readonlyPlaceholder');
          if (!placeholder) {
            placeholder = $('<input type="hidden" name="' + name + '"/>');
            $this.before(placeholder);
            $option.data('readonlyPlaceholder', placeholder);
          }
          placeholder.attr('value', $option.attr('value') || $option.text());
          placeholder.prop('disabled', !state || !$option.prop('selected'));
          if (!state) {
            const restoreDisabled = $option.data('readonlyRestoreDisabled');
            if (restoreDisabled !== undefined) {
              $option.prop('disabled', restoreDisabled);
            }
          } else {
            $option.data('readonlyRestoreDisabled', $option.prop('disabled'));
            $option.prop('disabled', true);
          }
        });
      }
      refreshSelectWidget($this);
    } else if ($this.is(':radio')) {
      let $container = $this.closest('fieldset');
      if (!$container) {
        $container = $this.closest('form');
      }
      if (!$container) {
        $container = $('body');
      }
      const $radioGroup = $container.find('input:radio[name="' + $this.attr('name') + '"]');
      $radioGroup.prop('readonly', state);
      $radioGroup.each(function() {
        const $radio = $(this);
        if (!state) {
          const restoreDisabled = $radio.data('readonlyRestoreDisabled');
          if (restoreDisabled !== undefined) {
            $radio.prop('disabled', restoreDisabled);
          }
        } else {
          $radio.data('readonlyRestoreDisabled', $radio.prop('disabled'));
          $radio.prop('disabled', !$radio.prop('checked'));
        }
      });
    } else if ($this.is(':checkbox')) {
      let placeholder = $this.data('readonlyPlaceholder');
      if (!placeholder) {
        placeholder = $('<input type="hidden" name="' + $this.attr('name') + '"/>');
        $this.before(placeholder);
        $this.data('readonlyPlaceholder', placeholder);
      }
      placeholder.attr('value', $this.attr('value') || 'on');
      placeholder.prop('disabled', !state || !$this.prop('checked'));
      $this.prop('disabled', state);
    }
  });
  return this;
};
