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

import jQuery from './app/jquery.js';
import Calendar from './legacy/calendar/calendar.js';
import './legacy/calendar/on-event.js';
import './legacy/calendar/jquery.multi-autocomplete.js';
import './legacy/calendar/jquery.ui.timepicker.js';

require('jquery-ui');

console.info('JQUERY', jQuery.widget, window.jQuery.widget);

require('legacy/calendar/jquery.ui.timepicker.css');

require('jquery-ui-multiselect-widget');
require('jquery-ui-multiselect-widget/css/jquery.multiselect.css');

export { Calendar };

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
