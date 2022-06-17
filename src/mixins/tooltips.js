/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from '../app/app-info.js';
import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router';

export default {
  methods: {
    async tooltip(key) {
      try {
        const response = await axios.get(generateUrl('apps/' + appName + '/tooltips/{key}', { key }), { params: { unescaped: true } });
        console.debug('GOT TOOLTIP', response.data.tooltip || '');
        return response.data.tooltip;
      } catch (e) {
        console.error('ERROR FETCHING TOOLTIP ' + key, e);
        return '';
      }
    },
    async tooltips(keys) {
      try {
        const response = await axios.get(generateUrl('apps/' + appName + '/tooltips'), {
          params: {
            unescaped: true,
            keys,
          },
        });
        console.debug('GOT TOOLTIPS', response.data);
        return response.data;
      } catch (e) {
        console.error('ERROR FETCHING TOOLTIPS', e, keys);
        return {};
      }
    },
  },
};
