/**
 * @copyright Copyright (c) 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 *
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
 */

import moment from '@nextcloud/moment';
import { getCanonicalLocale } from '@nextcloud/l10n';

export default {
  methods: {
    formatDate(date, flavour) {
      if (+date === parseInt(date, 10)) {
        if (date < 315529200000) { // 1980 in milliseconds+
          date *= 1000;
        }
        date = new Date(date);
      }
      flavour = flavour || 'medium';
      switch (flavour) {
      case 'short':
      case 'medium':
      case 'long':
        return moment(date).format('L');
      case 'omit-year': {
        const event = new Date(date);
        const options = { month: 'short', day: 'numeric' };
        return event.toLocaleString(getCanonicalLocale(), options);
      }
      }
      return moment(date).format(flavour);
    },
  },
};
