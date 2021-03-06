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

import Calendar from './legacy/calendar/calendar.js';
import './legacy/calendar/on-event.js';
import './legacy/calendar/jquery.multi-autocomplete.js';
import 'legacy/calendar/jquery.ui.timepicker.js';

jQuery = require('jquery');
require('jquery-ui');

//window.$ = jQuery;
//window.jQuery = jQuery;

console.info('JQUERY', window.jQuery.widget);

require('legacy/calendar/jquery.ui.timepicker.css');

require('jquery-ui-multiselect-widget');
require('jquery-ui-multiselect-widget/css/jquery.multiselect.css');

export { Calendar };

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
