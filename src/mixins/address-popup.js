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
      if (!option.id === 0) {
        return this.addressPopup(t(appName, 'selects all musicians'));
      }
      const name = option.informalDisplayName || '';
      const userId = option.userIdSlug ? ` (${option.userIdSlug})` : '';
      const email = option.email || this.addressItemUnknownLabel('email');
      const street = option.street || this.addressItemUnknownLabel('street');
      const streetNumber = option.streetNumber ? ' ' + option.streetNumber : '';
      const postalCode = option.postalCode && option.postalCode !== '0' ? option.postalCode + ' ' : '';
      const city = option.city || this.addressItemUnknownLabel('city');
      const additionalInfo = [email, street + streetNumber, postalCode + city];
      if (option.countryName) {
        let country = option.countryName;
        if (option.country) {
          country += ` (${option.country})`;
        }
        additionalInfo.push(country);
      }
      const content = `<h4>${name}${userId}</h4>` + additionalInfo.join('<br/>');
      return this.addressPopup(content);
    },
    contactNameFromContact(option) {
      let name = option.name.value || option.name;
      if (typeof name !== 'string') {
        name = t(appName, '[empty name]');
      }
      return name;
    },
    contactAddressPopup(option) {
      const name = this.contactNameFromContact(option);
      const additionalInfo = [];
      // const book = option.addressBookName || '';
      let emails = [];
      if (option.EMAIL) {
        for (const email of option.EMAIL) {
          const emailValue = email.value || email;
          if (typeof emailValue === 'string') {
            emails.push(`${emailValue}`);
          }
        }
      }
      emails = emails.join('<br/>');
      if (emails) {
        additionalInfo.push(emails);
      }
      let address = [];
      if (option.ADR && option.ADR.length > 0) {
        address = (option.ADR[0].value || option.ADR[0]).split(';');
      }
      const street = address[2] || this.addressItemUnknownLabel('street');
      const postalCode = (address[5] + ' ') || '';
      const city = address[3] || this.addressItemUnknownLabel('city');
      const country = address[6] || this.addressItemUnknownLabel('country');
      additionalInfo.splice(additionalInfo.length, 0, street, postalCode + city, country);
      const content = `<h4>${name}</h4>`
            + additionalInfo.join('<br/>');
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
