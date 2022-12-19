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

import { defineStore } from 'pinia';

import { appName as appId } from '../app/config.js';
import { set as vueSet } from 'vue';
import '@nextcloud/dialogs/styles/toast.scss';
import { generateUrl } from '@nextcloud/router';
import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs';
import axios from '@nextcloud/axios';

export const useMemberDataStore = defineStore('participant-data', {
  state: () => {
    return {
      musicians: [],
      initialized: {
        loaded: false,
      },
    };
  },
  actions: {
    async initialize() {
      if (!this.initialized.loaded) {
        this.$reset();
        try {
          const response = await axios.get(generateUrl('/apps/' + appId + '/member'));
          for (const [key, value] of Object.entries(response.data)) {
            vueSet(this, key, value);
          }
          this.initialized.loaded = true;
        } catch (e) {
          console.error('ERROR', e);
          let message = t(appId, 'reason unknown');
          if (e.response && e.response.data && e.response.data.message) {
            message = e.response.data.message;
          }
          showError(t(appId, 'Could not fetch musician(s): {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT });
        }
      }
    },
    async load() {
      this.$reset();
      await this.initialize();
    },
  },
});
