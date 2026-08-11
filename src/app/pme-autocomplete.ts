/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';

const pmeAutocomplete = function($input: JQuery<HTMLInputElement>) {
  const autocompleteData: string[] = $input.data('autocomplete');
  if (autocompleteData) {
    $input
      .autocomplete({
        source: autocompleteData.map((x) => String(x)),
        minLength: 0,
        open(event, _ui) {
          const $input = $(event.target);
          const $results = $input.autocomplete('widget');
          // The following would place the list above the input
          // const top = $results.position().top;
          // const height = $results.outerHeight();
          // const inputHeight = $input.outerHeight();
          // const newTop = top - height - inputHeight;

          // $results.css('top', newTop + 'px');
          const $parent = $results.parent();
          $results.data('savedOverflow', $parent.css('overflow'));
          $parent.css('overflow', 'visible');
        },
        close(event, _ui) {
          const $input = $(event.target);
          const $results = $input.autocomplete('widget');
          const $parent = $results.parent();
          $parent.css('overflow', $results.data('savedOverflow'));
          $results.removeData('savedOverflow');
        },
        select(event, ui) {
          const $input = $(event.target);
          $input.val(ui.item.value);
          $input.trigger('blur');
        },
      })
      .on('focus, click', function() {
        const $this = $(this);
        if (!$this.autocomplete('widget').is(':visible')) {
          $this.autocomplete('search', $this.val()! as string);
        }
      });
  }
};

export default pmeAutocomplete;
