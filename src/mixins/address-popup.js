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

import { appName } from '../app/app-info';

export default {
  methods: {
    addressItemUnknownLabel(item) {
      return t(appName, '{item}: unknown', { item: t(appName, item) });
    },
    musicianAddressPopup(option) {
      const name = option.informalDisplayName || '';
      const userId = option.userIdSlug ? ` (${option.userIdSlug})` : '';
      const email = option.email || this.addressItemUnknownLabel('email');
      const street = option.street || this.addressItemUnknownLabel('street');
      const streetNumber = option.streetNumber ? ' ' + option.streetNumber : '';
      const postalCode = option.postalCode && option.postalCode !== '0' ? option.postalCode + ' ' : '';
      const city = option.city || this.addressItemUnknownLabel('city');
      const content = `<h4>${name}${userId}</h4>`
            + [email, street + streetNumber, postalCode + city, `${option.countryName} (${option.country})`].join('<br/>');
      return this.addressPopup(content);
    },
    contactAddressPopup(option) {
      const name = option.name;
      // const book = option.addressBookName || '';
      let emails = [];
      if (option.EMAIL) {
        for (const email of option.EMAIL) {
          emails.push(`${email.value || email}`);
        }
      }
      emails = emails.join('<br/>');
      let address = [];
      if (option.ADR && option.ADR.length > 0) {
        address = (option.ADR[0].value || option.ADR[0]).split(';');
      }
      const street = address[2] || this.addressItemUnknownLabel('street');
      const postalCode = (address[5] + ' ') || '';
      const city = address[3] || this.addressItemUnknownLabel('city');
      const country = address[6] || this.addressItemUnknownLabel('country');
      const content = `<h4>${name}</h4>`
            + [emails, street, postalCode + city, country].join('<br/>');
      return this.addressPopup(content);
    },
    addressPopup(content) {
      return {
        content,
        // placement: 'bottom',
        preventOverflow: false,
        boundariesElement: 'viewport',
        html: true,
        classes: ['vue-tooltip-address-popup'],
      };
    },
  },
};
