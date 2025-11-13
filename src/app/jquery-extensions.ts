/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * Collect some jQuery tweaks in this file.
 *
 */

import { appNameTag, appPrefix } from './config.js';
import $ from './jquery.ts';
import * as CAFEVDB from './cafevdb.js';
import './jquery-cafevdb-tooltips.ts';

require('jquery-ui/ui/widgets/dialog');
require('jquery-ui/ui/widgets/resizable');

console.log('jquery-extensions');

/**
 * We leave it to the z-index-plane to disallow interaction. Every
 * input element above any modal dialog is allowed to interact with
 * the user.
 */
$.widget('ui.dialog', $.ui.dialog, {
  _allowInteraction() {
    return true;
  },
});

$.fn.extend({
  elements() {
    return Object.entries(this).filter(([key, _value]) => !isNaN(parseInt(key)));
  },
});

/**
 * Special dialog version which attaches the dialog to the
 * #content-wrapper div.
 *
 * @param argument TBD.
 *
 * @param rest TBD.
 */
$.fn.extend({
  cafevDialog(this: JQuery, first?: null|string|Record<string, unknown>, second?: string, third?: string) {
    if (!second && !third && typeof first === 'object' && first !== null) {
      console.debug('CAFEVDB DIALOG OPEN', { first, second, third });
      const options = {
        appendTo: '#' + appPrefix('general'),
        // appendTop: 'body',
        classes: {
          'ui-dialog': [
            'ui-corner-all',
            appNameTag,
          ].join(' '),
        },
      };
      const parameters = $.extend(true, {}, options, first);
      // dialogClass is gone ...
      if (parameters.dialogClass) {
        parameters.classes['ui-dialog'] += ' ' + parameters.dialogClass;
      }
      if ($('#appsettings_popup').length === 0) {
        CAFEVDB.snapperClose();
      }
      console.debug('will open dialog');
      // @ts-expect-error 2554
      $.fn.dialog.call(this, parameters);
      if (this.dialog('option', 'draggable')) {
        console.debug('Try to set containment');
        // @ts-expect-error 2554
        $.fn.dialog.call(this, 'widget').draggable('option', 'containment', '#app-content, #app-content-vue');
      }
    } else {
      console.debug('CAFEVDB DIALOG FORWARD', { first, second, third });
      // @ts-expect-error 2322
      return $.fn.dialog.apply(this, [first, second, third]);
    }
    return this;
  },
});

/** Determine whether we have a vertical scrollbar. */
$.fn.extend({
  hasVerticalScrollbar(this: JQuery) {
    const node = this.get(0);
    return !!node && node.scrollHeight > node.clientHeight + 1;
  },
});
